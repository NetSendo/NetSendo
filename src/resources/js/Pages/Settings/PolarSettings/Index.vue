<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SecretField from "@/Components/Ui/SecretField.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const { t } = useI18n();
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            is_configured: false,
            environment: "sandbox",
            access_token_masked: "",
            webhook_secret_masked: "",
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
            showToast(t("polar.settings_saved"));
        },
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    connectionResult.value = null;

    try {
        const response = await axios.post(route("settings.polar.test-connection"));
        connectionResult.value = { success: true, message: response.data.message };
    } catch (error) {
        connectionResult.value = {
            success: false,
            message: error.response?.data?.message || t("polar.connection_failed"),
        };
    } finally {
        testingConnection.value = false;
    }
};

// The address Polar has to call: this installation's own
const webhookUrl = typeof window !== "undefined" ? `${window.location.origin}/webhooks/polar` : "/webhooks/polar";

const WEBHOOK_EVENTS = [
    "checkout.created",
    "checkout.updated",
    "order.created",
    "order.paid",
    "order.refunded",
    "subscription.created",
    "subscription.canceled",
    "benefit.grant.created",
    "benefit.grant.revoked",
];
</script>

<template>
    <Head :title="$t('polar.settings_title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="$t('polar.settings_title')"
                :subtitle="settings.is_configured ? $t('polar.configured_desc') : $t('polar.not_configured_desc')"
            >
                <StatusPill :tone="settings.is_configured ? 'success' : 'neutral'">
                    {{ settings.is_configured ? $t("polar.configured") : $t("polar.not_configured") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <form @submit.prevent="submit">
                <Panel :title="$t('polar.api_settings')">
                    <div class="space-y-4">
                        <div>
                            <label for="polar-environment" :class="labelClass">{{ $t("polar.environment") }}</label>
                            <select id="polar-environment" v-model="form.environment" class="mt-1" :class="fieldClass">
                                <option value="sandbox">{{ $t("polar.sandbox") }}</option>
                                <option value="production">{{ $t("polar.production") }}</option>
                            </select>
                            <p :class="hintClass">{{ $t("polar.environment_help") }}</p>
                        </div>

                        <SecretField
                            id="polar-token"
                            v-model="form.access_token"
                            :label="$t('polar.access_token')"
                            :placeholder="settings.access_token_masked || $t('polar.access_token_placeholder')"
                            :hint="$t('polar.access_token_help')"
                            :error="form.errors.access_token"
                        />
                        <SecretField
                            id="polar-webhook-secret"
                            v-model="form.webhook_secret"
                            :label="$t('polar.webhook_secret')"
                            :placeholder="settings.webhook_secret_masked || $t('polar.webhook_secret_placeholder')"
                            :hint="$t('polar.webhook_secret_help')"
                            :error="form.errors.webhook_secret"
                        />

                        <Notice v-if="connectionResult" :tone="connectionResult.success ? 'success' : 'danger'">
                            {{ connectionResult.message }}
                        </Notice>

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-1">
                            <SecondaryButton
                                v-if="settings.is_configured"
                                type="button"
                                :disabled="testingConnection"
                                @click="testConnection"
                            >
                                {{ testingConnection ? $t("common.testing") : $t("polar.test_connection") }}
                            </SecondaryButton>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? $t("common.saving") : $t("common.save") }}
                            </PrimaryButton>
                        </div>
                    </div>
                </Panel>
            </form>

            <!-- What to set on Polar's side so payments are reported back -->
            <Panel :title="$t('polar.webhook_url')">
                <CopyField :value="webhookUrl" :hint="$t('polar.webhook_url_help')" />

                <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ $t("polar.webhook_setup_title") }}</p>
                <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                    <li>{{ $t("polar.webhook_setup_step1") }}</li>
                    <li>{{ $t("polar.webhook_setup_step2") }}</li>
                    <li>{{ $t("polar.webhook_setup_step3") }}</li>
                </ol>

                <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ $t("polar.webhook_events_title") }}</p>
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    <li
                        v-for="event in WEBHOOK_EVENTS"
                        :key="event"
                        class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                    >
                        {{ event }}
                    </li>
                </ul>

                <a
                    href="https://polar.sh/dashboard"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-4 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    {{ $t("polar.webhook_open_polar") }}
                </a>
            </Panel>
        </div>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
