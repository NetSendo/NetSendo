<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    qrCodeSvg: String,
    secret: String,
});

const form = useForm({
    code: '',
});

const secretCopied = ref(false);

const copySecret = () => {
    navigator.clipboard.writeText(props.secret);
    secretCopied.value = true;
    setTimeout(() => {
        secretCopied.value = false;
    }, 2000);
};

const submit = () => {
    form.post(route('profile.2fa.confirm'));
};
</script>

<template>
    <Head :title="$t('profile.two_factor.setup_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('profile.2fa.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('profile.two_factor.setup_title') }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm rounded-xl dark:bg-gray-800">
                    <div class="p-6">
                        <!-- Steps indicator -->
                        <div class="flex items-center justify-center gap-4 mb-8">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-purple-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('profile.two_factor.step_scan') }}</span>
                            </div>
                            <div class="w-12 h-0.5 bg-gray-300 dark:bg-gray-600"></div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 flex items-center justify-center text-sm font-bold">2</div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('profile.two_factor.step_verify') }}</span>
                            </div>
                        </div>

                        <!-- QR Code -->
                        <div class="text-center mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('profile.two_factor.scan_qr_title') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                {{ $t('profile.two_factor.scan_qr_subtitle') }}
                            </p>
                            
                            <div class="inline-block p-4 bg-white rounded-xl shadow-lg border border-gray-200">
                                <div v-html="qrCodeSvg" class="w-48 h-48 mx-auto"></div>
                            </div>
                        </div>

                        <!-- Manual entry -->
                        <div class="mb-8 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                                {{ $t('profile.two_factor.manual_entry_intro') }}
                            </p>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 px-3 py-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-600 text-sm font-mono text-gray-900 dark:text-gray-100">
                                    {{ secret }}
                                </code>
                                <button
                                    @click="copySecret"
                                    type="button"
                                    class="px-3 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-600 rounded transition-colors"
                                >
                                    <svg v-if="!secretCopied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Verification form -->
                        <form @submit.prevent="submit">
                            <div class="mb-6">
                                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('profile.two_factor.manual_entry_label') }}
                                </label>
                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="6"
                                    class="w-full px-4 py-3 text-center text-2xl font-mono tracking-[0.5em] border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    placeholder="000000"
                                    autocomplete="one-time-code"
                                />
                                <p v-if="form.errors.code" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.code }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between">
                                <Link
                                    :href="route('profile.2fa.index')"
                                    class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                >
                                    {{ $t('profile.two_factor.cancel_button') }}
                                </Link>
                                <button
                                    type="submit"
                                    :disabled="form.processing || form.code.length !== 6"
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="form.processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ $t('profile.two_factor.confirm_button') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
