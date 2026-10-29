<script setup lang="ts">
import { MoonIcon, SunIcon } from '@heroicons/vue/20/solid';
import { Button } from '@/packages/ui/src/Buttons';
import { theme, themeSetting } from '@/utils/theme';

function toggleTheme() {
    themeSetting.value = theme.value === 'dark' ? 'light' : 'dark';
}
</script>

<template>
    <Button
        variant="ghost"
        size="icon"
        class="h-7 w-7 shrink-0"
        data-testid="theme_toggle"
        :aria-label="theme === 'dark' ? $t('Switch to Light Theme') : $t('Switch to Dark Theme')"
        :title="theme === 'dark' ? $t('Switch to Light Theme') : $t('Switch to Dark Theme')"
        @click="toggleTheme">
        <SunIcon v-if="theme === 'dark'" class="h-4 w-4 text-icon-default" />
        <MoonIcon v-else class="h-4 w-4 text-icon-default" />
    </Button>
</template>
