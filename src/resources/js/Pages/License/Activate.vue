<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    licenseActive: Boolean,
    licensePlan: String,
    licenseKey: String,
    licenseExpiresAt: String,
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

// Form for manual license activation
const activateForm = useForm({
    license_key: '',
});

// Plan cards data
const planCards = computed(() => [
    {
        key: 'SILVER',
        name: 'SILVER',
        price: 'DARMOWA',
        priceSuffix: 'DOŻYWOTNIA',
        color: 'from-slate-400 to-slate-600',
        badgeColor: 'bg-slate-500',
        features: props.plans?.SILVER?.features || [
            'Wszystkie podstawowe funkcje',
            'Nieograniczone kontakty',
            'Szablony email',
            'Wsparcie społeczności',
        ],
        buttonText: 'Wybieram SILVER',
        buttonAction: 'request',
    },
    {
        key: 'GOLD',
        name: 'GOLD',
        price: '$97',
        priceSuffix: '/miesiąc',
        color: 'from-yellow-400 to-amber-600',
        badgeColor: 'bg-gradient-to-r from-yellow-400 to-amber-500',
        popular: true,
        comingSoon: !props.stripeGoldPaymentLink,
        features: props.plans?.GOLD?.features || [
            'Wszystko z SILVER',
            'Zaawansowane automatyzacje',
            'Priorytetowe wsparcie',
            'Dostęp API',
            'White-label',
        ],
        buttonText: props.stripeGoldPaymentLink ? 'Kup GOLD' : 'Wkrótce dostępne',
        buttonAction: 'stripe',
    },
]);

// Request SILVER license
const requestSilverLicense = async () => {
    isLoading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await fetch(route('license.request-silver'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
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
                requestSent.value = true;
                showManualInput.value = true;
                successMessage.value = data.message;
            }
        } else {
            errorMessage.value = data.message || 'Wystąpił błąd. Spróbuj ponownie.';
            showManualInput.value = true;
        }
    } catch (error) {
        errorMessage.value = 'Błąd połączenia. Wpisz klucz licencji ręcznie poniżej.';
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
            successMessage.value = 'Plan GOLD będzie dostępny wkrótce! Na razie możesz korzystać z planu SILVER.';
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
        return new Date(props.licenseExpiresAt).toLocaleDateString('pl-PL', {
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
    <Head title="Licencja NetSendo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Licencja
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
                                <h3 class="text-2xl font-bold">Licencja {{ licensePlan }} aktywna</h3>
                                <p class="mt-1 text-white/80">
                                    <template v-if="licensePlan === 'SILVER'">
                                        Dożywotnia licencja — bez limitu czasowego
                                    </template>
                                    <template v-else>
                                        Ważna do: {{ formattedExpiresAt || 'bezterminowo' }}
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- License details -->
                    <div class="p-6">
                        <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            Wersja aplikacji: {{ appVersion }}
                        </div>
                        
                        <!-- Upgrade to GOLD option for SILVER users -->
                        <div v-if="licensePlan === 'SILVER'" class="mt-6 rounded-xl bg-gradient-to-r from-yellow-50 to-amber-50 p-5 dark:from-yellow-900/20 dark:to-amber-900/20">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Uaktualnij do GOLD</h4>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Odblokuj zaawansowane automatyzacje i priorytetowe wsparcie
                                    </p>
                                </div>
                                <button
                                    v-if="stripeGoldPaymentLink"
                                    @click="window.open(stripeGoldPaymentLink, '_blank')"
                                    class="rounded-lg bg-gradient-to-r from-yellow-400 to-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                                >
                                    Kup GOLD — $97/mc
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
                            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">Licencja GOLD wygasła</h3>
                            <p class="text-red-600 dark:text-red-400">
                                Wygasła: {{ formattedExpiresAt }}. Odnów subskrypcję, aby przywrócić dostęp do funkcji premium.
                            </p>
                        </div>
                    </div>
                    <button
                        v-if="stripeGoldPaymentLink"
                        @click="window.open(stripeGoldPaymentLink, '_blank')"
                        class="mt-4 rounded-lg bg-gradient-to-r from-yellow-400 to-amber-500 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                    >
                        Odnów GOLD — $97/mc
                    </button>
                </div>

                <!-- Not activated - show plans -->
                <div v-if="!licenseActive || isGoldExpired">
                    <!-- Header -->
                    <div class="mb-10 text-center">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            Wybierz swoją licencję
                        </h1>
                        <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                            NetSendo oferuje dwa plany — wybierz ten, który odpowiada Twoim potrzebom
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

                    <!-- Plan cards -->
                    <div class="mb-10 grid gap-8 md:grid-cols-2">
                        <div
                            v-for="plan in planCards"
                            :key="plan.key"
                            class="relative overflow-hidden rounded-2xl bg-white shadow-xl transition-all duration-300 hover:shadow-2xl dark:bg-gray-800"
                        >
                            <!-- Popular badge -->
                            <div v-if="plan.popular" class="absolute -right-10 top-6 rotate-45 bg-gradient-to-r from-yellow-400 to-amber-500 px-12 py-1.5 text-xs font-bold text-white shadow">
                                POPULARNE
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
                                        Przetwarzanie...
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
                                    Aktywuj licencję ręcznie
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Wpisz klucz licencji otrzymany emailem lub po zakupie
                                </p>
                            </div>
                            
                            <form @submit.prevent="submitActivation" class="p-6">
                                <div class="mb-4">
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Klucz licencji
                                    </label>
                                    <textarea
                                        v-model="activateForm.license_key"
                                        required
                                        rows="3"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-3 font-mono text-sm transition-colors focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        placeholder="Wklej swój klucz licencji tutaj..."
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
                                        Aktywowanie...
                                    </span>
                                    <span v-else>Aktywuj licencję</span>
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 text-center">
                            <button 
                                @click="showManualInput = false; requestSent = false; errorMessage = ''"
                                class="text-sm text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-300"
                            >
                                ← Wróć do wyboru planów
                            </button>
                        </div>
                    </div>

                    <!-- Already have a license link -->
                    <div v-if="!showManualInput" class="mt-8 text-center">
                        <button 
                            @click="showManualInput = true"
                            class="text-base text-indigo-600 hover:text-indigo-500 hover:underline dark:text-indigo-400"
                        >
                            Mam już klucz licencji
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
