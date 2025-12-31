<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { ref } from "vue";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            is_configured: false,
            environment: 'sandbox',
            access_token_masked: '',
            webhook_secret_masked: '',
        }),
    },
});

const form = useForm({
    access_token: "",
    webhook_secret: "",
    environment: props.settings?.environment || "sandbox",
});

const testingConnection = ref(false);
const connectionResult = ref(null);

const submit = () => {
    form.post(route("settings.polar.update"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("access_token", "webhook_secret");
        },
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    connectionResult.value = null;

    try {
        const response = await axios.post(route("settings.polar.test-connection"));
        connectionResult.value = {
            success: true,
            message: response.data.message,
        };
    } catch (error) {
        connectionResult.value = {
            success: false,
            message: error.response?.data?.message || t("polar.connection_failed"),
        };
    } finally {
        testingConnection.value = false;
    }
};

// Webhook URL computed property (window is not available in Vue 3 templates)
const webhookUrl = typeof window !== 'undefined' ? `${window.location.origin}/webhooks/polar` : '/webhooks/polar';

const copyWebhookUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(webhookUrl);
    }
};
</script>

<template>
    <Head :title="$t('polar.settings_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-white">
                {{ $t("polar.settings_title") }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <!-- Configuration Status -->
                <div class="mb-6 rounded-xl p-4" :class="settings.is_configured ? 'bg-emerald-500/10 ring-1 ring-emerald-500/20' : 'bg-amber-500/10 ring-1 ring-amber-500/20'">
                    <div class="flex items-center gap-3">
                        <div v-if="settings.is_configured" class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/20">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/20">
                            <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 :class="settings.is_configured ? 'text-emerald-400' : 'text-amber-400'" class="font-semibold">
                                {{ settings.is_configured ? $t("polar.configured") : $t("polar.not_configured") }}
                            </h3>
                            <p class="text-sm text-slate-400">
                                {{ settings.is_configured ? $t("polar.configured_desc") : $t("polar.not_configured_desc") }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Settings Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-2xl bg-slate-800 p-6 ring-1 ring-white/10">
                        <h3 class="text-lg font-semibold text-white mb-6">
                            {{ $t("polar.api_settings") }}
                        </h3>

                        <!-- Environment -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                {{ $t("polar.environment") }}
                            </label>
                            <select
                                v-model="form.environment"
                                class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="sandbox">{{ $t("polar.sandbox") }}</option>
                                <option value="production">{{ $t("polar.production") }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t("polar.environment_help") }}
                            </p>
                        </div>

                        <!-- Access Token -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                {{ $t("polar.access_token") }}
                            </label>
                            <input
                                v-model="form.access_token"
                                type="password"
                                :placeholder="settings.access_token_masked || $t('polar.access_token_placeholder')"
                                class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t("polar.access_token_help") }}
                            </p>
                        </div>

                        <!-- Webhook Secret -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                {{ $t("polar.webhook_secret") }}
                            </label>
                            <input
                                v-model="form.webhook_secret"
                                type="password"
                                :placeholder="settings.webhook_secret_masked || $t('polar.webhook_secret_placeholder')"
                                class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white placeholder-slate-500 focus:border-blue-500 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $t("polar.webhook_secret_help") }}
                            </p>
                        </div>

                        <!-- Webhook URL Info -->
                        <div class="mb-6 p-4 rounded-lg bg-slate-900/50">
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                {{ $t("polar.webhook_url") }}
                            </label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 p-2 rounded bg-slate-800 text-sm text-slate-300 font-mono break-all">
                                    {{ webhookUrl }}
                                </code>
                                <button
                                    type="button"
                                    @click="copyWebhookUrl"
                                    class="p-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ $t("polar.webhook_url_help") }}
                            </p>

                            <!-- Webhook Setup Instructions -->
                            <div class="mt-4 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20">
                                <p class="text-xs font-medium text-amber-300 mb-2">{{ $t("polar.webhook_setup_title") }}</p>
                                <ol class="text-xs text-amber-200/80 space-y-1 list-decimal ml-4">
                                    <li>{{ $t("polar.webhook_setup_step1") }}</li>
                                    <li>{{ $t("polar.webhook_setup_step2") }}</li>
                                    <li>{{ $t("polar.webhook_setup_step3") }}</li>
                                </ol>
                                <div class="mt-3">
                                    <p class="text-xs font-medium text-amber-300 mb-1">{{ $t("polar.webhook_events_title") }}</p>
                                    <div class="flex flex-wrap gap-1">
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">checkout.created</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">checkout.updated</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">order.created</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">order.paid</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">order.refunded</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">subscription.created</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">subscription.canceled</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">benefit.grant.created</code>
                                        <code class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-200 rounded">benefit.grant.revoked</code>
                                    </div>
                                </div>
                                <a
                                    href="https://polar.sh/dashboard"
                                    target="_blank"
                                    class="inline-flex items-center mt-3 text-xs font-medium text-amber-300 hover:text-amber-200"
                                >
                                    {{ $t("polar.webhook_open_polar") }}
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- Connection Test -->
                        <div v-if="settings.is_configured" class="mb-6">
                            <button
                                type="button"
                                @click="testConnection"
                                :disabled="testingConnection"
                                class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600 disabled:opacity-50"
                            >
                                <svg v-if="testingConnection" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ $t("polar.test_connection") }}
                            </button>

                            <div v-if="connectionResult" class="mt-3 p-3 rounded-lg" :class="connectionResult.success ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'">
                                {{ connectionResult.message }}
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ $t("common.save") }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
