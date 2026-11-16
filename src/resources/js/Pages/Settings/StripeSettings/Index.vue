<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SecretField from "@/Components/Ui/SecretField.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    settings: Object,
});

const { t } = useI18n();
// Saving is confirmed here; what Stripe answers after the OAuth round trip comes from the server
const { toast, showToast, dismiss } = useToast({ flash: true });

// Two ways to connect: through Stripe Connect (OAuth), or with API keys typed in by hand
const connectionMode = ref(props.settings?.connection_mode || "api_key");
const connectionModes = computed(() => [
    { value: "oauth", label: t("stripe.connection_mode_oauth") },
    { value: "api_key", label: t("stripe.connection_mode_api_key") },
]);

const isOAuthConnected = computed(() => props.settings?.oauth?.is_connected);

const isConfigured = computed(() =>
    connectionMode.value === "oauth" ? isOAuthConnected.value : props.settings?.is_configured,
);

const statusLine = computed(() => {
    if (!isConfigured.value) return t("stripe.status_add_keys");
    return connectionMode.value === "oauth" ? t("stripe.connected_via_oauth") : t("stripe.status_ready");
});

const form = useForm({
    publishable_key: props.settings?.publishable_key || "",
    secret_key: "",
    webhook_secret: "",
    client_id: props.settings?.client_id || "",
});

const testingConnection = ref(false);
const testResult = ref(null);
const disconnecting = ref(false);
const showDisconnectConfirm = ref(false);
const savingClientId = ref(false);

// The address Stripe has to call: this installation's own
const webhookUrl = typeof window !== "undefined" ? `${window.location.origin}/webhooks/stripe` : "/webhooks/stripe";

const WEBHOOK_EVENTS = ["checkout.session.completed", "charge.refunded", "payment_intent.succeeded"];

const submit = () => {
    form.post(route("settings.stripe.update"), {
        preserveScroll: true,
        onSuccess: () => form.reset("secret_key", "webhook_secret"),
    });
};

const testConnection = async () => {
    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route("settings.stripe.test-connection"));
        testResult.value = { success: true, message: response.data.message };
    } catch (error) {
        testResult.value = {
            success: false,
            message: error.response?.data?.message || t("stripe.connection_error"),
        };
    } finally {
        testingConnection.value = false;
    }
};

const connectWithOAuth = () => {
    window.location.href = route("settings.stripe.oauth.authorize");
};

const disconnectOAuth = () => {
    disconnecting.value = true;
    router.post(
        route("settings.stripe.oauth.disconnect"),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                disconnecting.value = false;
                showDisconnectConfirm.value = false;
            },
        },
    );
};

const saveClientId = () => {
    savingClientId.value = true;
    router.post(
        route("settings.stripe.update"),
        { client_id: form.client_id },
        {
            preserveScroll: true,
            onError: (errors) => showToast(Object.values(errors).flat().join(" "), false),
            onFinish: () => (savingClientId.value = false),
        },
    );
};
</script>

