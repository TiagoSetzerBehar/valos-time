<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { TabBar, TabBarItem } from '@/packages/ui/src';
import { trans } from 'laravel-vue-i18n';

const props = defineProps<{
    active: 'reporting' | 'detailed' | 'weekly' | 'shared';
}>();

const tabs = computed(() => {
    const items = [
        { value: 'reporting', label: trans('Overview'), href: route('reporting') },
        { value: 'weekly', label: trans('Weekly'), href: route('reporting.weekly') },
    ];
    // VALO's Time: "Detailed" and "Shared" are not used; a click on a person opens their summary instead.
    return items;
});

function hrefForTab(value: string) {
    return tabs.value.find((tab) => tab.value === value)?.href;
}

function onTabChange(value: string | number) {
    const href = hrefForTab(String(value));
    if (href) {
        router.visit(href);
    }
}

function onTabHover(value: string) {
    const href = hrefForTab(value);
    if (href) {
        router.prefetch(href, {}, { cacheFor: '1m' });
    }
}
</script>

<template>
    <TabBar :default-value="props.active" @update:model-value="onTabChange">
        <TabBarItem
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            @mouseenter="onTabHover(tab.value)">
            {{ tab.label }}
        </TabBarItem>
    </TabBar>
</template>
