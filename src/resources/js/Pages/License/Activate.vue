<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    licenseActive: Boolean,
    licensePlan: String,
    licenseKey: String,
    licenseExpiresAt: String,
    licenseEmail: String,
    licenseDomain: String,
    appVersion: String,
    plans: Object,
    stripeGoldPaymentLink: String,
});

const page = usePage();

// State
const isLoading = ref(false);
const showManualInput = ref(false);
const requestSent = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const showLicenseKey = ref(false);

// Polling state
const isPolling = ref(false);
const pollingProgress = ref(0);
const pollingMessage = ref('');
let pollingInterval = null;
let pollingTimeout = null;

// Poll for license activation (called after requesting SILVER)
const pollForLicense = () => {
    const maxDuration = 90000; // 90 seconds
    const pollInterval = 5000; // 5 seconds
    const startTime = Date.now();
    
    isPolling.value = true;
    pollingMessage.value = t('license.waiting_for_activation');
    
    const checkLicenseStatus = async () => {
        try {
            const elapsed = Date.now() - startTime;
            pollingProgress.value = Math.min(100, (elapsed / maxDuration) * 100);
            
            const response = await fetch(route('license.status'), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.active) {
                    // License activated! Stop polling and redirect
                    stopPolling();
                    successMessage.value = t('license.auto_activated_success');
                    setTimeout(() => {
                        router.visit(route('dashboard'));
                    }, 1500);
                    return;
                }
            }
            
            // Check if we've exceeded max duration
            if (Date.now() - startTime >= maxDuration) {
                stopPolling();
                pollingMessage.value = '';
                showManualInput.value = true;
                errorMessage.value = t('license.polling_timeout');
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    };
    
    // Start polling
    pollingInterval = setInterval(checkLicenseStatus, pollInterval);
    
    // Set timeout to stop polling after max duration
    pollingTimeout = setTimeout(() => {
        stopPolling();
        showManualInput.value = true;
        errorMessage.value = t('license.polling_timeout');
    }, maxDuration);
    
    // Initial check
    checkLicenseStatus();
};

const stopPolling = () => {
    isPolling.value = false;
    pollingProgress.value = 0;
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    if (pollingTimeout) {
        clearTimeout(pollingTimeout);
        pollingTimeout = null;
    }
};

// Masked license key for display
const maskedLicenseKey = computed(() => {
    if (!props.licenseKey) return '';
    if (showLicenseKey.value) return props.licenseKey;
    // Show first 8 and last 8 characters
    const key = props.licenseKey;
    if (key.length <= 20) return '*'.repeat(key.length);
    return key.substring(0, 8) + '*'.repeat(key.length - 16) + key.substring(key.length - 8);
});

// Form for manual license activation
const activateForm = useForm({
    license_key: '',
});

// Plan cards data
const planCards = computed(() => [
    {
        key: 'SILVER',
        name: t('license.plans.silver.name'),
        price: t('license.plans.silver.price'),
        priceSuffix: t('license.plans.silver.suffix'),
        color: 'from-slate-400 to-slate-600',
        badgeColor: 'bg-slate-500',
        features: props.plans?.SILVER?.features || [
            t('license.plans.silver.features.basic'),
            t('license.plans.silver.features.unlimited'),
            t('license.plans.silver.features.templates'),
            t('license.plans.silver.features.support'),
        ],
        buttonText: t('license.plans.silver.select'),
        buttonAction: 'request',
    },
    {
        key: 'GOLD',
        name: t('license.plans.gold.name'),
        price: t('license.plans.gold.price'),
        priceSuffix: t('license.plans.gold.suffix'),
        color: 'from-yellow-400 to-amber-600',
        badgeColor: 'bg-gradient-to-r from-yellow-400 to-amber-500',
        popular: true,
        coming_soon: !props.stripeGoldPaymentLink,
        features: props.plans?.GOLD?.features || [
            t('license.plans.gold.features.everything'),
            t('license.plans.gold.features.automations'),
            t('license.plans.gold.features.priority'),
            t('license.plans.gold.features.api'),
            t('license.plans.gold.features.white_label'),
        ],
        buttonText: props.stripeGoldPaymentLink ? t('license.plans.gold.select') : t('license.coming_soon'),
        buttonAction: 'stripe',
    },
]);

// Request SILVER license
const requestSilverLicense = async () => {
    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            errorMessage.value = t('license.request_error');
            showManualInput.value = true;
            isLoading.value = false;
            return;
        }

        const response = await fetch(route('license.request-silver'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: page.props.auth.user?.email || '',
            }),
        });

        const data = await response.json();

        if (data.success) {
            if (data.auto_activated) {
                successMessage.value = data.message;
                // Redirect to dashboard after a short delay
                setTimeout(() => {
                    router.visit(route('dashboard'));
                }, 1500);
            } else {
                // License will be sent via webhook - start polling
                requestSent.value = true;
                successMessage.value = t('license.request_sent_polling');
                pollForLicense();
            }
        } else {
            errorMessage.value = data.message || t('license.generic_error');
            showManualInput.value = true;
        }
    } catch (error) {
        errorMessage.value = t('license.request_error');
        showManualInput.value = true;
    } finally {
        isLoading.value = false;
    }
};

