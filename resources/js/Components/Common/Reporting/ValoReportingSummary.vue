<script setup lang="ts">
import {
    ArrowLeftIcon,
    ChartBarIcon,
    ExclamationTriangleIcon,
    ChevronDownIcon,
    TagIcon,
    XMarkIcon,
} from '@heroicons/vue/20/solid';
import { FolderIcon } from '@heroicons/vue/16/solid';
import { Link } from '@inertiajs/vue3';
import { useMediaQuery, useSessionStorage } from '@vueuse/core';
import { trans } from 'laravel-vue-i18n';
import { computed, type ComputedRef, inject, ref } from 'vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import PageTitle from '@/Components/Common/PageTitle.vue';
import ReportingTabNavbar from '@/Components/Common/Reporting/ReportingTabNavbar.vue';
import ReportingExportButton from '@/Components/Common/Reporting/ReportingExportButton.vue';
import ReportingExportModal from '@/Components/Common/Reporting/ReportingExportModal.vue';
import ValoPicker from '@/Components/ValoPicker.vue';
import DateRangePicker from '@/packages/ui/src/Input/DateRangePicker.vue';
import {
    api,
    type AggregatedTimeEntries,
    type AggregatedTimeEntriesQueryParams,
    type Organization,
} from '@/packages/api/src';
import type { ExportFormat } from '@/types/reporting';
import { useAggregatedTimeEntriesQuery } from '@/utils/useAggregatedTimeEntriesQuery';
import { useMembersQuery } from '@/utils/useMembersQuery';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useReportingStore } from '@/utils/useReporting';
import { useNotificationsStore } from '@/utils/notification';
import { getInitialWeekRange } from '@/utils/useTimeEntriesCalendarQuery';
import {
    getCurrentMembershipId,
    getCurrentOrganizationId,
    getCurrentRole,
    getCurrentUser,
} from '@/utils/useUser';
import {
    formatHumanReadableDuration,
    getDayJsInstance,
    getLocalizedDayJs,
    getLocalizedDayJsFromMinutes,
} from '@/packages/ui/src/utils/time';
import { canViewAllTimeEntries } from '@/utils/permissions';
import { saveReportPdf } from '@/utils/valoPdf';
import { useValoLongEntries } from '@/utils/useValoLongEntries';
import { valoAvatarColor, valoInitials } from '@/utils/valo';

// VALO's Time summary report: the numbers of a period at a glance (vs the period before), who worked,
// on which projects and on which days. With one member selected it becomes that person's summary,
// with the tags they used.
const isPhone = useMediaQuery('(max-width: 639px)');
type Preset = 'this-week' | 'last-week' | 'this-month' | 'last-month';
const NO_PROJECT_COLOR = '#4F7CAC';

const organization = inject<ComputedRef<Organization>>('organization');
const { getNameForReportingRowEntry } = useReportingStore();
const { handleApiRequestNotifications } = useNotificationsStore();
const seesAll = canViewAllTimeEntries() && getCurrentRole() !== 'employee';
const { members } = useMembersQuery({ enabled: () => seesAll });
const { projects } = useProjectsQuery();
const { tags } = useTagsQuery();

// ---- period
function iso(dayKey: string): string {
    return getLocalizedDayJsFromMinutes(dayKey, 0).format();
}
function key(date: string): string {
    return getLocalizedDayJs(date).format('YYYY-MM-DD');
}
const todayKey = getLocalizedDayJs().format('YYYY-MM-DD');
const weekStartKey = getInitialWeekRange().start.format('YYYY-MM-DD');
const day = (dayKey: string) => getDayJsInstance()(dayKey);

function presetRange(preset: Preset): [string, string] {
    const weekStart = day(weekStartKey);
    const today = day(todayKey);
    if (preset === 'this-week') {
        return [weekStartKey, weekStart.add(6, 'day').format('YYYY-MM-DD')];
    }
    if (preset === 'last-week') {
        return [
            weekStart.subtract(7, 'day').format('YYYY-MM-DD'),
            weekStart.subtract(1, 'day').format('YYYY-MM-DD'),
        ];
    }
    const month = preset === 'this-month' ? today : today.subtract(1, 'month');
    return [month.startOf('month').format('YYYY-MM-DD'), month.endOf('month').format('YYYY-MM-DD')];
}
const presets: { value: Preset; label: string }[] = [
    { value: 'this-week', label: trans('This week') },
    { value: 'last-week', label: trans('Last week') },
    { value: 'this-month', label: trans('This month') },
    { value: 'last-month', label: trans('Last month') },
];

