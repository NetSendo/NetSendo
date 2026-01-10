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
    mailboxes: {
        type: Array,
        default: () => [],
    },
    providers: {
        type: Object,
        default: () => ({}),
    },
    providerFields: {
        type: Object,
        default: () => ({}),
    },
    messageTypes: {
        type: Object,
        default: () => ({}),
    },
    gmail_configured: {
        type: Boolean,
        default: false,
    },
    google_integrations: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showModal = ref(false);
const modalMode = ref("create");
const editingMailbox = ref(null);
const testingMailbox = ref(null);
const testResult = ref(null);
const showPassword = ref({});

// Delete modal state
const showDeleteModal = ref(false);
const deletingMailbox = ref(null);

// Error details modal state
const showErrorModal = ref(false);
const errorMailbox = ref(null);

const showErrorDetails = (mailbox) => {
    errorMailbox.value = mailbox;
    showErrorModal.value = true;
};

const closeErrorModal = () => {
    showErrorModal.value = false;
    errorMailbox.value = null;
};

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
    provider: "smtp",
    from_email: "",
    from_name: "",
    reply_to: "",
    is_active: true,
    allowed_types: ["broadcast", "autoresponder", "system"],
    credentials: {},
    daily_limit: null,
    time_restriction: 2,
    google_integration_id: null,
});

