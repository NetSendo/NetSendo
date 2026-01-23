<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { useI18n } from "vue-i18n";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import SubjectAiAssistant from "@/Components/SubjectAiAssistant.vue";

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            enabled: false,
            test_type: "subject",
            winning_metric: "open_rate",
            sample_percentage: 20,
            test_duration_hours: 4,
            auto_select_winner: true,
            confidence_threshold: 95,
            variants: [],
        }),
    },
    originalSubject: {
        type: String,
        default: "",
    },
    originalPreheader: {
        type: String,
        default: "",
    },
    messageContent: {
        type: String,
        default: "",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

// Local state with internal update flag to prevent loops
const localConfig = ref({ ...props.modelValue });
const isInternalUpdate = ref(false);

// Watch for external changes (from parent)
watch(
    () => props.modelValue,
    (newVal) => {
        if (!isInternalUpdate.value) {
            localConfig.value = { ...newVal };
        }
    },
    { deep: true },
);

// Watch for internal changes and emit
watch(
    localConfig,
    (newVal) => {
        isInternalUpdate.value = true;
        emit("update:modelValue", { ...newVal });
        nextTick(() => {
            isInternalUpdate.value = false;
        });
    },
    { deep: true },
);

// Ensure we always have at least variant A
const ensureVariantA = () => {
    if (
        !localConfig.value.variants ||
        localConfig.value.variants.length === 0
    ) {
        localConfig.value.variants = [
            {
                variant_letter: "A",
                subject: props.originalSubject,
                preheader: props.originalPreheader,
                is_control: true,
            },
        ];
    }
};

// Toggle enabled
const toggleEnabled = () => {
    localConfig.value.enabled = !localConfig.value.enabled;
    if (localConfig.value.enabled) {
        ensureVariantA();
        // Add variant B if not exists
        if (localConfig.value.variants.length < 2) {
            addVariant();
        }
    }
};

// Add new variant
const addVariant = () => {
    const letters = ["A", "B", "C", "D", "E"];
    const usedLetters = localConfig.value.variants.map((v) => v.variant_letter);
    const nextLetter = letters.find((l) => !usedLetters.includes(l));

    if (!nextLetter) return;

    localConfig.value.variants.push({
        variant_letter: nextLetter,
        subject: "",
        preheader: "",
        is_control: false,
    });
};

// Remove variant
const removeVariant = (index) => {
    if (localConfig.value.variants.length <= 2) return;
    localConfig.value.variants.splice(index, 1);
};

// Update variant
const updateVariant = (index, field, value) => {
    localConfig.value.variants[index][field] = value;
};

// Computed
const canAddVariant = computed(() => localConfig.value.variants.length < 5);
const hasMinimumVariants = computed(
    () => localConfig.value.variants.length >= 2,
);

// AI generation state per variant
const generatingVariantAI = ref({});

// Handle AI selection for a variant
const handleVariantAISelect = (variantIndex, { subject, preheader }) => {
    updateVariant(variantIndex, "subject", subject);
    updateVariant(variantIndex, "preheader", preheader);
};

// Get control variant data for AI context
const controlVariant = computed(() => {
    return (
        localConfig.value.variants.find((v) => v.is_control) || {
            subject: "",
            preheader: "",
        }
    );
});

// Check if control variant has empty data
const controlVariantEmpty = computed(() => {
    return !props.originalSubject && !props.originalPreheader;
});

// Options
const testTypeOptions = [
    { value: "subject", label: t("ab_tests.test_types.subject") },
    { value: "content", label: t("ab_tests.test_types.content") },
    { value: "sender", label: t("ab_tests.test_types.sender") },
    { value: "send_time", label: t("ab_tests.test_types.send_time") },
    { value: "full", label: t("ab_tests.test_types.full") },
];

const winningMetricOptions = [
    { value: "open_rate", label: t("ab_tests.metrics.open_rate"), icon: "📬" },
    {
        value: "click_rate",
        label: t("ab_tests.metrics.click_rate"),
        icon: "🔗",
    },
    {
        value: "conversion_rate",
        label: t("ab_tests.metrics.conversion_rate"),
        icon: "💰",
    },
];

const durationOptions = [
    { value: 1, label: "1 " + t("common.hour") },
    { value: 2, label: "2 " + t("common.hours") },
    { value: 4, label: "4 " + t("common.hours") },
    { value: 8, label: "8 " + t("common.hours") },
    { value: 12, label: "12 " + t("common.hours") },
    { value: 24, label: "24 " + t("common.hours") },
    { value: 48, label: "48 " + t("common.hours") },
    { value: 72, label: "72 " + t("common.hours") },
];

// Initialize
ensureVariantA();
</script>

<template>
    <div class="space-y-6">
        <!-- Enable Toggle -->
        <div
            class="rounded-xl border p-4 transition-all"
            :class="
                localConfig.enabled
                    ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-500 dark:bg-indigo-900/20'
                    : 'border-slate-200 dark:border-slate-700'
            "
        >
            <label class="flex cursor-pointer items-center gap-4">
                <input
                    type="checkbox"
                    :checked="localConfig.enabled"
                    @change="toggleEnabled"
                    :disabled="disabled"
                    class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
                <div>
                    <span
                        class="text-base font-semibold text-slate-900 dark:text-white"
                    >
                        {{ $t("ab_tests.enable_testing") }}
                    </span>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("ab_tests.enable_description") }}
                    </p>
                </div>
            </label>
        </div>

        <!-- A/B Test Configuration (shown when enabled) -->
        <div v-if="localConfig.enabled" class="space-y-6">
            <!-- Variants Section -->
            <div
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h4
                        class="text-base font-semibold text-slate-900 dark:text-white"
                    >
                        🧪 {{ $t("ab_tests.variants.title") }}
                    </h4>
                    <button
                        v-if="canAddVariant"
                        type="button"
                        @click="addVariant"
                        class="flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-1.5 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t("ab_tests.variants.add") }}
                    </button>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="(variant, index) in localConfig.variants"
                        :key="variant.variant_letter"
                        class="rounded-lg border p-4 transition-all"
                        :class="
                            variant.is_control
                                ? 'border-emerald-300 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-900/10'
                                : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800'
                        "
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                                    :class="
                                        variant.is_control
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                            : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    "
                                >
                                    {{ variant.variant_letter }}
                                </span>
                                <span
                                    class="font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ $t("ab_tests.variants.variant") }}
                                    {{ variant.variant_letter }}
                                </span>
                                <span
                                    v-if="variant.is_control"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                >
                                    {{ $t("ab_tests.variants.control") }}
                                </span>
                            </div>
                            <button
                                v-if="
                                    !variant.is_control &&
                                    localConfig.variants.length > 2
                                "
                                type="button"
                                @click="removeVariant(index)"
                                class="rounded p-1 text-slate-400 transition-colors hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30"
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
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-3">
                            <!-- Control Variant: Read-only with info -->
                            <template v-if="variant.is_control">
                                <div>
                                    <InputLabel
                                        :value="$t('ab_tests.variants.subject')"
                                        class="mb-1"
                                    />
                                    <TextInput
                                        type="text"
                                        :model-value="originalSubject"
                                        :placeholder="
                                            $t(
                                                'ab_tests.variants.subject_placeholder',
                                            )
                                        "
                                        class="w-full bg-slate-50 dark:bg-slate-700/50"
                                        disabled
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        :value="
                                            $t('ab_tests.variants.preheader')
                                        "
                                        class="mb-1"
                                    />
                                    <TextInput
                                        type="text"
                                        :model-value="originalPreheader"
                                        :placeholder="
                                            $t(
                                                'ab_tests.variants.preheader_placeholder',
                                            )
                                        "
                                        class="w-full bg-slate-50 dark:bg-slate-700/50"
                                        disabled
                                    />
                                </div>
                                <!-- Info message for control variant -->
                                <p
                                    v-if="!controlVariantEmpty"
                                    class="text-xs text-emerald-600 dark:text-emerald-400"
                                >
                                    ℹ️
                                    {{
                                        $t("ab_tests.variants.control_readonly")
                                    }}
                                </p>
                                <!-- Warning if control is empty -->
                                <p
                                    v-else
                                    class="text-xs text-amber-600 dark:text-amber-400"
                                >
                                    ⚠️
                                    {{
                                        $t(
                                            "ab_tests.variants.control_empty_warning",
                                        )
                                    }}
                                </p>
                            </template>

                            <!-- Other Variants: Editable with AI Assistant -->
                            <template v-else>
                                <div>
                                    <div
                                        class="mb-1 flex items-center justify-between"
                                    >
                                        <InputLabel
                                            :value="
                                                $t('ab_tests.variants.subject')
                                            "
                                        />
                                        <SubjectAiAssistant
                                            :current-content="messageContent"
                                            :control-subject="
                                                controlVariant.subject ||
                                                originalSubject
                                            "
                                            :control-preheader="
                                                controlVariant.preheader ||
                                                originalPreheader
                                            "
                                            :is-variant="true"
                                            @select="
                                                handleVariantAISelect(
                                                    index,
                                                    $event,
                                                )
                                            "
                                            button-size="sm"
                                        />
                                    </div>
                                    <TextInput
                                        type="text"
                                        :model-value="variant.subject"
                                        @update:model-value="
                                            updateVariant(
                                                index,
                                                'subject',
                                                $event,
                                            )
                                        "
                                        :placeholder="
                                            $t(
                                                'ab_tests.variants.subject_placeholder',
                                            )
                                        "
                                        class="w-full"
                                        :disabled="disabled"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        :value="
                                            $t('ab_tests.variants.preheader')
                                        "
                                        class="mb-1"
                                    />
                                    <TextInput
                                        type="text"
                                        :model-value="variant.preheader"
                                        @update:model-value="
                                            updateVariant(
                                                index,
                                                'preheader',
                                                $event,
                                            )
                                        "
                                        :placeholder="
                                            $t(
                                                'ab_tests.variants.preheader_placeholder',
                                            )
                                        "
                                        class="w-full"
                                        :disabled="disabled"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <p
                    v-if="!hasMinimumVariants"
                    class="mt-3 text-sm text-amber-600 dark:text-amber-400"
                >
                    ⚠️ {{ $t("ab_tests.variants.minimum_required") }}
                </p>
            </div>

            <!-- Test Settings -->
            <div
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
            >
                <h4
                    class="mb-4 text-base font-semibold text-slate-900 dark:text-white"
                >
                    ⚙️ {{ $t("ab_tests.settings.title") }}
                </h4>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Winning Metric -->
                    <div>
                        <InputLabel
                            :value="$t('ab_tests.settings.winning_metric')"
                            class="mb-2"
                        />
                        <div class="space-y-2">
                            <label
                                v-for="metric in winningMetricOptions"
                                :key="metric.value"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition-all"
                                :class="
                                    localConfig.winning_metric === metric.value
                                        ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-500 dark:bg-indigo-900/20'
                                        : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'
                                "
                            >
                                <input
                                    type="radio"
                                    v-model="localConfig.winning_metric"
                                    :value="metric.value"
                                    class="text-indigo-600 focus:ring-indigo-500"
                                    :disabled="disabled"
                                />
                                <span class="text-lg">{{ metric.icon }}</span>
                                <span
                                    class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ metric.label }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Sample Percentage -->
                    <div>
                        <InputLabel
                            :value="$t('ab_tests.settings.sample_percentage')"
                            class="mb-2"
                        />
                        <div class="space-y-2">
                            <input
                                type="range"
                                v-model.number="localConfig.sample_percentage"
                                min="5"
                                max="50"
                                step="5"
                                class="w-full"
                                :disabled="disabled"
                            />
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-slate-500">5%</span>
                                <span
                                    class="font-semibold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ localConfig.sample_percentage }}%
                                </span>
                                <span class="text-slate-500">50%</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ $t("ab_tests.settings.sample_help") }}
                            </p>
                        </div>
                    </div>

                    <!-- Test Duration -->
                    <div>
                        <InputLabel
                            :value="$t('ab_tests.settings.test_duration')"
                            class="mb-2"
                        />
                        <select
                            v-model.number="localConfig.test_duration_hours"
                            class="block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            :disabled="disabled"
                        >
                            <option
                                v-for="option in durationOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $t("ab_tests.settings.duration_help") }}
                        </p>
                    </div>
                </div>

                <!-- Auto Select Winner -->
                <div
                    class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700"
                >
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="localConfig.auto_select_winner"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            :disabled="disabled"
                        />
                        <div>
                            <span
                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ $t("ab_tests.settings.auto_winner") }}
                            </span>
                            <p class="text-xs text-slate-500">
                                {{ $t("ab_tests.settings.auto_winner_help") }}
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Confidence Threshold -->
                <div v-if="localConfig.auto_select_winner" class="mt-4">
                    <InputLabel
                        :value="$t('ab_tests.settings.confidence_threshold')"
                        class="mb-2"
                    />
                    <div class="flex items-center gap-4">
                        <input
                            type="range"
                            v-model.number="localConfig.confidence_threshold"
                            min="60"
                            max="99"
                            step="1"
                            class="flex-1"
                            :disabled="disabled"
                        />
                        <span
                            class="min-w-[4rem] text-center font-semibold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ localConfig.confidence_threshold }}%
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $t("ab_tests.settings.confidence_help") }}
                    </p>
                </div>
            </div>

            <!-- Info Banner -->
            <div
                class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
            >
                <div class="flex gap-3">
                    <svg
                        class="h-5 w-5 flex-shrink-0 text-blue-500"
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
                    <div>
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            {{ $t("ab_tests.info_banner") }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
