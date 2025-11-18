<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Combobox } from '@/components/ui/combobox';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import { 
    Upload, 
    FileText, 
    Briefcase, 
    MapPin, 
    User, 
    ChevronRight, 
    ChevronLeft,
    Check,
    X,
    Edit,
    Sparkles
} from 'lucide-vue-next';
interface JobTitle {
    id: number;
    name: string;
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

// Search states for dropdowns
const jobTitleSearch = ref('');
const areaOfResponsibilitySearch = ref('');
const regionSearch = ref('');
const responsibilityLevelSearch = ref('');
const skillSearch = ref('');

const form = useForm({
    // Step 1
    document: null as File | null,
    job_title_id: props.report?.job_title_id ?? null as number | null,
    area_of_responsibility_id: props.report?.area_of_responsibility_id ?? null as number | null,
    experience: props.report?.experience ?? null as number | null,
    gender: props.report?.gender ?? null as string | null,
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
    return selectedJobTitle ? props.leadership_roles.includes(selectedJobTitle.name) : false;
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

const canProceedToStep2 = computed(() => isStep1Valid.value);
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

const nextStep = async () => {
    if (currentStep.value === 1 && canProceedToStep2.value) {
        // Hvis der allerede er en report, opdater step 1 data
        if (reportId.value) {
            const step1Form = useForm({
                job_title_id: form.job_title_id,
                area_of_responsibility_id: form.area_of_responsibility_id,
                experience: form.experience,
                gender: form.gender,
                region_id: form.region_id,
            });

            step1Form.patch(`/reports/${reportId.value}/step1`, {
                preserveScroll: false,
                onSuccess: () => {
                    // Inertia vil automatisk reload siden med den opdaterede report data
                },
                onError: (errors: any) => {
                    console.error('Fejl ved opdatering:', errors);
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
            gender: form.gender,
            region_id: form.region_id,
        });

        step1Form.post('/reports/payslip', {
            forceFormData: true,
            preserveScroll: false,
            onSuccess: () => {
                // Inertia vil automatisk reload siden med den nye URL og report data
                // currentStep vil blive sat automatisk baseret på report.step fra props
            },
            onError: (errors: any) => {
                console.error('Fejl ved gemning:', errors);
                console.log(errors.errors);
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

    finalForm.post(reports().store().url, {
        onSuccess: () => {
            router.visit(reports().index().url);
        },
    });
};

const getJobTitleName = (id: number | null) => {
    if (!id) return '';
    return props.job_titles.find(jt => jt.id === id)?.name || '';
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
        currentStep.value = initialStep.value;
        persistedDocument.value = newReport.document ?? null;
        selectedSkills.value = newReport.skill_ids ?? [];
        
        // Opdater form felter
        form.job_title_id = newReport.job_title_id ?? null;
        form.area_of_responsibility_id = newReport.area_of_responsibility_id ?? null;
        form.experience = newReport.experience ?? null;
        form.gender = newReport.gender ?? null;
        form.region_id = newReport.region_id ?? null;
        form.responsibility_level_id = newReport.responsibility_level_id ?? null;
        form.team_size = newReport.team_size ?? null;
        form.skill_ids = newReport.skill_ids ?? [];
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

// Computed options for comboboxes
const jobTitleOptions = computed(() => 
    props.job_titles.map(jt => ({ value: jt.id, label: jt.name }))
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
</script>

<template>
    <Head title="Opret Rapport" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Progress Indicator -->
            <div class="flex items-center justify-center gap-8">
                <button
                    type="button"
                    @click="navigateToStep(1)"
                    :disabled="!isStep1Completed"
                    :class="[
                        'flex items-center gap-3 transition-opacity',
                        isStep1Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                            currentStep >= 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        <Check v-if="currentStep > 1" class="h-5 w-5" />
                        <span v-else>1</span>
                    </div>
                    <span class="text-sm font-medium" :class="currentStep >= 1 ? 'text-foreground' : 'text-muted-foreground'">
                        Upload Lønseddel
                    </span>
                </button>
                <ChevronRight class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                <button
                    type="button"
                    @click="navigateToStep(2)"
                    :disabled="!isStep2Completed"
                    :class="[
                        'flex items-center gap-3 transition-opacity',
                        isStep2Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                            currentStep >= 2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        <Check v-if="currentStep > 2" class="h-5 w-5" />
                        <span v-else>2</span>
                    </div>
                    <span class="text-sm font-medium" :class="currentStep >= 2 ? 'text-foreground' : 'text-muted-foreground'">
                        Ansvar & Kompetencer
                    </span>
                </button>
                <ChevronRight class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                <button
                    type="button"
                    @click="navigateToStep(3)"
                    :disabled="!isStep3Completed"
                    :class="[
                        'flex items-center gap-3 transition-opacity',
                        isStep3Completed ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed opacity-50'
                    ]"
                >
                    <div 
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                            currentStep >= 3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        3
                    </div>
                    <span class="text-sm font-medium" :class="currentStep >= 3 ? 'text-foreground' : 'text-muted-foreground'">
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
                            Upload din anonymiserede lønseddel og indtast detaljer om din stilling
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
                                Upload lønseddel (påkrævet)
                            </Label>
                            <div v-if="uploadedFile" class="mt-2">
                                <div class="flex items-center gap-4 rounded-lg border p-4">
                                    <div v-if="uploadedFilePreview" class="h-20 w-20 overflow-hidden rounded">
                                        <img :src="uploadedFilePreview" alt="Preview" class="h-full w-full object-cover" />
                                    </div>
                                    <FileText v-else class="h-12 w-12 text-muted-foreground" />
                                    <div class="flex-1">
                                        <p class="font-medium">{{ uploadedFile.name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ formatFileSize(uploadedFile.size) }}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="removeFile"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
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
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    Vælg fil (PDF eller billede)
                                </Button>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Maksimal størrelse: 10MB. Filen skal være anonymiseret.
                                </p>
                            </div>
                        </div>

                        <Separator />

                        <!-- Job Title -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <Briefcase class="h-4 w-4" />
                                Jobtitel (påkrævet)
                            </Label>
                            <Combobox
                                v-model="form.job_title_id"
                                :options="jobTitleOptions"
                                placeholder="Vælg jobtitel"
                                search-placeholder="Søg efter jobtitel..."
                                empty-text="Ingen jobtitler fundet"
                            />
                        </div>

                        <!-- Area of Responsibility (conditional) -->
                        <div v-if="showAreaOfResponsibility">
                            <Label class="mb-2">Område (påkrævet)</Label>
                            <Combobox
                                v-model="form.area_of_responsibility_id"
                                :options="areaOfResponsibilityOptions"
                                placeholder="Vælg område"
                                search-placeholder="Søg efter område..."
                                empty-text="Ingen områder fundet"
                            />
                        </div>

                        <!-- Experience -->
                        <div>
                            <Label class="mb-2">Erfaring i år (påkrævet)</Label>
                            <Input
                                v-model.number="experienceInput"
                                type="number"
                                min="0"
                                max="50"
                                placeholder="fx. 5"
                            />
                        </div>

                        <!-- Gender -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <User class="h-4 w-4" />
                                Køn (valgfrit)
                            </Label>
                            <select
                                v-model="form.gender"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option :value="null">Vælg køn</option>
                                <option value="mand">Mand</option>
                                <option value="kvinde">Kvinde</option>
                                <option value="andet">Andet</option>
                            </select>
                        </div>

                        <!-- Region -->
                        <div>
                            <Label class="mb-2 flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                Region (påkrævet)
                            </Label>
                            <Combobox
                                v-model="form.region_id"
                                :options="regionOptions"
                                placeholder="Vælg region"
                                search-placeholder="Søg efter region..."
                                empty-text="Ingen regioner fundet"
                            />
                        </div>
                    </div>

                    <!-- Step 2: Ansvar og færdigheder -->
                    <div v-if="currentStep === 2" class="space-y-6">
                        <!-- Responsibility Level -->
                        <div>
                            <Label class="mb-2">
                                Hvilket ansvarsniveau beskriver bedst din rolle? (påkrævet)
                            </Label>
                            <Combobox
                                v-model="form.responsibility_level_id"
                                :options="responsibilityLevelOptions"
                                placeholder="Vælg ansvarsniveau"
                                search-placeholder="Søg efter ansvarsniveau..."
                                empty-text="Ingen ansvarsniveauer fundet"
                            />
                        </div>

                        <!-- Team Size -->
                        <div>
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
                        <div class="rounded-lg border bg-muted/50 p-4">
                            <p class="mb-4 text-sm font-medium">
                                Generer lønforhandlings rapport baseret på følgende:
                            </p>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Jobtitel:</span>
                                    <span class="font-medium">{{ getJobTitleName(form.job_title_id) }}</span>
                                </div>
                                <div v-if="form.area_of_responsibility_id" class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Område:</span>
                                    <span class="font-medium">{{ getAreaOfResponsibilityName(form.area_of_responsibility_id) }}</span>
                                </div>
                                <div class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Erfaring:</span>
                                    <span class="font-medium">{{ form.experience }} år</span>
                                </div>
                                <div v-if="form.gender" class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Køn:</span>
                                    <span class="font-medium">{{ form.gender }}</span>
                                </div>
                                <div class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Region:</span>
                                    <span class="font-medium">{{ getRegionName(form.region_id) }}</span>
                                </div>
                                <div class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Ansvarsniveau:</span>
                                    <span class="font-medium">{{ getResponsibilityLevelName(form.responsibility_level_id) }}</span>
                                </div>
                                <div v-if="form.team_size" class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Team størrelse:</span>
                                    <span class="font-medium">{{ form.team_size }} personer</span>
                                </div>
                                <div class="flex items-start justify-between">
                                    <span class="text-muted-foreground">Færdigheder:</span>
                                    <span class="font-medium text-right max-w-xs">{{ getSkillNames(form.skill_ids) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between pt-4 border-t">
                        <Button
                            v-if="currentStep > 1"
                            type="button"
                            variant="outline"
                            @click="previousStep"
                        >
                            <ChevronLeft class="mr-2 h-4 w-4" />
                            Tilbage
                        </Button>
                        <div v-else></div>
                        
                        <div class="flex gap-2">
                            <Button
                                v-if="currentStep < 3"
                                type="button"
                                :disabled="currentStep === 1 && !canProceedToStep2 || currentStep === 2 && !canProceedToStep3"
                                @click="nextStep"
                            >
                                Næste
                                <ChevronRight class="ml-2 h-4 w-4" />
                            </Button>
                            <Button
                                v-else
                                type="button"
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
    </AppLayout>
</template>

