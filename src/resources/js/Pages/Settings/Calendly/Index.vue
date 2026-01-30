<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
    integrations: Array,
    mailingLists: Array,
    tags: Array,
    teamMembers: Array,
    webhookUrl: String,
});

const page = usePage();
const flash = computed(() => page.props.flash);

// Expanded event types for per-event-type configuration
const expandedEventTypes = ref({});

// Get event type mapping or defaults to global settings
function getEventTypeMapping(eventTypeUri) {
    const mappings = settingsForm.settings.event_type_mappings || {};
    if (mappings[eventTypeUri]) {
        return mappings[eventTypeUri];
    }
    // Return default structure for new mapping
    return {
        use_custom: false,
        list_ids: [],
        tag_ids: [],
    };
}

// Toggle event type expansion
function toggleEventType(eventTypeUri) {
    expandedEventTypes.value[eventTypeUri] =
        !expandedEventTypes.value[eventTypeUri];
}

// Update event type mapping
function updateEventTypeMapping(eventTypeUri, field, value) {
    if (!settingsForm.settings.event_type_mappings) {
        settingsForm.settings.event_type_mappings = {};
    }
    if (!settingsForm.settings.event_type_mappings[eventTypeUri]) {
        settingsForm.settings.event_type_mappings[eventTypeUri] = {
            use_custom: false,
            list_ids: [],
            tag_ids: [],
        };
    }
    settingsForm.settings.event_type_mappings[eventTypeUri][field] = value;
}

// Filtered lists based on search
const filteredMailingLists = computed(() => {
    if (!mailingListSearch.value) return props.mailingLists;
    const search = mailingListSearch.value.toLowerCase();
    return props.mailingLists.filter((list) =>
        list.name.toLowerCase().includes(search),
    );
});

const filteredTags = computed(() => {
    if (!tagSearch.value) return props.tags;
    const search = tagSearch.value.toLowerCase();
    return props.tags.filter((tag) => tag.name.toLowerCase().includes(search));
});

const activeIntegration = ref(props.integrations?.[0] || null);
const showSettingsModal = ref(false);
const showConnectModal = ref(false);
const saving = ref(false);
const syncing = ref(false);
const testing = ref(false);
const connecting = ref(false);
const toastMessage = ref("");
const showToast = ref(false);

// Search filters for lists
const mailingListSearch = ref("");
const tagSearch = ref("");

// Connect form for API credentials
const connectForm = useForm({
    client_id: "",
    client_secret: "",
});

// Settings form
const settingsForm = useForm({
    settings: activeIntegration.value?.settings || {
        crm: {
            enabled: true,
            default_status: "lead",
            create_tasks: true,
            default_owner_id: null,
        },
        mailing_lists: {
            enabled: true,
            default_list_ids: [],
            default_tag_ids: [],
        },
        event_type_mappings: {},
        automation: {
            trigger_on_booking: true,
            trigger_on_cancellation: true,
            trigger_on_no_show: false,
        },
    },
});

function displayToast(message) {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
}

function copyToClipboard(text) {
    navigator.clipboard
        .writeText(text)
        .then(() => {
            displayToast("Copied to clipboard!");
        })
        .catch(() => {
            displayToast("Failed to copy");
        });
}

function openConnectModal() {
    connectForm.reset();
    showConnectModal.value = true;
}

function connect() {
    connecting.value = true;
    connectForm.post(route("settings.calendly.connect"), {
        onError: (errors) => {
            displayToast(
                errors.client_id || errors.client_secret || "Failed to connect",
            );
        },
        onFinish: () => {
            connecting.value = false;
        },
    });
}

function disconnect(integration) {
    if (confirm("Are you sure you want to disconnect this Calendly account?")) {
        router.post(
            route("settings.calendly.disconnect", integration.id),
            {},
            {
                onSuccess: () => {
                    displayToast("Calendly disconnected successfully");
                },
            },
        );
    }
}

function openSettings(integration) {
    activeIntegration.value = integration;
    settingsForm.settings = { ...integration.settings };
    showSettingsModal.value = true;
}

