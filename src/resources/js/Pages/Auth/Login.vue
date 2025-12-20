<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FirstRunModal from '@/Components/FirstRunModal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    isFirstUser: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.login')" />

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 mb-4 shadow-lg shadow-purple-500/25">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">{{ $t('auth.welcome_back') }}</h1>
            <p class="text-white/60 text-sm">{{ $t('auth.login_to_account') }}</p>
        </div>

        <!-- Status message -->
        <div v-if="status" class="mb-4 rounded-lg bg-green-500/20 border border-green-500/30 p-3 text-sm text-green-300">
            {{ status }}
        </div>

        <!-- Flash error message (e.g., registration blocked) -->
        <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg bg-amber-500/20 border border-amber-500/30 p-3 text-sm text-amber-300 flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $page.props.flash.error }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-white/80 mb-2">
                    {{ $t('auth.email') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                        </svg>
                    </div>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        placeholder="your@email.com"
                    />
                </div>
                <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-white/80 mb-2">
                    {{ $t('auth.password') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        placeholder="••••••••"
                    />
                </div>
                <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Remember me & Forgot password -->
            <div class="flex items-center justify-between">
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="form.remember"
                        class="w-4 h-4 rounded border-white/30 bg-white/10 text-purple-600 focus:ring-purple-500/50 focus:ring-offset-0"
                    />
                    <span class="ml-2 text-sm text-white/60">{{ $t('auth.remember_me') }}</span>
                </label>
                
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-purple-400 hover:text-purple-300 transition-colors"
                >
                    {{ $t('auth.forgot_password_link') }}
                </Link>
            </div>

            <!-- Submit button -->
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full relative group"
            >
                <div 
                    class="absolute -inset-0.5 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl blur opacity-60 group-hover:opacity-100 transition duration-300"
                    :class="{ 'opacity-30': form.processing }"
                ></div>
                <div 
                    class="relative flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl text-white font-semibold transition-all duration-200"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                >
                    <svg v-if="form.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span v-if="form.processing">{{ $t('common.loading') }}</span>
                    <span v-else>{{ $t('auth.login') }}</span>
                </div>
            </button>
        </form>

        <!-- Register link -->
        <div class="mt-6 pt-6 border-t border-white/10 text-center">
            <p class="text-white/50 text-sm">
                {{ $t('auth.no_account') }}
                <Link :href="route('register')" class="text-purple-400 hover:text-purple-300 font-medium transition-colors">
                    {{ $t('welcome.register') }}
                </Link>
            </p>
        </div>
        <!-- First Run Modal -->
        <FirstRunModal :show="isFirstUser" />
    </GuestLayout>
</template>

