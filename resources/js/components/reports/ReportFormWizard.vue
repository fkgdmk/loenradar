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
import { Dialog, DialogContent, DialogFooter } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import PayslipAnonymizer from '@/components/PayslipAnonymizer.vue';
import { login } from '@/routes';
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
    Pencil,
    LogIn,
    Mail
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

// Local state for contact email
const contactEmail = ref('');

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

// Helper to trigger edit anonymizer
const triggerEditAnonymizer = () => {
    rf.value.fileToAnonymize.value = rf.value.uploadedFile.value;
    rf.value.showAnonymizer.value = true;
};

const handleSubmit = () => {
    emit('submit');
};
</script>

<template>
    <div class="flex flex-col gap-6 max-w-3xl mx-auto">
        <!-- Progress Indicator -->
        <div class="flex items-center justify-center gap-2 sm:gap-8">
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
                    Upload Lønseddel
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
                    Gennemgå & Generer
                </span>
            </button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>
                    <span v-if="rf.currentStep.value === 1">Trin 1: Upload lønseddel og detaljer</span>
                    <span v-else-if="rf.currentStep.value === 2">Trin 2: Ansvar og færdigheder</span>
                    <span v-else>Trin 3: Opsummering</span>
                </CardTitle>
                <CardDescription>
                    <span v-if="rf.currentStep.value === 1">
                        Upload din lønseddel og anonymiser den ved at strege sensitive data over.
                    </span>
                    <span v-else-if="rf.currentStep.value === 2">
                        Beskriv dit ansvarsniveau og vælg dine færdigheder
                    </span>
                    <span v-else>
                        Gennemgå dine valg og {{ showAuthPrompt ? 'opret en konto for at se din rapport' : 'generer din rapport' }}
                    </span>
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Step 1: Upload og detaljer -->
                <div v-if="rf.currentStep.value === 1" class="space-y-6">
                    <!-- File Upload -->
                    <div>
                        <Label v-if="!rf.showAnonymizer.value" class="mb-2 flex items-center gap-2">
                            <Upload class="h-4 w-4" />
                            Upload lønseddel
                        </Label>
                        <div v-if="rf.uploadedFile.value" class="mt-2">
                            <div v-if="!rf.showAnonymizer.value" class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 rounded-lg border p-4">
                                <div class="flex items-center gap-4 flex-1 min-w-0">
                                    <div v-if="rf.uploadedFilePreview.value" class="h-16 w-16 sm:h-20 sm:w-20 overflow-hidden rounded flex-shrink-0">
                                        <img :src="rf.uploadedFilePreview.value" alt="Preview" class="h-full w-full object-cover" />
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
                                    @save="rf.handleAnonymizedImage"
                                    @cancel="rf.cancelAnonymizer"
                                />
                            </div>
                        </div>
                        <div v-else-if="rf.persistedDocument.value" class="mt-2">
                            <div class="flex items-center gap-4 rounded-lg border p-4">
                                <div v-if="rf.persistedDocument.value.preview_url" class="h-20 w-20 overflow-hidden rounded">
                                    <img :src="rf.persistedDocument.value.preview_url" alt="Preview" class="h-full w-full object-cover" />
                                </div>
                                <FileText v-else class="h-12 w-12 text-muted-foreground" />
                                <div class="flex-1">
                                    <p class="font-medium">{{ rf.persistedDocument.value.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ rf.formatFileSize(rf.persistedDocument.value.size) }}
                                    </p>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Din anonymiserede lønseddel er gemt og vil blive brugt i rapporten.
                            </p>
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
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    @click="handleFileInputClick"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    Vælg fil (PDF eller billede)
                                </Button>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Du kan anonymisere direkte i browseren.
                                </p>
                                <InputError :message="rf.step1Errors.document" />
                            </div>
                            
                            <div v-if="rf.showAnonymizer.value && rf.fileToAnonymize.value" class="mt-4">
                                <PayslipAnonymizer
                                    :file="rf.fileToAnonymize.value"
                                    @save="rf.handleAnonymizedImage"
                                    @cancel="rf.cancelAnonymizer"
                                />
                            </div>
                        </div>
                    </div>

                    <Separator v-if="rf.isDocumentUploaded.value" />

                    <!-- Job Title -->
                    <div v-if="rf.isDocumentUploaded.value">
                        <CardDescription class="mb-4">
                        Udfyld detaljer om din stilling
                        </CardDescription>

                        <Label class="mb-2 flex items-center gap-2">
                            <Briefcase class="h-4 w-4" />
                            Jobtitel
                        </Label>
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
                    <div v-if="rf.isDocumentUploaded.value && rf.showAreaOfResponsibility.value">
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
                    <div v-if="rf.isDocumentUploaded.value">
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
                    <div v-if="rf.isDocumentUploaded.value">
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
                        <Label class="mb-2">
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
                            Oplist de vigtigste teknologier, systemer eller metoder, du bruger i dit job. (valgfrit)
                        </Label>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Vælg op til 5 færdigheder
                        </p>
                        <Input
                            v-model="rf.skillSearch.value"
                            type="text"
                            placeholder="Søg efter færdigheder..."
                            class="mb-4"
                            :disabled="!rf.form.job_title_id"
                        />
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
                            Valgt: {{ rf.selectedSkills.value.length }} / 5
                        </p>
                    </div>
                </div>

                <!-- Step 3: Opsummering -->
                <div v-if="rf.currentStep.value === 3" class="space-y-6">
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
                    <div v-if="showAuthPrompt" class="rounded-lg border border-primary/20 bg-primary/5 p-4">
                        <p class="text-center font-bold">
                            <Sparkles class="inline h-4 w-4 mr-1 text-primary" />
                            Opret en gratis konto eller log ind for at se din personlige lønrapport
                        </p>
                    </div>
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
                            :disabled="(rf.currentStep.value === 2 && !rf.canProceedToStep3.value) || !rf.isDocumentUploaded.value"
                            @click="rf.nextStep"
                        >
                            Næste
                            <ChevronRight class="ml-2 h-4 w-4" />
                        </Button>
                        <Button
                            v-else
                            type="button"
                            class="w-full sm:w-auto"
                            :disabled="rf.form.processing"
                            @click="handleSubmit"
                        >
                            <Sparkles class="mr-2 h-4 w-4" />
                            {{ submitButtonText }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Payslip Warning Modal -->
    <Dialog :open="rf.showPayslipWarningModal.value" @update:open="rf.showPayslipWarningModal.value = $event">
        <DialogContent class="sm:max-w-xl">
            <div class="whitespace-pre-line text-base mt-4 mx-4 space-y-2">
                <div class="font-bold">
                    {{ rf.payslipWarning.value }}
                </div>
                <div>
                    Det er desværre ikke nok til at kunne lave en værdifuld rapport.
                </div>
                <div>
                    Vi arbejder på at samle mere data, så vi kan give dig et mere præcist billede af dit lønniveau.
                </div>
                
                <!-- For ikke-autentificerede brugere: vis email input og login link -->
                <template v-if="!isAuthenticated">
                    <div class="pt-2 space-y-4">
                        <div class="space-y-2">
                            <Label for="contact-email" class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                Indtast din email, så kontakter vi dig når vi er i mål
                            </Label>
                            <Input
                                id="contact-email"
                                v-model="contactEmail"
                                type="email"
                                placeholder="din@email.dk"
                            />
                        </div>
                        
                        <div class="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>eller</span>
                            <Link 
                                :href="login()" 
                                class="inline-flex items-center gap-1 text-primary hover:underline font-medium"
                            >
                                <LogIn class="h-4 w-4" />
                                Log ind / Opret ny bruger
                            </Link>
                        </div>
                    </div>
                </template>
                
                <!-- For autentificerede brugere: vis standard besked -->
                <template v-else>
                    <div>
                        Vi kontakter dig lige så snart vi er i mål for din profil!
                    </div>
                </template>
            </div>
            <DialogFooter>
                <Button @click="rf.closePayslipWarningModal(contactEmail)">
                    Forstået
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
