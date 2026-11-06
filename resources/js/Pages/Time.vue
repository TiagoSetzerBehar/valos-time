<script setup lang="ts">
import TimeWeekSummary from '@/Components/TimeWeekSummary.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TimeTracker from '@/Components/TimeTracker.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import { storeToRefs } from 'pinia';
import type {
    CreateClientBody,
    CreateProjectBody,
    CreateTimeEntryBody,
    Project,
    TimeEntry,
    Client,
} from '@/packages/api/src';
import { useElementVisibility, useMediaQuery } from '@vueuse/core';
import { ClockIcon } from '@heroicons/vue/20/solid';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { groupSimilarTimeEntriesSetting } from '@/utils/timeEntryGrouping';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import TimeEntryGroupedTable from '@/packages/ui/src/TimeEntry/TimeEntryGroupedTable.vue';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { getOrganizationCurrencyString } from '@/utils/money';
import TimeEntryMassActionRow from '@/packages/ui/src/TimeEntry/TimeEntryMassActionRow.vue';
import type { UpdateMultipleTimeEntriesChangeset } from '@/packages/api/src';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { canCreateProjects } from '@/utils/permissions';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { useTimeEntriesInfiniteQuery } from '@/utils/useTimeEntriesInfiniteQuery';
import { useTimeEntriesMutations } from '@/utils/useTimeEntriesMutations';
import { getLocalizedDateFromTimestamp } from '@/packages/ui/src/utils/time';
import MobileTimeView from '@/Components/MobileTimeView.vue';
import TimeEntryEditModal from '@/packages/ui/src/TimeEntry/TimeEntryEditModal.vue';

const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isPending } =
    useTimeEntriesInfiniteQuery();
const {
    createTimeEntry: createTimeEntryMutation,
    updateTimeEntry,
    updateTimeEntries: updateTimeEntriesMutation,
    deleteTimeEntries: deleteTimeEntriesMutation,
    deleteTimeEntry,
} = useTimeEntriesMutations();

// VALO's Time: on a phone the page is a simple view (timer, today/week, list, add hours).
const isPhone = useMediaQuery('(max-width: 639px)');
const phoneTracker = ref<InstanceType<typeof TimeTracker> | null>(null);
const phoneEditEntry = ref<TimeEntry | null>(null);
const showPhoneEdit = ref(false);

function editOnPhone(timeEntry: TimeEntry) {
    phoneEditEntry.value = timeEntry;
    showPhoneEdit.value = true;
}

async function updateTimeEntryOnPhone(timeEntry: TimeEntry) {
    await updateTimeEntry(timeEntry);
}

async function deleteTimeEntryOnPhone(timeEntryId: string) {
    await deleteTimeEntry(timeEntryId);
}

const timeEntries = computed(() => data.value?.pages.flatMap((page) => page.data) || []);

// Day picked in the week strip: the list shows only that day (null = every day).
const selectedDay = ref<string | null>(null);
const visibleTimeEntries = computed(() =>
    selectedDay.value === null
        ? timeEntries.value
        : timeEntries.value.filter(
              (entry) => getLocalizedDateFromTimestamp(entry.start) === selectedDay.value
          )
);

async function updateTimeEntries(ids: string[], changes: UpdateMultipleTimeEntriesChangeset) {
    await updateTimeEntriesMutation({ ids, changes });
}

const loadMoreContainer = ref<HTMLDivElement | null>(null);
const isLoadMoreVisible = useElementVisibility(loadMoreContainer);
const currentTimeEntryStore = useCurrentTimeEntryStore();
const { currentTimeEntry } = storeToRefs(currentTimeEntryStore);
const { setActiveState } = currentTimeEntryStore;

async function startTimeEntry(timeEntry: Omit<CreateTimeEntryBody, 'member_id'>) {
    if (currentTimeEntry.value.id) {
        await setActiveState(false);
    }
    await createTimeEntryMutation(timeEntry);
    useCurrentTimeEntryStore().fetchCurrentTimeEntry();
}

async function deleteTimeEntries(timeEntries: TimeEntry[]) {
    await deleteTimeEntriesMutation(timeEntries);
}

watch(isLoadMoreVisible, async (isVisible) => {
    if (isVisible && hasNextPage.value) {
        await fetchNextPage();
    }
});

const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { clients } = useClientsQuery();

const { tags } = useTagsQuery();

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}
async function createProject(project: CreateProjectBody): Promise<Project | undefined> {
    return await useProjectsStore().createProject(project);
}
async function createClient(body: CreateClientBody): Promise<Client | undefined> {
    return await useClientsStore().createClient(body);
}

const { organization } = useOrganizationQuery(getCurrentOrganizationId()!);

const selectedTimeEntries = ref([] as TimeEntry[]);

async function clearSelectionAndState() {
    selectedTimeEntries.value = [];
}

function deleteSelected() {
    deleteTimeEntries(selectedTimeEntries.value);
    selectedTimeEntries.value = [];
}

// SPA-navigate the calendar to a break's day so its placement can be fixed there.
function goToCalendarDay(date: string) {
    router.visit(`/calendar?date=${date}`);
}
</script>

