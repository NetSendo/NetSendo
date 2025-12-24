<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import DangerButton from "@/Components/DangerButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    providers: {
        type: Array,
        default: () => [],
    },
    availableProviders: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showModal = ref(false);
const modalMode = ref("create");
const editingProvider = ref(null);
const testingProvider = ref(null);
const testResult = ref(null);
const showPassword = ref({});
const loadingFields = ref(false);
const credentialFields = ref([]);

// Delete modal state
const showDeleteModal = ref(false);
const deletingProvider = ref(null);

// Toast notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

// Form
const form = useForm({
    name: "",
    provider: "twilio",
    credentials: {},
    from_number: "",
    from_name: "",
    is_active: true,
    is_default: false,
    daily_limit: null,
});

// Provider icons
const providerIcons = {
    twilio: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.381 0 0 5.381 0 12s5.381 12 12 12 12-5.381 12-12S18.619 0 12 0zm0 20.4c-4.637 0-8.4-3.763-8.4-8.4S7.363 3.6 12 3.6s8.4 3.763 8.4 8.4-3.763 8.4-8.4 8.4zm3.6-8.4c0 1.988-1.612 3.6-3.6 3.6s-3.6-1.612-3.6-3.6 1.612-3.6 3.6-3.6 3.6 1.612 3.6 3.6z"/></svg>`,
    smsapi: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>`,
    smsapi_com: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>`,
    vonage: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18l7.27 3.64L12 11.45 4.73 7.82 12 4.18zM4 9.1l7 3.5v7.3l-7-3.5V9.1zm9 10.8v-7.3l7-3.5v7.3l-7 3.5z"/></svg>`,
    messagebird: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>`,
    plivo: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l4.59-4.58L18 11l-6 6z"/></svg>`,
};

// Provider colors
const providerColors = {
    twilio: "#F22F46",
    smsapi: "#00A7E1",
    smsapi_com: "#00A7E1",
    vonage: "#7D4CDB",
    messagebird: "#2481D7",
    plivo: "#00C853",
};

// Load credential fields for provider
const loadCredentialFields = async (provider) => {
    loadingFields.value = true;
    try {
        const response = await fetch(
            route("settings.sms-providers.fields", provider),
            {
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );
        const data = await response.json();
        credentialFields.value = data.fields || [];
    } catch (error) {
        console.error("Failed to load fields:", error);
        credentialFields.value = [];
    } finally {
        loadingFields.value = false;
    }
};

// Watch provider changes
watch(
    () => form.provider,
    async (newProvider) => {
        if (
            editingProvider.value &&
            newProvider === editingProvider.value.provider
        ) {
            return;
        }
        form.credentials = {};
        showPassword.value = {};
        await loadCredentialFields(newProvider);
    }
);

// Open modal for creating/editing
const openModal = async (provider = null) => {
    if (provider) {
        modalMode.value = "edit";
        editingProvider.value = provider;
        form.name = provider.name;
        form.provider = provider.provider;
        form.from_number = provider.from_number || "";
        form.from_name = provider.from_name || "";
        form.is_active = provider.is_active;
        form.is_default = provider.is_default;
        form.daily_limit = provider.daily_limit;
        form.credentials = {};
    } else {
        modalMode.value = "create";
        editingProvider.value = null;
        form.reset();
        form.provider = "twilio";
        form.is_active = true;
    }
    testResult.value = null;
    showPassword.value = {};
    await loadCredentialFields(form.provider);
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingProvider.value = null;
    form.reset();
    testResult.value = null;
};

// Submit form
const submitForm = () => {
    if (modalMode.value === "edit") {
        form.put(
            route("settings.sms-providers.update", editingProvider.value.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    showToast(t("sms_providers.notifications.updated"));
                },
                onError: () => {
                    showToast(t("common.notifications.error"), false);
                },
            }
        );
    } else {
        form.post(route("settings.sms-providers.store"), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast(t("sms_providers.notifications.created"));
            },
            onError: () => {
                showToast(t("common.notifications.error"), false);
            },
        });
    }
};

