<script setup>
import { ref, computed } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import DangerButton from "@/Components/DangerButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const page = usePage();

// API base URL
const originUrl = computed(() =>
    typeof window !== "undefined" ? window.location.origin : ""
);

const props = defineProps({
    apiKeys: Array,
    availablePermissions: Array,
});

// Modal state
const showCreateModal = ref(false);
const newKeyResult = ref(null);
const keyVisible = ref(false);
const copySuccess = ref(false);
const keyCopied = ref(false);

// Delete Modal state
const showDeleteConfirmModal = ref(false);
const keyToDeleteId = ref(null);

const form = useForm({
    name: "",
    permissions: [],
    expires_at: "",
    is_mcp: false,
});

// Edit Modal state
const showEditModal = ref(false);
const editingKey = ref(null);
const editForm = useForm({
    name: "",
    permissions: [],
    is_mcp: false,
    plain_key: "",
});

const permissionLabels = computed(() => ({
    "subscribers:read": {
        label: t("api_keys.permissions.subscribers_read"),
        icon: "📧",
        color: "blue",
    },
    "subscribers:write": {
        label: t("api_keys.permissions.subscribers_write"),
        icon: "✏️",
        color: "green",
    },
    "lists:read": {
        label: t("api_keys.permissions.lists_read"),
        icon: "📋",
        color: "purple",
    },
    "tags:read": {
        label: t("api_keys.permissions.tags_read"),
        icon: "🏷️",
        color: "orange",
    },
}));

function openCreateModal() {
    form.reset();
    form.permissions = [...props.availablePermissions]; // All permissions by default
    form.is_mcp = false;
    newKeyResult.value = null;
    keyVisible.value = false;
    keyCopied.value = false;
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
    if (newKeyResult.value) {
        // Refresh the page to show new key in list
        router.reload();
    }
}

async function createKey() {
    try {
        const response = await fetch(route("settings.api-keys.store"), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                name: form.name,
                permissions: form.permissions,
                expires_at: form.expires_at || null,
                is_mcp: form.is_mcp,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            newKeyResult.value = data;
            keyVisible.value = true;
        } else {
            const error = await response.json();
            alert(error.message || t("common.error"));
        }
    } catch (e) {
        alert(t("common.error"));
    }
}

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        copySuccess.value = true;
        keyCopied.value = true;
        setTimeout(() => (copySuccess.value = false), 2000);
    } catch (e) {
        const textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        copySuccess.value = true;
        keyCopied.value = true;
        setTimeout(() => (copySuccess.value = false), 2000);
    }
}

function confirmDeleteKey(keyId) {
    keyToDeleteId.value = keyId;
    showDeleteConfirmModal.value = true;
}

function closeModal() {
    showDeleteConfirmModal.value = false;
    keyToDeleteId.value = null;
}

function deleteKey() {
    if (keyToDeleteId.value) {
        router.delete(route("settings.api-keys.destroy", keyToDeleteId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onFinish: () => closeModal(),
        });
    }
}

function openEditModal(key) {
    editingKey.value = key;
    editForm.name = key.name;
    editForm.permissions = [...(key.permissions || [])];
    editForm.is_mcp = key.is_mcp || false;
    editForm.plain_key = "";
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingKey.value = null;
}

