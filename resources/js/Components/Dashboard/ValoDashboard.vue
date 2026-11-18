<script setup lang="ts">
import { computed, type ComputedRef, inject, ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useMediaQuery } from '@vueuse/core';
import { CheckIcon, PlusIcon } from '@heroicons/vue/20/solid';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import TimeTracker from '@/Components/TimeTracker.vue';
import MobileTimer from '@/Components/MobileTimer.vue';
import MobileManualEntry from '@/Components/MobileManualEntry.vue';
import TeamActivityCard from '@/Components/Dashboard/TeamActivityCard.vue';
import ValoMeetingConfirm from '@/Components/ValoMeetingConfirm.vue';
import ValoRoutineSheet from '@/Components/ValoRoutineSheet.vue';
import TimeEntryGroupedTable from '@/packages/ui/src/TimeEntry/TimeEntryGroupedTable.vue';
import TimeEntryEditModal from '@/packages/ui/src/TimeEntry/TimeEntryEditModal.vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Client,
    CreateClientBody,
    CreateProjectBody,
    CreateTimeEntryBody,
    Organization,
    Project,
    TimeEntry,
    UpdateMultipleTimeEntriesChangeset,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { useReportingStore } from '@/utils/useReporting';
import { type MeetingOccurrence, useMeetingOccurrences } from '@/utils/useValoMeetings';
import {
    getInitialWeekRange,
    useTimeEntriesCalendarQuery,
} from '@/utils/useTimeEntriesCalendarQuery';
import { useTimeEntriesMutations } from '@/utils/useTimeEntriesMutations';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { groupSimilarTimeEntriesSetting } from '@/utils/timeEntryGrouping';
import { getCurrentMembershipId, getCurrentUser } from '@/utils/useUser';
import { getOrganizationCurrencyString } from '@/utils/money';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { canCreateProjects, canViewMembers } from '@/utils/permissions';
import {
    formatHumanReadableDuration,
    getDayJsInstance,
    getLocalizedDateFromTimestamp,
    getLocalizedDayJs,
    localDateToUtc,
} from '@/packages/ui/src/utils/time';

// VALO's Time home ("Panel"): the timer, how today and this week are going, the meetings waiting for an
// OK and, below, the entries of the day picked in "Mi semana" to check or fix them. It replaces "Tiempo".
const organization = inject<ComputedRef<Organization>>('organization');
const queryClient = useQueryClient();
const isPhone = useMediaQuery('(max-width: 639px)');
const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { clients } = useClientsQuery();
const { tags } = useTagsQuery();
const { getNameForReportingRowEntry } = useReportingStore();

function refresh() {
    for (const queryKey of [
        'latestTeamActivity',
        'timeEntries',
        'aggregatedTimeEntries',
        'valoMeetings',
    ]) {
        queryClient.invalidateQueries({ queryKey: [queryKey] });
    }
}

// "Buenos días / Buenas tardes / Buenas noches" with the hour in Spain
function spanishHour(): number {
    return Number(
        new Intl.DateTimeFormat('en-GB', {
            hour: 'numeric',
            hour12: false,
            timeZone: 'Europe/Madrid',
        }).format(new Date())
    );
}
const firstName = computed(() => getCurrentUser().name.trim().split(/\s+/)[0] ?? '');
const greeting = computed(() => {
    const hour = spanishHour();
    if (hour >= 6 && hour < 14) {
        return 'Good morning, :name';
    }
    if (hour >= 14 && hour < 21) {
        return 'Good afternoon, :name';
    }
    return 'Good evening, :name';
});

const weekStart = getInitialWeekRange().start;
const todayKey = getLocalizedDayJs().format('YYYY-MM-DD');
const todayText = getDayJsInstance()(todayKey).format('dddd D [de] MMMM');
const todayLabel = todayText.charAt(0).toUpperCase() + todayText.slice(1);