// Provider icons
const providerIcons = {
    smtp: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M2 6l10 7 10-7"/></svg>`,
    sendgrid: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M1.344 0v8.009h8.009V0zm6.912 6.912H2.441V1.097h5.815zM10.49 0v8.009h8.009V0zm6.912 6.912h-5.815V1.097h5.815zM5.613 8.009v8.009h8.009V8.009zm6.912 6.912H6.71v-5.815h5.815zM14.759 8.009v8.009h8.009V8.009zm6.912 6.912h-5.815v-5.815h5.815zM1.344 16.018v8.009h8.009v-8.009zm6.912 6.912H2.441v-5.815h5.815zM10.49 16.018v8.009h8.009v-8.009zm6.912 6.912h-5.815v-5.815h5.815z"/></svg>`,
    gmail: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/></svg>`,
};

// Provider colors
const providerColors = {
    smtp: "#6366F1",
    sendgrid: "#1A82E2",
    gmail: "#EA4335",
};

// Current provider fields
const currentProviderFields = computed(() => {
    return props.providerFields[form.provider] || [];
});

// Watch provider changes to update allowed_types
// Watch provider changes to update allowed_types
// Watch provider changes to update allowed_types
watch(
    () => form.provider,
    (newProvider) => {
        if (newProvider === "gmail") {
            // Gmail does NOT support broadcast via API in this system version
            // Remove broadcast from allowed_types
            form.allowed_types = form.allowed_types.filter(
                (type) => type !== "broadcast"
            );
        } else {
            // For other providers, ensure we default to all unless edited
            if (!editingMailbox.value) {
                if (!form.allowed_types.includes("broadcast")) {
                    form.allowed_types.push("broadcast");
                }
            }
        }

        // If we are initializing the form for editing (provider matches the mailbox's provider),
        // don't reset credentials.
        if (
            editingMailbox.value &&
            newProvider === editingMailbox.value.provider
        ) {
            return;
        }

        // Reset credentials when provider changes
        form.credentials = {};
        showPassword.value = {};
    }
);

// Open modal for creating/editing
const openModal = (mailbox = null) => {
    if (mailbox) {
        // Edit mode
        modalMode.value = "edit";
        editingMailbox.value = mailbox;
        form.name = mailbox.name;
        form.provider = mailbox.provider;
        form.from_email = mailbox.from_email;
        form.from_name = mailbox.from_name;
        form.reply_to = mailbox.reply_to;
        form.is_active = mailbox.is_active;
        form.allowed_types = mailbox.allowed_types || [
            "broadcast",
            "autoresponder",
            "system",
        ];
        // Populate credentials (safe fields only)
        form.credentials = mailbox.credentials || {};
        form.daily_limit = mailbox.daily_limit;
        form.time_restriction = mailbox.time_restriction;
        form.google_integration_id = mailbox.google_integration_id || null;
    } else {
        // Create mode
        modalMode.value = "create";
        editingMailbox.value = null;
        form.reset();
        form.provider = "smtp";
        form.is_active = true;
        form.is_active = true;
        form.allowed_types = ["broadcast", "autoresponder", "system"];
        form.time_restriction = 2;
    }
    testResult.value = null;
    showPassword.value = {};
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingMailbox.value = null;
    form.reset();
    testResult.value = null;
};

// Submit form
const submitForm = () => {
    // PRE-SUBMISSION CLEANUP
    // If we are submitting GMAIL, we must clear fields that might have been autofilled
    // by the browser in the hidden SMTP/Sendgrid tabs. The backend validates these
    // if we send them, even if we are in "gmail" mode, because they are just requests fields.
    if (form.provider === "gmail") {
        form.from_email = ""; // Backend will set default
        form.reply_to = null;
        form.credentials = {}; // Ensure no garbage credentials are sent
    }

    if (modalMode.value === "edit") {
        form.put(route("settings.mailboxes.update", editingMailbox.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast(t("mailboxes.notifications.updated"));
            },
            onError: () => {
                showToast(t("common.notifications.error"), false);
            },
        });
    } else {
        form.post(route("settings.mailboxes.store"), {
            preserveScroll: true,
            onSuccess: (page) => {
                showToast(t("mailboxes.notifications.created"));

                // UX IMPROVEMENT: If we just created a GMAIL mailbox, it is NOT connected yet.
                // We should immediately re-open the modal in "edit" mode so the user sees the "Connect" button.
                if (form.provider === "gmail") {
                    // Find the newly created mailbox. It should be the last one or we can find by name.
                    // Since specific logic to find "the one we just made" can be tricky with lists,
                    // we'll try to match by name or just find the one that is gmail and not connected.
                    const newMailbox = props.mailboxes.find(
                        (m) => m.name === form.name && m.provider === "gmail"
                    );
                    if (newMailbox) {
                        openModal(newMailbox);
                        return; // Don't close modal
                    }
                }

                closeModal();
            },
            onError: () => {
                showToast(t("common.notifications.error"), false);
            },
        });
    }
};

// Test connection
const testConnection = async (mailbox) => {
    testingMailbox.value = mailbox.id;
    testResult.value = null;

    try {
        const response = await fetch(
            route("settings.mailboxes.test", mailbox.id),
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

        // Refresh to update status
        router.reload({ only: ["mailboxes"] });
    } catch (error) {
        showToast(
            t("common.notifications.error") + ": " + error.message,
            false
        );
    } finally {
        testingMailbox.value = null;
    }
};

// Set as default
const setDefault = (mailbox) => {
    router.post(
        route("settings.mailboxes.default", mailbox.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(t("mailboxes.notifications.set_default"));
            },
        }
    );
};

// Open delete confirmation modal
const confirmDelete = (mailbox) => {
    deletingMailbox.value = mailbox;
    showDeleteModal.value = true;
};

// Close delete modal
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingMailbox.value = null;
};

// Delete mailbox
const deleteMailbox = () => {
    if (!deletingMailbox.value) return;

    router.delete(
        route("settings.mailboxes.destroy", deletingMailbox.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
                showToast(t("mailboxes.notifications.deleted"));
            },
        }
    );
};

// Toggle active status
const toggleActive = (mailbox) => {
    router.put(
        route("settings.mailboxes.update", mailbox.id),
        {
            name: mailbox.name,
            provider: mailbox.provider,
            from_email: mailbox.from_email,
            from_name: mailbox.from_name,
            is_active: !mailbox.is_active,
            allowed_types: mailbox.allowed_types,
            daily_limit: mailbox.daily_limit,
            reply_to: mailbox.reply_to,
            time_restriction: mailbox.time_restriction,
            google_integration_id: mailbox.google_integration_id,
        },
        {
            preserveScroll: true,
        }
    );
};

// Get status class
const getStatusClass = (mailbox) => {
    if (!mailbox.is_active) {
        return "bg-slate-500/20 text-slate-400";
    }
    // GMAIL Special Case: Active but not connected
    if (mailbox.provider === "gmail" && !mailbox.gmail_connected) {
        return "bg-amber-500/20 text-amber-500";
    }

    if (mailbox.last_test_success === true) {
        return "bg-emerald-500/20 text-emerald-400";
    }
    if (mailbox.last_test_success === false) {
        return "bg-rose-500/20 text-rose-400";
    }

    // Default active but not tested
    return "bg-blue-500/20 text-blue-400";
};

// Get status text
const getStatusText = (mailbox) => {
    if (!mailbox.is_active) {
        return t("mailboxes.status.inactive");
    }
    // GMAIL Special Case: Active but not connected
    if (mailbox.provider === "gmail" && !mailbox.gmail_connected) {
        return t("mailboxes.status.pending_auth") || "Pending Auth"; // Fallback if translation missing
    }

    if (mailbox.last_test_success === true) {
        return t("mailboxes.status.active");
    }
    if (mailbox.last_test_success === false) {
        return t("mailboxes.status.error");
    }
    return t("mailboxes.status.not_tested");
};

// Disconnect Gmail
const disconnectGmail = () => {
    if (!editingMailbox.value) return;

    router.post(
        route("settings.mailboxes.gmail.disconnect", editingMailbox.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: (page) => {
                // Update the editingMailbox with the fresh instance from the updated props
                const updatedMailbox = props.mailboxes.find(
                    (m) => m.id === editingMailbox.value.id
                );
                if (updatedMailbox) {
                    editingMailbox.value = updatedMailbox;

                    // Update form values if needed (e.g. gmail_connected flag affects UI but logic is driven by mailbox object)
                    // We should also clear any specific fields if they were set?
                    // But mainly we rely on editingMailbox.gmail_connected in the template.
                }
                showToast(t("mailboxes.notifications.disconnected"));
            },
            onError: () => {
                showToast(t("common.notifications.error"), false);
            },
        }
    );
};

// Is broadcast disabled for provider
const isBroadcastDisabled = computed(() => {
    return form.provider === "gmail";
});
</script>

<template>
    <Head :title="$t('mailboxes.title')" />

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
                    {{ $t("mailboxes.title") }}
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
                    {{ $t("mailboxes.add_new") }}
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Header Info -->
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t("mailboxes.subtitle") }}
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-if="mailboxes.length === 0"
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
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                    />
                </svg>
                <h3
                    class="mt-4 text-lg font-medium text-gray-900 dark:text-white"
                >
                    {{ $t("mailboxes.empty.title") }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t("mailboxes.empty.description") }}
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
                    {{ $t("mailboxes.add_first") }}
                </button>
            </div>

            <!-- Mailbox Cards Grid -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="mailbox in mailboxes"
                    :key="mailbox.id"
                    class="group relative overflow-hidden rounded-xl border bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    :class="
                        mailbox.is_default
                            ? 'border-indigo-300 dark:border-indigo-700'
                            : 'border-slate-200'
                    "
                >
                    <!-- Default Badge -->
                    <div
                        v-if="mailbox.is_default"
                        class="absolute right-3 top-3"
                    >
                        <span
                            class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            {{ $t("mailboxes.default") }}
                        </span>
                    </div>

                    <!-- Provider Icon & Name -->
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl"
                            :style="{
                                backgroundColor:
                                    providerColors[mailbox.provider] + '20',
                            }"
                        >
                            <div
                                class="h-6 w-6"
                                :style="{
                                    color: providerColors[mailbox.provider],
                                }"
                                v-html="providerIcons[mailbox.provider]"
                            ></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3
                                class="font-semibold text-gray-900 dark:text-white truncate"
                            >
                                {{ mailbox.name }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 truncate"
                            >
                                {{ mailbox.from_email }}
                            </p>
                        </div>

                        <!-- Active toggle -->
                        <button
                            @click="toggleActive(mailbox)"
                            class="relative h-6 w-11 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            :class="
                                mailbox.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300 dark:bg-slate-600'
                            "
                        >
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                :class="
                                    mailbox.is_active
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
                            :class="getStatusClass(mailbox)"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        mailbox.is_active &&
                                        mailbox.last_test_success === true,
                                    'bg-rose-500':
                                        mailbox.last_test_success === false,
                                    'bg-amber-500':
                                        mailbox.is_active &&
                                        mailbox.last_test_success === null,
                                    'bg-slate-400': !mailbox.is_active,
                                }"
                            ></span>
                            {{ getStatusText(mailbox) }}
                        </span>
                    </div>

                    <!-- Allowed Types -->
                    <div class="mt-3 flex flex-wrap gap-1">
                        <span
                            v-for="type in mailbox.allowed_types"
                            :key="type"
                            class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-slate-700 dark:text-gray-400"
                        >
                            {{ messageTypes[type] || type }}
                        </span>
                    </div>

                    <!-- Daily Limit -->
                    <div v-if="mailbox.daily_limit" class="mt-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("mailboxes.sent_today") }}:
                            <span class="font-medium"
                                >{{ mailbox.sent_today || 0 }} /
                                {{ mailbox.daily_limit }}</span
                            >
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex items-center gap-2">
                        <button
                            @click="openModal(mailbox)"
                            class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600"
                        >
                            {{ $t("common.edit") }}
                        </button>

                        <button
                            @click="testConnection(mailbox)"
                            :disabled="testingMailbox === mailbox.id"
                            class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-gray-200 disabled:opacity-50 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-slate-600"
                            :title="$t('mailboxes.test_connection')"
                        >
                            <svg
                                v-if="testingMailbox === mailbox.id"
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
                            v-if="!mailbox.is_default"
                            @click="setDefault(mailbox)"
                            class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-400"
                            :title="$t('mailboxes.set_as_default')"
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
                            @click="confirmDelete(mailbox)"
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

                    <!-- Last Test Message (if error) -->
                    <div
                        v-if="
                            mailbox.last_test_success === false &&
                            mailbox.last_test_message
                        "
                        class="mt-3"
                    >
                        <button
                            @click="showErrorDetails(mailbox)"
                            class="w-full text-left group"
                        >
                            <p
                                class="text-xs text-rose-600 dark:text-rose-400 truncate group-hover:underline"
                                :title="$t('mailboxes.click_for_details')"
                            >
                                {{ mailbox.last_test_message }}
                            </p>
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
                            {{ $t("mailboxes.info.title") }}
                        </p>
                        <ul
                            class="mt-2 list-inside list-disc space-y-1 text-blue-700 dark:text-blue-300"
                        >
                            <li>
                                <strong>SMTP:</strong>
                                {{ $t("mailboxes.info.smtp") }}
                            </li>
                            <li>
                                <strong>SendGrid:</strong>
                                {{ $t("mailboxes.info.sendgrid") }}
                            </li>
                            <li>
                                <strong>Gmail:</strong>
                                {{ $t("mailboxes.info.gmail") }}
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
                                    ? $t("mailboxes.modal.edit_title")
                                    : $t("mailboxes.modal.add_title")
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
                                {{ $t("mailboxes.modal.provider") }}
                            </label>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                <button
                                    v-for="(provider, key) in providers"
                                    :key="key"
                                    type="button"
                                    @click="form.provider = key"
                                    class="flex flex-col items-center gap-2 rounded-lg border-2 p-3 transition-all"
                                    :class="
                                        form.provider === key
                                            ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-slate-600 dark:hover:border-slate-500'
                                    "
                                >
                                    <div
                                        class="h-6 w-6"
                                        :style="{ color: providerColors[key] }"
                                        v-html="providerIcons[key]"
                                    ></div>
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ provider.label }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Gmail OAuth Info -->
                        <div
                            v-if="form.provider === 'gmail'"
                            class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20"
                        >
                            <div class="flex gap-2">
                                <svg
                                    class="h-5 w-5 flex-shrink-0 text-blue-600"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <p
                                    class="text-sm text-blue-800 dark:text-blue-200"
                                >
                                    {{ $t("mailboxes.modal.gmail_oauth_info") }}
                                </p>
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("mailboxes.modal.name") }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="
                                    $t('mailboxes.modal.name_placeholder')
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

                        <!-- From Email -->
                        <div v-if="form.provider !== 'gmail'">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("mailboxes.modal.from_email") }}
                            </label>
                            <input
                                v-model="form.from_email"
                                type="email"
                                :placeholder="
                                    $t('mailboxes.modal.from_email_placeholder')
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :class="{
                                    'border-rose-500': form.errors.from_email,
                                }"
                            />
                            <p
                                v-if="form.errors.from_email"
                                class="mt-1 text-sm text-rose-600"
                            >
                                {{ form.errors.from_email }}
                            </p>
                        </div>
                        <div
                            v-else
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            <p class="mb-2">
                                {{ $t("mailboxes.modal.gmail_auto_email") }}
                            </p>
                        </div>

                        <div v-if="form.provider !== 'gmail'">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("mailboxes.modal.reply_to") }}
                            </label>
                            <input
                                v-model="form.reply_to"
                                type="email"
                                :placeholder="
                                    $t('mailboxes.modal.reply_to_placeholder')
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :class="{
                                    'border-rose-500': form.errors.reply_to,
                                }"
                            />
                            <p
                                v-if="form.errors.reply_to"
                                class="mt-1 text-sm text-rose-600"
                            >
                                {{ form.errors.reply_to }}
                            </p>
                        </div>

                        <!-- From Name -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("mailboxes.modal.from_name") }}
                            </label>
                            <input
                                v-model="form.from_name"
                                type="text"
                                :placeholder="
                                    $t('mailboxes.modal.from_name_placeholder')
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :class="{
                                    'border-rose-500': form.errors.from_name,
                                }"
                            />
                            <p
                                v-if="form.errors.from_name"
                                class="mt-1 text-sm text-rose-600"
                            >
                                {{ form.errors.from_name }}
                            </p>
                        </div>

                        <!-- Gmail OAuth Section -->
                        <div v-if="form.provider === 'gmail'" class="space-y-4">
                            <div
                                v-if="!gmail_configured"
                                class="rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
                            >
                                <p class="text-sm">
                                    {{
                                        $t(
                                            "mailboxes.modal.integration_not_configured_warning"
                                        )
                                    }}
                                    <br />
                                    <Link
                                        :href="
                                            route('settings.integrations.index')
                                        "
                                        class="font-medium underline hover:text-amber-900 dark:hover:text-amber-100"
                                    >
                                        {{
                                            $t(
                                                "mailboxes.modal.go_to_integrations"
                                            )
                                        }}
                                    </Link>
                                </p>
                            </div>

                            <div v-else class="space-y-4">
                                <!-- Select Integration (Always Visible) -->
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "mailboxes.modal.google_integration_label"
                                            )
                                        }}
                                    </label>
                                    <select
                                        v-model="form.google_integration_id"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    >
                                        <option :value="null" disabled>
                                            {{
                                                $t(
                                                    "mailboxes.modal.google_integration_placeholder"
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="integration in google_integrations"
                                            :key="integration.id"
                                            :value="integration.id"
                                        >
                                            {{ integration.name }} ({{
                                                integration.client_id.substring(
                                                    0,
                                                    15
                                                )
                                            }}...)
                                        </option>
                                    </select>
                                    <p
                                        v-if="!google_integrations.length"
                                        class="mt-1 text-xs text-rose-500"
                                    >
                                        {{
                                            $t(
                                                "mailboxes.modal.no_integrations"
                                            )
                                        }}
                                    </p>
                                </div>

                                <!-- Connect/Disconnect UI (Only in Edit Mode and if Saved) -->
                                <div v-if="editingMailbox">
                                    <!-- Show warning if integration changed and not saved -->
                                    <div
                                        v-if="
                                            form.google_integration_id !==
                                            editingMailbox.google_integration_id
                                        "
                                        class="rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
                                    >
                                        <p class="text-sm">
                                            {{
                                                $t(
                                                    "mailboxes.modal.integration_changed_warning"
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-else-if="!form.google_integration_id"
                                        class="rounded-lg bg-blue-50 p-4 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200"
                                    >
                                        <p class="text-sm">
                                            {{
                                                $t(
                                                    "mailboxes.modal.select_integration_to_connect"
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-lg border p-4"
                                        :class="
                                            editingMailbox.gmail_connected
                                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
                                                : 'border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800'
                                        "
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div>
                                                <h4
                                                    class="font-medium"
                                                    :class="
                                                        editingMailbox.gmail_connected
                                                            ? 'text-emerald-800 dark:text-emerald-200'
                                                            : 'text-gray-900 dark:text-white'
                                                    "
                                                >
                                                    {{
                                                        editingMailbox.gmail_connected
                                                            ? $t(
                                                                  "mailboxes.oauth.connected"
                                                              )
                                                            : $t(
                                                                  "mailboxes.oauth.not_connected"
                                                              )
                                                    }}
                                                </h4>
                                                <p
                                                    v-if="
                                                        editingMailbox.gmail_email
                                                    "
                                                    class="text-sm text-emerald-600 dark:text-emerald-400"
                                                >
                                                    {{
                                                        editingMailbox.gmail_email
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                v-if="
                                                    editingMailbox.gmail_connected
                                                "
                                            >
                                                <button
                                                    type="button"
                                                    @click="disconnectGmail"
                                                    class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:bg-slate-700 dark:text-rose-400 dark:hover:bg-rose-900/20"
                                                >
                                                    {{
                                                        $t(
                                                            "mailboxes.oauth.disconnect"
                                                        )
                                                    }}
                                                </button>
                                            </div>
                                            <div v-else>
                                                <a
                                                    :href="
                                                        route(
                                                            'settings.mailboxes.gmail.connect',
                                                            editingMailbox.id
                                                        )
                                                    "
                                                    class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-slate-700 dark:text-white dark:ring-slate-600 dark:hover:bg-slate-600"
                                                >
                                                    <svg
                                                        class="h-5 w-5"
                                                        viewBox="0 0 24 24"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                                            fill="#4285F4"
                                                        />
                                                        <path
                                                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                                            fill="#34A853"
                                                        />
                                                        <path
                                                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                                            fill="#FBBC05"
                                                        />
                                                        <path
                                                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                                            fill="#EA4335"
                                                        />
                                                    </svg>
                                                    {{
                                                        $t(
                                                            "mailboxes.oauth.connect_google"
                                                        )
                                                    }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="rounded-lg bg-blue-50 p-4 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200"
                                >
                                    <p class="text-sm">
                                        {{ $t("mailboxes.oauth.save_first") }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Provider-specific Credentials (Non-Gmail) -->
                        <div
                            v-else
                            v-for="field in currentProviderFields"
                            :key="field.name"
                        >
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ field.label }}
                                <span
                                    v-if="
                                        modalMode === 'edit' &&
                                        field.type === 'password'
                                    "
                                    class="font-normal text-gray-500"
                                >
                                    ({{ $t("mailboxes.modal.leave_empty") }})
                                </span>
                            </label>

                            <!-- Select field -->
                            <select
                                v-if="field.type === 'select'"
                                v-model="form.credentials[field.name]"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option
                                    v-for="(label, value) in field.options"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>

                            <!-- Password field with toggle -->
                            <div
                                v-else-if="field.type === 'password'"
                                class="relative mt-1"
                            >
                                <input
                                    v-model="form.credentials[field.name]"
                                    :type="
                                        showPassword[field.name]
                                            ? 'text'
                                            : 'password'
                                    "
                                    :placeholder="
                                        modalMode === 'edit'
                                            ? $t('mailboxes.modal.leave_empty')
                                            : field.placeholder || ''
                                    "
                                    class="block w-full rounded-lg border-gray-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <p
                                    v-if="modalMode === 'edit'"
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{ $t("mailboxes.modal.leave_empty") }}
                                </p>
                                <button
                                    type="button"
                                    @click="
                                        showPassword[field.name] =
                                            !showPassword[field.name]
                                    "
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
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
                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
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

                            <!-- Other input types -->
                            <input
                                v-else
                                v-model="form.credentials[field.name]"
                                :type="field.type"
                                :placeholder="field.placeholder || ''"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />

                            <!-- Minimum Interval (Time Restriction) -->
                            <div v-if="field.name === 'port'" class="mt-4 mb-4">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t("mailboxes.modal.min_interval") }}
                                </label>
                                <div class="relative mt-1">
                                    <input
                                        v-model.number="form.time_restriction"
                                        type="number"
                                        min="0"
                                        class="block w-full rounded-lg border-gray-300 pr-16 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    />
                                    <div
                                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                                    >
                                        <span
                                            class="text-gray-500 sm:text-sm"
                                            >{{
                                                $t("mailboxes.modal.seconds")
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{
                                        $t("mailboxes.modal.min_interval_desc")
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Allowed Message Types -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                            >
                                {{ $t("mailboxes.modal.allowed_types") }}
                            </label>
                            <div class="space-y-2">
                                <label
                                    v-for="(label, type) in messageTypes"
                                    :key="type"
                                    class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                                    :class="{
                                        'border-indigo-300 bg-indigo-50 dark:border-indigo-700 dark:bg-indigo-900/20':
                                            form.allowed_types.includes(type),
                                        'border-gray-200 dark:border-slate-600 hover:border-gray-300':
                                            !form.allowed_types.includes(type),
                                        'opacity-50 cursor-not-allowed':
                                            type === 'broadcast' &&
                                            isBroadcastDisabled,
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="type"
                                        v-model="form.allowed_types"
                                        :disabled="
                                            type === 'broadcast' &&
                                            isBroadcastDisabled
                                        "
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div>
                                        <span
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >{{ label }}</span
                                        >
                                        <p
                                            v-if="
                                                type === 'broadcast' &&
                                                isBroadcastDisabled
                                            "
                                            class="text-xs text-gray-500"
                                        >
                                            {{
                                                $t(
                                                    "mailboxes.modal.broadcast_disabled"
                                                )
                                            }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Daily Limit -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("mailboxes.modal.daily_limit") }}
                                <span class="font-normal text-gray-500"
                                    >({{
                                        $t("mailboxes.modal.optional")
                                    }})</span
                                >
                            </label>
                            <input
                                v-model.number="form.daily_limit"
                                type="number"
                                min="1"
                                :placeholder="$t('mailboxes.modal.no_limit')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <!-- Test Result -->
                        <div
                            v-if="testResult"
                            class="rounded-lg p-4"
                            :class="
                                testResult.success
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20'
                                    : 'bg-rose-50 dark:bg-rose-900/20'
                            "
                        >
                            <div class="flex items-center gap-2">
                                <svg
                                    v-if="testResult.success"
                                    class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
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
                                    class="h-5 w-5 text-rose-600 dark:text-rose-400"
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
                                <span
                                    :class="
                                        testResult.success
                                            ? 'text-emerald-800 dark:text-emerald-200'
                                            : 'text-rose-800 dark:text-rose-200'
                                    "
                                >
                                    {{ testResult.message }}
                                </span>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? $t("common.saving")
                                        : $t("common.save")
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-rose-600 dark:text-rose-400"
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
                    </div>
                    <div>
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ $t("mailboxes.delete.title") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t("mailboxes.delete.confirm", {
                                    name: deletingMailbox?.name,
                                })
                            }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <DangerButton @click="deleteMailbox">
                        {{ $t("common.delete") }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Error Details Modal -->
        <Modal :show="showErrorModal" @close="closeErrorModal">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-rose-600 dark:text-rose-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ $t("mailboxes.error_details.title") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ errorMailbox?.name }} ({{
                                errorMailbox?.from_email
                            }})
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <div
                        class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-900/20"
                    >
                        <p
                            class="text-sm text-rose-800 dark:text-rose-200 whitespace-pre-wrap break-words"
                        >
                            {{ errorMailbox?.last_test_message }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeErrorModal">
                        {{ $t("common.close") }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
