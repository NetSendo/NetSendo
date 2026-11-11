<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SecretField from "@/Components/Ui/SecretField.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import CheckList from "./Partials/CheckList.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    integrations: { type: Array, default: () => [] },
    mailingLists: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    teamMembers: { type: Array, default: () => [] },
    webhookUrl: { type: String, default: "" },
});

const { t, te, locale } = useI18n();
const page = usePage();

// Failures come from the server with Calendly's own reason; successes are worded here, in the
// user's language (the server's are English only)
const { toast, showToast, dismiss } = useToast({ flash: "error" });

// The only success that arrives with a full page load is the return from Calendly's consent
// screen
onMounted(() => {
    if (page.props.flash?.success) showToast(t("calendly_settings.toast.connected"));
});

// A refused action still ends in "success" for Inertia: the server redirects back with the
// reason in the session
const succeeded = () => !page.props.flash?.error;

const activeCount = computed(() => props.integrations.filter((integration) => integration.is_active).length);

const nameOf = (integration) =>
    integration.calendly_user_name || integration.calendly_user_email || t("calendly_settings.account_fallback");

const statusOf = (integration) => {
    if (integration.is_pending) return { tone: "warning", label: t("calendly_settings.status.pending") };
    return integration.is_active
        ? { tone: "success", label: t("calendly_settings.status.active") }
        : { tone: "neutral", label: t("calendly_settings.status.inactive") };
};

const BOOKING_TONES = { scheduled: "info", canceled: "danger", no_show: "warning", completed: "success" };

const bookingStatus = (status) => ({
    tone: BOOKING_TONES[status] || "neutral",
    label: te(`calendly_settings.booking_status.${status}`) ? t(`calendly_settings.booking_status.${status}`) : status,
});

const formatDateTime = (value) =>
    value ? new Date(value).toLocaleString(locale.value, { dateStyle: "medium", timeStyle: "short" }) : "—";

/* Connecting an account ---------------------------------------------------------------- */

const showConnect = ref(false);
const connectForm = useForm({ client_id: "", client_secret: "" });
const redirectUri = computed(() => route("settings.calendly.callback"));

const openConnect = () => {
    connectForm.reset();
    connectForm.clearErrors();
    showConnect.value = true;
};

// On success the server answers with a full redirect to Calendly's consent screen
const connect = () => connectForm.post(route("settings.calendly.connect"));

/* Actions on a connected account -------------------------------------------------------- */

const busy = ref(null); // "sync-3", "test-3"

const syncEventTypes = (integration) => {
    busy.value = `sync-${integration.id}`;
    router.post(
        route("settings.calendly.sync-event-types", integration.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => succeeded() && showToast(t("calendly_settings.toast.synced")),
            onFinish: () => (busy.value = null),
        },
    );
};

const testWebhook = (integration) => {
    busy.value = `test-${integration.id}`;
    router.post(
        route("settings.calendly.test-webhook", integration.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => succeeded() && showToast(t("calendly_settings.toast.webhook_ok")),
            onFinish: () => (busy.value = null),
        },
    );
};

const toDisconnect = ref(null);
const disconnecting = ref(false);

const disconnect = () => {
    disconnecting.value = true;
    router.post(
        route("settings.calendly.disconnect", toDisconnect.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => succeeded() && showToast(t("calendly_settings.toast.disconnected")),
            onFinish: () => {
                disconnecting.value = false;
                toDisconnect.value = null;
            },
        },
    );
};

/* What a booking does: the settings dialog ---------------------------------------------- */

const copy = (value) => JSON.parse(JSON.stringify(value ?? null));

// A copy with every group present. Mappings are keyed by the event type's address: an empty
// set comes from the server as a list, and keys written to a list are lost on the way back.
const normalise = (settings) => {
    const source = copy(settings) || {};
    const mappings = source.event_type_mappings;

    return {
        crm: { enabled: true, default_status: "lead", create_tasks: true, default_owner_id: null, ...(source.crm || {}) },
        mailing_lists: {
            enabled: true,
            ...(source.mailing_lists || {}),
            default_list_ids: source.mailing_lists?.default_list_ids || [],
            default_tag_ids: source.mailing_lists?.default_tag_ids || [],
        },
        event_type_mappings: mappings && !Array.isArray(mappings) ? mappings : {},
        automation: {
            trigger_on_booking: true,
            trigger_on_cancellation: true,
            trigger_on_no_show: false,
            ...(source.automation || {}),
        },
    };
};

const configured = ref(null);
const settingsTab = ref("crm");
const settingsForm = useForm({ settings: normalise(null) });

