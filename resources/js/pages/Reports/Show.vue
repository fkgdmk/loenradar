<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft, Check, TrendingUp, Download, Share2, ExternalLink, Info, CheckCircle2, X } from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface ProsaArea {
    id: number;
    area_name: string;
}

interface Region {
    id: number;
    name: string;
    prosa_areas: ProsaArea[];
}

interface ProsaSalaryStat {
    id: number;
    prosa_area_id: number;
    experience_from: number;
    experience_to: number;
    lower_quartile_salary: number;
    median_salary: number;
    upper_quartile_salary: number;
    average_salary: number;
    sample_size: number;
    statistic_year: number;
}

interface ProsaJobCategory {
    id: number;
    category_name: string;
    description: string | null;
    salary_stats: ProsaSalaryStat[];
}

interface JobTitle {
    id: number;
    name: string;
    name_en: string;
    prosa_categories: ProsaJobCategory[];
}

interface AreaOfResponsibility {
    id: number;
    name: string;
}

interface Payslip {
    id: number;
    salary: number;
    experience: number;
    uploaded_at: string;
    region: Region;
}

interface Skill {
    id: number;
    name: string;
}

interface JobPosting {
    id: number;
    title: string;
    company: string | null;
    url: string;
    salary_from: number | null;
    salary_to: number | null;
    region: Region | null;
    skills: Skill[];
    pivot: {
        match_score: number | null;
    };
}

interface Report {
    id: number;
    job_title: JobTitle;
    experience: number;
    region: Region;
    area_of_responsibility: AreaOfResponsibility | null;
    conclusion: string;
    description: string | null;
    lower_percentile: number;
    median: number;
    upper_percentile: number;
    created_at: string;
    uploaded_payslip_id: number;
    payslips: Payslip[];
    job_postings?: JobPosting[];
    filters?: {
        skill_ids?: number[];
        responsibility_level_id?: number;
        team_size?: number;
    };
}

interface ResponsibilityLevel {
    id: number;
    name: string;
}

const props = defineProps<{
    report: Report;
    responsibilityLevel: ResponsibilityLevel | null;
}>();

// Flash message handling
const page = usePage();
const showSuccessAlert = ref(false);
const successMessage = ref('');

onMounted(() => {
    const flash = page.props.flash as { success?: string } | undefined;
    if (flash?.success) {
        successMessage.value = 'Din rapport blev genereret';
        showSuccessAlert.value = true;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            showSuccessAlert.value = false;
        }, 5000);
    }
});

const dismissAlert = () => {
    showSuccessAlert.value = false;
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('da-DK', {
        style: 'currency',
        currency: 'DKK',
        maximumFractionDigits: 0,
    }).format(amount);
};

const roundToThousands = (amount: number) => {
    return Math.round(amount / 1000) * 1000;
};

const formatCurrencyRounded = (amount: number) => {
    return formatCurrency(roundToThousands(amount));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('da-DK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatMonthYear = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('da-DK', {
        month: 'long',
        year: 'numeric',
    });
};

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Rapporter',
        href: '/reports',
    },
    {
        title: 'Rapport',
        href: '#',
    },
];

const salaryRangeWidth = computed(() => {
    if (!props.report.lower_percentile || !props.report.upper_percentile) return 0;
    const min = props.report.lower_percentile * 0.8;
    const max = props.report.upper_percentile * 1.2;
    return max - min;
});

const getPosition = (value: number) => {
    if (!props.report.lower_percentile || !props.report.upper_percentile) return 0;
    const min = props.report.lower_percentile * 0.8;
    const max = props.report.upper_percentile * 1.2;
    const range = max - min;
    return ((value - min) / range) * 100;
};

