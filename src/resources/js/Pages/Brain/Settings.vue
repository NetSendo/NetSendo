<script setup>
import { ref, reactive, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    modes: Array,
    telegramConnected: Boolean,
    knowledgeEntries: Array,
    knowledgeCategories: Object,
    aiIntegrations: Array,
    modelRoutingTasks: Object,
    agents: Array,
    agent_modes: Object,
    strategy_settings: Object,
});

// --- Strategy Settings ---
const defaultStrategy = {
    campaign: {
        tone: 'professional',
        max_sends_per_week: 5,
        excluded_days: [],
        preferred_send_hours: { start: 9, end: 17 },
        goal_focus: 'engagement',
        preferred_topics: [],
    },
};

const strategyForm = reactive({
    tone: props.strategy_settings?.campaign?.tone || 'professional',
    max_sends_per_week: props.strategy_settings?.campaign?.max_sends_per_week || 5,
    excluded_days: props.strategy_settings?.campaign?.excluded_days || [],
    preferred_send_hours_start: props.strategy_settings?.campaign?.preferred_send_hours?.start || 9,
    preferred_send_hours_end: props.strategy_settings?.campaign?.preferred_send_hours?.end || 17,
    goal_focus: props.strategy_settings?.campaign?.goal_focus || 'engagement',
    preferred_topics_text: (props.strategy_settings?.campaign?.preferred_topics || []).join(', '),
});

const isSavingStrategy = ref(false);
const strategySaved = ref(false);

const toneOptions = [
    { value: 'professional', label: 'brain.strategy.tone_professional', emoji: '💼' },
    { value: 'casual', label: 'brain.strategy.tone_casual', emoji: '😊' },
    { value: 'friendly', label: 'brain.strategy.tone_friendly', emoji: '🤗' },
    { value: 'formal', label: 'brain.strategy.tone_formal', emoji: '📋' },
    { value: 'humorous', label: 'brain.strategy.tone_humorous', emoji: '😄' },
];

const goalFocusOptions = [
    { value: 'engagement', label: 'brain.strategy.focus_engagement', emoji: '💬' },
    { value: 'conversion', label: 'brain.strategy.focus_conversion', emoji: '💰' },
    { value: 'retention', label: 'brain.strategy.focus_retention', emoji: '🔄' },
    { value: 'growth', label: 'brain.strategy.focus_growth', emoji: '📈' },
];

const dayLabels = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const toggleDay = (day) => {
    const idx = strategyForm.excluded_days.indexOf(day);
    if (idx > -1) {
        strategyForm.excluded_days.splice(idx, 1);
    } else {
        strategyForm.excluded_days.push(day);
    }
};

const saveStrategy = async () => {
    isSavingStrategy.value = true;
    strategySaved.value = false;

    try {
        const topics = strategyForm.preferred_topics_text
            .split(',')
            .map(t => t.trim())
            .filter(t => t.length > 0);

        await axios.put("/brain/api/settings", {
            strategy_settings: {
                campaign: {
                    tone: strategyForm.tone,
                    max_sends_per_week: strategyForm.max_sends_per_week,
                    excluded_days: strategyForm.excluded_days,
                    preferred_send_hours: {
                        start: strategyForm.preferred_send_hours_start,
                        end: strategyForm.preferred_send_hours_end,
                    },
                    goal_focus: strategyForm.goal_focus,
                    preferred_topics: topics,
                },
            },
        });
        strategySaved.value = true;
        setTimeout(() => (strategySaved.value = false), 2000);
    } catch (error) {
        console.error('Strategy save failed', error);
    } finally {
        isSavingStrategy.value = false;
    }
};

// --- Per-Agent Mode ---
const agentList = props.agents || ['campaign', 'list', 'message', 'crm', 'analytics', 'segmentation', 'research'];
const agentModesLocal = reactive({ ...(props.agent_modes || {}) });
const isSavingAgentModes = ref(false);
const agentModesSaved = ref(false);

const agentIcons = {
    campaign: '📧', list: '📋', message: '✉️', crm: '🤝',
    analytics: '📊', segmentation: '🎯', research: '🔍',
};

const saveAgentModes = async () => {
    isSavingAgentModes.value = true;
    agentModesSaved.value = false;

    try {
        await axios.put("/brain/api/settings", { agent_modes: agentModesLocal });
        agentModesSaved.value = true;
        setTimeout(() => (agentModesSaved.value = false), 2000);
    } catch (error) {
        console.error('Agent modes save failed', error);
    } finally {
        isSavingAgentModes.value = false;
    }
};

// --- Mode ---
const currentMode = ref(props.settings?.work_mode || "semi_auto");
const isSavingMode = ref(false);
const modeSaved = ref(false);

const saveMode = async (mode) => {
    currentMode.value = mode;
    isSavingMode.value = true;
    modeSaved.value = false;

    try {
        await axios.put("/brain/api/settings", { work_mode: mode });
        modeSaved.value = true;
        setTimeout(() => (modeSaved.value = false), 2000);
    } catch (error) {
        // Revert
        currentMode.value = props.settings?.work_mode || "semi_auto";
    } finally {
        isSavingMode.value = false;
    }
};

// --- Language ---
const languageOptions = [
    { value: "auto", label: "brain.language.auto" },
    { value: "en", label: "English" },
    { value: "pl", label: "Polski" },
    { value: "de", label: "Deutsch" },
    { value: "es", label: "Español" },
    { value: "custom", label: "brain.language.custom" },
];
const selectedLanguage = ref(props.settings?.preferred_language || "auto");
const customLanguage = ref("");
const isSavingLanguage = ref(false);
const languageSaved = ref(false);

// If the stored value is not one of the standard options, treat it as custom
const isCustomLanguage = computed(() => {
    const standardCodes = ["auto", "en", "pl", "de", "es"];
    return !standardCodes.includes(selectedLanguage.value);
});

// Initialize custom field if needed
if (isCustomLanguage.value) {
    customLanguage.value = selectedLanguage.value;
    selectedLanguage.value = "custom";
}

const onLanguageChange = () => {
    if (selectedLanguage.value !== "custom") {
        customLanguage.value = "";
    }
};

const saveLanguage = async () => {
    isSavingLanguage.value = true;
    languageSaved.value = false;
    const langValue =
        selectedLanguage.value === "custom"
            ? customLanguage.value.trim()
            : selectedLanguage.value;
    if (!langValue) {
        isSavingLanguage.value = false;
        return;
    }
    try {
        await axios.put("/brain/api/settings", {
            preferred_language: langValue,
        });
        languageSaved.value = true;
        setTimeout(() => (languageSaved.value = false), 2000);
    } catch (error) {
        // Error
    } finally {
        isSavingLanguage.value = false;
    }
};

// --- AI Model Selector ---
const selectedIntegrationId = ref(
    props.settings?.preferred_integration_id || null,
);
const selectedModel = ref(props.settings?.preferred_model || "");
const isSavingModel = ref(false);
const modelSaved = ref(false);

const selectedIntegration = computed(() => {
    if (!selectedIntegrationId.value) return null;
    return (props.aiIntegrations || []).find(
        (i) => i.id === selectedIntegrationId.value,
    );
});

const availableModels = computed(() => {
    const integ = selectedIntegration.value;
    if (!integ) return [];
    return integ.models || [];
});

const onIntegrationChange = () => {
    const integ = selectedIntegration.value;
    if (integ && integ.default_model) {
        selectedModel.value = integ.default_model;
    } else if (availableModels.value.length > 0) {
        selectedModel.value = availableModels.value[0].model_id;
    } else {
        selectedModel.value = "";
    }
};

