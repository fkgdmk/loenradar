<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import ReportFormWizard from '@/components/reports/ReportFormWizard.vue';
import { useReportForm } from '@/composables/useReportForm';
import { dashboard } from '@/routes';
import type { ReportFormProps } from '@/types/report';

interface Props extends ReportFormProps {
    is_guest?: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Opret Rapport', href: '#' },
];

// Auth state
const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

// Determine if this is an update to existing report
const hasExistingReport = computed(() => 
    props.report?.id && props.report.id !== 'guest'
);

// Initialize report form composable with dynamic endpoints
const reportForm = useReportForm({
    props,
    endpoints: {
        // For step 1: Job details - PATCH existing report, or POST new report
        jobDetails: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/job-details`;
            }
            return '/reports/job-details';
        },
        jobDetailsMethod: hasExistingReport.value ? 'patch' : 'post',
        // For step 2: Competencies - PATCH existing report
        competencies: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/competencies`;
            }
            return '/reports/guest/competencies';
        },
        // For step 3: Payslip upload
        payslip: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/payslip`;
            }
            return '/reports/guest/payslip';
        },
        // Analyze anonymized payslip
        analyze: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/analyze`;
            }
            return null; // Will not be called if no reportId
        },
        // Delete payslip document
        deletePayslip: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/payslip`;
            }
            return null; // Will not be called if no reportId
        },
        submit: '/reports',
    },
});

// Custom submit for authenticated users
const handleSubmit = () => {
    if (!reportForm.reportId.value || reportForm.reportId.value === 'guest') {
        alert('Der opstod en fejl. Prøv venligst igen.');
        return;
    }

    const finalForm = useForm({
        report_id: reportForm.reportId.value,
        responsibility_level_id: reportForm.form.responsibility_level_id,
        team_size: reportForm.form.team_size,
        skill_ids: reportForm.form.skill_ids,
    });

    finalForm.post('/reports', {
        onError: (errors: Record<string, string>) => {
            console.error('Fejl ved rapport oprettelse:', errors);
            if (errors.error) {
                alert(errors.error);
            }
        },
    });
};

// Auto-finalize guest report after login
// Only finalize if report is complete (has all required data AND user explicitly submitted)
// Don't auto-finalize just because a payslip was uploaded
onMounted(() => {
    // Don't auto-finalize - let user explicitly submit
});
</script>

<template>
    <Head title="Opret Rapport" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <ReportFormWizard 
                :report-form="reportForm"
                :show-auth-prompt="false"
                :is-authenticated="isAuthenticated"
                submit-button-text="Generer Rapport"
                @submit="handleSubmit"
            />
        </div>
    </AppLayout>
</template>
