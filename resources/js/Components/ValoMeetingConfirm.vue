<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CheckIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { trans } from 'laravel-vue-i18n';
import { useTagsQuery } from '@/utils/useTagsQuery';
import { useNotificationsStore } from '@/utils/notification';
import { formatWeekday, getDayJsInstance, getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { useConfirmMeeting, type MeetingOccurrence } from '@/utils/useValoMeetings';

// VALO's Time: "¿Asististe?" — confirm a weekly meeting so it counts (times can be adjusted first).
const show = defineModel<boolean>('show', { default: false });
const props = defineProps<{ occurrence: MeetingOccurrence | null }>();

const { tags } = useTagsQuery();
const { addNotification } = useNotificationsStore();
const { mutateAsync, isPending } = useConfirmMeeting();

const from = ref('');
const to = ref('');
const error = ref('');

watch(
    () => [show.value, props.occurrence] as const,
    ([open, occurrence]) => {
        if (open && occurrence) {
            from.value = occurrence.start_time;
            to.value = occurrence.end_time;
            error.value = '';
        }
    },
    { immediate: true }
);

const tagNames = computed(() =>
    (props.occurrence?.tag_ids ?? [])
        .map((id) => tags.value.find((tag) => tag.id === id)?.name)
        .filter((name): name is string => !!name)
);

const dayLabel = computed(() => {
    const date = props.occurrence?.date;
    if (!date) {
        return '';
    }
    const weekday = formatWeekday(date);
    const prefix = date === getLocalizedDayJs().format('YYYY-MM-DD') ? `${trans('Today')}, ` : '';
    const label = `${weekday} ${getDayJsInstance()(date).format('D/M')}`;
    return prefix + (prefix ? label : label.charAt(0).toUpperCase() + label.slice(1));
});

const minutes = computed(() => {
    const parse = (value: string) => {
        const match = /^(\d{1,2}):(\d{2})/.exec(value);
        return match ? Number(match[1]) * 60 + Number(match[2]) : null;
    };
    const start = parse(from.value);
    const end = parse(to.value);
    return start === null || end === null ? 0 : end - start;
});

async function confirm() {
    if (!props.occurrence || minutes.value <= 0) {
        error.value = trans('The end must be after the start');
        return;
    }
    try {
        await mutateAsync({
            meetingId: props.occurrence.meeting_id,
            date: props.occurrence.date,
            startTime: from.value.slice(0, 5),
            endTime: to.value.slice(0, 5),
        });
        addNotification('success', trans('Meeting counted'));
        show.value = false;
    } catch {
        error.value = trans('The action failed. Please try again later.');
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && occurrence"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/30 sm:items-center sm:p-4"
            data-testid="meeting_confirm"
            @click.self="show = false">
            <div
                class="valo-floating-sheet w-full border border-card-border bg-default-background p-5 pb-8 shadow-dropdown sm:max-w-sm sm:pb-5"
                style="border-radius: 1.5rem">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-text-tertiary">{{ $t('Did you attend?') }}</div>
                        <h2 class="text-lg font-semibold text-text-primary">
                            {{ occurrence.name }}
                        </h2>
                        <div class="text-sm text-text-secondary">{{ dayLabel }}</div>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-text-tertiary"
                        :aria-label="$t('Close')"
                        @click="show = false">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="tagNames.length" class="mt-3 flex flex-wrap gap-1.5">
                    <span
                        v-for="name in tagNames"
                        :key="name"
                        class="rounded-full border border-card-border px-2.5 py-0.5 text-xs text-text-secondary"
                        >{{ name }}</span
                    >
                </div>

                <div class="valo-mobile-label">{{ $t('From – To') }}</div>
                <div class="grid grid-cols-2 gap-3">
                    <input
                        v-model="from"
                        type="time"
                        step="300"
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3 text-center text-xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('From')"
                        data-testid="meeting_confirm_from" />
                    <input
                        v-model="to"
                        type="time"
                        step="300"
                        class="w-full rounded-xl border border-card-border bg-card-background px-2 py-3 text-center text-xl font-semibold tabular-nums text-text-primary focus:border-sky-400 focus:ring-0"
                        :aria-label="$t('To')"
                        data-testid="meeting_confirm_to" />
                </div>
                <p class="mt-2 text-center text-xs text-text-tertiary">
                    {{ $t('Arrived late or left early? Adjust the times before pressing OK.') }}
                </p>
                <p v-if="error" class="mt-2 text-center text-sm text-red-500">{{ error }}</p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-card-border bg-card-background py-3 text-sm font-medium text-text-secondary"
                        @click="show = false">
                        {{ $t('I did not attend') }}
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-1.5 rounded-2xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-card disabled:opacity-50"
                        :disabled="isPending"
                        data-testid="meeting_confirm_ok"
                        @click="confirm">
                        <CheckIcon class="h-5 w-5" />
                        {{ $t('OK, I attended') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
