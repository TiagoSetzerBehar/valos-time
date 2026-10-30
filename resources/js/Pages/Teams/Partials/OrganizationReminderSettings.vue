<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref } from 'vue';
import FormSection from '@/Components/FormSection.vue';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';
import { Checkbox } from '@/packages/ui/src';
import { Field, FieldDescription, FieldLabel } from '@/packages/ui/src/field';
import { useNotificationsStore } from '@/utils/notification';
import { trans } from 'laravel-vue-i18n';
import type { Organization } from '@/types/models';

// VALO's Time: email reminders for members who forgot to log their hours.
type ReminderSettings = {
    daily_enabled: boolean;
    daily_time: string;
    weekly_enabled: boolean;
    expected_weekly_hours: number;
    member_weekly_enabled: boolean;
    require_project: boolean;
    require_description: boolean;
};

const props = defineProps<{
    team: Organization;
}>();

const { addNotification } = useNotificationsStore();
const loaded = ref(false);
const saving = ref(false);
const form = ref<ReminderSettings>({
    daily_enabled: false,
    daily_time: '18:00',
    weekly_enabled: false,
    expected_weekly_hours: 20,
    member_weekly_enabled: false,
    require_project: false,
    require_description: false,
});

const url = () => route('valo.reminders.show', props.team.id);

onMounted(async () => {
    const response = await axios.get<ReminderSettings>(url());
    form.value = response.data;
    loaded.value = true;
});

async function submit() {
    saving.value = true;
    try {
        const response = await axios.put<ReminderSettings>(url(), {
            ...form.value,
            expected_weekly_hours: Number(form.value.expected_weekly_hours),
        });
        form.value = response.data;
        addNotification('success', trans('Reminders saved'));
    } catch {
        addNotification('error', trans('Reminders could not be saved'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <FormSection data-testid="reminder_settings">
        <template #title>{{ $t('Reminders') }}</template>
        <template #description>
            {{
                $t(
                    'Email the members who forgot to log their hours, and send a weekly summary to owners and admins.'
                )
            }}
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4 space-y-5">
                <Field orientation="horizontal">
                    <Checkbox
                        id="dailyReminderEnabled"
                        v-model:checked="form.daily_enabled"
                        :disabled="!loaded" />
                    <FieldLabel for="dailyReminderEnabled">{{
                        $t('Daily email to members who logged no hours (Monday to Friday)')
                    }}</FieldLabel>
                </Field>
                <Field class="max-w-40">
                    <FieldLabel for="dailyReminderTime">{{ $t('Send at') }}</FieldLabel>
                    <TextInput
                        id="dailyReminderTime"
                        v-model="form.daily_time"
                        type="time"
                        step="900"
                        :disabled="!loaded || !form.daily_enabled" />
                    <FieldDescription>{{ $t("In each member's own timezone.") }}</FieldDescription>
                </Field>
                <Field orientation="horizontal">
                    <Checkbox
                        id="weeklySummaryEnabled"
                        v-model:checked="form.weekly_enabled"
                        :disabled="!loaded" />
                    <FieldLabel for="weeklySummaryEnabled">{{
                        $t('Weekly summary to owners and admins (Monday morning)')
                    }}</FieldLabel>
                </Field>
                <Field class="max-w-40">
                    <FieldLabel for="expectedWeeklyHours">{{
                        $t('Expected hours per week')
                    }}</FieldLabel>
                    <TextInput
                        id="expectedWeeklyHours"
                        v-model="form.expected_weekly_hours"
                        type="number"
                        min="1"
                        max="80"
                        :disabled="!loaded || !form.weekly_enabled" />
                    <FieldDescription>{{
                        $t('Members below this number appear in the summary.')
                    }}</FieldDescription>
                </Field>
                <Field orientation="horizontal">
                    <Checkbox
                        id="memberWeeklyEnabled"
                        v-model:checked="form.member_weekly_enabled"
                        :disabled="!loaded" />
                    <FieldLabel for="memberWeeklyEnabled">{{
                        $t('Weekly report to each member with their own hours (Monday morning)')
                    }}</FieldLabel>
                </Field>
                <div
                    class="pt-2 text-[11px] font-semibold uppercase tracking-wider text-text-quaternary">
                    {{ $t('Required fields') }}
                </div>
                <Field orientation="horizontal">
                    <Checkbox
                        id="requireProject"
                        v-model:checked="form.require_project"
                        :disabled="!loaded" />
                    <FieldLabel for="requireProject">{{
                        $t('Project is required to save a time entry')
                    }}</FieldLabel>
                </Field>
                <Field orientation="horizontal">
                    <Checkbox
                        id="requireDescription"
                        v-model:checked="form.require_description"
                        :disabled="!loaded" />
                    <FieldLabel for="requireDescription">{{
                        $t('Description is required to save a time entry')
                    }}</FieldLabel>
                </Field>
            </div>
        </template>

        <template #actions>
            <PrimaryButton :disabled="!loaded || saving" @click="submit">{{
                $t('Save')
            }}</PrimaryButton>
        </template>
    </FormSection>
</template>
