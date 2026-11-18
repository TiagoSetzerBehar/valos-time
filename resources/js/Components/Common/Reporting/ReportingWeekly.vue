<script setup lang="ts">
import {
    ArrowDownTrayIcon,
    CalendarIcon,
    ChartBarIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/20/solid';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { trans } from 'laravel-vue-i18n';
import { computed, type ComputedRef, inject, ref } from 'vue';
import type { Dayjs } from 'dayjs';
import { useStorage } from '@vueuse/core';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import PageTitle from '@/Components/Common/PageTitle.vue';
import ReportingTabNavbar from '@/Components/Common/Reporting/ReportingTabNavbar.vue';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { SecondaryButton } from '@/packages/ui/src';
import type {
    AggregatedTimeEntries,
    AggregatedTimeEntriesQueryParams,
    Organization,
} from '@/packages/api/src';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useMembersQuery } from '@/utils/useMembersQuery';
import { useReportingStore } from '@/utils/useReporting';
import { getCurrentMembershipId, getCurrentOrganizationId, getCurrentRole } from '@/utils/useUser';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import {
    formatHumanReadableDuration,
    getLocalizedDayJs,
    getLocalizedDayJsFromMinutes,
    localDateToUtc,
} from '@/packages/ui/src/utils/time';
import { canViewAllTimeEntries } from '@/utils/permissions';
import { useNotificationsStore } from '@/utils/notification';
import { downloadOnePagePdf } from '@/utils/valoPdf';
import { type LongEntry, useFixLongEntry, useValoLongEntries } from '@/utils/useValoLongEntries';
import { valoAvatarColor, valoInitials } from '@/utils/valo';

// VALO's Time weekly report: one row per member (or project), one column per day, drawn as a heat map
// (more hours = bluer). Entries of more than 16 h in a row (forgotten timers) are shown apart.
type WeeklyGroup = 'user' | 'project';

const organization = inject<ComputedRef<Organization>>('organization');
const { getNameForReportingRowEntry } = useReportingStore();
const { members } = useMembersQuery({ enabled: () => canViewAllTimeEntries() });

const group = useStorage<WeeklyGroup>('reporting-weekly-group', 'user');
const thisWeek = getInitialWeekRange().start;
const weekStart = ref<Dayjs>(thisWeek);

const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => weekStart.value.add(i, 'day')));
const dayKeys = computed(() => weekDays.value.map((day) => day.format('YYYY-MM-DD')));
const weekRangeLabel = computed(
    () => `${weekStart.value.format('D MMM')} – ${weekStart.value.add(6, 'day').format('D MMM')}`
);
const weekOffset = computed(() => Math.round(weekStart.value.diff(thisWeek, 'day') / 7));
const weekName = computed(() => {
    if (weekOffset.value === 0) {
        return trans('This week');
    }
    if (weekOffset.value === -1) {
        return trans('Last week');
    }
    return trans('W:week', { week: String(weekStart.value.week()) });
});
const todayKey = getLocalizedDayJs().format('YYYY-MM-DD');

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

const longRange = computed(() => ({
    start: dayKeys.value[0] as string,
    end: dayKeys.value[6] as string,
}));
const { longEntries } = useValoLongEntries(longRange);

function longKey(entry: LongEntry): string | null {
    return group.value === 'user' ? entry.user_id : entry.project_id;
}

const rows = computed(() => {
    const data = aggregated.value;
    return (data?.grouped_data ?? [])
        .map((entry) => {
            const days = dayKeys.value.map(
                (key) => entry.grouped_data?.find((sub) => sub.key === key)?.seconds ?? 0
            );
            // entries to review are shown apart, so they do not count here
            for (const long of longEntries.value) {
                const index = dayKeys.value.indexOf(long.day);
                if (index >= 0 && longKey(long) === (entry.key ?? null)) {
                    const rest = (days[index] ?? 0) - long.seconds;
                    days[index] = rest < 300 ? 0 : rest;
                }
            }
            return {
                key: entry.key ?? 'none',
                memberId:
                    group.value === 'user'
                        ? (members.value.find((member) => member.user_id === entry.key)?.id ?? null)
                        : null,
                name: getNameForReportingRowEntry(entry.key, data?.grouped_type ?? null) ?? '',
                total: days.reduce((sum, seconds) => sum + seconds, 0),
                days,
            };
        })
        .filter((row) => row.total > 0)
        .sort((a, b) => b.total - a.total || a.name.localeCompare(b.name));
});
const maxTotal = computed(() => Math.max(1, ...rows.value.map((row) => row.total)));

