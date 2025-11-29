<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Combobox } from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertCircle } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { dashboard } from '@/routes';
import { ref, computed, watch, reactive } from 'vue';
import { 
    Upload, 
    FileText, 
    Briefcase, 
    MapPin, 
    ChevronRight, 
    ChevronLeft,
    Check,
    X,
    Sparkles,
    Pencil
} from 'lucide-vue-next';
import PayslipAnonymizer from '@/components/PayslipAnonymizer.vue';
interface JobTitle {
    id: number;
    name: string;
    name_en: string;
    skill_ids: number[];
}

interface Region {
    id: number;
    name: string;
}

interface AreaOfResponsibility {
    id: number;
    name: string;
}

interface ResponsibilityLevel {
    id: number;
    name: string;
}

interface Skill {
    id: number;
    name: string;
}

interface UploadedDocument {
    name: string;
    size: number;
    mime_type: string | null;
    preview_url: string | null;
    download_url: string;
}

interface ReportData {
    id: number;
    job_title_id: number;
    area_of_responsibility_id: number | null;
    experience: number;
    gender: string | null;
    region_id: number;
    responsibility_level_id: number | null;
    team_size: number | null;
    skill_ids: number[];
    step: number;
    document?: UploadedDocument | null;
}

interface Props {
    job_titles: JobTitle[];
    regions: Region[];
    areas_of_responsibility: AreaOfResponsibility[];
    responsibility_levels: ResponsibilityLevel[];
    skills: Skill[];
    leadership_roles: string[];
    report?: ReportData | null;
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
    {
        title: 'Opret Rapport',
        href: '/reports/create',
    },
];

// Hent report_id fra URL hvis den eksisterer
const urlParams = new URLSearchParams(window.location.search);
const urlReportId = urlParams.get('report_id');
const reportId = ref<number | null>(props.report?.id ?? (urlReportId ? parseInt(urlReportId) : null));

// Payslip warning state
const payslipWarning = ref<string | null>(null);
const showPayslipWarningModal = ref(false);

// Bestem step baseret på report data
const initialStep = computed(() => {
    if (props.report?.step) {
        return props.report.step;
    }
    // Hvis der er responsibility_level_id, er vi i step 2 eller 3
    if (props.report?.responsibility_level_id) {
        return props.report.skill_ids?.length >= 3 ? 3 : 2;
    }
    return 1;
});

const currentStep = ref(initialStep.value);
const fileInputRef = ref<HTMLInputElement | null>(null);
const uploadedFile = ref<File | null>(null);
const uploadedFilePreview = ref<string | null>(null);
const persistedDocument = ref<UploadedDocument | null>(props.report?.document ?? null);
const selectedSkills = ref<number[]>(props.report?.skill_ids ?? []);

// Anonymizer state
const showAnonymizer = ref(false);
const fileToAnonymize = ref<File | null>(null);

// Search states for dropdowns
const jobTitleSearch = ref('');
const areaOfResponsibilitySearch = ref('');
const regionSearch = ref('');
const responsibilityLevelSearch = ref('');
const skillSearch = ref('');

// Validation errors for step 1
const step1Errors = reactive<Record<string, string>>({
    document: '',
    job_title_id: '',
    area_of_responsibility_id: '',
    experience: '',
    region_id: '',
});

// Normaliser gender ved initialisering
const initialGender = props.report?.gender && props.report.gender.trim() !== '' 
    ? props.report.gender 
    : null;

const form = useForm({
    // Step 1
    document: null as File | null,
    job_title_id: props.report?.job_title_id ?? null as number | null,
    area_of_responsibility_id: props.report?.area_of_responsibility_id ?? null as number | null,
    experience: props.report?.experience ?? null as number | null,
    gender: initialGender as string | null,
    region_id: props.report?.region_id ?? null as number | null,
    
    // Step 2
    responsibility_level_id: props.report?.responsibility_level_id ?? null as number | null,
    team_size: props.report?.team_size ?? null as number | null,
    skill_ids: props.report?.skill_ids ?? [] as number[],
});

