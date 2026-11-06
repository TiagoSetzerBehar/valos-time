import { computed, inject, type ComputedRef } from 'vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import {
    formatHumanReadableDuration,
    getDayJsInstance,
    localDateToUtc,
} from '@/packages/ui/src/utils/time';
import { getCurrentMembershipId } from '@/utils/useUser';

/**
 * VALO's Time: hours of the current member in this week, per day and in total
 * (used by the week strip on the computer and by the summary on the phone).
 */
export function useWeekSummary() {
    const organization = inject<ComputedRef<Organization>>('organization');
    const week = getInitialWeekRange();

    const queryParams = computed<AggregatedTimeEntriesQueryParams>(() => ({
        start: localDateToUtc(week.start),
        end: localDateToUtc(week.end.subtract(1, 'second')),
        member_id: getCurrentMembershipId(),
        type: 'work',
        group: 'day',
    }));

    const { data } = useAggregatedTimeEntriesQuery('time-week-days', queryParams);
    const aggregate = computed(() => data.value?.data as AggregatedTimeEntries | undefined);

    function formatDuration(seconds: number): string {
        return formatHumanReadableDuration(
            seconds,
            organization?.value?.interval_format,
            organization?.value?.number_format
        );
    }

    const weekTotal = computed(() => formatDuration(aggregate.value?.seconds ?? 0));

    const today = getDayJsInstance()().format('YYYY-MM-DD');

    const days = computed(() => {
        const seconds = new Map<string, number>();
        for (const group of aggregate.value?.grouped_data ?? []) {
            if (group.key) {
                seconds.set(group.key.substring(0, 10), group.seconds);
            }
        }
        const list = Array.from({ length: 7 }, (_, i) => {
            const date = week.start.add(i, 'day');
            const key = date.format('YYYY-MM-DD');
            const value = seconds.get(key) ?? 0;
            const name = date.format('ddd').replace('.', '');
            return {
                key,
                name: name.charAt(0).toUpperCase() + name.slice(1),
                number: date.format('D'),
                seconds: value,
                hours: `${Math.floor(value / 3600)}:${String(Math.floor(value / 60) % 60).padStart(2, '0')}`,
                isToday: key === today,
            };
        });
        const max = Math.max(8 * 3600, ...list.map((day) => day.seconds));
        return list.map((day) => ({ ...day, percent: Math.round((day.seconds / max) * 100) }));
    });

    const todayTotal = computed(() =>
        formatDuration(days.value.find((day) => day.isToday)?.seconds ?? 0)
    );

    return { weekTotal, todayTotal, days, formatDuration };
}