function updateKey() {
    if (!editingKey.value) return;

    router.put(route("settings.api-keys.update", editingKey.value.id), {
        name: editForm.name,
        permissions: editForm.permissions,
        is_mcp: editForm.is_mcp,
        plain_key: editForm.plain_key || null,
    }, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
}

const { locale } = useI18n();
function formatDateTime(dateString) {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleString(locale.value);
}
</script>

<template>
    <Head :title="t('api_keys.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold text-gray-800 dark:text-gray-100"
                >
                    🔑 {{ t("api_keys.title") }}
                </h2>
                <button
                    @click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors flex items-center gap-2"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ t("api_keys.generate_new") }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- API Documentation Link -->
                <div
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="h-5 w-5 text-blue-500 mt-0.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        <div>
                            <p
                                class="text-sm font-medium text-blue-800 dark:text-blue-200"
                            >
                                {{ t("api_keys.docs.title") }}
                            </p>
                            <p
                                class="text-sm text-blue-600 dark:text-blue-300 mt-1"
                            >
                                {{ t("api_keys.docs.text") }}
                                <a
                                    href="/docs/api"
                                    target="_blank"
                                    class="underline font-medium hover:text-blue-800 dark:hover:text-blue-100"
                                >
                                    /docs/api
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- API Keys List -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-white"
                        >
                            {{ t("api_keys.title") }}
                        </h3>
                        <p
                            class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {{ t("api_keys.subtitle") }}
                        </p>
                    </div>

                    <div
                        v-if="apiKeys.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <svg
                            class="w-12 h-12 mx-auto text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                            />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            {{ t("api_keys.empty_text") }}
                        </p>
                        <button
                            @click="openCreateModal"
                            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors"
                        >
                            {{ t("api_keys.generate_first") }}
                        </button>
                    </div>

                    <table
                        v-else
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.name") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.key") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.permissions") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.last_used") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.created") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("api_keys.table.actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="key in apiKeys"
                                :key="key.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <span
                                            class="font-medium text-gray-900 dark:text-white"
                                            >{{ key.name }}</span
                                        >
                                        <span
                                            v-if="key.is_mcp"
                                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                                        >
                                            🤖 MCP
                                        </span>
                                        <span
                                            v-if="key.is_expired"
                                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                                        >
                                            {{ t("api_keys.status.expired") }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <code
                                        class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono text-gray-600 dark:text-gray-300"
                                    >
                                        {{ key.key_prefix }}...
                                    </code>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="perm in key.permissions"
                                            :key="perm"
                                            class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                                        >
                                            {{ permissionLabels[perm]?.icon }}
                                            {{
                                                permissionLabels[perm]?.label ||
                                                perm
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        key.last_used_at
                                            ? formatDateTime(key.last_used_at)
                                            : t("api_keys.status.never_used")
                                    }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatDateTime(key.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="openEditModal(key)"
                                            class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                                            :title="t('api_keys.edit') || 'Edytuj'"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="confirmDeleteKey(key.id)"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- API Usage Examples -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3
                        class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                    >
                        💡 {{ t("api_keys.examples.title") }}
                    </h3>
                    <div
                        class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto"
                    >
                        <pre>
# {{ t("api_keys.examples.get_subscribers") }}
curl -H "Authorization: Bearer ns_live_TWOJ_KLUCZ" \
     {{ originUrl }}/api/v1/subscribers

# {{ t("api_keys.examples.add_subscriber") }}
curl -X POST \
     -H "Authorization: Bearer ns_live_TWOJ_KLUCZ" \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","contact_list_id":1}' \
     {{ originUrl }}/api/v1/subscribers</pre
                        >
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Key Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal">
            <div class="p-6">
                <!-- Before key is created -->
                <div v-if="!newKeyResult">
                    <h3
                        class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                    >
                        🔑 {{ t("api_keys.modal.title") }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ t("api_keys.modal.name_label") }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="
                                    t('api_keys.modal.name_placeholder')
                                "
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                {{ t("api_keys.modal.permissions_label") }}
                            </label>
                            <div class="space-y-2">
                                <label
                                    v-for="perm in availablePermissions"
                                    :key="perm"
                                    class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.permissions"
                                        :value="perm"
                                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                    />
                                    <span class="text-lg">{{
                                        permissionLabels[perm]?.icon
                                    }}</span>
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            permissionLabels[perm]?.label ||
                                            perm
                                        }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ t("api_keys.modal.expires_label") }}
                            </label>
                            <input
                                v-model="form.expires_at"
                                type="datetime-local"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- MCP Key Checkbox -->
                        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_mcp"
                                    class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500"
                                />
                                <div>
                                    <span class="font-medium text-purple-800 dark:text-purple-200">
                                        🤖 {{ t("api_keys.mcp_key") || "Klucz MCP" }}
                                    </span>
                                    <p class="text-sm text-purple-600 dark:text-purple-300 mt-0.5">
                                        {{ t("api_keys.mcp_key_hint") || "Użyj tego klucza do integracji z Claude, Cursor lub VS Code" }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeCreateModal">
                            {{ t("api_keys.modal.cancel") }}
                        </SecondaryButton>
                        <button
                            @click="createKey"
                            :disabled="
                                !form.name || form.permissions.length === 0
                            "
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-50 transition-colors"
                        >
                            {{ t("api_keys.modal.generate") }}
                        </button>
                    </div>
                </div>

                <!-- After key is created -->
                <div v-else>
                    <div class="text-center mb-4">
                        <div
                            class="w-16 h-16 mx-auto bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mb-4"
                        >
                            <svg
                                class="w-8 h-8 text-green-600 dark:text-green-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-white"
                        >
                            {{ t("api_keys.modal.success_title") }}
                        </h3>
                    </div>

                    <div
                        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-4"
                    >
                        <div class="flex items-start gap-2">
                            <svg
                                class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            <div>
                                <p
                                    class="text-sm font-medium text-amber-800 dark:text-amber-200"
                                >
                                    {{ t("api_keys.modal.warning_title") }}
                                </p>
                                <p
                                    class="text-sm text-amber-700 dark:text-amber-300 mt-1"
                                >
                                    {{ t("api_keys.modal.warning_text") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("api_keys.modal.key_label") }}
                        </label>
                        <div class="flex items-center gap-2">
                            <code
                                class="flex-1 px-3 py-2 bg-gray-900 text-green-400 rounded-lg text-sm font-mono overflow-x-auto"
                            >
                                {{
                                    keyVisible
                                        ? newKeyResult.key
                                        : "••••••••••••••••••••••••••••••••••••"
                                }}
                            </code>
                            <button
                                @click="keyVisible = !keyVisible"
                                class="px-3 py-2 bg-gray-200 dark:bg-gray-600 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500"
                                :title="
                                    keyVisible
                                        ? t('api_keys.modal.hide')
                                        : t('api_keys.modal.show')
                                "
                            >
                                {{ keyVisible ? "👁️" : "👁️‍🗨️" }}
                            </button>
                            <button
                                @click="copyToClipboard(newKeyResult.key)"
                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500"
                            >
                                {{
                                    copySuccess
                                        ? "✓ " + t("api_keys.modal.copied")
                                        : "📋 " + t("api_keys.modal.copy")
                                }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="closeCreateModal"
                            :class="[
                                'px-4 py-2 rounded-lg transition-colors',
                                keyCopied
                                    ? 'bg-green-600 text-white hover:bg-green-500'
                                    : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-500',
                            ]"
                        >
                            {{
                                keyCopied
                                    ? "✓ " + t("api_keys.modal.close")
                                    : t("api_keys.modal.close")
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteConfirmModal" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ t("api_keys.delete_confirm_title") || "Delete API Key" }}
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t("api_keys.delete_confirm") }}
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        {{ t("common.cancel") || "Cancel" }}
                    </SecondaryButton>

                    <DangerButton class="ml-3" @click="deleteKey">
                        {{ t("common.delete") || "Delete" }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Edit Key Modal -->
        <Modal :show="showEditModal" @close="closeEditModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    ✏️ {{ t("api_keys.edit_modal.title") || "Edytuj klucz API" }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("api_keys.modal.name_label") }}
                        </label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ t("api_keys.modal.permissions_label") }}
                        </label>
                        <div class="space-y-2">
                            <label
                                v-for="perm in availablePermissions"
                                :key="perm"
                                class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    v-model="editForm.permissions"
                                    :value="perm"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                />
                                <span class="text-lg">{{ permissionLabels[perm]?.icon }}</span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ permissionLabels[perm]?.label || perm }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- MCP Key Checkbox -->
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="editForm.is_mcp"
                                class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500"
                            />
                            <div>
                                <span class="font-medium text-purple-800 dark:text-purple-200">
                                    🤖 {{ t("api_keys.mcp_key") || "Klucz MCP" }}
                                </span>
                                <p class="text-sm text-purple-600 dark:text-purple-300 mt-0.5">
                                    {{ t("api_keys.mcp_key_hint") || "Użyj tego klucza do integracji z Claude, Cursor lub VS Code" }}
                                </p>
                            </div>
                        </label>

                        <!-- Plain Key Input (shown when MCP is checked) -->
                        <div v-if="editForm.is_mcp" class="mt-4 pt-4 border-t border-purple-200 dark:border-purple-700">
                            <label class="block text-sm font-medium text-purple-800 dark:text-purple-200 mb-1">
                                🔑 {{ t("api_keys.mcp_plain_key_label") || "Klucz API do szyfrowania" }}
                            </label>
                            <input
                                v-model="editForm.plain_key"
                                type="text"
                                :placeholder="t('api_keys.mcp_plain_key_placeholder') || 'ns_live_...'"
                                class="w-full px-3 py-2 border border-purple-300 dark:border-purple-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white font-mono text-sm"
                            />
                            <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                {{ t("api_keys.mcp_plain_key_hint") || "Wklej swój klucz API, aby umożliwić testowanie połączenia MCP" }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeEditModal">
                        {{ t("common.cancel") || "Anuluj" }}
                    </SecondaryButton>
                    <button
                        @click="updateKey"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors"
                    >
                        {{ t("common.save") || "Zapisz" }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
