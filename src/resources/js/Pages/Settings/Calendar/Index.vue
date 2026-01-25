<script setup>
import { useForm, Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import InputLabel from "@/Components/InputLabel.vue";
import Checkbox from "@/Components/Checkbox.vue";
import Modal from "@/Components/Modal.vue";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    connection: Object,
    integrations: Array,
    calendars: Array,
    webhook_url: String,
});

const showConnectModal = ref(false);
const selectedIntegration = ref(null);

const isConnected = computed(() => !!props.connection?.is_active);

const openConnectModal = () => {
    if (props.integrations.length === 1) {
        // Only one integration, connect directly
        connectWithIntegration(props.integrations[0].id);
    } else {
        showConnectModal.value = true;
    }
};

const connectWithIntegration = (integrationId) => {
    window.location.href = route("settings.calendar.connect", integrationId);
};

const disconnectForm = useForm({});
const disconnect = () => {
    if (confirm(t("calendar.confirm_disconnect"))) {
        disconnectForm.post(
            route("settings.calendar.disconnect", props.connection.id),
        );
    }
};

const settingsForm = useForm({
    calendar_id: props.connection?.calendar_id || "primary",
    auto_sync_tasks: props.connection?.auto_sync_tasks ?? true,
    sync_settings: props.connection?.sync_settings || {},
});

const updateSettings = () => {
    settingsForm.put(route("settings.calendar.settings", props.connection.id), {
        preserveScroll: true,
    });
};

const syncForm = useForm({});
const syncNow = () => {
    syncForm.post(route("settings.calendar.sync", props.connection.id), {
        preserveScroll: true,
    });
};

const bulkSyncForm = useForm({});
const bulkSyncLoading = ref(false);
const bulkSyncResult = ref(null);
const bulkSync = async () => {
    bulkSyncLoading.value = true;
    bulkSyncResult.value = null;
    try {
        const response = await fetch(
            route("settings.calendar.bulk-sync", props.connection.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    )?.content,
                },
            },
        );
        const data = await response.json();
        bulkSyncResult.value = data;
    } catch (error) {
        bulkSyncResult.value = {
            success: false,
            message: t("common.error_occurred"),
        };
    } finally {
        bulkSyncLoading.value = false;
    }
};

const refreshChannelForm = useForm({});
const refreshChannel = () => {
    refreshChannelForm.post(
        route("settings.calendar.refresh-channel", props.connection.id),
        {
            preserveScroll: true,
        },
    );
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};

