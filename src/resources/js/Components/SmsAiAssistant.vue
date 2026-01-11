<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import {
    useSpeechRecognition,
    getSpeechLang,
} from "@/Composables/useSpeechRecognition";

const { t, locale } = useI18n();

// Voice dictation
const {
    isListening,
    isSupported,
    transcript,
    interimTranscript,
    toggleListening,
} = useSpeechRecognition();

// Append transcript to prompt when voice recognition completes
watch(transcript, (newTranscript) => {
    if (newTranscript) {
        prompt.value = prompt.value
            ? prompt.value.trim() + " " + newTranscript
            : newTranscript;
    }
});

const emit = defineEmits(["close", "use-content"]);

// AI state
const prompt = ref("");
const tone = ref("casual");
const suggestionCount = ref(1); // 1 or 3
const isGenerating = ref(false);
const suggestions = ref([]); // Array of SMS content strings
const error = ref(null);

// Model selection state
const integrations = ref([]);
const selectedIntegrationId = ref(null);
const selectedModelId = ref(null);
const loadingModels = ref(false);

// Placeholder selection state
const standardPlaceholders = ref([]);
const specialPlaceholders = ref([]);
const customPlaceholders = ref([]);
const selectedCustomPlaceholders = ref([]); // IDs of selected custom placeholders
const showPlaceholderSection = ref(false);
const loadingPlaceholders = ref(false);

// Computed: current integration object
const selectedIntegration = computed(() => {
    return integrations.value.find((i) => i.id === selectedIntegrationId.value);
});

// Computed: available models for selected integration
const availableModels = computed(() => {
    return selectedIntegration.value?.models || [];
});

// SMS character calculation helper
const calculateSmsInfo = (text) => {
    const hasUnicode = /[^\x00-\x7F]/.test(text);
    const charLimit = hasUnicode ? 70 : 160;
    const length = text.length;
    const segments = Math.ceil(length / charLimit) || 1;
    return { length, charLimit, segments, hasUnicode };
};

// Fetch active integrations on mount
onMounted(async () => {
    loadingModels.value = true;
    loadingPlaceholders.value = true;

    try {
        // Fetch AI integrations
        const response = await axios.get(route("api.ai.active-models"));
        integrations.value = response.data.integrations || [];

        // Auto-select first integration and its default model
        if (integrations.value.length > 0) {
            selectedIntegrationId.value = integrations.value[0].id;
            const firstIntegration = integrations.value[0];
            selectedModelId.value =
                firstIntegration.default_model ||
                firstIntegration.models[0]?.id ||
                null;
        }
    } catch (err) {
        console.error("Failed to fetch AI integrations:", err);
    } finally {
        loadingModels.value = false;
    }

    try {
        // Fetch available placeholders
        const placeholderResponse = await axios.get(route("api.placeholders"));
        const data = placeholderResponse.data;

        standardPlaceholders.value = data.standard || [];
        specialPlaceholders.value = data.special || [];
        customPlaceholders.value = data.custom || [];
    } catch (err) {
        console.error("Failed to fetch placeholders:", err);
    } finally {
        loadingPlaceholders.value = false;
    }
});

// Watch integration changes to update model selection
watch(selectedIntegrationId, (newId) => {
    if (newId) {
        const integration = integrations.value.find((i) => i.id === newId);
        if (integration) {
            selectedModelId.value =
                integration.default_model || integration.models[0]?.id || null;
        }
    }
});

// Tone options
const toneOptions = computed(() => [
    { value: "casual", label: t("sms.ai_assistant.tones.casual"), icon: "😊" },
    { value: "formal", label: t("sms.ai_assistant.tones.formal"), icon: "👔" },
    {
        value: "persuasive",
        label: t("sms.ai_assistant.tones.persuasive"),
        icon: "🎯",
    },
]);

// Quick prompts for SMS
const quickPrompts = computed(() => [
    t("sms.ai_assistant.prompts.promo_reminder"),
    t("sms.ai_assistant.prompts.order_confirm"),
    t("sms.ai_assistant.prompts.event_notify"),
    t("sms.ai_assistant.prompts.discount_code"),
]);

