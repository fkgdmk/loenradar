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
        step1: '/reports/guest/payslip',
        step2: '/reports/guest/step2',
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
onMounted(() => {
    if (isAuthenticated.value && reportForm.reportId.value && reportForm.reportId.value !== 'guest' && props.report?.step === 3) {
        reportForm.submitReport();
    }
});

// Dynamic submit button text
const submitButtonText = computed(() => 
    isAuthenticated.value ? 'Generer Rapport' : 'Opret konto & generer'
);
</script>

<template>
    <Head title="Kom i gang - LønRadar" />

    <GuestLayout>
        <ReportFormWizard 
            :report-form="reportForm"
            :show-auth-prompt="!isAuthenticated"
            :submit-button-text="submitButtonText"
            @submit="handleSubmit"
        />

        <!-- Auth Modal for guests -->
        <AuthModal
            v-model:open="showAuthModal"
            title="Log ind for at se din rapport"
            description="Opret en konto eller log ind for at generere og gemme din lønrapport"
            @success="handleAuthSuccess"
        />
    </GuestLayout>
</template>
