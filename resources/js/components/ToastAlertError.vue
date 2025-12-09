<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, X } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';

interface Props {
    errors: string[];
    title?: string;
    autoHide?: boolean;
    autoHideDelay?: number;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Noget gik galt.',
    autoHide: true,
    autoHideDelay: 5000,
});

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();

const showAlert = ref(false);
const uniqueErrors = computed(() => Array.from(new Set(props.errors)));

let autoHideTimeout: ReturnType<typeof setTimeout> | null = null;

const dismissAlert = () => {
    showAlert.value = false;
    if (autoHideTimeout) {
        clearTimeout(autoHideTimeout);
        autoHideTimeout = null;
    }
    emit('dismiss');
};

watch(() => props.errors, (newErrors) => {
    if (newErrors && newErrors.length > 0) {
        showAlert.value = true;
        
        // Clear existing timeout
        if (autoHideTimeout) {
            clearTimeout(autoHideTimeout);
        }
        
        // Set new auto-hide timeout
        if (props.autoHide) {
            autoHideTimeout = setTimeout(() => {
                dismissAlert();
            }, props.autoHideDelay);
        }
    } else {
        showAlert.value = false;
        if (autoHideTimeout) {
            clearTimeout(autoHideTimeout);
            autoHideTimeout = null;
        }
    }
}, { immediate: true });

onMounted(() => {
    if (props.errors && props.errors.length > 0) {
        showAlert.value = true;
        
        if (props.autoHide) {
            autoHideTimeout = setTimeout(() => {
                dismissAlert();
            }, props.autoHideDelay);
        }
    }
});
</script>

<template>
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
                v-if="showAlert && uniqueErrors.length > 0" 
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4"
            >
                <Alert variant="destructive" class="shadow-lg">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle class="font-semibold">{{ title }}</AlertTitle>
                    <AlertDescription>
                        <ul class="list-inside list-disc text-sm">
                            <li v-for="(error, index) in uniqueErrors" :key="index">
                                {{ error }}
                            </li>
                        </ul>
                    </AlertDescription>
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