// Test connection
const testConnection = async (provider) => {
    testingProvider.value = provider.id;
    testResult.value = null;

    try {
        const response = await fetch(
            route("settings.sms-providers.test", provider.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );

        const data = await response.json();
        testResult.value = data;
        showToast(data.message, data.success);

        router.reload({ only: ["providers"] });
    } catch (error) {
        showToast(
            t("common.notifications.error") + ": " + error.message,
            false
        );
    } finally {
        testingProvider.value = null;
    }
};

// Set as default
const setDefault = (provider) => {
    router.post(
        route("settings.sms-providers.default", provider.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(t("sms_providers.notifications.set_default"));
            },
        }
    );
};

// Open delete confirmation modal
const confirmDelete = (provider) => {
    deletingProvider.value = provider;
    showDeleteModal.value = true;
};

// Close delete modal
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingProvider.value = null;
};

// Delete provider
const deleteProvider = () => {
    if (!deletingProvider.value) return;

    router.delete(
        route("settings.sms-providers.destroy", deletingProvider.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
                showToast(t("sms_providers.notifications.deleted"));
            },
        }
    );
};

// Toggle active status
const toggleActive = (provider) => {
    router.put(
        route("settings.sms-providers.update", provider.id),
        {
            name: provider.name,
            from_number: provider.from_number,
            from_name: provider.from_name,
            is_active: !provider.is_active,
            is_default: provider.is_default,
            daily_limit: provider.daily_limit,
        },
        {
            preserveScroll: true,
        }
    );
};

// Get status class
const getStatusClass = (provider) => {
    if (!provider.is_active) {
        return "bg-slate-500/20 text-slate-400";
    }
    if (provider.last_test_status === "success") {
        return "bg-emerald-500/20 text-emerald-400";
    }
    if (provider.last_test_status === "failed") {
        return "bg-rose-500/20 text-rose-400";
    }
    return "bg-blue-500/20 text-blue-400";
};

// Get status text
const getStatusText = (provider) => {
    if (!provider.is_active) {
        return t("sms_providers.status.inactive");
    }
    if (provider.last_test_status === "success") {
        return t("sms_providers.status.active");
    }
    if (provider.last_test_status === "failed") {
        return t("sms_providers.status.error");
    }
    return t("sms_providers.status.not_tested");
};
</script>