// Handle plan button click
const handlePlanAction = (plan) => {
    if (plan.buttonAction === 'request') {
        requestSilverLicense();
    } else if (plan.buttonAction === 'stripe') {
        if (props.stripeGoldPaymentLink) {
            window.open(props.stripeGoldPaymentLink, '_blank');
            // Show manual input for entering license after purchase
            showManualInput.value = true;
        } else {
            // Coming soon - show message
            successMessage.value = t('license.gold_coming_soon_msg');
        }
    }
};

// Submit manual license activation
const submitActivation = () => {
    errorMessage.value = '';
    activateForm.post(route('license.activate'), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.license_key) {
                errorMessage.value = errors.license_key;
            }
        },
    });
};

// Format expiration date
const formattedExpiresAt = computed(() => {
    if (!props.licenseExpiresAt) return null;
    try {
        return new Date(props.licenseExpiresAt).toLocaleDateString(locale.value === 'pl' ? 'pl-PL' : 'en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return props.licenseExpiresAt;
    }
});

// Check if GOLD license is expired
const isGoldExpired = computed(() => {
    if (props.licensePlan !== 'GOLD' || !props.licenseExpiresAt) return false;
    return new Date() > new Date(props.licenseExpiresAt);
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $t('license.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                
                <!-- Already activated - Active License Status -->
                <div v-if="licenseActive && !isGoldExpired" class="mb-8 overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                    <!-- Header with gradient -->
                    <div :class="[
                        'px-6 py-8 text-white',
                        licensePlan === 'GOLD' 
                            ? 'bg-gradient-to-r from-yellow-400 via-amber-500 to-orange-500' 
                            : 'bg-gradient-to-r from-slate-500 to-slate-700'
                    ]">
                        <div class="flex items-center gap-5">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold">{{ $t('license.active_status', { plan: licensePlan }) }}</h3>
                                <p class="mt-1 text-white/80">
                                    <template v-if="licensePlan === 'SILVER'">
                                        {{ $t('license.lifetime_license') }}
                                    </template>
                                    <template v-else>
                                        {{ $t('license.expires_at', { date: formattedExpiresAt || $t('license.expires_never') }) }}
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- License details -->
                    <div class="p-6">
                        <div class="mb-6 space-y-4">
                            <!-- License Key -->
                            <div v-if="licenseKey" class="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('license.license_key_label') }}</span>
                                    <button
                                        @click="showLicenseKey = !showLicenseKey"
                                        class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 flex items-center gap-1"
                                    >
                                        <svg v-if="!showLicenseKey" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                        {{ showLicenseKey ? $t('license.hide') : $t('license.show') }}
                                    </button>
                                </div>
                                <code class="block text-sm text-gray-700 dark:text-gray-300 font-mono break-all">{{ maskedLicenseKey }}</code>
                            </div>
                            
                            <!-- Domain -->
                            <div v-if="licenseDomain" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ $t('license.domain') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ licenseDomain }}</span>
                            </div>
                            
                            <!-- Email -->
                            <div v-if="licenseEmail" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ $t('license.email') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ licenseEmail }}</span>
                            </div>
                        </div>
                        
                        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('license.app_version', { version: appVersion }) }}
                        </div>
                        
                        <!-- Upgrade to GOLD option for SILVER users -->
                        <div v-if="licensePlan === 'SILVER'" class="mt-6 rounded-xl bg-gradient-to-r from-yellow-50 to-amber-50 p-5 dark:from-yellow-900/20 dark:to-amber-900/20">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">{{ $t('license.upgrade_to_gold') }}</h4>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $t('license.upgrade_desc') }}
                                    </p>
                                </div>
                                <button
                                    v-if="stripeGoldPaymentLink"
                                    @click="window.open(stripeGoldPaymentLink, '_blank')"
                                    class="rounded-lg bg-gradient-to-r from-yellow-400 to-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                                >
                                    {{ $t('license.buy_gold') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GOLD Expired Warning -->
                <div v-if="isGoldExpired" class="mb-8 rounded-xl bg-red-50 p-6 dark:bg-red-900/20">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-800">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">{{ $t('license.gold_expired') }}</h3>
                            <p class="text-red-600 dark:text-red-400">
                                {{ $t('license.gold_expired_desc', { date: formattedExpiresAt }) }}
                            </p>
                        </div>
                    </div>
                    <button
                        v-if="stripeGoldPaymentLink"
                        @click="window.open(stripeGoldPaymentLink, '_blank')"
                        class="mt-4 rounded-lg bg-gradient-to-r from-yellow-400 to-amber-500 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                    >
                        {{ $t('license.renew_gold') }}
                    </button>
                </div>

                <!-- Not activated - show plans -->
                <div v-if="!licenseActive || isGoldExpired">
                    <!-- Header -->
                    <div class="mb-10 text-center">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ $t('license.choose_plan') }}
                        </h1>
                        <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                            {{ $t('license.choose_plan_desc') }}
                        </p>
                    </div>

                    <!-- Messages -->
                    <div v-if="successMessage" class="mb-6 rounded-lg bg-green-50 p-4 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ successMessage }}
                        </div>
                    </div>

                    <div v-if="errorMessage" class="mb-6 rounded-lg bg-red-50 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            {{ errorMessage }}
                        </div>
                    </div>

                    <!-- Polling Progress -->
                    <div v-if="isPolling" class="mb-8 mx-auto max-w-lg">
                        <div class="overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800 p-6">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                    <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ $t('license.activating_license') }}
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ pollingMessage || $t('license.waiting_for_activation') }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Progress bar -->
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div 
                                    class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2.5 rounded-full transition-all duration-500"
                                    :style="{ width: pollingProgress + '%' }"
                                ></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center">
                                {{ $t('license.polling_hint') }}
                            </p>
                            
                            <!-- Cancel button -->
                            <button 
                                @click="stopPolling(); showManualInput = true;"
                                class="mt-4 w-full text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                            >
                                {{ $t('license.enter_manually') }}
                            </button>
                        </div>
                    </div>

                    <!-- Plan cards -->
                    <div class="mb-10 grid gap-8 md:grid-cols-2">
                        <div
                            v-for="plan in planCards"
                            :key="plan.key"
                            class="relative overflow-hidden rounded-2xl bg-white shadow-xl transition-all duration-300 hover:shadow-2xl dark:bg-gray-800"
                        >
                            <!-- Popular badge -->
                            <div v-if="plan.popular" class="absolute -right-10 top-6 rotate-45 bg-gradient-to-r from-yellow-400 to-amber-500 px-12 py-1.5 text-xs font-bold text-white shadow">
                                {{ $t('license.popular') }}
                            </div>

                            <!-- Card header -->
                            <div :class="['bg-gradient-to-r p-6 text-white', plan.color]">
                                <div class="mb-2 inline-block rounded-full bg-white/20 px-4 py-1 text-sm font-bold backdrop-blur-sm">
                                    {{ plan.name }}
                                </div>
                                <div class="mt-4 flex items-baseline gap-1">
                                    <span class="text-4xl font-bold">{{ plan.price }}</span>
                                    <span class="text-lg opacity-80">{{ plan.priceSuffix }}</span>
                                </div>
                            </div>

                            <!-- Features list -->
                            <div class="p-6">
                                <ul class="mb-8 space-y-4">
                                    <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-3">
                                        <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                            <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <span class="text-gray-700 dark:text-gray-300">{{ feature }}</span>
                                    </li>
                                </ul>

                                <button
                                    @click="handlePlanAction(plan)"
                                    :disabled="isLoading"
                                    :class="[
                                        'w-full rounded-xl py-4 text-lg font-semibold text-white shadow-lg transition-all hover:shadow-xl disabled:opacity-50',
                                        plan.key === 'GOLD' 
                                            ? 'bg-gradient-to-r from-yellow-400 to-amber-500 hover:from-yellow-500 hover:to-amber-600' 
                                            : 'bg-gradient-to-r from-slate-500 to-slate-700 hover:from-slate-600 hover:to-slate-800'
                                    ]"
                                >
                                    <span v-if="isLoading && plan.key === 'SILVER'" class="flex items-center justify-center gap-2">
                                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $t('license.processing') }}
                                    </span>
                                    <span v-else>{{ plan.buttonText }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Manual license input -->
                    <div v-if="showManualInput || requestSent" class="mx-auto max-w-lg">
                        <div class="overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
                            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $t('license.manual_activation_title') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('license.manual_activation_desc') }}
                                </p>
                            </div>
                            
                            <form @submit.prevent="submitActivation" class="p-6">
                                <div class="mb-4">
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $t('license.license_key_label') }}
                                    </label>
                                    <textarea
                                        v-model="activateForm.license_key"
                                        required
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 font-mono text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        :placeholder="$t('license.license_key_placeholder')"
                                    ></textarea>
                                    <p v-if="activateForm.errors.license_key" class="mt-2 text-sm text-red-500">
                                        {{ activateForm.errors.license_key }}
                                    </p>
                                </div>
                                <button
                                    type="submit"
                                    :disabled="activateForm.processing || !activateForm.license_key"
                                    class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl disabled:opacity-50"
                                >
                                    <span v-if="activateForm.processing" class="flex items-center justify-center gap-2">
                                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $t('license.activating') }}
                                    </span>
                                    <span v-else>{{ $t('license.activate_button') }}</span>
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 text-center">
                            <button 
                                @click="showManualInput = false; requestSent = false; errorMessage = ''"
                                class="text-sm text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-300"
                            >
                                ← {{ $t('license.back_to_plans') }}
                            </button>
                        </div>
                    </div>

                    <!-- Already have a license link -->
                    <div v-if="!showManualInput" class="mt-8 text-center">
                        <button 
                            @click="showManualInput = true"
                            class="text-base text-indigo-600 hover:text-indigo-500 hover:underline dark:text-indigo-400"
                        >
                            {{ $t('license.already_have_key') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
