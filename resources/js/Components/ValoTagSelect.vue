<script setup lang="ts">
import { computed, ref } from 'vue';
import { CheckIcon, TagIcon } from '@heroicons/vue/20/solid';
import type { Tag } from '@/packages/api/src';

// VALO's Time on the phone: tags open in our own sheet from the bottom (approved option B):
// a list with a circle that turns blue when picked, and "Listo". No keyboard, no iPhone arrows.
const props = defineProps<{
    tags: Tag[];
    testid?: string;
}>();
const model = defineModel<string[]>({ required: true });
const emit = defineEmits<{ changed: [] }>();

const open = ref(false);

const sortedTags = computed(() => [...props.tags].sort((a, b) => a.name.localeCompare(b.name)));

const selectedNames = computed(() =>
    sortedTags.value.filter((tag) => model.value.includes(tag.id)).map((tag) => tag.name)
);

function toggle(id: string) {
    model.value = model.value.includes(id)
        ? model.value.filter((item) => item !== id)
        : [...model.value, id];
}

let before: string[] = [];

function show() {
    (document.activeElement as HTMLElement | null)?.blur();
    before = [...model.value];
    open.value = true;
}

function close() {
    open.value = false;
    const changed =
        before.length !== model.value.length || before.some((id) => !model.value.includes(id));
    if (changed) {
        emit('changed');
    }
}
</script>

<template>
    <button
        type="button"
        class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-card-border px-3 py-1.5 text-sm"
        :class="selectedNames.length ? 'text-text-primary' : 'text-text-secondary'"
        :data-testid="testid"
        @click="show">
        <TagIcon class="h-4 w-4 shrink-0" />
        <span v-if="selectedNames.length === 0">{{ $t('Tag') }}</span>
        <span v-else class="truncate">{{ selectedNames[0] }}</span>
        <span v-if="selectedNames.length > 1" class="shrink-0 text-text-tertiary"
            >+{{ selectedNames.length - 1 }}</span
        >
    </button>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-end bg-black/35"
            data-testid="valo_tag_sheet"
            @click.self="close">
            <div
                class="valo-floating-sheet valo-tag-sheet flex max-h-[75vh] w-full flex-col border border-card-border bg-default-background shadow-dropdown">
                <div class="valo-tag-sheet-grab"></div>
                <h2 class="px-5 pt-1 pb-1 text-base font-semibold text-text-primary">
                    {{ $t('Tags') }}
                </h2>
                <div class="min-h-0 flex-1 overflow-y-auto px-5">
                    <button
                        v-for="tag in sortedTags"
                        :key="tag.id"
                        type="button"
                        class="valo-tag-row"
                        :class="{ on: model.includes(tag.id) }"
                        data-testid="valo_tag_row"
                        @click="toggle(tag.id)">
                        <span class="valo-tag-ring"
                            ><CheckIcon v-if="model.includes(tag.id)" class="h-3.5 w-3.5"
                        /></span>
                        <span class="min-w-0 flex-1 truncate">{{ tag.name }}</span>
                    </button>
                    <p
                        v-if="sortedTags.length === 0"
                        class="py-6 text-center text-sm text-text-tertiary">
                        {{ $t('No tags yet') }}
                    </p>
                </div>
                <div class="px-5 pt-3 pb-7">
                    <button
                        type="button"
                        class="w-full rounded-full bg-sky-500 py-3.5 text-[15px] font-semibold text-white shadow-card"
                        data-testid="valo_tag_done"
                        @click="close">
                        {{ $t('Done') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
