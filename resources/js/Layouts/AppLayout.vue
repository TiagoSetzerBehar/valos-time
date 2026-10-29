<script setup lang="ts">
import { isMoneyVisible } from '@/utils/valo';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import CurrentSidebarTimer from '@/Components/CurrentSidebarTimer.vue';
import {
    CalendarIcon,
    ChartBarIcon,
    ClockIcon,
    Cog6ToothIcon,
    CreditCardIcon,
    FolderIcon,
    HomeIcon,
    TagIcon,
    UserCircleIcon,
    UserGroupIcon,
    XMarkIcon,
    DocumentTextIcon,
    TableCellsIcon,
} from '@heroicons/vue/20/solid';
import { Target } from '@lucide/vue';
import NavigationSidebarItem from '@/Components/NavigationSidebarItem.vue';
import AppTopBar from '@/Components/AppTopBar.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
import { computed, nextTick, onMounted, provide, ref } from 'vue';
import NotificationContainer from '@/Components/NotificationContainer.vue';
import { initializeStores } from '@/utils/init';
import { useCurrentTimeEntryStore } from '@/utils/useCurrentTimeEntry';
import {
    canManageBilling,
    canUpdateOrganization,
    canViewClients,
    canViewGoals,
    canViewInvoices,
    canViewMembers,
    canViewProjects,
    canViewReport,
    canViewTags,
} from '@/utils/permissions';
import { canUseBillableRates, isBillingActivated, isInvoicingActivated } from '@/utils/billing';
import UpgradeModal from '@/packages/ui/src/UpgradeModal.vue';
import { billableRatesLockKey } from '@/packages/ui/src/utils/useBillableRatesLock';
import { moneyVisibleKey } from '@/packages/ui/src/utils/useMoneyVisible';
import { upgradeCtaKey } from '@/packages/ui/src/UpgradeModal/useUpgradeCta';
import type { User } from '@/types/models';
import { ArrowsRightLeftIcon } from '@heroicons/vue/16/solid';
import { fetchToken, isTokenValid } from '@/utils/session';
import BillingBanner from '@/Components/Billing/BillingBanner.vue';
import SidebarPlanStatus from '@/Components/Billing/SidebarPlanStatus.vue';
import UserTimezoneMismatchModal from '@/Components/Common/User/UserTimezoneMismatchModal.vue';
import { useTheme } from '@/utils/theme';
import { useOrganizationQuery } from '@/utils/useOrganizationQuery';
import { getCurrentOrganizationId } from '@/utils/useUser';
import LoadingSpinner from '@/packages/ui/src/LoadingSpinner.vue';
import { twMerge } from 'tailwind-merge';
import { Button } from '@/packages/ui/src/Buttons';
import { CommandPaletteProvider } from '@/Components/CommandPalette';

defineProps({
    title: String,
    mainClass: String,
});

const showSidebarMenu = ref(false);
const sidebarVisible = ref(false);

function openSidebar() {
    showSidebarMenu.value = true;
    nextTick(() => {
        requestAnimationFrame(() => {
            sidebarVisible.value = true;
        });
    });
}

function closeSidebar() {
    sidebarVisible.value = false;
    setTimeout(() => {
        showSidebarMenu.value = false;
    }, 200);
}

const isUnloading = ref(false);

const { organization, isLoading: isOrganizationLoading } = useOrganizationQuery(
    getCurrentOrganizationId()!
);

provide('organization', organization);
provide(
    moneyVisibleKey,
    computed(() => isMoneyVisible())
);

const showBillableRatesUpgradeModal = ref(false);
provide(billableRatesLockKey, {
    locked: computed(() => !canUseBillableRates()),
    requestUpgrade: () => {
        showBillableRatesUpgradeModal.value = true;
    },
});

provide(upgradeCtaKey, {
    canUpgrade: computed(() => isBillingActivated() && canManageBilling()),
    goToBilling: () => router.visit('/billing'),
});