<template>
    <AppLayout :title="$t('Time')" data-testid="time_view">
        <template v-if="isPhone">
            <div class="px-4 pt-4 pb-4">
                <div
                    class="valo-mobile-timer relative z-20 rounded border border-card-border bg-card-background px-3 py-2 shadow-card">
                    <TimeTracker ref="phoneTracker"></TimeTracker>
                </div>
            </div>
            <MobileTimeView
                :time-entries="timeEntries"
                :projects="projects"
                @edit="editOnPhone"
                @manual="phoneTracker?.openManualEntry()"></MobileTimeView>
            <TimeEntryEditModal
                v-model:show="showPhoneEdit"
                :time-entry="phoneEditEntry"
                :enable-estimated-time="isAllowedToPerformPremiumAction()"
                :update-time-entry="updateTimeEntryOnPhone"
                :delete-time-entry="deleteTimeEntryOnPhone"
                :create-client
                :create-project
                :create-tag
                :tags
                :projects
                :tasks
                :clients
                :currency="getOrganizationCurrencyString()"
                :organization-billable-rate="organization?.billable_rate ?? null"
                :can-create-project="canCreateProjects()"></TimeEntryEditModal>
        </template>
        <template v-else>
            <MainContainer class="pt-5 lg:pt-8 pb-4 lg:pb-6">
                <div
                    class="relative z-20 rounded border border-card-border bg-card-background px-2 sm:px-3 py-1.5 shadow-card">
                    <TimeTracker></TimeTracker>
                </div>
            </MainContainer>
            <TimeWeekSummary v-model:day="selectedDay"></TimeWeekSummary>
            <div class="valo-mass-slot min-h-8">
                <TimeEntryMassActionRow
                    v-if="selectedTimeEntries.length > 0"
                    :selected-time-entries="selectedTimeEntries"
                    :enable-estimated-time="isAllowedToPerformPremiumAction()"
                    :can-create-project="canCreateProjects()"
                    :all-selected="selectedTimeEntries.length === timeEntries.length"
                    :delete-selected="deleteSelected"
                    :projects="projects"
                    :tasks="tasks"
                    :tags="tags"
                    :currency="getOrganizationCurrencyString()"
                    :clients="clients"
                    :organization-billable-rate="organization?.billable_rate ?? null"
                    class="border-t border-default-background-separator hidden sm:block"
                    :update-time-entries="
                        (args) =>
                            updateTimeEntries(
                                selectedTimeEntries.map((timeEntry) => timeEntry.id),
                                args
                            )
                    "
                    :create-project="createProject"
                    :create-client="createClient"
                    :create-tag="createTag"
                    @submit="clearSelectionAndState"
                    @select-all="selectedTimeEntries = [...timeEntries]"
                    @unselect-all="selectedTimeEntries = []"></TimeEntryMassActionRow>
            </div>
            <TimeEntryGroupedTable
                v-model:selected="selectedTimeEntries"
                :create-project
                :enable-estimated-time="isAllowedToPerformPremiumAction()"
                :can-create-project="canCreateProjects()"
                :organization-billable-rate="organization?.billable_rate ?? null"
                :clients
                :create-client
                :update-time-entry
                :update-time-entries
                :delete-time-entries
                :create-time-entry="startTimeEntry"
                :create-tag
                :projects="projects"
                :tasks="tasks"
                :currency="getOrganizationCurrencyString()"
                :time-entries="visibleTimeEntries"
                :group-similar-time-entries="groupSimilarTimeEntriesSetting"
                :fix-in-calendar="goToCalendarDay"
                :tags="tags"></TimeEntryGroupedTable>
            <div v-if="isPending" class="flex justify-center items-center py-12">
                <LoadingSpinner></LoadingSpinner>
            </div>
            <div
                v-else-if="selectedDay !== null && visibleTimeEntries.length === 0"
                class="text-center pt-8 text-text-tertiary"
                data-testid="time_day_empty">
                {{ $t('No time entries on this day.') }}
            </div>
            <div v-else-if="timeEntries.length === 0" class="text-center pt-12">
                <ClockIcon class="w-8 text-icon-default inline pb-2"></ClockIcon>
                <h3 class="text-text-primary font-semibold">
                    {{ $t('Start tracking your time!') }}
                </h3>
                <p class="pb-5 max-w-md mx-auto">
                    {{
                        $t(
                            'Write above what you are working on and press start. Already finished? Add it with Manual.'
                        )
                    }}
                </p>
            </div>
        </template>
        <div v-show="selectedDay === null" ref="loadMoreContainer">
            <div
                v-if="isFetchingNextPage"
                class="flex justify-center items-center py-5 text-sm text-text-primary font-medium">
                <LoadingSpinner></LoadingSpinner>
                <span> {{ $t('Loading more time entries...') }} </span>
            </div>
            <div
                v-else-if="!hasNextPage && timeEntries.length > 0"
                class="flex justify-center items-center py-5 text-sm text-text-tertiary">
                {{ $t('All time entries are loaded!') }}
            </div>
        </div>
    </AppLayout>
</template>
