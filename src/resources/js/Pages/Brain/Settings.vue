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
});

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
const editingEntryId = ref(null);

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
    } catch (error) {
        // Error
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

                <!-- Entries Table -->
                <div class="overflow-x-auto">
                    <table v-if="entries.length" class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 dark:border-slate-700"
                            >
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
                                    <button
                                        @click="deleteEntry(entry.id)"
                                        class="rounded-lg px-2 py-1 text-xs text-red-500 transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        {{
                                            t("brain.knowledge.delete", "Usuń")
                                        }}
                                    </button>
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
