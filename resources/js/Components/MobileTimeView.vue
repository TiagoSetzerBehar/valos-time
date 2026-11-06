<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChartBarIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import MobileTimer from '@/Components/MobileTimer.vue';
import MobileManualEntry from '@/Components/MobileManualEntry.vue';
import { trans } from 'laravel-vue-i18n';
import type { Project, TimeEntry } from '@/packages/api/src';
import {
    formatWeekday,
    getDayJsInstance,
    getLocalizedDateFromTimestamp,
    getLocalizedDayJs,
    localDateToUtc,
} from '@/packages/ui/src/utils/time';
import { NO_PROJECT_COLOR } from '@/packages/ui/src/FullCalendar/useCalendarEvents';
import { useWeekSummary } from '@/utils/useWeekSummary';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { getCurrentMembershipId } from '@/utils/useUser';
import type { AggregatedTimeEntries, AggregatedTimeEntriesQueryParams } from '@/packages/api/src';

// VALO's Time on the phone: timer, a simple list of the latest entries (tap to edit) and a button
// to add hours by hand. The today/week summary per project opens from the top-left button.
const props = defineProps<{
    timeEntries: TimeEntry[];
    projects: Project[];
}>();

const emit = defineEmits<{
    edit: [timeEntry: TimeEntry];
}>();

const { weekTotal, todayTotal, formatDuration } = useWeekSummary();

const projectsById = computed(
    () => new Map(props.projects.map((project) => [project.id, project]))
);

// Summary: how much time went to each project, today or this week.
const summaryOpen = ref(false);
const manualOpen = ref(false);

function hoursMinutes(seconds: number): string {
    return `${Math.floor(seconds / 3600)}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}`;
}
const period = ref<'today' | 'week'>('today');
const periods = computed(() => [
    { value: 'today' as const, label: trans('Today') },
    { value: 'week' as const, label: trans('Week') },
]);

const week = getInitialWeekRange();
const startOfToday = getDayJsInstance()().startOf('day');

function projectParams(start: typeof week.start, end: typeof week.start) {
    return computed<AggregatedTimeEntriesQueryParams>(() => ({
        start: localDateToUtc(start),
        end: localDateToUtc(end),
        member_id: getCurrentMembershipId(),
        type: 'work',
        group: 'project',
    }));
}

const { data: todayData } = useAggregatedTimeEntriesQuery(
    'mobile-today-projects',
    projectParams(startOfToday, startOfToday.endOf('day'))
);
const { data: weekData } = useAggregatedTimeEntriesQuery(
    'mobile-week-projects',
    projectParams(week.start, week.end.subtract(1, 'second'))
);

const byProject = computed(() => {
    const data = (period.value === 'today' ? todayData.value : weekData.value)?.data as
        AggregatedTimeEntries | undefined;
    const groups = (data?.grouped_data ?? []).filter((group) => group.seconds > 0);
    const max = Math.max(1, ...groups.map((group) => group.seconds));
    return groups
        .map((group) => {
            const project = group.key ? projectsById.value.get(group.key) : undefined;
            return {
                key: group.key ?? 'none',
                name: project?.name ?? trans('No project'),
                color: project?.color ?? NO_PROJECT_COLOR,
                seconds: group.seconds,
                duration: formatDuration(group.seconds),
                percent: Math.round((group.seconds / max) * 100),
            };
        })
        .sort((a, b) => b.seconds - a.seconds);
});