const urlParams = new URLSearchParams(window.location.search);
const startDate = useSessionStorage<string>('valo-report-start', iso(presetRange('this-week')[0]));
const endDate = useSessionStorage<string>('valo-report-end', iso(presetRange('this-week')[1]));
if (/^\d{4}-\d{2}-\d{2}$/.test(urlParams.get('start') ?? '') && urlParams.get('end')) {
    startDate.value = iso(urlParams.get('start') as string);
    endDate.value = iso(urlParams.get('end') as string);
}
const startKey = computed(() => key(startDate.value));
const endKey = computed(() => key(endDate.value));
const activePreset = computed(
    () =>
        presets.find((preset) => {
            const [start, end] = presetRange(preset.value);
            return start === startKey.value && end === endKey.value;
        })?.value ?? null
);
function setRange(start: string, end: string) {
    startDate.value = iso(start);
    endDate.value = iso(end);
}
const isWholeMonth = computed(() => {
    const start = day(startKey.value);
    return start.date() === 1 && start.endOf('month').format('YYYY-MM-DD') === endKey.value;
});
const periodDays = computed(() => day(endKey.value).diff(day(startKey.value), 'day') + 1);
function shifted(direction: 1 | -1): [string, string] {
    if (isWholeMonth.value) {
        const month = day(startKey.value).add(direction, 'month');
        return [
            month.startOf('month').format('YYYY-MM-DD'),
            month.endOf('month').format('YYYY-MM-DD'),
        ];
    }
    return [
        day(startKey.value)
            .add(direction * periodDays.value, 'day')
            .format('YYYY-MM-DD'),
        day(endKey.value)
            .add(direction * periodDays.value, 'day')
            .format('YYYY-MM-DD'),
    ];
}
const previousRange = computed(() => shifted(-1));

// ---- filters
const selectedMembers = ref<string[]>(
    seesAll && urlParams.get('member') ? [urlParams.get('member') as string] : []
);
const selectedProjects = ref<string[]>([]);
const selectedTags = ref<string[]>([]);
const hasFilters = computed(
    () =>
        selectedMembers.value.length + selectedProjects.value.length + selectedTags.value.length > 0
);
// People are picked by clicking their name; the bar only filters by project and tag.
const hasExtraFilters = computed(
    () => selectedProjects.value.length + selectedTags.value.length > 0
);
function clearFilters() {
    selectedProjects.value = [];
    selectedTags.value = [];
}
function showWholeTeam() {
    selectedMembers.value = [];
}
const firstProject = computed(() =>
    projects.value.find((project) => project.id === selectedProjects.value[0])
);
const firstTag = computed(() => tags.value.find((tag) => tag.id === selectedTags.value[0]));
const projectItems = computed(() =>
    [...projects.value]
        .sort(
            (a, b) => Number(a.is_archived) - Number(b.is_archived) || a.name.localeCompare(b.name)
        )
        .map((project) => ({ id: project.id, name: project.name, color: project.color }))
);
const tagItems = computed(() =>
    [...tags.value]
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((tag) => ({ id: tag.id, name: tag.name }))
);

/** One member selected (or an employee, who only sees their own hours) = that person's summary. */
const person = computed(() => {
    if (!seesAll) {
        return {
            id: getCurrentMembershipId() ?? '',
            userId: getCurrentUser().id,
            name: getCurrentUser().name,
        };
    }
    if (selectedMembers.value.length !== 1) {
        return null;
    }
    const member = members.value.find((item) => item.id === selectedMembers.value[0]);
    return member ? { id: member.id, userId: member.user_id, name: member.name } : null;
});

