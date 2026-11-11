<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const { t, locale } = useI18n();
// The outcome of each action is worded here, in the user's language; the server's own
// message is shown only when something went wrong
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const props = defineProps({
    apiKeys: Array,
    availablePermissions: Array,
});

// Where the API of this installation lives, for the example below the list
const originUrl = computed(() => (typeof window !== "undefined" ? window.location.origin : ""));

// What each permission lets a key do, in words
const PERMISSION_LABELS = {
    "subscribers:read": "api_keys.permissions.subscribers_read",
    "subscribers:write": "api_keys.permissions.subscribers_write",
    "lists:read": "api_keys.permissions.lists_read",
    "lists:write": "api_keys.permissions.lists_write",
    "tags:read": "api_keys.permissions.tags_read",
    "notifications:write": "api_keys.permissions.notifications_write",
    "webhooks:read": "api_keys.permissions.webhooks_read",
    "webhooks:write": "api_keys.permissions.webhooks_write",
    "sms:read": "api_keys.permissions.sms_read",
    "sms:write": "api_keys.permissions.sms_write",
    "messages:read": "api_keys.permissions.messages_read",
    "messages:write": "api_keys.permissions.messages_write",
    "email:read": "api_keys.permissions.email_read",
    "email:write": "api_keys.permissions.email_write",
    "funnels:read": "api_keys.permissions.funnels_read",
    "funnels:write": "api_keys.permissions.funnels_write",
    "brain:read": "api_keys.permissions.brain_read",
    "brain:write": "api_keys.permissions.brain_write",
};

const permissionLabel = (permission) =>
    PERMISSION_LABELS[permission] ? t(PERMISSION_LABELS[permission]) : permission;

// "Full access", or the first two permissions and how many more there are
const permissionsSummary = (key) => {
    const permissions = key.permissions || [];
    if (props.availablePermissions.every((permission) => permissions.includes(permission))) {
        return { text: t("api_keys.full_access"), more: 0 };
    }
    return {
        text: permissions.slice(0, 2).map(permissionLabel).join(", ") || "—",
        more: Math.max(0, permissions.length - 2),
    };
};

const formatDateTime = (dateString) => (dateString ? new Date(dateString).toLocaleString(locale.value) : "—");

// ---- A new key. It is shown once, right after it is made.
const showCreateModal = ref(false);
const creating = ref(false);
const newKeyResult = ref(null);
const keyVisible = ref(false);
const keyCopied = ref(false);

const form = useForm({
    name: "",
    permissions: [],
    expires_at: "",
    is_mcp: false,
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.permissions = [...props.availablePermissions]; // everything, unless narrowed
    newKeyResult.value = null;
    keyVisible.value = false;
    keyCopied.value = false;
    dismiss();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const createKey = async () => {
    if (creating.value) return;
    creating.value = true;
    form.clearErrors();
    try {
        const response = await window.axios.post(route("settings.api-keys.store"), {
            name: form.name,
            permissions: form.permissions,
            expires_at: form.expires_at || null,
            is_mcp: form.is_mcp,
        });
        showCreateModal.value = false;
        newKeyResult.value = response.data;
        keyVisible.value = true;
        router.reload({ only: ["apiKeys"] });
    } catch (error) {
        if (error.response?.status === 419) {
            // The session ran out: a fresh page brings a fresh token
            showToast(t("api_keys.errors.session_expired"), false);
            setTimeout(() => window.location.reload(), 2500);
        } else if (error.response?.status === 422 && error.response.data?.errors) {
            form.setError(
                Object.fromEntries(
                    Object.entries(error.response.data.errors).map(([field, messages]) => [field, messages[0]]),
                ),
            );
        } else {
            showToast(error.response?.data?.message || t("common.notifications.error_occurred"), false);
        }
    } finally {
        creating.value = false;
    }
};

const closeKeyResult = () => {
    newKeyResult.value = null;
};

const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch (error) {
        // No clipboard permission (plain http): the old way still works
        const textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
    }
    keyCopied.value = true;
};

// ---- Changing a key
const showEditModal = ref(false);
const editingKey = ref(null);

const editForm = useForm({
    name: "",
    permissions: [],
    is_mcp: false,
    plain_key: "",
});

const openEditModal = (key) => {
    editingKey.value = key;
    editForm.clearErrors();
    editForm.name = key.name;
    editForm.permissions = [...(key.permissions || [])];
    editForm.is_mcp = key.is_mcp || false;
    editForm.plain_key = "";
    dismiss();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
};

const updateKey = () => {
    if (!editingKey.value) return;
    editForm
        .transform((data) => ({ ...data, plain_key: data.plain_key || null }))
        .put(route("settings.api-keys.update", editingKey.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
                showToast(t("api_keys.notifications.updated"));
            },
        });
};