const generatedContentRef = ref(null);

// Generate content
const generateContent = async () => {
    if (!prompt.value || isGenerating.value) return;

    isGenerating.value = true;
    suggestions.value = [];
    error.value = null;

    // Build selected placeholders array for API
    const selectedPlaceholders = customPlaceholders.value
        .filter((p) => selectedCustomPlaceholders.value.includes(p.id))
        .map((p) => ({
            name: p.name,
            label: p.label,
            description: p.description || p.label,
        }));

    try {
        const response = await axios.post(
            route("api.templates.ai.sms-content"),
            {
                prompt: prompt.value,
                count: suggestionCount.value,
                tone: tone.value,
                integration_id: selectedIntegrationId.value,
                model_id: selectedModelId.value,
                placeholders: selectedPlaceholders,
            }
        );

        if (response.data.success) {
            suggestions.value = response.data.suggestions || [];

            // Scroll to generated content
            setTimeout(() => {
                generatedContentRef.value?.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }, 100);
        }
    } catch (err) {
        console.error("AI generation failed:", err);
        error.value =
            err.response?.data?.error || t("sms.ai_assistant.error_generating");
    } finally {
        isGenerating.value = false;
    }
};

// Use selected content
const useContent = (content) => {
    emit("use-content", content);
    suggestions.value = [];
    prompt.value = "";
};