const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <Head :title="$t('calendar.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                {{ $t("calendar.title") }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Connection Status -->
                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <section>
                        <header class="flex items-center justify-between">
                            <div>
                                <h2
                                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ $t("calendar.connection.title") }}
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t("calendar.connection.description") }}
                                </p>
                            </div>
                        </header>

                        <div class="mt-6">
                            <div
                                v-if="!isConnected"
                                class="rounded-lg border border-dashed border-gray-300 p-8 text-center dark:border-gray-600"
                            >
                                <svg
                                    class="mx-auto h-12 w-12 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                <h3
                                    class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        $t("calendar.connection.not_connected")
                                    }}
                                </h3>
                                <p
                                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t("calendar.connection.connect_prompt")
                                    }}
                                </p>
                                <div class="mt-6">
                                    <PrimaryButton
                                        v-if="integrations.length > 0"
                                        @click="openConnectModal"
                                    >
                                        <svg
                                            class="mr-2 h-5 w-5"
                                            viewBox="0 0 24 24"
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
                                        {{ $t("calendar.connection.connect") }}
                                    </PrimaryButton>
                                    <p
                                        v-else
                                        class="text-sm text-amber-600 dark:text-amber-400"
                                    >
                                        {{
                                            $t(
                                                "calendar.connection.no_integrations",
                                            )
                                        }}
                                        <a
                                            :href="
                                                route(
                                                    'settings.integrations.index',
                                                )
                                            "
                                            class="underline hover:no-underline"
                                        >
                                            {{
                                                $t(
                                                    "calendar.connection.add_integration",
                                                )
                                            }}
                                        </a>
                                    </p>
                                </div>
                            </div>

                            <div v-else class="space-y-6">
                                <!-- Connected Status -->
                                <div
                                    class="flex items-start justify-between rounded-lg bg-green-50 p-4 dark:bg-green-900/20"
                                >
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg
                                                class="h-5 w-5 text-green-400"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3
                                                class="text-sm font-medium text-green-800 dark:text-green-200"
                                            >
                                                {{
                                                    $t(
                                                        "calendar.connection.connected",
                                                    )
                                                }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-green-700 dark:text-green-300"
                                            >
                                                {{ connection.connected_email }}
                                            </p>
                                            <p
                                                v-if="
                                                    connection.integration_name
                                                "
                                                class="text-xs text-green-600 dark:text-green-400"
                                            >
                                                {{
                                                    $t(
                                                        "calendar.connection.via",
                                                    )
                                                }}
                                                {{
                                                    connection.integration_name
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <DangerButton
                                        @click="disconnect"
                                        :disabled="disconnectForm.processing"
                                        size="sm"
                                    >
                                        {{
                                            $t("calendar.connection.disconnect")
                                        }}
                                    </DangerButton>
                                </div>

                                <!-- Push Notification Status -->
                                <div
                                    class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                            >
                                                {{
                                                    $t(
                                                        "calendar.push_notifications.title",
                                                    )
                                                }}
                                            </h4>
                                            <p
                                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                <span
                                                    v-if="
                                                        connection.has_push_notifications
                                                    "
                                                    class="text-green-600 dark:text-green-400"
                                                >
                                                    {{
                                                        $t(
                                                            "calendar.push_notifications.active",
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-amber-600 dark:text-amber-400"
                                                >
                                                    {{
                                                        $t(
                                                            "calendar.push_notifications.inactive",
                                                        )
                                                    }}
                                                </span>
                                            </p>
                                            <p
                                                v-if="
                                                    connection.channel_expires_at
                                                "
                                                class="mt-1 text-xs text-gray-500"
                                            >
                                                {{
                                                    $t(
                                                        "calendar.push_notifications.expires",
                                                    )
                                                }}:
                                                {{
                                                    formatDate(
                                                        connection.channel_expires_at,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <SecondaryButton
                                            @click="refreshChannel"
                                            :disabled="
                                                refreshChannelForm.processing
                                            "
                                            size="sm"
                                        >
                                            {{
                                                $t(
                                                    "calendar.push_notifications.refresh",
                                                )
                                            }}
                                        </SecondaryButton>
                                    </div>
                                </div>

                                <!-- Sync Settings -->
                                <form
                                    @submit.prevent="updateSettings"
                                    class="space-y-4"
                                >
                                    <div>
                                        <InputLabel
                                            :value="
                                                $t('calendar.settings.calendar')
                                            "
                                        />
                                        <select
                                            v-model="settingsForm.calendar_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            <option value="primary">
                                                {{
                                                    $t(
                                                        "calendar.settings.primary_calendar",
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="cal in calendars"
                                                :key="cal.id"
                                                :value="cal.id"
                                            >
                                                {{ cal.summary }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="flex items-center">
                                        <Checkbox
                                            id="auto_sync"
                                            v-model:checked="
                                                settingsForm.auto_sync_tasks
                                            "
                                        />
                                        <label
                                            for="auto_sync"
                                            class="ml-2 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "calendar.settings.auto_sync",
                                                )
                                            }}
                                        </label>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <PrimaryButton
                                            :disabled="settingsForm.processing"
                                        >
                                            {{ $t("common.save") }}
                                        </PrimaryButton>
                                        <SecondaryButton
                                            type="button"
                                            @click="syncNow"
                                            :disabled="syncForm.processing"
                                        >
                                            {{
                                                $t("calendar.settings.sync_now")
                                            }}
                                        </SecondaryButton>
                                    </div>
                                </form>
                            </div>

                            <!-- Bulk Sync Section -->
                            <div
                                class="mt-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20"
                            >
                                <h4
                                    class="text-sm font-medium text-indigo-900 dark:text-indigo-300"
                                >
                                    {{ $t("crm.calendar.sync_all") }}
                                </h4>
                                <p
                                    class="mt-1 text-sm text-indigo-700 dark:text-indigo-400"
                                >
                                    {{
                                        $t("crm.calendar.bulk_sync_description")
                                    }}
                                </p>
                                <div class="mt-3 flex items-center gap-3">
                                    <button
                                        type="button"
                                        @click="bulkSync"
                                        :disabled="bulkSyncLoading"
                                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        <svg
                                            v-if="bulkSyncLoading"
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
                                            />
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                        {{
                                            bulkSyncLoading
                                                ? $t("crm.calendar.syncing")
                                                : $t("crm.calendar.sync_all")
                                        }}
                                    </button>
                                </div>
                                <div v-if="bulkSyncResult" class="mt-3">
                                    <p
                                        v-if="bulkSyncResult.success"
                                        class="text-sm text-green-700 dark:text-green-400"
                                    >
                                        ✓ {{ bulkSyncResult.message }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-sm text-red-700 dark:text-red-400"
                                    >
                                        ✗ {{ bulkSyncResult.message }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Webhook Info (for debugging) -->
                <div
                    v-if="isConnected"
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <section>
                        <h2
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t("calendar.webhook.title") }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ $t("calendar.webhook.description") }}
                        </p>
                        <div class="mt-4 flex rounded-md shadow-sm">
                            <input
                                type="text"
                                readonly
                                :value="webhook_url"
                                class="block w-full rounded-l-md border-gray-300 bg-gray-50 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            />
                            <button
                                type="button"
                                @click="copyToClipboard(webhook_url)"
                                class="relative -ml-px inline-flex items-center rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            >
                                {{ $t("common.copy") }}
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Integration Selection Modal -->
        <Modal :show="showConnectModal" @close="showConnectModal = false">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t("calendar.modal.select_integration") }}
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t("calendar.modal.select_description") }}
                </p>
                <div class="mt-6 space-y-3">
                    <button
                        v-for="integration in integrations"
                        :key="integration.id"
                        @click="connectWithIntegration(integration.id)"
                        class="flex w-full items-center justify-between rounded-lg border border-gray-200 p-4 text-left hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                    >
                        <div>
                            <h3
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ integration.name }}
                            </h3>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs"
                            >
                                {{ integration.client_id }}
                            </p>
                        </div>
                        <svg
                            class="h-5 w-5 text-gray-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>
                    </button>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showConnectModal = false">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
