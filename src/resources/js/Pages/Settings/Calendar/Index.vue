<script setup>
import { useForm, Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import Modal from "@/Components/Modal.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { fieldClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t, locale } = useI18n();
// What Google answered and what a sync did comes from the server; saving is confirmed here
const { toast, showToast, dismiss } = useToast({ flash: true });

const props = defineProps({
    connection: Object,
    integrations: Array,
    calendars: Array,
    webhook_url: String,
    calendar_redirect_uri: String,
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
const showDisconnect = ref(false);

const disconnect = () => {
    disconnectForm.post(route("settings.calendar.disconnect", props.connection.id), {
        onFinish: () => (showDisconnect.value = false),
    });
};

const settingsForm = useForm({
    calendar_id: props.connection?.calendar_id || "primary",
    auto_sync_tasks: props.connection?.auto_sync_tasks ?? true,
    sync_settings: props.connection?.sync_settings || {},
});

const updateSettings = () => {
    settingsForm.put(route("settings.calendar.settings", props.connection.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast(t("calendar.settings_updated"));
        },
        onError: () => {
            showToast(t("common.notifications.error_occurred"), false);
        },
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
            message: t("common.notifications.error_occurred"),
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


const formatDate = (dateString) => {
    if (!dateString) return "—";
    return new Date(dateString).toLocaleString(locale.value);
};

// Task type color settings
const defaultTaskTypeColors = {
    call: '#8B5CF6',      // Purple
    email: '#3B82F6',     // Blue
    meeting: '#EF4444',   // Red
    task: '#10B981',      // Green
    follow_up: '#F59E0B', // Amber
};

const colorPresets = [
    '#EF4444', // Red
    '#F59E0B', // Amber
    '#10B981', // Green
    '#3B82F6', // Blue
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#14B8A6', // Teal
    '#6B7280', // Gray
];

const taskTypes = ['call', 'email', 'meeting', 'task', 'follow_up'];

const colorsForm = useForm({
    task_type_colors: {
        ...defaultTaskTypeColors,
        ...(props.connection?.task_type_colors || {}),
    },
});

const setTaskColor = (taskType, color) => {
    colorsForm.task_type_colors[taskType] = color;
};

const resetColorsToDefault = () => {
    colorsForm.task_type_colors = { ...defaultTaskTypeColors };
};

const saveTaskColors = () => {
    colorsForm.put(route("settings.calendar.task-colors", props.connection.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast(t("calendar.task_colors.saved"));
        },
        onError: () => {
            showToast(t("common.notifications.error_occurred"), false);
        },
    });
};
</script>

<template>
    <Head :title="$t('calendar.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('calendar.title')" :subtitle="$t('calendar.connection.description')">
                <StatusPill :tone="isConnected ? 'success' : 'neutral'">
                    {{ isConnected ? $t("calendar.connection.connected") : $t("calendar.connection.not_connected") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <!-- Not connected: what to do first -->
            <Panel v-if="!isConnected" flush>
                <EmptyState
                    class="!border-t-0"
                    :title="$t('calendar.connection.not_connected')"
                    :description="$t('calendar.connection.connect_prompt')"
                >
                    <PrimaryButton v-if="integrations.length > 0" type="button" @click="openConnectModal">
                        {{ $t("calendar.connection.connect") }}
                    </PrimaryButton>
                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $t("calendar.connection.no_integrations") }}
                        <a
                            :href="route('settings.integrations.index')"
                            class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ $t("calendar.connection.add_integration") }}
                        </a>
                    </p>
                </EmptyState>
            </Panel>

            <template v-else>
                <!-- The account, and which calendar tasks go to -->
                <Panel :title="$t('calendar.connection.title')">
                    <template #actions>
                        <SecondaryButton type="button" :disabled="disconnectForm.processing" @click="showDisconnect = true">
                            {{ $t("calendar.connection.disconnect") }}
                        </SecondaryButton>
                    </template>

                    <p class="break-all text-sm font-medium text-slate-900 dark:text-white">{{ connection.connected_email }}</p>
                    <p v-if="connection.integration_name" class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $t("calendar.connection.via") }} {{ connection.integration_name }}
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="updateSettings">
                        <div>
                            <label for="calendar-id" :class="labelClass">{{ $t("calendar.settings.calendar") }}</label>
                            <select id="calendar-id" v-model="settingsForm.calendar_id" class="mt-1" :class="fieldClass">
                                <option value="primary">{{ $t("calendar.settings.primary_calendar") }}</option>
                                <option v-for="cal in calendars" :key="cal.id" :value="cal.id">{{ cal.summary }}</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2.5">
                            <input v-model="settingsForm.auto_sync_tasks" type="checkbox" class="h-4 w-4 accent-indigo-600" />
                            <span class="text-sm text-slate-800 dark:text-slate-200">{{ $t("calendar.settings.auto_sync") }}</span>
                        </label>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <SecondaryButton type="button" :disabled="syncForm.processing" @click="syncNow">
                                {{ $t("calendar.settings.sync_now") }}
                            </SecondaryButton>
                            <PrimaryButton type="submit" :disabled="settingsForm.processing">
                                {{ settingsForm.processing ? $t("common.saving") : $t("common.save") }}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>

                <!-- Send every existing task to the calendar in one go -->
                <Panel :title="$t('crm.calendar.sync_all')">
                    <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $t("crm.calendar.bulk_sync_description") }}</p>
                    <SecondaryButton type="button" class="mt-4" :disabled="bulkSyncLoading" @click="bulkSync">
                        {{ bulkSyncLoading ? $t("crm.calendar.syncing") : $t("crm.calendar.sync_all") }}
                    </SecondaryButton>
                    <Notice v-if="bulkSyncResult" class="mt-3" :tone="bulkSyncResult.success ? 'success' : 'danger'">
                        {{ bulkSyncResult.message }}
                    </Notice>
                </Panel>

                <!-- Changes made in Google reach NetSendo through this channel -->
                <Panel :title="$t('calendar.push_notifications.title')">
                    <template #actions>
                        <StatusPill :tone="connection.has_push_notifications ? 'success' : 'warning'">
                            {{
                                connection.has_push_notifications
                                    ? $t("calendar.push_notifications.active")
                                    : $t("calendar.push_notifications.inactive")
                            }}
                        </StatusPill>
                    </template>

                    <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $t("calendar.webhook.description") }}</p>
                    <p v-if="connection.channel_expires_at" class="mt-1 text-sm tabular-nums text-slate-600 dark:text-slate-400">
                        {{ $t("calendar.push_notifications.expires") }}: {{ formatDate(connection.channel_expires_at) }}
                    </p>

                    <Notice
                        v-if="!connection.has_push_notifications"
                        class="mt-4"
                        tone="warning"
                        :title="$t('calendar.webhook.setup_required')"
                    >
                        {{ $t("calendar.webhook.setup_required_desc") }}
                    </Notice>

                    <CopyField class="mt-4" :label="$t('calendar.webhook.url_label')" :value="webhook_url || ''" />

                    <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">
                        {{ $t("calendar.webhook.instructions_title") }}
                    </p>
                    <ol class="mt-1 list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                        <li v-for="step in 5" :key="step">{{ $t("calendar.webhook.step" + step) }}</li>
                    </ol>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $t("calendar.webhook.note") }}</p>

                    <SecondaryButton type="button" class="mt-4" :disabled="refreshChannelForm.processing" @click="refreshChannel">
                        {{
                            connection.has_push_notifications
                                ? $t("calendar.push_notifications.refresh")
                                : $t("calendar.webhook.activate_btn")
                        }}
                    </SecondaryButton>
                </Panel>

                <!-- The colour each kind of task gets in the calendar -->
                <Panel flush :title="$t('calendar.task_colors.title')">
                    <p class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400">{{ $t("calendar.task_colors.description") }}</p>
                    <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60">
                        <li
                            v-for="taskType in taskTypes"
                            :key="taskType"
                            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-3"
                        >
                            <span class="flex items-center gap-2.5 text-sm font-medium text-slate-900 dark:text-white">
                                <span
                                    aria-hidden="true"
                                    class="h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: colorsForm.task_type_colors[taskType] }"
                                ></span>
                                {{ $t("crm.task_types." + taskType) }}
                            </span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    v-for="preset in colorPresets"
                                    :key="preset"
                                    type="button"
                                    :aria-label="preset"
                                    :aria-pressed="colorsForm.task_type_colors[taskType] === preset"
                                    class="h-7 w-7 rounded-full border-2 transition-transform focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                                    :class="
                                        colorsForm.task_type_colors[taskType] === preset
                                            ? 'scale-110 border-slate-900 dark:border-white'
                                            : 'border-transparent'
                                    "
                                    :style="{ backgroundColor: preset }"
                                    @click="setTaskColor(taskType, preset)"
                                ></button>
                                <input
                                    type="color"
                                    :value="colorsForm.task_type_colors[taskType]"
                                    :aria-label="$t('calendar.task_colors.custom_color')"
                                    :title="$t('calendar.task_colors.custom_color')"
                                    class="h-7 w-9 cursor-pointer rounded border border-slate-300 bg-transparent p-0.5 dark:border-slate-600"
                                    @input="setTaskColor(taskType, $event.target.value)"
                                />
                            </div>
                        </li>
                    </ul>
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-700/60">
                        <SecondaryButton type="button" :disabled="colorsForm.processing" @click="resetColorsToDefault">
                            {{ $t("calendar.task_colors.reset_default") }}
                        </SecondaryButton>
                        <PrimaryButton type="button" :disabled="colorsForm.processing" @click="saveTaskColors">
                            {{ colorsForm.processing ? $t("common.saving") : $t("common.save") }}
                        </PrimaryButton>
                    </div>
                </Panel>
            </template>

            <!-- The address Google needs before the first connection -->
            <Panel :title="$t('calendar.oauth.title')">
                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $t("calendar.oauth.description") }}</p>
                <Notice class="mt-4" tone="warning" :title="$t('calendar.oauth.important')">
                    {{ $t("calendar.oauth.instruction") }}
                </Notice>
                <CopyField class="mt-4" :label="$t('calendar.oauth.redirect_uri_label')" :value="calendar_redirect_uri || ''" />
            </Panel>
        </div>

        <!-- Several Google apps are set up: which one to connect through -->
        <Modal :show="showConnectModal" max-width="lg" @close="showConnectModal = false">
            <div class="flex flex-col gap-4 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ $t("calendar.modal.select_integration") }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $t("calendar.modal.select_description") }}</p>
                </div>
                <ul class="space-y-2">
                    <li v-for="integration in integrations" :key="integration.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 px-4 py-3 text-left transition-colors hover:border-indigo-500 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 dark:border-slate-600 dark:hover:bg-slate-700/40"
                            @click="connectWithIntegration(integration.id)"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ integration.name }}</span>
                                <span class="block truncate font-mono text-xs text-slate-600 dark:text-slate-400">{{ integration.client_id }}</span>
                            </span>
                            <svg
                                aria-hidden="true"
                                class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </li>
                </ul>
                <div class="flex justify-end">
                    <SecondaryButton type="button" @click="showConnectModal = false">{{ $t("common.cancel") }}</SecondaryButton>
                </div>
            </div>
        </Modal>

        <ConfirmModal
            :show="showDisconnect"
            type="danger"
            :title="$t('calendar.connection.disconnect')"
            :message="$t('calendar.confirm_disconnect')"
            :confirm-text="$t('calendar.connection.disconnect')"
            :processing="disconnectForm.processing"
            @close="showDisconnect = false"
            @confirm="disconnect"
        >
            {{ connection?.connected_email }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