// ---- data
function utcRange(start: string, end: string) {
    return {
        start: getLocalizedDayJsFromMinutes(start, 0).utc().format(),
        end: getLocalizedDayJsFromMinutes(end, 0).endOf('day').utc().format(),
    };
}
const filter = computed<AggregatedTimeEntriesQueryParams>(() => ({
    ...utcRange(startKey.value, endKey.value),
    member_ids: selectedMembers.value.length > 0 ? selectedMembers.value : undefined,
    project_ids: selectedProjects.value.length > 0 ? selectedProjects.value : undefined,
    tag_ids: selectedTags.value.length > 0 ? selectedTags.value : undefined,
    type: 'work',
    member_id: seesAll ? undefined : getCurrentMembershipId(),
}));
const timeGroup = computed<'day' | 'week' | 'month'>(() =>
    periodDays.value <= 31 ? 'day' : periodDays.value <= 200 ? 'week' : 'month'
);
function grouped(prefix: string, extra: () => Partial<AggregatedTimeEntriesQueryParams>) {
    const params = computed<AggregatedTimeEntriesQueryParams>(() => ({
        ...filter.value,
        ...extra(),
    }));
    const { data } = useAggregatedTimeEntriesQuery(prefix, params);
    return computed(() => data.value?.data as AggregatedTimeEntries | undefined);
}
const byTime = grouped('valo-time', () => ({
    group: timeGroup.value,
    fill_gaps_in_time_groups: 'true',
}));
const byUser = grouped('valo-user', () => ({ group: 'user' }));
const byProject = grouped('valo-project', () => ({ group: 'project' }));
const byTag = grouped('valo-tag', () => ({ group: 'tag' }));
const byDescription = grouped('valo-description', () => ({ group: 'description' }));
const before = grouped('valo-before', () => ({
    ...utcRange(previousRange.value[0], previousRange.value[1]),
    group: 'project',
}));

// Forgotten timers (more than 16 h in a row) are left out, like in the weekly report.
const longRange = computed(() => ({ start: startKey.value, end: endKey.value }));
const longRangeBefore = computed(() => ({
    start: previousRange.value[0],
    end: previousRange.value[1],
}));
const { longEntries: allLong } = useValoLongEntries(longRange);
const { longEntries: allLongBefore } = useValoLongEntries(longRangeBefore);
const leavesOutLong = computed(
    () => selectedProjects.value.length === 0 && selectedTags.value.length === 0
);
function relevant(entries: typeof allLong.value) {
    if (!leavesOutLong.value) {
        return [];
    }
    return selectedMembers.value.length > 0
        ? entries.filter((entry) => selectedMembers.value.includes(entry.member_id))
        : entries;
}
const longEntries = computed(() => relevant(allLong.value));
const longSeconds = computed(() =>
    longEntries.value.reduce((sum, entry) => sum + entry.seconds, 0)
);
const longSecondsBefore = computed(() =>
    relevant(allLongBefore.value).reduce((sum, entry) => sum + entry.seconds, 0)
);
function without(seconds: number, long: number): number {
    const rest = seconds - long;
    return rest < 300 ? 0 : rest;
}

const total = computed(() => without(byTime.value?.seconds ?? 0, longSeconds.value));
const totalBefore = computed(() => without(before.value?.seconds ?? 0, longSecondsBefore.value));
const difference = computed(() => total.value - totalBefore.value);

const people = computed(() =>
    (byUser.value?.grouped_data ?? [])
        .map((entry) => {
            const long = longEntries.value
                .filter((item) => item.user_id === entry.key)
                .reduce((sum, item) => sum + item.seconds, 0);
            return {
                key: entry.key ?? 'none',
                memberId: members.value.find((member) => member.user_id === entry.key)?.id ?? null,
                name: getNameForReportingRowEntry(entry.key, 'user') ?? '',
                seconds: without(entry.seconds, long),
            };
        })
        .filter((row) => row.seconds > 0)
        .sort((a, b) => b.seconds - a.seconds || a.name.localeCompare(b.name))
);
const peopleWithoutHours = computed(() => {
    if (!seesAll || hasFilters.value) {
        return [];
    }
    const withHours = new Set(people.value.map((row) => row.key));
    return members.value
        .filter((member) => member.role !== 'placeholder' && !withHours.has(member.user_id))
        .map((member) => member.name)
        .sort((a, b) => a.localeCompare(b));
});
const teamSize = computed(
    () => members.value.filter((member) => member.role !== 'placeholder').length
);