const saveModelPreference = async () => {
    isSavingModel.value = true;
    modelSaved.value = false;
    try {
        await axios.put("/brain/api/settings", {
            preferred_integration_id: selectedIntegrationId.value,
            preferred_model: selectedModel.value || null,
        });
        modelSaved.value = true;
        setTimeout(() => (modelSaved.value = false), 2000);
    } catch (error) {
        // Error
    } finally {
        isSavingModel.value = false;
    }
};

// --- Per-Task Model Routing ---
const modelRouting = ref({ ...(props.settings?.model_routing || {}) });
const isSavingRouting = ref(false);
const routingSaved = ref(false);
const showRoutingAdvanced = ref(false);

const getRoutingIntegration = (taskKey) => {
    return modelRouting.value[taskKey]?.integration_id || null;
};

const getRoutingModel = (taskKey) => {
    return modelRouting.value[taskKey]?.model || "";
};

const getModelsForIntegration = (integrationId) => {
    if (!integrationId) return [];
    const integ = (props.aiIntegrations || []).find(
        (i) => i.id === integrationId,
    );
    return integ?.models || [];
};

const updateRoutingIntegration = (taskKey, integrationId) => {
    if (!modelRouting.value[taskKey]) {
        modelRouting.value[taskKey] = {};
    }
    modelRouting.value[taskKey].integration_id = integrationId || null;
    // Auto-select default model for this integration
    if (integrationId) {
        const integ = (props.aiIntegrations || []).find(
            (i) => i.id === integrationId,
        );
        if (integ?.default_model) {
            modelRouting.value[taskKey].model = integ.default_model;
        }
    } else {
        modelRouting.value[taskKey].model = null;
    }
};

const updateRoutingModel = (taskKey, model) => {
    if (!modelRouting.value[taskKey]) {
        modelRouting.value[taskKey] = {};
    }
    modelRouting.value[taskKey].model = model || null;
};

const saveModelRouting = async () => {
    isSavingRouting.value = true;
    routingSaved.value = false;
    try {
        // Clean empty entries
        const cleaned = {};
        for (const [key, val] of Object.entries(modelRouting.value)) {
            if (val?.integration_id || val?.model) {
                cleaned[key] = val;
            }
        }
        await axios.put("/brain/api/settings", {
            model_routing: cleaned,
        });
        routingSaved.value = true;
        setTimeout(() => (routingSaved.value = false), 2000);
    } catch (error) {
        // Error
    } finally {
        isSavingRouting.value = false;
    }
};

// --- Telegram ---
const isTelegramConnected = ref(props.telegramConnected || false);
const telegramLinkCode = ref(null);
const isGeneratingCode = ref(false);
const isTesting = ref(false);
const telegramTestResult = ref(null);

// --- Telegram Bot Token ---
const botToken = ref("");
const isSavingToken = ref(false);
const tokenSaved = ref(false);
const webhookStatus = ref(null);
const isSettingWebhook = ref(false);
const existingTokenMask = ref(
    props.settings?.telegram_bot_token
        ? "••••••••" + props.settings.telegram_bot_token.slice(-4)
        : "",
);

const saveBotToken = async () => {
    if (!botToken.value.trim()) return;
    isSavingToken.value = true;
    tokenSaved.value = false;
    webhookStatus.value = null;
    try {
        const response = await axios.put("/brain/api/settings", {
            telegram_bot_token: botToken.value,
        });
        existingTokenMask.value = "••••••••" + botToken.value.slice(-4);
        botToken.value = "";
        tokenSaved.value = true;

        // Show webhook registration result
        if (response.data?.webhook_setup_result) {
            webhookStatus.value =
                response.data.webhook_setup_result === "success"
                    ? {
                          success: true,
                          message: t(
                              "brain.telegram.webhook_success",
                              "Webhook registered successfully",
                          ),
                      }
                    : {
                          success: false,
                          message:
                              t("brain.telegram.webhook_failed", "Webhook: ") +
                              response.data.webhook_setup_result,
                      };
        }

        setTimeout(() => (tokenSaved.value = false), 3000);
    } catch (error) {
        // Error
    } finally {
        isSavingToken.value = false;
    }
};

const setupWebhook = async () => {
    isSettingWebhook.value = true;
    webhookStatus.value = null;
    try {
        const webhookUrl = window.location.origin + "/api/telegram/webhook";
        const response = await axios.post("/brain/api/telegram/set-webhook", {
            url: webhookUrl,
        });
        if (response.data?.ok) {
            webhookStatus.value = {
                success: true,
                message: t(
                    "brain.telegram.webhook_success",
                    "Webhook registered successfully",
                ),
            };
        } else {
            webhookStatus.value = {
                success: false,
                message:
                    response.data?.description ||
                    t(
                        "brain.telegram.webhook_failed_generic",
                        "Failed to set up webhook",
                    ),
            };
        }
    } catch (error) {
        webhookStatus.value = {
            success: false,
            message:
                error.response?.data?.error ||
                error.response?.data?.description ||
                t("brain.telegram.webhook_error", "Error setting up webhook"),
        };
    } finally {
        isSettingWebhook.value = false;
    }
};

const generateLinkCode = async () => {
    isGeneratingCode.value = true;
    try {
        const response = await axios.post("/brain/api/telegram/link-code");
        telegramLinkCode.value = response.data.code;
    } catch (error) {
        // Error
    } finally {
        isGeneratingCode.value = false;
    }
};

const testTelegram = async () => {
    isTesting.value = true;
    telegramTestResult.value = null;
    try {
        const response = await axios.post("/brain/api/telegram/test");
        telegramTestResult.value = response.data;
    } catch (error) {
        telegramTestResult.value = {
            success: false,
            error: error.response?.data?.error || "Connection test failed",
        };
    } finally {
        isTesting.value = false;
    }
};

const disconnectTelegram = async () => {
    try {
        await axios.post("/brain/api/telegram/disconnect");
        isTelegramConnected.value = false;
    } catch (error) {
        // Error
    }
};

// --- Internet Research APIs ---
const perplexityApiKey = ref("");
const serpApiKey = ref("");
const isSavingPerplexity = ref(false);
const isSavingSerpApi = ref(false);
const perplexitySaved = ref(false);
const serpApiSaved = ref(false);
const perplexityTestResult = ref(null);
const serpApiTestResult = ref(null);
const isTestingPerplexity = ref(false);
const isTestingSerpApi = ref(false);

const existingPerplexityMask = ref(
    props.settings?.perplexity_api_key
        ? "••••••••" + props.settings.perplexity_api_key.slice(-4)
        : "",
);

const existingSerpApiMask = ref(
    props.settings?.serpapi_api_key
        ? "••••••••" + props.settings.serpapi_api_key.slice(-4)
        : "",
);

const savePerplexityKey = async () => {
    if (!perplexityApiKey.value.trim()) return;
    isSavingPerplexity.value = true;
    perplexitySaved.value = false;
    try {
        await axios.put("/brain/api/settings", {
            perplexity_api_key: perplexityApiKey.value,
        });
        existingPerplexityMask.value =
            "••••••••" + perplexityApiKey.value.slice(-4);
        perplexityApiKey.value = "";
        perplexitySaved.value = true;
        setTimeout(() => (perplexitySaved.value = false), 3000);
    } catch (error) {
        // Error
    } finally {
        isSavingPerplexity.value = false;
    }
};

const saveSerpApiKey = async () => {
    if (!serpApiKey.value.trim()) return;
    isSavingSerpApi.value = true;
    serpApiSaved.value = false;
    try {
        await axios.put("/brain/api/settings", {
            serpapi_api_key: serpApiKey.value,
        });
        existingSerpApiMask.value = "••••••••" + serpApiKey.value.slice(-4);
        serpApiKey.value = "";
        serpApiSaved.value = true;
        setTimeout(() => (serpApiSaved.value = false), 3000);
    } catch (error) {
        // Error
    } finally {
        isSavingSerpApi.value = false;
    }
};

