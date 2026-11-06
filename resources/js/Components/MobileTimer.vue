<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { PlayIcon, StopIcon, TagIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import type { User } from '@/types/models';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import { useProjectsQuery } from '@/utils/useProjectsQuery';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import TagDropdown from '@/packages/ui/src/Tag/TagDropdown.vue';
import { getDayJsInstance, getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { NO_PROJECT_COLOR } from '@/packages/ui/src/FullCalendar/useCalendarEvents';

// VALO's Time on the phone: one big, simple timer card (what, project, tag, clock, start/stop).
const page = usePage<{ auth: { user: User } }>();
const dayjs = getDayJsInstance();

const store = useCurrentTimeEntryStore();
const { currentTimeEntry, isActive, now } = storeToRefs(store);

const { projects } = useProjectsQuery();
const { tags } = useTagsQuery();

const activeProjects = computed(() =>
    projects.value
        .filter((project) => !project.is_archived)
        .sort((a, b) => a.name.localeCompare(b.name))
);
const selectedProject = computed(() =>
    projects.value.find((project) => project.id === currentTimeEntry.value.project_id)
);

onMounted(async () => {
    if (page.props.auth.user.current_team_id) {
        await store.fetchCurrentTimeEntry();
        now.value = dayjs().utc();
    }
});

watch(
    isActive,
    (active) => {
        if (active) {
            store.startLiveTimer();
        } else {
            store.stopLiveTimer();
        }
    },
    { immediate: true }
);

onUnmounted(() => store.stopLiveTimer());

const clock = computed(() => {
    if (!isActive.value || !now.value || !currentTimeEntry.value.start) {
        return '00:00:00';
    }
    const seconds = Math.max(0, now.value.diff(dayjs(currentTimeEntry.value.start), 'second'));
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor(seconds / 60) % 60)}:${pad(seconds % 60)}`;
});

const startedAt = computed(() =>
    isActive.value && currentTimeEntry.value.start
        ? getLocalizedDayJs(currentTimeEntry.value.start).format('HH:mm')
        : ''
);

function saveIfRunning() {
    if (isActive.value) {
        store.updateTimer();
    }
}

function onProjectChange(event: Event) {
    const projectId = (event.target as HTMLSelectElement).value || null;
    currentTimeEntry.value.project_id = projectId;
    currentTimeEntry.value.task_id = null;
    const project = projects.value.find((item) => item.id === projectId);
    currentTimeEntry.value.billable = project?.is_billable ?? false;
    saveIfRunning();
}

async function createTag(name: string) {
    return await useTagsStore().createTag(name);
}

async function toggle() {
    (document.activeElement as HTMLElement | null)?.blur();
    await store.setActiveState(!isActive.value);
}
</script>

<template>
    <div
        class="valo-mobile-timer rounded border bg-card-background p-4 shadow-card"
        :class="isActive ? 'border-red-400/60' : 'border-card-border'"
        data-testid="mobile_timer">
        <input
            v-model="currentTimeEntry.description"
            type="text"
            enterkeyhint="done"
            class="w-full rounded-lg bg-transparent text-text-primary placeholder-text-tertiary focus:border-sky-400 focus:ring-0"
            :class="
                isActive && currentTimeEntry.description
                    ? 'border-transparent px-0 py-1 text-lg font-semibold'
                    : 'border border-card-border px-3 py-2.5 text-base'
            "
            :placeholder="$t('What are you working on?')"
            data-testid="mobile_timer_description"
            @change="saveIfRunning"
            @keydown.enter="($event.target as HTMLInputElement).blur()" />

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <label
                class="relative inline-flex max-w-full items-center gap-1.5 rounded-full border border-card-border px-3 py-1.5 text-sm"
                :class="selectedProject ? 'text-text-primary' : 'valo-accent-text'">
                <span
                    v-if="selectedProject"
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: selectedProject.color || NO_PROJECT_COLOR }"></span>
                <PlusCircleIcon v-else class="h-4 w-4 shrink-0" />
                <span class="truncate">{{ selectedProject?.name ?? $t('Project') }}</span>
                <select
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    :value="currentTimeEntry.project_id ?? ''"
                    :aria-label="$t('Project')"
                    data-testid="mobile_timer_project"
                    @change="onProjectChange">
                    <option value="">{{ $t('No project') }}</option>
                    <option v-for="project in activeProjects" :key="project.id" :value="project.id">
                        {{ project.name }}
                    </option>
                </select>
            </label>

            <TagDropdown
                v-model="currentTimeEntry.tags"
                :tags="tags"
                :create-tag="createTag"
                @changed="saveIfRunning">
                <template #trigger>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-card-border px-3 py-1.5 text-sm text-text-secondary">
                        <TagIcon class="h-4 w-4" />
                        <span v-if="currentTimeEntry.tags.length === 0">{{ $t('Tag') }}</span>
                        <span v-else>{{
                            $t(':count tags', { count: String(currentTimeEntry.tags.length) })
                        }}</span>
                    </button>
                </template>
            </TagDropdown>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <div>
                <div
                    class="text-3xl font-semibold tabular-nums"
                    :class="isActive ? 'text-red-500' : 'text-text-primary'"
                    data-testid="mobile_timer_clock">
                    {{ clock }}
                </div>
                <div v-if="startedAt" class="mt-0.5 text-xs text-text-tertiary">
                    {{ $t('Started at :time', { time: startedAt }) }}
                </div>
            </div>
            <button
                type="button"
                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-white shadow-dropdown transition active:scale-95"
                :class="isActive ? 'bg-red-500' : 'bg-sky-500'"
                :aria-label="isActive ? $t('Stop') : $t('Start')"
                data-testid="mobile_timer_button"
                @click="toggle">
                <StopIcon v-if="isActive" class="h-7 w-7" />
                <PlayIcon v-else class="ml-1 h-8 w-8" />
            </button>
        </div>
    </div>
</template>