// ---- Deleting a key
const keyToDelete = ref(null);
const deleting = ref(false);

const deleteKey = () => {
    if (!keyToDelete.value) return;
    deleting.value = true;
    router.delete(route("settings.api-keys.destroy", keyToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("api_keys.notifications.deleted")),
        onFinish: () => {
            deleting.value = false;
            keyToDelete.value = null;
        },
    });
};

const messagesOf = (target) => [...new Set(Object.values(target.errors).filter(Boolean))];

const allSelected = (target) =>
    props.availablePermissions.every((permission) => target.permissions.includes(permission));

const toggleAll = (target) => {
    target.permissions = allSelected(target) ? [] : [...props.availablePermissions];
};

const dialogOpen = computed(() => showCreateModal.value || showEditModal.value || !!newKeyResult.value);
</script>

<template>
    <Head :title="t('api_keys.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('api_keys.title')" :subtitle="t('api_keys.subtitle')">
                <CreateLink @click="openCreateModal">{{ t("api_keys.generate_new") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <Panel flush>
                <EmptyState v-if="apiKeys.length === 0" class="!border-t-0" :title="t('api_keys.empty_text')">
                    <CreateLink @click="openCreateModal">{{ t("api_keys.generate_first") }}</CreateLink>
                </EmptyState>

                <div v-else class="relative -mt-3 overflow-x-auto">
                    <table class="w-full text-sm sm:min-w-[860px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ t("api_keys.table.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ t("api_keys.table.permissions") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ t("api_keys.table.last_used") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ t("api_keys.table.created") }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ t("api_keys.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="key in apiKeys"
                                :key="key.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <!-- The key: its name and the first characters, enough to tell it from the others -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[34%]">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="min-w-0 truncate text-left font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                            :title="key.name"
                                            @click="openEditModal(key)"
                                        >
                                            {{ key.name }}
                                        </button>
                                        <StatusPill v-if="key.is_mcp" tone="info" class="shrink-0 max-sm:hidden">MCP</StatusPill>
                                        <StatusPill v-if="key.is_expired" tone="danger" class="shrink-0 max-sm:hidden">
                                            {{ t("api_keys.status.expired") }}
                                        </StatusPill>
                                    </div>
                                    <p class="truncate font-mono text-xs text-slate-600 dark:text-slate-400">
                                        {{ key.key_prefix }}…
                                    </p>
                                    <!-- On a phone the badges would squeeze the name: they sit under it -->
                                    <div
                                        v-if="key.is_mcp || key.is_expired"
                                        class="mt-1.5 flex flex-wrap items-center gap-1.5 sm:hidden"
                                    >
                                        <StatusPill v-if="key.is_mcp" tone="info">MCP</StatusPill>
                                        <StatusPill v-if="key.is_expired" tone="danger">
                                            {{ t("api_keys.status.expired") }}
                                        </StatusPill>
                                    </div>
                                </td>

                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 30%">
                                    <p
                                        class="truncate text-slate-900 dark:text-slate-100"
                                        :title="(key.permissions || []).map(permissionLabel).join(', ')"
                                    >
                                        {{ permissionsSummary(key).text }}
                                    </p>
                                    <p
                                        v-if="permissionsSummary(key).more"
                                        class="text-xs tabular-nums text-slate-600 dark:text-slate-400"
                                    >
                                        {{ t("api_keys.more_permissions", { count: permissionsSummary(key).more }) }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-900 max-sm:hidden dark:text-slate-100">
                                    {{ key.last_used_at ? formatDateTime(key.last_used_at) : t("api_keys.status.never_used") }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ formatDateTime(key.created_at) }}
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                            @click="openEditModal(key)"
                                        >
                                            {{ t("common.edit") }}
                                        </button>
                                        <ActionMenu :label="t('api_keys.actions_for', { name: key.name })">
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="openEditModal(key)">
                                                {{ t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="keyToDelete = key">
                                                {{ t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- How to call the API with a key -->
            <Panel :title="t('api_keys.docs.title')">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ t("api_keys.docs.text") }}
                    <a
                        href="/docs/api"
                        target="_blank"
                        rel="noopener"
                        class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        /docs/api
                    </a>
                </p>
                <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">
                    {{ t("api_keys.examples.title") }}
                </p>
                <pre
                    class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-200"
                ># {{ t("api_keys.examples.get_subscribers") }}
curl -H "Authorization: Bearer ns_live_…" \
     {{ originUrl }}/api/v1/subscribers

# {{ t("api_keys.examples.add_subscriber") }}
curl -X POST \
     -H "Authorization: Bearer ns_live_…" \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","contact_list_id":1,"first_name":"Jan","last_name":"Kowalski"}' \
     {{ originUrl }}/api/v1/subscribers</pre>
            </Panel>
        </div>

        <!-- A new key: what it is for, what it may do, how long it lives -->
        <FormDialog
            :show="showCreateModal"
            :title="t('api_keys.modal.title')"
            :processing="creating"
            :submit-label="t('api_keys.modal.generate')"
            :errors="messagesOf(form)"
            :toast="toast"
            @close="closeCreateModal"
            @submit="createKey"
        >
            <div class="space-y-4">
                <div>
                    <label for="api-key-name" :class="labelClass">{{ t("api_keys.modal.name_label") }}</label>
                    <input
                        id="api-key-name"
                        v-model="form.name"
                        type="text"
                        required
                        :placeholder="t('api_keys.modal.name_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                </div>

                <fieldset>
                    <div class="flex items-center justify-between gap-3">
                        <legend :class="labelClass">{{ t("api_keys.modal.permissions_label") }}</legend>
                        <button
                            type="button"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="toggleAll(form)"
                        >
                            {{ allSelected(form) ? t("api_keys.select_none") : t("api_keys.select_all") }}
                        </button>
                    </div>
                    <div
                        class="mt-2 grid max-h-56 grid-cols-1 gap-x-4 gap-y-1 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700/60"
                    >
                        <label v-for="permission in availablePermissions" :key="permission" class="flex min-h-8 items-center gap-2.5">
                            <input
                                v-model="form.permissions"
                                type="checkbox"
                                :value="permission"
                                class="h-4 w-4 shrink-0 accent-indigo-600"
                            />
                            <span class="text-sm text-slate-800 dark:text-slate-200">{{ permissionLabel(permission) }}</span>
                        </label>
                    </div>
                </fieldset>

                <div>
                    <label for="api-key-expires" :class="labelClass">{{ t("api_keys.modal.expires_label") }}</label>
                    <input
                        id="api-key-expires"
                        v-model="form.expires_at"
                        type="datetime-local"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.expires_at }]"
                    />
                </div>

                <label class="flex items-start gap-2.5">
                    <input v-model="form.is_mcp" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 accent-indigo-600" />
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ t("api_keys.mcp_key") }}
                        </span>
                        <span class="block text-xs text-slate-600 dark:text-slate-400">{{ t("api_keys.mcp_key_hint") }}</span>
                    </span>
                </label>
            </div>
        </FormDialog>

        <!-- The new key, shown this once -->
        <Modal :show="!!newKeyResult" max-width="xl" @close="closeKeyResult">
            <div class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ t("api_keys.modal.success_title") }}
                </h2>
                <div
                    role="alert"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-500/30 dark:bg-amber-500/10"
                >
                    <p class="font-medium text-amber-900 dark:text-amber-200">{{ t("api_keys.modal.warning_title") }}</p>
                    <p class="mt-0.5 text-amber-900 dark:text-amber-200">{{ t("api_keys.modal.warning_text") }}</p>
                </div>
                <div>
                    <p :class="labelClass">{{ t("api_keys.modal.key_label") }}</p>
                    <code
                        class="mt-1 block break-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-sm text-slate-900 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-white"
                    >
                        {{ keyVisible ? newKeyResult?.key : "••••••••••••••••••••••••••••••••••••••••" }}
                    </code>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <SecondaryButton type="button" @click="keyVisible = !keyVisible">
                            {{ keyVisible ? t("api_keys.modal.hide") : t("api_keys.modal.show") }}
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="copyToClipboard(newKeyResult.key)">
                            {{ t("api_keys.modal.copy") }}
                        </SecondaryButton>
                        <span v-if="keyCopied" role="status" class="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                            {{ t("api_keys.modal.copied") }}
                        </span>
                    </div>
                </div>
                <div class="flex justify-end">
                    <PrimaryButton type="button" @click="closeKeyResult">{{ t("api_keys.modal.close") }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <FormDialog
            :show="showEditModal"
            :title="t('api_keys.edit_modal.title')"
            :processing="editForm.processing"
            :errors="messagesOf(editForm)"
            :toast="toast"
            @close="closeEditModal"
            @submit="updateKey"
        >
            <div class="space-y-4">
                <div>
                    <label for="api-key-edit-name" :class="labelClass">{{ t("api_keys.modal.name_label") }}</label>
                    <input
                        id="api-key-edit-name"
                        v-model="editForm.name"
                        type="text"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': editForm.errors.name }]"
                    />
                </div>

                <fieldset>
                    <div class="flex items-center justify-between gap-3">
                        <legend :class="labelClass">{{ t("api_keys.modal.permissions_label") }}</legend>
                        <button
                            type="button"
                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="toggleAll(editForm)"
                        >
                            {{ allSelected(editForm) ? t("api_keys.select_none") : t("api_keys.select_all") }}
                        </button>
                    </div>
                    <div
                        class="mt-2 grid max-h-56 grid-cols-1 gap-x-4 gap-y-1 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700/60"
                    >
                        <label v-for="permission in availablePermissions" :key="permission" class="flex min-h-8 items-center gap-2.5">
                            <input
                                v-model="editForm.permissions"
                                type="checkbox"
                                :value="permission"
                                class="h-4 w-4 shrink-0 accent-indigo-600"
                            />
                            <span class="text-sm text-slate-800 dark:text-slate-200">{{ permissionLabel(permission) }}</span>
                        </label>
                    </div>
                </fieldset>

                <label class="flex items-start gap-2.5">
                    <input v-model="editForm.is_mcp" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 accent-indigo-600" />
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ t("api_keys.mcp_key") }}
                        </span>
                        <span class="block text-xs text-slate-600 dark:text-slate-400">{{ t("api_keys.mcp_key_hint") }}</span>
                    </span>
                </label>

                <!-- The MCP connection test needs the key itself, and only its owner has it -->
                <div v-if="editForm.is_mcp">
                    <label for="api-key-plain" :class="labelClass">{{ t("api_keys.mcp_plain_key_label") }}</label>
                    <input
                        id="api-key-plain"
                        v-model="editForm.plain_key"
                        type="text"
                        autocomplete="off"
                        :placeholder="t('api_keys.mcp_plain_key_placeholder')"
                        class="mt-1 font-mono"
                        :class="[fieldClass, { '!border-rose-500': editForm.errors.plain_key }]"
                    />
                    <p :class="hintClass">{{ t("api_keys.mcp_plain_key_hint") }}</p>
                </div>
            </div>
        </FormDialog>

        <ConfirmModal
            :show="!!keyToDelete"
            type="danger"
            :title="t('api_keys.delete_confirm_title')"
            :message="t('api_keys.delete_confirm')"
            :confirm-text="t('common.delete')"
            :processing="deleting"
            @close="keyToDelete = null"
            @confirm="deleteKey"
        >
            {{ keyToDelete?.name }} ({{ keyToDelete?.key_prefix }}…)
        </ConfirmModal>

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="dialogOpen ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
