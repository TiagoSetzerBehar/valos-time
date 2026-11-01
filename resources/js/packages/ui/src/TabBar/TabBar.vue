<script setup lang="ts">
import { Tabs, TabsList } from '../tabs';
import GlassLens from '../GlassLens.vue';

defineProps<{
    defaultValue?: string;
}>();
</script>

<template>
    <Tabs :default-value="defaultValue" class="w-full">
        <TabsList class="valo-lens-host relative flex items-center space-x-0.5 sm:space-x-1">
            <GlassLens selector="[role='tab'][data-state='active']" storage-key="tabs" />
            <slot></slot>
        </TabsList>
    </Tabs>
</template>

<style scoped></style>
