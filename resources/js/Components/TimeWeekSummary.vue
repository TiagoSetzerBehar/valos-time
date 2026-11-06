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
        <div class="flex items-end justify-between pb-2.5">
            <div class="flex items-baseline gap-3">
                <h2 class="text-base text-text-primary">{{ $t('This Week') }}</h2>
                <button
                    v-if="selected"
                    type="button"
                    class="valo-accent-text text-sm font-medium hover:underline"
                    data-testid="time_week_show_all"
                    @click="selected = null">
                    {{ $t('Show all days') }}
                </button>
            </div>
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