const experienceInput = computed({
    get: () => form.experience ?? undefined,
    set: (value: number | undefined) => {
        form.experience = typeof value === 'number' && !Number.isNaN(value) ? value : null;
    },
});

const teamSizeInput = computed({
    get: () => form.team_size ?? undefined,
    set: (value: number | undefined) => {
        form.team_size = typeof value === 'number' && !Number.isNaN(value) ? value : null;
    },
});

// Opdater form.document når uploadedFile ændres
watch(uploadedFile, (newFile) => {
    if (newFile) {
        form.document = newFile;
        persistedDocument.value = null;
    }
});

const formatFileSize = (sizeInBytes: number | null | undefined) => {
    if (!sizeInBytes) return '';
    const kb = sizeInBytes / 1024;
    if (kb > 1024) {
        return `${(kb / 1024).toFixed(2)} MB`;
    }
    return `${kb.toFixed(2)} KB`;
};

const showAreaOfResponsibility = computed(() => {
    if (!form.job_title_id) return false;
    const selectedJobTitle = props.job_titles.find(jt => jt.id === form.job_title_id);
    return selectedJobTitle ? props.leadership_roles.includes(selectedJobTitle.name_en) : false;
});

const showTeamSize = computed(() => {
    if (!form.responsibility_level_id) return false;
    const selectedLevel = props.responsibility_levels.find(rl => rl.id === form.responsibility_level_id);
    return selectedLevel ? ['Faglig leder', 'Personaleleder'].includes(selectedLevel.name) : false;
});

const selectedJobTitle = computed(() => {
    if (!form.job_title_id) return null;
    return props.job_titles.find(jt => jt.id === form.job_title_id) ?? null;
});

const jobTitleSkillIds = computed(() => selectedJobTitle.value?.skill_ids ?? []);

const availableSkills = computed(() => {
    if (!form.job_title_id) return [];
    if (!jobTitleSkillIds.value.length) {
        return props.skills;
    }
    return props.skills.filter(skill => jobTitleSkillIds.value.includes(skill.id));
});

const isStep1Valid = computed(() => {
    // Hvis der allerede er en report, er step 1 allerede gemt
    if (reportId.value) {
        return true;
    }
    // Ellers skal alle felter være udfyldt inkl. fil
    return !!(
        uploadedFile.value &&
        form.job_title_id &&
        (!showAreaOfResponsibility.value || form.area_of_responsibility_id) &&
        form.experience !== null &&
        form.region_id
    );
});

const isStep2Valid = computed(() => {
    return !!(
        form.responsibility_level_id &&
        (selectedSkills.value.length === 0 || selectedSkills.value.length <= 5)
    );
});

const canProceedToStep3 = computed(() => isStep2Valid.value);

// Tjek om steps er udfyldt for navigation
const isStep1Completed = computed(() => {
    return reportId.value !== null || isStep1Valid.value;
});

const isStep2Completed = computed(() => {
    return form.responsibility_level_id !== null;
});

const isStep3Completed = computed(() => {
    return isStep2Completed.value;
});

// Navigation til step
const navigateToStep = (step: number) => {
    if (step === 1 && isStep1Completed.value) {
        currentStep.value = 1;
    } else if (step === 2 && isStep2Completed.value) {
        currentStep.value = 2;
    } else if (step === 3 && isStep3Completed.value) {
        currentStep.value = 3;
    }
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (!file) return;
    
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('Ugyldig filtype. Kun PDF og billeder (JPG, PNG, WEBP) er tilladt.');
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        alert('Filen er for stor. Maksimal størrelse er 10MB.');
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
        return;
    }
    
    // For billeder og PDF'er, åbn anonymizer først
    if (file.type.startsWith('image/') || file.type === 'application/pdf') {
        fileToAnonymize.value = file;
        showAnonymizer.value = true;
    } else {
        setUploadedFile(file);
    }
};

