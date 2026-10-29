<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import type { Organization, User } from '@/types/models';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/packages/ui/src';
import {
    UserCircleIcon,
    ArrowLeftOnRectangleIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/20/solid';
import { openFeedback } from '@/utils/feedback';
import { canUpdateOrganization } from '@/utils/permissions';

const page = usePage<{
    has_services_extension?: boolean;
    has_billing_extension?: boolean;
    auth: {
        user: User & {
            all_teams: Organization[];
        };
    };
}>();

const logout = () => {
    router.post(route('logout'));
};
</script>
<template>
    <div class="relative shrink-0">
        <DropdownMenu>
            <DropdownMenuTrigger
                class="flex text-sm outline-none rounded-full focus-visible:ring-2 focus-visible:ring-ring transition"
                as-child>
                <button data-testid="current_user_button" :aria-label="$t('Manage Account')">
                    <img
                        class="h-8 w-8 min-w-8 rounded-full object-cover"
                        :src="page.props.auth.user.profile_photo_url"
                        :alt="page.props.auth.user.name" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <div class="flex items-center gap-3 px-2 py-2.5">
                    <img
                        class="h-9 w-9 shrink-0 rounded-full object-cover"
                        :src="page.props.auth.user.profile_photo_url"
                        alt="" />
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-text-primary">
                            {{ page.props.auth.user.name }}
                        </div>
                        <div class="truncate text-xs text-text-tertiary">
                            {{ page.props.auth.user.email }}
                        </div>
                    </div>
                </div>
                <DropdownMenuSeparator />

                <DropdownMenuItem as-child>
                    <Link
                        :href="route('profile.show')"
                        class="inline-flex items-center gap-2.5 w-full">
                        <UserCircleIcon class="w-4 h-4 text-icon-default" />
                        <span>{{ $t('Profile Settings') }}</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem v-if="canUpdateOrganization()" as-child>
                    <Link
                        :href="route('organizations.show', page.props.auth.user.current_team.id)"
                        class="inline-flex items-center gap-2.5 w-full">
                        <Cog6ToothIcon class="w-4 h-4 text-icon-default" />
                        <span>{{ $t('Organization Settings') }}</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem v-if="page.props.has_services_extension" as-child>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2.5 w-full"
                        @click="openFeedback">
                        <ChatBubbleLeftRightIcon class="w-4 h-4 text-icon-default" />
                        <span>{{ $t('Feedback') }}</span>
                    </button>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                <form class="w-full" @submit.prevent="logout">
                    <DropdownMenuItem as-child class="inline-flex items-center gap-2.5 w-full">
                        <button type="submit" data-testid="logout_button">
                            <ArrowLeftOnRectangleIcon class="w-4 h-4 text-icon-default" />
                            <span>{{ $t('Log Out') }}</span>
                        </button>
                    </DropdownMenuItem>
                </form>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
