<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";
import GoldBadge from "@/Components/License/GoldBadge.vue";
import GoldNotice from "@/Components/License/GoldNotice.vue";
import { useLicense } from "@/Composables/useLicense";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const page = usePage();

// OpenRouter and Ollama are GOLD (provider.required_feature, set by AiIntegrationController)
const { can, requireGold } = useLicense();
const providerLocked = (key) => {
    const feature = props.providers[key]?.required_feature;
    return !!feature && !can(feature);
};
const anyProviderLocked = computed(() =>
    Object.keys(props.providers).some((key) => providerLocked(key)),
);

const props = defineProps({
    integrations: {
        type: Array,
        default: () => [],
    },
    integrationsMap: {
        type: Object,
        default: () => ({}),
    },
    providers: {
        type: Object,
        default: () => ({}),
    },
});

// Modal state
const showModal = ref(false);
const modalMode = ref("create"); // 'create' or 'edit'
const editingIntegration = ref(null);
const testingIntegration = ref(null);
const testResult = ref(null);
const fetchingModels = ref(null);
const showApiKey = ref(false);

// Delete modal state
const showDeleteModal = ref(false);
const deletingIntegration = ref(null);

// One message at a time: in the corner of the page, or in the footer of the open dialog
// (a dialog covers the corner). The server's own message is shown only for a failure.
const { toast, showToast, dismiss } = useToast({ flash: "error" });

// Model search
const modelSearch = ref("");

// Form
const form = useForm({
    provider: "",
    name: "",
    api_key: "",
    base_url: "",
    default_model: "",
    max_tokens_small: 2000,
    max_tokens_large: 50000,
});

// Provider logos (SVG icons)
const providerIcons = {
    openai: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.485 4.485 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"/></svg>`,
    anthropic: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.304 3.541h-3.672l6.696 16.918h3.672zm-10.608 0L0 20.459h3.744l1.368-3.552h6.624l1.392 3.552h3.744L10.176 3.541zm-.384 10.434l2.304-5.976 2.328 5.976z"/></svg>`,
    grok: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg>`,
    openrouter: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>`,
    ollama: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm0-14a6 6 0 1 0 6 6 6 6 0 0 0-6-6zm0 10a4 4 0 1 1 4-4 4 4 0 0 1-4 4z"/></svg>`,
    gemini: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.352 0 0 5.352 0 12s5.352 12 12 12 12-5.352 12-12S18.648 0 12 0zm0 3.6c4.632 0 8.4 3.768 8.4 8.4 0 .672-.096 1.32-.24 1.944l-3.864-2.232V9.6a4.2 4.2 0 0 0-8.4 0v2.112L4.032 13.8c-.144-.6-.24-1.248-.24-1.944A8.424 8.424 0 0 1 12 3.6zM8.4 12V9.6a3.6 3.6 0 0 1 7.2 0V12l-3.6 2.088zm3.6 8.4a8.352 8.352 0 0 1-7.296-4.296l3.696-2.136 3.6 2.088 3.6-2.088 3.696 2.136A8.352 8.352 0 0 1 12 20.4z"/></svg>`,
};

// Get integration for a provider
const getIntegration = (providerKey) => {
    return props.integrationsMap[providerKey] || null;
};

// Check if provider is configured
const isConfigured = (providerKey) => {
    const integration = getIntegration(providerKey);
    if (!integration) return false;

    const provider = props.providers[providerKey];
    if (!provider.requires_api_key) return true;

    // Use has_api_key attribute from backend
    return integration.has_api_key === true;
};

// Where a provider stands: not set up, missing its key, switched off, tested and fine,
// failing, or set up and never tested
const statusOf = (providerKey) => {
    const integration = getIntegration(providerKey);
    if (!integration) return { tone: "neutral", label: t("ai.status.not_configured") };
    if (!isConfigured(providerKey)) return { tone: "warning", label: t("ai.status.no_api_key") };
    if (!integration.is_active) return { tone: "neutral", label: t("ai.status.inactive") };
    if (integration.last_test_status === "success") return { tone: "success", label: t("ai.status.active") };
    if (integration.last_test_status === "error") return { tone: "danger", label: t("ai.status.connection_error") };
    return { tone: "info", label: t("ai.status.configured") };
};

