<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { CheckIcon, MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { Popover, PopoverContent, PopoverTrigger } from '@/packages/ui/src/popover';

// VALO's Time: one way to pick projects or tags everywhere. A glass list next to the button on the
// computer, a sheet from the bottom on the phone (no keyboard popping up by itself).
export type PickerItem = { id: string; name: string; color?: string | null };

const props = withDefaults(
    defineProps<{
        items: PickerItem[];
        title: string;
        multiple?: boolean;
        searchPlaceholder?: string;
        /** label of an extra first row such as "Sin proyecto" (id 'none') */
        noneLabel?: string;
        noneId?: string;
        /** when set, a "Create «text»" row appears if the search finds nothing equal */
        createLabel?: string;
        align?: 'start' | 'end';
    }>(),
    {
        multiple: true,
        searchPlaceholder: '',
        noneLabel: '',
        noneId: 'none',
        createLabel: '',
        align: 'start',
    }
);
const model = defineModel<string[]>({ default: () => [] });
const emit = defineEmits<{ create: [name: string] }>();

const isPhone = useMediaQuery('(max-width: 639px)');
const open = ref(false);
const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }
    search.value = '';
    if (!isPhone.value) {
        await nextTick();
        searchInput.value?.focus();
    }
});

const rows = computed(() => {
    const term = search.value.trim().toLowerCase();
    const all: PickerItem[] = [
        ...(props.noneLabel ? [{ id: props.noneId, name: props.noneLabel, color: null }] : []),
        ...props.items,
    ];
    return term === '' ? all : all.filter((item) => item.name.toLowerCase().includes(term));
});
const showSearch = computed(() => props.items.length > 7);
const canCreate = computed(() => {
    const term = search.value.trim().toLowerCase();
    return (
        props.createLabel !== '' &&
        term !== '' &&
        !props.items.some((item) => item.name.toLowerCase() === term)
    );
});

function isOn(id: string): boolean {
    return model.value.includes(id);
}
function toggle(id: string) {
    if (!props.multiple) {
        model.value = isOn(id) ? [] : [id];
        open.value = false;
        return;
    }
    model.value = isOn(id) ? model.value.filter((value) => value !== id) : [...model.value, id];
}
function create() {
    emit('create', search.value.trim());
    search.value = '';
}
</script>

<template>
    <!-- phone: the trigger opens a bottom sheet -->
    <template v-if="isPhone">
        <span class="contents" @click="open = true"><slot name="trigger" :open="open"></slot></span>
        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[60] flex items-end bg-black/35"
                data-testid="valo_picker_sheet"
                @click.self="open = false">
                <div
                    class="valo-floating-sheet valo-picker flex max-h-[80vh] w-full flex-col border border-card-border bg-default-background shadow-dropdown"
                    style="border-radius: 1.5rem 1.5rem 0 0">
                    <div class="flex items-center justify-between px-5 pt-4 pb-2">
                        <h2 class="text-base font-semibold text-text-primary">{{ title }}</h2>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-text-tertiary"
                            :aria-label="$t('Close')"
                            @click="open = false">
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <label v-if="showSearch" class="valo-picker-search mx-5 mb-2">
                        <MagnifyingGlassIcon class="h-4 w-4 shrink-0 opacity-60" />
                        <input
                            v-model="search"
                            type="search"
                            enterkeyhint="search"
                            :placeholder="searchPlaceholder" />
                    </label>
                    <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-2">
                        <button
                            v-for="row in rows"
                            :key="row.id"
                            type="button"
                            class="valo-picker-row"
                            :class="{ on: isOn(row.id) }"
                            data-testid="valo_picker_row"
                            @click="toggle(row.id)">
                            <span
                                class="valo-picker-dot"
                                :style="{
                                    background: row.color || 'rgba(148,163,184,.55)',
                                }"></span>
                            <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
                            <span class="valo-picker-check"
                                ><CheckIcon v-if="isOn(row.id)" class="h-4 w-4"
                            /></span>
                        </button>
                        <button
                            v-if="canCreate"
                            type="button"
                            class="valo-picker-row"
                            @click="create">
                            <PlusIcon class="h-4 w-4 valo-accent-text" />
                            <span class="valo-accent-text min-w-0 flex-1 truncate"
                                >{{ createLabel }} «{{ search.trim() }}»</span
                            >
                        </button>
                        <p
                            v-if="rows.length === 0 && !canCreate"
                            class="py-6 text-center text-sm text-text-tertiary">
                            {{ $t('Nothing found') }}
                        </p>
                    </div>
                    <div
                        class="grid grid-cols-2 gap-3 border-t border-card-background-separator px-5 pt-3 pb-7">
                        <button
                            type="button"
                            class="rounded-2xl border border-card-border bg-card-background py-3 text-sm font-medium text-text-secondary disabled:opacity-40"
                            :disabled="model.length === 0"
                            @click="model = []">
                            {{ $t('Clear') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl bg-sky-500 py-3 text-sm font-semibold text-white shadow-card"
                            data-testid="valo_picker_done"
                            @click="open = false">
                            {{ $t('Done') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </template>

    <!-- computer: a glass list next to the button -->
    <Popover v-else v-model:open="open">
        <PopoverTrigger as-child>
            <slot name="trigger" :open="open"></slot>
        </PopoverTrigger>
        <PopoverContent
            :align="align"
            class="valo-picker valo-picker-pop w-72 p-0"
            data-testid="valo_picker_popover">
            <label v-if="showSearch" class="valo-picker-search m-2.5 mb-1.5">
                <MagnifyingGlassIcon class="h-4 w-4 shrink-0 opacity-60" />
                <input
                    ref="searchInput"
                    v-model="search"
                    type="search"
                    :placeholder="searchPlaceholder" />
            </label>
            <div class="max-h-72 overflow-y-auto p-1.5">
                <button
                    v-for="row in rows"
                    :key="row.id"
                    type="button"
                    class="valo-picker-row compact"
                    :class="{ on: isOn(row.id) }"
                    data-testid="valo_picker_row"
                    @click="toggle(row.id)">
                    <span
                        class="valo-picker-dot"
                        :style="{ background: row.color || 'rgba(148,163,184,.55)' }"></span>
                    <span class="min-w-0 flex-1 truncate">{{ row.name }}</span>
                    <span class="valo-picker-check"
                        ><CheckIcon v-if="isOn(row.id)" class="h-3.5 w-3.5"
                    /></span>
                </button>
                <button
                    v-if="canCreate"
                    type="button"
                    class="valo-picker-row compact"
                    @click="create">
                    <PlusIcon class="h-4 w-4 valo-accent-text" />
                    <span class="valo-accent-text min-w-0 flex-1 truncate"
                        >{{ createLabel }} «{{ search.trim() }}»</span
                    >
                </button>
                <p
                    v-if="rows.length === 0 && !canCreate"
                    class="py-5 text-center text-sm text-text-tertiary">
                    {{ $t('Nothing found') }}
                </p>
            </div>
            <div
                v-if="multiple && model.length > 0"
                class="flex items-center justify-between border-t border-card-background-separator px-3 py-2 text-sm">
                <button
                    type="button"
                    class="text-text-secondary hover:underline"
                    @click="model = []">
                    {{ $t('Clear') }}
                </button>
                <button type="button" class="valo-accent-text font-semibold" @click="open = false">
                    {{ $t('Done') }}
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
