<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Cog6ToothIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { PanelLeft } from '@lucide/vue';
import { Button } from '@/packages/ui/src/Buttons';
import OrganizationSwitcher from '@/Components/OrganizationSwitcher.vue';
import ThemeToggleButton from '@/Components/Common/ThemeToggleButton.vue';
import UserSettingsIcon from '@/Components/UserSettingsIcon.vue';
import { canUpdateOrganization } from '@/utils/permissions';
import { useCommandPalette } from '@/utils/useCommandPalette';
import type { User } from '@/types/models';

defineEmits<{
    openSidebar: [];
}>();

const { openPalette } = useCommandPalette();
const page = usePage<{
    auth: {
        user: User;
    };
}>();
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-12 shrink-0 items-center gap-1.5 border-b border-default-background-separator bg-background px-3 sm:px-4">
        <Button
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 lg:hidden"
            :aria-label="$t('Toggle navigation')"
            @click="$emit('openSidebar')">
            <PanelLeft class="h-4 w-4 text-icon-default" />
        </Button>
        <div class="min-w-0 w-full max-w-[240px]">
            <OrganizationSwitcher></OrganizationSwitcher>
        </div>
        <div class="flex-1"></div>
        <button
            type="button"
            data-testid="command_palette_button"
            class="hidden md:flex h-8 w-60 items-center gap-2 rounded-md border border-default-background-separator bg-primary px-2.5 text-sm text-text-tertiary transition hover:border-card-border-active hover:text-text-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="openPalette">
            <MagnifyingGlassIcon class="h-4 w-4 shrink-0" />
            <span class="flex-1 text-left">{{ $t('Search') }}</span>
            <kbd class="font-sans text-xs text-text-quaternary">Ctrl K</kbd>
        </button>
        <Button
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 md:hidden"
            data-testid="command_palette_button_mobile"
            :aria-label="$t('Search')"
            @click="openPalette">
            <MagnifyingGlassIcon class="h-4 w-4 text-icon-default" />
        </Button>
        <div class="mx-1 hidden h-5 w-px bg-default-background-separator sm:block"></div>
        <ThemeToggleButton></ThemeToggleButton>
        <Link
            v-if="canUpdateOrganization()"
            :href="route('organizations.show', page.props.auth.user.current_team.id)"
            data-testid="organization_settings_button"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-icon-default transition hover:bg-tertiary hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :title="$t('Organization Settings')"
            :aria-label="$t('Organization Settings')">
            <Cog6ToothIcon class="h-4 w-4" />
        </Link>
        <UserSettingsIcon class="ml-1"></UserSettingsIcon>
    </header>
</template>
