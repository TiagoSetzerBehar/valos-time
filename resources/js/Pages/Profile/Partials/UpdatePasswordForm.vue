<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { trans } from 'laravel-vue-i18n';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import { Field, FieldLabel, FieldError } from '@/packages/ui/src/field';
import PrimaryButton from '@/packages/ui/src/Buttons/PrimaryButton.vue';
import TextInput from '@/packages/ui/src/Input/TextInput.vue';

const passwordInput = ref<HTMLElement | null>(null);
const currentPasswordInput = ref<HTMLElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// VALO's Time: who forgot the current password asks for the reset email from here.
const linkState = ref<'idle' | 'sending' | 'sent' | 'error'>('idle');
const linkMessage = ref('');
async function sendPasswordLink() {
    linkState.value = 'sending';
    try {
        const response = await axios.post<{ email: string }>(route('valo.password.link'));
        linkMessage.value = trans(
            'We sent an email to :email with a link to create a new password. It expires in 60 minutes.',
            { email: response.data.email }
        );
        linkState.value = 'sent';
    } catch (error) {
        const message = axios.isAxiosError(error) ? error.response?.data?.message : null;
        linkMessage.value = message ?? trans('The email could not be sent. Try again in a minute.');
        linkState.value = 'error';
    }
}

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <FormSection @submitted="updatePassword">
        <template #title> {{ $t('Update Password') }} </template>

        <template #description>
            {{ $t('Ensure your account is using a long, random password to stay secure.') }}
        </template>

        <template #form>
            <Field class="col-span-6 sm:col-span-4">
                <FieldLabel for="current_password">{{ $t('Current Password') }}</FieldLabel>
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="block w-full"
                    autocomplete="current-password" />
                <FieldError v-if="form.errors.current_password">{{
                    form.errors.current_password
                }}</FieldError>
                <div class="mt-2 text-sm" data-testid="forgot_current_password">
                    <button
                        type="button"
                        class="font-medium text-accent-300 underline hover:no-underline disabled:opacity-50"
                        :disabled="linkState === 'sending'"
                        @click="sendPasswordLink">
                        {{ $t('Forgot your current password? Send me a link by email') }}
                    </button>
                    <p
                        v-if="linkState === 'sent' || linkState === 'error'"
                        class="mt-2"
                        :class="linkState === 'sent' ? 'text-text-primary' : 'text-red-500'"
                        role="status">
                        {{ linkMessage }}
                    </p>
                </div>
            </Field>

            <Field class="col-span-6 sm:col-span-4">
                <FieldLabel for="password">{{ $t('New Password') }}</FieldLabel>
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="block w-full"
                    autocomplete="new-password" />
                <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
            </Field>

            <Field class="col-span-6 sm:col-span-4">
                <FieldLabel for="password_confirmation">{{ $t('Confirm Password') }}</FieldLabel>
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="block w-full"
                    autocomplete="new-password" />
                <FieldError v-if="form.errors.password_confirmation">{{
                    form.errors.password_confirmation
                }}</FieldError>
            </Field>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                {{ $t('Saved.') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ $t('Save') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
