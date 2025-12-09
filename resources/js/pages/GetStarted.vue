<script setup lang="ts">
import GuestLayout from '@/layouts/GuestLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import ReportFormWizard from '@/components/reports/ReportFormWizard.vue';
import AuthModal from '@/components/AuthModal.vue';
import { useReportForm } from '@/composables/useReportForm';
import type { ReportFormProps } from '@/types/report';

const props = defineProps<ReportFormProps>();

// Auth state
const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const showAuthModal = ref(false);

    // Initialize report form composable with guest endpoints
    const reportForm = useReportForm({
        props,
        endpoints: {
            // Step 1: Job details - Use PATCH for existing reports, POST for new
            jobDetails: (reportId) => {
                if (reportId && reportId !== 'guest') {
                    return `/reports/guest/${reportId}/job-details`;
                }
                return '/reports/guest/job-details';
            },
            // Step 2: Competencies
            competencies: '/reports/guest/competencies',
            // Step 3: Payslip upload
            payslip: (reportId) => {
                if (reportId && reportId !== 'guest') {
                    return `/reports/guest/${reportId}/payslip`;
                }
                return '/reports/guest/payslip';
            },
            // Analyze anonymized payslip
            analyze: (reportId) => {
                if (reportId && reportId !== 'guest') {
                    return `/reports/guest/${reportId}/analyze`;
                }
                return null; // Will not be called if no reportId
            },
            // Delete payslip document
            deletePayslip: (reportId) => {
                if (reportId && reportId !== 'guest') {
                    return `/reports/guest/${reportId}/payslip`;
                }
                return null; // Will not be called if no reportId
            },
            submit: '/reports/guest/finalize',
        },
    });

// Handle submit - show auth modal for guests
const handleSubmit = () => {
    if (!isAuthenticated.value) {
        showAuthModal.value = true;
        return;
    }
    
    reportForm.submitReport();
};

// Handle auth success
const handleAuthSuccess = () => {
    // After successful auth, the page will reload with same report_id
    // Auto-finalize will run on mount if step 3 is ready
};

// Auto-finalize guest report after login
// Only finalize if report is complete (has all required data AND user explicitly submitted)
// Don't auto-finalize just because a payslip was uploaded
onMounted(() => {
    // Don't auto-finalize - let user explicitly submit
});

// Dynamic submit button text
const submitButtonText = computed(() => 
    isAuthenticated.value ? 'Generer Rapport' : 'Log ind & generer'
);

const isInsufficientData = computed(() => reportForm.payslipMatch.value === 'insufficient_data');

const authModalTitle = computed(() => 
    isInsufficientData.value 
        ? 'Log ind' 
        : 'Log ind for at se din rapport'
);

const authModalDescription = computed(() => 
    isInsufficientData.value
        ? 'Opret en konto eller log ind, så vi kan kontakte dig når din rapport er klar'
        : 'Opret en konto eller log ind for at generere og gemme din lønrapport'
);
</script>

<template>
    <Head title="Kom i gang - LønRadar" />

    <GuestLayout>
        <ReportFormWizard 
            :report-form="reportForm"
            :show-auth-prompt="!isAuthenticated"
            :is-authenticated="isAuthenticated"
            :submit-button-text="submitButtonText"
            @submit="handleSubmit"
        />

        <!-- Auth Modal for guests -->
        <AuthModal
            v-model:open="showAuthModal"
            :title="authModalTitle"
            :description="authModalDescription"
            @success="handleAuthSuccess"
        />
    </GuestLayout>
</template>
