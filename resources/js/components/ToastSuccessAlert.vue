<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CheckCircle2, X } from 'lucide-vue-next';
import { ref, watch, onMounted } from 'vue';

interface Props {
    message: string;
    title?: string;
    autoHide?: boolean;
    autoHideDelay?: number;
    show?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Sådan!',
    autoHide: true,
    autoHideDelay: 7000,
    show: false,
});

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();

const showAlert = ref(props.show);

let autoHideTimeout: ReturnType<typeof setTimeout> | null = null;

const dismissAlert = () => {
    showAlert.value = false;
    if (autoHideTimeout) {
        clearTimeout(autoHideTimeout);
        autoHideTimeout = null;
    }
    emit('dismiss');
};

watch(() => props.show, (newValue) => {
    if (newValue) {
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

watch(() => props.message, () => {
    if (props.message && props.show) {
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
    }
});

onMounted(() => {
    if (props.show && props.message) {
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
                v-if="showAlert && message" 
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-full max-w-md px-4"
            >
                <Alert variant="default" class="shadow-lg border-gray-500/80">
                    <CheckCircle2 class="h-4 w-4" />
                    <AlertTitle class="font-semibold">{{ title }}</AlertTitle>
                    <AlertDescription>{{ message }}</AlertDescription>
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