const projectRows = computed(() =>
    (byProject.value?.grouped_data ?? [])
        .map((entry) => {
            const long = longEntries.value
                .filter((item) => item.project_id === (entry.key ?? null))
                .reduce((sum, item) => sum + item.seconds, 0);
            const project = projects.value.find((item) => item.id === entry.key);
            return {
                key: entry.key ?? 'none',
                name: getNameForReportingRowEntry(entry.key, 'project') ?? '',
                color: project?.color ?? NO_PROJECT_COLOR,
                seconds: without(entry.seconds, long),
            };
        })
        .filter((row) => row.seconds > 0)
        .sort((a, b) => b.seconds - a.seconds)
);
const withoutProject = computed(
    () => projectRows.value.find((row) => row.key === 'none')?.seconds ?? 0
);

const tagRows = computed(() =>
    (byTag.value?.grouped_data ?? [])
        .map((entry) => ({
            key: entry.key ?? 'none',
            name: getNameForReportingRowEntry(entry.key, 'tag') ?? '',
            seconds: entry.seconds,
        }))
        .filter((row) => row.seconds > 0)
        .sort((a, b) => b.seconds - a.seconds)
);
const descriptionRows = computed(() =>
    (byDescription.value?.grouped_data ?? [])
        .map((entry) => ({
            key: entry.key ?? '',
            name: entry.key ? entry.key : trans('No description'),
            seconds: entry.seconds,
        }))
        .filter((row) => row.seconds > 0)
        .sort((a, b) => b.seconds - a.seconds)
        .slice(0, 8)
);

const timeBars = computed(() => {
    const rows = (byTime.value?.grouped_data ?? []).map((entry) => {
        const dayKey = entry.key ?? '';
        const long =
            timeGroup.value === 'day'
                ? longEntries.value
                      .filter((item) => item.day === dayKey)
                      .reduce((sum, item) => sum + item.seconds, 0)
                : 0;
        const date = day(dayKey);
        return {
            key: dayKey,
            label:
                timeGroup.value === 'day'
                    ? date.format(periodDays.value > 10 ? 'D' : isPhone.value ? 'dd D' : 'ddd D')
                    : timeGroup.value === 'week'
                      ? date.format('D MMM')
                      : date.format('MMM'),
            seconds: without(entry.seconds, long),
        };
    });
    const max = Math.max(1, ...rows.map((row) => row.seconds));
    return rows.map((row) => ({
        ...row,
        percent: row.seconds > 0 ? Math.max(3, (row.seconds / max) * 100) : 1,
    }));
});
const daysWithHours = computed(() => timeBars.value.filter((row) => row.seconds > 0).length);

function percent(seconds: number, max: number): string {
    return Math.max(2, Math.round((seconds / Math.max(1, max)) * 100)) + '%';
}
function share(seconds: number): string {
    return Math.round((seconds / Math.max(1, total.value)) * 100) + ' %';
}
function formatDuration(seconds: number): string {
    return formatHumanReadableDuration(
        seconds,
        organization?.value?.interval_format,
        organization?.value?.number_format
    );
}
function shortDuration(seconds: number): string {
    return Math.round(seconds / 3600) + 'h';
}
const rangeLabel = computed(() => {
    const start = day(startKey.value);
    const end = day(endKey.value);
    return `${start.format('D MMM')} – ${end.format('D MMM YYYY')}`;
});

// ---- export (PDF / Excel / CSV) of what is on screen
const showExportModal = ref(false);
const exportUrl = ref<string | null>(null);
async function downloadExport(format: ExportFormat) {
    const organizationId = getCurrentOrganizationId();
    if (!organizationId) {
        return;
    }
    const response = await handleApiRequestNotifications(
        () =>
            api.exportAggregatedTimeEntries({
                params: { organization: organizationId },
                queries: {
                    ...filter.value,
                    group: person.value ? 'project' : 'user',
                    sub_group: person.value ? 'description' : 'project',
                    history_group: timeGroup.value,
                    format: format,
                    ...(format === 'pdf' ? { debug: 'true' as const } : {}),
                },
            }),
        trans('Export successful'),
        trans('Export failed')
    );
    const pdfHtml = (response as { html?: string } | undefined)?.html;
    if (format === 'pdf' && pdfHtml) {
        await saveReportPdf(pdfHtml);
        return;
    }
    if (response?.download_url) {
        showExportModal.value = true;
        exportUrl.value = response.download_url as string;
    }
}
</script>

