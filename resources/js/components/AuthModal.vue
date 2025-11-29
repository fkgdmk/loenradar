<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import { store as loginStore } from '@/routes/login';
import { store as registerStore } from '@/routes/register';

interface Props {
    open: boolean;
    title?: string;
    description?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Log ind for at fortsætte',
    description: 'Log ind eller opret en konto for at generere din rapport',
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const activeTab = ref<'login' | 'register'>('login');

// Login form state
const loginForm = ref({
    email: '',
    password: '',
    remember: false,
});
const loginErrors = ref<Record<string, string>>({});
const loginProcessing = ref(false);

// Register form state
const registerForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const registerErrors = ref<Record<string, string>>({});
const registerProcessing = ref(false);

// Computed for dialog open state
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

// Reset forms when dialog closes
watch(isOpen, (newValue) => {
    if (!newValue) {
        resetForms();
    }
});

const resetForms = () => {
    loginForm.value = { email: '', password: '', remember: false };
    loginErrors.value = {};
    registerForm.value = { name: '', email: '', password: '', password_confirmation: '' };
    registerErrors.value = {};
};

const handleLogin = () => {
    loginProcessing.value = true;
    loginErrors.value = {};

    router.post(loginStore().url, loginForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            loginProcessing.value = false;
            isOpen.value = false;
            emit('success');
            // Let Inertia handle the redirect from the server response
        },
        onError: (errors) => {
            loginProcessing.value = false;
            loginErrors.value = errors;
        },
        onFinish: () => {
            loginProcessing.value = false;
        },
    });
};

const handleRegister = () => {
    registerProcessing.value = true;
    registerErrors.value = {};

    router.post(registerStore().url, registerForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            registerProcessing.value = false;
            isOpen.value = false;
            emit('success');
            // Let Inertia handle the redirect from the server response
        },
        onError: (errors) => {
            registerProcessing.value = false;
            registerErrors.value = errors;
        },
        onFinish: () => {
            registerProcessing.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <!-- Tabs -->
            <div class="flex border-b border-border mb-6">
                <button
                    type="button"
                    :class="[
                        'flex-1 py-3 text-sm font-medium border-b-2 transition-colors',
                        activeTab === 'login'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                    @click="activeTab = 'login'"
                >
                    Log ind
                </button>
                <button
                    type="button"
                    :class="[
                        'flex-1 py-3 text-sm font-medium border-b-2 transition-colors',
                        activeTab === 'register'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                    @click="activeTab = 'register'"
                >
                    Opret konto
                </button>
            </div>

            <!-- Login Form -->
            <form v-if="activeTab === 'login'" @submit.prevent="handleLogin" class="space-y-4">
                <div class="space-y-2">
                    <Label for="login-email">Email</Label>
                    <Input
                        id="login-email"
                        v-model="loginForm.email"
                        type="email"
                        placeholder="email@eksempel.dk"
                        required
                        autocomplete="email"
                    />
                    <InputError :message="loginErrors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="login-password">Adgangskode</Label>
                    <Input
                        id="login-password"
                        v-model="loginForm.password"
                        type="password"
                        placeholder="Adgangskode"
                        required
                        autocomplete="current-password"
                    />
                    <InputError :message="loginErrors.password" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="login-remember"
                        v-model:checked="loginForm.remember"
                    />
                    <Label for="login-remember" class="text-sm font-normal cursor-pointer">
                        Husk mig
                    </Label>
                </div>

                <Button type="submit" class="w-full" :disabled="loginProcessing">
                    <Spinner v-if="loginProcessing" class="mr-2" />
                    Log ind
                </Button>
            </form>

            <!-- Register Form -->
            <form v-if="activeTab === 'register'" @submit.prevent="handleRegister" class="space-y-4">
                <div class="space-y-2">
                    <Label for="register-name">Navn</Label>
                    <Input
                        id="register-name"
                        v-model="registerForm.name"
                        type="text"
                        placeholder="Dit fulde navn"
                        required
                        autocomplete="name"
                    />
                    <InputError :message="registerErrors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="register-email">Email</Label>
                    <Input
                        id="register-email"
                        v-model="registerForm.email"
                        type="email"
                        placeholder="email@eksempel.dk"
                        required
                        autocomplete="email"
                    />
                    <InputError :message="registerErrors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="register-password">Adgangskode</Label>
                    <Input
                        id="register-password"
                        v-model="registerForm.password"
                        type="password"
                        placeholder="Adgangskode"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="registerErrors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="register-password-confirm">Bekræft adgangskode</Label>
                    <Input
                        id="register-password-confirm"
                        v-model="registerForm.password_confirmation"
                        type="password"
                        placeholder="Bekræft adgangskode"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="registerErrors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :disabled="registerProcessing">
                    <Spinner v-if="registerProcessing" class="mr-2" />
                    Opret konto
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>

