import { ref, computed, watch, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import type {
    ReportFormProps,
    ReportData,
    UploadedDocument,
    Step1Errors,
    ReportFormEndpoints,
} from '@/types/report';

export type EndpointResolver = string | ((reportId: number | string | null) => string);

export interface ReportFormEndpointsConfig {
    step1: EndpointResolver;
    step2: EndpointResolver;
    submit: EndpointResolver;
    /** HTTP method for step1 - 'post' for new reports, 'patch' for updates */
    step1Method?: 'post' | 'patch';
}

export interface UseReportFormOptions {
    props: ReportFormProps;
    endpoints: ReportFormEndpointsConfig;
    onStep1Success?: () => void;
    onStep2Success?: () => void;
    onSubmitSuccess?: () => void;
}

function resolveEndpoint(endpoint: EndpointResolver, reportId: number | string | null): string {
    if (typeof endpoint === 'function') {
        return endpoint(reportId);
    }
    return endpoint;
}

export function useReportForm(options: UseReportFormOptions) {
    const { props, endpoints, onStep1Success, onStep2Success, onSubmitSuccess } = options;

    // Report ID
    const reportId = ref<number | string | null>(props.report?.id ?? null);

    // Payslip warning state
    const payslipWarning = ref<string | null>(null);
    const showPayslipWarningModal = ref(false);

    // Step calculation
    const initialStep = computed(() => {
        if (props.report?.step) {
            return props.report.step;
        }
        if (props.report?.responsibility_level_id) {
            return props.report.skill_ids?.length >= 0 ? 3 : 2;
        }
        return 1;
    });

    const currentStep = ref(initialStep.value);
    
    // File upload state
    const fileInputRef = ref<HTMLInputElement | null>(null);
    const uploadedFile = ref<File | null>(null);
    const uploadedFilePreview = ref<string | null>(null);
    const persistedDocument = ref<UploadedDocument | null>(props.report?.document ?? null);
    const selectedSkills = ref<number[]>(props.report?.skill_ids ?? []);

    // Anonymizer state
    const showAnonymizer = ref(false);
    const fileToAnonymize = ref<File | null>(null);

    // Search state for skills
    const skillSearch = ref('');

    // Validation errors for step 1
    const step1Errors = reactive<Step1Errors>({
        document: '',
        job_title_id: '',
        area_of_responsibility_id: '',
        experience: '',
        region_id: '',
    });

    // Normalize gender on init
    const initialGender = props.report?.gender && props.report.gender.trim() !== '' 
        ? props.report.gender 
        : null;

    // Form state
    const form = useForm({
        document: null as File | null,
        job_title_id: props.report?.job_title_id ?? null as number | null,
        area_of_responsibility_id: props.report?.area_of_responsibility_id ?? null as number | null,
        experience: props.report?.experience ?? null as number | null,
        gender: initialGender as string | null,
        region_id: props.report?.region_id ?? null as number | null,
        responsibility_level_id: props.report?.responsibility_level_id ?? null as number | null,
        team_size: props.report?.team_size ?? null as number | null,
        skill_ids: props.report?.skill_ids ?? [] as number[],
    });

    // Computed getters/setters for number inputs
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

    // Watch uploadedFile
    watch(uploadedFile, (newFile) => {
        if (newFile) {
            form.document = newFile;
            persistedDocument.value = null;
        }
    });

    // Format file size
    const formatFileSize = (sizeInBytes: number | null | undefined) => {
        if (!sizeInBytes) return '';
        const kb = sizeInBytes / 1024;
        if (kb > 1024) {
            return `${(kb / 1024).toFixed(2)} MB`;
        }
        return `${kb.toFixed(2)} KB`;
    };

    // Computed properties
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

    const isDocumentUploaded = computed(() => {
        return !!(uploadedFile.value || persistedDocument.value);
    });

    const isStep1Valid = computed(() => {
        if (reportId.value) {
            return true;
        }
        return !!(
            (uploadedFile.value || persistedDocument.value) &&
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

    const isStep1Completed = computed(() => {
        return reportId.value !== null || isStep1Valid.value;
    });

    const isStep2Completed = computed(() => {
        return form.responsibility_level_id !== null;
    });

    const isStep3Completed = computed(() => {
        return isStep2Completed.value;
    });

    // Navigation
    const navigateToStep = (step: number) => {
        if (step === 1 && isStep1Completed.value) {
            currentStep.value = 1;
        } else if (step === 2 && isStep2Completed.value) {
            currentStep.value = 2;
        } else if (step === 3 && isStep3Completed.value) {
            currentStep.value = 3;
        }
    };

    // File handling
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
        
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
    };

    const cancelAnonymizer = () => {
        showAnonymizer.value = false;
        fileToAnonymize.value = null;
        
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

    // Skills
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

    const sanitizeSelectedSkillsForJobTitle = () => {
        const allowed = jobTitleSkillIds.value;
        if (!allowed.length) return;
        const filtered = selectedSkills.value.filter(id => allowed.includes(id));
        if (filtered.length !== selectedSkills.value.length) {
            selectedSkills.value = filtered;
            form.skill_ids = filtered;
        }
    };

    const filteredSkills = computed(() => {
        if (!form.job_title_id) return [];
        const source = availableSkills.value;
        if (!skillSearch.value) return source;
        const search = skillSearch.value.toLowerCase();
        return source.filter(s => s.name.toLowerCase().includes(search));
    });

    // Error handling
    const clearStep1Errors = () => {
        step1Errors.document = '';
        step1Errors.job_title_id = '';
        step1Errors.area_of_responsibility_id = '';
        step1Errors.experience = '';
        step1Errors.region_id = '';
    };

    const setStep1Errors = (errors: Record<string, string>) => {
        clearStep1Errors();
        if (errors.document) step1Errors.document = errors.document;
        if (errors.job_title_id) step1Errors.job_title_id = errors.job_title_id;
        if (errors.area_of_responsibility_id) step1Errors.area_of_responsibility_id = errors.area_of_responsibility_id;
        if (errors.experience) step1Errors.experience = errors.experience;
        if (errors.region_id) step1Errors.region_id = errors.region_id;
    };

    // Navigation and submission
    const nextStep = async () => {
        if (currentStep.value === 1) {
            clearStep1Errors();
            
            const normalizedGender = form.gender && form.gender.trim() !== '' ? form.gender : null;
            
            // Determine method: use PATCH if report already exists, otherwise use configured method
            const hasExistingReport = reportId.value && reportId.value !== 'guest';
            const step1Method = hasExistingReport ? 'patch' : (endpoints.step1Method ?? 'post');
            const step1Url = resolveEndpoint(endpoints.step1, reportId.value);
            
            // Build form data - only include document for new reports (POST)
            const step1Data: Record<string, any> = {
                job_title_id: form.job_title_id,
                area_of_responsibility_id: form.area_of_responsibility_id,
                experience: form.experience,
                gender: normalizedGender,
                region_id: form.region_id,
            };
            
            // Only include document for new reports
            if (step1Method === 'post') {
                step1Data.document = uploadedFile.value || form.document;
            }
            
            const step1Form = useForm(step1Data);

            const submitOptions = {
                forceFormData: step1Method === 'post',
                preserveScroll: true,
                onSuccess: () => {
                    onStep1Success?.();
                },
                onError: (errors: Record<string, string>) => {
                    if (errors.payslip_warning) {
                        payslipWarning.value = errors.payslip_warning;
                        showPayslipWarningModal.value = true;
                        // Use report_id from error response if available (report was saved)
                        if (errors.report_id) {
                            reportId.value = parseInt(errors.report_id, 10);
                            delete errors.report_id;
                        }
                        delete errors.payslip_warning;
                    }
                    setStep1Errors(errors);
                },
            };

            if (step1Method === 'patch') {
                step1Form.patch(step1Url, submitOptions);
            } else {
                step1Form.post(step1Url, submitOptions);
            }
        } else if (currentStep.value === 2 && canProceedToStep3.value) {
            if (!reportId.value || reportId.value === 'guest') {
                console.error('Ingen report_id fundet');
                return;
            }

            const step2Url = resolveEndpoint(endpoints.step2, reportId.value);
            
            const step2Form = useForm({
                report_id: reportId.value,
                responsibility_level_id: form.responsibility_level_id,
                team_size: form.team_size,
                skill_ids: form.skill_ids,
            });

            step2Form.patch(step2Url, {
                preserveScroll: false,
                onSuccess: () => {
                    onStep2Success?.();
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

    const submitReport = (additionalData?: Record<string, any>) => {
        if (!reportId.value || reportId.value === 'guest') {
            console.error('Ingen report_id fundet');
            return;
        }

        const submitForm = useForm({
            report_id: reportId.value,
            ...additionalData,
        });
        
        submitForm.post(endpoints.submit, {
            onSuccess: () => {
                onSubmitSuccess?.();
            },
            onError: (errors: any) => {
                console.error('Fejl ved færdiggørelse:', errors);
            },
        });
    };

    // Lookup helpers
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

    // Combobox options
    const jobTitleOptions = computed(() => 
        props.job_titles.map(jt => ({ 
            value: jt.id, 
            label: jt.name_en,
            searchText: `${jt.name} ${jt.name_en}`
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

    // Close payslip warning modal and optionally save contact email
    const closePayslipWarningModal = (contactEmail?: string) => {
        // If contact email is provided and report exists, save it
        if (contactEmail && contactEmail.trim() && reportId.value) {
            router.post('/reports/contact-email', {
                report_id: reportId.value,
                contact_email: contactEmail.trim(),
            }, {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    console.error('Fejl ved gemning af kontakt email:', errors);
                },
            });
        }
        
        showPayslipWarningModal.value = false;
        payslipWarning.value = null;
    };

    // Watchers
    watch(() => props.report, (newReport) => {
        if (newReport) {
            reportId.value = newReport.id;
            persistedDocument.value = newReport.document ?? null;
            selectedSkills.value = newReport.skill_ids ?? [];
            
            form.job_title_id = newReport.job_title_id ?? null;
            form.area_of_responsibility_id = newReport.area_of_responsibility_id ?? null;
            form.experience = newReport.experience ?? null;
            form.gender = newReport.gender && newReport.gender.trim() !== '' ? newReport.gender : null;
            form.region_id = newReport.region_id ?? null;
            form.responsibility_level_id = newReport.responsibility_level_id ?? null;
            form.team_size = newReport.team_size ?? null;
            form.skill_ids = newReport.skill_ids ?? [];
            
            let newStep = newReport.step ?? initialStep.value;
            if (newStep === 1 && newReport.job_title_id && newReport.region_id && newReport.experience !== null) {
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
            skillSearch.value = '';
        } else {
            sanitizeSelectedSkillsForJobTitle();
        }
    });

    watch(jobTitleSkillIds, () => {
        sanitizeSelectedSkillsForJobTitle();
    });

    watch(() => form.gender, (newValue) => {
        if (newValue === '' || newValue === null) {
            form.gender = null;
        }
    });

    watch(() => form.responsibility_level_id, () => {
        if (!showTeamSize.value) {
            form.team_size = null;
        }
    });

    // Initialize
    sanitizeSelectedSkillsForJobTitle();

    return {
        // State
        reportId,
        currentStep,
        form,
        
        // File state
        fileInputRef,
        uploadedFile,
        uploadedFilePreview,
        persistedDocument,
        showAnonymizer,
        fileToAnonymize,
        
        // Skills
        selectedSkills,
        skillSearch,
        filteredSkills,
        
        // Errors
        step1Errors,
        payslipWarning,
        showPayslipWarningModal,
        
        // Computed
        experienceInput,
        teamSizeInput,
        showAreaOfResponsibility,
        showTeamSize,
        isDocumentUploaded,
        isStep1Valid,
        isStep2Valid,
        canProceedToStep3,
        isStep1Completed,
        isStep2Completed,
        isStep3Completed,
        
        // Options
        jobTitleOptions,
        areaOfResponsibilityOptions,
        regionOptions,
        responsibilityLevelOptions,
        
        // Methods
        formatFileSize,
        navigateToStep,
        handleFileChange,
        handleAnonymizedImage,
        cancelAnonymizer,
        removeFile,
        toggleSkill,
        nextStep,
        previousStep,
        submitReport,
        closePayslipWarningModal,
        
        // Lookup helpers
        getJobTitleName,
        getRegionName,
        getAreaOfResponsibilityName,
        getResponsibilityLevelName,
        getSkillNames,
    };
}