const mine = computed<AggregatedTimeEntriesQueryParams>(() => ({
    start: localDateToUtc(weekStart),
    end: localDateToUtc(weekStart.add(7, 'day').subtract(1, 'second')),
    type: 'work',
    member_id: getCurrentMembershipId(),
}));
const dayParams = computed<AggregatedTimeEntriesQueryParams>(() => ({
    ...mine.value,
    group: 'day',
}));
const projectParams = computed<AggregatedTimeEntriesQueryParams>(() => ({
    ...mine.value,
    group: 'project',
}));
const { data: dayResponse } = useAggregatedTimeEntriesQuery('valo-dashboard-days', dayParams);
const { data: projectResponse } = useAggregatedTimeEntriesQuery(
    'valo-dashboard-projects',
    projectParams
);
const byDay = computed(() => dayResponse.value?.data as AggregatedTimeEntries | undefined);
const byProject = computed(() => projectResponse.value?.data as AggregatedTimeEntries | undefined);

const days = computed(() => {
    const rows = Array.from({ length: 7 }, (_, index) => {
        const date = weekStart.add(index, 'day');
        const dayKey = date.format('YYYY-MM-DD');
        return {
            key: dayKey,
            label: date.format('ddd D'),
            short: date.format('dd D'),
            isToday: dayKey === todayKey,
            seconds: byDay.value?.grouped_data?.find((entry) => entry.key === dayKey)?.seconds ?? 0,
        };
    });
    const max = Math.max(1, ...rows.map((row) => row.seconds));
    return rows.map((row) => ({
        ...row,
        percent: row.seconds > 0 ? Math.max(3, (row.seconds / max) * 100) : 1,
    }));
});
const weekSeconds = computed(() => byDay.value?.seconds ?? 0);
const todaySeconds = computed(() => days.value.find((row) => row.isToday)?.seconds ?? 0);
const daysWithHours = computed(() => days.value.filter((row) => row.seconds > 0).length);

const projectRows = computed(() =>
    (byProject.value?.grouped_data ?? [])
        .filter((entry) => entry.seconds > 0)
        .map((entry) => ({
            key: entry.key ?? 'none',
            name: getNameForReportingRowEntry(entry.key, 'project') ?? '',
            color: projects.value.find((project) => project.id === entry.key)?.color ?? '#4F7CAC',
            seconds: entry.seconds,
            percent: (entry.seconds / Math.max(1, weekSeconds.value)) * 100,
        }))
        .sort((a, b) => b.seconds - a.seconds)
);

// meetings and routines of this week (up to today) that still wait for "OK, asistí"
const meetingRange = computed(() => ({ start: weekStart.format('YYYY-MM-DD'), end: todayKey }));
const { occurrences } = useMeetingOccurrences(meetingRange);
const pendingMeetings = computed(() =>
    occurrences.value.filter((item) => item.time_entry_id === null && item.date <= todayKey)
);
const showConfirm = ref(false);
const confirming = ref<MeetingOccurrence | null>(null);
function confirm(occurrence: MeetingOccurrence) {
    confirming.value = occurrence;
    showConfirm.value = true;
}
function meetingDay(occurrence: MeetingOccurrence): string {
    return getDayJsInstance()(occurrence.date).format('ddd D');
}
const routineOpen = ref(false);
const manualOpen = ref(false);

// ---- the entries of the picked day (today unless another bar is clicked)
const selectedDay = ref(todayKey);
const selectedDayLabel = computed(() => {
    if (selectedDay.value === todayKey) {
        return 'Today';
    }
    const text = getDayJsInstance()(selectedDay.value).format('dddd D MMM');
    return text.charAt(0).toUpperCase() + text.slice(1);
});
const selectedDaySeconds = computed(
    () => days.value.find((row) => row.key === selectedDay.value)?.seconds ?? 0
);
const weekQueryStart = computed(() => weekStart);
const weekQueryEnd = computed(() => weekStart.add(7, 'day'));
const { data: weekEntriesResponse } = useTimeEntriesCalendarQuery(weekQueryStart, weekQueryEnd);
const dayEntries = computed<TimeEntry[]>(() =>
    (weekEntriesResponse.value?.data ?? [])
        .filter((entry) => getLocalizedDateFromTimestamp(entry.start) === selectedDay.value)
        .filter((entry) => entry.end !== null)
        .sort((a, b) => b.start.localeCompare(a.start))
);

