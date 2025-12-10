<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Combobox } from '@/components/ui/combobox';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import ToastAlertError from '@/components/ToastAlertError.vue';
import InputError from '@/components/InputError.vue';
import PayslipAnonymizer from '@/components/PayslipAnonymizer.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { 
    FileText, 
    MapPin, 
    ChevronRight, 
    ChevronLeft,
    Check,
    X,
    Sparkles,
    Pencil,
    LogIn,
    Image,
    Rocket,
} from 'lucide-vue-next';
import type { useReportForm } from '@/composables/useReportForm';

interface Props {
    reportForm: ReturnType<typeof useReportForm>;
    showAuthPrompt?: boolean;
    isAuthenticated?: boolean;
    submitButtonText?: string;
    hideDocumentUploadAfterStep1?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showAuthPrompt: false,
    isAuthenticated: false,
    submitButtonText: 'Generer Rapport',
    hideDocumentUploadAfterStep1: false,
});

// Drag and drop state
const isDragging = ref(false);

const emit = defineEmits<{
    submit: [];
}>();

// Local file input ref
const localFileInputRef = ref<HTMLInputElement | null>(null);

// Access reportForm - it's already reactive, just need to access .value for refs
const rf = computed(() => props.reportForm);

// Helper to handle file input click
const handleFileInputClick = () => {
    localFileInputRef.value?.click();
};

// Drag and drop handlers
const handleDragEnter = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = true;
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = false;
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = false;
    
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        const file = files[0];
        // Check if file type is acceptable
        const acceptedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (acceptedTypes.includes(file.type)) {
            // Create a synthetic event-like object for handleFileChange
            const syntheticEvent = {
                target: {
                    files: files
                }
            } as unknown as Event;
            rf.value.handleFileChange(syntheticEvent);
        }
    }
};

// Helper to trigger edit anonymizer
const triggerEditAnonymizer = () => {
    rf.value.fileToAnonymize.value = rf.value.uploadedFile.value;
    rf.value.showAnonymizer.value = true;
};

const isInsufficientData = computed(() => rf.value.payslipMatch.value === 'insufficient_data');

const payslipCountDisplay = computed(() => {
    const count = rf.value.matchMetadata.value?.payslip_count ?? 0;
    if (count < 1) return 2;
    if (count <= 2) return 3;
    return count; // 4 or more shows actual count
});

const payslipsNeeded = computed(() => 5 - payslipCountDisplay.value);
const progressPercentage = computed(() => (payslipCountDisplay.value / 5) * 100);

const effectiveSubmitButtonText = computed(() => {
    if (!props.isAuthenticated) {
        return 'Log ind & Upload';
    }

    if (isInsufficientData.value) {
        return 'Upload';
    }

    return props.submitButtonText;
});

const handleSubmit = () => {
    emit('submit');
};
</script>

