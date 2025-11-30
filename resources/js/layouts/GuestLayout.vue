<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { login, register, home } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { LogIn, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- Simple Header -->
        <header class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/80 backdrop-blur-xl">
            <div class="container mx-auto flex h-16 items-center justify-between px-4 md:px-6">
                <!-- Logo -->
                <Link :href="home()" class="flex items-center gap-2.5 group">
                    <div class="relative flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 shadow-lg shadow-primary/25 transition-transform group-hover:scale-105">
                        <AppLogoIcon class="size-5 text-primary-foreground" />
                        <div class="absolute -inset-1 rounded-xl bg-primary/20 blur-md -z-10"></div>
                    </div>
                    <span class="text-xl font-bold tracking-tight">
                        LønRadar
                    </span>
                </Link>
                
                <!-- Auth Buttons (only show for guests) -->
                <div v-if="!user" class="flex items-center gap-3">
                    <Button as-child variant="ghost" size="sm">
                        <Link :href="login()">
                            <LogIn class="size-4 mr-2" />
                            Log ind
                        </Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="register()">
                            <UserPlus class="size-4 mr-2" />
                            Opret konto
                        </Link>
                    </Button>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8 md:px-6 mb-20">
            <slot />
        </main>
        
        <!-- Simple Footer -->
        <footer class="border-t border-border py-6 mt-auto">
            <div class="container mx-auto px-4 md:px-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground">
                            © {{ new Date().getFullYear() }} LønRadar
                        </span>
                    </div>
                    <nav class="flex gap-4 text-sm text-muted-foreground">
                        <Link :href="home()" class="hover:text-foreground transition-colors">
                            Forside
                        </Link>
                        <a href="#" class="hover:text-foreground transition-colors">
                            Privatlivspolitik
                        </a>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>