const settingsTabs = computed(() => [
    { value: "crm", label: t("calendly_settings.tabs.crm") },
    { value: "lists", label: t("calendly_settings.tabs.lists") },
    { value: "event_types", label: t("calendly_settings.tabs.event_types") },
    { value: "automation", label: t("calendly_settings.tabs.automation") },
]);

const openSettings = (integration) => {
    configured.value = integration;
    settingsTab.value = "crm";
    settingsForm.clearErrors();
    settingsForm.settings = normalise(integration.settings);
};

const closeSettings = () => (configured.value = null);

const settingsErrors = computed(() => [...new Set(Object.values(settingsForm.errors))]);

const saveSettings = () => {
    settingsForm.put(route("settings.calendly.settings", configured.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (!succeeded()) return;
            closeSettings();
            showToast(t("calendly_settings.toast.saved"));
        },
    });
};

const mappingOf = (uri) =>
    settingsForm.settings.event_type_mappings[uri] || { use_custom: false, list_ids: [], tag_ids: [] };

const setMapping = (uri, field, value) => {
    settingsForm.settings.event_type_mappings[uri] = { ...mappingOf(uri), [field]: value };
};

const tick = "h-4 w-4 shrink-0 accent-indigo-600";
const optionRow = "flex min-h-10 cursor-pointer items-center gap-3 text-sm text-slate-900 dark:text-slate-100";
</script>

