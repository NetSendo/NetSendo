<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    settings: Object
});

const { t } = useI18n();
const page = usePage();

// Connection mode: 'oauth' or 'api_key'
const connectionMode = ref(props.settings?.connection_mode || 'api_key');
const isOAuthConnected = computed(() => props.settings?.oauth?.is_connected);

const form = useForm({
    publishable_key: props.settings?.publishable_key || '',
    secret_key: '',
    webhook_secret: '',
});

const showSecretKey = ref(false);
const showWebhookSecret = ref(false);
const testingConnection = ref(false);
const testResult = ref(null);
const disconnecting = ref(false);
const showDisconnectConfirm = ref(false);

const isConfigured = computed(() => {
    if (connectionMode.value === 'oauth') {
        return isOAuthConnected.value;
    }
    return props.settings?.is_configured;
});

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

const connectWithOAuth = () => {
    window.location.href = route('settings.stripe.oauth.authorize');
};

const disconnectOAuth = () => {
    disconnecting.value = true;
    router.post(route('settings.stripe.oauth.disconnect'), {}, {
        preserveScroll: true,
        onFinish: () => {
            disconnecting.value = false;
            showDisconnectConfirm.value = false;
        }
    });
};

const switchToApiKeys = () => {
    connectionMode.value = 'api_key';
};

const switchToOAuth = () => {
    connectionMode.value = 'oauth';
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
                        <div class="flex items-center justify-between">
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
                                        <template v-if="isConfigured && connectionMode === 'oauth'">
                                            {{ t('stripe.connected_via_oauth') }}
                                        </template>
                                        <template v-else-if="isConfigured">
                                            {{ t('stripe.status_ready') }}
                                        </template>
                                        <template v-else>
                                            {{ t('stripe.status_add_keys') }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <!-- Connection Mode Badge -->
                            <div v-if="isConfigured" class="flex items-center">
                                <span :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    connectionMode === 'oauth'
                                        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
                                        : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                ]">
                                    {{ connectionMode === 'oauth' ? 'OAuth' : 'API Keys' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Connection Mode Toggle -->
                <div class="mb-6 overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-4">
                        <div class="flex rounded-lg bg-gray-100 dark:bg-gray-700 p-1">
                            <button
                                type="button"
                                @click="switchToOAuth"
                                :class="[
                                    'flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors',
                                    connectionMode === 'oauth'
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                <div class="flex items-center justify-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>{{ t('stripe.connection_mode_oauth') }}</span>
                                </div>
                            </button>
                            <button
                                type="button"
                                @click="switchToApiKeys"
                                :class="[
                                    'flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors',
                                    connectionMode === 'api_key'
                                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                                ]"
                            >
                                <div class="flex items-center justify-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    <span>{{ t('stripe.connection_mode_api_key') }}</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- OAuth Connect Section -->
                <div v-if="connectionMode === 'oauth'" class="mb-6 overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="p-6">
                        <template v-if="isOAuthConnected">
                            <!-- Connected State -->
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-green-100 rounded-full dark:bg-green-900/30">
                                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ t('stripe.connected_account') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('stripe.stripe_account_id') }}:
                                    <code class="px-2 py-1 text-xs bg-gray-100 rounded dark:bg-gray-700">
                                        {{ settings?.oauth?.stripe_user_id }}
                                    </code>
                                </p>

                                <div class="mt-6">
                                    <button
                                        v-if="!showDisconnectConfirm"
                                        type="button"
                                        @click="showDisconnectConfirm = true"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-100 border border-transparent rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
                                    >
                                        {{ t('stripe.oauth_disconnect') }}
                                    </button>

                                    <div v-else class="space-y-3">
                                        <p class="text-sm text-red-600 dark:text-red-400">
                                            {{ t('stripe.oauth_disconnect_confirm') }}
                                        </p>
                                        <div class="flex items-center justify-center space-x-3">
                                            <button
                                                type="button"
                                                @click="showDisconnectConfirm = false"
                                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                                            >
                                                {{ t('common.cancel') }}
                                            </button>
                                            <button
                                                type="button"
                                                @click="disconnectOAuth"
                                                :disabled="disconnecting"
                                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50"
                                            >
                                                <svg v-if="disconnecting" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                {{ t('common.confirm') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <!-- Not Connected State -->
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-purple-100 rounded-full dark:bg-purple-900/30">
                                    <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ t('stripe.oauth_connect') }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('stripe.oauth_connect_desc') }}
                                </p>

                                <div class="mt-6">
                                    <button
                                        v-if="settings?.client_id_configured"
                                        type="button"
                                        @click="connectWithOAuth"
                                        class="inline-flex items-center px-6 py-3 text-base font-medium text-white bg-purple-600 border border-transparent rounded-md shadow-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                                    >
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                        </svg>
                                        {{ t('stripe.oauth_connect') }}
                                    </button>

                                    <div v-else class="p-4 rounded-md bg-yellow-50 dark:bg-yellow-900/20">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                                    {{ t('stripe.oauth_client_id_missing') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- API Keys Form Section -->
                <div v-if="connectionMode === 'api_key'" class="overflow-hidden bg-white rounded-lg shadow dark:bg-gray-800">
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
