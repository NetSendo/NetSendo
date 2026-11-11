<script setup>
import { computed, ref } from "vue";
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
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const form = useForm({
    client_id: props.settings?.client_id || "",
    client_secret: "",
    security_code: "",
    sandbox_mode: props.settings?.sandbox_mode ?? true,
});

const testingConnection = ref(false);
const testResult = ref(null);

// The address Tpay has to call: this installation's own
const webhookUrl = typeof window !== "undefined" ? `${window.location.origin}/webhooks/tpay` : "/webhooks/tpay";

const isConfigured = computed(() => props.settings?.is_configured);

const submit = () => {
    form.post(route("settings.tpay.update"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("client_secret", "security_code");
            showToast(t("tpay.settings_saved"));
        },
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route("settings.tpay.test-connection"));
        testResult.value = { success: true, message: response.data.message };
    } catch (error) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || t("tpay.connection_error"),
        };
    } finally {
        testingConnection.value = false;
    }
};
</script>

<template>
    <Head :title="t('tpay.settings_title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="t('tpay.settings_title')"
                :subtitle="isConfigured ? t('tpay.status_ready') : t('tpay.status_add_keys')"
            >
                <StatusPill v-if="form.sandbox_mode" tone="warning">Sandbox</StatusPill>
                <StatusPill :tone="isConfigured ? 'success' : 'neutral'">
                    {{ isConfigured ? t("tpay.status_configured") : t("tpay.status_not_configured") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <form @submit.prevent="submit">
                <Panel :title="t('tpay.settings_subtitle')">
                    <div class="space-y-4">
                        <!-- Test addresses or the real ones -->
                        <div class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700/60">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ t("tpay.sandbox_mode") }}</p>
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ t("tpay.sandbox_mode_help") }}</p>
                            </div>
                            <Switch v-model="form.sandbox_mode" :label="t('tpay.sandbox_mode')" />
                        </div>

                        <div>
                            <label for="tpay-client-id" :class="labelClass">{{ t("tpay.client_id") }}</label>
                            <input
                                id="tpay-client-id"
                                v-model="form.client_id"
                                type="text"
                                autocomplete="off"
                                :placeholder="t('tpay.client_id_placeholder')"
                                class="mt-1 font-mono"
                                :class="[fieldClass, { '!border-rose-500': form.errors.client_id }]"
                            />
                            <p v-if="form.errors.client_id" :class="errorClass">{{ form.errors.client_id }}</p>
                            <p v-else :class="hintClass">{{ t("tpay.client_id_help") }}</p>
                        </div>

                        <SecretField
                            id="tpay-client-secret"
                            v-model="form.client_secret"
                            :label="t('tpay.client_secret')"
                            :placeholder="settings?.client_secret_masked || t('tpay.client_secret_placeholder')"
                            :hint="t('tpay.client_secret_help')"
                            :error="form.errors.client_secret"
                        />
                        <SecretField
                            id="tpay-security-code"
                            v-model="form.security_code"
                            :label="t('tpay.security_code')"
                            :placeholder="settings?.security_code_masked || t('tpay.security_code_placeholder')"
                            :hint="t('tpay.security_code_help')"
                            :error="form.errors.security_code"
                        />

                        <CopyField :label="t('tpay.webhook_url')" :value="webhookUrl" :hint="t('tpay.webhook_url_help')" />

                        <Notice v-if="testResult" :tone="testResult.success ? 'success' : 'danger'">
                            {{ testResult.message }}
                        </Notice>

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-1">
                            <SecondaryButton
                                type="button"
                                :disabled="testingConnection || !isConfigured"
                                @click="testConnection"
                            >
                                {{ testingConnection ? t("common.testing") : t("tpay.test_connection") }}
                            </SecondaryButton>
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? t("common.saving") : t("common.save") }}
                            </PrimaryButton>
                        </div>
                    </div>
                </Panel>
            </form>

            <Panel :title="t('tpay.help_title')">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("tpay.help_text") }}</p>
                <a
                    href="https://panel.tpay.com"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    {{ t("tpay.open_tpay_panel") }}
                </a>
            </Panel>
        </div>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