function saveSettings() {
    if (!activeIntegration.value) return;

    saving.value = true;
    settingsForm.put(
        route("settings.calendly.settings", activeIntegration.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                displayToast("Settings saved successfully");
                showSettingsModal.value = false;
            },
            onError: () => {
                displayToast("Failed to save settings");
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function syncEventTypes(integration) {
    syncing.value = true;
    router.post(
        route("settings.calendly.sync-event-types", integration.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                displayToast("Event types synced successfully");
            },
            onError: () => {
                displayToast("Failed to sync event types");
            },
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function testWebhook(integration) {
    testing.value = true;
    router.post(
        route("settings.calendly.test-webhook", integration.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                displayToast("Webhook is working correctly");
            },
            onError: () => {
                displayToast("Webhook test failed");
            },
            onFinish: () => {
                testing.value = false;
            },
        },
    );
}

function getStatusBadgeClass(status) {
    switch (status) {
        case "scheduled":
            return "bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400";
        case "canceled":
            return "bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400";
        case "no_show":
            return "bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400";
        case "completed":
            return "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400";
        default:
            return "bg-gray-100 text-gray-700 dark:bg-gray-500/10 dark:text-gray-400";
    }
}

function formatDateTime(dateString) {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleString();
}
</script>

<template>
    <Head title="Calendly Integration" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div
                    class="mb-8 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400"
                >
                    <Link
                        :href="route('dashboard')"
                        class="hover:text-gray-700 dark:hover:text-white transition-colors"
                    >
                        Dashboard
                    </Link>
                    <span>/</span>
                    <Link
                        :href="route('marketplace.calendly')"
                        class="hover:text-gray-700 dark:hover:text-white transition-colors"
                    >
                        Calendly
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">Settings</span>
                </div>

                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-sky-600 shadow-lg"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                Calendly Integration
                            </h2>
                            <p class="text-gray-500 dark:text-slate-400">
                                Connect Calendly to sync bookings with CRM and
                                mailing lists
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div
                    v-if="flash?.success"
                    class="mb-6 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-500/20"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash?.error"
                    class="mb-6 rounded-xl bg-red-50 dark:bg-red-500/10 p-4 text-red-700 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-500/20"
                >
                    {{ flash.error }}
                </div>

                <div class="space-y-6">
                    <!-- Connected Accounts -->
                    <div v-if="integrations.length > 0" class="space-y-4">
                        <div
                            v-for="integration in integrations"
                            :key="integration.id"
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-500/10"
                                    >
                                        <svg
                                            class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                integration.calendly_user_name ||
                                                "Calendly Account"
                                            }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-500 dark:text-slate-400"
                                        >
                                            {{
                                                integration.calendly_user_email
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="integration.is_active"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full bg-emerald-500"
                                        ></span>
                                        Connected
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full bg-red-100 dark:bg-red-500/10 px-3 py-1 text-sm font-medium text-red-700 dark:text-red-400"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full bg-red-500"
                                        ></span>
                                        Inactive
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-wrap items-center gap-3 mb-6">
                                <button
                                    @click="openSettings(integration)"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors ring-1 ring-blue-200 dark:ring-blue-500/30"
                                >
                                    Configure
                                </button>
                                <button
                                    @click="syncEventTypes(integration)"
                                    :disabled="syncing"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors ring-1 ring-gray-200 dark:ring-white/10"
                                >
                                    <span v-if="syncing">Syncing...</span>
                                    <span v-else>Sync Event Types</span>
                                </button>
                                <button
                                    @click="testWebhook(integration)"
                                    :disabled="testing"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors ring-1 ring-gray-200 dark:ring-white/10"
                                >
                                    <span v-if="testing">Testing...</span>
                                    <span v-else>Test Webhook</span>
                                </button>
                                <button
                                    @click="disconnect(integration)"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                                >
                                    Disconnect
                                </button>
                            </div>

                            <!-- Event Types -->
                            <div
                                v-if="integration.event_types?.length > 0"
                                class="mb-6"
                            >
                                <h4
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                >
                                    Event Types
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="eventType in integration.event_types"
                                        :key="eventType.uri"
                                        class="inline-flex items-center rounded-lg bg-gray-100 dark:bg-white/5 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ eventType.name }}
                                    </span>
                                </div>
                            </div>

                            <!-- Recent Events -->
                            <div v-if="integration.recent_events?.length > 0">
                                <h4
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                >
                                    Recent Bookings
                                </h4>
                                <div
                                    class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                                >
                                    <table
                                        class="min-w-full divide-y divide-gray-200 dark:divide-white/10"
                                    >
                                        <thead
                                            class="bg-gray-50 dark:bg-white/5"
                                        >
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                                >
                                                    Event
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                                >
                                                    Invitee
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                                >
                                                    Date
                                                </th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                                >
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-gray-200 dark:divide-white/10"
                                        >
                                            <tr
                                                v-for="event in integration.recent_events"
                                                :key="event.id"
                                            >
                                                <td
                                                    class="px-4 py-3 text-sm text-gray-900 dark:text-white"
                                                >
                                                    {{ event.event_type_name }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    <div>
                                                        {{ event.invitee_name }}
                                                    </div>
                                                    <div class="text-xs">
                                                        {{
                                                            event.invitee_email
                                                        }}
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    {{
                                                        formatDateTime(
                                                            event.start_time,
                                                        )
                                                    }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        :class="[
                                                            'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                                            getStatusBadgeClass(
                                                                event.status,
                                                            ),
                                                        ]"
                                                    >
                                                        {{ event.status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connect Button -->
                    <div
                        class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                    >
                        <div class="text-center">
                            <div
                                class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-sky-600 shadow-lg mb-4"
                            >
                                <svg
                                    class="h-8 w-8 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                            >
                                {{
                                    integrations.length > 0
                                        ? "Connect Another Account"
                                        : "Connect Your Calendly Account"
                                }}
                            </h3>
                            <p
                                class="text-gray-500 dark:text-slate-400 mb-6 max-w-md mx-auto"
                            >
                                Automatically sync Calendly bookings with your
                                CRM and add invitees to your mailing lists.
                            </p>
                            <button
                                @click="openConnectModal"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-lg hover:from-blue-700 hover:to-sky-700 transition-all"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                    />
                                </svg>
                                Connect with Calendly
                            </button>
                        </div>
                    </div>

                    <!-- Webhook URL Info -->
                    <div
                        class="rounded-2xl bg-gray-50 dark:bg-white/5 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10"
                    >
                        <h4
                            class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Webhook URL
                        </h4>
                        <p
                            class="text-sm text-gray-500 dark:text-gray-400 mb-3"
                        >
                            This URL is automatically configured when you
                            connect your account.
                        </p>
                        <div class="flex items-center gap-2">
                            <code
                                class="flex-1 rounded-lg bg-white dark:bg-slate-800 px-4 py-2 text-sm text-gray-900 dark:text-white ring-1 ring-gray-200 dark:ring-white/10 overflow-x-auto"
                            >
                                {{ webhookUrl }}
                            </code>
                            <button
                                @click="copyToClipboard(webhookUrl)"
                                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors"
                                title="Copy to clipboard"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connect Modal -->
        <Teleport to="body">
            <div
                v-if="showConnectModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 shadow-2xl"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                Connect Calendly
                            </h3>
                            <button
                                @click="showConnectModal = false"
                                class="rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
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
                    </div>

                    <form @submit.prevent="connect" class="p-6 space-y-4">
                        <div
                            class="rounded-xl bg-blue-50 dark:bg-blue-500/10 p-4 text-sm text-blue-700 dark:text-blue-400"
                        >
                            <p class="mb-2">
                                To connect Calendly, you need to create an OAuth
                                application:
                            </p>
                            <ol class="list-decimal ml-4 space-y-1">
                                <li>
                                    Go to
                                    <a
                                        href="https://developer.calendly.com"
                                        target="_blank"
                                        class="underline hover:no-underline"
                                        >developer.calendly.com</a
                                    >
                                </li>
                                <li>Create a new OAuth application</li>
                                <li>
                                    Set Redirect URI to:
                                    <code
                                        class="bg-blue-100 dark:bg-blue-900/30 px-1 rounded text-xs"
                                        >{{
                                            webhookUrl.replace(
                                                "/api/webhooks/calendly",
                                                "/settings/calendly/callback",
                                            )
                                        }}</code
                                    >
                                </li>
                                <li>Copy Client ID and Client Secret below</li>
                            </ol>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                Client ID
                            </label>
                            <input
                                v-model="connectForm.client_id"
                                type="text"
                                required
                                placeholder="Enter your Calendly Client ID"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 text-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                            <p
                                v-if="connectForm.errors.client_id"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ connectForm.errors.client_id }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                Client Secret
                            </label>
                            <input
                                v-model="connectForm.client_secret"
                                type="password"
                                required
                                placeholder="Enter your Calendly Client Secret"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 text-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                            <p
                                v-if="connectForm.errors.client_secret"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ connectForm.errors.client_secret }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showConnectModal = false"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="
                                    connecting ||
                                    !connectForm.client_id ||
                                    !connectForm.client_secret
                                "
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                <span v-if="connecting">Connecting...</span>
                                <span v-else>Connect</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Settings Modal -->
        <Teleport to="body">
            <div
                v-if="showSettingsModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 shadow-2xl"
                >
                    <div
                        class="sticky top-0 bg-white dark:bg-slate-800 px-6 py-4 border-b border-gray-200 dark:border-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                Integration Settings
                            </h3>
                            <button
                                @click="showSettingsModal = false"
                                class="rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
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
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- CRM Settings -->
                        <div>
                            <h4
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                CRM Integration
                            </h4>
                            <div class="space-y-4">
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.crm.enabled
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Create CRM contacts from bookings</span
                                    >
                                </label>
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.crm
                                                .create_tasks
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Create CRM tasks for meetings</span
                                    >
                                </label>
                                <div>
                                    <label
                                        class="block text-sm text-gray-700 dark:text-gray-300 mb-1"
                                        >Default Contact Status</label
                                    >
                                    <select
                                        v-model="
                                            settingsForm.settings.crm
                                                .default_status
                                        "
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 text-sm focus:ring-blue-500"
                                    >
                                        <option value="lead">Lead</option>
                                        <option value="prospect">
                                            Prospect
                                        </option>
                                        <option value="customer">
                                            Customer
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm text-gray-700 dark:text-gray-300 mb-1"
                                        >Default Owner</label
                                    >
                                    <select
                                        v-model="
                                            settingsForm.settings.crm
                                                .default_owner_id
                                        "
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 text-sm focus:ring-blue-500"
                                    >
                                        <option :value="null">No owner</option>
                                        <option
                                            v-for="member in teamMembers"
                                            :key="member.id"
                                            :value="member.id"
                                        >
                                            {{ member.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Mailing List Settings -->
                        <div>
                            <h4
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                Mailing Lists
                            </h4>
                            <div class="space-y-4">
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.mailing_lists
                                                .enabled
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Add invitees to mailing lists</span
                                    >
                                </label>
                                <div>
                                    <label
                                        class="block text-sm text-gray-700 dark:text-gray-300 mb-2"
                                        >Default Mailing Lists</label
                                    >
                                    <!-- Search Input -->
                                    <div class="relative mb-2">
                                        <svg
                                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                            />
                                        </svg>
                                        <input
                                            v-model="mailingListSearch"
                                            type="text"
                                            placeholder="Search lists..."
                                            class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>
                                    <!-- Checkbox List -->
                                    <div
                                        class="max-h-48 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700"
                                    >
                                        <div
                                            v-if="
                                                filteredMailingLists.length ===
                                                0
                                            "
                                            class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center"
                                        >
                                            No lists found
                                        </div>
                                        <label
                                            v-for="list in filteredMailingLists"
                                            :key="list.id"
                                            class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-600 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                                        >
                                            <input
                                                :value="list.id"
                                                v-model="
                                                    settingsForm.settings
                                                        .mailing_lists
                                                        .default_list_ids
                                                "
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-500 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span
                                                class="text-sm text-gray-900 dark:text-white"
                                                >{{ list.name }}</span
                                            >
                                        </label>
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            settingsForm.settings.mailing_lists
                                                .default_list_ids?.length || 0
                                        }}
                                        selected
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm text-gray-700 dark:text-gray-300 mb-2"
                                        >Default Tags</label
                                    >
                                    <!-- Search Input -->
                                    <div class="relative mb-2">
                                        <svg
                                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                            />
                                        </svg>
                                        <input
                                            v-model="tagSearch"
                                            type="text"
                                            placeholder="Search tags..."
                                            class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500"
                                        />
                                    </div>
                                    <!-- Checkbox List -->
                                    <div
                                        class="max-h-48 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700"
                                    >
                                        <div
                                            v-if="filteredTags.length === 0"
                                            class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center"
                                        >
                                            No tags found
                                        </div>
                                        <label
                                            v-for="tag in filteredTags"
                                            :key="tag.id"
                                            class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-slate-600 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                                        >
                                            <input
                                                :value="tag.id"
                                                v-model="
                                                    settingsForm.settings
                                                        .mailing_lists
                                                        .default_tag_ids
                                                "
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-500 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span
                                                class="text-sm text-gray-900 dark:text-white"
                                                >{{ tag.name }}</span
                                            >
                                        </label>
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            settingsForm.settings.mailing_lists
                                                .default_tag_ids?.length || 0
                                        }}
                                        selected
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Event Type Specific Mappings -->
                        <div
                            v-if="
                                activeIntegration?.event_types?.length > 0 &&
                                settingsForm.settings.mailing_lists.enabled
                            "
                        >
                            <h4
                                class="text-sm font-medium text-gray-900 dark:text-white mb-2"
                            >
                                Per Event Type Settings
                            </h4>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 mb-4"
                            >
                                Override default mailing list settings for
                                specific event types. If "Use custom settings"
                                is disabled, the default settings above will be
                                used.
                            </p>
                            <div class="space-y-3">
                                <div
                                    v-for="eventType in activeIntegration.event_types"
                                    :key="eventType.uri"
                                    class="rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden"
                                >
                                    <!-- Event Type Header -->
                                    <button
                                        @click="toggleEventType(eventType.uri)"
                                        type="button"
                                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors text-left"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ eventType.name }}
                                            </span>
                                            <span
                                                v-if="
                                                    getEventTypeMapping(
                                                        eventType.uri,
                                                    ).use_custom
                                                "
                                                class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400"
                                            >
                                                Custom
                                            </span>
                                        </div>
                                        <svg
                                            :class="[
                                                'h-5 w-5 text-gray-400 transition-transform',
                                                expandedEventTypes[
                                                    eventType.uri
                                                ]
                                                    ? 'rotate-180'
                                                    : '',
                                            ]"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </button>

                                    <!-- Event Type Settings (Expandable) -->
                                    <div
                                        v-if="expandedEventTypes[eventType.uri]"
                                        class="p-4 space-y-4 bg-white dark:bg-slate-800"
                                    >
                                        <label class="flex items-center gap-3">
                                            <input
                                                :checked="
                                                    getEventTypeMapping(
                                                        eventType.uri,
                                                    ).use_custom
                                                "
                                                @change="
                                                    updateEventTypeMapping(
                                                        eventType.uri,
                                                        'use_custom',
                                                        $event.target.checked,
                                                    )
                                                "
                                                type="checkbox"
                                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span
                                                class="text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                Use custom settings for this
                                                event type
                                            </span>
                                        </label>

                                        <div
                                            v-if="
                                                getEventTypeMapping(
                                                    eventType.uri,
                                                ).use_custom
                                            "
                                            class="pl-6 space-y-4"
                                        >
                                            <!-- Mailing Lists for this event type -->
                                            <div>
                                                <label
                                                    class="block text-sm text-gray-700 dark:text-gray-300 mb-2"
                                                >
                                                    Mailing Lists
                                                </label>
                                                <div
                                                    class="max-h-32 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700"
                                                >
                                                    <label
                                                        v-for="list in mailingLists"
                                                        :key="list.id"
                                                        class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-600 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                                                    >
                                                        <input
                                                            :value="list.id"
                                                            :checked="
                                                                getEventTypeMapping(
                                                                    eventType.uri,
                                                                ).list_ids?.includes(
                                                                    list.id,
                                                                )
                                                            "
                                                            @change="
                                                                (e) => {
                                                                    const mapping =
                                                                        getEventTypeMapping(
                                                                            eventType.uri,
                                                                        );
                                                                    let ids = [
                                                                        ...(mapping.list_ids ||
                                                                            []),
                                                                    ];
                                                                    if (
                                                                        e.target
                                                                            .checked
                                                                    ) {
                                                                        if (
                                                                            !ids.includes(
                                                                                list.id,
                                                                            )
                                                                        ) {
                                                                            ids.push(
                                                                                list.id,
                                                                            );
                                                                        }
                                                                    } else {
                                                                        ids =
                                                                            ids.filter(
                                                                                (
                                                                                    id,
                                                                                ) =>
                                                                                    id !==
                                                                                    list.id,
                                                                            );
                                                                    }
                                                                    updateEventTypeMapping(
                                                                        eventType.uri,
                                                                        'list_ids',
                                                                        ids,
                                                                    );
                                                                }
                                                            "
                                                            type="checkbox"
                                                            class="rounded border-gray-300 dark:border-gray-500 text-blue-600 focus:ring-blue-500"
                                                        />
                                                        <span
                                                            class="text-sm text-gray-900 dark:text-white"
                                                        >
                                                            {{ list.name }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Tags for this event type -->
                                            <div>
                                                <label
                                                    class="block text-sm text-gray-700 dark:text-gray-300 mb-2"
                                                >
                                                    Tags
                                                </label>
                                                <div
                                                    class="max-h-32 overflow-y-auto rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700"
                                                >
                                                    <label
                                                        v-for="tag in tags"
                                                        :key="tag.id"
                                                        class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-600 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                                                    >
                                                        <input
                                                            :value="tag.id"
                                                            :checked="
                                                                getEventTypeMapping(
                                                                    eventType.uri,
                                                                ).tag_ids?.includes(
                                                                    tag.id,
                                                                )
                                                            "
                                                            @change="
                                                                (e) => {
                                                                    const mapping =
                                                                        getEventTypeMapping(
                                                                            eventType.uri,
                                                                        );
                                                                    let ids = [
                                                                        ...(mapping.tag_ids ||
                                                                            []),
                                                                    ];
                                                                    if (
                                                                        e.target
                                                                            .checked
                                                                    ) {
                                                                        if (
                                                                            !ids.includes(
                                                                                tag.id,
                                                                            )
                                                                        ) {
                                                                            ids.push(
                                                                                tag.id,
                                                                            );
                                                                        }
                                                                    } else {
                                                                        ids =
                                                                            ids.filter(
                                                                                (
                                                                                    id,
                                                                                ) =>
                                                                                    id !==
                                                                                    tag.id,
                                                                            );
                                                                    }
                                                                    updateEventTypeMapping(
                                                                        eventType.uri,
                                                                        'tag_ids',
                                                                        ids,
                                                                    );
                                                                }
                                                            "
                                                            type="checkbox"
                                                            class="rounded border-gray-300 dark:border-gray-500 text-blue-600 focus:ring-blue-500"
                                                        />
                                                        <span
                                                            class="text-sm text-gray-900 dark:text-white"
                                                        >
                                                            {{ tag.name }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Automation Settings -->
                        <div>
                            <h4
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                Automation Triggers
                            </h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.automation
                                                .trigger_on_booking
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Trigger on new booking</span
                                    >
                                </label>
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.automation
                                                .trigger_on_cancellation
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Trigger on cancellation</span
                                    >
                                </label>
                                <label class="flex items-center gap-3">
                                    <input
                                        v-model="
                                            settingsForm.settings.automation
                                                .trigger_on_no_show
                                        "
                                        type="checkbox"
                                        class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                        >Trigger on no-show</span
                                    >
                                </label>
                            </div>
                        </div>
                    </div>

                    <div
                        class="sticky bottom-0 bg-gray-50 dark:bg-slate-900 px-6 py-4 border-t border-gray-200 dark:border-white/10 flex justify-end gap-3"
                    >
                        <button
                            @click="showSettingsModal = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveSettings"
                            :disabled="saving"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="saving">Saving...</span>
                            <span v-else>Save Settings</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Toast -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="transform translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform translate-y-2 opacity-0"
            >
                <div
                    v-if="showToast"
                    class="fixed bottom-6 right-6 z-50 rounded-lg bg-gray-900 dark:bg-white px-4 py-3 text-sm text-white dark:text-gray-900 shadow-lg"
                >
                    {{ toastMessage }}
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