// Where each service hands out its API keys
const KEY_PAGES = {
    openai: "https://platform.openai.com/api-keys",
    anthropic: "https://console.anthropic.com/settings/keys",
    grok: "https://console.x.ai/",
    openrouter: "https://openrouter.ai/keys",
    gemini: "https://aistudio.google.com/apikey",
};
const hostOf = (url) => url.replace(/^https?:\/\//, "").replace(/\/$/, "");

// Every validation message of the last save, each once
const formErrorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);

// Open modal for creating/editing
const openModal = (providerKey) => {
    const integration = getIntegration(providerKey);
    const provider = props.providers[providerKey];

    // Adding a GOLD provider without GOLD: show the upgrade dialog instead (an existing
    // one can still be edited or removed)
    if (!integration && providerLocked(providerKey)) {
        requireGold(provider?.required_feature);
        return;
    }

    if (integration) {
        // Edit mode
        modalMode.value = "edit";
        editingIntegration.value = integration;
        form.provider = providerKey;
        form.name = integration.name;
        form.api_key = ""; // Don't show existing key
        form.base_url = integration.base_url || "";
        form.default_model = integration.default_model || "";
        form.max_tokens_small = integration.max_tokens_small || 2000;
        form.max_tokens_large = integration.max_tokens_large || 50000;
    } else {
        // Create mode
        modalMode.value = "create";
        editingIntegration.value = null;
        form.provider = providerKey;
        form.name = provider.name;
        form.api_key = "";
        form.base_url = provider.default_base_url || "";
        form.default_model = "";
        form.max_tokens_small = 2000;
        form.max_tokens_large = 50000;
    }

    showApiKey.value = false;
    testResult.value = null;
    modelSearch.value = "";
    form.clearErrors();
    dismiss();
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingIntegration.value = null;
    form.reset();
    form.clearErrors();
    testResult.value = null;
    modelSearch.value = "";
};

// Submit form
const submitForm = () => {
    if (modalMode.value === "edit") {
        form.put(
            route(
                "settings.ai-integrations.update",
                editingIntegration.value.id,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    showToast(t("ai.notifications.saved"));
                },
            },
        );
    } else {
        form.post(route("settings.ai-integrations.store"), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast(t("ai.notifications.saved"));
            },
        });
    }
};

// Test connection
const testConnection = async (integration) => {
    if (!integration || !integration.id) {
        console.error("Invalid integration for testing:", integration);
        return;
    }

    testingIntegration.value = integration.id;
    testResult.value = null;

    try {
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]',
        )?.content;

        if (!csrfToken) {
            console.error("CSRF token not found");
            showToast(t("common.notifications.error_occurred"), false);
            return;
        }

        const response = await fetch(
            route("settings.ai-integrations.test", integration.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
            },
        );

        const data = await response.json();

        testResult.value = data;
        showToast(data.message, data.success);

        // Refresh the page to update integration status
        router.reload({ only: ["integrations", "integrationsMap"] });
    } catch (error) {
        console.error("Test connection error:", error);
        showToast(
            t("common.error") + ": " + error.message,
            false,
        );
    } finally {
        testingIntegration.value = null;
    }
};

// Fetch models from API
const fetchModels = async (integration) => {
    fetchingModels.value = integration.id;

    try {
        const response = await fetch(
            route("settings.ai-integrations.models", integration.id),
            {
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    ).content,
                },
            },
        );

        const result = await response.json();

        if (result.success) {
            router.reload({ only: ["integrations", "integrationsMap"] });
        }
    } catch (error) {
        console.error("Error fetching models:", error);
    } finally {
        fetchingModels.value = null;
    }
};

// Open delete confirmation modal
const confirmDelete = (integration) => {
    deletingIntegration.value = integration;
    showDeleteModal.value = true;
};

// Close delete modal
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingIntegration.value = null;
};

// Delete integration
const deleteIntegration = () => {
    if (!deletingIntegration.value) return;

    router.delete(
        route("settings.ai-integrations.destroy", deletingIntegration.value.id),
        {
            preserveScroll: true,
            onSuccess: () => showToast(t("ai.notifications.deleted")),
            onFinish: () => closeDeleteModal(),
        },
    );
};

// The switch in the row
const toggling = ref(null);

const toggleActive = (integration) => {
    if (toggling.value) return;
    toggling.value = integration.id;
    router.put(
        route("settings.ai-integrations.update", integration.id),
        {
            is_active: !integration.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                showToast(t(integration.is_active ? "ai.notifications.disabled" : "ai.notifications.enabled")),
            onError: (errors) => showToast(Object.values(errors).flat().join(" "), false),
            onFinish: () => (toggling.value = null),
        },
    );
};