<template>
    <Head :title="t('stripe.settings_title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('stripe.settings_title')" :subtitle="statusLine">
                <StatusPill :tone="isConfigured ? 'success' : 'neutral'">
                    {{ isConfigured ? t("stripe.status_configured") : t("stripe.status_not_configured") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <SegmentedControl
                v-model="connectionMode"
                :options="connectionModes"
                :label="t('stripe.settings_title')"
                class="self-start"
            />

            <!-- Stripe Connect -->
            <template v-if="connectionMode === 'oauth'">
                <Panel v-if="isOAuthConnected" :title="t('stripe.connected_account')">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ t("stripe.stripe_account_id") }}:
                        <code
                            class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                        >
                            {{ settings?.oauth?.stripe_user_id }}
                        </code>
                    </p>
                    <div class="mt-4">
                        <SecondaryButton type="button" @click="showDisconnectConfirm = true">
                            {{ t("stripe.oauth_disconnect") }}
                        </SecondaryButton>
                    </div>
                </Panel>

                <!-- Not connected yet: four steps, in the order they have to be done -->
                <Panel v-else flush>
                    <ol class="-mt-3 divide-y divide-slate-100 dark:divide-slate-700/40">
                        <li class="flex gap-4 px-5 py-5">
                            <span
                                aria-hidden="true"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"
                                >1</span
                            >
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ t("stripe.oauth_setup_step1_title") }}
                                </h3>
                                <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                                    <li>{{ t("stripe.oauth_setup_step1_1") }}</li>
                                    <li>{{ t("stripe.oauth_setup_step1_2") }}</li>
                                    <li>{{ t("stripe.oauth_setup_step1_3") }}</li>
                                </ol>
                                <p class="mt-3 text-sm font-medium text-slate-900 dark:text-white">
                                    {{ t("stripe.oauth_required_permissions") }}
                                </p>
                                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("stripe.oauth_permissions_list") }}</p>
                                <a
                                    href="https://dashboard.stripe.com/settings/connect"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    {{ t("stripe.oauth_open_connect_settings") }}
                                </a>
                            </div>
                        </li>
                        <li class="flex gap-4 px-5 py-5">
                            <span
                                aria-hidden="true"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"
                                >2</span
                            >
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ t("stripe.oauth_setup_step2_title") }}
                                </h3>
                                <p class="mb-2 mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ t("stripe.oauth_setup_step2_desc") }}
                                </p>
                                <CopyField :value="settings?.redirect_uri || ''" />
                            </div>
                        </li>
                        <li class="flex gap-4 px-5 py-5">
                            <span
                                aria-hidden="true"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"
                                >3</span
                            >
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ t("stripe.oauth_setup_step3_title") }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ t("stripe.oauth_setup_step3_desc") }}
                                </p>
                                <label for="stripe-client-id" class="mt-3" :class="labelClass">{{ t("stripe.client_id") }}</label>
                                <div class="mt-1 flex gap-2">
                                    <input
                                        id="stripe-client-id"
                                        v-model="form.client_id"
                                        type="text"
                                        autocomplete="off"
                                        :placeholder="t('stripe.client_id_placeholder')"
                                        class="min-w-0 font-mono"
                                        :class="fieldClass"
                                    />
                                    <SecondaryButton
                                        type="button"
                                        class="shrink-0"
                                        :disabled="savingClientId || !form.client_id"
                                        @click="saveClientId"
                                    >
                                        {{ savingClientId ? t("common.saving") : t("common.save") }}
                                    </SecondaryButton>
                                </div>
                            </div>
                        </li>
                        <li class="flex gap-4 px-5 py-5">
                            <span
                                aria-hidden="true"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white dark:bg-white dark:text-slate-900"
                                >4</span
                            >
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ t("stripe.oauth_setup_step4_title") }}
                                </h3>
                                <div class="mt-2">
                                    <PrimaryButton v-if="settings?.client_id_configured" type="button" @click="connectWithOAuth">
                                        {{ t("stripe.oauth_connect") }}
                                    </PrimaryButton>
                                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                                        {{ t("stripe.oauth_save_client_id_first") }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </Panel>
            </template>

            <!-- API keys typed in by hand -->
            <template v-else>
                <form @submit.prevent="submit">
                    <Panel :title="t('stripe.connection_mode_api_key')">
                        <div class="space-y-4">
                            <div>
                                <label for="stripe-publishable" :class="labelClass">{{ t("stripe.publishable_key") }}</label>
                                <input
                                    id="stripe-publishable"
                                    v-model="form.publishable_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="t('stripe.publishable_key_placeholder')"
                                    class="mt-1 font-mono"
                                    :class="[fieldClass, { '!border-rose-500': form.errors.publishable_key }]"
                                />
                                <p v-if="form.errors.publishable_key" :class="errorClass">{{ form.errors.publishable_key }}</p>
                                <p v-else :class="hintClass">{{ t("stripe.publishable_key_help") }}</p>
                            </div>

                            <SecretField
                                id="stripe-secret"
                                v-model="form.secret_key"
                                :label="t('stripe.secret_key')"
                                :placeholder="settings?.secret_key_masked || t('stripe.secret_key_placeholder')"
                                :hint="t('stripe.secret_key_help')"
                                :error="form.errors.secret_key"
                            />
                            <SecretField
                                id="stripe-webhook-secret"
                                v-model="form.webhook_secret"
                                :label="t('stripe.webhook_secret')"
                                :placeholder="settings?.webhook_secret_masked || t('stripe.webhook_secret_placeholder')"
                                :hint="t('stripe.webhook_secret_help')"
                                :error="form.errors.webhook_secret"
                            />

                            <Notice v-if="testResult" :tone="testResult.success ? 'success' : 'danger'">
                                {{ testResult.message }}
                            </Notice>

                            <div class="flex flex-wrap items-center justify-end gap-3 pt-1">
                                <SecondaryButton
                                    type="button"
                                    :disabled="testingConnection || !isConfigured"
                                    @click="testConnection"
                                >
                                    {{ testingConnection ? t("common.testing") : t("stripe.test_connection") }}
                                </SecondaryButton>
                                <PrimaryButton type="submit" :disabled="form.processing">
                                    {{ form.processing ? t("common.saving") : t("common.save") }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </Panel>
                </form>

                <!-- What to set on Stripe's side so payments are reported back -->
                <Panel :title="t('stripe.webhook_url')">
                    <CopyField :value="webhookUrl" :hint="t('stripe.webhook_url_help')" />

                    <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ t("stripe.webhook_setup_title") }}</p>
                    <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                        <li>{{ t("stripe.webhook_setup_step1") }}</li>
                        <li>{{ t("stripe.webhook_setup_step2") }}</li>
                        <li>{{ t("stripe.webhook_setup_step3") }}</li>
                    </ol>

                    <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ t("stripe.webhook_events_title") }}</p>
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
                        href="https://dashboard.stripe.com/webhooks/create"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        {{ t("stripe.webhook_open_stripe") }}
                    </a>
                </Panel>

                <Panel :title="t('stripe.help_title')">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("stripe.help_text") }}</p>
                    <a
                        href="https://dashboard.stripe.com/apikeys"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        {{ t("stripe.open_stripe_dashboard") }}
                    </a>
                </Panel>
            </template>
        </div>

        <ConfirmModal
            :show="showDisconnectConfirm"
            type="danger"
            :title="t('stripe.oauth_disconnect')"
            :message="t('stripe.oauth_disconnect_confirm')"
            :confirm-text="t('stripe.oauth_disconnect')"
            :processing="disconnecting"
            @close="showDisconnectConfirm = false"
            @confirm="disconnectOAuth"
        >
            {{ settings?.oauth?.stripe_user_id }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
