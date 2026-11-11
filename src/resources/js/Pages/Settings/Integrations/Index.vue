<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SecretField from "@/Components/Ui/SecretField.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const { t } = useI18n();
// What Google answered after the round trip, and what saving or deleting did, comes from the server
const { toast, dismiss } = useToast({ flash: true });

const props = defineProps({
    integrations: Array,
    google_redirect_uri: String,
});

const form = useForm({
    name: "",
    client_id: "",
    client_secret: "",
});

const showAddModal = ref(false);

const openAddModal = () => {
    form.reset();
    form.clearErrors();
    dismiss();
    showAddModal.value = true;
};

const submit = () => {
    form.post(route("settings.integrations.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const formErrorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);

const integrationToDelete = ref(null);
const deleting = ref(false);

const deleteIntegration = () => {
    if (!integrationToDelete.value) return;
    deleting.value = true;
    router.delete(route("settings.integrations.destroy", integrationToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            integrationToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head :title="$t('integrations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('integrations.google.title')" :subtitle="$t('integrations.google.description')">
                <CreateLink @click="openAddModal">{{ $t("integrations.google.add") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <Panel flush>
                <EmptyState v-if="integrations.length === 0" class="!border-t-0" :title="$t('integrations.google.empty')">
                    <CreateLink @click="openAddModal">{{ $t("integrations.google.add") }}</CreateLink>
                </EmptyState>

                <ul v-else class="-mt-3 divide-y divide-slate-100 dark:divide-slate-700/40">
                    <li
                        v-for="integration in integrations"
                        :key="integration.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-5 py-3"
                    >
                        <div class="min-w-0 flex-1 basis-64">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ integration.name }}</p>
                                <StatusPill :tone="integration.status === 'active' ? 'success' : 'neutral'">
                                    {{
                                        integration.status === "active"
                                            ? $t("integrations.google.connected")
                                            : $t("integrations.google.ready")
                                    }}
                                </StatusPill>
                            </div>
                            <p class="truncate font-mono text-xs text-slate-600 dark:text-slate-400" :title="integration.client_id">
                                {{ integration.client_id }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <!-- A full page load: Google's consent screen is on another site -->
                            <a
                                :href="route('settings.integrations.verify', integration.id)"
                                class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-700/60"
                            >
                                {{
                                    integration.status === "active"
                                        ? $t("integrations.google.reconnect")
                                        : $t("integrations.google.connect")
                                }}
                            </a>
                            <ActionMenu :label="$t('integrations.actions_for', { name: integration.name })">
                                <ActionMenuItem danger @click="integrationToDelete = integration">
                                    {{ $t("common.delete") }}
                                </ActionMenuItem>
                            </ActionMenu>
                        </div>
                    </li>
                </ul>
            </Panel>

            <!-- The address to enter on Google's side for every connection made here -->
            <Panel :title="$t('integrations.google.redirect_uri')">
                <CopyField :value="props.google_redirect_uri || ''" />
            </Panel>
        </div>

        <FormDialog
            :show="showAddModal"
            :title="$t('integrations.google.modal_title')"
            :processing="form.processing"
            :errors="formErrorMessages"
            :toast="toast"
            @close="showAddModal = false"
            @submit="submit"
        >
            <div class="space-y-4">
                <div>
                    <label for="google-name" :class="labelClass">{{ $t("integrations.google.name_placeholder") }}</label>
                    <input
                        id="google-name"
                        v-model="form.name"
                        type="text"
                        required
                        autocomplete="off"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                    <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="google-client-id" :class="labelClass">Client ID</label>
                    <input
                        id="google-client-id"
                        v-model="form.client_id"
                        type="text"
                        required
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-1 font-mono"
                        :class="[fieldClass, { '!border-rose-500': form.errors.client_id }]"
                    />
                    <p v-if="form.errors.client_id" :class="errorClass">{{ form.errors.client_id }}</p>
                </div>
                <SecretField
                    id="google-client-secret"
                    v-model="form.client_secret"
                    label="Client Secret"
                    :error="form.errors.client_secret"
                />
            </div>
        </FormDialog>

        <ConfirmModal
            :show="!!integrationToDelete"
            type="danger"
            :title="$t('integrations.delete_title')"
            :message="$t('integrations.confirm_delete')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="integrationToDelete = null"
            @confirm="deleteIntegration"
        >
            {{ integrationToDelete?.name }}
        </ConfirmModal>

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="showAddModal ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