<template>
    <div class="flex flex-col gap-6 max-w-3xl mx-auto">
        <!-- Progress Indicator -->
        <div class="flex items-center justify-center gap-2 sm:gap-8 mb-4">
            <button
                type="button"
                @click="rf.navigateToStep(1)"
                :disabled="!rf.isStep1Completed.value"
                :class="[
                    'flex items-center gap-2 sm:gap-3 transition-opacity',
                    rf.isStep1Completed.value ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed'
                ]"
            >
                <div 
                    :class="[
                        'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                        rf.currentStep.value >= 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                    ]"
                >
                    <Check v-if="rf.currentStep.value > 1" class="h-4 w-4 sm:h-5 sm:w-5" />
                    <span v-else>1</span>
                </div>
                <span class="hidden sm:inline text-sm font-medium" :class="rf.currentStep.value >= 1 ? 'text-foreground' : 'text-muted-foreground'">
                    Jobdetaljer
                </span>
            </button>
            <ChevronRight class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground flex-shrink-0" />
            <button
                type="button"
                @click="rf.navigateToStep(2)"
                :disabled="!rf.isStep2Completed.value"
                :class="[
                    'flex items-center gap-2 sm:gap-3 transition-opacity',
                    rf.isStep2Completed.value ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed'
                ]"
            >
                <div 
                    :class="[
                        'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                        rf.currentStep.value >= 2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                    ]"
                >
                    <Check v-if="rf.currentStep.value > 2" class="h-4 w-4 sm:h-5 sm:w-5" />
                    <span v-else>2</span>
                </div>
                <span class="hidden sm:inline text-sm font-medium" :class="rf.currentStep.value >= 2 ? 'text-foreground' : 'text-muted-foreground'">
                    Kompetencer
                </span>
            </button>
            <ChevronRight class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground flex-shrink-0" />
            <button
                type="button"
                @click="rf.navigateToStep(3)"
                :disabled="!rf.isStep3Completed.value"
                :class="[
                    'flex items-center gap-2 sm:gap-3 transition-opacity',
                    rf.isStep3Completed.value ? 'cursor-pointer hover:opacity-80' : 'cursor-not-allowed'
                ]"
            >
                <div 
                    :class="[
                        'flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full text-xs sm:text-sm font-semibold transition-colors',
                        rf.currentStep.value >= 3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                    ]"
                >
                    3
                </div>
                <span class="hidden sm:inline text-sm font-medium" :class="rf.currentStep.value >= 3 ? 'text-foreground' : 'text-muted-foreground'">
                    Upload & Generer
                </span>
            </button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>
                    <span v-if="rf.currentStep.value === 1">Trin 1: Jobdetaljer</span>
                    <span v-else-if="rf.currentStep.value === 2">Trin 2: Kompetencer</span>
                    <span v-else>Trin 3: Upload og generer</span>
                </CardTitle>
                <CardDescription>
                    <span v-if="rf.currentStep.value === 1">
                        Fortæl os om din stilling, erfaring og arbejdsområde
                    </span>
                    <span v-else-if="rf.currentStep.value === 2">
                        Beskriv dit ansvarsniveau og vælg dine færdigheder
                    </span>
                    <div v-else-if="!rf.isDocumentUploaded.value" class="space-y-2">
                        <div>Upload lønseddel der er maks. 1 år gammel.</div>
                        <div>Anonymiser lønseddelen ved at strege sensitive data over direkte i browseren.</div>
                        <div>Vi gemmer kun den anonymiserede version.</div>
                    </div>
                    <span v-else>
                        Gennemgå dine valg og {{ showAuthPrompt ? 'opret en konto for at se din rapport' : 'generer din rapport' }}
                    </span>
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Step 1: Jobdetaljer -->
                <div v-if="rf.currentStep.value === 1" class="space-y-6">
                    <!-- Job Title -->
                    <div>
                        <Label class="mb-2">Jobtitel</Label>
                        <Combobox
                            v-model="rf.form.job_title_id"
                            :options="rf.jobTitleOptions.value"
                            placeholder="Vælg jobtitel"
                            search-placeholder="Søg efter jobtitel..."
                            empty-text="Ingen jobtitler fundet"
                        />
                        <InputError :message="rf.step1Errors.job_title_id" />
                    </div>

                    <!-- Area of Responsibility (conditional) -->
                    <div v-if="rf.showAreaOfResponsibility.value">
                        <Label class="mb-2">Område</Label>
                        <Combobox
                            v-model="rf.form.area_of_responsibility_id"
                            :options="rf.areaOfResponsibilityOptions.value"
                            placeholder="Vælg område"
                            search-placeholder="Søg efter område..."
                            empty-text="Ingen områder fundet"
                        />
                        <InputError :message="rf.step1Errors.area_of_responsibility_id" />
                    </div>

                    <!-- Experience -->
                    <div>
                        <Label class="mb-2">Erfaring i år</Label>
                        <Input
                            v-model.number="rf.experienceInput.value"
                            type="number"
                            min="0"
                            max="50"
                            placeholder="fx. 5"
                        />
                        <InputError :message="rf.step1Errors.experience" />
                    </div>

                    <!-- Region -->
                    <div>
                        <Label class="mb-2 flex items-center gap-2">
                            <MapPin class="h-4 w-4" />
                            Region
                        </Label>
                        <Combobox
                            v-model="rf.form.region_id"
                            :options="rf.regionOptions.value"
                            placeholder="Vælg region"
                            search-placeholder="Søg efter region..."
                            empty-text="Ingen regioner fundet"
                        />
                        <InputError :message="rf.step1Errors.region_id" />
                    </div>
                </div>

                <!-- Step 2: Ansvar og færdigheder -->
                <div v-if="rf.currentStep.value === 2" class="space-y-6">
                    <div v-if="isInsufficientData" class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary/5 via-primary/5 to-primary/10 border border-primary/10 p-5 mb-8">
                        
                        <div class="relative space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary flex items-center justify-center shadow-lg shadow-primary/25">
                                    <Rocket class="h-5 w-5 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-foreground text-base">Vi er tæt på at have nok data for din profil!</h3>
                                    <p class="text-sm text-muted-foreground mt-0.5">Hjælp os i mål, ved at uploade din anonymiserede lønseddel i sidste trin.</p>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-muted-foreground">Lønsedler indsamlet</span>
                                    <span class="font-semibold text-foreground">{{ payslipCountDisplay }} af 5</span>
                                </div>
                                <div class="h-2.5 bg-primary/10 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-primary rounded-full transition-all duration-500"
                                        :style="{ width: `${progressPercentage}%` }"
                                    ></div>
                                </div>
                                <p class="text-xs text-muted-foreground">Vi mangler kun {{ payslipsNeeded }} mere for at kunne bygge din rapport</p>
                            </div>
                            
                            <p class="text-sm text-foreground leading-relaxed font-semibold">
                                Vi sender dig rapporten automatisk, så snart vi har 5 lønsedler.
                            </p>
                        </div>
                    </div>

                    <!-- Responsibility Level -->
                    <div>
                        <Label class="mb-2">
                            Hvilket ansvarsniveau beskriver bedst din rolle?
                        </Label>
                        <Combobox
                            v-model="rf.form.responsibility_level_id"
                            :options="rf.responsibilityLevelOptions.value"
                            placeholder="Vælg ansvarsniveau"
                            search-placeholder="Søg efter ansvarsniveau..."
                            empty-text="Ingen ansvarsniveauer fundet"
                        />
                    </div>

                    <!-- Team Size (kun for ledere) -->
                    <div v-if="rf.showTeamSize.value">
                        <Label class="mb-4">
                            Hvor mange personer er du faglig eller personalemæssig leder for?
                        </Label>
                        <Input
                            v-model.number="rf.teamSizeInput.value"
                            type="number"
                            min="0"
                            max="1000"
                            placeholder="fx. 5"
                        />
                    </div>

                    <!-- Skills -->
                    <div>
                        <Label class="mb-2">
                            Vælg de vigtigste teknologier, systemer eller metoder, du bruger i dit job.
                        </Label>
                        <Input
                            v-model="rf.skillSearch.value"
                            type="text"
                            placeholder="Søg efter færdigheder..."
                            class="mb-1"
                            :disabled="!rf.form.job_title_id"
                        />
                        <p class="mb-4 text-xs text-muted-foreground">
                            Vælg op til 10 færdigheder
                        </p>
                        <div
                            v-if="!rf.form.job_title_id"
                            class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            Vælg først en jobtitel for at se relevante færdigheder.
                        </div>
                        <div
                            v-else-if="rf.filteredSkills.value.length === 0"
                            class="rounded-md border border-dashed p-4 text-sm text-muted-foreground"
                        >
                            Ingen færdigheder fundet for den valgte jobtitel.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <Badge
                                v-for="skill in rf.filteredSkills.value"
                                :key="skill.id"
                                :variant="rf.selectedSkills.value.includes(skill.id) ? 'default' : 'outline'"
                                class="cursor-pointer"
                                @click="rf.toggleSkill(skill.id)"
                            >
                                {{ skill.name }}
                                <Check v-if="rf.selectedSkills.value.includes(skill.id)" class="ml-1 h-3 w-3" />
                            </Badge>
                        </div>
                        <p v-if="rf.selectedSkills.value.length > 0" class="mt-2 text-sm text-muted-foreground">
                            Valgt: {{ rf.selectedSkills.value.length }} / 10
                        </p>
                    </div>
                </div>

                <!-- Step 3: Upload og Generer -->
                <div v-if="rf.currentStep.value === 3" class="space-y-6">
                    <div v-if="isInsufficientData" class="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary/5 via-primary/5 to-primary/10 border border-primary/10 p-5">
                        
                        <div class="relative">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary flex items-center justify-center shadow-lg shadow-primary/25">
                                    <Rocket class="h-5 w-5 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-foreground text-base">Vi er tæt på at have nok data for din profil!</h3>
                                    <p class="text-sm text-muted-foreground mt-0.5">Hjælp os i mål, ved at uploade din anonymiserede lønseddel her.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Alert -->
                    <ToastAlertError v-if="rf.errorMessages.value.length > 0" :errors="rf.errorMessages.value" title="Fejl ved upload af lønseddel" />

                    <!-- File Upload Section -->
                    <div>
                        <div v-if="rf.uploadedFile.value" class="mt-2">
                            <div v-if="!rf.showAnonymizer.value" class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 rounded-lg border p-4">
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <div v-if="rf.uploadedFilePreview.value" class="h-16 w-16 sm:h-20 sm:w-20 overflow-hidden rounded flex-shrink-0 relative">
                                        <img :src="rf.uploadedFilePreview.value" alt="Preview" class="h-full w-full object-cover" />
                                        <!-- Analysis loader overlay -->
                                        <div v-if="rf.isAnalyzing.value" class="absolute inset-0 bg-background/80 backdrop-blur-sm flex flex-col items-center justify-center gap-2 rounded">
                                            <Spinner class="size-6 text-primary" />
                                            <span class="text-sm font-medium text-foreground">Analyserer lønseddel</span>
                                        </div>
                                    </div>
                                    <FileText v-else class="h-10 w-10 sm:h-12 sm:w-12 text-muted-foreground flex-shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate">{{ rf.uploadedFile.value.name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ rf.formatFileSize(rf.uploadedFile.value.size) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 sm:gap-1 self-end sm:self-center">
                                    <Button
                                        v-if="rf.uploadedFile.value.type.startsWith('image/')"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="triggerEditAnonymizer"
                                        title="Rediger anonymisering"
                                    >
                                        <Pencil class="h-4 w-4 sm:mr-0 mr-1" />
                                        <span class="sm:hidden">Rediger</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="rf.removeFile"
                                        title="Fjern fil"
                                    >
                                        <X class="h-4 w-4 sm:mr-0 mr-1" />
                                        <span class="sm:hidden">Fjern</span>
                                    </Button>
                                </div>
                            </div>
                            
                            <div v-if="rf.showAnonymizer.value && rf.fileToAnonymize.value" class="mt-4">
                                <PayslipAnonymizer
                                    :file="rf.fileToAnonymize.value"
                                    :is-analyzing="rf.isAnalyzing.value"
                                    @save="rf.handleAnonymizedImage"
                                    @cancel="rf.cancelAnonymizer"
                                />
                            </div>
                        </div>
                        <div v-else-if="rf.persistedDocument.value" class="mt-2">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 rounded-lg border p-4">
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <div v-if="rf.persistedDocument.value.preview_url" class="h-16 w-16 sm:h-20 sm:w-20 overflow-hidden rounded flex-shrink-0 relative">
                                        <img :src="rf.persistedDocument.value.preview_url" alt="Preview" class="h-full w-full object-cover" />
                                        <!-- Analysis loader overlay -->
                                        <div v-if="rf.isAnalyzing.value" class="absolute inset-0 bg-background/80 backdrop-blur-sm flex flex-col items-center justify-center gap-2 rounded">
                                            <Spinner class="size-6 text-primary" />
                                            <span class="text-sm font-medium text-foreground">Analyserer lønseddel</span>
                                        </div>
                                    </div>
                                    <FileText v-else class="h-10 w-10 sm:h-12 sm:w-12 text-muted-foreground flex-shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate">{{ rf.persistedDocument.value.name }}</p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ rf.formatFileSize(rf.persistedDocument.value.size) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 sm:gap-1 self-end sm:self-center">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="rf.removeFile"
                                        title="Fjern fil"
                                    >
                                        <X class="h-4 w-4 sm:mr-0 mr-1" />
                                        <span class="sm:hidden">Fjern</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-2">
                            <input
                                ref="localFileInputRef"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="hidden"
                                @change="rf.handleFileChange"
                            />
                            <div v-if="!rf.showAnonymizer.value">
                                <!-- Drag and Drop Zone -->
                                <div
                                    @click="handleFileInputClick"
                                    @dragenter="handleDragEnter"
                                    @dragleave="handleDragLeave"
                                    @dragover="handleDragOver"
                                    @drop="handleDrop"
                                    :class="[
                                        'relative flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed p-8 transition-all cursor-pointer',
                                        isDragging 
                                            ? 'border-primary bg-primary/10 scale-[1.02]' 
                                            : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
                                    ]"
                                >
                                    <div 
                                        :class="[
                                            'flex h-14 w-14 items-center justify-center rounded-full transition-colors',
                                            isDragging ? 'bg-primary/20' : 'bg-muted'
                                        ]"
                                    >
                                        <Image 
                                            :class="[
                                                'h-7 w-7 transition-colors',
                                                isDragging ? 'text-primary' : 'text-muted-foreground'
                                            ]" 
                                        />
                                    </div>
                                    <div class="text-center">
                                        <p :class="['font-medium', isDragging ? 'text-primary' : 'text-foreground']">
                                            {{ isDragging ? 'Slip filen her' : 'Træk og slip din lønseddel her' }}
                                        </p>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            eller <span class="text-primary font-medium">klik for at vælge</span>
                                        </p>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        PDF eller billede (JPG, PNG, WebP)
                                    </p>
                                </div>
                                <p class="mt-3 text-xs text-muted-foreground text-center">
                                    Du kan anonymisere direkte i browseren efter upload.
                                </p>
                                <InputError :message="rf.step3Errors.document" />
                            </div>
                            
                            <div v-if="rf.showAnonymizer.value && rf.fileToAnonymize.value" class="mt-4">
                                <PayslipAnonymizer
                                    :file="rf.fileToAnonymize.value"
                                    :is-analyzing="rf.isAnalyzing.value"
                                    @save="rf.handleAnonymizedImage"
                                    @cancel="rf.cancelAnonymizer"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Summary Section (only shown when document is uploaded) -->
                    <template v-if="rf.isDocumentUploaded.value && !rf.showAnonymizer.value">
                        <Separator />
                        
                        <div class="rounded-lg border bg-muted/50 p-3 sm:p-4">
                            <p class="mb-4 text-sm font-medium">
                                Generer lønforhandlings rapport baseret på følgende:
                            </p>
                            <div class="space-y-2 sm:space-y-3 text-sm">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Jobtitel:</span>
                                    <span class="font-medium sm:text-right">{{ rf.getJobTitleName(rf.form.job_title_id) }}</span>
                                </div>
                                <div v-if="rf.form.area_of_responsibility_id" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Område:</span>
                                    <span class="font-medium sm:text-right">{{ rf.getAreaOfResponsibilityName(rf.form.area_of_responsibility_id) }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Erfaring:</span>
                                    <span class="font-medium sm:text-right">{{ rf.form.experience }} år</span>
                                </div>
                                <div v-if="rf.form.gender" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Køn:</span>
                                    <span class="font-medium sm:text-right">{{ rf.form.gender }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Region:</span>
                                    <span class="font-medium sm:text-right">{{ rf.getRegionName(rf.form.region_id) }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Ansvarsniveau:</span>
                                    <span class="font-medium sm:text-right">{{ rf.getResponsibilityLevelName(rf.form.responsibility_level_id) }}</span>
                                </div>
                                <div v-if="rf.form.team_size" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Team størrelse:</span>
                                    <span class="font-medium sm:text-right">{{ rf.form.team_size }} personer</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-0.5 sm:gap-2">
                                    <span class="text-muted-foreground">Færdigheder:</span>
                                    <span class="font-medium sm:text-right">{{ rf.getSkillNames(rf.form.skill_ids) || 'Ingen valgt' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Auth info for guests -->
                        <div v-if="showAuthPrompt && !isInsufficientData" class="rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <p class="text-center font-bold">
                                <Sparkles class="inline h-4 w-4 mr-1 text-primary" />
                                Opret en gratis konto eller log ind for at se din personlige rapport
                            </p>
                        </div>
                    </template>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-2 pt-4 border-t">
                    <Button
                        v-if="rf.currentStep.value > 1"
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="rf.previousStep"
                    >
                        <ChevronLeft class="mr-2 h-4 w-4" />
                        Tilbage
                    </Button>
                    <div v-else class="hidden sm:block"></div>
                    
                    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                        <Button
                            v-if="rf.currentStep.value < 3"
                            type="button"
                            class="w-full sm:w-auto"
                            :disabled="(rf.currentStep.value === 1 && !rf.isStep1Valid.value) || (rf.currentStep.value === 2 && !rf.canProceedToStep3.value)"
                            @click="rf.nextStep"
                        >
                            Næste
                            <ChevronRight class="ml-2 h-4 w-4" />
                        </Button>
                        <Button
                            v-else
                            type="button"
                            class="w-full sm:w-auto"
                            :disabled="rf.form.processing || !rf.isDocumentUploaded.value || rf.showAnonymizer.value"
                            @click="handleSubmit"
                        >
                            <Sparkles class="mr-2 h-4 w-4" />
                            {{ effectiveSubmitButtonText }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Payslip Warning Modal -->
    <Dialog :open="rf.showPayslipWarningModal.value" @update:open="rf.showPayslipWarningModal.value = $event">
        <DialogContent class="sm:max-w-xl">
            <div class="flex flex-col items-center text-center space-y-6 pt-6">
                <!-- Icon and Title -->
                <div class="rounded-full bg-primary/10 p-3">
                    <Sparkles class="h-8 w-8 text-primary" />
                </div>
                
                <div class="space-y-2">
                    <h2 class="text-xl font-semibold tracking-tight">
                        Tak for dit bidrag!
                    </h2>
                    <p class="text-muted-foreground max-w-sm mx-auto">
                        Opret en gratis konto for at få besked, når vi har indsamlet nok data til din rapport.
                    </p>
                </div>

                <!-- Action Button -->
                <div class="w-full max-w-sm space-y-3">
                    <Link 
                        :href="login()" 
                        class="inline-flex w-full items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2"
                    >
                        <LogIn class="mr-2 h-4 w-4" />
                        Log ind / Opret ny bruger
                    </Link>
                    
                    <Button 
                        variant="ghost" 
                        class="w-full"
                        @click="rf.closePayslipWarningModal()"
                    >
                        Nej tak, jeg vil ikke gemme mit bidrag
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
