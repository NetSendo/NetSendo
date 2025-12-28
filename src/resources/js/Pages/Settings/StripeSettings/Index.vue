<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    settings: Object
});

const { t } = useI18n();
const page = usePage();

const form = useForm({
    publishable_key: props.settings?.publishable_key || '',
    secret_key: '',
    webhook_secret: '',
});

const showSecretKey = ref(false);
const showWebhookSecret = ref(false);
const testingConnection = ref(false);
const testResult = ref(null);

const isConfigured = computed(() => props.settings?.is_configured);

const submit = () => {
    form.post(route('settings.stripe.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('secret_key', 'webhook_secret');
        }
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route('settings.stripe.test-connection'));
        testResult.value = {
            success: true,
            message: response.data.message
        };
    } catch (error) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || t('stripe.connection_error')
        };
    } finally {
        testingConnection.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout :title="t('stripe.settings_title')">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('stripe.settings_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ t('stripe.settings_subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <!-- Status Card -->
                <div class="mb-6 overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-6">
                        <div class="flex items-center space-x-3">
                            <div :class="[
                                'flex-shrink-0 w-3 h-3 rounded-full',
                                isConfigured ? 'bg-green-500' : 'bg-yellow-500'
                            ]"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ isConfigured ? t('stripe.status_configured') : t('stripe.status_not_configured') }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ isConfigured ? t('stripe.status_ready') : t('stripe.status_add_keys') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Form -->
                <div class="overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Publishable Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('stripe.publishable_key') }}
                            </label>
                            <input
                                type="text"
                                v-model="form.publishable_key"
                                :placeholder="t('stripe.publishable_key_placeholder')"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('stripe.publishable_key_help') }}
                            </p>
                        </div>

                        <!-- Secret Key -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('stripe.secret_key') }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    :type="showSecretKey ? 'text' : 'password'"
                                    v-model="form.secret_key"
                                    :placeholder="settings?.secret_key_masked || t('stripe.secret_key_placeholder')"
                                    class="block w-full pr-10 border-gray-300 rounded-md shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <button
                                    type="button"
                                    @click="showSecretKey = !showSecretKey"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3"
                                >
                                    <svg v-if="showSecretKey" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('stripe.secret_key_help') }}
                            </p>
                        </div>

                        <!-- Webhook Secret -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('stripe.webhook_secret') }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    :type="showWebhookSecret ? 'text' : 'password'"
                                    v-model="form.webhook_secret"
                                    :placeholder="settings?.webhook_secret_masked || t('stripe.webhook_secret_placeholder')"
                                    class="block w-full pr-10 border-gray-300 rounded-md shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <button
                                    type="button"
                                    @click="showWebhookSecret = !showWebhookSecret"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3"
                                >
                                    <svg v-if="showWebhookSecret" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('stripe.webhook_secret_help') }}
                            </p>
                        </div>

                        <!-- Test Result -->
                        <div v-if="testResult" :class="[
                            'p-4 rounded-md',
                            testResult.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'
                        ]">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg v-if="testResult.success" class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p :class="[
                                        'text-sm font-medium',
                                        testResult.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'
                                    ]">
                                        {{ testResult.message }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-600">
                            <button
                                type="button"
                                @click="testConnection"
                                :disabled="testingConnection || !isConfigured"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg v-if="testingConnection" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ t('stripe.test_connection') }}
                            </button>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ form.processing ? t('common.saving') : t('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Help Card -->
                <div class="p-6 mt-6 bg-blue-50 rounded-lg dark:bg-blue-900/20">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        {{ t('stripe.help_title') }}
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>{{ t('stripe.help_text') }}</p>
                        <a
                            href="https://dashboard.stripe.com/apikeys"
                            target="_blank"
                            class="inline-flex items-center mt-2 font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                        >
                            {{ t('stripe.open_stripe_dashboard') }}
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