const testPerplexityApi = async () => {
    const key = perplexityApiKey.value || props.settings?.perplexity_api_key;
    if (!key) return;
    isTestingPerplexity.value = true;
    perplexityTestResult.value = null;
    try {
        const response = await axios.post("/brain/api/research/test", {
            provider: "perplexity",
            api_key: key,
        });
        perplexityTestResult.value = response.data;
    } catch (error) {
        perplexityTestResult.value = {
            success: false,
            message: error.response?.data?.message || "Connection test failed",
        };
    } finally {
        isTestingPerplexity.value = false;
    }
};

const testSerpApi = async () => {
    const key = serpApiKey.value || props.settings?.serpapi_api_key;
    if (!key) return;
    isTestingSerpApi.value = true;
    serpApiTestResult.value = null;
    try {
        const response = await axios.post("/brain/api/research/test", {
            provider: "serpapi",
            api_key: key,
        });
        serpApiTestResult.value = response.data;
    } catch (error) {
        serpApiTestResult.value = {
            success: false,
            message: error.response?.data?.message || "Connection test failed",
        };
    } finally {
        isTestingSerpApi.value = false;
    }
};

// --- Knowledge Base ---
const entries = ref(props.knowledgeEntries || []);
const showAddForm = ref(false);
const newEntry = reactive({
    category: "general",
    title: "",
    content: "",
});
const CONTENT_MAX_LENGTH = 10000;
const TITLE_MAX_LENGTH = 255;
const isSavingEntry = ref(false);

// --- Bulk Selection ---
const selectedIds = ref(new Set());
const isDeletingBulk = ref(false);

const isAllSelected = computed(() => {
    return entries.value.length > 0 && selectedIds.value.size === entries.value.length;
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(entries.value.map((e) => e.id));
    }
};

const toggleSelection = (id) => {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
};
const editingEntryId = ref(null);

// --- View / Edit Knowledge Entry ---
const viewingEntry = ref(null);
const editingEntry = reactive({
    id: null,
    category: "",
    title: "",
    content: "",
});
const isSavingEdit = ref(false);

const viewEntry = (entry) => {
    viewingEntry.value = entry;
};

const closeView = () => {
    viewingEntry.value = null;
};

const startEdit = (entry) => {
    editingEntry.id = entry.id;
    editingEntry.category = entry.category;
    editingEntry.title = entry.title;
    editingEntry.content = entry.content;
    viewingEntry.value = null; // close view modal if open
};

const cancelEdit = () => {
    editingEntry.id = null;
    editingEntry.category = "";
    editingEntry.title = "";
    editingEntry.content = "";
};

const saveEdit = async () => {
    if (!editingEntry.title.trim() || !editingEntry.content.trim()) return;
    isSavingEdit.value = true;
    try {
        const response = await axios.put(
            `/brain/api/knowledge/${editingEntry.id}`,
            {
                category: editingEntry.category,
                title: editingEntry.title,
                content: editingEntry.content,
            },
        );
        const idx = entries.value.findIndex((e) => e.id === editingEntry.id);
        if (idx !== -1) entries.value[idx] = response.data;
        cancelEdit();
    } catch (error) {
        // Error
    } finally {
        isSavingEdit.value = false;
    }
};

const addEntry = async () => {
    if (!newEntry.title.trim() || !newEntry.content.trim()) return;
    isSavingEntry.value = true;

    try {
        const response = await axios.post("/brain/api/knowledge", {
            category: newEntry.category,
            title: newEntry.title,
            content: newEntry.content,
        });
        entries.value.unshift(response.data);
        newEntry.category = "general";
        newEntry.title = "";
        newEntry.content = "";
        showAddForm.value = false;
    } catch (error) {
        // Error
    } finally {
        isSavingEntry.value = false;
    }
};

const deleteEntry = async (id) => {
    try {
        await axios.delete(`/brain/api/knowledge/${id}`);
        entries.value = entries.value.filter((e) => e.id !== id);
        selectedIds.value.delete(id);
    } catch (error) {
        // Error
    }
};

const bulkDeleteEntries = async () => {
    if (selectedIds.value.size === 0) return;
    const ids = Array.from(selectedIds.value);
    if (!confirm(t('brain.knowledge.bulk_delete_confirm', `Czy na pewno chcesz usunąć ${ids.length} wpisów? Tej operacji nie można cofnąć.`))) return;

    isDeletingBulk.value = true;
    try {
        await axios.post('/brain/api/knowledge/bulk-delete', { ids });
        entries.value = entries.value.filter((e) => !selectedIds.value.has(e.id));
        selectedIds.value = new Set();
    } catch (error) {
        // Error
    } finally {
        isDeletingBulk.value = false;
    }
};

const toggleEntryActive = async (entry) => {
    try {
        const response = await axios.put(`/brain/api/knowledge/${entry.id}`, {
            is_active: !entry.is_active,
        });
        const idx = entries.value.findIndex((e) => e.id === entry.id);
        if (idx !== -1) entries.value[idx] = response.data;
    } catch (error) {
        // Error
    }
};

const getCategoryLabel = (key) => {
    return props.knowledgeCategories?.[key] || key;
};

const getCategoryColor = (key) => {
    const colors = {
        general:
            "bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300",
        brand: "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400",
        product:
            "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
        audience:
            "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        campaign:
            "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
        competitor:
            "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
    };
    return colors[key] || colors.general;
};
</script>