const relevantProsaStats = computed(() => {
    const categories = props.report.job_title.prosa_categories;
    if (!categories || categories.length === 0) return [];

    // Get IDs of prosa areas associated with the report's region
    const reportProsaAreaIds = props.report.region.prosa_areas?.map(area => area.id) || [];

    // Get stats from the first category
    let stats = categories[0].salary_stats;

    // Filter by prosa area if we have any mapping
    if (reportProsaAreaIds.length > 0) {
        stats = stats.filter(stat => reportProsaAreaIds.includes(stat.prosa_area_id));
    }

    // Return stats sorted by experience
    return stats.sort((a, b) => a.experience_from - b.experience_from);
});

const isStatRelevant = (stat: ProsaSalaryStat) => {
    return props.report.experience >= stat.experience_from && props.report.experience <= stat.experience_to;
};

const getMatchScoreClass = (score: number | null) => {
    if (!score) return 'bg-muted text-muted-foreground';
    if (score >= 8) return 'bg-green-500/20 text-green-700 dark:text-green-400';
    if (score >= 6) return 'bg-blue-500/20 text-blue-700 dark:text-blue-400';
    if (score >= 4) return 'bg-yellow-500/20 text-yellow-700 dark:text-yellow-400';
    return 'bg-orange-500/20 text-orange-700 dark:text-orange-400';
};

const formatSalaryRange = (from: number | null, to: number | null) => {
    if (from && to) {
        return `${formatCurrency(from)} - ${formatCurrency(to)}`;
    }
    if (from) {
        return `Fra ${formatCurrency(from)}`;
    }
    if (to) {
        return `Til ${formatCurrency(to)}`;
    }
    return '';
};

const getReportSkillIds = (): number[] => {
    return props.report.filters?.skill_ids || [];
};

const isSkillMatching = (skillId: number): boolean => {
    return getReportSkillIds().includes(skillId);
};

</script>

