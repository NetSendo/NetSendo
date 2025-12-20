<script setup>
import SetupLayout from '@/Layouts/SetupLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const form = useForm({
    license_key: '',
});

// Format license key as user types (XXXX-XXXX-XXXX-XXXX)
const displayKey = ref('');
const inputRef = ref(null);

const formatLicenseKey = (value) => {
    // Remove all non-alphanumeric characters
    const cleaned = value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    // Split into groups of 4
    const groups = cleaned.match(/.{1,4}/g) || [];
    return groups.slice(0, 4).join('-');
};

const handleInput = (event) => {
    const formatted = formatLicenseKey(event.target.value);
    displayKey.value = formatted;
    form.license_key = formatted.replace(/-/g, '');
};

const isValidLength = computed(() => form.license_key.length >= 10);

const submit = () => {
    if (!isValidLength.value) return;
    
    form.post(route('setup.store'), {
        onFinish: () => {
            if (form.hasErrors) {
                // Keep the formatted display on error
            } else {
                form.reset();
                displayKey.value = '';
            }
        },
    });
};
</script>

<template>
    <SetupLayout>
        <Head :title="$t('setup.title')" />

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-indigo-600 mb-4 shadow-lg shadow-purple-500/25">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">
                {{ $t('setup.welcome') }}
            </h1>
            <p class="text-white/60 text-sm">
                {{ $t('setup.intro') }}
            </p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-6">
            <!-- License Key Input -->
            <div>
                <label for="license_key" class="block text-sm font-medium text-white/80 mb-2">
                    {{ $t('setup.license_label') }}
                </label>
                
                <div class="relative">
                    <input
                        ref="inputRef"
                        id="license_key"
                        type="text"
                        :value="displayKey"
                        @input="handleInput"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200 text-center font-mono text-lg tracking-widest"
                        :placeholder="$t('setup.license_placeholder')"
                        maxlength="19"
                        autocomplete="off"
                        autofocus
                    />
                    
                    <!-- Validation indicator -->
                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                        <transition name="fade" mode="out-in">
                            <div v-if="isValidLength" class="w-6 h-6 rounded-full bg-green-500/20 flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div v-else-if="form.license_key.length > 0" class="w-6 h-6 rounded-full bg-yellow-500/20 flex items-center justify-center">
                                <span class="text-yellow-400 text-xs font-bold">{{ 10 - form.license_key.length }}</span>
                            </div>
                        </transition>
                    </div>
                </div>

                <!-- Error message -->
                <transition name="slide-fade">
                    <p v-if="form.errors.license_key" class="mt-2 text-sm text-red-400 flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        {{ form.errors.license_key }}
                    </p>
                </transition>

                <!-- Helper text -->
                <p class="mt-2 text-xs text-white/40">
                    {{ $t('setup.license_help') }}
                </p>
            </div>

            <!-- Submit Button -->
            <button
                type="submit"
                :disabled="form.processing || !isValidLength"
                class="w-full relative group"
            >
                <div 
                    class="absolute -inset-0.5 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl blur opacity-60 group-hover:opacity-100 transition duration-300"
                    :class="{ 'opacity-30': !isValidLength || form.processing }"
                ></div>
                <div 
                    class="relative flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl text-white font-semibold transition-all duration-200"
                    :class="{ 
                        'opacity-50 cursor-not-allowed': !isValidLength || form.processing,
                        'hover:shadow-lg hover:shadow-purple-500/25': isValidLength && !form.processing
                    }"
                >
                    <!-- Loading spinner -->
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    
                    <!-- Button text -->
                    <span v-if="form.processing">{{ $t('setup.verifying') }}</span>
                    <span v-else>{{ $t('setup.activate_button') }}</span>
                    
                    <!-- Arrow icon -->
                    <svg v-if="!form.processing" class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </div>
            </button>
        </form>

        <!-- Features hint -->
        <div class="mt-8 pt-6 border-t border-white/10">
            <p class="text-center text-white/40 text-xs mb-4">{{ $t('setup.features_title') }}</p>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="flex flex-col items-center gap-1">
                    <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <span class="text-white/60 text-xs">{{ $t('setup.features.campaigns') }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <span class="text-white/60 text-xs">{{ $t('setup.features.subscribers') }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="text-white/60 text-xs">{{ $t('setup.features.stats') }}</span>
                </div>
            </div>
        </div>
    </SetupLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
    transition: all 0.2s ease-in;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateY(-10px);
    opacity: 0;
}
</style>
