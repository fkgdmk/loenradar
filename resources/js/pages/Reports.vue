<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { MapPin, TrendingUp, Calendar, Plus, FileText } from 'lucide-vue-next';
import ToastSuccessAlert from '@/components/ToastSuccessAlert.vue';
import { ref, onMounted, watch } from 'vue';

interface Report {
    id: number;
    job_title: string | null;
    sub_job_title: string | null;
    experience: number | null;
    region: string | null;
    area_of_responsibility: string | null;
    lower_percentile: number | null;
    median: number | null;
    upper_percentile: number | null;
    conclusion: string | null;
    status: 'completed' | 'draft';
    created_at: string;
}

interface Props {
    reports: Report[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Rapporter',
        href: '/reports',
    },
];

// Flash message handling
const page = usePage();
const showSuccessAlert = ref(false);

const checkFlashMessage = () => {
    const flash = page.props.flash as { success?: string } | undefined;
    if (flash?.success) {
        showSuccessAlert.value = true;
    }
};

onMounted(() => {
    // Scroll to top when component mounts
    window.scrollTo({ top: 0, behavior: 'smooth' });
    // Check flash message after a small delay to ensure props are loaded
    setTimeout(() => {
        checkFlashMessage();
    }, 100);
});

// Watch for changes in flash messages (e.g., after redirect)
watch(() => page.props.flash, () => {
    checkFlashMessage();
}, { deep: true });

const dismissSuccessAlert = () => {
    showSuccessAlert.value = false;
};

const formatCurrency = (value: number | null): string => {
    if (!value) return 'N/A';
    return new Intl.NumberFormat('da-DK', {
        style: 'currency',
        currency: 'DKK',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};
</script>

<template>
    <Head title="Rapporter" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header med Opret knap -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-foreground">Rapporter</h1>
                <Button as-child>
                    <Link href="/reports/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Opret
                    </Link>
                </Button>
            </div>

            <!-- Tom state -->
            <div
                v-if="props.reports.length === 0"
                class="flex flex-col items-center justify-center py-16"
            >
                <FileText class="mb-4 h-16 w-16 text-muted-foreground" />
                <h2 class="mb-2 text-xl font-semibold text-foreground">
                    Ingen rapporter endnu
                </h2>
                <p class="mb-6 text-center text-muted-foreground max-w-md">
                    Opret din første rapport for at få et overblik over lønstatistikker baseret på dine filtre.
                </p>
                <Button as-child size="lg">
                    <Link href="/reports/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Opret Rapport
                    </Link>
                </Button>
            </div>

            <!-- Reports Grid -->
            <div
                v-else
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <template v-for="report in props.reports" :key="report.id">
                    <!-- Completed Reports (clickable) -->
                    <Link
                        v-if="report.status !== 'draft'"
                        :href="`/reports/${report.id}`"
                        class="block"
                    >
                        <Card
                            class="hover:shadow-md transition-shadow flex flex-col cursor-pointer"
                        >
                            <CardHeader>
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <CardTitle class="text-lg mb-2">
                                            {{ report.job_title || 'Ukendt jobtitel' }}
                                        </CardTitle>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="flex flex-col flex-1 space-y-4">
                                <!-- Region og Erfaring -->
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-if="report.region"
                                        variant="secondary"
                                        class="flex items-center gap-1"
                                    >
                                        <MapPin class="h-3 w-3" />
                                        {{ report.region }}
                                    </Badge>
                                    <Badge
                                        v-if="report.experience !== null"
                                        variant="secondary"
                                    >
                                        {{ report.experience }} års erfaring
                                    </Badge>
                                    <Badge
                                        v-if="report.area_of_responsibility"
                                        variant="secondary"
                                    >
                                        {{ report.area_of_responsibility }}
                                    </Badge>
                                </div>

                                <!-- Lønstatistikker -->
                                <div class="space-y-2 border-t pt-4">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">Median løn:</span>
                                        <span class="font-semibold text-foreground">
                                            {{ formatCurrency(report.median) }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="report.lower_percentile || report.upper_percentile"
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        <span v-if="report.lower_percentile">
                                            {{ formatCurrency(report.lower_percentile) }}
                                        </span>
                                        <TrendingUp class="h-3 w-3" />
                                        <span v-if="report.upper_percentile">
                                            {{ formatCurrency(report.upper_percentile) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Oprettelsesdato -->
                                <div class="flex items-center gap-2 text-xs text-muted-foreground border-t pt-4 mt-auto">
                                    <Calendar class="h-3 w-3" />
                                    <span>
                                        Oprettet {{ new Date(report.created_at).toLocaleDateString('da-DK') }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>

                    <!-- Draft Reports (not clickable) -->
                    <Card
                        v-else
                        class="flex flex-col opacity-75"
                    >
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <CardTitle class="text-lg mb-2">
                                        {{ report.job_title || 'Ukendt jobtitel' }}
                                    </CardTitle>
                                </div>
                                <Badge variant="outline" class="text-muted-foreground bg-muted">
                                    Kladde
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="flex flex-col flex-1 space-y-4">
                            <!-- Region og Erfaring -->
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    v-if="report.region"
                                    variant="secondary"
                                    class="flex items-center gap-1"
                                >
                                    <MapPin class="h-3 w-3" />
                                    {{ report.region }}
                                </Badge>
                                <Badge
                                    v-if="report.experience !== null"
                                    variant="secondary"
                                >
                                    {{ report.experience }} års erfaring
                                </Badge>
                                <Badge
                                    v-if="report.area_of_responsibility"
                                    variant="secondary"
                                >
                                    {{ report.area_of_responsibility }}
                                </Badge>
                            </div>

                            <!-- Lønstatistikker (skjules hvis ikke tilgængelig) -->
                            <div v-if="report.median" class="space-y-2 border-t pt-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Median løn:</span>
                                    <span class="font-semibold text-foreground">
                                        {{ formatCurrency(report.median) }}
                                    </span>
                                </div>
                                <div
                                    v-if="report.lower_percentile || report.upper_percentile"
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span v-if="report.lower_percentile">
                                        {{ formatCurrency(report.lower_percentile) }}
                                    </span>
                                    <TrendingUp class="h-3 w-3" />
                                    <span v-if="report.upper_percentile">
                                        {{ formatCurrency(report.upper_percentile) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Oprettelsesdato -->
                            <div class="flex items-center gap-2 text-xs text-muted-foreground border-t pt-4 mt-auto">
                                <Calendar class="h-3 w-3" />
                                <span>
                                    Oprettet {{ new Date(report.created_at).toLocaleDateString('da-DK') }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>

    <!-- Success Toast Alert -->
    <ToastSuccessAlert 
        :show="showSuccessAlert"
        message="Vi kontakter dig når rapporten er klar. Tak for hjælpen med at gøre vores database endnu bedre!"
        @dismiss="dismissSuccessAlert"
    />
</template>

