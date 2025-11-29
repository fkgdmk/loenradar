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
        // For step 1: PATCH existing report, or POST new payslip
        step1: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/step1`;
            }
            return '/reports/payslip';
        },
        step1Method: hasExistingReport.value ? 'patch' : 'post',
        // For step 2: PATCH existing report
        step2: (reportId) => {
            if (reportId && reportId !== 'guest') {
                return `/reports/${reportId}/step2`;
            }
            return '/reports/guest/step2';
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
        onSuccess: () => {
            // Inertia automatisk redirect
        },
    });
};

// Auto-finalize guest report after login
onMounted(() => {
    if (isAuthenticated.value && reportForm.reportId.value && reportForm.reportId.value !== 'guest' && props.report?.step === 3) {
        // Check if this is a guest report (no user_id yet) that needs finalization
        const finalizeForm = useForm({
            report_id: reportForm.reportId.value,
        });
        finalizeForm.post('/reports/guest/finalize', {
            onSuccess: () => {
                // Redirect happens automatically
            },
            onError: (errors: any) => {
                console.error('Fejl ved automatisk færdiggørelse:', errors);
            },
        });
    }
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