<template>
    <ReportingExportModal
        v-model:show="showExportModal"
        :export-url="exportUrl"></ReportingExportModal>
    <MainContainer class="pt-3 sm:pt-0 sm:h-16 flex flex-wrap gap-y-2 justify-between items-center">
        <div class="flex items-center space-x-3 sm:space-x-6">
            <PageTitle :icon="ChartBarIcon" :title="$t('Reporting')"></PageTitle>
            <ReportingTabNavbar active="reporting" class="hidden sm:flex"></ReportingTabNavbar>
        </div>
        <div class="hidden sm:block">
            <ReportingExportButton :download="downloadExport"></ReportingExportButton>
        </div>
        <ReportingTabNavbar active="reporting" class="w-full sm:hidden"></ReportingTabNavbar>
    </MainContainer>

    <MainContainer class="pt-3 sm:pt-0 pb-10 valo-report" data-testid="valo_report">
        <!-- period and filters -->
        <div class="valo-gcard valo-report-controls" data-testid="report_controls">
            <div class="valo-report-controls-top">
                <div class="valo-period-seg" role="group" :aria-label="$t('Period')">
                    <button
                        v-for="preset in presets"
                        :key="preset.value"
                        type="button"
                        :class="{ on: activePreset === preset.value }"
                        :aria-pressed="activePreset === preset.value"
                        :data-testid="'report_period_' + preset.value"
                        @click="setRange(...presetRange(preset.value))">
                        {{ preset.label }}
                    </button>
                </div>
                <div class="valo-report-range">
                    <DateRangePicker v-model:start="startDate" v-model:end="endDate" allow-future />
                </div>
            </div>
            <div class="valo-report-filters">
                <span class="valo-report-filters-label">{{ $t('Filter by') }}</span>
                <div class="valo-filter" :class="{ on: selectedProjects.length > 0 }">
                    <ValoPicker
                        v-model="selectedProjects"
                        :items="projectItems"
                        :title="$t('Projects')"
                        :search-placeholder="$t('Search project')"
                        :none-label="$t('No Project')">
                        <template #trigger>
                            <button
                                type="button"
                                class="valo-filter-main"
                                data-testid="report_filter_projects">
                                <span
                                    v-if="firstProject && selectedProjects.length === 1"
                                    class="valo-dot"
                                    :style="{ background: firstProject.color }"></span>
                                <FolderIcon v-else class="h-4 w-4 shrink-0 opacity-70" />
                                <span class="truncate">{{
                                    selectedProjects.length > 1
                                        ? $t(':count projects', {
                                              count: String(selectedProjects.length),
                                          })
                                        : selectedProjects.length === 1
                                          ? (firstProject?.name ?? $t('No Project'))
                                          : $t('Projects')
                                }}</span>
                                <ChevronDownIcon
                                    v-if="selectedProjects.length === 0"
                                    class="h-4 w-4 shrink-0 opacity-60" />
                            </button>
                        </template>
                    </ValoPicker>
                    <button
                        v-if="selectedProjects.length > 0"
                        type="button"
                        class="valo-filter-x"
                        :aria-label="$t('Remove filter')"
                        data-testid="report_filter_projects_clear"
                        @click="selectedProjects = []">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
                <div class="valo-filter" :class="{ on: selectedTags.length > 0 }">
                    <ValoPicker
                        v-model="selectedTags"
                        :items="tagItems"
                        :title="$t('Tags')"
                        :search-placeholder="$t('Search tag')">
                        <template #trigger>
                            <button
                                type="button"
                                class="valo-filter-main"
                                data-testid="report_filter_tags">
                                <TagIcon class="h-4 w-4 shrink-0 opacity-70" />
                                <span class="truncate">{{
                                    selectedTags.length > 1
                                        ? $t(':count tags', { count: String(selectedTags.length) })
                                        : selectedTags.length === 1
                                          ? (firstTag?.name ?? $t('No tag'))
                                          : $t('Tags')
                                }}</span>
                                <ChevronDownIcon
                                    v-if="selectedTags.length === 0"
                                    class="h-4 w-4 shrink-0 opacity-60" />
                            </button>
                        </template>
                    </ValoPicker>
                    <button
                        v-if="selectedTags.length > 0"
                        type="button"
                        class="valo-filter-x"
                        :aria-label="$t('Remove filter')"
                        data-testid="report_filter_tags_clear"
                        @click="selectedTags = []">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>
                <button
                    v-if="hasExtraFilters"
                    type="button"
                    class="valo-accent-text hidden text-sm font-medium hover:underline sm:inline"
                    data-testid="report_clear_filters"
                    @click="clearFilters">
                    {{ $t('Clear filters') }}
                </button>
            </div>
        </div>

        <!-- whose summary this is -->
        <div
            v-if="person && seesAll"
            class="mt-4 flex flex-wrap items-center gap-3"
            data-testid="report_person">
            <span
                class="valo-avatar"
                style="width: 40px; height: 40px; font-size: 14px"
                :style="{ background: valoAvatarColor(person.userId) }"
                >{{ valoInitials(person.name) }}</span
            >
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-text-secondary">
                    {{ $t('Summary of') }}
                </div>
                <h2 class="text-xl font-semibold text-text-primary">{{ person.name }}</h2>
            </div>
            <button
                type="button"
                class="valo-pill ml-auto flex items-center gap-2"
                data-testid="report_whole_team"
                @click="showWholeTeam">
                <ArrowLeftIcon class="h-4 w-4" />
                {{ $t('See the whole team') }}
            </button>
        </div>

        <div
            v-if="longEntries.length > 0"
            class="valo-weekly-review mt-4 flex flex-wrap items-center gap-2"
            data-testid="report_review">
            <ExclamationTriangleIcon class="h-4 w-4" />
            <span>
                {{
                    longEntries.length === 1
                        ? $t(
                              '1 entry of more than 16 h in a row (:duration) is not counted here.',
                              {
                                  duration: formatDuration(longSeconds),
                              }
                          )
                        : $t(
                              ':count entries of more than 16 h in a row (:duration) are not counted here.',
                              {
                                  count: String(longEntries.length),
                                  duration: formatDuration(longSeconds),
                              }
                          )
                }}
            </span>
            <Link :href="route('reporting.weekly')" class="ml-auto font-semibold underline">{{
                $t('Review them in Weekly')
            }}</Link>
        </div>

        <!-- big numbers -->
        <div class="mt-4 grid grid-cols-2 gap-3.5 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
            <div class="valo-kpi valo-kpi-main" data-testid="report_total">
                <div class="valo-kpi-label">{{ $t('Hours in the period') }}</div>
                <div class="valo-kpi-value">{{ formatDuration(total) }}</div>
                <div class="valo-kpi-note">
                    <template v-if="totalBefore === 0">{{
                        $t('No hours in the period before')
                    }}</template>
                    <template v-else>
                        {{ difference >= 0 ? '+' : '−' }}{{ formatDuration(Math.abs(difference)) }}
                        {{ $t('than the period before') }} ({{ formatDuration(totalBefore) }})
                    </template>
                </div>
            </div>
            <div class="valo-gcard valo-kpi">
                <div class="valo-kpi-label">{{ $t('Without project') }}</div>
                <div class="valo-kpi-value">{{ total > 0 ? share(withoutProject) : '–' }}</div>
                <div class="valo-kpi-note">
                    {{ formatDuration(withoutProject) }} {{ $t('not assigned to a project') }}
                </div>
            </div>
            <template v-if="person">
                <div class="valo-gcard valo-kpi">
                    <div class="valo-kpi-label">{{ $t('Days with entries') }}</div>
                    <div class="valo-kpi-value">
                        {{ daysWithHours }}
                        <span v-if="timeGroup === 'day'" class="valo-kpi-of"
                            >/ {{ periodDays }}</span
                        >
                    </div>
                    <div class="valo-kpi-note">{{ $t('in this period') }}</div>
                </div>
                <div class="valo-gcard valo-kpi">
                    <div class="valo-kpi-label">{{ $t('Average per day') }}</div>
                    <div class="valo-kpi-value">
                        {{
                            daysWithHours > 0
                                ? formatDuration(Math.round(total / daysWithHours))
                                : '–'
                        }}
                    </div>
                    <div class="valo-kpi-note">{{ $t('on the days with entries') }}</div>
                </div>
            </template>
            <template v-else>
                <div class="valo-gcard valo-kpi">
                    <div class="valo-kpi-label">{{ $t('People with hours') }}</div>
                    <div class="valo-kpi-value">
                        {{ people.length }}
                        <span v-if="!hasFilters" class="valo-kpi-of">/ {{ teamSize }}</span>
                    </div>
                    <div class="valo-kpi-note">{{ $t('tracked something') }}</div>
                </div>
                <div class="valo-gcard valo-kpi">
                    <div class="valo-kpi-label">{{ $t('Average per person') }}</div>
                    <div class="valo-kpi-value">
                        {{
                            people.length > 0
                                ? formatDuration(Math.round(total / people.length))
                                : '–'
                        }}
                    </div>
                    <div class="valo-kpi-note">{{ $t('among those who tracked') }}</div>
                </div>
            </template>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.12fr_1fr]">
            <!-- left column -->
            <div class="flex min-w-0 flex-col gap-4">
                <section v-if="!person" class="valo-gcard valo-rcard" data-testid="report_people">
                    <h2>
                        {{ $t('By person') }}
                        <span>{{ $t('click a name to see their summary') }}</span>
                    </h2>
                    <div class="mt-2">
                        <button
                            v-for="row in people"
                            :key="row.key"
                            type="button"
                            class="valo-bar-row valo-bar-person"
                            :disabled="!row.memberId"
                            data-testid="report_person_row"
                            @click="row.memberId && (selectedMembers = [row.memberId])">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="valo-avatar"
                                    :style="{ background: valoAvatarColor(row.key) }"
                                    >{{ valoInitials(row.name) }}</span
                                >
                                <span class="truncate font-medium">{{ row.name }}</span>
                            </span>
                            <span class="valo-track"
                                ><i
                                    :style="{
                                        width: percent(row.seconds, people[0]?.seconds ?? 1),
                                    }"></i
                            ></span>
                            <span class="text-right font-semibold tabular-nums">{{
                                formatDuration(row.seconds)
                            }}</span>
                        </button>
                        <p v-if="people.length === 0" class="py-6 text-center text-text-secondary">
                            {{ $t('No hours in this period.') }}
                        </p>
                    </div>
                    <p
                        v-if="peopleWithoutHours.length > 0"
                        class="mt-2 border-t border-card-background-separator pt-3 text-sm text-text-secondary">
                        {{ $t('No hours in the period') }} ({{ peopleWithoutHours.length }}):
                        {{ peopleWithoutHours.join(', ') }}
                    </p>
                </section>

                <template v-else>
                    <section class="valo-gcard valo-rcard" data-testid="report_projects">
                        <h2>
                            {{ $t('By project') }} <span>{{ $t('% of the time') }}</span>
                        </h2>
                        <div
                            v-for="row in projectRows"
                            :key="row.key"
                            class="valo-bar-row valo-bar-project">
                            <span class="flex min-w-0 items-center gap-2.5 font-medium">
                                <span class="valo-dot" :style="{ background: row.color }"></span>
                                <span class="truncate">{{ row.name }}</span>
                            </span>
                            <span class="text-right font-semibold tabular-nums">{{
                                formatDuration(row.seconds)
                            }}</span>
                            <span class="text-right text-sm text-text-secondary">{{
                                share(row.seconds)
                            }}</span>
                            <span class="valo-track col-span-3"
                                ><i
                                    :style="{
                                        width: share(row.seconds).replace(' ', ''),
                                        background: row.color,
                                    }"></i
                            ></span>
                        </div>
                        <p
                            v-if="projectRows.length === 0"
                            class="py-6 text-center text-text-secondary">
                            {{ $t('No hours in this period.') }}
                        </p>
                    </section>
                    <section class="valo-gcard valo-rcard" data-testid="report_tags">
                        <h2>
                            {{ $t('Tags used') }} <span>{{ $t('hours with each tag') }}</span>
                        </h2>
                        <div class="mt-2">
                            <div
                                v-for="row in tagRows"
                                :key="row.key"
                                class="valo-bar-row valo-bar-tag">
                                <span class="flex min-w-0 items-center gap-2 font-medium">
                                    <TagIcon class="h-4 w-4 shrink-0 text-text-tertiary" />
                                    <span class="truncate">{{ row.name }}</span>
                                </span>
                                <span class="valo-track"
                                    ><i
                                        :style="{
                                            width: percent(row.seconds, tagRows[0]?.seconds ?? 1),
                                        }"></i
                                ></span>
                                <span class="text-right font-semibold tabular-nums">{{
                                    formatDuration(row.seconds)
                                }}</span>
                            </div>
                            <p
                                v-if="tagRows.length === 0"
                                class="py-6 text-center text-text-secondary">
                                {{ $t('No hours in this period.') }}
                            </p>
                        </div>
                    </section>
                </template>
            </div>

            <!-- right column -->
            <div class="flex min-w-0 flex-col gap-4">
                <section v-if="!person" class="valo-gcard valo-rcard" data-testid="report_projects">
                    <h2>
                        {{ $t('By project') }} <span>{{ $t('% of the time') }}</span>
                    </h2>
                    <div
                        v-for="row in projectRows"
                        :key="row.key"
                        class="valo-bar-row valo-bar-project">
                        <span class="flex min-w-0 items-center gap-2.5 font-medium">
                            <span class="valo-dot" :style="{ background: row.color }"></span>
                            <span class="truncate">{{ row.name }}</span>
                        </span>
                        <span class="text-right font-semibold tabular-nums">{{
                            formatDuration(row.seconds)
                        }}</span>
                        <span class="text-right text-sm text-text-secondary">{{
                            share(row.seconds)
                        }}</span>
                        <span class="valo-track col-span-3"
                            ><i
                                :style="{
                                    width: share(row.seconds).replace(' ', ''),
                                    background: row.color,
                                }"></i
                        ></span>
                    </div>
                    <p v-if="projectRows.length === 0" class="py-6 text-center text-text-secondary">
                        {{ $t('No hours in this period.') }}
                    </p>
                </section>

                <section class="valo-gcard valo-rcard" data-testid="report_days">
                    <h2>
                        {{ timeGroup === 'day' ? $t('Day by day') : $t('Over time') }}
                        <span>{{ rangeLabel }}</span>
                    </h2>
                    <div class="valo-bars">
                        <div
                            v-for="bar in timeBars"
                            :key="bar.key"
                            :style="{ height: bar.percent + '%' }">
                            <span v-if="bar.seconds > 0 && timeBars.length <= 16">{{
                                shortDuration(bar.seconds)
                            }}</span>
                        </div>
                    </div>
                    <div class="valo-bar-labels" :class="{ dense: timeBars.length > 12 }">
                        <span v-for="bar in timeBars" :key="bar.key" class="capitalize">{{
                            bar.label
                        }}</span>
                    </div>
                </section>

                <section
                    v-if="person"
                    class="valo-gcard valo-rcard"
                    data-testid="report_descriptions">
                    <h2>
                        {{ $t('What they worked on') }}
                        <span>{{ $t('the 8 with most hours') }}</span>
                    </h2>
                    <div class="mt-2">
                        <div
                            v-for="row in descriptionRows"
                            :key="row.key"
                            class="valo-bar-row valo-bar-tag">
                            <span class="truncate font-medium" :title="row.name">{{
                                row.name
                            }}</span>
                            <span class="valo-track"
                                ><i
                                    :style="{
                                        width: percent(
                                            row.seconds,
                                            descriptionRows[0]?.seconds ?? 1
                                        ),
                                    }"></i
                            ></span>
                            <span class="text-right font-semibold tabular-nums">{{
                                formatDuration(row.seconds)
                            }}</span>
                        </div>
                        <p
                            v-if="descriptionRows.length === 0"
                            class="py-6 text-center text-text-secondary">
                            {{ $t('No hours in this period.') }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </MainContainer>
</template>
