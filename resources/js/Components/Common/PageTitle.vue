<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon: Component;
    title: string;
}>();
</script>

<template>
    <h3 class="text-text-primary font-normal text-lg sm:text-2xl flex items-center">
        <component :is="icon" class="hidden"></component>
        <span> {{ title }} </span>
    </h3>
</template>

<style scoped></style>