<template>
    <Head title="Lønrapport" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 sm:gap-6 overflow-x-auto rounded-xl p-2 sm:p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="space-y-1">
                    <h2 class="text-xl sm:text-2xl font-semibold tracking-tight">Din Lønrapport</h2>
                    <p class="text-sm text-muted-foreground">
                        Genereret d. {{ formatDate(report.created_at) }}
                    </p>
                </div>
                <div class="flex items-center">
                    <Button variant="outline" as-child class="w-full sm:w-auto">
                        <Link href="/reports">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Tilbage til oversigt
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Description Alert -->
            <Alert v-if="report.description" class="border-blue-200 bg-blue-50/50 dark:border-blue-900 dark:bg-blue-950/30">
                <Info class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                <AlertDescription class="text-blue-900 dark:text-blue-100">
                    {{ report.description }}
                </AlertDescription>
            </Alert>

            <!-- Top Section: Konklusion + Profil Detaljer side by side -->
            <div class="grid gap-4 sm:gap-6 md:grid-cols-3">
                <!-- Conclusion Card -->
                <Card class="bg-primary/5 border-primary/20 flex flex-col md:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-primary">
                            <TrendingUp class="h-5 w-5" />
                            Konklusion
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex-1 flex items-center">
                        <p class="text-lg font-medium leading-relaxed">
                            Baseret på 
                                            <Dialog>
                                                <DialogTrigger as-child>
                                                    <span class="font-bold underline cursor-pointer hover:text-primary transition-colors">
                                        {{ report.payslips.length }} datapunkter
                                                    </span>
                                                </DialogTrigger>
                                                <DialogContent class="sm:max-w-xl max-h-[80vh] overflow-y-auto">
                                                    <DialogHeader>
                                                        <DialogTitle>Lønsedler</DialogTitle>
                                                        <DialogDescription>
                                                            Herunder ses lønsedlerne der danner grundlag for rapporten. Løn er ikke vist for at sikre anonymitet.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div class="">
                                                        <div class="rounded-md border">
                                                            <table class="w-full text-sm">
                                                                <thead class="border-b bg-muted/50">
                                                                    <tr>
                                                                        <th class="p-4 text-left font-medium">Region</th>
                                                                        <th class="p-4 text-left font-medium">Erfaring</th>
                                                                        <th class="p-4 text-left font-medium">Uploadet</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <tr v-for="payslip in report.payslips" :key="payslip.id" class="border-b last:border-0 hover:bg-muted/50">
                                                                        <td class="p-4">{{ payslip.region?.name }}</td>
                                                                        <td class="p-4">{{ payslip.experience }} år</td>
                                                                        <td class="p-4 text-muted-foreground">{{ formatMonthYear(payslip.uploaded_at) }}</td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </DialogContent>
                                            </Dialog>
                            for din profil, er et realistisk og velbegrundet lønudspil i intervallet 
                            <span class="font-bold">{{ formatCurrencyRounded(report.lower_percentile) }}</span> 
                            til 
                            <span class="font-bold">{{ formatCurrencyRounded(report.upper_percentile) }}</span>.
                        </p>
                    </CardContent>
                </Card>

                <!-- Profile Details Card -->
                <Card class="flex flex-col">
                    <CardHeader>
                        <CardTitle>Profil Detaljer</CardTitle>
                    </CardHeader>
                    <CardContent class="flex-1">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Jobtitel</span>
                                <span class="font-medium text-right">{{ report.job_title.name_en }}</span>
                            </div>
                            
                            <div v-if="report.area_of_responsibility" class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Område</span>
                                <span class="font-medium text-right">{{ report.area_of_responsibility.name }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Erfaring</span>
                                <span class="font-medium">{{ report.experience }} år</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Region</span>
                                <span class="font-medium">{{ report.region.name }}</span>
                            </div>

                            <div v-if="responsibilityLevel" class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Rolle</span>
                                <span class="font-medium text-right">{{ responsibilityLevel.name }}</span>
                            </div>

                            <div v-if="report.filters?.team_size && report.filters.team_size > 0" class="flex justify-between items-center">
                                <span class="text-sm text-muted-foreground">Lederansvar</span>
                                <span class="font-medium">{{ report.filters.team_size }} medarbejder{{ report.filters.team_size === 1 ? '' : 'e' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Full Width Cards Section -->
            <div class="space-y-4 sm:space-y-6">

                    <!-- Visualization Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Løninterval</CardTitle>
                            <CardDescription>
                                Visualisering af lønniveauet baseret på sammenlignelige profiler.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4 sm:space-y-6">
                                <!-- Salary Range Bar - Clean Minimal Design -->
                                <div class="relative py-3">
                                    <!-- Main track -->
                                    <div class="relative h-2.5 bg-muted/60 rounded-full">
                                        <!-- Active range -->
                                        <div 
                                            class="absolute inset-y-0 bg-gradient-to-r from-primary/60 via-primary to-primary/60 rounded-full"
                                            :style="{ 
                                                left: `${getPosition(report.lower_percentile)}%`, 
                                                right: `${100 - getPosition(report.upper_percentile)}%`
                                            }"
                                        ></div>
                                        
                                        <!-- Lower percentile marker -->
                                        <div 
                                            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2"
                                            :style="{ left: `${getPosition(report.lower_percentile)}%` }"
                                        >
                                            <div class="w-4 h-4 rounded-full bg-background border-[3px] border-primary/70 shadow-sm"></div>
                                        </div>
                                        
                                        <!-- Median marker (prominent) -->
                                        <div 
                                            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 z-10"
                                            :style="{ left: `${getPosition(report.median)}%` }"
                                        >
                                            <div class="w-5 h-5 rounded-full bg-primary shadow-lg shadow-primary/40"></div>
                                        </div>
                                        
                                        <!-- Upper percentile marker -->
                                        <div 
                                            class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2"
                                            :style="{ left: `${getPosition(report.upper_percentile)}%` }"
                                        >
                                            <div class="w-4 h-4 rounded-full bg-background border-[3px] border-primary/70 shadow-sm"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Values displayed as cards below -->
                                <div class="grid grid-cols-3 gap-1.5 sm:gap-3">
                                    <div class="text-center p-2 sm:p-3 rounded-lg bg-muted/50">
                                        <div class="text-[9px] sm:text-xs text-muted-foreground mb-0.5 sm:mb-1">Nedre (25%)</div>
                                        <div class="text-xs sm:text-base font-bold truncate">{{ formatCurrencyRounded(report.lower_percentile) }}</div>
                                    </div>
                                    <div class="text-center p-2 sm:p-3 rounded-lg bg-primary/10 border border-primary/20">
                                        <div class="text-[9px] sm:text-xs text-primary font-medium mb-0.5 sm:mb-1">Median (50%)</div>
                                        <div class="text-sm sm:text-lg font-bold text-primary truncate">{{ formatCurrencyRounded(report.median) }}</div>
                                    </div>
                                    <div class="text-center p-2 sm:p-3 rounded-lg bg-muted/50">
                                        <div class="text-[9px] sm:text-xs text-muted-foreground mb-0.5 sm:mb-1">Øvre (75%)</div>
                                        <div class="text-xs sm:text-base font-bold truncate">{{ formatCurrencyRounded(report.upper_percentile) }}</div>
                                    </div>
                                </div>

                                <p class="text-center text-[10px] sm:text-xs text-muted-foreground">
                                    50% af lønningerne ligger mellem nedre og øvre kvartil.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Prosa Stats Card -->
                    <Card v-if="relevantProsaStats.length > 0">
                        <CardHeader>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <CardTitle class="text-base sm:text-lg">Fagforenings Statistik (PROSA)</CardTitle>
                                    <CardDescription class="text-xs sm:text-sm">
                                            Prosa's lønsstatistik er baseret på 5 forskellige grupper. Din rolle ligger i gruppen: <span class="font-bold">{{ report.job_title.prosa_categories[0].category_name }}</span>
                                            <Dialog>
                                                <DialogTrigger as-child>
                                                    <button class="ml-1 text-primary hover:text-primary/80 underline cursor-pointer transition-colors">
                                                        (Læs mere her)
                                                    </button>
                                                </DialogTrigger>
                                                <DialogContent class="sm:max-w-lg">
                                                    <DialogHeader>
                                                        <DialogTitle>{{ report.job_title.prosa_categories[0].category_name }}</DialogTitle>
                                                        <DialogDescription>
                                                            PROSA jobkategori beskrivelse
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div class="prose prose-sm dark:prose-invert max-w-none">
                                                        <p class="whitespace-pre-line text-sm leading-relaxed">{{ report.job_title.prosa_categories[0].description }}</p>
                                                    </div>
                                                </DialogContent>
                                            </Dialog>
                                    </CardDescription>
                                </div>
                                <a href="https://www.prosa.dk/raad-og-svar/loenstatistik" target="_blank" rel="noopener noreferrer">
                                    <Button variant="ghost" size="sm">
                                        Kilde <ExternalLink class="ml-2 h-4 w-4" />
                                    </Button>
                                </a>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="rounded-md border overflow-x-auto">
                                <table class="w-full text-xs sm:text-sm min-w-[500px]">
                                    <thead class="border-b bg-muted/50">
                                        <tr>
                                            <th class="p-2 sm:p-3 text-left font-medium whitespace-nowrap">Erfaring</th>
                                            <th class="p-2 sm:p-3 text-right font-medium whitespace-nowrap">Nedre</th>
                                            <th class="p-2 sm:p-3 text-right font-medium whitespace-nowrap">Median</th>
                                            <th class="p-2 sm:p-3 text-right font-medium whitespace-nowrap">Øvre</th>
                                            <th class="p-2 sm:p-3 text-right font-medium whitespace-nowrap">Gns.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr 
                                            v-for="stat in relevantProsaStats" 
                                            :key="stat.id" 
                                            class="border-b last:border-0"
                                            :class="isStatRelevant(stat) ? 'bg-primary/10 font-medium' : 'hover:bg-muted/50 text-muted-foreground'"
                                        >
                                            <td class="p-2 sm:p-3 whitespace-nowrap">
                                                {{ stat.experience_from }}-{{ stat.experience_to }} år
                                                <span v-if="isStatRelevant(stat)" class="ml-1 sm:ml-2 text-[10px] sm:text-xs bg-primary text-primary-foreground px-1 sm:px-1.5 py-0.5 rounded-full">Dig</span>
                                            </td>
                                            <td class="p-2 sm:p-3 text-right whitespace-nowrap">{{ formatCurrency(stat.lower_quartile_salary) }}</td>
                                            <td class="p-2 sm:p-3 text-right whitespace-nowrap">{{ formatCurrency(stat.median_salary) }}</td>
                                            <td class="p-2 sm:p-3 text-right whitespace-nowrap">{{ formatCurrency(stat.upper_quartile_salary) }}</td>
                                            <td class="p-2 sm:p-3 text-right whitespace-nowrap">{{ formatCurrency(stat.average_salary) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-3 sm:mt-4 text-[10px] sm:text-xs text-muted-foreground">
                                Data er baseret på PROSA's lønstatistik. Markeret række viser intervallet der matcher din erfaring.
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Job Postings Card -->
                    <Card v-if="report.job_postings && report.job_postings.length > 0">
                        <CardHeader>
                            <CardTitle class="text-base sm:text-lg">Matchende Jobopslag</CardTitle>
                            <CardDescription class="text-xs sm:text-sm">
                                Jobopslag der matcher din profil baseret på job titel, region, erfaring og skills.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div 
                                    v-for="jobPosting in report.job_postings" 
                                    :key="jobPosting.id"
                                    class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 p-3 sm:p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-start sm:items-center gap-2 mb-2">
                                            <h3 class="font-semibold text-base sm:text-lg break-words">{{ jobPosting.title }}</h3>
                                            <span 
                                                class="px-2 py-0.5 sm:py-1 text-[10px] sm:text-xs font-medium rounded-full flex-shrink-0"
                                                :class="getMatchScoreClass(jobPosting.pivot.match_score)"
                                            >
                                                Match: {{ jobPosting.pivot.match_score ?? 0 }}/10
                                            </span>
                                        </div>
                                        <div class="space-y-1.5 sm:space-y-2 text-xs sm:text-sm text-muted-foreground">
                                            <div v-if="jobPosting.company" class="font-medium text-foreground">
                                                {{ jobPosting.company }}
                                            </div>
                                            <div v-if="jobPosting.region" class="flex flex-wrap items-center gap-2 sm:gap-4">
                                                <span>{{ jobPosting.region.name }}</span>
                                                <span v-if="jobPosting.salary_from || jobPosting.salary_to">
                                                    {{ formatSalaryRange(jobPosting.salary_from, jobPosting.salary_to) }}
                                                </span>
                                            </div>
                                            <div v-if="jobPosting.skills && jobPosting.skills.length > 0" class="flex flex-wrap gap-1 mt-2">
                                                <span
                                                    v-for="skill in jobPosting.skills"
                                                    :key="skill.id"
                                                    class="px-1 py-0.5 text-[9px] rounded border"
                                                    :class="isSkillMatching(skill.id) 
                                                        ? 'bg-green-500/20 text-green-700 dark:text-green-400 border-green-500/30' 
                                                        : 'bg-muted text-muted-foreground border-border'"
                                                >
                                                    {{ skill.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sm:ml-4 flex-shrink-0">
                                        <Button variant="outline" size="sm" as-child class="w-full sm:w-auto">
                                            <a :href="jobPosting.url" target="_blank" rel="noopener noreferrer">
                                                Se opslag <ExternalLink class="ml-2 h-4 w-4" />
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
            </div>
        </div>
    </AppLayout>

    <!-- Success Toast Alert -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div 
                v-if="showSuccessAlert" 
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4"
            >
                <Alert variant="default" class="shadow-lg border-gray-500/80">
                    <CheckCircle2 class="h-4 w-4" />
                    <AlertTitle class="font-semibold">Sådan!</AlertTitle>
                    <AlertDescription>{{ successMessage }}</AlertDescription>
                    <button 
                        @click="dismissAlert" 
                        class="absolute top-3 right-3 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </Alert>
            </div>
        </Transition>
    </Teleport>
</template>