// Localize the functional descriptors that may appear in a model label.
// Time/recency wording ("Najnowszy", month tags, …) is deliberately no longer
// used in model names, so it is not translated here.
const localizeModelName = (name) => {
    if (!name) return name;
    return name
        .replace("(Głębokie analizy)", `(${t("ai.models.thinking")})`)
        .replace("(Szybki i tani)", `(${t("ai.models.fast_and_cheap")})`)
        .replace("(Rozumowanie)", `(${t("ai.models.reasoning")})`)
        .replace("(Szybki)", `(${t("ai.models.fast")})`);
};

// Get models for current provider with search filter.
// The curated catalog comes from the backend (props.providers[…].default_models),
// so there is a single source of truth and the list never drifts from the server.
const currentProviderModels = computed(() => {
    if (!form.provider) return [];

    const integration = getIntegration(form.provider);
    const defaults = props.providers[form.provider]?.default_models || [];

    // Always start with the curated catalog
    const modelMap = new Map();
    defaults.forEach((m) => modelMap.set(m.model_id, m));

    // Add API/custom models that aren't already in the catalog
    if (integration && integration.models && integration.models.length > 0) {
        integration.models.forEach((m) => {
            if (!modelMap.has(m.model_id)) {
                modelMap.set(m.model_id, m);
            }
        });
    }

    let models = Array.from(modelMap.values());

    // Filter by search
    if (modelSearch.value) {
        const search = modelSearch.value.toLowerCase();
        models = models.filter(
            (m) =>
                m.model_id.toLowerCase().includes(search) ||
                m.display_name.toLowerCase().includes(search),
        );
    }

    return models.map((m) => ({
        ...m,
        display_name: localizeModelName(m.display_name),
    }));
});
</script>

