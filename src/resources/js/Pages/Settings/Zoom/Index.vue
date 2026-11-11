<script setup>
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SecretField from "@/Components/Ui/SecretField.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    settings: Object,
    connection: Object,
    is_configured: Boolean,
});

const { t } = useI18n();
// What Zoom answered after the round trip comes from the server; saving is confirmed here
const { toast, showToast, dismiss } = useToast({ flash: true });

const form = useForm({
    client_id: props.settings?.client_id || "",
    client_secret: "",
});

const saveSettings = () => {
    form.post(route("settings.zoom.save"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("client_secret");
            showToast(t("zoom_settings.saved"));
        },
    });
};

const showDisconnect = ref(false);
const disconnecting = ref(false);

const disconnect = () => {
    disconnecting.value = true;
    router.post(
        route("settings.zoom.disconnect"),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                disconnecting.value = false;
                showDisconnect.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="t('zoom_settings.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('zoom_settings.title')" :subtitle="t('zoom_settings.subtitle')">
                <StatusPill :tone="connection ? 'success' : 'neutral'">
                    {{ connection ? t("zoom_settings.connected") : t("zoom_settings.not_connected") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <!-- Where this page sits -->
            <nav :aria-label="t('zoom_settings.title')" class="flex flex-wrap items-center gap-x-2 text-sm text-slate-600 dark:text-slate-400">
                <Link
                    :href="route('marketplace.index')"
                    class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    {{ t("zoom_settings.marketplace") }}
                </Link>
                <span aria-hidden="true">/</span>
                <Link
                    :href="route('marketplace.zoom')"
                    class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Zoom
                </Link>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ t("zoom_settings.breadcrumb_settings") }}</span>
            </nav>

            <!-- The account meetings are created on -->
            <Panel v-if="connection" :title="t('zoom_settings.connected_account')">
                <template #actions>
                    <SecondaryButton type="button" @click="showDisconnect = true">
                        {{ t("zoom_settings.disconnect") }}
                    </SecondaryButton>
                </template>

                <p class="break-all text-sm font-medium text-slate-900 dark:text-white">{{ connection.zoom_email }}</p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ t("zoom_settings.connected_at", { date: connection.connected_at }) }}
                </p>

                <template v-if="connection.granted_scopes && connection.granted_scopes.length">
                    <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ t("zoom_settings.permissions") }}</p>
                    <!-- Zoom's own names for the permissions, as its consent screen shows them -->
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="scope in connection.granted_scopes"
                            :key="scope"
                            class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                        >
                            {{ scope }}
                        </li>
                    </ul>
                    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">{{ t("zoom_settings.permissions_note") }}</p>
                </template>
            </Panel>

            <form @submit.prevent="saveSettings">
                <Panel :title="t('zoom_settings.credentials')">
                    <i18n-t keypath="zoom_settings.credentials_help" tag="p" class="-mt-3 mb-4 text-sm text-slate-600 dark:text-slate-400">
                        <template #link>
                            <a
                                href="https://marketplace.zoom.us/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >Zoom Marketplace</a
                            >
                        </template>
                    </i18n-t>

                    <div class="space-y-4">
                        <div>
                            <label for="zoom-client-id" :class="labelClass">{{ t("zoom_settings.client_id") }}</label>
                            <input
                                id="zoom-client-id"
                                v-model="form.client_id"
                                type="text"
                                autocomplete="off"
                                spellcheck="false"
                                :placeholder="t('zoom_settings.client_id_placeholder')"
                                class="mt-1 font-mono"
                                :class="[fieldClass, { '!border-rose-500': form.errors.client_id }]"
                            />
                            <p v-if="form.errors.client_id" :class="errorClass">{{ form.errors.client_id }}</p>
                        </div>

                        <SecretField
                            id="zoom-client-secret"
                            v-model="form.client_secret"
                            :label="t('zoom_settings.client_secret')"
                            :placeholder="settings?.has_client_secret ? '••••••••••••' : t('zoom_settings.client_secret_placeholder')"
                            :hint="settings?.has_client_secret ? t('zoom_settings.keep_secret') : ''"
                            :error="form.errors.client_secret"
                        />

                        <CopyField
                            :label="t('zoom_settings.redirect_uri')"
                            :value="settings?.redirect_uri || ''"
                            :hint="t('zoom_settings.redirect_help')"
                        />

                        <div class="flex justify-end pt-1">
                            <PrimaryButton type="submit" :disabled="form.processing">
                                {{ form.processing ? t("common.saving") : t("zoom_settings.save") }}
                            </PrimaryButton>
                        </div>
                    </div>
                </Panel>
            </form>

            <!-- The app is known, the account is not connected yet -->
            <Panel v-if="is_configured && !connection" :title="t('zoom_settings.connect_title')">
                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ t("zoom_settings.connect_desc") }}</p>
                <!-- A full page load: Zoom's consent screen is on another site -->
                <a
                    :href="route('settings.zoom.connect')"
                    class="mt-4 inline-flex min-h-10 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                >
                    {{ t("zoom_settings.connect_button") }}
                </a>
            </Panel>

            <Notice v-if="!is_configured" tone="warning" :title="t('zoom_settings.config_required')">
                {{ t("zoom_settings.config_required_text") }}
            </Notice>
        </div>

        <ConfirmModal
            :show="showDisconnect"
            type="danger"
            :title="t('zoom_settings.disconnect_title')"
            :message="t('zoom_settings.disconnect_message')"
            :confirm-text="t('zoom_settings.disconnect')"
            :processing="disconnecting"
            @close="showDisconnect = false"
            @confirm="disconnect"
        >
            {{ connection?.zoom_email }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
