<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    code: '',
});

const submit = () => {
    form.post(route('2fa.verify'), {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.two_factor_verification')" />

        <!-- Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 mb-4 shadow-lg shadow-purple-500/25">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">{{ t('auth.two_factor_verification') }}</h1>
            <p class="text-white/60 text-sm">{{ t('auth.enter_code_from_app') }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Code -->
            <div>
                <label for="code" class="block text-sm font-medium text-white/80 mb-2">
                    {{ t('auth.code') }}
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </div>
                    <input
                        id="code"
                        type="text"
                        inputmode="numeric"
                        v-model="form.code"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200 text-center tracking-[0.5em] font-mono text-lg"
                        placeholder="000000"
                    />
                </div>
                <p v-if="form.errors.code" class="mt-2 text-sm text-red-400">
                    {{ form.errors.code }}
                </p>
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
                    <span v-if="form.processing">{{ t('auth.verifying') }}...</span>
                    <span v-else>{{ t('auth.confirm') }}</span>
                </div>
            </button>
        </form>
    </GuestLayout>
</template>