onMounted(async () => {
    useTheme();
    // make sure that the initial requests are only loaded once, this can be removed once we move away from inertia
    if (window.initialDataLoaded !== true) {
        window.initialDataLoaded = true;
        initializeStores();
    }
    window.onbeforeunload = () => {
        isUnloading.value = true;
    };
    window.onfocus = async () => {
        if (!isTokenValid()) {
            await fetchToken();
        }
        setTimeout(() => {
            // TanStack Query automatically refetches on window focus
            // Only refresh non-migrated stores
            if (isUnloading.value === false) {
                useCurrentTimeEntryStore().fetchCurrentTimeEntry();
            }
        }, 100);
    };
});
const page = usePage<{
    has_services_extension?: boolean;
    auth: {
        user: User;
    };
}>();
</script>

<template>
    <div v-bind="$attrs" class="flex flex-wrap bg-background text-text-secondary">
        <!-- Mobile sidebar overlay -->
        <Teleport to="body">
            <div v-if="showSidebarMenu" class="fixed inset-0 z-40 lg:hidden" @click="closeSidebar">
                <div
                    class="absolute inset-0 bg-default-background transition-opacity duration-200"
                    :class="sidebarVisible ? 'opacity-50' : 'opacity-0'" />
            </div>
        </Teleport>

        <div
            :class="[
                sidebarVisible
                    ? 'max-lg:translate-x-0 max-lg:shadow-xl'
                    : 'max-lg:-translate-x-full',
            ]"
            class="flex-shrink-0 h-screen fixed w-[280px] px-2.5 py-4 hidden lg:flex flex-col justify-between bg-background border-r border-default-background-separator max-lg:z-50 max-lg:transition-transform max-lg:duration-200 max-lg:ease-in-out lg:w-[230px] lg:border-r-0"
            :style="showSidebarMenu ? { display: 'flex' } : undefined">
            <div class="flex flex-col h-full">
                <div
                    class="border-b border-default-background-separator pb-3 pt-0.5 flex items-center gap-1">
                    <Link
                        :href="route('dashboard')"
                        class="flex-1 min-w-0 px-2 text-[15px] tracking-tight text-text-primary">
                        <span class="font-semibold valo-brand">VALO's</span>
                        <span class="font-normal text-text-secondary"> Time</span>
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 flex-shrink-0 lg:hidden"
                        @click="closeSidebar">
                        <XMarkIcon class="h-4 w-4 text-icon-default" />
                    </Button>
                </div>
                <div
                    v-if="!route().current('time')"
                    class="border-b border-default-background-separator">
                    <CurrentSidebarTimer></CurrentSidebarTimer>
                </div>
                <div
                    class="overflow-y-scroll flex-1 w-full"
                    style="
                        scrollbar-width: thin;
                        scrollbar-color: var(--color-bg-primary) transparent;
                    ">
                    <div class="pt-3"></div>
                    <nav>
                        <ul>
                            <NavigationSidebarItem
                                :title="$t('Time')"
                                :icon="ClockIcon"
                                :current="route().current('time')"
                                :href="route('time')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                :title="$t('Calendar')"
                                :icon="CalendarIcon"
                                :current="route().current('calendar')"
                                :href="route('calendar')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                :title="$t('Timesheet')"
                                :icon="TableCellsIcon"
                                :current="route().current('timesheet')"
                                :href="route('timesheet')"></NavigationSidebarItem>
                        </ul>
                    </nav>

                    <div
                        class="text-text-quaternary text-[11px] font-semibold uppercase tracking-wider pt-6 pb-1.5 px-2">
                        {{ $t('Analyze') }}
                    </div>

                    <nav>
                        <ul>
                            <NavigationSidebarItem
                                :title="$t('Dashboard')"
                                :icon="HomeIcon"
                                :href="route('dashboard')"
                                :current="route().current('dashboard')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                :title="$t('Reporting')"
                                :icon="ChartBarIcon"
                                :sub-items="[
                                    {
                                        title: $t('Overview'),
                                        route: 'reporting',
                                        show: true,
                                    },
                                    {
                                        title: $t('Detailed'),
                                        route: 'reporting.detailed',
                                        show: true,
                                    },
                                    {
                                        title: $t('Weekly'),
                                        route: 'reporting.weekly',
                                        show: true,
                                    },
                                    {
                                        title: $t('Shared'),
                                        route: 'reporting.shared',
                                        show: canViewReport(),
                                    },
                                ]"
                                :current="
                                    route().current('reporting') ||
                                    route().current('reporting.detailed') ||
                                    route().current('reporting.weekly') ||
                                    route().current('reporting.shared')
                                "
                                :href="route('reporting')">
                            </NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canViewGoals()"
                                :title="$t('Goals')"
                                :icon="Target"
                                :current="route().current('goals')"
                                :href="route('goals')"></NavigationSidebarItem>
                        </ul>
                    </nav>

                    <div
                        class="text-text-quaternary text-[11px] font-semibold uppercase tracking-wider pt-6 pb-1.5 px-2">
                        {{ $t('Manage') }}
                    </div>

                    <nav>
                        <ul>
                            <NavigationSidebarItem
                                v-if="canViewProjects()"
                                :title="$t('Projects')"
                                :icon="FolderIcon"
                                :href="route('projects')"
                                :current="route().current('projects')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canViewClients()"
                                :title="$t('Clients')"
                                :icon="UserCircleIcon"
                                :current="route().current('clients')"
                                :href="route('clients')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canViewMembers()"
                                :title="$t('Members')"
                                :icon="UserGroupIcon"
                                :current="route().current('members')"
                                :href="route('members')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canViewTags()"
                                :title="$t('Tags')"
                                :icon="TagIcon"
                                :current="route().current('tags')"
                                :href="route('tags')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="isInvoicingActivated() && canViewInvoices()"
                                :title="$t('Invoices')"
                                :icon="DocumentTextIcon"
                                :current="
                                    route().current('invoices') || route().current('invoices.*')
                                "
                                href="/invoices"></NavigationSidebarItem>
                        </ul>
                    </nav>
                    <div
                        v-if="canUpdateOrganization()"
                        class="text-text-quaternary text-[11px] font-semibold uppercase tracking-wider pt-6 pb-1.5 px-2">
                        {{ $t('Admin') }}
                    </div>

                    <nav>
                        <ul>
                            <NavigationSidebarItem
                                v-if="canManageBilling() && isBillingActivated()"
                                :title="$t('Billing')"
                                :icon="CreditCardIcon"
                                href="/billing"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canUpdateOrganization()"
                                :title="$t('Import / Export')"
                                :icon="ArrowsRightLeftIcon"
                                :current="route().current('import')"
                                :href="route('import')"></NavigationSidebarItem>
                            <NavigationSidebarItem
                                v-if="canUpdateOrganization()"
                                :title="$t('Settings')"
                                :icon="Cog6ToothIcon"
                                :href="
                                    route(
                                        'organizations.show',
                                        page.props.auth.user.current_team.id
                                    )
                                "
                                :current="
                                    route().current(
                                        'organizations.show',
                                        page.props.auth.user.current_team.id
                                    )
                                "></NavigationSidebarItem>
                        </ul>
                    </nav>
                </div>
                <div class="justify-self-end">
                    <SidebarPlanStatus v-if="isBillingActivated() && canManageBilling()" />
                </div>
            </div>
        </div>
        <div class="flex-1 lg:ml-[230px] min-w-0">
            <div
                class="h-screen overflow-y-auto flex flex-col bg-default-background border-l border-default-background-separator">
                <AppTopBar @open-sidebar="openSidebar"></AppTopBar>

                <Head :title="title" />

                <!-- Page Heading -->
                <Banner />
                <BillingBanner v-if="isBillingActivated()" />

                <header
                    v-if="$slots.header"
                    class="bg-default-background border-b border-default-background-separator shadow">
                    <div class="pt-8 pb-3">
                        <MainContainer>
                            <slot name="header" />
                        </MainContainer>
                    </div>
                </header>

                <!-- Page Content -->
                <main :class="twMerge('pb-28 relative flex-1', mainClass)">
                    <div
                        v-if="isOrganizationLoading"
                        class="flex items-center justify-center h-screen">
                        <LoadingSpinner />
                    </div>
                    <slot v-else />
                </main>
            </div>
        </div>
    </div>
    <NotificationContainer></NotificationContainer>
    <UserTimezoneMismatchModal></UserTimezoneMismatchModal>
    <CommandPaletteProvider></CommandPaletteProvider>
    <UpgradeModal v-model:show="showBillableRatesUpgradeModal" feature="billable-rates" />
</template>
