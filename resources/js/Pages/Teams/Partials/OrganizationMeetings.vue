<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';
import { PencilSquareIcon, TrashIcon, PlusIcon } from '@heroicons/vue/20/solid';
import FormSection from '@/Components/FormSection.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import SecondaryButton from '@/packages/ui/src/Buttons/SecondaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import { Checkbox } from '@/packages/ui/src';
import { Field, FieldLabel } from '@/packages/ui/src/field';
import { useNotificationsStore } from '@/utils/notification';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useMembersQuery } from '@/utils/useMembersQuery';
import type { Organization } from '@/types/models';

// VALO's Time: weekly meetings that appear prepared in the calendar of their members (counted after OK).
type Meeting = {
    id?: string;
    name: string;
    weekday: number;
    start_time: string;
    end_time: string;
    tag_ids: string[];
    member_ids: string[] | null;
};

const props = defineProps<{ team: Organization }>();

const { addNotification } = useNotificationsStore();
const { tags } = useTagsQuery();
const { members } = useMembersQuery();

const meetings = ref<Meeting[]>([]);
const editing = ref<Meeting | null>(null);
const saving = ref(false);

const weekdays = computed(() => [
    { value: 1, label: trans('Monday') },
    { value: 2, label: trans('Tuesday') },
    { value: 3, label: trans('Wednesday') },
    { value: 4, label: trans('Thursday') },
    { value: 5, label: trans('Friday') },
    { value: 6, label: trans('Saturday') },
    { value: 7, label: trans('Sunday') },
]);

const sortedMembers = computed(() =>
    [...members.value].sort((a, b) => a.name.localeCompare(b.name))
);

async function load() {
    const response = await axios.get<{ data: Meeting[] }>(
        route('valo.meetings.index', props.team.id)
    );
    meetings.value = response.data.data;
}

onMounted(load);

function weekdayName(weekday: number) {
    return weekdays.value.find((day) => day.value === weekday)?.label ?? '';
}

function tagNames(ids: string[]) {
    return ids
        .map((id) => tags.value.find((tag) => tag.id === id)?.name)
        .filter(Boolean)
        .join(', ');
}

function whoLabel(meeting: Meeting) {
    if (meeting.member_ids === null) {
        return trans('Everyone');
    }
    return meeting.member_ids
        .map((id) => members.value.find((member) => member.id === id)?.name)
        .filter(Boolean)
        .join(', ');
}

function startNew() {
    editing.value = {
        name: '',
        weekday: 1,
        start_time: '10:00',
        end_time: '11:00',
        tag_ids: [],
        member_ids: null,
    };
}

function edit(meeting: Meeting) {
    editing.value = {
        ...meeting,
        tag_ids: [...meeting.tag_ids],
        member_ids: meeting.member_ids ? [...meeting.member_ids] : null,
    };
}

function toggle(list: string[], id: string, checked: boolean) {
    const index = list.indexOf(id);
    if (checked && index === -1) {
        list.push(id);
    }
    if (!checked && index !== -1) {
        list.splice(index, 1);
    }
}

const everyone = computed({
    get: () => editing.value?.member_ids === null,
    set: (value: boolean) => {
        if (editing.value) {
            editing.value.member_ids = value ? null : [];
        }
    },
});

async function save() {
    if (!editing.value) {
        return;
    }
    saving.value = true;
    try {
        const payload = { ...editing.value };
        if (payload.id) {
            await axios.put(route('valo.meetings.update', [props.team.id, payload.id]), payload);
        } else {
            await axios.post(route('valo.meetings.store', props.team.id), payload);
        }
        editing.value = null;
        await load();
        addNotification('success', trans('Meeting saved'));
    } catch {
        addNotification(
            'error',
            trans('Check the name, the times (end after start) and who attends.')
        );
    } finally {
        saving.value = false;
    }
}

async function remove(meeting: Meeting) {
    if (!meeting.id || !window.confirm(trans('Delete this meeting? Hours already counted stay.'))) {
        return;
    }
    await axios.delete(route('valo.meetings.destroy', [props.team.id, meeting.id]));
    await load();
}
</script>

