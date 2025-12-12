<script setup lang="ts">
import { dashboard, login, register, getStarted } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { 
    ChartNoAxesCombined, 
    Upload, 
    Shield, 
    TrendingUp, 
    Users, 
    Zap,
    ArrowRight,
    CheckCircle2,
    Menu,
    X,
    Sparkles,
    Lock,
    BadgeCheck
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const mobileMenuOpen = ref(false);

// Animated counter
const animatedCount = ref(0);
const targetCount = 723;

onMounted(() => {
    const duration = 2000;
    const steps = 60;
    const increment = targetCount / steps;
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= targetCount) {
            animatedCount.value = targetCount;
            clearInterval(timer);
        } else {
            animatedCount.value = Math.floor(current);
        }
    }, duration / steps);
});
</script>

<template>
    <Head title="LønRadar - Få overblik over din løn">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet" />
    </Head>
    
    <div class="min-h-screen bg-background text-foreground overflow-x-hidden">
        <!-- Navigation -->
        <header class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/80 backdrop-blur-xl">
            <div class="container mx-auto flex h-16 items-center justify-between px-4 md:px-6">
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-2.5 group">
                    <div class="relative flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 shadow-lg shadow-primary/25 transition-transform group-hover:scale-105">
                        <AppLogoIcon class="size-5 text-primary-foreground" />
                        <div class="absolute -inset-1 rounded-xl bg-primary/20 blur-md -z-10"></div>
                    </div>
                    <span class="text-xl font-bold tracking-tight font-display">
                        LønRadar
                    </span>
                </Link>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="#features" class="px-4 py-2 text-sm font-medium text-muted-foreground rounded-lg transition-all hover:text-foreground hover:bg-accent">
                        Funktioner
                    </a>
                    <a href="#how-it-works" class="px-4 py-2 text-sm font-medium text-muted-foreground rounded-lg transition-all hover:text-foreground hover:bg-accent">
                        Sådan virker det
                    </a>
                </nav>
                
                <!-- Auth Buttons -->
                <div class="hidden md:flex items-center gap-3">
                    <template v-if="$page.props.auth.user">
                        <Button as-child>
                            <Link :href="dashboard()">
                                Dashboard
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button as-child variant="ghost" class="font-medium">
                            <Link :href="login()">
                                Log ind
                            </Link>
                        </Button>
                        <Button as-child class="shadow-lg shadow-primary/25">
                            <Link :href="getStarted()">
                                Kom i gang
                                <Sparkles class="size-4 ml-1" />
                            </Link>
                        </Button>
                    </template>
                </div>
                
                <!-- Mobile Menu Button -->
                <button 
                    class="md:hidden p-2 rounded-lg hover:bg-accent transition-colors"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <Menu v-if="!mobileMenuOpen" class="size-5" />
                    <X v-else class="size-5" />
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div 
                v-if="mobileMenuOpen"
                class="md:hidden border-t border-border bg-background/95 backdrop-blur-xl"
            >
                <nav class="container mx-auto flex flex-col gap-2 p-4">
                    <a href="#features" class="px-4 py-3 text-sm font-medium text-muted-foreground rounded-lg transition-all hover:text-foreground hover:bg-accent" @click="mobileMenuOpen = false">
                        Funktioner
                    </a>
                    <a href="#how-it-works" class="px-4 py-3 text-sm font-medium text-muted-foreground rounded-lg transition-all hover:text-foreground hover:bg-accent" @click="mobileMenuOpen = false">
                        Sådan virker det
                    </a>
                    <div class="flex flex-col gap-2 pt-4 mt-2 border-t border-border">
                        <template v-if="$page.props.auth.user">
                            <Button as-child class="w-full">
                                <Link :href="dashboard()">Dashboard</Link>
                            </Button>
                        </template>
                        <template v-else>
                            <Button as-child variant="outline" class="w-full">
                                <Link :href="login()">Log ind</Link>
                            </Button>
                            <Button as-child class="w-full">
                                <Link :href="getStarted()">Kom i gang gratis</Link>
                            </Button>
                        </template>
                    </div>
                </nav>
            </div>
        </header>
        
        <main>
            <!-- Hero Section -->
            <section class="relative min-h-[90vh] flex items-center overflow-hidden">
                <!-- Animated Background -->
                <div class="absolute inset-0 -z-10">
                    <!-- Grid pattern -->
                    <div class="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border)/0.3)_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border)/0.3)_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_110%)]"></div>
                    
                    <!-- Gradient orbs -->
                    <div class="absolute top-20 left-10 w-72 h-72 bg-primary/30 rounded-full blur-[100px] animate-blob"></div>
                    <div class="absolute top-40 right-10 w-96 h-96 bg-orange-400/20 rounded-full blur-[100px] animate-blob animation-delay-2000"></div>
                    <div class="absolute -bottom-20 left-1/3 w-80 h-80 bg-rose-400/20 rounded-full blur-[100px] animate-blob animation-delay-4000"></div>
                </div>
                
                <div class="container mx-auto px-4 md:px-6 py-20">
                    <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                        <!-- Left Column - Text -->
                        <div class="text-center lg:text-left">
                            <!-- Floating Badge -->
                            <div class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-primary/10 to-orange-500/10 border border-primary/20 px-4 py-2 text-sm font-medium mb-8">
                                <span class="flex size-2">
                                    <span class="animate-ping absolute inline-flex size-2 rounded-full bg-primary opacity-75"></span>
                                    <span class="relative inline-flex rounded-full size-2 bg-primary"></span>
                                </span>
                                <span class="bg-gradient-to-r from-primary to-orange-500 bg-clip-text text-transparent font-semibold">
                                    {{ animatedCount.toLocaleString('da-DK') }}+ lønsedler analyseret
                                </span>
                            </div>
                            
                            <!-- Headline -->
                            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold tracking-tight mb-6 font-display">
                                Ved du hvad din løn
                                <span class="relative">
                                    <span class="bg-gradient-to-r from-primary via-orange-500 to-rose-500 bg-clip-text text-transparent"> burde være?</span>
                                    <svg class="absolute -bottom-2 left-0 w-full h-3 text-primary/30" viewBox="0 0 200 12" preserveAspectRatio="none">
                                        <path d="M0 9c30-4 60-7 100-7s70 3 100 7" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"/>
                                    </svg>
                                </span>
                            </h1>
                            
                            <p class="text-lg md:text-xl text-muted-foreground max-w-xl mx-auto lg:mx-0 mb-10">
                                Upload din lønseddel og få adgang til Danmarks eneste lønstatistik baseret på verificerede lønsedler. 
                                <span class="text-foreground font-medium">Vi gemmer aldrig følsomme oplysninger – kun dataen.</span>
                            </p>
                            
                            <!-- CTA -->
                            <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center">
                                <Button as-child size="lg" class="text-base px-8 h-14 shadow-xl shadow-primary/30 hover:shadow-primary/40 transition-shadow">
                                    <Link :href="getStarted()">
                                        Kom i gang gratis
                                        <ArrowRight class="size-5 ml-2" />
                                    </Link>
                                </Button>
                                <Button as-child variant="outline" size="lg" class="text-base px-8 h-14 border-2">
                                    <a href="#how-it-works">
                                        Se hvordan det virker
                                    </a>
                                </Button>
                            </div>
                            
                            <!-- Trust badges -->
                            <div class="flex flex-wrap justify-center lg:justify-start gap-6 mt-12">
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-border/50 text-black text-sm font-medium">
                                    <TrendingUp class="size-4" />
                                    Optimér din løn
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-border/50 text-sm font-medium">
                                    <Users class="size-4" />
                                    Crowdsourced data
                                </div>
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-border/50 text-sm font-medium">
                                    <Shield class="size-4" />
                                    100% anonymt
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - Visual -->
                        <div class="relative hidden lg:block">
                            <!-- Salary Visualization Card -->
                            <div class="relative">
                                <!-- Main card -->
                                <div class="relative bg-card/80 backdrop-blur-xl rounded-3xl border border-border/50 shadow-2xl p-8 transform rotate-1 hover:rotate-0 transition-transform duration-500">
                                    <div class="flex items-center justify-between mb-6">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-xl bg-gradient-to-br from-primary to-orange-500 flex items-center justify-center">
                                                <ChartNoAxesCombined class="size-5 text-white" />
                                            </div>
                                            <div>
                                                <p class="font-semibold font-display">Din lønrapport</p>
                                                <p class="text-sm text-muted-foreground">Software Engineer</p>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-full bg-green-500/10 text-green-600 text-sm font-medium">
                                            +12% over median
                                        </span>
                                    </div>
                                    
                                    <!-- Salary bar visualization -->
                                    <div class="space-y-4">
                                        <div class="relative h-16 bg-gradient-to-r from-rose-500/20 via-amber-500/20 to-green-500/20 rounded-xl overflow-hidden">
                                            <div class="absolute inset-y-0 left-0 w-1/4 bg-rose-500/30 flex items-center justify-center">
                                                <span class="text-xs font-medium text-rose-700 dark:text-rose-300">25%</span>
                                            </div>
                                            <div class="absolute inset-y-0 left-1/4 w-1/2 bg-amber-500/30 flex items-center justify-center">
                                                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Median</span>
                                            </div>
                                            <div class="absolute inset-y-0 right-0 w-1/4 bg-green-500/30 flex items-center justify-center">
                                                <span class="text-xs font-medium text-green-700 dark:text-green-300">75%</span>
                                            </div>
                                            <!-- Indicator -->
                                            <div class="absolute top-3/5 -translate-y-1/2 left-[62%] flex flex-col items-center animate-bounce-subtle">
                                                <div class="size-6 rounded-full bg-primary border-4 border-background shadow-lg"></div>
                                                <div class="mt-1 px-2 py-0.5 rounded bg-primary text-primary-foreground text-xs font-bold whitespace-nowrap">
                                                    Din løn
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-3 gap-4 text-center">
                                            <div class="p-3 rounded-xl bg-muted/50">
                                                <p class="text-2xl font-bold font-display text-rose-600">44.500</p>
                                                <p class="text-xs text-muted-foreground">Laveste</p>
                                            </div>
                                            <div class="p-3 rounded-xl bg-muted/50">
                                                <p class="text-2xl font-bold font-display text-amber-600">52.000</p>
                                                <p class="text-xs text-muted-foreground">Median</p>
                                            </div>
                                            <div class="p-3 rounded-xl bg-muted/50">
                                                <p class="text-2xl font-bold font-display text-green-600">58.000</p>
                                                <p class="text-xs text-muted-foreground">Højeste</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Floating elements -->
                                <div class="absolute -top-6 -right-6 px-4 py-2 bg-card rounded-2xl border border-border shadow-lg flex items-center gap-2 animate-float">
                                    <BadgeCheck class="size-5 text-primary" />
                                    <div>
                                        <p class="text-sm font-semibold">Klar til lønforhandling?</p>
                                        <p class="text-xs text-muted-foreground">Underbyg med data</p>
                                    </div>
                                </div>
                                
                                <div class="absolute -bottom-4 -left-8 px-4 py-2 bg-card rounded-2xl border border-border shadow-lg flex items-center gap-2 animate-float animation-delay-2000">
                                    <Lock class="size-5 text-primary" />
                                    <div>
                                        <p class="text-sm font-semibold">Anonymiseret</p>
                                        <p class="text-xs text-muted-foreground">100% sikkert</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Marquee Section -->
            <section class="py-8 border-y border-border bg-muted/30 overflow-hidden">
                <div class="flex animate-marquee">
                    <div class="flex items-center gap-8 px-4 whitespace-nowrap">
                        <span class="text-muted-foreground font-medium">🎯 Præcise løndata</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">🔐 Fuld anonymitet</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">⚡ AI-drevet analyse</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">📈 Sammenlign din løn</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">🇩🇰 Dansk løndata</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">💼 Alle brancher</span>
                        <span class="text-primary">•</span>
                    </div>
                    <div class="flex items-center gap-8 px-4 whitespace-nowrap">
                        <span class="text-muted-foreground font-medium">🎯 Præcise løndata</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">🔐 Fuld anonymitet</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">⚡ AI-drevet analyse</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">📈 Sammenlign din løn</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">🇩🇰 Dansk løndata</span>
                        <span class="text-primary">•</span>
                        <span class="text-muted-foreground font-medium">💼 Alle brancher</span>
                        <span class="text-primary">•</span>
                    </div>
                </div>
            </section>
            
            <!-- Features Section - Bento Grid -->
            <section id="features" class="py-24 md:py-32">
                <div class="container mx-auto px-4 md:px-6">
                    <div class="mx-auto max-w-2xl text-center mb-16">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary mb-4">
                            <Sparkles class="size-4" />
                            Funktioner
                        </div>
                        <h2 class="text-3xl md:text-5xl font-bold tracking-tight mb-4 font-display">
                            Alt hvad du behøver for at
                            <span class="bg-gradient-to-r from-primary to-orange-500 bg-clip-text text-transparent">forstå din løn</span>
                        </h2>
                        <p class="text-lg text-muted-foreground">
                            Få indsigt i lønstatistikker og sammenlign din løn med andre i din branche.
                        </p>
                    </div>
                    
                    <!-- Bento Grid -->
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                        <!-- Large Feature Card -->
                        <div class="md:col-span-2 lg:col-span-2 group relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/10 via-primary/5 to-transparent border border-primary/20 p-8 transition-all hover:shadow-2xl hover:shadow-primary/10">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full blur-3xl -z-10 group-hover:bg-primary/20 transition-colors"></div>
                            <div class="flex flex-col h-full">
                                <div class="mb-6 inline-flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-orange-500 text-white shadow-lg shadow-primary/30">
                                    <Upload class="size-7" />
                                </div>
                                <h3 class="text-2xl font-bold mb-3 font-display">Upload lønseddel</h3>
                                <p class="text-muted-foreground text-lg flex-1">
                                    Træk og slip din lønseddel - vores AI ekstraherer automatisk alle relevante data på få sekunder. Sikkert og anonymt.
                                </p>
                                <div class="mt-6 flex items-center gap-4">
                                    <div class="flex -space-x-2">
                                        <div class="size-8 rounded-full bg-gradient-to-br from-rose-400 to-rose-500 border-2 border-background flex items-center justify-center text-white text-xs">JK</div>
                                        <div class="size-8 rounded-full bg-gradient-to-br from-blue-400 to-blue-500 border-2 border-background flex items-center justify-center text-white text-xs">ML</div>
                                        <div class="size-8 rounded-full bg-gradient-to-br from-green-400 to-green-500 border-2 border-background flex items-center justify-center text-white text-xs">AS</div>
                                    </div>
                                    <span class="text-sm text-muted-foreground">500+ har uploadet denne uge</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Small Feature Cards -->
                        <div class="group relative overflow-hidden rounded-3xl bg-card border border-border p-6 transition-all hover:shadow-xl hover:border-primary/30 hover:-translate-y-1">
                            <div class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
                                <ChartNoAxesCombined class="size-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-2 font-display">Se statistikker</h3>
                            <p class="text-muted-foreground">
                                Detaljeret indsigt i hvad andre med samme jobtitel og erfaring tjener.
                            </p>
                        </div>
                        
                        <div class="group relative overflow-hidden rounded-3xl bg-card border border-border p-6 transition-all hover:shadow-xl hover:border-primary/30 hover:-translate-y-1">
                            <div class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-green-500/10 text-green-500">
                                <TrendingUp class="size-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-2 font-display">Sammenlign</h3>
                            <p class="text-muted-foreground">
                                Se hvordan din løn placerer sig i forhold til gennemsnittet for din branche.
                            </p>
                        </div>
                        
                        <div class="group relative overflow-hidden rounded-3xl bg-card border border-border p-6 transition-all hover:shadow-xl hover:border-primary/30 hover:-translate-y-1">
                            <div class="mb-4 inline-flex size-12 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500">
                                <Shield class="size-6" />
                            </div>
                            <h3 class="text-xl font-bold mb-2 font-display">Fuld anonymitet</h3>
                            <p class="text-muted-foreground">
                                Dine data behandles 100% anonymt. Vi deler aldrig personlige oplysninger.
                            </p>
                        </div>
                        
                        <!-- Wide Feature Card -->
                        <div class="md:col-span-2 group relative overflow-hidden rounded-3xl bg-gradient-to-r from-card to-muted/30 border border-border p-6 md:p-8 transition-all hover:shadow-xl">
                            <div class="flex flex-col md:flex-row gap-6 items-start md:items-center">
                                <div class="mb-4 md:mb-0 inline-flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500">
                                    <Users class="size-7" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold mb-2 font-display">Fællesskabsdata</h3>
                                    <p class="text-muted-foreground">
                                        Jo flere der bidrager, jo bedre bliver statistikkerne for alle. Vær med til at skabe gennemsigtighed i det danske arbejdsmarked.
                                    </p>
                                </div>
                                <div class="flex flex-col items-center justify-center p-4 rounded-2xl bg-muted/50">
                                    <span class="text-3xl font-bold font-display text-primary">{{ animatedCount.toLocaleString('da-DK') }}</span>
                                    <span class="text-sm text-muted-foreground">lønsedler</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- How it works Section -->
            <section id="how-it-works" class="py-24 md:py-32 bg-muted/30 relative overflow-hidden">
                <!-- Background decoration -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-gradient-to-r from-primary/5 to-orange-500/5 rounded-full blur-3xl -z-10"></div>
                
                <div class="container mx-auto px-4 md:px-6">
                    <div class="mx-auto max-w-2xl text-center mb-16">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary mb-4">
                            <Zap class="size-4" />
                            Kom i gang
                        </div>
                        <h2 class="text-3xl md:text-5xl font-bold tracking-tight mb-4 font-display">
                            Tre simple trin
                        </h2>
                        <p class="text-lg text-muted-foreground">
                            Fra upload til indsigt på under et minut
                        </p>
                    </div>
                    
                    <div class="mx-auto max-w-5xl">
                        <div class="grid md:grid-cols-3 gap-8 md:gap-4">
                            <!-- Step 1 -->
                            <div class="relative group">
                                <div class="text-center">
                                    <!-- Number -->
                                    <div class="relative inline-flex mb-8">
                                        <div class="size-20 rounded-3xl bg-gradient-to-br from-primary to-orange-500 flex items-center justify-center text-3xl font-bold text-white shadow-xl shadow-primary/30 group-hover:scale-110 transition-transform">
                                            1
                                        </div>
                                        <div class="absolute -inset-4 rounded-full bg-primary/10 -z-10 animate-pulse"></div>
                                    </div>
                                    <!-- Arrow (hidden on mobile) -->
                                    <div class="hidden md:block absolute top-10 left-full w-full h-0.5 bg-gradient-to-r from-primary to-transparent -z-10"></div>
                                    
                                    <h3 class="text-xl font-bold mb-3 font-display">Opret gratis konto</h3>
                                    <p class="text-muted-foreground">
                                        Tilmeld dig på få sekunder med din email. Ingen kreditkort påkrævet.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Step 2 -->
                            <div class="relative group">
                                <div class="text-center">
                                    <div class="relative inline-flex mb-8">
                                        <div class="size-20 rounded-3xl bg-gradient-to-br from-orange-500 to-amber-500 flex items-center justify-center text-3xl font-bold text-white shadow-xl shadow-orange-500/30 group-hover:scale-110 transition-transform">
                                            2
                                        </div>
                                        <div class="absolute -inset-4 rounded-full bg-orange-500/10 -z-10 animate-pulse animation-delay-2000"></div>
                                    </div>
                                    <div class="hidden md:block absolute top-10 left-full w-full h-0.5 bg-gradient-to-r from-orange-500 to-transparent -z-10"></div>
                                    
                                    <h3 class="text-xl font-bold mb-3 font-display">Upload lønseddel</h3>
                                    <p class="text-muted-foreground">
                                        Træk og slip din lønseddel. Vores AI gør resten automatisk.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Step 3 -->
                            <div class="relative group">
                                <div class="text-center">
                                    <div class="relative inline-flex mb-8">
                                        <div class="size-20 rounded-3xl bg-gradient-to-br from-green-500 to-emerald-500 flex items-center justify-center text-3xl font-bold text-white shadow-xl shadow-green-500/30 group-hover:scale-110 transition-transform">
                                            3
                                        </div>
                                        <div class="absolute -inset-4 rounded-full bg-green-500/10 -z-10 animate-pulse animation-delay-4000"></div>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold mb-3 font-display">Få din rapport</h3>
                                    <p class="text-muted-foreground">
                                        Se hvordan din løn sammenligner sig med andre i din branche.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- CTA Section -->
            <section id="cta" class="py-24 md:py-32 relative overflow-hidden">
                <!-- Gradient background -->
                <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary to-orange-600 -z-10"></div>
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4xIj48cGF0aCBkPSJNMzYgMzRoLTJ2LTRoMnYtMmg0djJoMnY0aC0ydjJoLTR2LTJ6bTAtOGgtMnYtMmgydjJ6Ii8+PC9nPjwvZz48L3N2Zz4=')] opacity-30 -z-10"></div>
                
                <div class="container mx-auto px-4 md:px-6">
                    <div class="mx-auto max-w-4xl">
                        <div class="text-center text-white">
                            <!-- Emoji decoration -->
                            <div class="flex justify-center gap-4 mb-8">
                                <span class="text-4xl animate-bounce animation-delay-100">💰</span>
                                <span class="text-4xl animate-bounce animation-delay-200">📊</span>
                                <span class="text-4xl animate-bounce animation-delay-300">🚀</span>
                            </div>
                            
                            <h2 class="text-4xl md:text-6xl font-bold tracking-tight mb-6 font-display">
                                Klar til at få indsigt i din løn?
                            </h2>
                            <p class="text-xl opacity-90 mb-10 max-w-2xl mx-auto">
                                Kom i gang gratis i dag. Ingen skjulte gebyrer, ingen binding – bare reel værdi.
                            </p>
                            
                            <div class="flex flex-col items-center gap-8">
                                <div class="flex flex-wrap justify-center gap-6">
                                    <div class="flex items-center gap-2 text-white/90">
                                        <CheckCircle2 class="size-5" />
                                        <span>100% gratis</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-white/90">
                                        <CheckCircle2 class="size-5" />
                                        <span>Ingen kreditkort</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-white/90">
                                        <CheckCircle2 class="size-5" />
                                        <span>Anonym analyse</span>
                                    </div>
                                </div>
                                
                                <Button as-child size="lg" variant="secondary" class="text-base px-10 h-14 text-primary font-semibold shadow-2xl hover:scale-105 transition-transform">
                                    <Link :href="getStarted()">
                                        Kom i gang gratis
                                        <ArrowRight class="size-5 ml-2" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        
        <!-- Footer -->
        <footer class="border-t border-border bg-card py-16">
            <div class="container mx-auto px-4 md:px-6">
                <div class="grid md:grid-cols-4 gap-12 mb-12">
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/80 shadow-lg shadow-primary/25">
                                <AppLogoIcon class="size-5 text-primary-foreground" />
                            </div>
                            <span class="text-xl font-bold font-display">
                                LønRadar
                            </span>
                        </div>
                        <p class="text-muted-foreground max-w-xs">
                            Få indsigt i det danske lønmarked. Anonym, sikker og gratis lønstatistik baseret på rigtige data.
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-4 font-display">Navigation</h4>
                        <nav class="flex flex-col gap-3 text-sm text-muted-foreground">
                            <a href="#features" class="hover:text-foreground transition-colors">Funktioner</a>
                            <a href="#how-it-works" class="hover:text-foreground transition-colors">Sådan virker det</a>
                        </nav>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-4 font-display">Juridisk</h4>
                        <nav class="flex flex-col gap-3 text-sm text-muted-foreground">
                            <a href="#" class="hover:text-foreground transition-colors">Privatlivspolitik</a>
                            <a href="#" class="hover:text-foreground transition-colors">Vilkår og betingelser</a>
                            <a href="#" class="hover:text-foreground transition-colors">Cookie politik</a>
                        </nav>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 pt-8 border-t border-border">
                    <p class="text-sm text-muted-foreground">
                        © {{ new Date().getFullYear() }} LønRadar. Alle rettigheder forbeholdes.
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Lavet med ❤️ i Danmark
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Custom fonts */
.font-display {
    font-family: 'Space Grotesk', sans-serif;
}

body {
    font-family: 'DM Sans', sans-serif;
}

/* Animations */
@keyframes blob {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(20px, -30px) scale(1.1);
    }
    50% {
        transform: translate(-20px, 20px) scale(0.9);
    }
    75% {
        transform: translate(30px, 10px) scale(1.05);
    }
}

.animate-blob {
    animation: blob 10s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.animate-float {
    animation: float 4s ease-in-out infinite;
}

@keyframes bounce-subtle {
    0%, 100% {
        transform: translateY(-50%) translateY(0);
    }
    50% {
        transform: translateY(-50%) translateY(-4px);
    }
}

.animate-bounce-subtle {
    animation: bounce-subtle 2s ease-in-out infinite;
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

@keyframes marquee {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

.animate-marquee {
    animation: marquee 20s linear infinite;
}

/* Animation delays */
.animation-delay-100 {
    animation-delay: 100ms;
}

.animation-delay-200 {
    animation-delay: 200ms;
}

.animation-delay-300 {
    animation-delay: 300ms;
}

.animation-delay-400 {
    animation-delay: 400ms;
}

.animation-delay-2000 {
    animation-delay: 2000ms;
}

.animation-delay-4000 {
    animation-delay: 4000ms;
}
</style>
