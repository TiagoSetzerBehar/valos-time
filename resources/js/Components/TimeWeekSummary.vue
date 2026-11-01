<script setup lang="ts">
import { computed, inject, ref, type ComputedRef } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import GlassLens from '@/packages/ui/src/GlassLens.vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { formatHumanReadableDuration, localDateToUtc } from '@/packages/ui/src/utils/time';
import { getCurrentMembershipId } from '@/utils/useUser';
import { getDayJsInstance } from '@/packages/ui/src/utils/time';

// Clockify-style "this week" line with the week total of the current member,
// plus a strip with the hours of each day (click a day to jump to it in the list).
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

const weekTotal = computed(() =>
    formatHumanReadableDuration(
        aggregate.value?.seconds ?? 0,
        organization?.value?.interval_format,
        organization?.value?.number_format
    )
);

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

const selected = ref(today);

function pick(key: string) {
    selected.value = key;
    document
        .querySelector(`[data-valo-day="${key}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <MainContainer class="pt-1 pb-4">
        <div class="flex items-end justify-between pb-2.5">
            <h2 class="text-base text-text-primary">{{ $t('This Week') }}</h2>
            <div class="flex items-baseline gap-2 text-sm">
                <span class="text-text-tertiary">{{ $t('Week Total') }}</span>
                <span class="text-xl font-medium text-text-primary" data-testid="time_week_total">{{
                    weekTotal
                }}</span>
            </div>
        </div>
        <div
            role="group"
            :aria-label="$t('Days of the week')"
            class="valo-week-strip valo-lens-host relative grid grid-cols-7 gap-1 rounded border border-card-border bg-card-background p-1.5 shadow-card">
            <GlassLens selector="[aria-pressed='true']" storage-key="week" />
            <button
                v-for="day in days"
                :key="day.key"
                type="button"
                class="valo-week-day flex flex-col items-center justify-center gap-1 rounded py-2 min-h-[64px] text-text-secondary transition hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring aria-pressed:bg-[var(--valo-bar)]"
                :aria-pressed="selected === day.key ? 'true' : 'false'"
                @click="pick(day.key)">
                <span
                    class="text-[11px] font-semibold uppercase tracking-wider"
                    :class="day.isToday ? 'valo-accent-text' : 'text-text-tertiary'"
                    >{{ day.name }} {{ day.number }}</span
                >
                <span
                    class="text-[15px] font-semibold"
                    :class="selected === day.key ? 'valo-accent-text' : 'text-text-primary'"
                    >{{ day.hours }}</span
                >
                <span
                    class="valo-week-bar block h-1.5 w-full max-w-[56px] overflow-hidden rounded-full">
                    <span
                        class="valo-week-fill block h-full rounded-full"
                        :style="{ width: day.percent + '%' }"></span>
                </span>
            </button>
        </div>
    </MainContainer>
</template>
