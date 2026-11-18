<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ValoDashboard from '@/Components/Dashboard/ValoDashboard.vue';
</script>

<template>
    <AppLayout :title="$t('Dashboard')" data-testid="dashboard_view">
        <ValoDashboard></ValoDashboard>
    </AppLayout>
</template>