const setUploadedFile = (file: File) => {
    uploadedFile.value = file;
    form.document = file;
    
    // Opret preview for billeder
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            uploadedFilePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        uploadedFilePreview.value = null;
    }
};

const handleAnonymizedImage = (anonymizedFile: File) => {
    showAnonymizer.value = false;
    fileToAnonymize.value = null;
    setUploadedFile(anonymizedFile);
    
    // Ryd file input
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const cancelAnonymizer = () => {
    showAnonymizer.value = false;
    fileToAnonymize.value = null;
    
    // Ryd file input
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const removeFile = () => {
    uploadedFile.value = null;
    uploadedFilePreview.value = null;
    form.document = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const toggleSkill = (skillId: number) => {
    const index = selectedSkills.value.indexOf(skillId);
    if (index > -1) {
        selectedSkills.value.splice(index, 1);
    } else {
        if (selectedSkills.value.length < 5) {
            selectedSkills.value.push(skillId);
        }
    }
    form.skill_ids = selectedSkills.value;
};

// Funktion til at rydde step 1 fejl
const clearStep1Errors = () => {
    step1Errors.document = '';
    step1Errors.job_title_id = '';
    step1Errors.area_of_responsibility_id = '';
    step1Errors.experience = '';
    step1Errors.region_id = '';
};

// Funktion til at sætte step 1 fejl fra backend
const setStep1Errors = (errors: Record<string, string>) => {
    clearStep1Errors();
    if (errors.document) step1Errors.document = errors.document;
    if (errors.job_title_id) step1Errors.job_title_id = errors.job_title_id;
    if (errors.area_of_responsibility_id) step1Errors.area_of_responsibility_id = errors.area_of_responsibility_id;
    if (errors.experience) step1Errors.experience = errors.experience;
    if (errors.region_id) step1Errors.region_id = errors.region_id;
};

const nextStep = async () => {
    if (currentStep.value === 1) {
        // Ryd tidligere fejl
        clearStep1Errors();
        
        // Normaliser gender: konverter tom streng til null
        const normalizedGender = form.gender && form.gender.trim() !== '' ? form.gender : null;
        
        // Hvis der allerede er en report, opdater step 1 data
        if (reportId.value) {
            const step1Form = useForm({
                job_title_id: form.job_title_id,
                area_of_responsibility_id: form.area_of_responsibility_id,
                experience: form.experience,
                gender: normalizedGender,
                region_id: form.region_id,
            });

            step1Form.patch(`/reports/${reportId.value}/step1`, {
                preserveScroll: true,
                onSuccess: () => {
                    // Gå til step 2 efter succesfuld opdatering
                    currentStep.value = 2;
                },
                onError: (errors: Record<string, string>) => {
                    // Håndter payslip_warning fejl separat
                    if (errors.payslip_warning) {
                        payslipWarning.value = errors.payslip_warning;
                        showPayslipWarningModal.value = true;
                        // Fjern payslip_warning fra errors så den ikke vises som normal fejl
                        delete errors.payslip_warning;
                    }
                    setStep1Errors(errors);
                },
            });
            return;
        }

        // Gem payslip og report når man går fra step 1 til step 2 (ny report)
        const step1Form = useForm({
            document: uploadedFile.value || form.document,
            job_title_id: form.job_title_id,
            area_of_responsibility_id: form.area_of_responsibility_id,
            experience: form.experience,
            gender: normalizedGender,
            region_id: form.region_id,
        });

        step1Form.post('/reports/payslip', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // Inertia vil automatisk reload siden med den nye URL og report data
                // Watch på props.report vil automatisk opdatere step til 2 når data er klar
            },
            onError: (errors: Record<string, string>) => {
                // Håndter payslip_warning fejl separat
                if (errors.payslip_warning) {
                    payslipWarning.value = errors.payslip_warning;
                    showPayslipWarningModal.value = true;
                    // Nulstil reportId da report er blevet slettet
                    reportId.value = null;
                    // Fjern payslip_warning fra errors så den ikke vises som normal fejl
                    delete errors.payslip_warning;
                }
                setStep1Errors(errors);
            },
        });
    } else if (currentStep.value === 2 && canProceedToStep3.value) {
        if (!reportId.value) {
            alert('Der opstod en fejl. Prøv venligst igen.');
            return;
        }

        // Gem step 2 data når man går fra step 2 til step 3
        const step2Form = useForm({
            responsibility_level_id: form.responsibility_level_id,
            team_size: form.team_size,
            skill_ids: form.skill_ids,
        });

        step2Form.patch(`/reports/${reportId.value}/step2`, {
            preserveScroll: false,
            onSuccess: () => {
                // Inertia vil automatisk reload siden med den opdaterede report data
                // currentStep vil blive sat automatisk baseret på report.step fra props
            },
            onError: (errors: any) => {
                console.error('Fejl ved gemning:', errors);
            },
        });
    }
};

const previousStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const submitForm = () => {
    if (!reportId.value) {
        alert('Der opstod en fejl. Prøv venligst igen.');
        return;
    }

    const finalForm = useForm({
        report_id: reportId.value,
        responsibility_level_id: form.responsibility_level_id,
        team_size: form.team_size,
        skill_ids: form.skill_ids,
    });

    finalForm.post('/reports', {
        onSuccess: () => {
            // Inertia automatisk redirect
        },
    });
};

const getJobTitleName = (id: number | null) => {
    if (!id) return '';
    return props.job_titles.find(jt => jt.id === id)?.name_en || '';
};

const getRegionName = (id: number | null) => {
    if (!id) return '';
    return props.regions.find(r => r.id === id)?.name || '';
};

const getAreaOfResponsibilityName = (id: number | null) => {
    if (!id) return '';
    return props.areas_of_responsibility.find(aor => aor.id === id)?.name || '';
};

const getResponsibilityLevelName = (id: number | null) => {
    if (!id) return '';
    return props.responsibility_levels.find(rl => rl.id === id)?.name || '';
};

const getSkillNames = (ids: number[]) => {
    return ids.map(id => props.skills.find(s => s.id === id)?.name).filter(Boolean).join(', ');
};

const sanitizeSelectedSkillsForJobTitle = () => {
    const allowed = jobTitleSkillIds.value;
    if (!allowed.length) return;
    const filtered = selectedSkills.value.filter(id => allowed.includes(id));
    if (filtered.length !== selectedSkills.value.length) {
        selectedSkills.value = filtered;
        form.skill_ids = filtered;
    }
};

// Opdater step og persistedDocument når report data kommer ind
watch(() => props.report, (newReport) => {
    if (newReport) {
        reportId.value = newReport.id;
        persistedDocument.value = newReport.document ?? null;
        selectedSkills.value = newReport.skill_ids ?? [];
        
        // Opdater form felter
        form.job_title_id = newReport.job_title_id ?? null;
        form.area_of_responsibility_id = newReport.area_of_responsibility_id ?? null;
        form.experience = newReport.experience ?? null;
        // Normaliser gender: konverter tom streng til null
        form.gender = newReport.gender && newReport.gender.trim() !== '' ? newReport.gender : null;
        form.region_id = newReport.region_id ?? null;
        form.responsibility_level_id = newReport.responsibility_level_id ?? null;
        form.team_size = newReport.team_size ?? null;
        form.skill_ids = newReport.skill_ids ?? [];
        
        // Opdater step baseret på report.step eller beregnet step
        // Hvis step 1 er fuldført (report eksisterer med data) men ingen responsibility_level_id,
        // skal vi være klar til step 2
        let newStep = newReport.step ?? initialStep.value;
        if (newStep === 1 && newReport.job_title_id && newReport.region_id && newReport.experience !== null) {
            // Step 1 er fuldført, så vi kan gå til step 2 hvis der ikke er responsibility_level_id endnu
            if (!newReport.responsibility_level_id) {
                newStep = 2;
            }
        }
        currentStep.value = newStep;
    }
}, { immediate: true });

watch(() => form.job_title_id, () => {
    if (!showAreaOfResponsibility.value) {
        form.area_of_responsibility_id = null;
    }
    if (!form.job_title_id) {
        selectedSkills.value = [];
        form.skill_ids = [];
    } else {
        sanitizeSelectedSkillsForJobTitle();
    }
    skillSearch.value = '';
});

watch(jobTitleSkillIds, () => {
    sanitizeSelectedSkillsForJobTitle();
});

// Normaliser gender: konverter tom streng til null
watch(() => form.gender, (newValue) => {
    if (newValue === '' || newValue === null) {
        form.gender = null;
    }
});

// Nulstil team_size når ansvarsniveau ændres til ikke-leder
watch(() => form.responsibility_level_id, () => {
    if (!showTeamSize.value) {
        form.team_size = null;
    }
});

// Computed options for comboboxes
const jobTitleOptions = computed(() => 
    props.job_titles.map(jt => ({ 
        value: jt.id, 
        label: jt.name_en,
        searchText: `${jt.name} ${jt.name_en}` // Søg i både name og name_en
    }))
);

const areaOfResponsibilityOptions = computed(() => 
    props.areas_of_responsibility.map(aor => ({ value: aor.id, label: aor.name }))
);

const regionOptions = computed(() => 
    props.regions.map(r => ({ value: r.id, label: r.name }))
);

const responsibilityLevelOptions = computed(() => 
    props.responsibility_levels.map(rl => ({ value: rl.id, label: rl.name }))
);

const filteredSkills = computed(() => {
    if (!form.job_title_id) return [];
    const source = availableSkills.value;
    if (!skillSearch.value) return source;
    const search = skillSearch.value.toLowerCase();
    return source.filter(s => s.name.toLowerCase().includes(search));
});

sanitizeSelectedSkillsForJobTitle();

// Funktion til at lukke modal
const closePayslipWarningModal = () => {
    showPayslipWarningModal.value = false;
    payslipWarning.value = null;
};
</script>

<template>
    <Head title="Opret Rapport" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 sm:gap-6 overflow-x-auto rounded-xl p-2 sm:p-4">
            <!-- Progress Indicator -->
            <div class="flex items-center justify-center gap-2 sm:gap-8">
                <button
                    type="button"
                    @click="navigateToStep(1)"
                    :disabled="!isStep1Completed"
                    :class="[
                        'flex items-center gap-2 sm:gap-3 transition-opacity',
                        isStep1Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                            currentStep >= 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        <Check v-if="currentStep > 1" class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span v-else>1</span>
                    </div>
                    <span class="hidden sm:inline text-sm font-medium" :class="currentStep >= 1 ? 'text-foreground' : 'text-muted-foreground'">
                        Upload Lønseddel
                    </span>
                </button>
                <ChevronRight class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground flex-shrink-0" />
                <button
                    type="button"
                    @click="navigateToStep(2)"
                    :disabled="!isStep2Completed"
                    :class="[
                        'flex items-center gap-2 sm:gap-3 transition-opacity',
                        isStep2Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                            currentStep >= 2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        <Check v-if="currentStep > 2" class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span v-else>2</span>
                    </div>
                    <span class="hidden sm:inline text-sm font-medium" :class="currentStep >= 2 ? 'text-foreground' : 'text-muted-foreground'">
                        Ansvar & Kompetencer
                    </span>
                </button>
                <ChevronRight class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground flex-shrink-0" />
                <button
                    type="button"
                    @click="navigateToStep(3)"
                    :disabled="!isStep3Completed"
                    :class="[
                        'flex items-center gap-2 sm:gap-3 transition-opacity',
                        isStep3Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                            currentStep >= 3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        3
                    </div>
                    <span class="hidden sm:inline text-sm font-medium" :class="currentStep >= 3 ? 'text-foreground' : 'text-muted-foreground'">
                        Gennemgå & Generer
                    </span>
                </button>
            </div>

            <Card class="max-w-3xl mx-auto w-full">
                <CardHeader>
                    <CardTitle>
                        <span v-if="currentStep === 1">Trin 1: Upload lønseddel og detaljer</span>
                        <span v-else-if="currentStep === 2">Trin 2: Ansvar og færdigheder</span>
                        <span v-else>Trin 3: Opsummering</span>
                    </CardTitle>
                    <CardDescription>
                        <span v-if="currentStep === 1">
                            Upload din lønseddel og indtast detaljer om din stilling
                        </span>
                        <span v-else-if="currentStep === 2">
                            Beskriv dit ansvarsniveau og vælg dine færdigheder
                        </span>
                        <span v-else>
                            Gennemgå dine valg og generer rapporten
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Step 1: Upload og detaljer -->
                    <div v-if="currentStep === 1" class="space-y-6">
                        <!-- File Upload -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <Upload class="h-4 w-4" />
                                Upload lønseddel
                            </Label>
                            <div v-if="uploadedFile" class="mt-2">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 rounded-lg border p-4">
                                    <div class="flex items-center gap-4 flex-1 min-w-0">
                                        <div v-if="uploadedFilePreview" class="h-16 w-16 sm:h-20 sm:w-20 overflow-hidden rounded flex-shrink-0">
                                            <img :src="uploadedFilePreview" alt="Preview" class="h-full w-full object-cover" />
                                        </div>
                                        <FileText v-else class="h-10 w-10 sm:h-12 sm:w-12 text-muted-foreground flex-shrink-0" />
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium truncate">{{ uploadedFile.name }}</p>
                                            <p class="text-sm text-muted-foreground">
                                                {{ formatFileSize(uploadedFile.size) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 sm:gap-1 self-end sm:self-center">
                                        <Button
                                            v-if="uploadedFile.type.startsWith('image/')"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="sm:variant-ghost"
                                            @click="fileToAnonymize = uploadedFile; showAnonymizer = true"
                                            title="Rediger anonymisering"
                                        >
                                            <Pencil class="h-4 w-4 sm:mr-0 mr-1" />
                                            <span class="sm:hidden">Rediger</span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="sm:variant-ghost"
                                            @click="removeFile"
                                            title="Fjern fil"
                                        >
                                            <X class="h-4 w-4 sm:mr-0 mr-1" />
                                            <span class="sm:hidden">Fjern</span>
                                        </Button>
                                    </div>
                                </div>
                                
                                <!-- Anonymizer for redigering af allerede uploadet fil -->
                                <div v-if="showAnonymizer && fileToAnonymize" class="mt-4">
                                    <PayslipAnonymizer
                                        :file="fileToAnonymize"
                                        @save="handleAnonymizedImage"
                                        @cancel="cancelAnonymizer"
                                    />
                                </div>
                            </div>
                            <div v-else-if="persistedDocument" class="mt-2">
                                <div class="flex items-center gap-4 rounded-lg border p-4">
                                    <div v-if="persistedDocument.preview_url" class="h-20 w-20 overflow-hidden rounded">
                                        <img :src="persistedDocument.preview_url" alt="Preview" class="h-full w-full object-cover" />
                                    </div>
                                    <FileText v-else class="h-12 w-12 text-muted-foreground" />
                                    <div class="flex-1">
                                        <p class="font-medium">{{ persistedDocument.name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ formatFileSize(persistedDocument.size) }}
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Din anonymiserede lønseddel er gemt og vil blive brugt i rapporten.
                                </p>
                            </div>
                            <div v-else class="mt-2">
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="hidden"
                                    @change="handleFileChange"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    @click="fileInputRef?.click()"
                                    :disabled="showAnonymizer"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    Vælg fil (PDF eller billede)
                                </Button>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Maksimal størrelse: 10MB. Du kan anonymisere direkte i browseren.
                                </p>
                                <InputError :message="step1Errors.document" />
                                
                                <!-- Anonymizer -->
                                <div v-if="showAnonymizer && fileToAnonymize" class="mt-4">
                                    <PayslipAnonymizer
                                        :file="fileToAnonymize"
                                        @save="handleAnonymizedImage"
                                        @cancel="cancelAnonymizer"
                                    />
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <!-- Job Title -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <Briefcase class="h-4 w-4" />
                                Jobtitel
                            </Label>
                            <Combobox
                                v-model="form.job_title_id"
                                :options="jobTitleOptions"
                                placeholder="Vælg jobtitel"
                                search-placeholder="Søg efter jobtitel..."
                                empty-text="Ingen jobtitler fundet"
                            />
                            <InputError :message="step1Errors.job_title_id" />
                        </div>

                        <!-- Area of Responsibility (conditional) -->
                        <div v-if="showAreaOfResponsibility">
                            <Label class="mb-2">Område</Label>
                            <Combobox
                                v-model="form.area_of_responsibility_id"
                                :options="areaOfResponsibilityOptions"
                                placeholder="Vælg område"
                                search-placeholder="Søg efter område..."
                                empty-text="Ingen områder fundet"
                            />
                            <InputError :message="step1Errors.area_of_responsibility_id" />
                        </div>

                        <!-- Experience -->
                        <div>
                            <Label class="mb-2">Erfaring i år</Label>
                            <Input
                                v-model.number="experienceInput"
                                type="number"
                                min="0"
                                max="50"
                                placeholder="fx. 5"
                            />
                            <InputError :message="step1Errors.experience" />
                        </div>

                        <!-- Region -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                Region
                            </Label>
                            <Combobox
                                v-model="form.region_id"
                                :options="regionOptions"
                                placeholder="Vælg region"
                                search-placeholder="Søg efter region..."
                                empty-text="Ingen regioner fundet"
                            />
                            <InputError :message="step1Errors.region_id" />
                        </div>
                    </div>

                    <!-- Step 2: Ansvar og færdigheder -->
                    <div v-if="currentStep === 2" class="space-y-6">
                        <!-- Responsibility Level -->
                        <div>
                            <Label class="mb-2">
                                Hvilket ansvarsniveau beskriver bedst din rolle?
                            </Label>
                            <Combobox
                                v-model="form.responsibility_level_id"
                                :options="responsibilityLevelOptions"
                                placeholder="Vælg ansvarsniveau"
                                search-placeholder="Søg efter ansvarsniveau..."
                                empty-text="Ingen ansvarsniveauer fundet"
                            />
                        </div>

                        <!-- Team Size (kun for ledere) -->
                        <div v-if="showTeamSize">
                            <Label class="mb-2">
                                Hvor mange personer er du faglig eller personalemæssig leder for?
                            </Label>
                            <Input
                                v-model.number="teamSizeInput"
                                type="number"
                                min="0"
                                max="1000"
                                placeholder="fx. 5"
                            />
                        </div>

                        <!-- Skills -->
                        <div>
                            <Label class="mb-2">
                                Oplist de vigtigste teknologier, systemer eller metoder, du bruger i dit job. (valgfrit)
                            </Label>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Vælg op til 5 færdigheder
                            </p>
                            <Input
                                v-model="skillSearch"
                                type="text"
                                placeholder="Søg efter færdigheder..."
                                class="mb-4"
                                :disabled="!form.job_title_id"
                            />
                            <div
                                v-if="!form.job_title_id"
                                class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                Vælg først en jobtitel for at se relevante færdigheder.
                            </div>
                            <div
                                v-else-if="filteredSkills.length === 0"
                                class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                Ingen færdigheder fundet for den valgte jobtitel.
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="skill in filteredSkills"
                                    :key="skill.id"
                                    :variant="selectedSkills.includes(skill.id) ? 'default' : 'outline'"
                                    class="cursor-pointer"
                                    @click="toggleSkill(skill.id)"
                                >
                                    {{ skill.name }}
                                    <Check v-if="selectedSkills.includes(skill.id)" class="ml-1 h-3 w-3" />
                                </Badge>
                            </div>
                            <p v-if="selectedSkills.length > 0" class="mt-2 text-sm text-muted-foreground">
                                Valgt: {{ selectedSkills.length }} / 5
                            </p>
                        </div>
                    </div>

                    <!-- Step 3: Opsummering -->
                    <div v-if="currentStep === 3" class="space-y-6">
                        <div class="rounded-lg border bg-muted/50 p-3 sm:p-4">
                            <p class="mb-4 text-sm font-medium">
                                Generer lønforhandlings rapport baseret på følgende:
                            </p>
                            <div class="space-y-2 sm:space-y-3 text-sm">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Jobtitel:</span>
                                    <span class="font-medium sm:text-right">{{ getJobTitleName(form.job_title_id) }}</span>
                                </div>
                                <div v-if="form.area_of_responsibility_id" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Område:</span>
                                    <span class="font-medium sm:text-right">{{ getAreaOfResponsibilityName(form.area_of_responsibility_id) }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Erfaring:</span>
                                    <span class="font-medium sm:text-right">{{ form.experience }} år</span>
                                </div>
                                <div v-if="form.gender" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Køn:</span>
                                    <span class="font-medium sm:text-right">{{ form.gender }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Region:</span>
                                    <span class="font-medium sm:text-right">{{ getRegionName(form.region_id) }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Ansvarsniveau:</span>
                                    <span class="font-medium sm:text-right">{{ getResponsibilityLevelName(form.responsibility_level_id) }}</span>
                                </div>
                                <div v-if="form.team_size" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Team størrelse:</span>
                                    <span class="font-medium sm:text-right">{{ form.team_size }} personer</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Færdigheder:</span>
                                    <span class="font-medium sm:text-right">{{ getSkillNames(form.skill_ids) || 'Ingen valgt' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-2 pt-4 border-t">
                        <Button
                            v-if="currentStep > 1"
                            type="button"
                            variant="outline"
                            class="w-full sm:w-auto"
                            @click="previousStep"
                        >
                            <ChevronLeft class="mr-2 h-4 w-4" />
                            Tilbage
                        </Button>
                        <div v-else class="hidden sm:block"></div>
                        
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            <Button
                                v-if="currentStep < 3"
                                type="button"
                                class="w-full sm:w-auto"
                                :disabled="currentStep === 2 && !canProceedToStep3"
                                @click="nextStep"
                            >
                                Næste
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                            <Button
                                v-else
                                type="button"
                                class="w-full sm:w-auto"
                                :disabled="form.processing"
                                @click="submitForm"
                            >
                                <Sparkles class="mr-2 h-4 w-4" />
                                Generer Rapport
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Payslip Warning Modal -->
        <Dialog :open="showPayslipWarningModal" @update:open="showPayslipWarningModal = $event">
            <DialogContent class="sm:max-w-xl">
                <div class="whitespace-pre-line text-base text-white mt-4 mx-4 space-y-2">
                    <div class="font-bold">
                        {{payslipWarning}}
                    </div>
                    <div>
                        Det er desværre ikke nok til at kunne lave en værdifuld rapport.
                    </div>
                    <div>
                        Vi arbejder på samle mere data, så vi kan give dig et mere præcist billede af dit lønniveau.
                    </div>
                    <div>
                        Vi kontaker dig lige så snart vi er i mål for din profil!
                    </div>
                </div>
                <DialogFooter>
                    <Button @click="closePayslipWarningModal">
                        Forstået
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

