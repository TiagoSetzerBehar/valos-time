<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ValoReportingSummary from '@/Components/Common/Reporting/ValoReportingSummary.vue';
</script>

<template>
    <AppLayout :title="$t('Reporting')" data-testid="reporting_view" class="overflow-hidden">
        <ValoReportingSummary></ValoReportingSummary>
    </AppLayout>
</template>
