<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <div class="min-h-screen relative overflow-hidden bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <!-- Animated background elements -->
        <div class="absolute inset-0 overflow-hidden">
            <!-- Floating orbs -->
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500/30 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
            
            <!-- Grid pattern overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:50px_50px]"></div>
        </div>

        <!-- Main content -->
        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <!-- Logo -->
            <div class="mb-8 transform hover:scale-105 transition-transform duration-300">
                <ApplicationLogo class="h-16 w-auto drop-shadow-2xl" />
            </div>

            <!-- Glassmorphism card -->
            <div class="w-full max-w-md">
                <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-8 relative overflow-hidden">
                    <!-- Subtle gradient overlay on card -->
                    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent pointer-events-none"></div>
                    
                    <!-- Card content -->
                    <div class="relative z-10">
                        <slot />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-white/40 text-sm">
                    NetSendo v{{ $page.props.appVersion || '1.0.0' }} &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(5deg);
    }
}

.animate-float {
    animation: float 6s ease-in-out infinite;
}
</style>