// Copy to clipboard
const copyToClipboard = async (content) => {
    try {
        await navigator.clipboard.writeText(content);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};
</script>

<template>
    <!-- Slide-over panel -->
    <div class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div
            class="absolute inset-0 bg-black/30 backdrop-blur-sm"
            @click="$emit('close')"
        ></div>

        <!-- Panel -->
        <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
            <div class="w-screen max-w-xl">
                <div
                    class="flex h-full flex-col overflow-y-auto bg-white shadow-xl dark:bg-slate-900 custom-scrollbar"
                >
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-4 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-3 text-white">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">
                                    {{ $t("sms.ai_assistant.title") }}
                                </h2>
                                <p class="text-sm text-emerald-100">
                                    {{ $t("sms.ai_assistant.subtitle") }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="$emit('close')"
                            class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white"
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

                    <!-- Model Selection -->
                    <div
                        class="border-b border-slate-200 p-4 dark:border-slate-700"
                    >
                        <label
                            class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ $t("sms.ai_assistant.select_model") }}
                        </label>

                        <!-- Loading state -->
                        <div
                            v-if="loadingModels"
                            class="flex items-center gap-2 text-sm text-slate-500"
                        >
                            <svg
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                ></path>
                            </svg>
                            {{ $t("common.loading") }}
                        </div>

                        <!-- No integrations -->
                        <div
                            v-else-if="integrations.length === 0"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300"
                        >
                            <div class="flex items-center gap-2">
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
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                                {{ $t("sms.ai_assistant.no_models_available") }}
                            </div>
                        </div>

                        <!-- Integration & Model selectors -->
                        <div v-else class="space-y-2">
                            <select
                                v-model="selectedIntegrationId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option
                                    v-for="integration in integrations"
                                    :key="integration.id"
                                    :value="integration.id"
                                >
                                    {{ integration.name }}
                                </option>
                            </select>

                            <select
                                v-model="selectedModelId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option
                                    v-for="model in availableModels"
                                    :key="model.id"
                                    :value="model.id"
                                >
                                    {{ model.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                        <!-- Prompt input with voice dictation -->
                        <div class="mb-4">
                            <div class="mb-1 flex items-center justify-between">
                                <label
                                    class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        $t("sms.ai_assistant.describe_content")
                                    }}
                                </label>
                                <!-- Voice dictation button -->
                                <button
                                    v-if="isSupported"
                                    type="button"
                                    @click="
                                        toggleListening(getSpeechLang(locale))
                                    "
                                    :title="
                                        isListening
                                            ? $t('sms.ai_assistant.voice.stop')
                                            : $t('sms.ai_assistant.voice.start')
                                    "
                                    class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium transition-all"
                                    :class="
                                        isListening
                                            ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                            : 'bg-slate-100 text-slate-600 hover:bg-emerald-100 hover:text-emerald-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400'
                                    "
                                >
                                    <svg
                                        class="h-4 w-4"
                                        :class="{
                                            'animate-pulse': isListening,
                                        }"
                                        fill="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"
                                        />
                                        <path
                                            d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"
                                        />
                                    </svg>
                                    <span v-if="isListening">{{
                                        $t("sms.ai_assistant.voice.listening")
                                    }}</span>
                                </button>
                            </div>
                            <div class="relative">
                                <textarea
                                    v-model="prompt"
                                    rows="3"
                                    class="w-full rounded-lg border px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:bg-slate-800 dark:text-white custom-scrollbar"
                                    :class="
                                        isListening
                                            ? 'border-red-300 dark:border-red-700'
                                            : 'border-slate-200 dark:border-slate-700'
                                    "
                                    :placeholder="
                                        $t('sms.ai_assistant.placeholder')
                                    "
                                ></textarea>
                                <!-- Interim transcript indicator -->
                                <div
                                    v-if="interimTranscript"
                                    class="absolute bottom-2 left-3 right-3 rounded bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                >
                                    <span class="animate-pulse">🎤</span>
                                    {{ interimTranscript }}
                                </div>
                            </div>
                        </div>

                        <!-- Quick prompts -->
                        <div class="mb-4">
                            <label
                                class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("sms.ai_assistant.quick_prompts") }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="qp in quickPrompts"
                                    :key="qp"
                                    @click="prompt = qp"
                                    class="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-600 transition-colors hover:bg-emerald-100 hover:text-emerald-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-emerald-900/50"
                                >
                                    {{ qp }}
                                </button>
                            </div>
                        </div>

                        <!-- Tone selector -->
                        <div class="mb-4">
                            <label
                                class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("sms.ai_assistant.select_tone") }}
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="opt in toneOptions"
                                    :key="opt.value"
                                    @click="tone = opt.value"
                                    :class="
                                        tone === opt.value
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                    class="flex flex-col items-center gap-1 rounded-lg border p-3 transition-all"
                                >
                                    <span class="text-xl">{{ opt.icon }}</span>
                                    <span class="text-xs font-medium">{{
                                        opt.label
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Suggestion count selector -->
                        <div class="mb-4">
                            <label
                                class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("sms.ai_assistant.select_count") }}
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    @click="suggestionCount = 1"
                                    :class="
                                        suggestionCount === 1
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                    class="flex items-center justify-center gap-2 rounded-lg border p-3 transition-all"
                                >
                                    <span class="text-lg">1️⃣</span>
                                    <span class="text-sm font-medium">{{
                                        $t("sms.ai_assistant.count_one")
                                    }}</span>
                                </button>
                                <button
                                    @click="suggestionCount = 3"
                                    :class="
                                        suggestionCount === 3
                                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                    class="flex items-center justify-center gap-2 rounded-lg border p-3 transition-all"
                                >
                                    <span class="text-lg">3️⃣</span>
                                    <span class="text-sm font-medium">{{
                                        $t("sms.ai_assistant.count_three")
                                    }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Placeholder selection (collapsible) -->
                        <div class="mb-4">
                            <button
                                @click="
                                    showPlaceholderSection =
                                        !showPlaceholderSection
                                "
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                <span class="flex items-center gap-2">
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
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                        />
                                    </svg>
                                    {{
                                        $t(
                                            "sms.ai_assistant.placeholders.section_title"
                                        )
                                    }}
                                    <span
                                        v-if="
                                            selectedCustomPlaceholders.length >
                                            0
                                        "
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                    >
                                        {{ selectedCustomPlaceholders.length }}
                                    </span>
                                </span>
                                <svg
                                    class="h-4 w-4 transition-transform"
                                    :class="{
                                        'rotate-180': showPlaceholderSection,
                                    }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>

                            <div
                                v-if="showPlaceholderSection"
                                class="mt-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <!-- Standard placeholders (info only) -->
                                <div class="mb-3">
                                    <p
                                        class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t(
                                                "sms.ai_assistant.placeholders.standard_title"
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        [[first_name]], [[last_name]],
                                        [[phone]], [[email]]
                                    </p>
                                </div>

                                <!-- Special placeholders (e.g., gender form) -->
                                <div v-if="specialPlaceholders.length > 0" class="mb-3">
                                    <p
                                        class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t(
                                                "sms.ai_assistant.placeholders.special_title"
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        <span v-for="(p, index) in specialPlaceholders" :key="p.name">
                                            {{ p.placeholder }}<span v-if="index < specialPlaceholders.length - 1">, </span>
                                        </span>
                                    </p>
                                </div>

                                <!-- Custom placeholders (selectable) -->
                                <div v-if="customPlaceholders.length > 0">
                                    <p
                                        class="mb-2 text-xs font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            $t(
                                                "sms.ai_assistant.placeholders.custom_title"
                                            )
                                        }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <label
                                            v-for="p in customPlaceholders"
                                            :key="p.id"
                                            class="flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-all"
                                            :class="
                                                selectedCustomPlaceholders.includes(
                                                    p.id
                                                )
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                            "
                                        >
                                            <input
                                                type="checkbox"
                                                :value="p.id"
                                                v-model="
                                                    selectedCustomPlaceholders
                                                "
                                                class="sr-only"
                                            />
                                            <span>[[{{ p.name }}]]</span>
                                        </label>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="text-xs text-slate-400 dark:text-slate-500"
                                >
                                    {{
                                        $t(
                                            "sms.ai_assistant.placeholders.no_custom"
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Generate button -->
                        <button
                            @click="generateContent"
                            :disabled="
                                !prompt ||
                                isGenerating ||
                                integrations.length === 0
                            "
                            class="mb-4 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 font-medium text-white transition-all hover:from-emerald-600 hover:to-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg
                                v-if="isGenerating"
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
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
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                            {{
                                isGenerating
                                    ? $t("sms.ai_assistant.generating")
                                    : $t("sms.ai_assistant.generate")
                            }}
                        </button>

                        <!-- Error message -->
                        <div
                            v-if="error"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300"
                        >
                            <div class="flex items-center gap-2">
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
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                {{ error }}
                            </div>
                        </div>

                        <!-- Generated suggestions -->
                        <div
                            v-if="suggestions.length > 0"
                            ref="generatedContentRef"
                            class="space-y-3"
                        >
                            <h3
                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("sms.ai_assistant.suggestions") }} ({{
                                    suggestions.length
                                }})
                            </h3>

                            <div
                                v-for="(suggestion, index) in suggestions"
                                :key="index"
                                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <!-- Content -->
                                <p
                                    class="mb-3 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap"
                                >
                                    {{ suggestion }}
                                </p>

                                <!-- Stats -->
                                <div
                                    class="mb-3 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <span
                                        :class="
                                            calculateSmsInfo(suggestion)
                                                .length >
                                            calculateSmsInfo(suggestion)
                                                .charLimit
                                                ? 'text-amber-600 dark:text-amber-400'
                                                : ''
                                        "
                                    >
                                        {{
                                            calculateSmsInfo(suggestion).length
                                        }}
                                        /
                                        {{
                                            calculateSmsInfo(suggestion)
                                                .charLimit
                                        }}
                                        {{ $t("sms.ai_assistant.chars") }}
                                    </span>
                                    <span>•</span>
                                    <span
                                        >{{
                                            calculateSmsInfo(suggestion)
                                                .segments
                                        }}
                                        {{
                                            $t("sms.ai_assistant.segments")
                                        }}</span
                                    >
                                    <span
                                        v-if="
                                            calculateSmsInfo(suggestion)
                                                .hasUnicode
                                        "
                                        class="text-amber-600 dark:text-amber-400"
                                        >• Unicode</span
                                    >
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <button
                                        @click="useContent(suggestion)"
                                        class="flex-1 rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600"
                                    >
                                        {{ $t("sms.ai_assistant.use_this") }}
                                    </button>
                                    <button
                                        @click="copyToClipboard(suggestion)"
                                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