const dayTotals = computed(() =>
    weekDays.value.map((_, index) =>
        rows.value.reduce((sum, row) => sum + (row.days[index] ?? 0), 0)
    )
);
const weekTotal = computed(() => dayTotals.value.reduce((sum, seconds) => sum + seconds, 0));
const weekTotalWithReview = computed(() => aggregated.value?.seconds ?? 0);

// members without a single hour this week, in one line under the table
const withoutHours = computed(() => {
    if (group.value !== 'user' || !canViewAllTimeEntries()) {
        return [];
    }
    const withHours = new Set(rows.value.map((row) => row.key));
    const inReview = new Set(longEntries.value.map((entry) => entry.user_id));
    return members.value
        .filter(
            (member) =>
                member.role !== 'placeholder' &&
                !withHours.has(member.user_id) &&
                !inReview.has(member.user_id)
        )
        .map((member) => member.name)
        .sort((a, b) => a.localeCompare(b));
});

function formatDuration(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        organization?.value?.interval_format,
        organization?.value?.number_format
    );
}

function heat(seconds: number): string {
    const hours = seconds / 3600;
    return hours < 2
        ? 'valo-heat-1'
        : hours < 4
          ? 'valo-heat-2'
          : hours < 7
            ? 'valo-heat-3'
            : 'valo-heat-4';
}

function personLink(memberId: string): string {
    return route('reporting', {
        member: memberId,
        start: dayKeys.value[0],
        end: dayKeys.value[6],
    });
}

// ---- one-page summary of the shown week (vs the week before) to share with the team
const summaryLoading = ref(false);
async function downloadSummary() {
    summaryLoading.value = true;
    try {
        const response = await axios.get<{ html: string; filename: string }>(
            route('valo.weekly-summary', {
                organizationId: getCurrentOrganizationId(),
                week_start: weekStart.value.format('YYYY-MM-DD'),
            })
        );
        await downloadOnePagePdf(response.data.html, response.data.filename);
    } catch (error) {
        console.error(error);
        useNotificationsStore().addNotification('error', trans('Export failed'));
    } finally {
        summaryLoading.value = false;
    }
}

// ---- "Corregir": set the real end of a forgotten timer
const fixing = ref<LongEntry | null>(null);
const fixEnd = ref('');
const fixError = ref('');
const { mutateAsync: fixLongEntry, isPending: isFixing } = useFixLongEntry();

function startOf(entry: LongEntry) {
    return getLocalizedDayJs(entry.start);
}
function openFix(entry: LongEntry) {
    fixing.value = entry;
    fixEnd.value = startOf(entry).add(1, 'hour').format('YYYY-MM-DDTHH:mm');
    fixError.value = '';
}
async function saveFix() {
    const entry = fixing.value;
    const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/.exec(fixEnd.value);
    if (!entry || !match) {
        fixError.value = trans('Choose the day and time the work really ended');
        return;
    }
    const end = getLocalizedDayJsFromMinutes(
        match[1] as string,
        Number(match[2]) * 60 + Number(match[3])
    );
    if (!end.isAfter(startOf(entry))) {
        fixError.value = trans('The end must be after the start');
        return;
    }
    try {
        await fixLongEntry({ id: entry.id, end: end.utc().format() });
        useNotificationsStore().addNotification('success', trans('Time entry corrected'));
        fixing.value = null;
    } catch (error) {
        const message = axios.isAxiosError(error) ? error.response?.data?.message : null;
        fixError.value = message ?? trans('The action failed. Please try again later.');
    }
}

/** "7:56" style hours for the small phone cells. */
function compactDuration(seconds: number): string {
    const minutes = Math.round(seconds / 60);
    return Math.floor(minutes / 60) + ':' + String(minutes % 60).padStart(2, '0');
}
</script>