<template>
    <Head :title="t('brain.settings_page_title', 'Brain — Ustawienia')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg shadow-cyan-500/25"
                    >
                        <svg
                            class="h-5 w-5 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{
                                t(
                                    "brain.settings_page_title",
                                    "Brain — Ustawienia",
                                )
                            }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "brain.settings_subtitle",
                                    "Konfiguracja trybu pracy, integracji i bazy wiedzy",
                                )
                            }}
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('brain.index')"
                    class="inline-flex items-center gap-2 rounded-lg bg-white/10 border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    ← {{ t("brain.back_to_chat", "Wróć do chatu") }}
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Work Mode Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🎛️ {{ t("brain.mode.title", "Tryb pracy") }}
                    <span
                        v-if="modeSaved"
                        class="text-xs font-normal text-green-500"
                        >✓ {{ t("brain.saved", "Zapisano") }}</span
                    >
                </h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <button
                        v-for="mode in modes"
                        :key="mode.value"
                        @click="saveMode(mode.value)"
                        class="relative rounded-xl border-2 p-4 text-left transition-all"
                        :class="{
                            'border-cyan-500 bg-cyan-50 shadow-lg shadow-cyan-500/10 dark:border-cyan-500 dark:bg-cyan-900/20':
                                currentMode === mode.value,
                            'border-slate-200 hover:border-slate-300 dark:border-slate-600 dark:hover:border-slate-500':
                                currentMode !== mode.value,
                        }"
                        :disabled="isSavingMode"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-lg"
                                :class="{
                                    'bg-green-100 dark:bg-green-900/30':
                                        mode.value === 'autonomous',
                                    'bg-amber-100 dark:bg-amber-900/30':
                                        mode.value === 'semi_auto',
                                    'bg-slate-100 dark:bg-slate-700':
                                        mode.value === 'manual',
                                }"
                            >
                                {{
                                    mode.value === "autonomous"
                                        ? "🚀"
                                        : mode.value === "semi_auto"
                                          ? "🤝"
                                          : "💡"
                                }}
                            </span>
                            <span
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                                >{{ t(mode.label) }}</span
                            >
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ t(mode.description) }}
                        </p>
                        <span
                            v-if="currentMode === mode.value"
                            class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-cyan-500 text-white"
                        >
                            <svg
                                class="h-3 w-3"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="3"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Per-Agent Mode Grid -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-2 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🧩 {{ t("brain.agent_modes.title", "Tryby agentów") }}
                    <span
                        v-if="agentModesSaved"
                        class="text-xs font-normal text-green-500"
                        >✓ {{ t("brain.saved", "Zapisano") }}</span
                    >
                </h3>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    {{ t("brain.agent_modes.description", "Ustaw indywidualny tryb autonomii dla każdego agenta. Dzięki temu np. analizy mogą działać w pełni automatycznie, a kampanie wymagać zatwierdzenia.") }}
                </p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <div
                        v-for="agent in agentList"
                        :key="agent"
                        class="rounded-lg border border-slate-200 p-3 dark:border-slate-600"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <span class="text-lg">{{ agentIcons[agent] || '🤖' }}</span>
                            <span class="text-sm font-medium capitalize text-slate-900 dark:text-white">{{ agent }}</span>
                        </div>
                        <select
                            v-model="agentModesLocal[agent]"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        >
                            <option value="autonomous">🚀 {{ t("brain.mode.autonomous", "Autonomiczny") }}</option>
                            <option value="semi_auto">🤝 {{ t("brain.mode.semi_auto", "Pół-auto") }}</option>
                            <option value="manual">💡 {{ t("brain.mode.manual", "Ręczny") }}</option>
                        </select>
                    </div>
                </div>
                <button
                    @click="saveAgentModes"
                    :disabled="isSavingAgentModes"
                    class="mt-4 rounded-lg bg-cyan-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-cyan-700 disabled:opacity-50"
                >
                    {{ isSavingAgentModes ? '...' : t("brain.save_agent_modes", "Zapisz tryby agentów") }}
                </button>
            </div>

            <!-- Campaign Strategy Settings -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-2 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🎯 {{ t("brain.strategy.title", "Strategia kampanii") }}
                    <span
                        v-if="strategySaved"
                        class="text-xs font-normal text-green-500"
                        >✓ {{ t("brain.saved", "Zapisano") }}</span
                    >
                </h3>
                <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">
                    {{ t("brain.strategy.description", "Zdefiniuj ograniczenia i preferencje, którymi Brain będzie się kierował przy planowaniu i realizacji kampanii.") }}
                </p>

                <div class="space-y-5">
                    <!-- Tone -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("brain.strategy.tone_label", "Ton komunikacji") }}
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in toneOptions"
                                :key="opt.value"
                                @click="strategyForm.tone = opt.value"
                                class="rounded-lg border-2 px-3 py-1.5 text-sm transition"
                                :class="{
                                    'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20': strategyForm.tone === opt.value,
                                    'border-slate-200 dark:border-slate-600': strategyForm.tone !== opt.value,
                                }"
                            >
                                {{ opt.emoji }} {{ t(opt.label, opt.value) }}
                            </button>
                        </div>
                    </div>

                    <!-- Max Sends Per Week -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("brain.strategy.max_sends_label", "Maks. kampanii na tydzień") }}: <strong>{{ strategyForm.max_sends_per_week }}</strong>
                        </label>
                        <input
                            type="range"
                            v-model.number="strategyForm.max_sends_per_week"
                            min="1"
                            max="20"
                            class="w-full accent-cyan-500"
                        />
                        <div class="mt-1 flex justify-between text-xs text-slate-400">
                            <span>1</span>
                            <span>5</span>
                            <span>10</span>
                            <span>15</span>
                            <span>20</span>
                        </div>
                    </div>

                    <!-- Excluded Days -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("brain.strategy.excluded_days_label", "Wykluczone dni (nie wysyłaj)") }}
                        </label>
                        <div class="flex gap-2">
                            <button
                                v-for="day in dayLabels"
                                :key="day.value"
                                @click="toggleDay(day.value)"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border-2 text-xs font-medium transition"
                                :class="{
                                    'border-red-400 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400':
                                        strategyForm.excluded_days.includes(day.value),
                                    'border-slate-200 text-slate-600 dark:border-slate-600 dark:text-slate-400':
                                        !strategyForm.excluded_days.includes(day.value),
                                }"
                            >
                                {{ day.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Send Hours -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("brain.strategy.send_from", "Od godziny") }}
                            </label>
                            <input
                                type="number"
                                v-model.number="strategyForm.preferred_send_hours_start"
                                min="0"
                                max="23"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("brain.strategy.send_to", "Do godziny") }}
                            </label>
                            <input
                                type="number"
                                v-model.number="strategyForm.preferred_send_hours_end"
                                min="0"
                                max="23"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <!-- Goal Focus -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("brain.strategy.goal_focus_label", "Główny cel") }}
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in goalFocusOptions"
                                :key="opt.value"
                                @click="strategyForm.goal_focus = opt.value"
                                class="rounded-lg border-2 px-3 py-1.5 text-sm transition"
                                :class="{
                                    'border-cyan-500 bg-cyan-50 dark:bg-cyan-900/20': strategyForm.goal_focus === opt.value,
                                    'border-slate-200 dark:border-slate-600': strategyForm.goal_focus !== opt.value,
                                }"
                            >
                                {{ opt.emoji }} {{ t(opt.label, opt.value) }}
                            </button>
                        </div>
                    </div>

                    <!-- Preferred Topics -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("brain.strategy.topics_label", "Preferowane tematy (oddziel przecinkami)") }}
                        </label>
                        <input
                            type="text"
                            v-model="strategyForm.preferred_topics_text"
                            :placeholder="t('brain.strategy.topics_placeholder', 'np. nowości produktowe, porady branżowe, case studies')"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                </div>

                <button
                    @click="saveStrategy"
                    :disabled="isSavingStrategy"
                    class="mt-5 rounded-lg bg-cyan-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-cyan-700 disabled:opacity-50"
                >
                    {{ isSavingStrategy ? '...' : t("brain.save_strategy", "Zapisz strategię") }}
                </button>
            </div>

            <!-- Response Language Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🌐 {{ t("brain.language.title", "Język odpowiedzi") }}
                    <span
                        v-if="languageSaved"
                        class="text-xs font-normal text-green-500"
                        >✓ {{ t("brain.saved", "Zapisano") }}</span
                    >
                </h3>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        t(
                            "brain.language.description",
                            "Wybierz język, w którym Brain będzie odpowiadać. Auto używa języka interfejsu.",
                        )
                    }}
                </p>
                <div class="space-y-4">
                    <div>
                        <select
                            v-model="selectedLanguage"
                            @change="onLanguageChange"
                            class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        >
                            <option
                                v-for="lang in languageOptions"
                                :key="lang.value"
                                :value="lang.value"
                            >
                                {{
                                    lang.value === "auto" ||
                                    lang.value === "custom"
                                        ? t(lang.label, lang.label)
                                        : lang.label
                                }}
                            </option>
                        </select>
                    </div>
                    <div v-if="selectedLanguage === 'custom'">
                        <input
                            v-model="customLanguage"
                            type="text"
                            :placeholder="
                                t(
                                    'brain.language.custom_placeholder',
                                    'np. Chinese, Japanese, Arabic...',
                                )
                            "
                            class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-500"
                        />
                    </div>
                    <button
                        @click="saveLanguage"
                        :disabled="
                            isSavingLanguage ||
                            (selectedLanguage === 'custom' &&
                                !customLanguage.trim())
                        "
                        class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            isSavingLanguage
                                ? t("brain.saving", "Zapisywanie...")
                                : t("brain.language.save", "Zapisz język")
                        }}
                    </button>
                </div>
            </div>

            <!-- AI Model Selector Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🤖 {{ t("brain.model.title", "Model AI") }}
                    <span
                        v-if="modelSaved"
                        class="text-xs font-normal text-green-500"
                        >✓ {{ t("brain.saved", "Zapisano") }}</span
                    >
                </h3>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        t(
                            "brain.model.description",
                            "Wybierz dostawcę i model AI, który będzie używany przez Brain do prowadzenia konwersacji.",
                        )
                    }}
                </p>
                <div class="space-y-4">
                    <!-- Integration selector -->
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ t("brain.model.provider", "Dostawca AI") }}
                        </label>
                        <select
                            v-model="selectedIntegrationId"
                            @change="onIntegrationChange"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 transition-colors focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        >
                            <option :value="null">
                                {{
                                    t(
                                        "brain.model.default_provider",
                                        "— Domyślny dostawca —",
                                    )
                                }}
                            </option>
                            <option
                                v-for="integ in aiIntegrations"
                                :key="integ.id"
                                :value="integ.id"
                            >
                                {{ integ.name || integ.provider }} ({{
                                    integ.provider
                                }})
                            </option>
                        </select>
                    </div>

                    <!-- Model selector -->
                    <div v-if="selectedIntegrationId">
                        <label
                            class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ t("brain.model.model", "Model") }}
                        </label>
                        <select
                            v-model="selectedModel"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 transition-colors focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        >
                            <option value="">
                                {{
                                    t(
                                        "brain.model.default_model",
                                        "— Domyślny model dostawcy —",
                                    )
                                }}
                            </option>
                            <option
                                v-for="model in availableModels"
                                :key="model.model_id"
                                :value="model.model_id"
                            >
                                {{ model.display_name || model.model_id }}
                            </option>
                        </select>
                    </div>

                    <!-- Save button -->
                    <button
                        @click="saveModelPreference"
                        :disabled="isSavingModel"
                        class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30 disabled:opacity-50"
                    >
                        {{
                            isSavingModel
                                ? t("brain.saving", "Zapisywanie...")
                                : t("brain.model.save_model", "Zapisz model")
                        }}
                    </button>
                </div>
            </div>

            <!-- Per-Task Model Routing Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        ⚙️
                        {{
                            t(
                                "brain.routing.title",
                                "Zaawansowane ustawienia modeli",
                            )
                        }}
                        <span
                            v-if="routingSaved"
                            class="text-xs font-normal text-green-500"
                            >✓ {{ t("brain.saved", "Zapisano") }}</span
                        >
                    </h3>
                    <button
                        @click="showRoutingAdvanced = !showRoutingAdvanced"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    >
                        {{
                            showRoutingAdvanced
                                ? t("brain.routing.hide", "Ukryj")
                                : t("brain.routing.show", "Pokaż")
                        }}
                    </button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    {{
                        t(
                            "brain.routing.description",
                            "Przypisz różne modele AI do różnych zadań Brain. Puste pola używają domyślnego modelu.",
                        )
                    }}
                </p>

                <div v-if="showRoutingAdvanced" class="space-y-4">
                    <div
                        v-for="(label, taskKey) in modelRoutingTasks"
                        :key="taskKey"
                        class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-700/30"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <span
                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >{{ t(label) }}</span
                            >
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <select
                                :value="getRoutingIntegration(taskKey)"
                                @change="
                                    updateRoutingIntegration(
                                        taskKey,
                                        $event.target.value
                                            ? Number($event.target.value)
                                            : null,
                                    )
                                "
                                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 transition-colors focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option :value="null">
                                    {{
                                        t(
                                            "brain.routing.default_provider",
                                            "— Default —",
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="integ in aiIntegrations"
                                    :key="integ.id"
                                    :value="integ.id"
                                >
                                    {{ integ.name || integ.provider }}
                                </option>
                            </select>
                            <select
                                v-if="getRoutingIntegration(taskKey)"
                                :value="getRoutingModel(taskKey)"
                                @change="
                                    updateRoutingModel(
                                        taskKey,
                                        $event.target.value,
                                    )
                                "
                                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 transition-colors focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option value="">
                                    {{
                                        t(
                                            "brain.routing.default_model",
                                            "— Default model —",
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="model in getModelsForIntegration(
                                        getRoutingIntegration(taskKey),
                                    )"
                                    :key="model.model_id"
                                    :value="model.model_id"
                                >
                                    {{ model.display_name || model.model_id }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <button
                        @click="saveModelRouting"
                        :disabled="isSavingRouting"
                        class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30 disabled:opacity-50"
                    >
                        {{
                            isSavingRouting
                                ? t("brain.saving", "Zapisywanie...")
                                : t(
                                      "brain.routing.save",
                                      "Zapisz routing modeli",
                                  )
                        }}
                    </button>
                </div>
            </div>

            <!-- Telegram Integration Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    📱
                    {{ t("brain.telegram.title", "Integracja z Telegramem") }}
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                isTelegramConnected,
                            'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400':
                                !isTelegramConnected,
                        }"
                    >
                        {{
                            isTelegramConnected
                                ? t("brain.telegram.connected", "Połączony")
                                : t(
                                      "brain.telegram.disconnected",
                                      "Niepołączony",
                                  )
                        }}
                    </span>
                </h3>

                <!-- Bot Token Input -->
                <div
                    class="mb-4 rounded-lg border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-700/30"
                >
                    <label
                        class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        🤖
                        {{
                            t(
                                "brain.telegram.bot_token_label",
                                "Token bota Telegram",
                            )
                        }}
                        <span
                            v-if="tokenSaved"
                            class="ml-2 text-xs font-normal text-green-500"
                            >✓
                            {{
                                t("brain.telegram.bot_token_saved", "Zapisano")
                            }}</span
                        >
                    </label>
                    <p
                        v-if="existingTokenMask"
                        class="mb-2 text-xs text-slate-400 dark:text-slate-500"
                    >
                        {{
                            t(
                                "brain.telegram.bot_token_current",
                                "Aktualny token:",
                            )
                        }}
                        <code
                            class="rounded bg-slate-200 px-1 py-0.5 font-mono dark:bg-slate-600"
                            >{{ existingTokenMask }}</code
                        >
                    </p>
                    <div class="flex gap-2">
                        <input
                            v-model="botToken"
                            type="password"
                            class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            :placeholder="
                                t(
                                    'brain.telegram.bot_token_placeholder',
                                    'Wklej token z @BotFather...',
                                )
                            "
                        />
                        <button
                            @click="saveBotToken"
                            :disabled="isSavingToken || !botToken.trim()"
                            class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-cyan-600 disabled:opacity-50"
                        >
                            {{
                                isSavingToken
                                    ? t(
                                          "brain.telegram.bot_token_saving",
                                          "Zapisywanie...",
                                      )
                                    : t(
                                          "brain.telegram.bot_token_save",
                                          "Zapisz",
                                      )
                            }}
                        </button>
                    </div>
                    <p
                        class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"
                    >
                        {{
                            t(
                                "brain.telegram.bot_token_help",
                                "Uzyskaj token od @BotFather na Telegramie. Wymagany do integracji z Brain.",
                            )
                        }}
                    </p>

                    <!-- Webhook Status -->
                    <div
                        v-if="webhookStatus"
                        class="mt-2 rounded-lg px-3 py-2 text-xs"
                        :class="{
                            'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400':
                                webhookStatus.success,
                            'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400':
                                !webhookStatus.success,
                        }"
                    >
                        {{ webhookStatus.success ? "✅" : "⚠️" }}
                        {{ webhookStatus.message }}
                    </div>

                    <!-- Manual Webhook Setup -->
                    <div v-if="existingTokenMask" class="mt-2">
                        <button
                            @click="setupWebhook"
                            :disabled="isSettingWebhook"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700 disabled:opacity-50"
                        >
                            {{
                                isSettingWebhook
                                    ? t(
                                          "brain.telegram.webhook_setting",
                                          "Setting webhook...",
                                      )
                                    : t(
                                          "brain.telegram.webhook_setup",
                                          "🔗 Setup Webhook",
                                      )
                            }}
                        </button>
                    </div>
                </div>

                <div v-if="isTelegramConnected" class="space-y-4">
                    <div class="flex items-center gap-3">
                        <button
                            @click="testTelegram"
                            :disabled="isTesting"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{
                                isTesting
                                    ? t(
                                          "brain.telegram.testing",
                                          "Testowanie...",
                                      )
                                    : t(
                                          "brain.telegram.test",
                                          "Testuj połączenie",
                                      )
                            }}
                        </button>
                        <button
                            @click="disconnectTelegram"
                            class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            {{ t("brain.telegram.disconnect", "Rozłącz") }}
                        </button>
                    </div>
                    <div
                        v-if="telegramTestResult"
                        class="rounded-lg p-3 text-sm"
                        :class="{
                            'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400':
                                telegramTestResult.success,
                            'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400':
                                !telegramTestResult.success,
                        }"
                    >
                        <template v-if="telegramTestResult.success">
                            ✓ Bot:
                            <strong>{{ telegramTestResult.bot?.name }}</strong>
                            (@{{ telegramTestResult.bot?.username }})
                        </template>
                        <template v-else>
                            ✕ {{ telegramTestResult.error }}
                        </template>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            t(
                                "brain.telegram.connect_description",
                                "Połącz konto Telegram, aby rozmawiać z Brain bezpośrednio przez Telegram.",
                            )
                        }}
                    </p>
                    <button
                        @click="generateLinkCode"
                        :disabled="isGeneratingCode"
                        class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl"
                    >
                        {{
                            isGeneratingCode
                                ? t(
                                      "brain.telegram.generating",
                                      "Generowanie...",
                                  )
                                : t(
                                      "brain.telegram.generate_code",
                                      "Wygeneruj kod połączenia",
                                  )
                        }}
                    </button>
                    <div
                        v-if="telegramLinkCode"
                        class="rounded-lg bg-slate-50 p-4 dark:bg-slate-700/50"
                    >
                        <p
                            class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{
                                t(
                                    "brain.telegram.send_code",
                                    "Wyślij do bota Telegram:",
                                )
                            }}
                        </p>
                        <code
                            class="rounded-lg bg-slate-200 px-3 py-2 text-sm font-mono text-cyan-600 dark:bg-slate-600 dark:text-cyan-400"
                        >
                            /connect {{ telegramLinkCode }}
                        </code>
                    </div>
                </div>
            </div>

            <!-- Internet Research Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 text-lg font-semibold text-slate-800 dark:text-white"
                >
                    🌐 {{ t("brain.research.title", "Internet Research") }}
                </h3>
                <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        t(
                            "brain.research.description",
                            "Give Brain access to real-time internet data. Research competitors, market trends, and enrich CRM with live company information.",
                        )
                    }}
                </p>

                <!-- Perplexity API -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <label
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Perplexity AI API Key
                        </label>
                        <span
                            v-if="existingPerplexityMask"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        >
                            ✅ {{ t("brain.research.connected", "Connected") }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                        >
                            {{
                                t(
                                    "brain.research.not_configured",
                                    "Not configured",
                                )
                            }}
                        </span>
                    </div>

                    <p
                        v-if="existingPerplexityMask"
                        class="mb-2 text-xs text-slate-400 dark:text-slate-500"
                    >
                        {{ t("brain.research.current_key", "Current key:") }}
                        {{ existingPerplexityMask }}
                    </p>

                    <div class="flex gap-2">
                        <input
                            v-model="perplexityApiKey"
                            type="password"
                            :placeholder="
                                t(
                                    'brain.research.perplexity_placeholder',
                                    'pplx-...',
                                )
                            "
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                        <button
                            @click="savePerplexityKey"
                            :disabled="
                                isSavingPerplexity || !perplexityApiKey.trim()
                            "
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-violet-700 disabled:opacity-50"
                        >
                            {{
                                isSavingPerplexity
                                    ? "..."
                                    : t("brain.research.save", "Save")
                            }}
                        </button>
                        <button
                            @click="testPerplexityApi"
                            :disabled="
                                isTestingPerplexity ||
                                (!existingPerplexityMask &&
                                    !perplexityApiKey.trim())
                            "
                            class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-medium text-violet-600 transition hover:bg-violet-50 disabled:opacity-50 dark:border-violet-600 dark:text-violet-400 dark:hover:bg-violet-900/20"
                        >
                            {{
                                isTestingPerplexity
                                    ? "..."
                                    : t("brain.research.test", "Test")
                            }}
                        </button>
                    </div>

                    <p
                        v-if="perplexitySaved"
                        class="mt-2 text-xs text-emerald-500"
                    >
                        ✅ {{ t("brain.research.key_saved", "API Key saved") }}
                    </p>

                    <div
                        v-if="perplexityTestResult"
                        class="mt-2 rounded-lg px-3 py-2 text-xs"
                        :class="
                            perplexityTestResult.success
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                        "
                    >
                        {{ perplexityTestResult.success ? "✅" : "❌" }}
                        {{ perplexityTestResult.message }}
                    </div>

                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                        {{
                            t(
                                "brain.research.perplexity_help",
                                "Deep research with AI-powered analysis and cited sources.",
                            )
                        }}
                        <a
                            href="https://www.perplexity.ai/settings/api"
                            target="_blank"
                            class="text-violet-500 hover:underline"
                            >{{
                                t("brain.research.get_key", "Get API key")
                            }}
                            →</a
                        >
                    </p>
                </div>

                <!-- SerpAPI -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            SerpAPI Key
                        </label>
                        <span
                            v-if="existingSerpApiMask"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400"
                        >
                            ✅ {{ t("brain.research.connected", "Connected") }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                        >
                            {{
                                t(
                                    "brain.research.not_configured",
                                    "Not configured",
                                )
                            }}
                        </span>
                    </div>

                    <p
                        v-if="existingSerpApiMask"
                        class="mb-2 text-xs text-slate-400 dark:text-slate-500"
                    >
                        {{ t("brain.research.current_key", "Current key:") }}
                        {{ existingSerpApiMask }}
                    </p>

                    <div class="flex gap-2">
                        <input
                            v-model="serpApiKey"
                            type="password"
                            :placeholder="
                                t(
                                    'brain.research.serpapi_placeholder',
                                    'Enter SerpAPI key...',
                                )
                            "
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                        <button
                            @click="saveSerpApiKey"
                            :disabled="isSavingSerpApi || !serpApiKey.trim()"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700 disabled:opacity-50"
                        >
                            {{
                                isSavingSerpApi
                                    ? "..."
                                    : t("brain.research.save", "Save")
                            }}
                        </button>
                        <button
                            @click="testSerpApi"
                            :disabled="
                                isTestingSerpApi ||
                                (!existingSerpApiMask && !serpApiKey.trim())
                            "
                            class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-600 transition hover:bg-amber-50 disabled:opacity-50 dark:border-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20"
                        >
                            {{
                                isTestingSerpApi
                                    ? "..."
                                    : t("brain.research.test", "Test")
                            }}
                        </button>
                    </div>

                    <p
                        v-if="serpApiSaved"
                        class="mt-2 text-xs text-emerald-500"
                    >
                        ✅ {{ t("brain.research.key_saved", "API Key saved") }}
                    </p>

                    <div
                        v-if="serpApiTestResult"
                        class="mt-2 rounded-lg px-3 py-2 text-xs"
                        :class="
                            serpApiTestResult.success
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
                        "
                    >
                        {{ serpApiTestResult.success ? "✅" : "❌" }}
                        {{ serpApiTestResult.message }}
                    </div>

                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                        {{
                            t(
                                "brain.research.serpapi_help",
                                "Google Search results for company lookup, news, and trends.",
                            )
                        }}
                        <a
                            href="https://serpapi.com/manage-api-key"
                            target="_blank"
                            class="text-amber-500 hover:underline"
                            >{{
                                t("brain.research.get_key", "Get API key")
                            }}
                            →</a
                        >
                    </p>
                </div>
            </div>

            <!-- Knowledge Base Card -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3
                        class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        📚
                        {{ t("brain.knowledge.title", "Baza wiedzy") }}
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                        >
                            {{ entries.length }}
                        </span>
                    </h3>
                    <button
                        @click="showAddForm = !showAddForm"
                        class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-3 py-2 text-xs font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl"
                    >
                        {{
                            showAddForm
                                ? t("brain.knowledge.cancel", "Anuluj")
                                : t("brain.knowledge.add", "➕ Dodaj wpis")
                        }}
                    </button>
                </div>

                <!-- Add Entry Form -->
                <div
                    v-if="showAddForm"
                    class="mb-4 rounded-xl border border-cyan-200 bg-cyan-50/50 p-4 dark:border-cyan-800/50 dark:bg-cyan-900/10"
                >
                    <div class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                    >{{
                                        t(
                                            "brain.knowledge.category",
                                            "Kategoria",
                                        )
                                    }}</label
                                >
                                <select
                                    v-model="newEntry.category"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                >
                                    <option
                                        v-for="(
                                            label, key
                                        ) in knowledgeCategories"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                    >{{
                                        t(
                                            "brain.knowledge.entry_title",
                                            "Tytuł",
                                        )
                                    }}</label
                                >
                                <input
                                    v-model="newEntry.title"
                                    type="text"
                                    :maxlength="TITLE_MAX_LENGTH"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    :placeholder="
                                        t(
                                            'brain.knowledge.title_placeholder',
                                            'np. Nasza misja',
                                        )
                                    "
                                />
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                >{{
                                    t("brain.knowledge.content", "Treść")
                                }}</label
                            >
                            <textarea
                                v-model="newEntry.content"
                                rows="3"
                                :maxlength="CONTENT_MAX_LENGTH"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :placeholder="
                                    t(
                                        'brain.knowledge.content_placeholder',
                                        'Opisz informację, którą Brain powinien znać...',
                                    )
                                "
                            ></textarea>
                            <div class="mt-1 flex items-center justify-between">
                                <span
                                    class="text-xs text-slate-400 dark:text-slate-500"
                                >
                                    {{
                                        t(
                                            "brain.knowledge.content_help",
                                            "Informacje, które Brain wykorzysta w rozmowach i przy wykonywaniu zadań.",
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-xs font-medium tabular-nums transition-colors"
                                    :class="{
                                        'text-slate-400 dark:text-slate-500':
                                            newEntry.content.length <
                                            CONTENT_MAX_LENGTH * 0.8,
                                        'text-amber-500 dark:text-amber-400':
                                            newEntry.content.length >=
                                                CONTENT_MAX_LENGTH * 0.8 &&
                                            newEntry.content.length <
                                                CONTENT_MAX_LENGTH * 0.95,
                                        'text-red-500 dark:text-red-400':
                                            newEntry.content.length >=
                                            CONTENT_MAX_LENGTH * 0.95,
                                    }"
                                >
                                    {{
                                        newEntry.content.length.toLocaleString()
                                    }}
                                    / {{ CONTENT_MAX_LENGTH.toLocaleString() }}
                                </span>
                            </div>
                        </div>
                        <button
                            @click="addEntry"
                            :disabled="
                                isSavingEntry ||
                                !newEntry.title.trim() ||
                                !newEntry.content.trim()
                            "
                            class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-cyan-600 disabled:opacity-50"
                        >
                            {{
                                isSavingEntry
                                    ? t(
                                          "brain.knowledge.saving",
                                          "Zapisywanie...",
                                      )
                                    : t("brain.knowledge.save", "Zapisz")
                            }}
                        </button>
                    </div>
                </div>

                <!-- Bulk Action Toolbar -->
                <div
                    v-if="selectedIds.size > 0"
                    class="mb-3 flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-red-50/50 px-4 py-3 dark:border-red-800/50 dark:bg-red-900/10"
                >
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                        {{ t('brain.knowledge.selected_count', `Zaznaczono: ${selectedIds.size}`) }}
                    </span>
                    <button
                        @click="toggleSelectAll"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                    >
                        {{ isAllSelected
                            ? t('brain.knowledge.deselect_all', 'Odznacz wszystkie')
                            : t('brain.knowledge.select_all', 'Zaznacz wszystkie')
                        }}
                    </button>
                    <button
                        @click="bulkDeleteEntries"
                        :disabled="isDeletingBulk"
                        class="rounded-lg bg-red-500 px-3 py-1.5 text-xs font-medium text-white shadow-lg shadow-red-500/25 transition-all hover:bg-red-600 hover:shadow-xl disabled:opacity-50"
                    >
                        {{ isDeletingBulk
                            ? t('brain.knowledge.deleting', 'Usuwanie...')
                            : t('brain.knowledge.delete_selected', `🗑️ Usuń zaznaczone (${selectedIds.size})`)
                        }}
                    </button>
                </div>

                <!-- Entries Table -->
                <div class="overflow-x-auto">
                    <table v-if="entries.length" class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 dark:border-slate-700"
                            >
                                <th class="pb-2 pr-2 text-left">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        @change="toggleSelectAll"
                                        class="h-4 w-4 rounded border-slate-300 text-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700"
                                    />
                                </th>
                                <th
                                    class="pb-2 text-left text-xs font-medium uppercase tracking-wider text-slate-400"
                                >
                                    {{
                                        t("brain.knowledge.col_title", "Tytuł")
                                    }}
                                </th>
                                <th
                                    class="pb-2 text-left text-xs font-medium uppercase tracking-wider text-slate-400"
                                >
                                    {{
                                        t(
                                            "brain.knowledge.col_category",
                                            "Kategoria",
                                        )
                                    }}
                                </th>
                                <th
                                    class="pb-2 text-center text-xs font-medium uppercase tracking-wider text-slate-400"
                                >
                                    {{
                                        t(
                                            "brain.knowledge.col_active",
                                            "Aktywny",
                                        )
                                    }}
                                </th>
                                <th
                                    class="pb-2 text-right text-xs font-medium uppercase tracking-wider text-slate-400"
                                >
                                    {{
                                        t(
                                            "brain.knowledge.col_actions",
                                            "Akcje",
                                        )
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in entries"
                                :key="entry.id"
                                class="border-b border-slate-100 dark:border-slate-700/50"
                            >
                                <td class="py-3 pr-2">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.has(entry.id)"
                                        @change="toggleSelection(entry.id)"
                                        class="h-4 w-4 rounded border-slate-300 text-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-700"
                                    />
                                </td>
                                <td class="py-3 pr-4">
                                    <p
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ entry.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 truncate text-xs text-slate-400 max-w-xs"
                                    >
                                        {{ entry.content?.substring(0, 80)
                                        }}{{
                                            entry.content?.length > 80
                                                ? "..."
                                                : ""
                                        }}
                                    </p>
                                </td>
                                <td class="py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getCategoryColor(entry.category)
                                        "
                                    >
                                        {{ getCategoryLabel(entry.category) }}
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <button
                                        @click="toggleEntryActive(entry)"
                                        class="text-lg transition-colors"
                                        :class="{
                                            'text-green-500': entry.is_active,
                                            'text-slate-300 dark:text-slate-600':
                                                !entry.is_active,
                                        }"
                                    >
                                        {{ entry.is_active ? "●" : "○" }}
                                    </button>
                                </td>
                                <td class="py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <button
                                            @click="viewEntry(entry)"
                                            class="rounded-lg px-2 py-1 text-xs text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
                                            :title="
                                                t(
                                                    'brain.knowledge.view',
                                                    'Podgląd',
                                                )
                                            "
                                        >
                                            👁
                                        </button>
                                        <button
                                            @click="startEdit(entry)"
                                            class="rounded-lg px-2 py-1 text-xs text-cyan-500 transition-colors hover:bg-cyan-50 dark:hover:bg-cyan-900/20"
                                            :title="
                                                t(
                                                    'brain.knowledge.edit',
                                                    'Edytuj',
                                                )
                                            "
                                        >
                                            ✏️
                                        </button>
                                        <button
                                            @click="deleteEntry(entry.id)"
                                            class="rounded-lg px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                        >
                                            {{
                                                t(
                                                    "brain.knowledge.delete",
                                                    "Usuń",
                                                )
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-8 text-center text-sm text-slate-400">
                        {{
                            t(
                                "brain.knowledge.empty",
                                "Brak wpisów w bazie wiedzy. Dodaj informacje, które Brain powinien znać.",
                            )
                        }}
                    </p>
                </div>

                <!-- View Entry Modal -->
                <Teleport to="body">
                    <div
                        v-if="viewingEntry"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    >
                        <div
                            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                            @click="closeView"
                        ></div>
                        <div
                            class="relative w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800"
                        >
                            <!-- Header -->
                            <div class="mb-4 flex items-start justify-between">
                                <div class="flex-1 pr-4">
                                    <h3
                                        class="text-lg font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ viewingEntry.title }}
                                    </h3>
                                    <span
                                        class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            getCategoryColor(
                                                viewingEntry.category,
                                            )
                                        "
                                    >
                                        {{
                                            getCategoryLabel(
                                                viewingEntry.category,
                                            )
                                        }}
                                    </span>
                                </div>
                                <button
                                    @click="closeView"
                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Content -->
                            <div
                                class="mb-4 rounded-lg border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-700/30"
                            >
                                <p
                                    class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-300"
                                >
                                    {{ viewingEntry.content }}
                                </p>
                            </div>

                            <!-- Metadata -->
                            <div
                                class="grid grid-cols-2 gap-3 rounded-lg border border-slate-100 bg-slate-50/30 p-3 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-700/20 dark:text-slate-400 sm:grid-cols-4"
                            >
                                <div>
                                    <span
                                        class="block font-medium text-slate-400 dark:text-slate-500"
                                    >
                                        {{
                                            t(
                                                "brain.knowledge.meta_source",
                                                "Źródło",
                                            )
                                        }}
                                    </span>
                                    {{ viewingEntry.source || "—" }}
                                </div>
                                <div>
                                    <span
                                        class="block font-medium text-slate-400 dark:text-slate-500"
                                    >
                                        {{
                                            t(
                                                "brain.knowledge.meta_confidence",
                                                "Pewność",
                                            )
                                        }}
                                    </span>
                                    {{
                                        viewingEntry.confidence != null
                                            ? Math.round(
                                                  viewingEntry.confidence * 100,
                                              ) + "%"
                                            : "—"
                                    }}
                                </div>
                                <div>
                                    <span
                                        class="block font-medium text-slate-400 dark:text-slate-500"
                                    >
                                        {{
                                            t(
                                                "brain.knowledge.meta_usage",
                                                "Użycia",
                                            )
                                        }}
                                    </span>
                                    {{ viewingEntry.usage_count || 0 }}
                                </div>
                                <div>
                                    <span
                                        class="block font-medium text-slate-400 dark:text-slate-500"
                                    >
                                        {{
                                            t(
                                                "brain.knowledge.meta_status",
                                                "Status",
                                            )
                                        }}
                                    </span>
                                    <span
                                        :class="
                                            viewingEntry.is_active
                                                ? 'text-green-500'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            viewingEntry.is_active
                                                ? t(
                                                      "brain.knowledge.active",
                                                      "Aktywny",
                                                  )
                                                : t(
                                                      "brain.knowledge.inactive",
                                                      "Nieaktywny",
                                                  )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    @click="startEdit(viewingEntry)"
                                    class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30"
                                >
                                    ✏️ {{ t("brain.knowledge.edit", "Edytuj") }}
                                </button>
                                <button
                                    @click="closeView"
                                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                                >
                                    {{ t("brain.knowledge.close", "Zamknij") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>

                <!-- Edit Entry Modal -->
                <Teleport to="body">
                    <div
                        v-if="editingEntry.id"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    >
                        <div
                            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                            @click="cancelEdit"
                        ></div>
                        <div
                            class="relative w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800"
                        >
                            <!-- Header -->
                            <div class="mb-4 flex items-center justify-between">
                                <h3
                                    class="text-lg font-bold text-slate-900 dark:text-white"
                                >
                                    ✏️
                                    {{
                                        t(
                                            "brain.knowledge.edit_title",
                                            "Edytuj wpis",
                                        )
                                    }}
                                </h3>
                                <button
                                    @click="cancelEdit"
                                    class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Edit Form -->
                            <div class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    "brain.knowledge.category",
                                                    "Kategoria",
                                                )
                                            }}
                                        </label>
                                        <select
                                            v-model="editingEntry.category"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        >
                                            <option
                                                v-for="(
                                                    label, key
                                                ) in knowledgeCategories"
                                                :key="key"
                                                :value="key"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                        >
                                            {{
                                                t(
                                                    "brain.knowledge.entry_title",
                                                    "Tytuł",
                                                )
                                            }}
                                        </label>
                                        <input
                                            v-model="editingEntry.title"
                                            type="text"
                                            :maxlength="TITLE_MAX_LENGTH"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                "brain.knowledge.content",
                                                "Treść",
                                            )
                                        }}
                                    </label>
                                    <textarea
                                        v-model="editingEntry.content"
                                        rows="8"
                                        :maxlength="CONTENT_MAX_LENGTH"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    ></textarea>
                                    <div class="mt-1 flex justify-end">
                                        <span
                                            class="text-xs font-medium tabular-nums transition-colors"
                                            :class="{
                                                'text-slate-400 dark:text-slate-500':
                                                    editingEntry.content
                                                        .length <
                                                    CONTENT_MAX_LENGTH * 0.8,
                                                'text-amber-500 dark:text-amber-400':
                                                    editingEntry.content
                                                        .length >=
                                                        CONTENT_MAX_LENGTH *
                                                            0.8 &&
                                                    editingEntry.content
                                                        .length <
                                                        CONTENT_MAX_LENGTH *
                                                            0.95,
                                                'text-red-500 dark:text-red-400':
                                                    editingEntry.content
                                                        .length >=
                                                    CONTENT_MAX_LENGTH * 0.95,
                                            }"
                                        >
                                            {{
                                                editingEntry.content.length.toLocaleString()
                                            }}
                                            /
                                            {{
                                                CONTENT_MAX_LENGTH.toLocaleString()
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    @click="cancelEdit"
                                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-700"
                                >
                                    {{ t("brain.knowledge.cancel", "Anuluj") }}
                                </button>
                                <button
                                    @click="saveEdit"
                                    :disabled="
                                        isSavingEdit ||
                                        !editingEntry.title.trim() ||
                                        !editingEntry.content.trim()
                                    "
                                    class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30 disabled:opacity-50"
                                >
                                    {{
                                        isSavingEdit
                                            ? t(
                                                  "brain.knowledge.saving",
                                                  "Zapisywanie...",
                                              )
                                            : t(
                                                  "brain.knowledge.save_changes",
                                                  "Zapisz zmiany",
                                              )
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
