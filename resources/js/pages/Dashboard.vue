<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Statistics {
    verifiedPayslips: number;
    jobTitles: number;
    salaryDataPoints: number;
    jobTitlesWith5PlusPayslips: number;
    jobTitlesWith3PlusJobPostings: number;
}

interface CountItem {
    name: string;
    count: number;
    salaryDataPoints?: number;
    verifiedPayslips?: number;
}

interface RegionListItem {
    jobTitle: string;
    region: string;
    count: number;
}

interface ExperienceRangeListItem {
    jobTitle: string;
    experienceRange: string;
    count: number;
}

interface RegionExperienceListItem {
    jobTitle: string;
    region: string;
    experienceRange: string;
    count: number;
}

defineProps<{
    statistics: Statistics;
    jobTitles: CountItem[];
    regions: CountItem[];
    jobTitlesWith5PlusPayslipsPerRegionList: RegionListItem[];
    jobTitlesWith3PlusJobPostingsPerRegionList: RegionListItem[];
    experienceRanges: CountItem[];
    jobTitlesWith5PlusPayslipsPerExperienceRangeList: ExperienceRangeListItem[];
    jobTitlesWith5PlusJobPostingsPerExperienceRangeList: ExperienceRangeListItem[];
    verifiedPayslipsPerJobTitleRegionExperience: RegionExperienceListItem[];
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
                <!-- Løn datapunkter -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-green-50 to-green-100 p-6 dark:border-sidebar-border dark:from-green-950/50 dark:to-green-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-green-700 dark:text-green-300"
                        >
                            Løn datapunkter
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-green-900 dark:text-green-50"
                            >
                                {{ statistics.salaryDataPoints }}
                            </div>
                            <div
                                class="text-xs text-green-600 dark:text-green-400"
                            >
                               Med jobtitel og løn
                            </div>
                        </div>
                    </div>
                </div>

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
                                Med jobtitel, løn og verificeret lønsedler 
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
                        Unikke Jobtitler
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-purple-900 dark:text-purple-50"
                            >
                                {{ statistics.jobTitles }}
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

            <!-- Nye statistik cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                <!-- Jobtitler med 5+ lønsedler -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-orange-50 to-orange-100 p-6 dark:border-sidebar-border dark:from-orange-950/50 dark:to-orange-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-orange-700 dark:text-orange-300"
                        >
                            Jobtitler med 5+ lønsedler
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-orange-900 dark:text-orange-50"
                            >
                                {{ statistics.jobTitlesWith5PlusPayslips }}
                            </div>
                            <div
                                class="text-xs text-orange-600 dark:text-orange-400"
                            >
                                Verificerede lønsedler
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jobtitler med 3+ jobopslag -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-teal-50 to-teal-100 p-6 dark:border-sidebar-border dark:from-teal-950/50 dark:to-teal-900/30"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div
                            class="text-sm font-medium text-teal-700 dark:text-teal-300"
                        >
                            Jobtitler med 3+ jobopslag
                        </div>
                        <div class="space-y-1">
                            <div
                                class="text-4xl font-bold text-teal-900 dark:text-teal-50"
                            >
                                {{ statistics.jobTitlesWith3PlusJobPostings }}
                            </div>
                            <div
                                class="text-xs text-teal-600 dark:text-teal-400"
                            >
                                Totalt antal jobtitler
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste sektioner -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Jobtitler Liste -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="jobTitle in jobTitles"
                            :key="jobTitle.name"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <span class="font-medium text-foreground">
                                {{ jobTitle.name }}
                            </span>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                    title="Løndatapunkter"
                                >
                                    {{ jobTitle.salaryDataPoints ?? jobTitle.count }}
                                </span>
                                <span
                                    class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                    title="Verificerede lønseddeler"
                                >
                                    {{ jobTitle.verifiedPayslips ?? 0 }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="jobTitles.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>

                <!-- Statistiske grupper Liste -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Statistiske grupper
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
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
                            Ingen statistiske grupper fundet
                        </div>
                    </div>
                </div>

                <!-- Erfaringsniveauer Liste -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Erfaringsniveauer
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="experienceRange in experienceRanges"
                            :key="experienceRange.name"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <span class="font-medium text-foreground">
                                {{ experienceRange.name }}
                            </span>
                            <span
                                class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                {{ experienceRange.count }}
                            </span>
                        </div>
                        <div
                            v-if="experienceRanges.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen erfaringsniveauer fundet
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nye lister -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Jobtitler med 5+ lønsedler pr statistisk gruppe -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler med ≥5 lønsedler pr statistisk gruppe
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="(item, index) in jobTitlesWith5PlusPayslipsPerRegionList"
                            :key="`${item.jobTitle}-${item.region}-${index}`"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">
                                    {{ item.jobTitle }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ item.region }}
                                </span>
                            </div>
                            <span
                                class="rounded-full bg-orange-100 px-3 py-1 text-sm font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                        <div
                            v-if="jobTitlesWith5PlusPayslipsPerRegionList.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>

                <!-- Jobtitler med 3+ jobopslag pr statistisk gruppe -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler med ≥3 jobopslag pr statistisk gruppe
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="(item, index) in jobTitlesWith3PlusJobPostingsPerRegionList"
                            :key="`${item.jobTitle}-${item.region}-${index}`"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">
                                    {{ item.jobTitle }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ item.region }}
                                </span>
                            </div>
                            <span
                                class="rounded-full bg-teal-100 px-3 py-1 text-sm font-semibold text-teal-700 dark:bg-teal-900/30 dark:text-teal-300"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                        <div
                            v-if="jobTitlesWith3PlusJobPostingsPerRegionList.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>
            </div>

            <!-- Experience range lister -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Jobtitler med 5+ lønsedler pr erfaringsniveau -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler med ≥5 lønsedler pr erfaringsniveau
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="(item, index) in jobTitlesWith5PlusPayslipsPerExperienceRangeList"
                            :key="`${item.jobTitle}-${item.experienceRange}-${index}`"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">
                                    {{ item.jobTitle }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ item.experienceRange }}
                                </span>
                            </div>
                            <span
                                class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                        <div
                            v-if="jobTitlesWith5PlusPayslipsPerExperienceRangeList.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>

                <!-- Jobtitler med 3+ jobopslag pr erfaringsniveau -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-foreground"
                    >
                        Jobtitler med ≥3 jobopslag pr erfaringsniveau
                    </h2>
                    <div class="h-96 space-y-2 overflow-y-auto">
                        <div
                            v-for="(item, index) in jobTitlesWith5PlusJobPostingsPerExperienceRangeList"
                            :key="`${item.jobTitle}-${item.experienceRange}-${index}`"
                            class="flex items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar/50 px-4 py-3 transition-colors hover:bg-sidebar/80"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium text-foreground">
                                    {{ item.jobTitle }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    {{ item.experienceRange }}
                                </span>
                            </div>
                            <span
                                class="rounded-full bg-pink-100 px-3 py-1 text-sm font-semibold text-pink-700 dark:bg-pink-900/30 dark:text-pink-300"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                        <div
                            v-if="jobTitlesWith5PlusJobPostingsPerExperienceRangeList.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Ingen jobtitler fundet
                        </div>
                    </div>
                </div>
            </div>

            <!-- Komplet oversigt: Verificerede lønsedler pr jobtitel, statistisk gruppe og erfaringsniveau -->
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-sidebar">
                <h2 class="mb-4 text-lg font-semibold text-foreground">
                    Komplet oversigt: Verificerede lønsedler pr jobtitel, statistisk gruppe og erfaringsniveau
                </h2>
                <div class="h-[500px] overflow-y-auto">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-white dark:bg-sidebar">
                            <tr class="border-b border-sidebar-border/50">
                                <th class="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                    Jobtitel
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                    Statistisk gruppe
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                    Erfaringsniveau
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-foreground">
                                    Verificerede lønsedler
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in verifiedPayslipsPerJobTitleRegionExperience"
                                :key="`${item.jobTitle}-${item.region}-${item.experienceRange}-${index}`"
                                class="border-b border-sidebar-border/30 transition-colors hover:bg-sidebar/50"
                            >
                                <td class="px-4 py-3 font-medium text-foreground">
                                    {{ item.jobTitle }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ item.region }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': item.experienceRange === '0-3 år',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': item.experienceRange === '4-9 år',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300': item.experienceRange === '10+ år'
                                        }"
                                    >
                                        {{ item.experienceRange }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span
                                        class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                    >
                                        {{ item.count }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="verifiedPayslipsPerJobTitleRegionExperience.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        Ingen data fundet
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
