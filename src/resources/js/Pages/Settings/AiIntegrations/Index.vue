<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import DangerButton from "@/Components/DangerButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();
const page = usePage();

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

// Toast notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

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

// Get status text
const getStatusText = (providerKey) => {
    const integration = getIntegration(providerKey);
    if (!integration)
        return page.props.auth.user
            ? t("ai.status.not_configured")
            : "Nie skonfigurowano";

    if (!isConfigured(providerKey)) return t("ai.status.no_api_key");

    if (!integration.is_active) return t("ai.status.inactive");

    if (integration.last_test_status === "success")
        return t("ai.status.active");
    if (integration.last_test_status === "error")
        return t("ai.status.connection_error");

    return t("ai.status.configured");
};

// Get status color class
const getStatusClass = (providerKey) => {
    const integration = getIntegration(providerKey);
    if (!integration || !isConfigured(providerKey)) {
        return "bg-slate-500/20 text-slate-400";
    }

    if (!integration.is_active) {
        return "bg-slate-500/20 text-slate-400";
    }

    if (integration.last_test_status === "success") {
        return "bg-emerald-500/20 text-emerald-400";
    }
    if (integration.last_test_status === "error") {
        return "bg-rose-500/20 text-rose-400";
    }

    return "bg-amber-500/20 text-amber-400";
};