<template>
    <FormSection data-testid="meeting_settings">
        <template #title>{{ $t('Weekly meetings') }}</template>
        <template #description>
            {{
                $t(
                    'They appear prepared every week in the calendar of the people who attend. They only count when each person presses OK.'
                )
            }}
        </template>

        <template #form>
            <div class="col-span-6 space-y-3">
                <div
                    v-for="meeting in meetings"
                    :key="meeting.id"
                    class="flex items-start gap-3 rounded-xl border border-card-border bg-card-background px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-text-primary">{{ meeting.name }}</div>
                        <div class="text-sm text-text-secondary">
                            {{ weekdayName(meeting.weekday) }} · {{ meeting.start_time }}–{{
                                meeting.end_time
                            }}
                            <span v-if="meeting.tag_ids.length">
                                · {{ tagNames(meeting.tag_ids) }}</span
                            >
                        </div>
                        <div class="text-xs text-text-tertiary">{{ whoLabel(meeting) }}</div>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1.5 text-text-tertiary hover:text-text-primary"
                        :aria-label="$t('Edit')"
                        @click="edit(meeting)">
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-1.5 text-text-tertiary hover:text-red-500"
                        :aria-label="$t('Delete')"
                        @click="remove(meeting)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>

                <p v-if="meetings.length === 0 && !editing" class="text-sm text-text-tertiary">
                    {{ $t('No weekly meetings yet.') }}
                </p>

                <div v-if="editing" class="space-y-4 rounded-xl border border-card-border p-4">
                    <Field>
                        <FieldLabel for="meetingName">{{ $t('Name') }}</FieldLabel>
                        <TextInput id="meetingName" v-model="editing.name" placeholder="TSA Tech" />
                    </Field>
                    <div class="grid grid-cols-3 gap-3">
                        <Field>
                            <FieldLabel for="meetingWeekday">{{ $t('Day') }}</FieldLabel>
                            <select
                                id="meetingWeekday"
                                v-model.number="editing.weekday"
                                class="w-full rounded-md border border-input-border bg-input-background py-2 text-sm text-text-primary">
                                <option v-for="day in weekdays" :key="day.value" :value="day.value">
                                    {{ day.label }}
                                </option>
                            </select>
                        </Field>
                        <Field>
                            <FieldLabel for="meetingFrom">{{ $t('From') }}</FieldLabel>
                            <TextInput
                                id="meetingFrom"
                                v-model="editing.start_time"
                                type="time"
                                step="300" />
                        </Field>
                        <Field>
                            <FieldLabel for="meetingTo">{{ $t('To') }}</FieldLabel>
                            <TextInput
                                id="meetingTo"
                                v-model="editing.end_time"
                                type="time"
                                step="300" />
                        </Field>
                    </div>
                    <div>
                        <div class="mb-1.5 text-sm font-medium text-text-primary">
                            {{ $t('Tags') }}
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <Field v-for="tag in tags" :key="tag.id" orientation="horizontal">
                                <Checkbox
                                    :id="'meetingTag' + tag.id"
                                    :checked="editing.tag_ids.includes(tag.id)"
                                    @update:checked="
                                        (value: boolean) => toggle(editing!.tag_ids, tag.id, value)
                                    " />
                                <FieldLabel :for="'meetingTag' + tag.id">{{ tag.name }}</FieldLabel>
                            </Field>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1.5 text-sm font-medium text-text-primary">
                            {{ $t('Who attends') }}
                        </div>
                        <Field orientation="horizontal">
                            <Checkbox id="meetingEveryone" v-model:checked="everyone" />
                            <FieldLabel for="meetingEveryone">{{ $t('Everyone') }}</FieldLabel>
                        </Field>
                        <div
                            v-if="editing.member_ids !== null"
                            class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2">
                            <Field
                                v-for="member in sortedMembers"
                                :key="member.id"
                                orientation="horizontal">
                                <Checkbox
                                    :id="'meetingMember' + member.id"
                                    :checked="editing.member_ids.includes(member.id)"
                                    @update:checked="
                                        (value: boolean) =>
                                            toggle(editing!.member_ids!, member.id, value)
                                    " />
                                <FieldLabel :for="'meetingMember' + member.id">{{
                                    member.name
                                }}</FieldLabel>
                            </Field>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton @click="editing = null">{{
                            $t('Cancel')
                        }}</SecondaryButton>
                        <PrimaryButton :loading="saving" @click="save">{{
                            $t('Save')
                        }}</PrimaryButton>
                    </div>
                </div>

                <SecondaryButton v-if="!editing" :icon="PlusIcon" @click="startNew">
                    {{ $t('Add meeting') }}
                </SecondaryButton>
            </div>
        </template>
    </FormSection>
</template>
