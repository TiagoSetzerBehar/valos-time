<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { PlusCircleIcon, TagIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { trans } from 'laravel-vue-i18n';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import { useTimeEntriesMutations } from '@/utils/useTimeEntriesMutations';
import TagDropdown from '@/packages/ui/src/Tag/TagDropdown.vue';
import {
    formatWeekday,
    getDayJsInstance,
    getLocalizedDayJs,
    getLocalizedDayJsFromMinutes,
} from '@/packages/ui/src/utils/time';
import { NO_PROJECT_COLOR } from '@/packages/ui/src/FullCalendar/useCalendarEvents';

// VALO's Time on the phone: "Añadir horas a mano" — what, project/tag, day, from–to, save.
const show = defineModel<boolean>('show', { default: false });

const { projects } = useProjectsQuery();
const { tags } = useTagsQuery();
const { createTimeEntry } = useTimeEntriesMutations();

const description = ref('');
const projectId = ref<string | null>(null);
const tagIds = ref<string[]>([]);
const day = ref('');
const from = ref('');
const to = ref('');
const saving = ref(false);

function toClock(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

// Fresh form each time it opens: today, the last hour rounded to 15 minutes.
watch(show, (open) => {
    if (!open) {
        return;
    }
    const now = getLocalizedDayJs();
    const end = Math.max(60, Math.floor((now.hour() * 60 + now.minute()) / 15) * 15);
    description.value = '';
    projectId.value = null;
    tagIds.value = [];
    day.value = now.format('YYYY-MM-DD');
    from.value = toClock(end - 60);
    to.value = toClock(end);
});

const activeProjects = computed(() =>
    projects.value
        .filter((project) => !project.is_archived)
        .sort((a, b) => a.name.localeCompare(b.name))
);
const selectedProject = computed(() => projects.value.find((p) => p.id === projectId.value));

function minutesOf(clock: string): number | null {
    const match = /^(\d{1,2}):(\d{2})/.exec(clock);
    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
}

const durationMinutes = computed(() => {
    const start = minutesOf(from.value);
    const end = minutesOf(to.value);
    return start === null || end === null ? null : end - start;
});

const durationLabel = computed(() => {
    const minutes = durationMinutes.value;
    if (minutes === null || minutes <= 0) {
        return trans('The end must be after the start');
    }
    return `= ${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}min`;
});

const dayLabel = computed(() => {
    if (!day.value) {
        return '';
    }
    const today = getLocalizedDayJs().format('YYYY-MM-DD');
    const weekday = formatWeekday(day.value);
    const number = getDayJsInstance()(day.value).format('D');
    if (day.value === today) {
        return `${trans('Today')}, ${weekday} ${number}`;
    }
    return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${number}`;
});

const canSave = computed(() => !saving.value && !!day.value && (durationMinutes.value ?? 0) > 0);

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}

async function save() {
    const start = minutesOf(from.value);
    const end = minutesOf(to.value);
    if (!canSave.value || start === null || end === null) {
        return;
    }
    saving.value = true;
    try {
        await createTimeEntry({
            description: description.value,
            project_id: projectId.value,
            task_id: null,
            tags: tagIds.value,
            billable: selectedProject.value?.is_billable ?? false,
            type: 'work',
            start: getLocalizedDayJsFromMinutes(day.value, start).utc().format(),
            end: getLocalizedDayJsFromMinutes(day.value, end).utc().format(),
        });
        show.value = false;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-end bg-black/30"
            data-testid="mobile_manual_entry"
            @click.self="show = false">
            <div
                class="valo-floating-sheet max-h-[92vh] w-full overflow-y-auto border border-card-border bg-default-background p-5 pb-8 shadow-dropdown"
                style="border-radius: 1.5rem 1.5rem 0 0">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-text-primary">
                        {{ $t('Add hours by hand') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-text-tertiary"
                        :aria-label="$t('Close')"
                        @click="show = false">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="valo-mobile-label">{{ $t('What did you do?') }}</div>
                <input
                    v-model="description"
                    type="text"
                    enterkeyhint="done"
                    class="w-full rounded-xl border border-card-border bg-card-background px-3.5 py-3 text-base text-text-primary placeholder-text-tertiary focus:border-sky-400 focus:ring-0"
                    :placeholder="$t('E.g. meeting with a client')"
                    data-testid="mobile_manual_description" />

                <div class="valo-mobile-label">{{ $t('Project and tag') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    <label
                        class="relative inline-flex max-w-full items-center gap-1.5 rounded-full border border-card-border bg-card-background px-3 py-1.5 text-sm"
                        :class="selectedProject ? 'text-text-primary' : 'valo-accent-text'">
                        <span
                            v-if="selectedProject"
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :style="{
                                backgroundColor: selectedProject.color || NO_PROJECT_COLOR,
                            }"></span>
                        <PlusCircleIcon v-else class="h-4 w-4 shrink-0" />
                        <span class="truncate">{{ selectedProject?.name ?? $t('Project') }}</span>
                        <select
                            v-model="projectId"
                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            :aria-label="$t('Project')"
                            data-testid="mobile_manual_project">
                            <option :value="null">{{ $t('No project') }}</option>
                            <option
                                v-for="project in activeProjects"
                                :key="project.id"
                                :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </label>
                    <TagDropdown v-model="tagIds" :tags="tags" :create-tag="createTag">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border border-card-border bg-card-background px-3 py-1.5 text-sm text-text-secondary">
                                <TagIcon class="h-4 w-4" />
                                <span v-if="tagIds.length === 0">{{ $t('Tag') }}</span>
                                <span v-else>{{
                                    $t(':count tags', { count: String(tagIds.length) })
                                }}</span>
                            </button>
                        </template>
                    </TagDropdown>
                </div>

                <div class="valo-mobile-label">{{ $t('Day') }}</div>
                <label
                    class="relative block w-full rounded-xl border border-card-border bg-card-background px-3.5 py-3 text-base text-text-primary">
                    {{ dayLabel }}
                    <input
                        v-model="day"
                        type="date"
                        required
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                        :aria-label="$t('Day')"
                        data-testid="mobile_manual_day" />
                </label>

                <div class="valo-mobile-label">{{ $t('From – To') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <input
                        v-model="from"
                        type="time"
                        step="300"
                        required
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3.5 text-center text-2xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('From')"
                        data-testid="mobile_manual_from" />
                    <input
                        v-model="to"
                        type="time"
                        step="300"
                        required
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3.5 text-center text-2xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('To')"
                        data-testid="mobile_manual_to" />
                </div>
                <div
                    class="mt-2 text-center text-sm"
                    :class="(durationMinutes ?? 0) > 0 ? 'text-text-tertiary' : 'text-red-500'"
                    data-testid="mobile_manual_duration">
                    {{ durationLabel }}
                </div>

                <button
                    type="button"
                    class="mt-5 w-full rounded-2xl bg-sky-500 py-4 text-base font-semibold text-white shadow-card transition active:scale-[0.99] disabled:opacity-50"
                    :disabled="!canSave"
                    data-testid="mobile_manual_save"
                    @click="save">
                    {{ $t('Save') }}
                </button>
            </div>
        </div>
    </Teleport>
</template>
