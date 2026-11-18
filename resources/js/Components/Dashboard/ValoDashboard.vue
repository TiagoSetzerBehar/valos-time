<script setup lang="ts">
import { computed, type ComputedRef, inject, ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { CheckIcon, PlusIcon } from '@heroicons/vue/20/solid';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import TimeTracker from '@/Components/TimeTracker.vue';
import MobileTimer from '@/Components/MobileTimer.vue';
import MobileManualEntry from '@/Components/MobileManualEntry.vue';
import { useMediaQuery } from '@vueuse/core';
import RecentlyTrackedTasksCard from '@/Components/Dashboard/RecentlyTrackedTasksCard.vue';
import TeamActivityCard from '@/Components/Dashboard/TeamActivityCard.vue';
import ValoMeetingConfirm from '@/Components/ValoMeetingConfirm.vue';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useReportingStore } from '@/utils/useReporting';
import { type MeetingOccurrence, useMeetingOccurrences } from '@/utils/useValoMeetings';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import { getCurrentMembershipId, getCurrentUser } from '@/utils/useUser';
import {
    formatHumanReadableDuration,
    getDayJsInstance,
    getLocalizedDayJs,
    localDateToUtc,
} from '@/packages/ui/src/utils/time';
import { canViewMembers } from '@/utils/permissions';

// VALO's Time dashboard: "what do I need to know right now": my hours today and this week, where they
// went, the meetings still waiting for my OK, my last entries and what the team is doing.
const organization = inject<ComputedRef<Organization>>('organization');
const queryClient = useQueryClient();
const { projects } = useProjectsQuery();
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

const weekStart = getInitialWeekRange().start;
const todayKey = getLocalizedDayJs().format('YYYY-MM-DD');
const firstName = computed(() => getCurrentUser().name.trim().split(/\s+/)[0] ?? '');
const todayLabel = getDayJsInstance()(todayKey).format('dddd D MMM');
const isPhone = useMediaQuery('(max-width: 639px)');
const manualOpen = ref(false);

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

// meetings of this week (up to today) that still wait for "OK, asistí"
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
            {{ $t('Hello, :name', { name: firstName }) }}
        </h1>
        <span class="text-text-secondary"
            >{{ todayLabel }} · {{ $t('this is how your week is going') }}</span
        >
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
            <div class="valo-gcard valo-kpi col-span-2 lg:col-span-1">
                <div class="valo-kpi-label">{{ $t('My time by project · this week') }}</div>
                <template v-if="projectRows.length > 0">
                    <div class="mt-4 flex h-3.5 gap-[3px] overflow-hidden rounded-full">
                        <span
                            v-for="row in projectRows"
                            :key="row.key"
                            :style="{ width: row.percent + '%', background: row.color }"></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                        <span
                            v-for="row in projectRows"
                            :key="row.key"
                            class="flex items-center gap-2">
                            <i
                                class="valo-dot"
                                style="width: 10px; height: 10px; border-radius: 3px"
                                :style="{ background: row.color }"></i>
                            {{ row.name }}
                            <b class="font-semibold tabular-nums">{{
                                formatDuration(row.seconds)
                            }}</b>
                        </span>
                    </div>
                </template>
                <div v-else class="valo-kpi-note mt-4">{{ $t('No hours this week yet.') }}</div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
            <section class="valo-gcard valo-rcard" data-testid="dashboard_my_week">
                <h2>
                    {{ $t('My week') }} <span>{{ $t('hours per day') }}</span>
                </h2>
                <div class="valo-bars">
                    <div
                        v-for="bar in days"
                        :key="bar.key"
                        :style="{ height: bar.percent + '%', opacity: bar.seconds > 0 ? 1 : 0.35 }">
                        <span v-if="bar.seconds > 0">{{ formatDuration(bar.seconds) }}</span>
                    </div>
                </div>
                <div class="valo-bar-labels">
                    <span
                        v-for="bar in days"
                        :key="bar.key"
                        class="capitalize"
                        :class="{ 'valo-accent-text font-semibold': bar.isToday }"
                        >{{ bar.isToday ? $t('Today') : isPhone ? bar.short : bar.label }}</span
                    >
                </div>
            </section>

            <section class="valo-gcard valo-rcard" data-testid="dashboard_meetings">
                <h2>
                    {{ $t('Meetings to confirm') }}
                    <span>{{ $t('they do not count until your OK') }}</span>
                </h2>
                <div class="mt-2">
                    <div
                        v-for="meeting in pendingMeetings"
                        :key="meeting.meeting_id + meeting.date"
                        class="valo-meet-row">
                        <span>
                            <b class="block text-[15px] font-semibold">{{ meeting.name }}</b>
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
                    <p
                        v-if="pendingMeetings.length === 0"
                        class="py-3 text-sm text-text-secondary sm:py-8 sm:text-center sm:text-base">
                        {{ $t('All set: no meetings waiting for your OK.') }}
                    </p>
                </div>
            </section>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4" :class="canViewMembers() ? 'lg:grid-cols-2' : ''">
            <RecentlyTrackedTasksCard></RecentlyTrackedTasksCard>
            <TeamActivityCard v-if="canViewMembers()"></TeamActivityCard>
        </div>
    </MainContainer>

    <ValoMeetingConfirm v-model:show="showConfirm" :occurrence="confirming"></ValoMeetingConfirm>
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
    </template>
</template>
