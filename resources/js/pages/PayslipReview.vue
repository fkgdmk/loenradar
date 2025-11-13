<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import PayslipReviewController from '@/actions/App/Http/Controllers/PayslipReviewController';
import { dashboard } from '@/routes';
import { ref } from 'vue';
import { MessageSquare, Maximize2, ExternalLink, Pencil, Check, X } from 'lucide-vue-next';

interface PayslipData {
    id: number;
    title: string | null;
    description: string | null;
    comments: string[] | null;
    salary: number;
    sub_job_title: string | null;
    experience: string | null;
    job_title_id: number | null;
    job_title: string | null;
    area_of_responsibility_id: number | null;
    area_of_responsibility: string | null;
    region_id: number | null;
    region: string | null;
    media_url: string;
    url: string | null;
}

interface JobTitle {
    id: number;
    name: string;
}

interface Region {
    id: number;
    name: string;
}

interface AreaOfResponsibility {
    id: number;
    name: string;
}

interface Props {
    payslip: PayslipData | null;
    pending_count: number;
    job_titles: JobTitle[];
    regions: Region[];
    areas_of_responsibility: AreaOfResponsibility[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Payslip Review',
        href: PayslipReviewController.index().url,
    },
];

const commentsDialogOpen = ref(false);
const imageDialogOpen = ref(false);
const descriptionDialogOpen = ref(false);
const isEditingExperience = ref(false);
const isEditingSalary = ref(false);
const isEditingJobTitle = ref(false);
const isEditingRegion = ref(false);
const isEditingAreaOfResponsibility = ref(false);
const experienceInput = ref('');
const salaryInput = ref('');
const jobTitleInput = ref<number | null>(null);
const regionInput = ref<number | null>(null);
const areaOfResponsibilityInput = ref<number | null>(null);

const startEditingExperience = () => {
    experienceInput.value = props.payslip?.experience || '';
    isEditingExperience.value = true;
};

const cancelEditingExperience = () => {
    isEditingExperience.value = false;
    experienceInput.value = '';
};

const saveExperience = () => {
    if (!props.payslip) return;
    
    router.patch(`/payslips/${props.payslip.id}/experience`, {
        experience: experienceInput.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            isEditingExperience.value = false;
        },
    });
};

const startEditingSalary = () => {
    salaryInput.value = props.payslip?.salary.toString() || '';
    isEditingSalary.value = true;
};

const cancelEditingSalary = () => {
    isEditingSalary.value = false;
    salaryInput.value = '';
};

const saveSalary = () => {
    if (!props.payslip) return;
    
    router.patch(`/payslips/${props.payslip.id}/salary`, {
        salary: parseFloat(salaryInput.value),
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            isEditingSalary.value = false;
        },
    });
};

const startEditingJobTitle = () => {
    jobTitleInput.value = props.payslip?.job_title_id || null;
    isEditingJobTitle.value = true;
};

const cancelEditingJobTitle = () => {
    isEditingJobTitle.value = false;
    jobTitleInput.value = null;
};

const saveJobTitle = () => {
    if (!props.payslip || !jobTitleInput.value) return;
    
    router.patch(`/payslips/${props.payslip.id}/job-title`, {
        job_title_id: jobTitleInput.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            isEditingJobTitle.value = false;
        },
    });
};

const startEditingRegion = () => {
    regionInput.value = props.payslip?.region_id || null;
    isEditingRegion.value = true;
};

const cancelEditingRegion = () => {
    isEditingRegion.value = false;
    regionInput.value = null;
};

const saveRegion = () => {
    if (!props.payslip || !regionInput.value) return;
    
    router.patch(`/payslips/${props.payslip.id}/region`, {
        region_id: regionInput.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            isEditingRegion.value = false;
        },
    });
};

const startEditingAreaOfResponsibility = () => {
    areaOfResponsibilityInput.value = props.payslip?.area_of_responsibility_id || null;
    isEditingAreaOfResponsibility.value = true;
};

const cancelEditingAreaOfResponsibility = () => {
    isEditingAreaOfResponsibility.value = false;
    areaOfResponsibilityInput.value = null;
};

const saveAreaOfResponsibility = () => {
    if (!props.payslip || !areaOfResponsibilityInput.value) return;
    
    router.patch(`/payslips/${props.payslip.id}/area-of-responsibility`, {
        area_of_responsibility_id: areaOfResponsibilityInput.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => {
            isEditingAreaOfResponsibility.value = false;
        },
    });
};

const handleApprove = () => {
    if (!props.payslip) return;
    
    router.post(PayslipReviewController.approve(props.payslip.id).url, {}, {
        preserveScroll: true,
    });
};

