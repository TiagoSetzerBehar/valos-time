<script setup lang="ts">
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import GlassLens from '@/packages/ui/src/GlassLens.vue';
import { useWeekSummary } from '@/utils/useWeekSummary';

// Clockify-style "this week" line with the week total of the current member,
// plus a strip with the hours of each day. Clicking a day shows only that day in the list below
// (the page does not scroll); clicking it again shows every day.
const { weekTotal, days } = useWeekSummary();

const selected = defineModel<string | null>('day', { default: null });

function pick(key: string) {
    selected.value = selected.value === key ? null : key;
}
</script>

<template>
    <MainContainer class="pt-1 pb-4">
        <div
            role="group"
            :aria-label="$t('Days of the week')"
            class="valo-week-strip valo-lens-host relative flex items-stretch gap-1 rounded border border-card-border bg-card-background p-1.5 shadow-card">
            <GlassLens selector="[aria-pressed='true']" storage-key="week" />
            <div class="flex w-[124px] shrink-0 flex-col justify-center pl-3">
                <h2 class="text-sm font-semibold text-text-primary">{{ $t('This Week') }}</h2>
                <button
                    v-if="selected"
                    type="button"
                    class="valo-accent-text text-left text-xs font-medium hover:underline"
                    data-testid="time_week_show_all"
                    @click="selected = null">
                    {{ $t('Show all days') }}
                </button>
            </div>
            <button
                v-for="day in days"
                :key="day.key"
                type="button"
                class="valo-week-day flex min-h-[52px] flex-1 flex-col items-center justify-center gap-0.5 rounded py-1.5 text-text-secondary transition hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring aria-pressed:bg-[var(--valo-bar)]"
                :aria-pressed="selected === day.key ? 'true' : 'false'"
                @click="pick(day.key)">
                <span
                    class="text-[11px] font-semibold uppercase tracking-wider"
                    :class="day.isToday ? 'valo-accent-text' : 'text-text-tertiary'"
                    >{{ day.isToday ? $t('Today') + ' · ' : '' }}{{ day.name }}
                    {{ day.number }}</span
                >
                <span
                    class="text-[15px] font-semibold tabular-nums"
                    :class="
                        selected === day.key
                            ? 'valo-accent-text'
                            : day.percent > 0
                              ? 'text-text-primary'
                              : 'text-text-quaternary'
                    "
                    >{{ day.hours }}</span
                >
            </button>
            <div class="flex w-[140px] shrink-0 flex-col items-end justify-center pr-4">
                <span
                    class="text-[11px] font-semibold uppercase tracking-wider text-text-tertiary"
                    >{{ $t('Week Total') }}</span
                >
                <span
                    class="text-lg font-semibold text-text-primary tabular-nums"
                    data-testid="time_week_total"
                    >{{ weekTotal }}</span
                >
            </div>
        </div>
    </MainContainer>
</template>
