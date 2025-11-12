<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Statistics {
    verifiedPayslips: number;
    jobTitles: number;
    regions: number;
}

interface CountItem {
    name: string;
    count: number;
}

defineProps<{
    statistics: Statistics;
    jobTitles: CountItem[];
    regions: CountItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- Verificerede Payslips -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-blue-50 to-blue-100 p-6 dark:border-sidebar-border dark:from-blue-950/50 dark:to-blue-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-blue-700 dark:text-blue-300"
                        >
                            Verificerede Lønsedler
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-blue-900 dark:text-blue-50"
                            >
                                {{ statistics.verifiedPayslips }}
                            </div>
                            <div class="text-xs text-blue-600 dark:text-blue-400">
                                Med jobtitel, løn og verificeret
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Antal Jobtitler -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-green-50 to-green-100 p-6 dark:border-sidebar-border dark:from-green-950/50 dark:to-green-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-green-700 dark:text-green-300"
                        >
                            Unikke Jobtitler
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-green-900 dark:text-green-50"
                            >
                                {{ statistics.jobTitles }}
                            </div>
                            <div
                                class="text-xs text-green-600 dark:text-green-400"
                            >
                                Med tilknyttede lønsedler
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Antal Regioner -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-purple-50 to-purple-100 p-6 dark:border-sidebar-border dark:from-purple-950/50 dark:to-purple-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-purple-700 dark:text-purple-300"
                        >
                            Regioner
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-purple-900 dark:text-purple-50"
                            >
                                {{ statistics.regions }}
                            </div>
                            <div
                                class="text-xs text-purple-600 dark:text-purple-400"
                            >
                                Med tilknyttede lønsedler
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste sektioner -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Jobtitler Liste -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler
                    </h2>
                    <div class="space-y-2">
                        <div
                            v-for="jobTitle in jobTitles"
                            :key="jobTitle.name"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <span class="font-medium text-foreground">
                                {{ jobTitle.name }}
                            </span>
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300"
                            >
                                {{ jobTitle.count }}
                            </span>
                        </div>
                        <div
                            v-if="jobTitles.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>

                <!-- Regioner Liste -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Regioner
                    </h2>
                    <div class="space-y-2">
                        <div
                            v-for="region in regions"
                            :key="region.name"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <span class="font-medium text-foreground">
                                {{ region.name }}
                            </span>
                            <span
                                class="rounded-full bg-purple-100 px-3 py-1 text-sm font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                            >
                                {{ region.count }}
                            </span>
                        </div>
                        <div
                            v-if="regions.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen regioner fundet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
