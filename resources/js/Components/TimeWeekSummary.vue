<script setup lang="ts">
import { computed, inject, type ComputedRef } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { formatHumanReadableDuration, localDateToUtc } from '@/packages/ui/src/utils/time';
import { getCurrentMembershipId } from '@/utils/useUser';

// Clockify-style "this week" line with the week total of the current member.
const organization = inject<ComputedRef<Organization>>('organization');
const week = getInitialWeekRange();

const queryParams = computed<AggregatedTimeEntriesQueryParams>(() => ({
    start: localDateToUtc(week.start),
    end: localDateToUtc(week.end.subtract(1, 'second')),
    member_id: getCurrentMembershipId(),
    type: 'work',
}));

const { data } = useAggregatedTimeEntriesQuery('time-week-total', queryParams);

const weekTotal = computed(() =>
    formatHumanReadableDuration(
        (data.value?.data as AggregatedTimeEntries | undefined)?.seconds ?? 0,
        organization?.value?.interval_format,
        organization?.value?.number_format
    )
);
</script>

<template>
    <MainContainer
        class="flex items-center justify-between py-2.5 border-t border-default-background-separator">
        <h2 class="text-sm font-medium text-text-primary">{{ $t('This Week') }}</h2>
        <div class="flex items-baseline gap-2 text-sm">
            <span class="text-text-tertiary">{{ $t('Week Total') }}</span>
            <span class="font-semibold text-text-primary" data-testid="time_week_total">{{
                weekTotal
            }}</span>
        </div>
    </MainContainer>
</template>