<template>
    <Head :title="t('calendly_settings.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('calendly_settings.title')" :subtitle="t('calendly_settings.subtitle')">
                <StatusPill :tone="activeCount ? 'success' : 'neutral'">
                    {{ activeCount ? t("calendly_settings.connected") : t("calendly_settings.not_connected") }}
                </StatusPill>
                <CreateLink v-if="integrations.length" @click="openConnect">
                    {{ t("calendly_settings.connect_another") }}
                </CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <!-- Where this page sits -->
            <nav
                :aria-label="t('calendly_settings.title')"
                class="flex flex-wrap items-center gap-x-2 text-sm text-slate-600 dark:text-slate-400"
            >
                <Link
                    :href="route('marketplace.index')"
                    class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    {{ t("calendly_settings.marketplace") }}
                </Link>
                <span aria-hidden="true">/</span>
                <Link
                    :href="route('marketplace.calendly')"
                    class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Calendly
                </Link>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ t("calendly_settings.breadcrumb_settings") }}</span>
            </nav>

            <!-- Nothing connected yet -->
            <Panel v-if="integrations.length === 0" flush>
                <EmptyState
                    class="!border-t-0"
                    :title="t('calendly_settings.empty.title')"
                    :description="t('calendly_settings.empty.description')"
                >
                    <CreateLink @click="openConnect">{{ t("calendly_settings.connect_button") }}</CreateLink>
                </EmptyState>
            </Panel>

            <!-- One panel per connected account -->
            <Panel v-for="integration in integrations" :key="integration.id" flush>
                <template #title>
                    <span class="flex min-w-0 flex-col">
                        <span class="break-words">{{ nameOf(integration) }}</span>
                        <span
                            v-if="integration.calendly_user_name && integration.calendly_user_email"
                            class="break-all text-sm font-normal text-slate-600 dark:text-slate-400"
                        >
                            {{ integration.calendly_user_email }}
                        </span>
                    </span>
                </template>

                <template #actions>
                    <StatusPill :tone="statusOf(integration).tone">{{ statusOf(integration).label }}</StatusPill>

                    <button
                        v-if="!integration.is_pending"
                        type="button"
                        class="min-h-9 rounded-lg px-2 text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 max-sm:hidden dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="openSettings(integration)"
                    >
                        {{ t("calendly_settings.actions.configure") }}
                    </button>

                    <ActionMenu :label="t('calendly_settings.actions.menu_for', { name: nameOf(integration) })">
                        <template v-if="!integration.is_pending">
                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                            <ActionMenuItem class="sm:hidden" @click="openSettings(integration)">
                                {{ t("calendly_settings.actions.configure") }}
                            </ActionMenuItem>
                            <ActionMenuItem
                                :disabled="busy === `sync-${integration.id}`"
                                @click="syncEventTypes(integration)"
                            >
                                {{
                                    busy === `sync-${integration.id}`
                                        ? t("calendly_settings.actions.syncing")
                                        : t("calendly_settings.actions.sync")
                                }}
                            </ActionMenuItem>
                            <ActionMenuItem
                                :disabled="busy === `test-${integration.id}`"
                                @click="testWebhook(integration)"
                            >
                                {{
                                    busy === `test-${integration.id}`
                                        ? t("common.testing")
                                        : t("calendly_settings.actions.test")
                                }}
                            </ActionMenuItem>
                        </template>
                        <ActionMenuItem danger @click="toDisconnect = integration">
                            {{
                                integration.is_pending
                                    ? t("common.delete")
                                    : t("calendly_settings.actions.disconnect")
                            }}
                        </ActionMenuItem>
                    </ActionMenu>
                </template>

                <!-- The app details were saved, the consent on Calendly's side was not given -->
                <div v-if="integration.is_pending" class="px-5 pb-5 pt-2">
                    <Notice tone="warning" :title="t('calendly_settings.pending.title')">
                        {{ t("calendly_settings.pending.text") }}
                    </Notice>
                </div>

                <template v-else>
                    <div class="px-5 pb-5 pt-2">
                        <h3 class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ t("calendly_settings.event_types") }}
                        </h3>
                        <ul v-if="integration.event_types?.length" class="mt-2 flex flex-wrap gap-1.5">
                            <li
                                v-for="eventType in integration.event_types"
                                :key="eventType.uri"
                                class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                            >
                                {{ eventType.name }}
                            </li>
                        </ul>
                        <p v-else class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                            {{ t("calendly_settings.no_event_types") }}
                        </p>
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-700/60">
                        <h3 class="px-5 pt-4 text-sm font-medium text-slate-900 dark:text-white">
                            {{ t("calendly_settings.recent.title") }}
                        </h3>

                        <p
                            v-if="!integration.recent_events?.length"
                            class="px-5 pb-5 pt-1 text-sm text-slate-600 dark:text-slate-400"
                        >
                            {{ t("calendly_settings.recent.empty") }}
                        </p>

                        <div v-else class="relative mt-2 overflow-x-auto">
                            <table class="w-full text-left text-sm sm:min-w-[560px]">
                                <!-- One column is left on a phone: it needs no heading -->
                                <thead class="max-sm:hidden">
                                    <tr
                                        class="border-b border-slate-200 text-xs font-medium text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                                    >
                                        <th scope="col" class="px-5 py-2 font-medium">
                                            {{ t("calendly_settings.recent.invitee") }}
                                        </th>
                                        <th scope="col" class="px-3 py-2 font-medium max-sm:hidden">
                                            {{ t("calendly_settings.recent.event") }}
                                        </th>
                                        <th scope="col" class="px-3 py-2 font-medium max-sm:hidden">
                                            {{ t("calendly_settings.recent.date") }}
                                        </th>
                                        <th scope="col" class="py-2 pl-3 pr-5 font-medium max-sm:hidden">
                                            {{ t("common.status") }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                                    <tr v-for="event in integration.recent_events" :key="event.id">
                                        <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[38%]">
                                            <p class="truncate font-medium text-slate-900 dark:text-white">
                                                {{ event.invitee_name || event.invitee_email }}
                                            </p>
                                            <p
                                                v-if="event.invitee_name"
                                                class="truncate text-slate-600 dark:text-slate-400"
                                            >
                                                {{ event.invitee_email }}
                                            </p>
                                            <!-- On a phone the other columns are folded under the person -->
                                            <div class="mt-1.5 flex flex-col items-start gap-1.5 sm:hidden">
                                                <p class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                                    {{ event.event_type_name }} · {{ formatDateTime(event.start_time) }}
                                                </p>
                                                <StatusPill :tone="bookingStatus(event.status).tone">
                                                    {{ bookingStatus(event.status).label }}
                                                </StatusPill>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-slate-900 max-sm:hidden dark:text-slate-100">
                                            {{ event.event_type_name }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400"
                                        >
                                            {{ formatDateTime(event.start_time) }}
                                        </td>
                                        <td class="py-3 pl-3 pr-5 max-sm:hidden">
                                            <StatusPill :tone="bookingStatus(event.status).tone">
                                                {{ bookingStatus(event.status).label }}
                                            </StatusPill>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </Panel>

            <Panel :title="t('calendly_settings.webhook.title')">
                <CopyField class="-mt-2" :value="webhookUrl" :hint="t('calendly_settings.webhook.hint')" />
            </Panel>
        </div>

        <!-- Connect an account: the details of the user's own OAuth app at Calendly -->
        <FormDialog
            :show="showConnect"
            :title="t('calendly_settings.connect.title')"
            :processing="connectForm.processing"
            :submit-label="connectForm.processing ? t('calendly_settings.connect.connecting') : t('calendly_settings.connect.submit')"
            @close="showConnect = false"
            @submit="connect"
        >
            <div class="space-y-4">
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    <p>{{ t("calendly_settings.connect.intro") }}</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <i18n-t keypath="calendly_settings.connect.step_open" tag="li">
                            <template #link>
                                <a
                                    href="https://developer.calendly.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >developer.calendly.com</a
                                >
                            </template>
                        </i18n-t>
                        <li>{{ t("calendly_settings.connect.step_create") }}</li>
                        <li>{{ t("calendly_settings.connect.step_redirect") }}</li>
                        <li>{{ t("calendly_settings.connect.step_copy") }}</li>
                    </ol>
                </div>

                <CopyField :label="t('calendly_settings.connect.redirect_uri')" :value="redirectUri" />

                <div>
                    <label for="calendly-client-id" :class="labelClass">{{ t("calendly_settings.connect.client_id") }}</label>
                    <input
                        id="calendly-client-id"
                        v-model="connectForm.client_id"
                        type="text"
                        required
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-1 font-mono"
                        :class="[fieldClass, { '!border-rose-500': connectForm.errors.client_id }]"
                    />
                    <p v-if="connectForm.errors.client_id" :class="errorClass">{{ connectForm.errors.client_id }}</p>
                </div>

                <SecretField
                    id="calendly-client-secret"
                    v-model="connectForm.client_secret"
                    :label="t('calendly_settings.connect.client_secret')"
                    :error="connectForm.errors.client_secret"
                />
            </div>
        </FormDialog>

        <!-- What a booking does in NetSendo -->
        <FormDialog
            :show="!!configured"
            :title="t('calendly_settings.settings.title')"
            max-width="2xl"
            :processing="settingsForm.processing"
            :submit-label="t('common.save')"
            :errors="settingsErrors"
            :toast="toast"
            :note="configured ? nameOf(configured) : ''"
            @close="closeSettings"
            @submit="saveSettings"
        >
            <template #tabs>
                <div class="overflow-x-auto">
                    <SegmentedControl
                        v-model="settingsTab"
                        :options="settingsTabs"
                        :label="t('calendly_settings.tabs.label')"
                    />
                </div>
            </template>

            <!-- The dialog keeps its height from tab to tab instead of jumping -->
            <div class="sm:min-h-72">
            <!-- CRM -->
            <div v-show="settingsTab === 'crm'" class="space-y-4">
                <div class="space-y-1">
                    <label :class="optionRow">
                        <input v-model="settingsForm.settings.crm.enabled" type="checkbox" :class="tick" />
                        {{ t("calendly_settings.crm.enabled") }}
                    </label>
                    <label :class="optionRow">
                        <input v-model="settingsForm.settings.crm.create_tasks" type="checkbox" :class="tick" />
                        {{ t("calendly_settings.crm.create_tasks") }}
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="calendly-crm-status" :class="labelClass">
                            {{ t("calendly_settings.crm.default_status") }}
                        </label>
                        <select
                            id="calendly-crm-status"
                            v-model="settingsForm.settings.crm.default_status"
                            class="mt-1"
                            :class="fieldClass"
                        >
                            <option value="lead">{{ t("crm.contacts.status.lead") }}</option>
                            <option value="prospect">{{ t("crm.contacts.status.prospect") }}</option>
                            <option value="customer">{{ t("crm.contacts.status.customer") }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="calendly-crm-owner" :class="labelClass">
                            {{ t("calendly_settings.crm.default_owner") }}
                        </label>
                        <select
                            id="calendly-crm-owner"
                            v-model="settingsForm.settings.crm.default_owner_id"
                            class="mt-1"
                            :class="fieldClass"
                        >
                            <option :value="null">{{ t("calendly_settings.crm.no_owner") }}</option>
                            <option v-for="member in teamMembers" :key="member.id" :value="member.id">
                                {{ member.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Lists and tags every invitee gets -->
            <div v-show="settingsTab === 'lists'" class="space-y-4">
                <label :class="optionRow">
                    <input v-model="settingsForm.settings.mailing_lists.enabled" type="checkbox" :class="tick" />
                    {{ t("calendly_settings.lists.enabled") }}
                </label>

                <template v-if="settingsForm.settings.mailing_lists.enabled">
                    <CheckList
                        v-model="settingsForm.settings.mailing_lists.default_list_ids"
                        :items="mailingLists"
                        :label="t('calendly_settings.lists.default_lists')"
                        :search-placeholder="t('calendly_settings.lists.search_lists')"
                        :empty-text="t('calendly_settings.lists.no_lists')"
                    />
                    <CheckList
                        v-model="settingsForm.settings.mailing_lists.default_tag_ids"
                        :items="tags"
                        :label="t('calendly_settings.lists.default_tags')"
                        :search-placeholder="t('calendly_settings.lists.search_tags')"
                        :empty-text="t('calendly_settings.lists.no_tags')"
                    />
                </template>
            </div>

            <!-- Other lists and tags for chosen event types -->
            <div v-show="settingsTab === 'event_types'" class="space-y-4">
                <Notice v-if="!settingsForm.settings.mailing_lists.enabled" tone="info">
                    {{ t("calendly_settings.per_type.lists_off") }}
                </Notice>
                <p v-else-if="!configured?.event_types?.length" class="text-sm text-slate-600 dark:text-slate-400">
                    {{ t("calendly_settings.per_type.none") }}
                </p>
                <template v-else>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("calendly_settings.per_type.intro") }}</p>

                    <div
                        v-for="eventType in configured.event_types"
                        :key="eventType.uri"
                        class="rounded-xl border border-slate-200 dark:border-slate-700/60"
                    >
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="min-w-0">
                                <span class="block break-words text-sm font-medium text-slate-900 dark:text-white">
                                    {{ eventType.name }}
                                </span>
                                <span :class="hintClass" class="!mt-0 block">
                                    {{
                                        mappingOf(eventType.uri).use_custom
                                            ? t("calendly_settings.per_type.custom")
                                            : t("calendly_settings.per_type.default")
                                    }}
                                </span>
                            </span>
                            <Switch
                                :model-value="!!mappingOf(eventType.uri).use_custom"
                                :label="t('calendly_settings.per_type.switch_label', { name: eventType.name })"
                                @update:model-value="setMapping(eventType.uri, 'use_custom', $event)"
                            />
                        </div>

                        <div
                            v-if="mappingOf(eventType.uri).use_custom"
                            class="space-y-4 border-t border-slate-200 px-4 py-4 dark:border-slate-700/60"
                        >
                            <CheckList
                                :model-value="mappingOf(eventType.uri).list_ids || []"
                                :items="mailingLists"
                                :label="t('calendly_settings.per_type.lists')"
                                :search-placeholder="t('calendly_settings.lists.search_lists')"
                                :empty-text="t('calendly_settings.lists.no_lists')"
                                @update:model-value="setMapping(eventType.uri, 'list_ids', $event)"
                            />
                            <CheckList
                                :model-value="mappingOf(eventType.uri).tag_ids || []"
                                :items="tags"
                                :label="t('calendly_settings.per_type.tags')"
                                :search-placeholder="t('calendly_settings.lists.search_tags')"
                                :empty-text="t('calendly_settings.lists.no_tags')"
                                @update:model-value="setMapping(eventType.uri, 'tag_ids', $event)"
                            />
                        </div>
                    </div>
                </template>
            </div>

            <!-- Which events start automations -->
            <div v-show="settingsTab === 'automation'" class="space-y-1">
                <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">{{ t("calendly_settings.automation.intro") }}</p>
                <label :class="optionRow">
                    <input v-model="settingsForm.settings.automation.trigger_on_booking" type="checkbox" :class="tick" />
                    {{ t("calendly_settings.automation.booking") }}
                </label>
                <label :class="optionRow">
                    <input
                        v-model="settingsForm.settings.automation.trigger_on_cancellation"
                        type="checkbox"
                        :class="tick"
                    />
                    {{ t("calendly_settings.automation.cancellation") }}
                </label>
                <label :class="optionRow">
                    <input v-model="settingsForm.settings.automation.trigger_on_no_show" type="checkbox" :class="tick" />
                    {{ t("calendly_settings.automation.no_show") }}
                </label>
            </div>
            </div>
        </FormDialog>

        <ConfirmModal
            :show="!!toDisconnect"
            type="danger"
            :title="t('calendly_settings.disconnect.title')"
            :message="t('calendly_settings.disconnect.message')"
            :confirm-text="toDisconnect?.is_pending ? t('common.delete') : t('calendly_settings.actions.disconnect')"
            :processing="disconnecting"
            @close="toDisconnect = null"
            @confirm="disconnect"
        >
            {{ toDisconnect ? nameOf(toDisconnect) : "" }}
        </ConfirmModal>

        <!-- A dialog is in the top layer: while one is open the page's toast would sit under it -->
        <Toast :toast="showConnect || configured ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