const {
    createTimeEntry: createTimeEntryMutation,
    updateTimeEntry,
    updateTimeEntries: updateTimeEntriesMutation,
    deleteTimeEntries,
    deleteTimeEntry,
} = useTimeEntriesMutations();
const currentTimeEntryStore = useCurrentTimeEntryStore();
async function startTimeEntry(timeEntry: Omit<CreateTimeEntryBody, 'member_id'>) {
    if (currentTimeEntryStore.currentTimeEntry.id) {
        await currentTimeEntryStore.setActiveState(false);
    }
    await createTimeEntryMutation(timeEntry);
    currentTimeEntryStore.fetchCurrentTimeEntry();
}
async function updateTimeEntries(ids: string[], changes: UpdateMultipleTimeEntriesChangeset) {
    await updateTimeEntriesMutation({ ids, changes });
}
const selectedTimeEntries = ref<TimeEntry[]>([]);
async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}
async function createProject(project: CreateProjectBody): Promise<Project | undefined> {
    return await useProjectsStore().createProject(project);
}
async function createClient(body: CreateClientBody): Promise<Client | undefined> {
    return await useClientsStore().createClient(body);
}
const phoneEdit = ref<TimeEntry | null>(null);
const showPhoneEdit = ref(false);
function editOnPhone(entry: TimeEntry) {
    phoneEdit.value = entry;
    showPhoneEdit.value = true;
}
async function updateOnPhone(entry: TimeEntry) {
    await updateTimeEntry(entry);
}
async function deleteOnPhone(id: string) {
    await deleteTimeEntry(id);
}
function projectOf(entry: TimeEntry) {
    return projects.value.find((project) => project.id === entry.project_id);
}
function entryClock(entry: TimeEntry): string {
    const minutes = Math.round(
        getLocalizedDayJs(entry.end as string).diff(getLocalizedDayJs(entry.start), 'minute')
    );
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
}

function formatDuration(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        organization?.value?.interval_format,
        organization?.value?.number_format
    );
}
</script>

