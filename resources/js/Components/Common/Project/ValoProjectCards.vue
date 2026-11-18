<script setup lang="ts">
import { computed, type ComputedRef, inject, ref } from 'vue';
import { FolderPlusIcon } from '@heroicons/vue/24/outline';
import { ArchiveBoxIcon, PlusIcon } from '@heroicons/vue/16/solid';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import ProjectMoreOptionsDropdown from '@/Components/Common/Project/ProjectMoreOptionsDropdown.vue';
import ProjectEditModal from '@/Components/Common/Project/ProjectEditModal.vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
    Project,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useProjectsStore } from '@/utils/useProjects';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { getCurrentMembershipId, getCurrentRole } from '@/utils/useUser';
import { formatHumanReadableDuration, localDateToUtc } from '@/packages/ui/src/utils/time';
import { canCreateProjects } from '@/utils/permissions';
import { useValoLongEntries } from '@/utils/useValoLongEntries';

// VALO's Time: projects as cards with the hours of THIS WEEK only (not the total since the beginning).
const props = withDefaults(
    defineProps<{
        projects: Project[];
        isFiltered?: boolean;
        isLoading?: boolean;
    }>(),
    { isFiltered: false, isLoading: false }
);
const emit = defineEmits<{ create: [] }>();

const organization = inject<ComputedRef<Organization>>('organization');
const { tasks } = useTasksQuery();

const weekStart = getInitialWeekRange().start;
const weekLabel = `${weekStart.format('ddd D')} – ${weekStart.add(6, 'day').format('ddd D MMM')}`;
const params = computed<AggregatedTimeEntriesQueryParams>(() => ({
    start: localDateToUtc(weekStart),
    end: localDateToUtc(weekStart.add(7, 'day').subtract(1, 'second')),
    group: 'project',
    sub_group: 'user',
    type: 'work',
    member_id: getCurrentRole() === 'employee' ? getCurrentMembershipId() : undefined,
}));
const { data } = useAggregatedTimeEntriesQuery('valo-projects-week', params);
const range = computed(() => ({
    start: weekStart.format('YYYY-MM-DD'),
    end: weekStart.add(6, 'day').format('YYYY-MM-DD'),
}));
const { longEntries } = useValoLongEntries(range);

const week = computed(() => {
    const result = new Map<string, { seconds: number; people: number }>();
    const grouped = (data.value?.data as AggregatedTimeEntries | undefined)?.grouped_data ?? [];
    for (const entry of grouped) {
        if (!entry.key) {
            continue;
        }
        // forgotten timers (more than 16 h in a row) do not count
        const long = longEntries.value
            .filter((item) => item.project_id === entry.key)
            .reduce((sum, item) => sum + item.seconds, 0);
        const seconds = entry.seconds - long;
        result.set(entry.key, {
            seconds: seconds < 300 ? 0 : seconds,
            people: (entry.grouped_data ?? []).filter((sub) => sub.seconds > 0).length,
        });
    }
    return result;
});

const cards = computed(() =>
    props.projects
        .map((project) => ({
            project,
            seconds: week.value.get(project.id)?.seconds ?? 0,
            people: week.value.get(project.id)?.people ?? 0,
            tasks: tasks.value.filter((task) => task.project_id === project.id).length,
        }))
        .sort(
            (a, b) =>
                Number(a.project.is_archived) - Number(b.project.is_archived) ||
                b.seconds - a.seconds ||
                a.project.name.localeCompare(b.project.name)
        )
);

function formatDuration(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        organization?.value?.interval_format,
        organization?.value?.number_format
    );
}
function meta(card: { seconds: number; people: number; tasks: number }): string {
    const parts: string[] = [];
    if (card.seconds > 0) {
        parts.push(
            card.people === 1
                ? trans('1 person this week')
                : trans(':count people this week', { count: String(card.people) })
        );
    }
    if (card.tasks > 0) {
        parts.push(
            card.tasks === 1
                ? trans('1 task')
                : trans(':count tasks', { count: String(card.tasks) })
        );
    }
    return parts.join(' · ');
}

const editing = ref<Project | null>(null);
const showEdit = computed({
    get: () => editing.value !== null,
    set: (value: boolean) => {
        if (!value) {
            editing.value = null;
        }
    },
});
function archive(project: Project) {
    useProjectsStore().updateProject(project.id, { ...project, is_archived: !project.is_archived });
}
function remove(project: Project) {
    useProjectsStore().deleteProject(project.id);
}
</script>

<template>
    <ProjectEditModal
        v-if="editing"
        v-model:show="showEdit"
        :original-project="editing"></ProjectEditModal>
    <p class="mb-3 pl-1 text-sm text-text-secondary" data-testid="projects_week_note">
        {{ $t('Hours of this week only') }} ({{ weekLabel }}).
        {{ $t('For other periods use Reporting.') }}
    </p>
    <div v-if="isLoading" class="flex justify-center items-center py-24">
        <LoadingSpinner></LoadingSpinner>
    </div>
    <div v-else-if="cards.length === 0" class="valo-gcard py-20 text-center">
        <FolderPlusIcon class="w-8 text-icon-default inline pb-2"></FolderPlusIcon>
        <h3 class="text-text-primary font-semibold">
            {{ isFiltered ? $t('No projects found') : $t('No projects yet') }}
        </h3>
        <SecondaryButton
            v-if="!isFiltered && canCreateProjects()"
            class="mt-4"
            :icon="PlusIcon"
            @click="emit('create')"
            >{{ $t('Create your First Project') }}
        </SecondaryButton>
    </div>
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-testid="project_table">
        <article
            v-for="card in cards"
            :key="card.project.id"
            class="valo-gcard valo-pcard"
            :class="{ 'opacity-60': card.project.is_archived }"
            data-testid="project_card">
            <div class="flex items-center justify-between gap-2">
                <Link
                    :href="route('projects.show', { project: card.project.id })"
                    class="flex min-w-0 items-center gap-2.5 text-lg font-semibold text-text-primary hover:underline">
                    <span
                        class="valo-dot"
                        style="width: 14px; height: 14px; border-radius: 5px"
                        :style="{ background: card.project.color }"></span>
                    <span class="truncate">{{ card.project.name }}</span>
                </Link>
                <div class="flex shrink-0 items-center gap-2">
                    <span
                        v-if="card.project.is_archived"
                        class="flex items-center gap-1 text-xs font-semibold text-text-secondary">
                        <ArchiveBoxIcon class="h-3.5 w-3.5" />{{ $t('Archived') }}
                    </span>
                    <ProjectMoreOptionsDropdown
                        :project="card.project"
                        @edit="editing = card.project"
                        @archive="archive(card.project)"
                        @delete="remove(card.project)"></ProjectMoreOptionsDropdown>
                </div>
            </div>
            <div class="valo-kpi-label mt-4">{{ $t('This week') }}</div>
            <div class="valo-pcard-hours" :class="{ zero: card.seconds === 0 }">
                {{ card.seconds > 0 ? formatDuration(card.seconds) : $t('No hours yet') }}
            </div>
            <div class="mt-1 min-h-5 text-sm text-text-secondary">{{ meta(card) }}</div>
        </article>
    </div>
</template>