// Open modal for creating/editing
const openModal = (providerKey) => {
    const integration = getIntegration(providerKey);
    const provider = props.providers[providerKey];

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
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingIntegration.value = null;
    form.reset();
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
                onSuccess: () => closeModal(),
            },
        );
    } else {
        form.post(route("settings.ai-integrations.store"), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
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
        console.log("Testing connection for integration:", integration.id);
        const csrfToken = document.querySelector(
            'meta[name="csrf-token"]',
        )?.content;

        if (!csrfToken) {
            console.error("CSRF token not found");
            showToast(t("common.notifications.error"), false);
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

        console.log("Test response status:", response.status);
        const data = await response.json();
        console.log("Test response data:", data);

        testResult.value = data;
        showToast(data.message, data.success);

        // Refresh the page to update integration status
        router.reload({ only: ["integrations", "integrationsMap"] });
    } catch (error) {
        console.error("Test connection error:", error);
        showToast(
            t("common.notifications.error") + ": " + error.message,
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
            onSuccess: () => closeDeleteModal(),
        },
    );
};

// Toggle active status
const toggleActive = (integration) => {
    router.put(
        route("settings.ai-integrations.update", integration.id),
        {
            is_active: !integration.is_active,
        },
        {
            preserveScroll: true,
        },
    );
};

// Updated default models for December 2025
// Helper to localize model descriptions
const localizeModelName = (name) => {
    if (!name) return name;
    return name
        .replace(
            "(Najnowszy - Styczeń 2026)",
            `(${t("ai.models.latest")} - ${t("ai.models.january_2026")})`,
        )
        .replace(
            "(Nowość - Styczeń 2026)",
            `(${t("ai.models.new")} - ${t("ai.models.january_2026")})`,
        )
        .replace(
            "(Najnowszy - Grudzień 2025)",
            `(${t("ai.models.latest")} - ${t("ai.models.december_2025")})`,
        )
        .replace("(Najnowszy)", `(${t("ai.models.latest")})`)
        .replace("(Nowość)", `(${t("ai.models.new")})`)
        .replace("(Głębokie analizy)", `(${t("ai.models.thinking")})`)
        .replace("(Szybki i tani)", `(${t("ai.models.fast_and_cheap")})`)
        .replace("(Rozumowanie)", `(${t("ai.models.reasoning")})`)
        .replace("(Szybki)", `(${t("ai.models.fast")})`);
};

const defaultModels = {
    openai: [
        {
            model_id: "gpt-5.2-preview",
            display_name: "GPT-5.2 Preview (Nowość - Styczeń 2026)",
        },
        { model_id: "gpt-5-pro", display_name: "GPT-5 Pro (Stable)" },
        { model_id: "gpt-5-mini", display_name: "GPT-5 Mini" },
        { model_id: "o3-full", display_name: "o3 Full (Perfect Reasoning)" },
        { model_id: "o3-mini", display_name: "o3 Mini" },
        { model_id: "o1-pro", display_name: "o1 Pro" },
        { model_id: "gpt-4.5", display_name: "GPT-4.5 (Legacy)" },
    ],
    anthropic: [
        // Claude 4.5 - Latest (January 2026)
        {
            model_id: "claude-sonnet-4-5-20250929",
            display_name: "Claude Sonnet 4.5 (Najnowszy - Styczeń 2026)",
        },
        {
            model_id: "claude-haiku-4-5-20251001",
            display_name: "Claude Haiku 4.5 (Szybki)",
        },
        {
            model_id: "claude-opus-4-5-20251101",
            display_name: "Claude Opus 4.5 (Premium)",
        },
        // Aliases (convenient for testing)
        {
            model_id: "claude-sonnet-4-5",
            display_name: "Claude Sonnet 4.5 (Alias)",
        },
        {
            model_id: "claude-haiku-4-5",
            display_name: "Claude Haiku 4.5 (Alias)",
        },
        {
            model_id: "claude-opus-4-5",
            display_name: "Claude Opus 4.5 (Alias)",
        },
        // Other active snapshots
        {
            model_id: "claude-sonnet-4-20250514",
            display_name: "Claude Sonnet 4",
        },
        { model_id: "claude-opus-4-20250514", display_name: "Claude Opus 4" },
        {
            model_id: "claude-opus-4-1-20250805",
            display_name: "Claude Opus 4.1",
        },
        {
            model_id: "claude-3-haiku-20240307",
            display_name: "Claude 3 Haiku (Legacy)",
        },
    ],
    grok: [
        {
            model_id: "grok-3-ultra",
            display_name: "Grok 3 Ultra (Styczeń 2026)",
        },
        { model_id: "grok-3", display_name: "Grok 3" },
        { model_id: "grok-2", display_name: "Grok 2 (Legacy)" },
    ],
    openrouter: [
        { model_id: "openai/gpt-5.2", display_name: "OpenAI GPT-5.2" },
        { model_id: "openai/o3", display_name: "OpenAI o3" },
        {
            model_id: "anthropic/claude-4.5-opus",
            display_name: "Claude 4.5 Opus",
        },
        { model_id: "google/gemini-2.5-pro", display_name: "Gemini 2.5 Pro" },
        { model_id: "meta-llama/llama-4-405b", display_name: "Llama 4 (Full)" },
        { model_id: "x-ai/grok-3-ultra", display_name: "Grok 3 Ultra" },
        {
            model_id: "mistralai/mistral-large-3",
            display_name: "Mistral Large 3",
        },
        {
            model_id: "moonshotai/kimi-k2.5",
            display_name: "MoonshotAI Kimi K2.5",
        },
    ],
    ollama: [
        { model_id: "llama4.1", display_name: "Llama 4.1 (Latest)" },
        { model_id: "llama4", display_name: "Llama 4" },
        { model_id: "qwen3", display_name: "Qwen 3" },
        { model_id: "mistral4", display_name: "Mistral 4" },
        { model_id: "phi5", display_name: "Phi-5" },
        { model_id: "deepseek-v4", display_name: "DeepSeek V4" },
        {
            model_id: "kimi-k2.5:cloud",
            display_name: "Kimi K2.5 Cloud (Multimodal)",
        },
    ],
    gemini: [
        {
            model_id: "gemini-2.5-pro",
            display_name: "Gemini 2.5 Pro (Styczeń 2026)",
        },
        { model_id: "gemini-2.5-flash", display_name: "Gemini 2.5 Flash" },
        { model_id: "gemini-2.0-pro", display_name: "Gemini 2.0 Pro" },
        { model_id: "gemini-2.0-flash", display_name: "Gemini 2.0 Flash" },
    ],
};

// Get models for current provider with search filter
const currentProviderModels = computed(() => {
    if (!form.provider) return [];

    const integration = getIntegration(form.provider);
    const defaults = defaultModels[form.provider] || [];

    // Always start with default models
    const modelMap = new Map();
    defaults.forEach((m) => modelMap.set(m.model_id, m));

    // Add API models (will not override defaults)
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
                    class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-5 py-4 shadow-lg"
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
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                {{ $t("ai.title") }}
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $t("ai.subtitle") }}
                    </p>
                </div>
            </div>

            <!-- Provider Cards Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(provider, key) in providers"
                    :key="key"
                    class="group relative overflow-hidden rounded-xl border bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    :class="
                        isConfigured(key) && getIntegration(key)?.is_active
                            ? 'border-emerald-200 dark:border-emerald-800'
                            : 'border-slate-200'
                    "
                >
                    <!-- Provider Icon & Name -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl"
                                :style="{
                                    backgroundColor: provider.color + '20',
                                }"
                            >
                                <div
                                    class="h-6 w-6"
                                    :style="{ color: provider.color }"
                                    v-html="providerIcons[key]"
                                ></div>
                            </div>
                            <div>
                                <h3
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ provider.name }}
                                </h3>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ provider.description }}
                                </p>
                            </div>
                        </div>

                        <!-- Active toggle (only if configured) -->
                        <button
                            v-if="isConfigured(key)"
                            @click="toggleActive(getIntegration(key))"
                            class="relative h-6 w-11 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            :class="
                                getIntegration(key)?.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-gray-300 dark:bg-slate-600'
                            "
                            :title="
                                getIntegration(key)?.is_active
                                    ? $t('ai.actions.disable')
                                    : $t('ai.actions.enable')
                            "
                        >
                            <span
                                class="absolute left-0.5 top-0.5 h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                :class="
                                    getIntegration(key)?.is_active
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
                            :class="getStatusClass(key)"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        isConfigured(key) &&
                                        getIntegration(key)?.is_active &&
                                        getIntegration(key)
                                            ?.last_test_status === 'success',
                                    'bg-rose-500':
                                        getIntegration(key)
                                            ?.last_test_status === 'error',
                                    'bg-amber-500':
                                        isConfigured(key) &&
                                        getIntegration(key)?.is_active &&
                                        !getIntegration(key)?.last_test_status,
                                    'bg-slate-400':
                                        !isConfigured(key) ||
                                        !getIntegration(key)?.is_active,
                                }"
                            ></span>
                            {{ getStatusText(key) }}
                        </span>
                    </div>

                    <!-- Default Model (if configured) -->
                    <div v-if="getIntegration(key)?.default_model" class="mt-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Model:
                            <span
                                class="font-medium text-gray-700 dark:text-gray-300"
                                >{{ getIntegration(key).default_model }}</span
                            >
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex items-center gap-2">
                        <button
                            @click="openModal(key)"
                            class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                isConfigured(key)
                                    ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600'
                                    : 'bg-indigo-600 text-white hover:bg-indigo-700'
                            "
                        >
                            {{
                                isConfigured(key)
                                    ? $t("ai.actions.edit")
                                    : $t("ai.actions.configure")
                            }}
                        </button>

                        <template v-if="isConfigured(key)">
                            <button
                                @click="testConnection(getIntegration(key))"
                                :disabled="
                                    testingIntegration ===
                                    getIntegration(key)?.id
                                "
                                class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-gray-200 disabled:opacity-50 dark:bg-slate-700 dark:text-gray-400 dark:hover:bg-slate-600"
                                :title="$t('ai.actions.test_connection')"
                            >
                                <svg
                                    v-if="
                                        testingIntegration ===
                                        getIntegration(key)?.id
                                    "
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
                                @click="confirmDelete(getIntegration(key))"
                                class="rounded-lg bg-gray-100 p-2 text-rose-600 transition-colors hover:bg-rose-50 dark:bg-slate-700 dark:hover:bg-rose-900/20"
                                :title="$t('ai.actions.delete')"
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
                        </template>
                    </div>

                    <!-- Last Test Message (if error) -->
                    <div
                        v-if="getIntegration(key)?.last_test_status === 'error'"
                        class="mt-3"
                    >
                        <p class="text-xs text-rose-600 dark:text-rose-400">
                            {{ getIntegration(key).last_test_message }}
                        </p>
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
                        <p class="font-medium">{{ $t("ai.info.title") }}</p>
                        <ul
                            class="mt-2 list-inside list-disc space-y-1 text-blue-700 dark:text-blue-300"
                        >
                            <li>
                                <strong>OpenAI:</strong>
                                <a
                                    href="https://platform.openai.com/api-keys"
                                    target="_blank"
                                    class="underline hover:no-underline"
                                    >platform.openai.com/api-keys</a
                                >
                            </li>
                            <li>
                                <strong>Anthropic:</strong>
                                <a
                                    href="https://console.anthropic.com/settings/keys"
                                    target="_blank"
                                    class="underline hover:no-underline"
                                    >console.anthropic.com/settings/keys</a
                                >
                            </li>
                            <li>
                                <strong>Grok:</strong>
                                <a
                                    href="https://console.x.ai/"
                                    target="_blank"
                                    class="underline hover:no-underline"
                                    >console.x.ai</a
                                >
                            </li>
                            <li>
                                <strong>OpenRouter:</strong>
                                <a
                                    href="https://openrouter.ai/keys"
                                    target="_blank"
                                    class="underline hover:no-underline"
                                    >openrouter.ai/keys</a
                                >
                            </li>
                            <li>
                                <strong>Gemini:</strong>
                                <a
                                    href="https://aistudio.google.com/apikey"
                                    target="_blank"
                                    class="underline hover:no-underline"
                                    >aistudio.google.com/apikey</a
                                >
                            </li>
                            <li>
                                <strong>Ollama:</strong>
                                {{ $t("ai.info.ollama", { url: "ollama.ai" }) }}
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
                    class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800"
                >
                    <!-- Modal Header -->
                    <div class="mb-6 flex items-center justify-between">
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                modalMode === "edit"
                                    ? $t("ai.actions.edit")
                                    : $t("ai.actions.configure")
                            }}
                            {{ providers[form.provider]?.name }}
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
                        <!-- Name -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("ai.modal.name_label") }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
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

                        <!-- API Key -->
                        <div
                            v-if="
                                providers[form.provider]?.requires_api_key ||
                                providers[form.provider]
                                    ?.supports_optional_api_key
                            "
                        >
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("ai.modal.api_key_label") }}
                                <span
                                    v-if="
                                        providers[form.provider]
                                            ?.supports_optional_api_key &&
                                        !providers[form.provider]
                                            ?.requires_api_key
                                    "
                                    class="font-normal text-gray-500"
                                    >({{ $t("ai.modal.optional") }})</span
                                >
                                <span
                                    v-if="modalMode === 'edit'"
                                    class="font-normal text-gray-500"
                                    >{{ $t("ai.modal.api_key_help") }}</span
                                >
                            </label>
                            <p
                                v-if="
                                    providers[form.provider]
                                        ?.optional_api_key_hint
                                "
                                class="text-xs text-gray-500 mb-1"
                            >
                                {{
                                    providers[form.provider]
                                        .optional_api_key_hint
                                }}
                            </p>
                            <div class="relative mt-1">
                                <input
                                    v-model="form.api_key"
                                    :type="showApiKey ? 'text' : 'password'"
                                    :placeholder="
                                        modalMode === 'edit'
                                            ? '••••••••••••••••'
                                            : 'sk-...'
                                    "
                                    class="block w-full rounded-lg border-gray-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    :class="{
                                        'border-rose-500': form.errors.api_key,
                                    }"
                                />
                                <button
                                    type="button"
                                    @click="showApiKey = !showApiKey"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
                                >
                                    <svg
                                        v-if="showApiKey"
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
                            <p
                                v-if="form.errors.api_key"
                                class="mt-1 text-sm text-rose-600"
                            >
                                {{ form.errors.api_key }}
                            </p>
                        </div>

                        <!-- Base URL (for Ollama or custom endpoints) -->
                        <div v-if="providers[form.provider]?.supports_base_url">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("ai.modal.base_url_label") }}
                                <span class="font-normal text-gray-500">{{
                                    $t("ai.modal.base_url_help")
                                }}</span>
                            </label>
                            <input
                                v-model="form.base_url"
                                type="url"
                                :placeholder="
                                    providers[form.provider]?.default_base_url
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{
                                    $t("ai.modal.base_url_default", {
                                        url: providers[form.provider]
                                            ?.default_base_url,
                                    })
                                }}
                            </p>
                        </div>

                        <!-- Default Model with Search -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("ai.modal.default_model_label") }}
                            </label>

                            <!-- Search input -->
                            <div class="relative mt-1">
                                <svg
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                                <input
                                    v-model="modelSearch"
                                    type="text"
                                    :placeholder="
                                        $t('ai.modal.model_search_placeholder')
                                    "
                                    class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                            </div>

                            <!-- Model select -->
                            <select
                                v-model="form.default_model"
                                class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                size="6"
                            >
                                <option value="">
                                    {{
                                        $t("ai.modal.select_model_placeholder")
                                    }}
                                </option>
                                <option
                                    v-for="model in currentProviderModels"
                                    :key="model.model_id"
                                    :value="model.model_id"
                                >
                                    {{ model.display_name }}
                                </option>
                            </select>

                            <p
                                v-if="form.default_model"
                                class="mt-1 text-xs text-gray-500"
                            >
                                {{
                                    $t("ai.modal.selected_model", {
                                        model: form.default_model,
                                    })
                                }}
                            </p>
                        </div>

                        <!-- Max Tokens Settings -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t("ai.modal.tokens_small_label") }}
                                </label>
                                <input
                                    v-model.number="form.max_tokens_small"
                                    type="number"
                                    min="100"
                                    max="100000"
                                    step="100"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    {{
                                        $t("ai.modal.tokens_small_help", {
                                            default: 2000,
                                        })
                                    }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ $t("ai.modal.tokens_large_label") }}
                                </label>
                                <input
                                    v-model.number="form.max_tokens_large"
                                    type="number"
                                    min="1000"
                                    max="200000"
                                    step="1000"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    {{
                                        $t("ai.modal.tokens_large_help", {
                                            default: 50000,
                                        })
                                    }}
                                </p>
                            </div>
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
                                        ? $t("ai.modal.saving")
                                        : $t("ai.modal.save")
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
                            {{ $t("ai.delete.title") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t("ai.delete.confirm", {
                                    name: deletingIntegration?.name,
                                })
                            }}
                            {{ $t("ai.delete.irreversible") }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <DangerButton @click="deleteIntegration">
                        {{ $t("ai.actions.delete") }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