const handleDeny = () => {
    if (!props.payslip) return;
    
    router.post(PayslipReviewController.deny(props.payslip.id).url, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Payslip Review" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gennemgå Lønsedler</h1>
                <Badge variant="secondary">
                    {{ pending_count }} mangler at blive håndteret
                </Badge>
            </div>

            <div v-if="!payslip" class="flex h-full items-center justify-center">
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Ingen lønsedler at gennemgå</CardTitle>
                        <CardDescription>
                            Der er ingen lønsedler der venter på godkendelse.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>

            <div v-else class="grid gap-4" :class="{ 'lg:grid-cols-3': payslip.media_url }">
                <!-- Billede - 2/3 af bredden -->
                <Card class="lg:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle>Dokument</CardTitle>
                        <div class="flex gap-2">
                            <Button 
                                v-if="payslip.url" 
                                variant="outline" 
                                size="sm"
                                as="a"
                                :href="payslip.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink class="h-4 w-4 mr-2" />
                                Besøg
                            </Button>
                            <Dialog v-model:open="imageDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        <Maximize2 class="h-4 w-4 mr-2" />
                                        Forstør
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="w-7xl">
                                    <DialogHeader>
                                        <DialogTitle>{{ payslip.title || 'Payslip' }}</DialogTitle>
                                    </DialogHeader>
                                    <div class="flex items-center justify-center overflow-auto h-full">
                                        <img 
                                            :src="payslip.media_url" 
                                            :alt="payslip.title || 'Payslip'" 
                                            class="max-w-full max-h-full object-contain"
                                        />
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent v-if="payslip.media_url">
                        <img 
                            :src="payslip.media_url" 
                            :alt="payslip.title || 'Payslip'" 
                            class="w-full rounded-lg border cursor-pointer hover:opacity-90 transition-opacity"
                            @click="imageDialogOpen = true"
                        />
                    </CardContent>
                </Card>

                <!-- Detaljer - 1/3 af bredden -->
                <Card>
                    <CardHeader>
                        <CardTitle>Detaljer</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div v-if="payslip.title">
                            <h3 class="text-sm font-medium text-muted-foreground">Titel</h3>
                            <p class="mt-1">{{ payslip.title }}</p>
                        </div>

                        <Separator v-if="payslip.title" />

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Løn</h3>
                                <Button 
                                    v-if="!isEditingSalary"
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2"
                                    @click="startEditingSalary"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!isEditingSalary" class="mt-1">
                                <p class="text-xl font-semibold">{{ payslip.salary.toLocaleString('da-DK') }} kr.</p>
                            </div>
                            <div v-else class="mt-1 flex gap-2">
                                <Input 
                                    v-model="salaryInput" 
                                    type="number"
                                    placeholder="F.eks. 50000"
                                    class="h-8"
                                    @keyup.enter="saveSalary"
                                    @keyup.escape="cancelEditingSalary"
                                />
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="saveSalary"
                                >
                                    <Check class="h-4 w-4 text-green-600" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="cancelEditingSalary"
                                >
                                    <X class="h-4 w-4 text-red-600" />
                                </Button>
                            </div>
                        </div>

                        <div v-if="payslip.description">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Beskrivelse</h3>
                                <Dialog v-model:open="descriptionDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button variant="ghost" size="sm" class="h-6 px-2">
                                            <Maximize2 class="h-3 w-3" />
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-2xl">
                                        <DialogHeader>
                                            <DialogTitle>Beskrivelse</DialogTitle>
                                        </DialogHeader>
                                        <div class="max-h-[60vh] overflow-y-auto">
                                            <p class="whitespace-pre-wrap">{{ payslip.description }}</p>
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </div>
                            <p class="mt-1 line-clamp-8">{{ payslip.description }}</p>
                        </div>

                        <Separator v-if="payslip.description" />


                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Jobtitel</h3>
                                <Button 
                                    v-if="!isEditingJobTitle"
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2"
                                    @click="startEditingJobTitle"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!isEditingJobTitle" class="mt-1">
                                <p v-if="payslip.job_title">{{ payslip.job_title }}</p>
                                <p v-else class="text-muted-foreground italic">Ingen jobtitel valgt</p>
                            </div>
                            <div v-else class="mt-1 flex gap-2">
                                <select 
                                    v-model="jobTitleInput" 
                                    class="h-8 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option :value="null">Vælg jobtitel</option>
                                    <option 
                                        v-for="jobTitle in job_titles" 
                                        :key="jobTitle.id" 
                                        :value="jobTitle.id"
                                    >
                                        {{ jobTitle.name }}
                                    </option>
                                </select>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="saveJobTitle"
                                >
                                    <Check class="h-4 w-4 text-green-600" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="cancelEditingJobTitle"
                                >
                                    <X class="h-4 w-4 text-red-600" />
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div v-if="payslip.sub_job_title">
                            <h3 class="text-sm font-medium text-muted-foreground">Under-jobtitel</h3>
                            <p class="mt-1">{{ payslip.sub_job_title }}</p>
                        </div>

                        <Separator v-if="payslip.sub_job_title" />

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Erfaring</h3>
                                <Button 
                                    v-if="!isEditingExperience"
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2"
                                    @click="startEditingExperience"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!isEditingExperience" class="mt-1">
                                <p v-if="payslip.experience !== null">{{ payslip.experience }}</p>
                                <p v-else class="text-muted-foreground italic">Ingen erfaring angivet</p>
                            </div>
                            <div v-else class="mt-1 flex gap-2">
                                <Input 
                                    v-model="experienceInput" 
                                    placeholder="F.eks. 2-3 år"
                                    class="h-8"
                                    @keyup.enter="saveExperience"
                                    @keyup.escape="cancelEditingExperience"
                                />
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="saveExperience"
                                >
                                    <Check class="h-4 w-4 text-green-600" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="cancelEditingExperience"
                                >
                                    <X class="h-4 w-4 text-red-600" />
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Ansvarsområde</h3>
                                <Button 
                                    v-if="!isEditingAreaOfResponsibility"
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2"
                                    @click="startEditingAreaOfResponsibility"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!isEditingAreaOfResponsibility" class="mt-1">
                                <p v-if="payslip.area_of_responsibility">{{ payslip.area_of_responsibility }}</p>
                                <p v-else class="text-muted-foreground italic">Intet ansvarsområde valgt</p>
                            </div>
                            <div v-else class="mt-1 flex gap-2">
                                <select 
                                    v-model="areaOfResponsibilityInput" 
                                    class="h-8 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option :value="null">Vælg ansvarsområde</option>
                                    <option 
                                        v-for="area in areas_of_responsibility" 
                                        :key="area.id" 
                                        :value="area.id"
                                    >
                                        {{ area.name }}
                                    </option>
                                </select>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="saveAreaOfResponsibility"
                                >
                                    <Check class="h-4 w-4 text-green-600" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="cancelEditingAreaOfResponsibility"
                                >
                                    <X class="h-4 w-4 text-red-600" />
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-muted-foreground">Region</h3>
                                <Button 
                                    v-if="!isEditingRegion"
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-6 px-2"
                                    @click="startEditingRegion"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="!isEditingRegion" class="mt-1">
                                <p v-if="payslip.region">{{ payslip.region }}</p>
                                <p v-else class="text-muted-foreground italic">Ingen region valgt</p>
                            </div>
                            <div v-else class="mt-1 flex gap-2">
                                <select 
                                    v-model="regionInput" 
                                    class="h-8 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option :value="null">Vælg region</option>
                                    <option 
                                        v-for="region in regions" 
                                        :key="region.id" 
                                        :value="region.id"
                                    >
                                        {{ region.name }}
                                    </option>
                                </select>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="saveRegion"
                                >
                                    <Check class="h-4 w-4 text-green-600" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    class="h-8 w-8 p-0"
                                    @click="cancelEditingRegion"
                                >
                                    <X class="h-4 w-4 text-red-600" />
                                </Button>
                            </div>
                        </div>

                        <Separator v-if="payslip.comments && payslip.comments.length > 0" />

                        <div v-if="payslip.comments && payslip.comments.length > 0">
                            <Dialog v-model:open="commentsDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" class="w-full">
                                        <MessageSquare class="h-4 w-4 mr-2" />
                                        Vis kommentarer ({{ payslip.comments.length }})
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Kommentarer</DialogTitle>
                                        <DialogDescription>
                                            {{ payslip.comments.length }} kommentar{{ payslip.comments.length !== 1 ? 'er' : '' }}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                        <div 
                                            v-for="(comment, index) in payslip.comments" 
                                            :key="index"
                                            class="p-3 rounded-lg bg-muted"
                                        >
                                            <p class="text-sm">{{ comment }}</p>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button 
                            @click="handleApprove" 
                            variant="default"
                            class="flex-1"
                        >
                            Godkend
                        </Button>
                        <Button 
                            @click="handleDeny" 
                            variant="destructive"
                            class="flex-1"
                        >
                            Afslå
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

