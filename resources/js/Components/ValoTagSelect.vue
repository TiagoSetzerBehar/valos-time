<script setup lang="ts">
import { computed } from 'vue';
import { TagIcon } from '@heroicons/vue/20/solid';
import type { Tag } from '@/packages/api/src';

// VALO's Time on the phone: tags are picked with the phone's own list (same as the project chip).
const props = defineProps<{
    tags: Tag[];
    testid?: string;
}>();
const model = defineModel<string[]>({ required: true });
const emit = defineEmits<{ changed: [] }>();

const sortedTags = computed(() => [...props.tags].sort((a, b) => a.name.localeCompare(b.name)));

const selectedNames = computed(() =>
    sortedTags.value.filter((tag) => model.value.includes(tag.id)).map((tag) => tag.name)
);

function onChange(event: Event) {
    const select = event.target as HTMLSelectElement;
    model.value = Array.from(select.selectedOptions).map((option) => option.value);
    emit('changed');
}
</script>

<template>
    <label
        class="relative inline-flex max-w-full items-center gap-1.5 rounded-full border border-card-border px-3 py-1.5 text-sm"
        :class="selectedNames.length ? 'text-text-primary' : 'text-text-secondary'">
        <TagIcon class="h-4 w-4 shrink-0" />
        <span v-if="selectedNames.length === 0">{{ $t('Tag') }}</span>
        <span v-else class="truncate">{{ selectedNames[0] }}</span>
        <span v-if="selectedNames.length > 1" class="shrink-0 text-text-tertiary"
            >+{{ selectedNames.length - 1 }}</span
        >
        <select
            multiple
            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            :aria-label="$t('Tag')"
            :data-testid="testid"
            @change="onChange">
            <option
                v-for="tag in sortedTags"
                :key="tag.id"
                :value="tag.id"
                :selected="model.includes(tag.id)">
                {{ tag.name }}
            </option>
        </select>
    </label>
</template>