<template>
    <MainContainer
        class="pt-3 sm:pt-0 sm:h-16 sm:border-b border-default-background-separator flex flex-wrap gap-y-2 justify-between items-center">
        <div class="flex items-center space-x-3 sm:space-x-6">
            <PageTitle :icon="ChartBarIcon" :title="$t('Reporting')"></PageTitle>
            <ReportingTabNavbar active="weekly" class="hidden sm:flex"></ReportingTabNavbar>
        </div>
        <SecondaryButton
            v-if="canViewAllTimeEntries()"
            :icon="ArrowDownTrayIcon"
            :loading="summaryLoading"
            data-testid="weekly_summary_pdf"
            @click="downloadSummary">
            <span class="sm:hidden">PDF</span>
            <span class="hidden sm:inline">{{ $t('Weekly summary (PDF)') }}</span>
        </SecondaryButton>
        <ReportingTabNavbar active="weekly" class="w-full sm:hidden"></ReportingTabNavbar>
    </MainContainer>
    <MainContainer class="pt-5 pb-10 valo-weekly">
        <!-- week, grouping and total -->
        <div class="valo-weekly-bar flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="valo-weekly-week flex items-center gap-1">
                    <button
                        type="button"
                        class="valo-weekly-arrow"
                        :aria-label="$t('Previous week')"
                        data-testid="weekly_previous"
                        @click="weekStart = weekStart.subtract(7, 'day')">
                        <ChevronLeftIcon class="h-5 w-5" />
                    </button>
                    <CalendarIcon class="h-4 w-4 text-text-tertiary" />
                    <span
                        class="px-1 text-sm font-medium text-text-primary"
                        data-testid="weekly_week">
                        {{ weekName }} · {{ weekRangeLabel }}
                    </span>
                    <button
                        type="button"
                        class="valo-weekly-arrow"
                        :aria-label="$t('Next week')"
                        @click="weekStart = weekStart.add(7, 'day')">
                        <ChevronRightIcon class="h-5 w-5" />
                    </button>
                </div>
                <button
                    v-if="weekOffset !== 0"
                    type="button"
                    class="valo-weekly-pill"
                    @click="weekStart = thisWeek">
                    {{ $t('Go to this week') }}
                </button>
                <span class="ml-2 hidden text-sm text-text-secondary sm:inline">{{
                    $t('Group by')
                }}</span>
                <div class="valo-weekly-seg" data-testid="weekly_report_group">
                    <button
                        type="button"
                        :class="{ on: group === 'user' }"
                        :aria-pressed="group === 'user'"
                        @click="group = 'user'">
                        {{ $t('Members') }}
                    </button>
                    <button
                        type="button"
                        :class="{ on: group === 'project' }"
                        :aria-pressed="group === 'project'"
                        @click="group = 'project'">
                        {{ $t('Projects') }}
                    </button>
                </div>
            </div>
            <div class="text-right">
                <div class="text-xs font-semibold uppercase tracking-wider text-text-secondary">
                    {{ $t('Week Total') }}
                    <span
                        class="ml-2 text-xl font-bold normal-case tracking-normal text-text-primary tabular-nums"
                        data-testid="weekly_total"
                        >{{ formatDuration(weekTotal) }}</span
                    >
                </div>
                <div v-if="longEntries.length > 0" class="text-xs text-text-secondary">
                    {{
                        $t(':total counting the entries to review', {
                            total: formatDuration(weekTotalWithReview),
                        })
                    }}
                </div>
            </div>
        </div>

        <!-- forgotten timers -->
        <div
            v-if="longEntries.length > 0"
            class="valo-weekly-review mt-4 flex flex-wrap items-center gap-3"
            data-testid="weekly_review">
            <span class="flex items-center gap-2 whitespace-nowrap font-semibold">
                <ExclamationTriangleIcon class="h-4 w-4" />
                {{ $t('To review') }} ({{ longEntries.length }})
            </span>
            <span
                v-for="entry in longEntries"
                :key="entry.id"
                class="valo-weekly-review-chip flex items-center gap-2">
                <span class="valo-avatar" :style="{ background: valoAvatarColor(entry.user_id) }">{{
                    valoInitials(entry.name)
                }}</span>
                <b class="font-semibold">{{ entry.name }}</b>
                <span>
                    · {{ startOf(entry).format('ddd D') }} ·
                    {{ formatDuration(entry.seconds) }}
                    {{ entry.running ? $t('and still running') : $t('in a row') }}
                </span>
                <button
                    v-if="entry.can_fix"
                    type="button"
                    class="font-semibold underline hover:no-underline"
                    data-testid="weekly_review_fix"
                    @click="openFix(entry)">
                    {{ $t('Fix') }}
                </button>
            </span>
            <span class="ml-auto whitespace-nowrap text-sm">{{ $t('Forgotten timer?') }}</span>
        </div>

        <!-- heat map -->
        <!-- phone: one block per row, the 7 days as small heat squares -->
        <div class="valo-weekly-table valo-wp mt-4 sm:hidden" data-testid="weekly_report_phone">
            <div class="valo-wp-days">
                <span
                    v-for="(day, index) in weekDays"
                    :key="dayKeys[index]"
                    class="capitalize"
                    :class="{
                        'valo-weekly-weekend': day.day() === 0 || day.day() === 6,
                        'valo-weekly-today': dayKeys[index] === todayKey,
                    }"
                    >{{ day.format('dd') }}<small>{{ day.format('D') }}</small></span
                >
            </div>
            <div v-if="isPending" class="flex justify-center py-10">
                <LoadingSpinner />
            </div>
            <template v-else-if="rows.length > 0">
                <div
                    v-for="row in rows"
                    :key="row.key"
                    class="valo-wp-row"
                    data-testid="weekly_row_phone">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="valo-avatar"
                            :style="{ background: valoAvatarColor(row.key) }"
                            >{{ valoInitials(row.name) }}</span
                        >
                        <Link
                            v-if="row.memberId"
                            :href="personLink(row.memberId)"
                            class="min-w-0 flex-1 truncate font-medium text-text-primary">
                            {{ row.name }}
                        </Link>
                        <span
                            v-else
                            class="min-w-0 flex-1 truncate font-medium text-text-primary"
                            >{{ row.name }}</span
                        >
                        <span class="shrink-0 font-semibold tabular-nums text-text-primary">{{
                            formatDuration(row.total)
                        }}</span>
                    </div>
                    <div class="valo-wp-cells">
                        <template v-for="(seconds, index) in row.days" :key="index">
                            <span v-if="seconds > 0" class="valo-heat" :class="heat(seconds)">{{
                                compactDuration(seconds)
                            }}</span>
                            <span v-else class="valo-wp-empty"><i class="valo-heat-0"></i></span>
                        </template>
                    </div>
                </div>
                <div v-if="withoutHours.length > 0" class="valo-wp-row text-sm text-text-secondary">
                    {{ $t('No hours this week') }} ({{ withoutHours.length }}):
                    {{ withoutHours.join(', ') }}
                </div>
                <div class="valo-wp-row valo-wp-foot">
                    <div class="flex items-center justify-between font-semibold">
                        <span>{{ $t('Team total') }}</span>
                        <span class="tabular-nums">{{ formatDuration(weekTotal) }}</span>
                    </div>
                    <div class="valo-wp-cells">
                        <span
                            v-for="(seconds, index) in dayTotals"
                            :key="index"
                            class="valo-wp-total"
                            >{{ seconds > 0 ? compactDuration(seconds) : '–' }}</span
                        >
                    </div>
                </div>
            </template>
            <div v-else class="px-4 py-10 text-center">
                <p class="font-semibold text-text-primary">{{ $t('No time entries found') }}</p>
                <p class="text-sm text-text-secondary">
                    {{ $t('Try to track some time entries this week') }}
                </p>
            </div>
        </div>
        <div
            class="valo-weekly-table mt-4 hidden overflow-x-auto sm:block"
            data-testid="weekly_report">
            <div class="min-w-[900px]">
                <div class="valo-weekly-row valo-weekly-head">
                    <span class="pl-5 text-left">{{
                        group === 'user' ? $t('Member') : $t('Project')
                    }}</span>
                    <span
                        v-for="(day, index) in weekDays"
                        :key="dayKeys[index]"
                        class="capitalize"
                        :class="{
                            'valo-weekly-weekend': day.day() === 0 || day.day() === 6,
                            'valo-weekly-today': dayKeys[index] === todayKey,
                        }">
                        {{ day.format('ddd D') }}
                    </span>
                    <span class="pr-5 text-right">{{ $t('Total') }} ▾</span>
                </div>
                <div v-if="isPending" class="flex justify-center py-12">
                    <LoadingSpinner />
                </div>
                <template v-else-if="rows.length > 0">
                    <div
                        v-for="row in rows"
                        :key="row.key"
                        class="valo-weekly-row valo-weekly-line"
                        data-testid="weekly_row">
                        <span class="flex min-w-0 items-center gap-2.5 pl-5 font-medium">
                            <span
                                class="valo-avatar"
                                :style="{ background: valoAvatarColor(row.key) }"
                                >{{ valoInitials(row.name) }}</span
                            >
                            <Link
                                v-if="row.memberId"
                                :href="personLink(row.memberId)"
                                class="truncate hover:underline"
                                :title="$t('See the summary of :name', { name: row.name })">
                                {{ row.name }}
                            </Link>
                            <span v-else class="truncate">{{ row.name }}</span>
                        </span>
                        <template v-for="(seconds, index) in row.days" :key="index">
                            <span v-if="seconds > 0" class="valo-heat" :class="heat(seconds)">{{
                                formatDuration(seconds)
                            }}</span>
                            <span v-else class="valo-heat-0" aria-label="0"></span>
                        </template>
                        <span class="pr-5 text-right font-semibold tabular-nums">
                            {{ formatDuration(row.total) }}
                            <i
                                class="valo-weekly-total-bar"
                                :style="{
                                    width:
                                        Math.max(4, Math.round((row.total / maxTotal) * 100)) + '%',
                                }"></i>
                        </span>
                    </div>
                    <div
                        v-if="withoutHours.length > 0"
                        class="valo-weekly-line px-5 py-3 text-sm text-text-secondary"
                        data-testid="weekly_without_hours">
                        {{ $t('No hours this week') }} ({{ withoutHours.length }}):
                        {{ withoutHours.join(', ') }}
                    </div>
                    <div class="valo-weekly-row valo-weekly-foot">
                        <span class="pl-5 text-left">{{ $t('Team total') }}</span>
                        <span v-for="(seconds, index) in dayTotals" :key="index">{{
                            seconds > 0 ? formatDuration(seconds) : '–'
                        }}</span>
                        <span class="pr-5 text-right text-base">{{
                            formatDuration(weekTotal)
                        }}</span>
                    </div>
                </template>
                <div v-else class="flex flex-col items-center justify-center py-12">
                    <p class="text-lg text-text-primary font-semibold">
                        {{ $t('No time entries found') }}
                    </p>
                    <p class="text-text-secondary">
                        {{ $t('Try to track some time entries this week') }}
                    </p>
                </div>
            </div>
        </div>
        <div
            class="mt-3 flex flex-wrap items-center gap-1.5 pl-2 sm:pl-5 text-sm text-text-secondary">
            {{ $t('Fewer hours') }}
            <b class="valo-heat-key valo-heat-1"></b><b class="valo-heat-key valo-heat-2"></b
            ><b class="valo-heat-key valo-heat-3"></b><b class="valo-heat-key valo-heat-4"></b>
            {{ $t('More hours in the day') }}
        </div>
    </MainContainer>

    <Teleport to="body">
        <div
            v-if="fixing"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/30 sm:items-center sm:p-4"
            data-testid="weekly_fix_dialog"
            @click.self="fixing = null">
            <div
                class="valo-floating-sheet w-full border border-card-border bg-default-background p-5 pb-8 shadow-dropdown sm:max-w-sm sm:pb-5"
                style="border-radius: 1.5rem">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-text-tertiary">{{ $t('Forgotten timer?') }}</div>
                        <h2 class="text-lg font-semibold text-text-primary">{{ fixing.name }}</h2>
                        <div class="text-sm text-text-secondary">
                            {{ fixing.description || $t('No description') }}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-text-tertiary"
                        :aria-label="$t('Close')"
                        @click="fixing = null">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <p class="mt-3 text-sm text-text-secondary">
                    {{
                        $t('It started on :start and counts :duration.', {
                            start: startOf(fixing).format('ddd D MMM, HH:mm'),
                            duration: formatDuration(fixing.seconds),
                        })
                    }}
                </p>
                <label class="valo-mobile-label" for="weekly_fix_end">{{
                    $t('When did the work really end?')
                }}</label>
                <input
                    id="weekly_fix_end"
                    v-model="fixEnd"
                    type="datetime-local"
                    :min="startOf(fixing).format('YYYY-MM-DDTHH:mm')"
                    class="w-full rounded-xl border border-card-border bg-card-background px-3 py-3 text-base font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                    data-testid="weekly_fix_end" />
                <p v-if="fixError" class="mt-2 text-sm text-red-500">{{ fixError }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-card-border bg-card-background py-3 text-sm font-medium text-text-secondary"
                        @click="fixing = null">
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-card disabled:opacity-50"
                        :disabled="isFixing"
                        data-testid="weekly_fix_save"
                        @click="saveFix">
                        {{ $t('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