<template>
    <Head :title="$t('ai.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('ai.title')" :subtitle="$t('ai.subtitle')" />
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <GoldNotice
                v-if="anyProviderLocked"
                feature="advanced_ai"
                :message="$t('license.gating.ai_provider_locked')"
            />

            <!-- Every service the panel can talk to, set up or not -->
            <Panel flush>
                <div class="relative -mt-3 overflow-x-auto">
                    <table class="w-full text-sm sm:min-w-[820px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("ai.table.provider") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("ai.table.status") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("ai.modal.default_model_label") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("ai.table.active") }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("common.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(provider, key) in providers"
                                :key="key"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[40%]">
                                    <div class="flex items-center gap-3">
                                        <span
                                            aria-hidden="true"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 max-sm:hidden dark:border-slate-700/60"
                                        >
                                            <span
                                                class="h-4 w-4 text-slate-900 dark:text-white"
                                                v-html="providerIcons[key]"
                                            ></span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="min-w-0 truncate text-left font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                                    @click="openModal(key)"
                                                >
                                                    {{ provider.name }}
                                                </button>
                                                <GoldBadge
                                                    v-if="provider.required_feature"
                                                    :locked="providerLocked(key)"
                                                    class="shrink-0 max-sm:hidden"
                                                />
                                            </div>
                                            <p
                                                class="truncate text-xs text-slate-600 dark:text-slate-400"
                                                :title="provider.description"
                                            >
                                                {{ provider.description }}
                                            </p>
                                            <!-- On a phone the status has no column of its own and the badge
                                                 would squeeze the name: both sit under it -->
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 sm:hidden">
                                                <StatusPill :tone="statusOf(key).tone">
                                                    {{ statusOf(key).label }}
                                                </StatusPill>
                                                <GoldBadge v-if="provider.required_feature" :locked="providerLocked(key)" />
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 22%">
                                    <StatusPill :tone="statusOf(key).tone">
                                        {{ statusOf(key).label }}
                                    </StatusPill>
                                    <p
                                        v-if="getIntegration(key)?.last_test_status === 'error' && getIntegration(key).last_test_message"
                                        class="mt-1 truncate text-xs text-rose-700 dark:text-rose-300"
                                        :title="getIntegration(key).last_test_message"
                                    >
                                        {{ getIntegration(key).last_test_message }}
                                    </p>
                                </td>

                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 20%">
                                    <p
                                        v-if="getIntegration(key)?.default_model"
                                        class="truncate font-mono text-xs text-slate-900 dark:text-slate-100"
                                        :title="getIntegration(key).default_model"
                                    >
                                        {{ getIntegration(key).default_model }}
                                    </p>
                                    <span v-else class="text-slate-600 dark:text-slate-400">—</span>
                                </td>

                                <td class="px-3 py-3">
                                    <Switch
                                        v-if="isConfigured(key)"
                                        :model-value="!!getIntegration(key).is_active"
                                        :label="$t('ai.switch_label', { name: provider.name })"
                                        :disabled="toggling === getIntegration(key).id"
                                        @update:model-value="toggleActive(getIntegration(key))"
                                    />
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium transition-colors"
                                            :class="
                                                isConfigured(key)
                                                    ? 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white'
                                                    : 'border border-slate-300 text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-700/60'
                                            "
                                            @click="openModal(key)"
                                        >
                                            {{ isConfigured(key) ? $t("ai.actions.edit") : $t("ai.actions.configure") }}
                                        </button>
                                        <ActionMenu
                                            v-if="isConfigured(key)"
                                            :label="$t('ai.actions_for', { name: provider.name })"
                                        >
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="openModal(key)">
                                                {{ $t("ai.actions.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                :disabled="testingIntegration === getIntegration(key).id"
                                                @click="testConnection(getIntegration(key))"
                                            >
                                                {{
                                                    testingIntegration === getIntegration(key).id
                                                        ? $t("ai.testing")
                                                        : $t("ai.actions.test_connection")
                                                }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="confirmDelete(getIntegration(key))">
                                                {{ $t("ai.actions.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- Where the keys come from -->
            <Panel :title="$t('ai.info.title')">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="(provider, key) in providers" :key="key" class="flex min-w-0 flex-col gap-1">
                        <dt class="font-medium text-slate-900 dark:text-white">{{ provider.name }}</dt>
                        <dd class="min-w-0 text-slate-600 dark:text-slate-400">
                            <a
                                v-if="KEY_PAGES[key]"
                                :href="KEY_PAGES[key]"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="break-all font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                {{ hostOf(KEY_PAGES[key]) }}
                            </a>
                            <template v-else-if="key === 'ollama'">
                                {{ $t("ai.info.ollama", { url: "ollama.ai" }) }}
                            </template>
                            <template v-else>—</template>
                        </dd>
                    </div>
                </dl>
            </Panel>
        </div>

        <FormDialog
            :show="showModal"
            :title="
                (modalMode === 'edit' ? $t('ai.actions.edit') : $t('ai.actions.configure')) +
                ' ' +
                (providers[form.provider]?.name || '')
            "
            :processing="form.processing"
            :errors="formErrorMessages"
            :toast="toast"
            @close="closeModal"
            @submit="submitForm"
        >
            <div class="space-y-4">
                <div>
                    <label for="ai-name" :class="labelClass">{{ $t("ai.modal.name_label") }}</label>
                    <input
                        id="ai-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                    <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                </div>

                <!-- API key: required, optional (a local server may have none) or not used at all -->
                <div v-if="providers[form.provider]?.requires_api_key || providers[form.provider]?.supports_optional_api_key">
                    <label for="ai-key" :class="labelClass">
                        {{ $t("ai.modal.api_key_label") }}
                        <span
                            v-if="providers[form.provider]?.supports_optional_api_key && !providers[form.provider]?.requires_api_key"
                            class="font-normal text-slate-600 dark:text-slate-400"
                        >
                            ({{ $t("ai.modal.optional") }})
                        </span>
                        <span v-if="modalMode === 'edit'" class="font-normal text-slate-600 dark:text-slate-400">
                            {{ $t("ai.modal.api_key_help") }}
                        </span>
                    </label>
                    <div class="relative mt-1">
                        <input
                            id="ai-key"
                            v-model="form.api_key"
                            :type="showApiKey ? 'text' : 'password'"
                            :placeholder="modalMode === 'edit' ? '••••••••••••••••' : 'sk-...'"
                            autocomplete="off"
                            class="pr-11 font-mono"
                            :class="[fieldClass, { '!border-rose-500': form.errors.api_key }]"
                        />
                        <button
                            type="button"
                            :aria-label="showApiKey ? $t('common.hide') : $t('common.show')"
                            :aria-pressed="showApiKey"
                            class="absolute right-1 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white"
                            @click="showApiKey = !showApiKey"
                        >
                            <svg
                                aria-hidden="true"
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                viewBox="0 0 24 24"
                            >
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                <circle cx="12" cy="12" r="3" />
                                <path v-if="showApiKey" d="M4 4l16 16" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="form.errors.api_key" :class="errorClass">{{ form.errors.api_key }}</p>
                    <p v-if="providers[form.provider]?.optional_api_key_hint" :class="hintClass">
                        {{ providers[form.provider].optional_api_key_hint }}
                    </p>
                    <p v-if="KEY_PAGES[form.provider]" :class="hintClass">
                        {{ $t("ai.modal.key_from") }}
                        <a
                            :href="KEY_PAGES[form.provider]"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ hostOf(KEY_PAGES[form.provider]) }}
                        </a>
                    </p>
                </div>

                <!-- Server address: a local Ollama, or an endpoint of one's own -->
                <div v-if="providers[form.provider]?.supports_base_url">
                    <label for="ai-url" :class="labelClass">
                        {{ $t("ai.modal.base_url_label") }}
                        <span class="font-normal text-slate-600 dark:text-slate-400">
                            {{ $t("ai.modal.base_url_help") }}
                        </span>
                    </label>
                    <input
                        id="ai-url"
                        v-model="form.base_url"
                        type="url"
                        :placeholder="providers[form.provider]?.default_base_url"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.base_url }]"
                    />
                    <p v-if="form.errors.base_url" :class="errorClass">{{ form.errors.base_url }}</p>
                    <p v-else :class="hintClass">
                        {{ $t("ai.modal.base_url_default", { url: providers[form.provider]?.default_base_url }) }}
                    </p>
                </div>

                <!-- Default model: a list to pick from, narrowed by the search above it -->
                <div>
                    <label for="ai-model" :class="labelClass">{{ $t("ai.modal.default_model_label") }}</label>
                    <input
                        v-model="modelSearch"
                        type="search"
                        :placeholder="$t('ai.modal.model_search_placeholder')"
                        :aria-label="$t('ai.modal.model_search_placeholder')"
                        class="mt-1"
                        :class="fieldClass"
                    />
                    <select
                        id="ai-model"
                        v-model="form.default_model"
                        size="6"
                        class="mt-2 !px-1 !py-1"
                        :class="fieldClass"
                    >
                        <option value="" class="rounded-lg px-2 py-1">
                            {{ $t("ai.modal.select_model_placeholder") }}
                        </option>
                        <option
                            v-for="model in currentProviderModels"
                            :key="model.model_id"
                            :value="model.model_id"
                            class="rounded-lg px-2 py-1"
                        >
                            {{ model.display_name }}
                        </option>
                    </select>
                    <p v-if="form.errors.default_model" :class="errorClass">{{ form.errors.default_model }}</p>
                    <p v-else-if="form.default_model" :class="hintClass">
                        {{ $t("ai.modal.selected_model", { model: form.default_model }) }}
                    </p>
                </div>

                <!-- How long an answer may be, for short and for long jobs -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ai-tokens-small" :class="labelClass">{{ $t("ai.modal.tokens_small_label") }}</label>
                        <input
                            id="ai-tokens-small"
                            v-model.number="form.max_tokens_small"
                            type="number"
                            min="100"
                            max="100000"
                            step="100"
                            class="mt-1"
                            :class="[fieldClass, { '!border-rose-500': form.errors.max_tokens_small }]"
                        />
                        <p :class="hintClass">{{ $t("ai.modal.tokens_small_help", { default: 2000 }) }}</p>
                    </div>
                    <div>
                        <label for="ai-tokens-large" :class="labelClass">{{ $t("ai.modal.tokens_large_label") }}</label>
                        <input
                            id="ai-tokens-large"
                            v-model.number="form.max_tokens_large"
                            type="number"
                            min="1000"
                            max="200000"
                            step="1000"
                            class="mt-1"
                            :class="[fieldClass, { '!border-rose-500': form.errors.max_tokens_large }]"
                        />
                        <p :class="hintClass">{{ $t("ai.modal.tokens_large_help", { default: 50000 }) }}</p>
                    </div>
                </div>
            </div>
        </FormDialog>

        <ConfirmModal
            :show="showDeleteModal"
            type="danger"
            :title="$t('ai.delete.title')"
            :message="$t('ai.delete.confirm', { name: deletingIntegration?.name }) + ' ' + $t('ai.delete.irreversible')"
            :confirm-text="$t('ai.actions.delete')"
            @close="closeDeleteModal"
            @confirm="deleteIntegration"
        />

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="showModal ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
