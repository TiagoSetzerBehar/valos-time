<script setup lang="ts">
import OrganizationReminderSettings from '@/Pages/Teams/Partials/OrganizationReminderSettings.vue';
import OrganizationMeetings from '@/Pages/Teams/Partials/OrganizationMeetings.vue';
import { isMoneyVisible } from '@/utils/valo';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteTeamForm from '@/Pages/Teams/Partials/DeleteTeamForm.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import UpdateTeamNameForm from '@/Pages/Teams/Partials/UpdateTeamNameForm.vue';
import type { Organization } from '@/types/models';
import type { Permissions } from '@/types/jetstream';
import OrganizationBillableRate from '@/Pages/Teams/Partials/OrganizationBillableRate.vue';
import OrganizationFormatSettings from '@/Pages/Teams/Partials/OrganizationFormatSettings.vue';
import OrganizationTimeEntrySettings from '@/Pages/Teams/Partials/OrganizationTimeEntrySettings.vue';
import OrganizationTrial from '@/Pages/Teams/Partials/OrganizationTrial.vue';
import { isBillingActivated, isInTrial } from '@/utils/billing';
import { canManageBilling } from '@/utils/permissions';
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { TagIcon, UserGroupIcon } from '@heroicons/vue/20/solid';
import { useOrganizationStore } from '@/utils/useOrganization';
import { storeToRefs } from 'pinia';

defineProps<{
    team: Organization;
    permissions: Permissions;
}>();

const loading = ref(true);
const orgStore = useOrganizationStore();
const { organization } = storeToRefs(orgStore);

onMounted(async () => {
    await orgStore.fetchOrganization();
    loading.value = false;
});
</script>

<template>
    <AppLayout :title="$t('Organization Settings')">
        <template #header>
            <h2 class="font-semibold text-xl text-text-primary leading-tight">
                {{ $t('Organization Settings') }}
            </h2>
        </template>

        <div>
            <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
                <div v-if="loading || !organization" class="py-16 text-center text-text-secondary">
                    {{ $t('Loading organization settings...') }}
                </div>
                <template v-else>
                    <div
                        class="mb-8 grid gap-3 px-4 sm:grid-cols-2 sm:px-0"
                        data-testid="settings_links">
                        <Link :href="route('members')" class="valo-gcard valo-settings-link">
                            <UserGroupIcon class="h-6 w-6 valo-accent-text" />
                            <span>
                                <b>{{ $t('Members') }}</b>
                                <small>{{
                                    $t('Invite people, roles and who is in the team')
                                }}</small>
                            </span>
                        </Link>
                        <Link :href="route('tags')" class="valo-gcard valo-settings-link">
                            <TagIcon class="h-6 w-6 valo-accent-text" />
                            <span>
                                <b>{{ $t('Tags') }}</b>
                                <small>{{ $t('The tags everyone can use in their hours') }}</small>
                            </span>
                        </Link>
                    </div>
                    <UpdateTeamNameForm :team="team" :permissions="permissions" />

                    <SectionBorder v-if="isMoneyVisible() && permissions.canUpdateTeam" />
                    <OrganizationBillableRate
                        v-if="isMoneyVisible() && permissions.canUpdateTeam"
                        :team="team" />

                    <SectionBorder v-if="permissions.canUpdateTeam" />
                    <OrganizationFormatSettings v-if="permissions.canUpdateTeam" :team="team" />

                    <SectionBorder v-if="permissions.canUpdateTeam" />
                    <OrganizationTimeEntrySettings v-if="permissions.canUpdateTeam" />

                    <SectionBorder v-if="permissions.canUpdateTeam" />
                    <OrganizationReminderSettings v-if="permissions.canUpdateTeam" :team="team" />

                    <SectionBorder v-if="permissions.canUpdateTeam" />
                    <OrganizationMeetings v-if="permissions.canUpdateTeam" :team="team" />

                    <template v-if="isBillingActivated() && isInTrial() && canManageBilling()">
                        <SectionBorder />
                        <OrganizationTrial />
                    </template>

                    <template v-if="permissions.canDeleteTeam">
                        <SectionBorder />
                        <DeleteTeamForm class="mt-10 sm:mt-0" :team="team" />
                    </template>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
