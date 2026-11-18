<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import {
    PencilSquareIcon,
    TagIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/20/solid';
import { trans } from 'laravel-vue-i18n';
import ValoPicker from '@/Components/ValoPicker.vue';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useTagsStore } from '@/utils/useTags';
import { getCurrentOrganizationId } from '@/utils/useUser';

// VALO's Time: "Crear rutina". Each member adds the things they do every week (a class, a weekly call...).
// They appear dashed in their own calendar and only count after "OK, asistí".
type Routine = {
    id: string;
    name: string;
    weekday: number;
    start_time: string;
    end_time: string;
    tag_ids: string[];
};

const show = defineModel<boolean>('show', { default: false });
const queryClient = useQueryClient();
const { tags } = useTagsQuery();

const { data: routines } = useQuery({
    queryKey: computed(() => ['valoRoutines', getCurrentOrganizationId()]),
    queryFn: async () =>
        (
            await axios.get<{ data: Routine[] }>(
                route('valo.routines.index', { organizationId: getCurrentOrganizationId() })
            )
        ).data.data,
    enabled: computed(() => show.value && !!getCurrentOrganizationId()),
});

const weekdays = computed(() => [
    { value: 1, short: trans('Mon'), long: trans('Monday') },
    { value: 2, short: trans('Tue'), long: trans('Tuesday') },
    { value: 3, short: trans('Wed'), long: trans('Wednesday') },
    { value: 4, short: trans('Thu'), long: trans('Thursday') },
    { value: 5, short: trans('Fri'), long: trans('Friday') },
    { value: 6, short: trans('Sat'), long: trans('Saturday') },
    { value: 7, short: trans('Sun'), long: trans('Sunday') },
]);

const editingId = ref<string | null>(null);
const name = ref('');
const weekday = ref(1);
const from = ref('10:00');
const to = ref('11:00');
const tagIds = ref<string[]>([]);
const saving = ref(false);
const error = ref('');

function reset() {
    editingId.value = null;
    name.value = '';
    weekday.value = 1;
    from.value = '10:00';
    to.value = '11:00';
    tagIds.value = [];
    error.value = '';
}
watch(show, (open) => open && reset());

function edit(routine: Routine) {
    editingId.value = routine.id;
    name.value = routine.name;
    weekday.value = routine.weekday;
    from.value = routine.start_time;
    to.value = routine.end_time;
    tagIds.value = [...routine.tag_ids];
    error.value = '';
}

const canSave = computed(
    () => !saving.value && name.value.trim() !== '' && from.value !== '' && to.value > from.value
);

function refresh() {
    queryClient.invalidateQueries({ queryKey: ['valoRoutines'] });
    queryClient.invalidateQueries({ queryKey: ['valoMeetings'] });
}

async function save() {
    if (!canSave.value) {
        return;
    }
    saving.value = true;
    error.value = '';
    const body = {
        name: name.value.trim(),
        weekday: weekday.value,
        start_time: from.value,
        end_time: to.value,
        tag_ids: tagIds.value,
    };
    const organizationId = getCurrentOrganizationId();
    try {
        if (editingId.value) {
            await axios.put(
                route('valo.routines.update', { organizationId, meetingId: editingId.value }),
                body
            );
        } else {
            await axios.post(route('valo.routines.store', { organizationId }), body);
        }
        refresh();
        reset();
    } catch {
        error.value = trans('It could not be saved. Check the hours and try again.');
    } finally {
        saving.value = false;
    }
}

async function remove(routine: Routine) {
    if (!window.confirm(trans('Delete the routine ":name"?', { name: routine.name }))) {
        return;
    }
    await axios.delete(
        route('valo.routines.destroy', {
            organizationId: getCurrentOrganizationId(),
            meetingId: routine.id,
        })
    );
    if (editingId.value === routine.id) {
        reset();
    }
    refresh();
}

