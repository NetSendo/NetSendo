<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    settings: Object,
});

// Form for saving settings
const form = useForm({
    store_url: props.settings?.store_url || '',
    consumer_key: '',
    consumer_secret: '',
});

// Test connection state
const testingConnection = ref(false);
const testResult = ref(null);

// Whether we have existing credentials
const hasCredentials = computed(() => props.settings?.consumer_key);

// Test connection
const testConnection = async () => {
    if (!form.store_url || !form.consumer_key || !form.consumer_secret) {
        testResult.value = { success: false, error: t('settings.woocommerce.fill_all_fields') };
        return;
    }

    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route('settings.woocommerce.test'), {
            store_url: form.store_url,
            consumer_key: form.consumer_key,
            consumer_secret: form.consumer_secret,
        });

        testResult.value = response.data;
    } catch (error) {
        testResult.value = {
            success: false,
            error: error.response?.data?.message || error.message,
        };
    } finally {
        testingConnection.value = false;
    }
};

// Save settings
const saveSettings = () => {
    form.post(route('settings.woocommerce.store'), {
        preserveScroll: true,
    });
};

// Disconnect
const disconnect = () => {
    if (confirm(t('settings.woocommerce.confirm_disconnect'))) {
        form.post(route('settings.woocommerce.disconnect'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="$t('settings.woocommerce.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/30">
                            <svg viewBox="0 0 24 24" fill="#96588a" class="h-8 w-8">
                                <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407zM1.533 5.72h20.934c.847 0 1.533.686 1.533 1.532v.47a.407.407 0 0 1-.406.406h-5.01a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H9.767a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 1 0 10.496V7.252c0-.847.686-1.533 1.533-1.533z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                                {{ $t('settings.woocommerce.title') }}
                            </h1>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $t('settings.woocommerce.subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Current Connection Status -->
                <div v-if="settings?.is_connected" class="mb-8 rounded-2xl bg-emerald-50 p-6 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:ring-emerald-800">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-800">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-emerald-800 dark:text-emerald-300">
                                {{ $t('settings.woocommerce.connected') }}
                            </h3>
                            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                                {{ settings.store_url }}
                            </p>
                            <div v-if="settings.store_info" class="mt-2 flex flex-wrap gap-3 text-xs text-emerald-600 dark:text-emerald-400">
                                <span v-if="settings.store_info.currency">{{ $t('settings.woocommerce.currency') }}: {{ settings.store_info.currency }}</span>
                                <span v-if="settings.store_info.wc_version">WooCommerce: {{ settings.store_info.wc_version }}</span>
                            </div>
                            <p v-if="settings.connection_verified_at" class="mt-2 text-xs text-emerald-600 dark:text-emerald-500">
                                {{ $t('settings.woocommerce.verified_at') }}: {{ settings.connection_verified_at }}
                            </p>
                        </div>
                        <button
                            @click="disconnect"
                            class="rounded-lg bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
                        >
                            {{ $t('settings.woocommerce.disconnect') }}
                        </button>
                    </div>
                </div>

                <!-- Connection Form -->
                <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200/50 dark:bg-slate-800 dark:ring-slate-700/50">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-6">
                        {{ settings?.is_connected ? $t('settings.woocommerce.update_credentials') : $t('settings.woocommerce.connect') }}
                    </h2>

                    <form @submit.prevent="saveSettings" class="space-y-6">
                        <!-- Store URL -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ $t('settings.woocommerce.store_url') }}
                            </label>
                            <input
                                v-model="form.store_url"
                                type="url"
                                required
                                :placeholder="$t('settings.woocommerce.store_url_placeholder')"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                            />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('settings.woocommerce.store_url_help') }}
                            </p>
                        </div>

                        <!-- Consumer Key -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ $t('settings.woocommerce.consumer_key') }}
                            </label>
                            <input
                                v-model="form.consumer_key"
                                type="text"
                                required
                                :placeholder="hasCredentials ? settings.consumer_key : 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white font-mono"
                            />
                        </div>

                        <!-- Consumer Secret -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                {{ $t('settings.woocommerce.consumer_secret') }}
                            </label>
                            <input
                                v-model="form.consumer_secret"
                                type="password"
                                required
                                placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white font-mono"
                            />
                        </div>

                        <!-- Help text -->
                        <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-900/20">
                            <div class="flex gap-3">
                                <svg class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="text-sm text-amber-700 dark:text-amber-300">
                                    <p class="font-medium mb-1">{{ $t('settings.woocommerce.api_keys_title') }}</p>
                                    <p>{{ $t('settings.woocommerce.api_keys_help') }}</p>
                                    <p class="mt-2 font-mono text-xs bg-amber-100 dark:bg-amber-900/30 px-2 py-1 rounded inline-block">
                                        WooCommerce → Settings → Advanced → REST API
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Test Result -->
                        <div v-if="testResult" :class="[
                            'rounded-xl p-4',
                            testResult.success
                                ? 'bg-emerald-50 dark:bg-emerald-900/20'
                                : 'bg-red-50 dark:bg-red-900/20'
                        ]">
                            <div class="flex items-start gap-3">
                                <svg v-if="testResult.success" class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-else class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p :class="testResult.success ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'" class="font-medium">
                                        {{ testResult.success ? $t('settings.woocommerce.connection_success') : $t('settings.woocommerce.connection_failed') }}
                                    </p>
                                    <p v-if="testResult.error" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ testResult.error }}
                                    </p>
                                    <div v-if="testResult.store_info" class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">
                                        <span v-if="testResult.store_info.currency">{{ $t('settings.woocommerce.currency') }}: {{ testResult.store_info.currency }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3 pt-4">
                            <button
                                type="button"
                                @click="testConnection"
                                :disabled="testingConnection || !form.store_url || !form.consumer_key || !form.consumer_secret"
                                class="inline-flex items-center gap-2 rounded-xl border border-purple-200 bg-white px-5 py-2.5 text-sm font-medium text-purple-700 transition-colors hover:bg-purple-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-purple-700 dark:bg-transparent dark:text-purple-400 dark:hover:bg-purple-900/20"
                            >
                                <svg v-if="testingConnection" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                {{ testingConnection ? $t('settings.woocommerce.testing') : $t('settings.woocommerce.test_connection') }}
                            </button>

                            <button
                                type="submit"
                                :disabled="form.processing || !form.store_url || !form.consumer_key || !form.consumer_secret"
                                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ form.processing ? $t('common.saving') : $t('settings.woocommerce.save_settings') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Features Info -->
                <div class="mt-8 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50 p-8 dark:from-purple-900/20 dark:to-pink-900/20">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                        {{ $t('settings.woocommerce.features_title') }}
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">📦</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_import') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_import_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">👁️</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_recently_viewed') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_recently_viewed_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">📧</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_templates') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_templates_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">🔄</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_sync') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_sync_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
