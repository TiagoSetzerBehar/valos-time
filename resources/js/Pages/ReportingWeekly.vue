<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ReportingWeekly from '@/Components/Common/Reporting/ReportingWeekly.vue';
</script>

<template>
    <AppLayout :title="$t('Reporting')" data-testid="reporting_weekly_view" class="overflow-hidden">
        <ReportingWeekly></ReportingWeekly>
    </AppLayout>
</template>