function dayName(value: number): string {
    return weekdays.value.find((day) => day.value === value)?.long ?? '';
}
function tagNames(ids: string[]): string {
    return ids
        .map((id) => tags.value.find((tag) => tag.id === id)?.name)
        .filter(Boolean)
        .join(', ');
}
const tagItems = computed(() => tags.value.map((tag) => ({ id: tag.id, name: tag.name })));
async function createTag(tagName: string) {
    const tag = await useTagsStore().createTag(tagName);
    if (tag) {
        tagIds.value = [...tagIds.value, tag.id];
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/35 sm:items-center sm:p-4"
            data-testid="routine_sheet"
            @click.self="show = false">
            <div
                class="valo-floating-sheet max-h-[92vh] w-full overflow-y-auto border border-card-border bg-default-background p-5 pb-8 shadow-dropdown sm:max-w-md sm:pb-5 valo-routine-sheet">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-text-primary">
                            {{ editingId ? $t('Edit routine') : $t('Create routine') }}
                        </h2>
                        <p class="text-sm text-text-secondary">
                            {{
                                $t(
                                    'Something you do every week. It appears in your calendar and counts when you press OK.'
                                )
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-text-tertiary"
                        :aria-label="$t('Close')"
                        @click="show = false">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="valo-mobile-label">{{ $t('Name') }}</div>
                <input
                    v-model="name"
                    type="text"
                    enterkeyhint="done"
                    maxlength="255"
                    class="w-full rounded-xl border border-card-border bg-card-background px-3.5 py-3 text-base text-text-primary placeholder-text-tertiary focus:border-sky-400 focus:ring-0"
                    :placeholder="$t('E.g. English class')"
                    data-testid="routine_name" />

                <div class="valo-mobile-label">{{ $t('Every') }}</div>
                <div class="grid grid-cols-7 gap-1.5" role="group" :aria-label="$t('Day')">
                    <button
                        v-for="day in weekdays"
                        :key="day.value"
                        type="button"
                        class="valo-routine-day"
                        :class="{ on: weekday === day.value }"
                        :aria-pressed="weekday === day.value"
                        :title="day.long"
                        :data-testid="'routine_day_' + day.value"
                        @click="weekday = day.value">
                        {{ day.short }}
                    </button>
                </div>

                <div class="valo-mobile-label">{{ $t('From – To') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <input
                        v-model="from"
                        type="time"
                        step="300"
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3 text-center text-xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('From')"
                        data-testid="routine_from" />
                    <input
                        v-model="to"
                        type="time"
                        step="300"
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3 text-center text-xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('To')"
                        data-testid="routine_to" />
                </div>

                <div class="valo-mobile-label">{{ $t('Tag (optional)') }}</div>
                <ValoPicker
                    v-model="tagIds"
                    :items="tagItems"
                    :title="$t('Tags')"
                    :search-placeholder="$t('Search tag')"
                    :create-label="$t('Create tag')"
                    @create="createTag">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-card-border bg-card-background px-3.5 py-2 text-sm text-text-secondary">
                            <TagIcon class="h-4 w-4" />
                            {{ tagIds.length === 0 ? $t('No tag') : tagNames(tagIds) }}
                        </button>
                    </template>
                </ValoPicker>

                <p v-if="error" class="mt-3 text-sm text-red-500">{{ error }}</p>
                <div class="mt-5 flex gap-3">
                    <button
                        v-if="editingId"
                        type="button"
                        class="rounded-2xl border border-card-border bg-card-background px-4 py-3.5 text-sm font-medium text-text-secondary"
                        @click="reset">
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-2xl bg-sky-500 py-3.5 text-base font-semibold text-white shadow-card transition active:scale-[0.99] disabled:opacity-50"
                        :disabled="!canSave"
                        data-testid="routine_save"
                        @click="save">
                        {{ editingId ? $t('Save changes') : $t('Create routine') }}
                    </button>
                </div>

                <template v-if="(routines ?? []).length > 0">
                    <div class="valo-mobile-label mt-6">{{ $t('Your routines') }}</div>
                    <div
                        class="overflow-hidden rounded-2xl border border-card-border bg-card-background">
                        <div
                            v-for="routine in routines"
                            :key="routine.id"
                            class="flex items-center gap-3 border-t border-card-background-separator px-4 py-3 first:border-t-0"
                            data-testid="routine_row">
                            <CalendarDaysIcon class="h-4 w-4 shrink-0 valo-accent-text" />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-semibold text-text-primary"
                                    >{{ routine.name }}</span
                                >
                                <span class="block text-xs text-text-secondary"
                                    >{{ dayName(routine.weekday) }} · {{ routine.start_time }} –
                                    {{ routine.end_time
                                    }}<template v-if="routine.tag_ids.length">
                                        · {{ tagNames(routine.tag_ids) }}</template
                                    ></span
                                >
                            </span>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-text-tertiary hover:text-text-primary"
                                :aria-label="$t('Edit')"
                                @click="edit(routine)">
                                <PencilSquareIcon class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-text-tertiary hover:text-red-500"
                                :aria-label="$t('Delete')"
                                data-testid="routine_delete"
                                @click="remove(routine)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