function dayLabel(date: string): string {
    const today = getLocalizedDayJs();
    if (date === today.format('YYYY-MM-DD')) {
        return trans('Today');
    }
    if (date === today.subtract(1, 'day').format('YYYY-MM-DD')) {
        return trans('Yesterday');
    }
    const weekday = formatWeekday(date);
    return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${getDayJsInstance()(date).format('D/M')}`;
}

const days = computed(() => {
    const groups = new Map<string, TimeEntry[]>();
    for (const entry of props.timeEntries) {
        // the running entry is shown in the timer
        if (entry.end === null) {
            continue;
        }
        const key = getLocalizedDateFromTimestamp(entry.start);
        groups.set(key, [...(groups.get(key) ?? []), entry]);
    }
    return Array.from(groups, ([key, entries]) => ({
        key,
        label: dayLabel(key),
        total: formatDuration(entries.reduce((sum, entry) => sum + (entry.duration ?? 0), 0)),
        entries: entries.map((entry) => {
            const project = entry.project_id ? projectsById.value.get(entry.project_id) : undefined;
            return {
                entry,
                description: entry.description || trans('No description'),
                project: project?.name ?? trans('No project'),
                color: entry.type === 'break' ? '#F59E0B' : (project?.color ?? NO_PROJECT_COLOR),
                duration: hoursMinutes(entry.duration ?? 0),
            };
        }),
    }));
});
</script>

<template>
    <div class="valo-mobile px-4 pt-3 pb-28" data-testid="mobile_time_view">
        <button
            type="button"
            class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-card-border bg-card-background px-3.5 py-2 text-sm font-medium text-text-primary shadow-card"
            data-testid="mobile_summary_button"
            @click="summaryOpen = true">
            <ChartBarIcon class="h-4 w-4 valo-accent-text" />
            {{ $t('Summary') }}
        </button>

        <MobileTimer class="relative z-20"></MobileTimer>

        <div
            v-if="days.length > 0"
            class="mt-4 rounded border border-card-border bg-card-background shadow-card overflow-hidden"
            data-testid="mobile_entries">
            <template v-for="(day, index) in days" :key="day.key">
                <div
                    class="flex items-center justify-between px-4 pb-1.5 text-sm"
                    :class="index === 0 ? 'pt-3.5' : 'pt-5'">
                    <span class="font-semibold text-text-primary">{{ day.label }}</span>
                    <span class="font-semibold tabular-nums text-text-primary">{{
                        day.total
                    }}</span>
                </div>
                <button
                    v-for="item in day.entries"
                    :key="item.entry.id"
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-card-background-separator px-4 py-3 text-left first-of-type:border-t-0"
                    @click="emit('edit', item.entry)">
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: item.color }"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm text-text-primary">{{
                            item.description
                        }}</span>
                        <span class="block truncate text-xs text-text-tertiary">{{
                            item.project
                        }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold tabular-nums text-text-primary">{{
                        item.duration
                    }}</span>
                </button>
            </template>
        </div>

        <p v-else class="pt-8 text-center text-sm text-text-tertiary">
            {{ $t('Press play to start the timer, or add your hours with the button below.') }}
        </p>

        <button
            type="button"
            class="valo-mobile-add fixed bottom-6 right-4 z-30 flex items-center gap-2 rounded-full bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white shadow-dropdown dark:bg-white dark:text-slate-900"
            data-testid="mobile_add_hours"
            @click="manualOpen = true">
            <PlusIcon class="h-5 w-5" />
            {{ $t('Add hours') }}
        </button>

        <MobileManualEntry v-model:show="manualOpen"></MobileManualEntry>

        <Teleport to="body">
            <div
                v-if="summaryOpen"
                class="fixed inset-0 z-50 flex items-end bg-black/30"
                data-testid="mobile_summary"
                @click.self="summaryOpen = false">
                <div
                    class="valo-floating-sheet w-full border border-card-border bg-default-background p-5 pb-10 shadow-dropdown"
                    style="border-radius: 1.5rem 1.5rem 0 0">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-text-primary">
                            {{ $t('Summary') }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-text-tertiary"
                            :aria-label="$t('Close')"
                            @click="summaryOpen = false">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="flex rounded-full bg-[var(--valo-bar)] p-1 text-sm">
                            <button
                                v-for="option in periods"
                                :key="option.value"
                                type="button"
                                class="rounded-full px-3.5 py-1 font-medium transition"
                                :class="
                                    period === option.value
                                        ? 'bg-card-background text-text-primary shadow-card'
                                        : 'text-text-tertiary'
                                "
                                :data-testid="'mobile_summary_' + option.value"
                                @click="period = option.value">
                                {{ option.label }}
                            </button>
                        </div>
                        <div
                            class="text-xl font-semibold tabular-nums text-text-primary"
                            data-testid="mobile_summary_total">
                            {{ period === 'today' ? todayTotal : weekTotal }}
                        </div>
                    </div>
                    <ul v-if="byProject.length > 0" class="mt-4 space-y-3">
                        <li v-for="row in byProject" :key="row.key" class="text-sm">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: row.color }"></span>
                                <span class="min-w-0 flex-1 truncate text-text-primary">{{
                                    row.name
                                }}</span>
                                <span class="shrink-0 font-medium tabular-nums text-text-primary">{{
                                    row.duration
                                }}</span>
                            </div>
                            <div
                                class="ml-[18px] mt-1 h-1.5 overflow-hidden rounded-full bg-[var(--valo-bar)]">
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: row.percent + '%',
                                        backgroundColor: row.color,
                                    }"></div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-text-tertiary">{{ $t('No hours yet.') }}</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