<template>
    <Head :title="$t('sms_providers.title')" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="toast"
                    class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 rounded-xl px-5 py-4 shadow-lg"
                    :class="
                        toast.success
                            ? 'bg-emerald-600 text-white'
                            : 'bg-rose-600 text-white'
                    "
                >
                    <svg
                        v-if="toast.success"
                        class="h-5 w-5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span class="font-medium">{{ toast.message }}</span>
                    <button
                        @click="toast = null"
                        class="ml-2 opacity-80 hover:opacity-100"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    {{ $t("sms_providers.title") }}
                </h2>
                <button
                    @click="openModal()"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    <svg
                        class="h-5 w-5"
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
                    {{ $t("sms_providers.add_new") }}
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header Info -->
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t("sms_providers.subtitle") }}
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-if="providers.length === 0"
                class="rounded-xl border-2 border-dashed border-gray-300 p-12 text-center dark:border-slate-700"
            >
                <svg
                    class="mx-auto h-12 w-12 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                </svg>
                <h3
                    class="mt-4 text-lg font-medium text-gray-900 dark:text-white"
                >
                    {{ $t("sms_providers.empty.title") }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t("sms_providers.empty.description") }}
                </p>
                <button
                    @click="openModal()"
                    class="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    <svg
                        class="h-5 w-5"
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
                    {{ $t("sms_providers.add_first") }}
                </button>
            </div>

            <!-- Provider Cards Grid -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="provider in providers"
                    :key="provider.id"
                    class="group relative overflow-hidden rounded-xl border bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    :class="
                        provider.is_default
                            ? 'border-indigo-300 dark:border-indigo-700'
                            : 'border-slate-200'
                    "
                >
                    <!-- Default Badge -->
                    <div
                        v-if="provider.is_default"
                        class="absolute right-3 top-3"
                    >
                        <span
                            class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            {{ $t("sms_providers.default") }}
                        </span>
                    </div>

                    <!-- Provider Icon & Name -->
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl"
                            :style="{
                                backgroundColor:
                                    providerColors[provider.provider] + '20',
                            }"
                        >
                            <div
                                class="h-6 w-6"
                                :style="{
                                    color: providerColors[provider.provider],
                                }"
                                v-html="providerIcons[provider.provider]"
                            ></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3
                                class="font-semibold text-gray-900 dark:text-white truncate"
                            >
                                {{ provider.name }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 truncate"
                            >
                                {{
                                    provider.from_number ||
                                    provider.from_name ||
                                    provider.provider_label
                                }}
                            </p>
                        </div>

                        <!-- Active toggle -->
                        <button
                            @click="toggleActive(provider)"
                            class="relative h-6 w-11 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            :class="
                                provider.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300 dark:bg-slate-600'
                            "
                        >
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                :class="
                                    provider.is_active
                                        ? 'translate-x-5'
                                        : 'translate-x-0'
                                "
                            ></span>
                        </button>
                    </div>

                    <!-- Status Badge -->
                    <div class="mt-4">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="getStatusClass(provider)"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        provider.is_active &&
                                        provider.last_test_status === 'success',
                                    'bg-rose-500':
                                        provider.last_test_status === 'failed',
                                    'bg-blue-500':
                                        provider.is_active &&
                                        !provider.last_test_status,
                                    'bg-slate-400': !provider.is_active,
                                }"
                            ></span>
                            {{ getStatusText(provider) }}
                        </span>
                    </div>

                    <!-- Daily Limit -->
                    <div v-if="provider.daily_limit" class="mt-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("sms_providers.sent_today") }}:
                            <span class="font-medium"
                                >{{ provider.sent_today || 0 }} /
                                {{ provider.daily_limit }}</span
                            >
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex items-center gap-2">
                        <button
                            @click="openModal(provider)"
                            class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600"
                        >
                            {{ $t("common.edit") }}
                        </button>

                        <button
                            @click="testConnection(provider)"
                            :disabled="testingProvider === provider.id"
                            class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-gray-200 disabled:opacity-50 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-slate-600"
                            :title="$t('sms_providers.test_connection')"
                        >
                            <svg
                                v-if="testingProvider === provider.id"
                                class="h-5 w-5 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <svg
                                v-else
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </button>

                        <button
                            v-if="!provider.is_default"
                            @click="setDefault(provider)"
                            class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-400"
                            :title="$t('sms_providers.set_as_default')"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                />
                            </svg>
                        </button>

                        <button
                            @click="confirmDelete(provider)"
                            class="rounded-lg bg-gray-100 p-2 text-rose-600 transition-colors hover:bg-rose-50 dark:bg-slate-700 dark:hover:bg-rose-900/20"
                            :title="$t('common.delete')"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div
                class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
            >
                <div class="flex gap-3">
                    <svg
                        class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-medium">
                            {{ $t("sms_providers.info.title") }}
                        </p>
                        <ul
                            class="mt-2 list-inside list-disc space-y-1 text-blue-700 dark:text-blue-300"
                        >
                            <li>
                                <strong>Twilio:</strong>
                                {{ $t("sms_providers.info.twilio") }}
                            </li>
                            <li>
                                <strong>SMS API (PL):</strong>
                                {{ $t("sms_providers.info.smsapi") }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration Modal -->
        <Teleport to="body">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div
                    class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 max-h-[90vh] overflow-y-auto"
                >
                    <!-- Modal Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                modalMode === "edit"
                                    ? $t("sms_providers.modal.edit_title")
                                    : $t("sms_providers.modal.add_title")
                            }}
                        </h3>
                        <button
                            @click="closeModal"
                            class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <!-- Provider Selection -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("sms_providers.modal.provider") }}
                            </label>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <button
                                    v-for="availableProvider in availableProviders"
                                    :key="availableProvider.id"
                                    type="button"
                                    @click="
                                        form.provider = availableProvider.id
                                    "
                                    class="flex flex-col items-center gap-2 rounded-lg border-2 p-3 transition-all"
                                    :class="
                                        form.provider === availableProvider.id
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-slate-600 dark:hover:border-slate-500'
                                    "
                                >
                                    <div
                                        class="h-6 w-6"
                                        :style="{
                                            color: providerColors[
                                                availableProvider.id
                                            ],
                                        }"
                                        v-html="
                                            providerIcons[availableProvider.id]
                                        "
                                    ></div>
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ availableProvider.name }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("sms_providers.modal.name") }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="
                                    $t('sms_providers.modal.name_placeholder')
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :class="{ 'border-rose-500': form.errors.name }"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-sm text-rose-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- From Number (Twilio) -->
                        <div v-if="form.provider === 'twilio'">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("sms_providers.modal.from_number") }}
                            </label>
                            <input
                                v-model="form.from_number"
                                type="text"
                                placeholder="+48123456789"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <!-- From Name (SMS API) -->
                        <div v-if="form.provider !== 'twilio'">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("sms_providers.modal.from_name") }}
                            </label>
                            <input
                                v-model="form.from_name"
                                type="text"
                                placeholder="NetSendo"
                                maxlength="11"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $t("sms_providers.modal.from_name_hint") }}
                            </p>
                        </div>

                        <!-- Dynamic Credential Fields -->
                        <div
                            v-if="loadingFields"
                            class="flex items-center justify-center py-4"
                        >
                            <svg
                                class="h-6 w-6 animate-spin text-indigo-600"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                        </div>
                        <template v-else>
                            <div
                                v-for="field in credentialFields"
                                :key="field.name"
                            >
                                <template v-if="field.type === 'checkbox'">
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="
                                                form.credentials[field.name]
                                            "
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                        />
                                        <span
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                            >{{ field.label }}</span
                                        >
                                    </label>
                                    <p
                                        v-if="field.hint"
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{ field.hint }}
                                    </p>
                                </template>
                                <template v-else>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ field.label }}
                                        <span
                                            v-if="field.required"
                                            class="text-rose-500"
                                            >*</span
                                        >
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model="
                                                form.credentials[field.name]
                                            "
                                            :type="
                                                field.type === 'password' &&
                                                !showPassword[field.name]
                                                    ? 'password'
                                                    : 'text'
                                            "
                                            :placeholder="
                                                field.placeholder || ''
                                            "
                                            :required="
                                                field.required &&
                                                modalMode === 'create'
                                            "
                                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        />
                                        <button
                                            v-if="field.type === 'password'"
                                            type="button"
                                            @click="
                                                showPassword[field.name] =
                                                    !showPassword[field.name]
                                            "
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        >
                                            <svg
                                                v-if="showPassword[field.name]"
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.05 6.05m7.071 7.071l3.535 3.536M3 3l18 18"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                    <p
                                        v-if="field.hint"
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{ field.hint }}
                                    </p>
                                </template>
                            </div>
                        </template>

                        <!-- Daily Limit -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("sms_providers.modal.daily_limit") }}
                            </label>
                            <input
                                v-model="form.daily_limit"
                                type="number"
                                min="1"
                                :placeholder="
                                    $t(
                                        'sms_providers.modal.daily_limit_placeholder'
                                    )
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <!-- Active & Default Toggles -->
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t("sms_providers.modal.is_active")
                                    }}</span
                                >
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t("sms_providers.modal.is_default")
                                    }}</span
                                >
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end gap-3 pt-4">
                            <SecondaryButton type="button" @click="closeModal">
                                {{ $t("common.cancel") }}
                            </SecondaryButton>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                {{
                                    modalMode === "edit"
                                        ? $t("common.save")
                                        : $t("common.add")
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    {{ $t("sms_providers.delete_modal.title") }}
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t("sms_providers.delete_modal.message") }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <DangerButton @click="deleteProvider">
                        {{ $t("common.delete") }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
