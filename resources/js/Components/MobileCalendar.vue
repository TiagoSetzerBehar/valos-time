<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type { Dayjs } from 'dayjs';
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    PlusIcon,
} from '@heroicons/vue/20/solid';
import type {
    Client,
    CreateClientBody,
    CreateProjectBody,
    Project,
    TimeEntry,
} from '@/packages/api/src';
import TimeEntryEditModal from '@/packages/ui/src/TimeEntry/TimeEntryEditModal.vue';
import MobileManualEntry from '@/Components/MobileManualEntry.vue';
import ValoMeetingConfirm from '@/Components/ValoMeetingConfirm.vue';
import ValoRoutineSheet from '@/Components/ValoRoutineSheet.vue';
import {
    useTimeEntriesCalendarQuery,
    getInitialWeekRange,
} from '@/utils/useTimeEntriesCalendarQuery';
import { useTimeEntriesMutations } from '@/utils/useTimeEntriesMutations';
import { useMeetingOccurrences, type MeetingOccurrence } from '@/utils/useValoMeetings';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTasksQuery } from '@/utils/useTasksQuery';
import { useClientsQuery } from '@/utils/useClientsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import { useProjectsStore } from '@/utils/useProjects';
import { useClientsStore } from '@/utils/useClients';
import { getOrganizationCurrencyString } from '@/utils/money';
import { canCreateProjects } from '@/utils/permissions';
import { isAllowedToPerformPremiumAction } from '@/utils/billing';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { getDayJsInstance, getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { NO_PROJECT_COLOR } from '@/packages/ui/src/FullCalendar/useCalendarEvents';

// VALO's Time calendar on the phone: pick a day in the week strip, see that day as a clear timeline.
// Tapping never creates anything by accident: entries open to edit, routines open "OK, asistí", and new
// hours go through the buttons at the bottom.
const HOUR = 60; // px per hour

const props = defineProps<{ initialDate?: string | null }>();
const dayjs = getDayJsInstance();
const todayKey = getLocalizedDayJs().format('YYYY-MM-DD');

const firstWeek = getInitialWeekRange().start;
const startDay = props.initialDate ? dayjs(props.initialDate) : getLocalizedDayJs();
const weekStart = ref<Dayjs>(
    firstWeek.add(Math.floor(startDay.startOf('day').diff(firstWeek, 'day') / 7) * 7, 'day')
);
const selected = ref<string>(startDay.format('YYYY-MM-DD'));

const weekEnd = computed(() => weekStart.value.add(7, 'day'));
const queryStart = computed<Dayjs | undefined>(() => weekStart.value);
const queryEnd = computed<Dayjs | undefined>(() => weekEnd.value);
const { data: response } = useTimeEntriesCalendarQuery(queryStart, queryEnd);
const entries = computed<TimeEntry[]>(() => response.value?.data ?? []);

const meetingRange = computed(() => ({
    start: weekStart.value.format('YYYY-MM-DD'),
    end: weekStart.value.add(6, 'day').format('YYYY-MM-DD'),
}));
const { occurrences } = useMeetingOccurrences(meetingRange);

const { projects } = useProjectsQuery();
const { tasks } = useTasksQuery();
const { clients } = useClientsQuery();
const { tags } = useTagsQuery();
const { organization } = useOrganizationQuery(getCurrentOrganizationId()!);
const { updateTimeEntry, deleteTimeEntry } = useTimeEntriesMutations();

function localKey(iso: string): string {
    return getLocalizedDayJs(iso).format('YYYY-MM-DD');
}
function endOf(entry: TimeEntry): Dayjs {
    return entry.end ? getLocalizedDayJs(entry.end) : getLocalizedDayJs();
}
function seconds(entry: TimeEntry): number {
    return Math.max(0, endOf(entry).diff(getLocalizedDayJs(entry.start), 'second'));
}
function clock(totalSeconds: number): string {
    const minutes = Math.round(totalSeconds / 60);
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}`;
}

const days = computed(() =>
    Array.from({ length: 7 }, (_, index) => {
        const date = weekStart.value.add(index, 'day');
        const dayKey = date.format('YYYY-MM-DD');
        const total = entries.value
            .filter((entry) => localKey(entry.start) === dayKey)
            .reduce((sum, entry) => sum + seconds(entry), 0);
        return {
            key: dayKey,
            name: date.format('dd'),
            number: date.format('D'),
            total,
            isToday: dayKey === todayKey,
        };
    })
);
const weekLabel = computed(
    () => `${weekStart.value.format('D MMM')} – ${weekStart.value.add(6, 'day').format('D MMM')}`
);
const selectedLabel = computed(() => {
    const date = dayjs(selected.value);
    const text = date.format('dddd D [de] MMMM');
    return text.charAt(0).toUpperCase() + text.slice(1);
});
const selectedTotal = computed(
    () => days.value.find((day) => day.key === selected.value)?.total ?? 0
);

function moveWeek(direction: 1 | -1) {
    weekStart.value = weekStart.value.add(direction * 7, 'day');
    const index = dayjs(selected.value).diff(weekStart.value.subtract(direction * 7, 'day'), 'day');
    selected.value = weekStart.value
        .add(Math.min(6, Math.max(0, index)), 'day')
        .format('YYYY-MM-DD');
}
function goToday() {
    weekStart.value = getInitialWeekRange().start;
    selected.value = todayKey;
}

type Block = {
    key: string;
    kind: 'entry' | 'meeting';
    top: number;
    height: number;
    lane: number;
    lanes: number;
    title: string;
    subtitle: string;
    time: string;
    color: string;
    entry?: TimeEntry;
    meeting?: MeetingOccurrence;
};

const blocks = computed<Block[]>(() => {
    const dayStart = dayjs(selected.value).startOf('day');
    const items: Omit<Block, 'lane' | 'lanes'>[] = [];
    for (const entry of entries.value) {
        if (localKey(entry.start) !== selected.value) {
            continue;
        }
        const start = getLocalizedDayJs(entry.start);
        const end = endOf(entry);
        const project = projects.value.find((item) => item.id === entry.project_id);
        const top = (start.diff(dayStart, 'minute') / 60) * HOUR;
        items.push({
            key: entry.id,
            kind: 'entry',
            top,
            height: Math.max(
                26,
                Math.min(24 * HOUR - top, (end.diff(start, 'minute') / 60) * HOUR)
            ),
            title: entry.description || '—',
            subtitle: project?.name ?? '',
            time: `${start.format('HH:mm')} – ${entry.end ? end.format('HH:mm') : '…'}`,
            color: project?.color ?? NO_PROJECT_COLOR,
            entry,
        });
    }
    for (const meeting of occurrences.value) {
        if (meeting.date !== selected.value || meeting.time_entry_id !== null) {
            continue;
        }
        const start = getLocalizedDayJs(meeting.start);
        const end = getLocalizedDayJs(meeting.end);
        items.push({
            key: meeting.meeting_id + meeting.date,
            kind: 'meeting',
            top: (start.diff(dayStart, 'minute') / 60) * HOUR,
            height: Math.max(26, (end.diff(start, 'minute') / 60) * HOUR),
            title: meeting.name,
            subtitle: '',
            time: `${meeting.start_time} – ${meeting.end_time}`,
            color: '#0284c7',
            meeting,
        });
    }
    items.sort((a, b) => a.top - b.top || b.height - a.height);
    // side by side when they overlap
    const placed: Block[] = [];
    let group: Block[] = [];
    let groupEnd = -1;
    const closeGroup = () => {
        const lanes = Math.max(1, ...group.map((block) => block.lane + 1));
        group.forEach((block) => (block.lanes = lanes));
        group = [];
    };
    for (const item of items) {
        if (item.top >= groupEnd && group.length > 0) {
            closeGroup();
        }
        const used = new Set(
            group.filter((block) => block.top + block.height > item.top).map((block) => block.lane)
        );
        let lane = 0;
        while (used.has(lane)) {
            lane++;
        }
        const block: Block = { ...item, lane, lanes: 1 };
        group.push(block);
        placed.push(block);
        groupEnd = Math.max(groupEnd, item.top + item.height);
    }
    if (group.length > 0) {
        closeGroup();
    }
    return placed;
});

const nowTop = ref(-1);
function updateNow() {
    const now = getLocalizedDayJs();
    nowTop.value =
        selected.value === todayKey ? ((now.hour() * 60 + now.minute()) / 60) * HOUR : -1;
}
watch(selected, updateNow, { immediate: true });

const timeline = ref<HTMLDivElement | null>(null);
async function scrollToFirst() {
    await nextTick();
    const first = blocks.value[0]?.top ?? (selected.value === todayKey ? nowTop.value : 8 * HOUR);
    const target = Math.max(0, Math.min(first, nowTop.value >= 0 ? nowTop.value : first) - HOUR);
    if (timeline.value) {
        timeline.value.scrollTop = target;
    }
}
onMounted(scrollToFirst);
watch(selected, scrollToFirst);
watch(
    () => blocks.value.length,
    (now, before) => before === 0 && now > 0 && scrollToFirst()
);

// editing
const editEntry = ref<TimeEntry | null>(null);
const showEdit = ref(false);
const meetingToConfirm = ref<MeetingOccurrence | null>(null);
const showMeeting = ref(false);
const manualOpen = ref(false);
const routineOpen = ref(false);
function open(block: Block) {
    if (block.entry) {
        editEntry.value = block.entry;
        showEdit.value = true;
    } else if (block.meeting) {
        meetingToConfirm.value = block.meeting;
        showMeeting.value = true;
    }
}
async function saveEntry(entry: TimeEntry) {
    await updateTimeEntry(entry);
}
async function removeEntry(id: string) {
    await deleteTimeEntry(id);
}
async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}
async function createProject(project: CreateProjectBody): Promise<Project | undefined> {
    return await useProjectsStore().createProject(project);
}
async function createClient(body: CreateClientBody): Promise<Client | undefined> {
    return await useClientsStore().createClient(body);
}
</script>

<template>
    <div class="valo-mcal px-3 pt-3 pb-28" data-testid="mobile_calendar">
        <div class="valo-gcard valo-mcal-head">
            <div class="flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="valo-weekly-arrow"
                    :aria-label="$t('Previous week')"
                    @click="moveWeek(-1)">
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <span class="text-sm font-semibold text-text-primary">{{ weekLabel }}</span>
                <div class="flex items-center gap-1">
                    <button
                        v-if="!days.some((day) => day.isToday) || selected !== todayKey"
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold valo-accent-text"
                        @click="goToday">
                        {{ $t('Today') }}
                    </button>
                    <button
                        type="button"
                        class="valo-weekly-arrow"
                        :aria-label="$t('Next week')"
                        @click="moveWeek(1)">
                        <ChevronRightIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <div class="mt-2 grid grid-cols-7 gap-1">
                <button
                    v-for="day in days"
                    :key="day.key"
                    type="button"
                    class="valo-mcal-day"
                    :class="{ on: day.key === selected, today: day.isToday }"
                    :data-testid="'mobile_calendar_day_' + day.key"
                    @click="selected = day.key">
                    <span class="capitalize">{{ day.name }}</span>
                    <b>{{ day.number }}</b>
                    <small>{{ day.total > 0 ? clock(day.total) : '·' }}</small>
                </button>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-2 px-1">
            <h2 class="min-w-0 truncate text-base font-semibold text-text-primary">
                {{ selectedLabel }}
                <span class="ml-1 text-sm font-semibold tabular-nums text-text-secondary">{{
                    clock(selectedTotal)
                }}</span>
            </h2>
            <button
                type="button"
                class="valo-routine-chip shrink-0"
                data-testid="mobile_calendar_routine"
                @click="routineOpen = true">
                <PlusIcon class="h-4 w-4" />
                {{ $t('Routine') }}
            </button>
        </div>

        <div ref="timeline" class="valo-gcard valo-mcal-timeline mt-2">
            <div class="relative" :style="{ height: 24 * HOUR + 'px' }">
                <div
                    v-for="hour in 24"
                    :key="hour"
                    class="valo-mcal-hour"
                    :style="{ top: (hour - 1) * HOUR + 'px' }">
                    <span>{{ String(hour - 1).padStart(2, '0') }}:00</span>
                </div>
                <div v-if="nowTop >= 0" class="valo-mcal-now" :style="{ top: nowTop + 'px' }"></div>
                <button
                    v-for="block in blocks"
                    :key="block.key"
                    type="button"
                    class="valo-mcal-block"
                    :class="block.kind"
                    :style="{
                        top: block.top + 'px',
                        height: block.height + 'px',
                        left: `calc(52px + (100% - 58px) * ${block.lane / block.lanes})`,
                        width: `calc((100% - 58px) / ${block.lanes} - 3px)`,
                        '--c': block.color,
                    }"
                    :data-testid="'mobile_calendar_' + block.kind"
                    @click="open(block)">
                    <b class="truncate">{{ block.title }}</b>
                    <span v-if="block.height >= 44" class="truncate"
                        >{{ block.time
                        }}<template v-if="block.subtitle"> · {{ block.subtitle }}</template></span
                    >
                    <em v-if="block.kind === 'meeting' && block.height >= 60">{{
                        $t('Tap to confirm')
                    }}</em>
                </button>
            </div>
        </div>

        <div class="valo-mcal-actions">
            <button
                type="button"
                class="valo-mcal-add"
                data-testid="mobile_calendar_add"
                @click="manualOpen = true">
                <PlusIcon class="h-5 w-5" />
                {{ $t('Add hours') }}
            </button>
        </div>

        <MobileManualEntry v-model:show="manualOpen"></MobileManualEntry>
        <ValoRoutineSheet v-model:show="routineOpen"></ValoRoutineSheet>
        <ValoMeetingConfirm
            v-model:show="showMeeting"
            :occurrence="meetingToConfirm"></ValoMeetingConfirm>
        <TimeEntryEditModal
            v-model:show="showEdit"
            :time-entry="editEntry"
            :enable-estimated-time="isAllowedToPerformPremiumAction()"
            :update-time-entry="saveEntry"
            :delete-time-entry="removeEntry"
            :create-client
            :create-project
            :create-tag
            :tags
            :projects
            :tasks
            :clients
            :currency="getOrganizationCurrencyString()"
            :organization-billable-rate="organization?.billable_rate ?? null"
            :can-create-project="canCreateProjects()"></TimeEntryEditModal>
    </div>
</template>
