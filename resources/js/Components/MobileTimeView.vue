<script setup lang="ts">
import { computed } from 'vue';
import { PlusIcon } from '@heroicons/vue/20/solid';
import { trans } from 'laravel-vue-i18n';
import type { Project, TimeEntry } from '@/packages/api/src';
import {
    formatWeekday,
    getDayJsInstance,
    getLocalizedDateFromTimestamp,
    getLocalizedDayJs,
} from '@/packages/ui/src/utils/time';
import { NO_PROJECT_COLOR } from '@/packages/ui/src/FullCalendar/useCalendarEvents';
import { useWeekSummary } from '@/utils/useWeekSummary';

// VALO's Time on the phone: today/week summary and a simple list of the latest entries.
// Tapping an entry opens it for editing; the button at the bottom adds hours by hand.
const props = defineProps<{
    timeEntries: TimeEntry[];
    projects: Project[];
}>();

const emit = defineEmits<{
    edit: [timeEntry: TimeEntry];
    manual: [];
}>();

const { weekTotal, todayTotal, formatDuration } = useWeekSummary();

const projectsById = computed(
    () => new Map(props.projects.map((project) => [project.id, project]))
);

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
                duration: formatDuration(entry.duration ?? 0),
            };
        }),
    }));
});
</script>

<template>
    <div class="valo-mobile px-4 pb-28 space-y-4" data-testid="mobile_time_view">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded border border-card-border bg-card-background px-4 py-3 shadow-card">
                <div class="text-xs text-text-tertiary">{{ $t('Today') }}</div>
                <div
                    class="text-xl font-semibold text-text-primary"
                    data-testid="mobile_today_total">
                    {{ todayTotal }}
                </div>
            </div>
            <div class="rounded border border-card-border bg-card-background px-4 py-3 shadow-card">
                <div class="text-xs text-text-tertiary">{{ $t('This Week') }}</div>
                <div
                    class="text-xl font-semibold text-text-primary"
                    data-testid="mobile_week_total">
                    {{ weekTotal }}
                </div>
            </div>
        </div>

        <section
            v-for="day in days"
            :key="day.key"
            class="rounded border border-card-border bg-card-background shadow-card overflow-hidden">
            <header class="flex items-center justify-between px-4 py-2.5 text-sm">
                <span class="font-medium text-text-primary">{{ day.label }}</span>
                <span class="font-semibold text-text-primary">{{ day.total }}</span>
            </header>
            <button
                v-for="item in day.entries"
                :key="item.entry.id"
                type="button"
                class="flex w-full items-center gap-3 border-t border-card-background-separator px-4 py-3 text-left active:bg-card-background-active"
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
        </section>

        <p v-if="days.length === 0" class="pt-6 text-center text-sm text-text-tertiary">
            {{ $t('Press play to start the timer, or add your hours with the button below.') }}
        </p>

        <button
            type="button"
            class="valo-mobile-add fixed bottom-6 right-4 z-30 flex items-center gap-2 rounded-full bg-sky-500 px-5 py-3.5 text-sm font-semibold text-white shadow-dropdown"
            data-testid="mobile_add_hours"
            @click="emit('manual')">
            <PlusIcon class="h-5 w-5" />
            {{ $t('Add hours') }}
        </button>
    </div>
</template>