<template>
    <MainContainer class="pt-5 sm:pt-7 flex flex-wrap items-baseline gap-x-3">
        <h1 class="text-3xl font-semibold text-text-primary" data-testid="dashboard_greeting">
            {{ $t(greeting, { name: firstName }) }}
        </h1>
        <span class="text-text-secondary">{{ todayLabel }}</span>
    </MainContainer>
    <MainContainer class="pt-4 pb-4">
        <MobileTimer v-if="isPhone" class="relative z-20"></MobileTimer>
        <div
            v-else
            class="valo-timer-hero relative z-20 rounded border border-card-border bg-card-background px-5 lg:px-7 pt-4 pb-5 shadow-card">
            <TimeTracker hero @change="refresh"></TimeTracker>
        </div>
    </MainContainer>

    <MainContainer class="pb-28 sm:pb-10 valo-report">
        <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-[1fr_1fr_1.7fr]">
            <div class="valo-gcard valo-kpi" data-testid="dashboard_today">
                <div class="valo-kpi-label">{{ $t('Today') }}</div>
                <div class="valo-kpi-value">{{ formatDuration(todaySeconds) }}</div>
                <div class="valo-kpi-note">
                    {{ todaySeconds > 0 ? $t('tracked today') : $t('nothing tracked yet today') }}
                </div>
            </div>
            <div class="valo-gcard valo-kpi" data-testid="dashboard_week">
                <div class="valo-kpi-label">{{ $t('This week') }}</div>
                <div class="valo-kpi-value">{{ formatDuration(weekSeconds) }}</div>
                <div class="valo-kpi-note">
                    {{
                        daysWithHours === 1
                            ? $t('1 day with entries')
                            : $t(':count days with entries', { count: String(daysWithHours) })
                    }}
                </div>
            </div>
            <section
                class="valo-gcard valo-kpi valo-dash-meetings col-span-2 lg:col-span-1"
                data-testid="dashboard_meetings">
                <div class="flex items-center justify-between gap-3">
                    <div class="valo-kpi-label">{{ $t('Meetings to confirm') }}</div>
                    <button
                        type="button"
                        class="valo-routine-btn"
                        data-testid="dashboard_create_routine"
                        @click="routineOpen = true">
                        <PlusIcon class="h-4 w-4" />{{ $t('Create routine') }}
                    </button>
                </div>
                <div class="mt-1.5">
                    <div
                        v-for="meeting in pendingMeetings"
                        :key="meeting.meeting_id + meeting.date"
                        class="valo-meet-row">
                        <span class="min-w-0">
                            <b class="block truncate text-[15px] font-semibold">{{
                                meeting.name
                            }}</b>
                            <small class="block text-[13px] capitalize text-text-secondary">
                                {{ meeting.date === todayKey ? $t('Today') : meetingDay(meeting) }}
                                · {{ meeting.start_time }} – {{ meeting.end_time }}
                            </small>
                        </span>
                        <button
                            type="button"
                            class="valo-ok-btn"
                            data-testid="dashboard_meeting_ok"
                            @click="confirm(meeting)">
                            <CheckIcon class="h-4 w-4" />{{ $t('OK, I attended') }}
                        </button>
                    </div>
                    <p v-if="pendingMeetings.length === 0" class="valo-kpi-note pt-2">
                        {{ $t('All set: no meetings waiting for your OK.') }}
                    </p>
                </div>
            </section>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
            <section class="valo-gcard valo-rcard" data-testid="dashboard_my_week">
                <h2>
                    {{ $t('My week') }} <span>{{ $t('click a day to see its entries') }}</span>
                </h2>
                <div class="valo-bars valo-bars-pick">
                    <button
                        v-for="bar in days"
                        :key="bar.key"
                        type="button"
                        :class="{ picked: bar.key === selectedDay }"
                        :style="{ height: bar.percent + '%', opacity: bar.seconds > 0 ? 1 : 0.35 }"
                        :aria-label="bar.label"
                        :data-testid="'dashboard_day_' + bar.key"
                        @click="selectedDay = bar.key">
                        <span v-if="bar.seconds > 0">{{ formatDuration(bar.seconds) }}</span>
                    </button>
                </div>
                <div class="valo-bar-labels">
                    <button
                        v-for="bar in days"
                        :key="bar.key"
                        type="button"
                        class="capitalize"
                        :class="{ 'valo-accent-text font-semibold': bar.key === selectedDay }"
                        @click="selectedDay = bar.key">
                        {{ bar.isToday ? $t('Today') : isPhone ? bar.short : bar.label }}
                    </button>
                </div>
            </section>

            <section class="valo-gcard valo-rcard" data-testid="dashboard_projects">
                <h2>
                    {{ $t('My time by project') }} <span>{{ $t('this week') }}</span>
                </h2>
                <template v-if="projectRows.length > 0">
                    <div class="mt-4 flex h-3.5 gap-[3px] overflow-hidden rounded-full">
                        <span
                            v-for="row in projectRows"
                            :key="row.key"
                            :style="{ width: row.percent + '%', background: row.color }"></span>
                    </div>
                    <div
                        v-for="row in projectRows"
                        :key="row.key"
                        class="mt-3 flex items-center gap-2.5 text-[15px]">
                        <i class="valo-dot" :style="{ background: row.color }"></i>
                        <span class="min-w-0 flex-1 truncate font-medium">{{ row.name }}</span>
                        <b class="font-semibold tabular-nums">{{ formatDuration(row.seconds) }}</b>
                        <span class="w-10 text-right text-sm text-text-secondary"
                            >{{ Math.round(row.percent) }}%</span
                        >
                    </div>
                </template>
                <div v-else class="valo-kpi-note mt-4">{{ $t('No hours this week yet.') }}</div>
            </section>
        </div>

        <!-- the entries of the picked day -->
        <section class="mt-4" data-testid="dashboard_day_entries">
            <div class="mb-2 flex items-baseline justify-between px-1">
                <h2 class="text-lg font-semibold text-text-primary">
                    {{ selectedDayLabel === 'Today' ? $t('Today') : selectedDayLabel }}
                    <span class="ml-1 text-sm font-normal text-text-secondary">{{
                        $t('your entries')
                    }}</span>
                </h2>
                <span class="font-semibold tabular-nums text-text-primary">{{
                    formatDuration(selectedDaySeconds)
                }}</span>
            </div>
            <template v-if="dayEntries.length > 0">
                <div
                    v-if="isPhone"
                    class="overflow-hidden rounded border border-card-border bg-card-background shadow-card">
                    <button
                        v-for="entry in dayEntries"
                        :key="entry.id"
                        type="button"
                        class="flex w-full items-center gap-3 border-t border-card-background-separator px-4 py-3 text-left first:border-t-0"
                        data-testid="dashboard_entry_phone"
                        @click="editOnPhone(entry)">
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :style="{
                                backgroundColor: projectOf(entry)?.color ?? '#4F7CAC',
                            }"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-text-primary">{{
                                entry.description || $t('No description')
                            }}</span>
                            <span class="block truncate text-xs text-text-tertiary"
                                >{{ getLocalizedDayJs(entry.start).format('HH:mm') }} –
                                {{ getLocalizedDayJs(entry.end as string).format('HH:mm') }} ·
                                {{ projectOf(entry)?.name ?? $t('No project') }}</span
                            >
                        </span>
                        <span
                            class="shrink-0 text-sm font-semibold tabular-nums text-text-primary"
                            >{{ entryClock(entry) }}</span
                        >
                    </button>
                </div>
                <TimeEntryGroupedTable
                    v-else
                    v-model:selected="selectedTimeEntries"
                    class="valo-dash-table"
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
                    :time-entries="dayEntries"
                    :group-similar-time-entries="groupSimilarTimeEntriesSetting"
                    :tags="tags"></TimeEntryGroupedTable>
            </template>
            <p
                v-else
                class="valo-gcard rounded px-4 py-6 text-center text-sm text-text-secondary"
                data-testid="dashboard_day_empty">
                {{ $t('No entries on this day.') }}
            </p>
        </section>

        <div v-if="canViewMembers()" class="mt-4">
            <TeamActivityCard></TeamActivityCard>
        </div>
    </MainContainer>

    <ValoMeetingConfirm v-model:show="showConfirm" :occurrence="confirming"></ValoMeetingConfirm>
    <ValoRoutineSheet v-model:show="routineOpen"></ValoRoutineSheet>
    <template v-if="isPhone">
        <button
            type="button"
            class="valo-mobile-add fixed bottom-6 right-4 z-30 flex items-center gap-2 rounded-full bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white shadow-dropdown dark:bg-white dark:text-slate-900"
            data-testid="dashboard_add_hours"
            @click="manualOpen = true">
            <PlusIcon class="h-5 w-5" />
            {{ $t('Add hours') }}
        </button>
        <MobileManualEntry v-model:show="manualOpen"></MobileManualEntry>
        <TimeEntryEditModal
            v-model:show="showPhoneEdit"
            :time-entry="phoneEdit"
            :enable-estimated-time="isAllowedToPerformPremiumAction()"
            :update-time-entry="updateOnPhone"
            :delete-time-entry="deleteOnPhone"
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
</template>
