<script setup lang="ts">
import { ChartBarIcon } from '@heroicons/vue/20/solid';
import { computed, type ComputedRef, inject, ref } from 'vue';
import type { Dayjs } from 'dayjs';
import { useStorage } from '@vueuse/core';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import PageTitle from '@/Components/Common/PageTitle.vue';
import ReportingTabNavbar from '@/Components/Common/Reporting/ReportingTabNavbar.vue';
import TimesheetHeader from '@/Components/Timesheet/TimesheetHeader.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/packages/ui/src';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useReportingStore } from '@/utils/useReporting';
import { getCurrentMembershipId, getCurrentRole } from '@/utils/useUser';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { formatHumanReadableDuration, localDateToUtc } from '@/packages/ui/src/utils/time';
import { getDateRangeLabel } from '@/packages/ui/src/utils/dateRange';

// Weekly report like Clockify: one row per member (or project), one column per day of the week.
type WeeklyGroup = 'user' | 'project';

const organization = inject<ComputedRef<Organization>>('organization');
const { getNameForReportingRowEntry } = useReportingStore();

const group = useStorage<WeeklyGroup>('reporting-weekly-group', 'user');
const weekStart = ref<Dayjs>(getInitialWeekRange().start);

const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => weekStart.value.add(i, 'day')));
const weekNumber = computed(() => weekStart.value.week());
const weekRangeLabel = computed(() =>
    getDateRangeLabel(weekStart.value, weekStart.value.add(6, 'day'))
);

const queryParams = computed<AggregatedTimeEntriesQueryParams>(() => ({
    start: localDateToUtc(weekStart.value),
    end: localDateToUtc(weekStart.value.add(7, 'day').subtract(1, 'second')),
    group: group.value,
    sub_group: 'day',
    type: 'work',
    member_id: getCurrentRole() === 'employee' ? getCurrentMembershipId() : undefined,
}));

const { data: response, isPending } = useAggregatedTimeEntriesQuery('weekly', queryParams);

const aggregated = computed<AggregatedTimeEntries | undefined>(
    () => response.value?.data as AggregatedTimeEntries | undefined
);

const rows = computed(() => {
    const data = aggregated.value;
    return (data?.grouped_data ?? [])
        .map((entry) => ({
            key: entry.key ?? 'none',
            name: getNameForReportingRowEntry(entry.key, data?.grouped_type ?? null) ?? '',
            total: entry.seconds,
            days: weekDays.value.map(
                (day) =>
                    entry.grouped_data?.find((sub) => sub.key === day.format('YYYY-MM-DD'))
                        ?.seconds ?? 0
            ),
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
});

const dayTotals = computed(() =>
    weekDays.value.map((_, index) =>
        rows.value.reduce((sum, row) => sum + (row.days[index] ?? 0), 0)
    )
);
const weekTotal = computed(() => aggregated.value?.seconds ?? 0);

function formatDuration(seconds: number): string {
    if (seconds === 0) {
        return '-';
    }
    return formatHumanReadableDuration(
        seconds,
        organization?.value?.interval_format,
        organization?.value?.number_format
    );
}

const gridStyle =
    'grid-template-columns: minmax(180px, 1fr) repeat(7, minmax(72px, 96px)) minmax(88px, 110px)';
</script>

<template>
    <MainContainer
        class="h-14 sm:h-16 border-b border-default-background-separator flex flex-wrap gap-y-3 justify-between items-center">
        <div class="flex items-center space-x-3 sm:space-x-6">
            <PageTitle :icon="ChartBarIcon" :title="$t('Reporting')"></PageTitle>
            <ReportingTabNavbar active="weekly" class="hidden sm:flex"></ReportingTabNavbar>
        </div>
    </MainContainer>
    <MainContainer class="pt-5 pb-10">
        <TimesheetHeader
            class="!px-0"
            :week-number="weekNumber"
            :range-label="weekRangeLabel"
            :week-total-formatted="formatDuration(weekTotal)"
            @previous="weekStart = weekStart.subtract(7, 'day')"
            @next="weekStart = weekStart.add(7, 'day')"
            @current="weekStart = getInitialWeekRange().start" />
        <div class="flex items-center gap-2 mb-4 text-sm text-text-secondary">
            <span>{{ $t('Group by') }}</span>
            <Select v-model="group">
                <SelectTrigger class="w-44" data-testid="weekly_report_group">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="user">{{ $t('Members') }}</SelectItem>
                    <SelectItem value="project">{{ $t('Projects') }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div
            class="overflow-x-auto rounded-lg border border-card-background-separator bg-card-background"
            data-testid="weekly_report">
            <div class="grid text-sm" :style="gridStyle">
                <div
                    class="contents [&>*]:border-b [&>*]:border-card-background-separator [&>*]:bg-tertiary [&>*]:py-2 text-text-secondary">
                    <div class="pl-4 sticky left-0 z-10">
                        {{ group === 'user' ? $t('Member') : $t('Project') }}
                    </div>
                    <div
                        v-for="day in weekDays"
                        :key="day.format('YYYY-MM-DD')"
                        class="text-right pr-3 capitalize">
                        {{ day.format('ddd D') }}
                    </div>
                    <div class="text-right pr-4 sticky right-0 z-10">{{ $t('Total') }}</div>
                </div>
                <div v-if="isPending" class="col-span-9 flex justify-center py-12">
                    <LoadingSpinner />
                </div>
                <template v-else-if="rows.length > 0">
                    <div
                        v-for="row in rows"
                        :key="row.key"
                        class="contents [&>*]:border-b [&>*]:border-card-background-separator [&>*]:py-2.5 text-text-primary">
                        <div class="pl-4 truncate sticky left-0 z-10 bg-card-background">
                            {{ row.name }}
                        </div>
                        <div
                            v-for="(seconds, index) in row.days"
                            :key="index"
                            class="text-right pr-3"
                            :class="seconds === 0 ? 'text-text-quaternary' : ''">
                            {{ formatDuration(seconds) }}
                        </div>
                        <div class="text-right pr-4 font-medium">
                            {{ formatDuration(row.total) }}
                        </div>
                    </div>
                    <div class="contents [&>*]:py-2.5 text-text-secondary font-medium">
                        <div class="pl-4 sticky left-0 z-10 bg-card-background">
                            {{ $t('Total') }}
                        </div>
                        <div
                            v-for="(seconds, index) in dayTotals"
                            :key="index"
                            class="text-right pr-3">
                            {{ formatDuration(seconds) }}
                        </div>
                        <div
                            class="text-right pr-4 text-text-primary sticky right-0 z-10 bg-card-background">
                            {{ formatDuration(weekTotal) }}
                        </div>
                    </div>
                </template>
                <div v-else class="col-span-9 flex flex-col items-center justify-center py-12">
                    <p class="text-lg text-text-primary font-semibold">
                        {{ $t('No time entries found') }}
                    </p>
                    <p class="text-text-secondary">
                        {{ $t('Try to track some time entries this week') }}
                    </p>
                </div>
            </div>
        </div>
    </MainContainer>
</template>
