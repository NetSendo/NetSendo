<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    plans: Array,
    lists: Array,
    industries: Object,
    businessModels: Object,
    campaignGoals: Object,
    languages: Object,
    messageTypes: Object,
    licensePlan: {
        type: String,
        default: "SILVER",
    },
    campaignCount: {
        type: Number,
        default: 0,
    },
    campaignLimit: {
        type: Number,
        default: null,
    },
    canCreateCampaign: {
        type: Boolean,
        default: true,
    },
});

// Wizard state
const wizardStep = ref(1);
const totalSteps = 4;
const isGenerating = ref(false);
const generatedPlan = ref(null);
const showExportSuccessModal = ref(false);
const exportResult = ref(null);
const showDeleteModal = ref(false);
const planToDelete = ref(null);
const deleteWithMessages = ref(true);
const isDeleting = ref(false);
const showLimitModal = ref(false);

// Form data
const form = useForm({
    name: "",
    industry: "",
    business_model: "",
    campaign_goal: "",
    campaign_language: "en",
    average_order_value: 100,
    margin_percent: 30,
    decision_cycle_days: 7,
    selected_lists: [],
});

// Audience data
const audienceData = ref(null);
const loadingAudience = ref(false);

// Forecast adjustments
const forecastAdjustments = ref({
    message_count: 1.0,
    timeline: 1.0,
    audience_size: 1.0,
});

// Computed
const canProceedStep1 = computed(() => {
    return (
        form.name && form.industry && form.business_model && form.campaign_goal
    );
});

const canProceedStep2 = computed(() => {
    return form.selected_lists.length > 0 && audienceData.value;
});

const selectedListsInfo = computed(() => {
    return props.lists.filter((l) => form.selected_lists.includes(l.id));
});

const campaignLimitDisplay = computed(() => {
    if (props.campaignLimit === null) {
        return null;
    }
    return `${props.campaignCount}/${props.campaignLimit}`;
});

// Methods
const nextStep = async () => {
    if (wizardStep.value === 1 && canProceedStep1.value) {
        wizardStep.value = 2;
    } else if (wizardStep.value === 2 && canProceedStep2.value) {
        wizardStep.value = 3;
        await createPlanAndGenerate();
    } else if (wizardStep.value === 3 && generatedPlan.value) {
        wizardStep.value = 4;
    }
};

const prevStep = () => {
    if (wizardStep.value > 1) {
        wizardStep.value--;
    }
};

const fetchAudienceData = async () => {
    if (form.selected_lists.length === 0) {
        audienceData.value = null;
        return;
    }

    loadingAudience.value = true;
    try {
        const params = new URLSearchParams();
        form.selected_lists.forEach((id) => {
            params.append("list_ids[]", id);
        });

        const response = await fetch(
            route("api.campaign-architect.audience") + "?" + params.toString()
        );
        const data = await response.json();
        audienceData.value = data;
    } catch (error) {
        console.error("Failed to fetch audience data:", error);
    } finally {
        loadingAudience.value = false;
    }
};

const createPlanAndGenerate = async () => {
    isGenerating.value = true;
    try {
        // Create the plan first
        const createResponse = await fetch(route("campaign-architect.store"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                ).content,
            },
            body: JSON.stringify(form.data()),
        });

        const planResult = await createResponse.json();

        if (!planResult.success) {
            // Check if limit is reached - show styled modal instead of alert
            if (planResult.limit_reached) {
                showLimitModal.value = true;
                wizardStep.value = 1;
                return;
            }
            throw new Error(planResult.error || "Failed to create plan");
        }

        // Generate AI strategy
        const generateResponse = await fetch(
            route("campaign-architect.generate", planResult.plan.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
            }
        );

        const generateResult = await generateResponse.json();

        if (generateResult.success) {
            generatedPlan.value = generateResult.plan;
        } else {
            throw new Error(
                generateResult.error || "Failed to generate strategy"
            );
        }
    } catch (error) {
        console.error("Error:", error);
        // Show styled modal for general errors too
        alert(error.message || t("campaign_architect.generation_failed"));
        wizardStep.value = 2;
    } finally {
        isGenerating.value = false;
    }
};

const updateForecast = async () => {
    if (!generatedPlan.value) return;

    try {
        const response = await fetch(
            route("campaign-architect.forecast", generatedPlan.value.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
                body: JSON.stringify(forecastAdjustments.value),
            }
        );

        const result = await response.json();
        if (result.success) {
            generatedPlan.value.forecast = result.forecast;
        }
    } catch (error) {
        console.error("Forecast update failed:", error);
    }
};

const exportPlan = async (mode) => {
    if (!generatedPlan.value) return;

    try {
        const response = await fetch(
            route("campaign-architect.export", generatedPlan.value.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
                body: JSON.stringify({ mode }),
            }
        );

        const result = await response.json();
        if (result.success) {
            exportResult.value = result;
            showExportSuccessModal.value = true;
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error("Export failed:", error);
        alert(error.message || t("campaign_architect.export_failed"));
    }
};

const closeExportModal = () => {
    showExportSuccessModal.value = false;
    exportResult.value = null;
};

const goToList = (type) => {
    closeExportModal();
    if (type === 'email') {
        router.visit(route('messages.index', { campaign_plan_id: generatedPlan.value.id }));
    } else {
        router.visit(route('sms.index', { campaign_plan_id: generatedPlan.value.id }));
    }
};

const openDeleteModal = (plan, event) => {
    event.preventDefault();
    event.stopPropagation();
    planToDelete.value = plan;
    deleteWithMessages.value = true;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    planToDelete.value = null;
    isDeleting.value = false;
};

const confirmDeletePlan = async () => {
    if (!planToDelete.value) return;

    isDeleting.value = true;
    try {
        const response = await fetch(
            route("campaign-architect.destroy", planToDelete.value.id),
            {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
                body: JSON.stringify({ delete_messages: deleteWithMessages.value }),
            }
        );

        const result = await response.json();
        if (result.success) {
            closeDeleteModal();
            router.reload();
        } else {
            throw new Error(result.error || 'Delete failed');
        }
    } catch (error) {
        console.error("Delete failed:", error);
        alert(error.message || t("common.error"));
        isDeleting.value = false;
    }
};

// Watch for list changes
watch(() => form.selected_lists, fetchAudienceData, { deep: true });

// Watch forecast adjustments
watch(forecastAdjustments, updateForecast, { deep: true });

const getMessageTypeIcon = (type) => {
    const icons = {
        educational: "📚",
        sales: "💰",
        reminder: "⏰",
        social_proof: "⭐",
        follow_up: "📤",
        onboarding: "👋",
        reengagement: "🔄",
        announcement: "📢",
        thank_you: "🙏",
        survey: "📊",
    };
    return icons[type] || "📧";
};
</script>

<template>
    <Head :title="$t('campaign_architect.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        🎯 {{ $t("campaign_architect.title") }}
                    </h2>
                    <!-- Campaign Count Badge for SILVER -->
                    <span
                        v-if="licensePlan === 'SILVER' && campaignLimit"
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-bold',
                            canCreateCampaign
                                ? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
                        ]"
                    >
                        {{ campaignLimitDisplay }} {{ $t("campaign_architect.campaigns_label", { count: campaignLimit }) || "kampanii" }}
                    </span>
                </div>
                <span
                    class="rounded-full bg-gradient-to-r from-purple-500 to-pink-500 px-3 py-1 text-xs font-bold text-white"
                >
                    {{ $t("campaign_architect.ai_powered") }}
                </span>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <!-- Progress Steps -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <template v-for="step in totalSteps" :key="step">
                            <div class="flex items-center">
                                <div
                                    :class="[
                                        'flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold',
                                        wizardStep >= step
                                            ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white'
                                            : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                                    ]"
                                >
                                    {{ step }}
                                </div>
                                <span
                                    class="ml-2 hidden text-sm font-medium text-gray-600 dark:text-gray-300 md:block"
                                >
                                    {{
                                        $t(
                                            `campaign_architect.step${step}_title`
                                        )
                                    }}
                                </span>
                            </div>
                            <div
                                v-if="step < totalSteps"
                                :class="[
                                    'mx-4 h-1 flex-1 rounded',
                                    wizardStep > step
                                        ? 'bg-indigo-600'
                                        : 'bg-gray-200 dark:bg-gray-700',
                                ]"
                            />
                        </template>
                    </div>
                </div>

                <!-- Step 1: Business Context -->
                <div
                    v-if="wizardStep === 1"
                    class="rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-800"
                >
                    <h3
                        class="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ $t("campaign_architect.business_context") }}
                    </h3>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Campaign Name -->
                        <div class="md:col-span-2">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.campaign_name") }} *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="
                                    $t(
                                        'campaign_architect.campaign_name_placeholder'
                                    )
                                "
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Industry -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.industry") }} *
                            </label>
                            <select
                                v-model="form.industry"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">
                                    {{ $t("common.select") }}...
                                </option>
                                <option
                                    v-for="(label, key) in industries"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Business Model -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.business_model") }} *
                            </label>
                            <select
                                v-model="form.business_model"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">
                                    {{ $t("common.select") }}...
                                </option>
                                <option
                                    v-for="(label, key) in businessModels"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Campaign Goal -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.campaign_goal") }} *
                            </label>
                            <select
                                v-model="form.campaign_goal"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">
                                    {{ $t("common.select") }}...
                                </option>
                                <option
                                    v-for="(label, key) in campaignGoals"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Campaign Language -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.campaign_language") }}
                                *
                            </label>
                            <select
                                v-model="form.campaign_language"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option
                                    v-for="(label, key) in languages"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    $t(
                                        "campaign_architect.campaign_language_hint"
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Average Order Value -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.aov") }}
                            </label>
                            <div class="relative mt-1">
                                <span
                                    class="absolute left-3 top-2 text-gray-500"
                                    >$</span
                                >
                                <input
                                    v-model.number="form.average_order_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="block w-full rounded-lg border-gray-300 pl-8 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Margin -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.margin") }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    v-model.number="form.margin_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="block w-full rounded-lg border-gray-300 pr-8 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                                <span
                                    class="absolute right-3 top-2 text-gray-500"
                                    >%</span
                                >
                            </div>
                        </div>

                        <!-- Decision Cycle -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("campaign_architect.decision_cycle") }}
                            </label>
                            <div class="relative mt-1">
                                <input
                                    v-model.number="form.decision_cycle_days"
                                    type="number"
                                    min="1"
                                    class="block w-full rounded-lg border-gray-300 pr-14 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                                <span
                                    class="absolute right-3 top-2 text-gray-500"
                                    >{{ $t("funnels.builder.days") }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button
                            @click="nextStep"
                            :disabled="!canProceedStep1"
                            :class="[
                                'inline-flex items-center gap-2 rounded-lg px-6 py-3 font-semibold text-white shadow-lg transition-all',
                                canProceedStep1
                                    ? 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700'
                                    : 'cursor-not-allowed bg-gray-400',
                            ]"
                        >
                            {{ $t("common.next") }}
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
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Audience Selection -->
                <div
                    v-if="wizardStep === 2"
                    class="rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-800"
                >
                    <h3
                        class="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ $t("campaign_architect.audience_selection") }}
                    </h3>

                    <!-- Lists Selection -->
                    <div class="mb-6">
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                        >
                            {{ $t("campaign_architect.select_lists") }} *
                        </label>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label
                                v-for="list in lists"
                                :key="list.id"
                                :class="[
                                    'flex cursor-pointer items-center gap-3 rounded-lg border-2 p-4 transition-all',
                                    form.selected_lists.includes(list.id)
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-600',
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    :value="list.id"
                                    v-model="form.selected_lists"
                                    class="h-5 w-5 rounded border-gray-300 text-indigo-600"
                                />
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span>{{
                                            list.type === "email" ? "📧" : "📱"
                                        }}</span>
                                        <span
                                            class="font-medium text-gray-900 dark:text-white"
                                            >{{ list.name }}</span
                                        >
                                    </div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ list.subscribers_count }}
                                        {{ $t("subscribers.statuses.active") }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Audience Stats -->
                    <div
                        v-if="audienceData"
                        class="rounded-xl bg-gradient-to-r from-slate-100 to-slate-200 p-6 dark:from-slate-700 dark:to-slate-800"
                    >
                        <h4
                            class="mb-4 font-semibold text-gray-900 dark:text-white"
                        >
                            📊 {{ $t("campaign_architect.audience_analysis") }}
                        </h4>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div
                                class="rounded-lg bg-white p-4 dark:bg-gray-800"
                            >
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            "campaign_architect.total_subscribers"
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{
                                        audienceData.total_subscribers.toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-white p-4 dark:bg-gray-800"
                            >
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            "campaign_architect.active_subscribers"
                                        )
                                    }}
                                </p>
                                <p class="text-2xl font-bold text-emerald-600">
                                    {{
                                        audienceData.active_subscribers.toLocaleString()
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-white p-4 dark:bg-gray-800"
                            >
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("campaign_architect.avg_open_rate") }}
                                </p>
                                <p class="text-2xl font-bold text-blue-600">
                                    {{ audienceData.engagement.open_rate }}%
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-white p-4 dark:bg-gray-800"
                            >
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        $t("campaign_architect.avg_click_rate")
                                    }}
                                </p>
                                <p class="text-2xl font-bold text-purple-600">
                                    {{ audienceData.engagement.click_rate }}%
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="loadingAudience"
                        class="flex items-center justify-center py-8"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"
                        ></div>
                    </div>

                    <div class="mt-8 flex justify-between">
                        <button
                            @click="prevStep"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
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
                                    d="M11 17l-5-5m0 0l5-5m-5 5h12"
                                />
                            </svg>
                            {{ $t("common.back") }}
                        </button>
                        <button
                            @click="nextStep"
                            :disabled="!canProceedStep2"
                            :class="[
                                'inline-flex items-center gap-2 rounded-lg px-6 py-3 font-semibold text-white shadow-lg transition-all',
                                canProceedStep2
                                    ? 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700'
                                    : 'cursor-not-allowed bg-gray-400',
                            ]"
                        >
                            🤖 {{ $t("campaign_architect.generate_strategy") }}
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
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Step 3: AI Generation -->
                <div
                    v-if="wizardStep === 3"
                    class="rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-800"
                >
                    <div
                        v-if="isGenerating"
                        class="flex flex-col items-center py-16"
                    >
                        <div
                            class="mb-6 h-16 w-16 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"
                        ></div>
                        <h3
                            class="mb-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            🤖 {{ $t("campaign_architect.generating") }}
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ $t("campaign_architect.generating_hint") }}
                        </p>
                    </div>

                    <div v-else-if="generatedPlan" class="space-y-8">
                        <!-- Strategy Summary -->
                        <div
                            class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white"
                        >
                            <h3 class="mb-2 text-xl font-bold">
                                {{ $t("campaign_architect.strategy_summary") }}
                            </h3>
                            <p>{{ generatedPlan.strategy?.summary }}</p>
                        </div>

                        <!-- SILVER vs GOLD Comparison -->
                        <div
                            :class="[
                                'rounded-xl border-2 p-6',
                                licensePlan === 'GOLD'
                                    ? 'border-amber-400 bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20'
                                    : 'border-indigo-300 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20',
                            ]"
                        >
                            <!-- GOLD License Message -->
                            <div
                                v-if="licensePlan === 'GOLD'"
                                class="flex items-start gap-4"
                            >
                                <div class="flex-shrink-0">
                                    <span class="text-4xl">👑</span>
                                </div>
                                <div>
                                    <h4
                                        class="text-lg font-bold text-amber-700 dark:text-amber-400"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.gold_active_title"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="mt-2 text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.gold_active_desc"
                                            )
                                        }}
                                    </p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            ✅
                                            {{
                                                $t(
                                                    "campaign_architect.feature_unlimited_campaigns"
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            ✅
                                            {{
                                                $t(
                                                    "campaign_architect.feature_advanced_ai"
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            ✅
                                            {{
                                                $t(
                                                    "campaign_architect.feature_ab_testing"
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            ✅
                                            {{
                                                $t(
                                                    "campaign_architect.feature_priority_support"
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- SILVER License Upgrade Message -->
                            <div
                                v-else
                                class="flex flex-col lg:flex-row lg:items-start lg:gap-6"
                            >
                                <div class="flex-1">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <span class="text-4xl">🚀</span>
                                        </div>
                                        <div>
                                            <h4
                                                class="text-lg font-bold text-indigo-700 dark:text-indigo-400"
                                            >
                                                {{
                                                    $t(
                                                        "campaign_architect.upgrade_title"
                                                    )
                                                }}
                                            </h4>
                                            <p
                                                class="mt-2 text-gray-700 dark:text-gray-300"
                                            >
                                                {{
                                                    $t(
                                                        "campaign_architect.upgrade_desc"
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Features Comparison -->
                                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                        <!-- SILVER Features -->
                                        <div
                                            class="rounded-lg bg-gray-100 p-4 dark:bg-gray-800"
                                        >
                                            <h5
                                                class="mb-3 flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300"
                                            >
                                                🥈 SILVER
                                                <span
                                                    class="text-xs text-gray-500"
                                                    >({{
                                                        $t(
                                                            "campaign_architect.current_plan"
                                                        )
                                                    }})</span
                                                >
                                            </h5>
                                            <ul class="space-y-2 text-sm">
                                                <li
                                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.silver_basic_ai"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.silver_3_campaigns"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                                >
                                                    <span class="text-red-400"
                                                        >✗</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.silver_no_ab"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                                >
                                                    <span class="text-red-400"
                                                        >✗</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.silver_no_advanced"
                                                        )
                                                    }}
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- GOLD Features -->
                                        <div
                                            class="rounded-lg bg-gradient-to-br from-amber-100 to-yellow-100 p-4 ring-2 ring-amber-400 dark:from-amber-900/30 dark:to-yellow-900/30"
                                        >
                                            <h5
                                                class="mb-3 flex items-center gap-2 font-semibold text-amber-700 dark:text-amber-400"
                                            >
                                                👑 GOLD
                                                <span
                                                    class="ml-1 rounded bg-amber-500 px-2 py-0.5 text-xs font-bold text-white"
                                                    >PRO</span
                                                >
                                            </h5>
                                            <ul class="space-y-2 text-sm">
                                                <li
                                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.gold_advanced_ai"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.gold_unlimited"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.gold_ab_testing"
                                                        )
                                                    }}
                                                </li>
                                                <li
                                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                                >
                                                    <span class="text-green-500"
                                                        >✓</span
                                                    >
                                                    {{
                                                        $t(
                                                            "campaign_architect.gold_priority"
                                                        )
                                                    }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upgrade CTA -->
                                <div class="mt-6 flex-shrink-0 lg:mt-0">
                                    <Link
                                        :href="route('license.index')"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-4 text-lg font-bold text-white shadow-lg transition-all hover:from-amber-600 hover:to-yellow-600 hover:shadow-xl"
                                    >
                                        👑
                                        {{
                                            $t(
                                                "campaign_architect.upgrade_to_gold"
                                            )
                                        }}
                                    </Link>
                                    <p
                                        class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.upgrade_price"
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Campaign Blueprint -->
                        <div>
                            <h4
                                class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                📋
                                {{
                                    $t("campaign_architect.campaign_blueprint")
                                }}
                            </h4>

                            <div class="space-y-4">
                                <div
                                    v-for="(step, index) in generatedPlan.steps"
                                    :key="step.id"
                                    class="relative"
                                >
                                    <!-- Timeline connector -->
                                    <div
                                        v-if="
                                            index <
                                            generatedPlan.steps.length - 1
                                        "
                                        class="absolute left-6 top-16 h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                                    ></div>

                                    <div class="flex gap-4">
                                        <!-- Step number -->
                                        <div
                                            class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 text-lg font-bold text-white"
                                        >
                                            {{ step.order }}
                                        </div>

                                        <!-- Step content -->
                                        <div
                                            class="flex-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-3"
                                            >
                                                <span class="text-2xl">{{
                                                    getMessageTypeIcon(
                                                        step.message_type
                                                    )
                                                }}</span>
                                                <span
                                                    :class="[
                                                        'rounded-full px-3 py-1 text-xs font-medium',
                                                        step.channel === 'email'
                                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                                            : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                    ]"
                                                >
                                                    {{
                                                        step.channel.toUpperCase()
                                                    }}
                                                </span>
                                                <span
                                                    class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                                >
                                                    {{
                                                        messageTypes[
                                                            step.message_type
                                                        ] || step.message_type
                                                    }}
                                                </span>
                                                <span
                                                    v-if="step.delay_days > 0"
                                                    class="text-sm text-gray-500 dark:text-gray-400"
                                                >
                                                    ⏱️ +{{ step.delay_days }}d
                                                </span>
                                            </div>

                                            <h5
                                                class="font-semibold text-gray-900 dark:text-white"
                                            >
                                                {{ step.subject }}
                                            </h5>
                                            <p
                                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                {{ step.description }}
                                            </p>

                                            <!-- Conditions -->
                                            <div
                                                v-if="step.conditions"
                                                class="mt-3 rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20"
                                            >
                                                <span
                                                    class="text-xs font-medium text-amber-800 dark:text-amber-300"
                                                >
                                                    ⚡
                                                    {{
                                                        $t(
                                                            "campaign_architect.condition"
                                                        )
                                                    }}:
                                                    {{
                                                        step.conditions
                                                            .trigger ===
                                                        "opened"
                                                            ? "IF opened previous email"
                                                            : step.conditions
                                                                  .trigger
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <button
                                @click="prevStep"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
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
                                        d="M11 17l-5-5m0 0l5-5m-5 5h12"
                                    />
                                </svg>
                                {{ $t("common.back") }}
                            </button>
                            <button
                                @click="nextStep"
                                class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg hover:from-indigo-700 hover:to-purple-700"
                            >
                                {{ $t("campaign_architect.view_forecast") }}
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
                                        d="M13 7l5 5m0 0l-5 5m5-5H6"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Forecast & Export -->
                <div v-if="wizardStep === 4 && generatedPlan" class="space-y-6">
                    <!-- Forecast Dashboard -->
                    <div
                        class="rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-800"
                    >
                        <h3
                            class="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            📈 {{ $t("campaign_architect.forecast_dashboard") }}
                        </h3>

                        <!-- Metrics Grid -->
                        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div
                                class="rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-4 text-white"
                            >
                                <p class="text-sm opacity-80">
                                    {{
                                        $t(
                                            "campaign_architect.predicted_open_rate"
                                        )
                                    }}
                                </p>
                                <p class="text-3xl font-bold">
                                    {{
                                        generatedPlan.forecast?.open_rate || 0
                                    }}%
                                </p>
                            </div>
                            <div
                                class="rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-4 text-white"
                            >
                                <p class="text-sm opacity-80">
                                    {{
                                        $t(
                                            "campaign_architect.predicted_click_rate"
                                        )
                                    }}
                                </p>
                                <p class="text-3xl font-bold">
                                    {{
                                        generatedPlan.forecast?.click_rate || 0
                                    }}%
                                </p>
                            </div>
                            <div
                                class="rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-4 text-white"
                            >
                                <p class="text-sm opacity-80">
                                    {{
                                        $t(
                                            "campaign_architect.predicted_conversion"
                                        )
                                    }}
                                </p>
                                <p class="text-3xl font-bold">
                                    {{
                                        generatedPlan.forecast
                                            ?.conversion_rate || 0
                                    }}%
                                </p>
                            </div>
                            <div
                                class="rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 p-4 text-white"
                            >
                                <p class="text-sm opacity-80">
                                    {{
                                        $t(
                                            "campaign_architect.predicted_revenue"
                                        )
                                    }}
                                </p>
                                <p class="text-3xl font-bold">
                                    ${{
                                        (
                                            generatedPlan.forecast
                                                ?.projected_revenue || 0
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- ROI Card -->
                        <div
                            class="mb-8 rounded-xl bg-gradient-to-r from-slate-800 to-slate-900 p-6 text-white"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-lg opacity-80">
                                        {{
                                            $t(
                                                "campaign_architect.estimated_roi"
                                            )
                                        }}
                                    </p>
                                    <p class="text-5xl font-bold">
                                        {{ generatedPlan.forecast?.roi || 0 }}%
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm opacity-60">
                                        {{
                                            $t(
                                                "campaign_architect.expected_conversions"
                                            )
                                        }}
                                    </p>
                                    <p class="text-2xl font-semibold">
                                        {{
                                            generatedPlan.forecast
                                                ?.expected_conversions || 0
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Adjustment Sliders -->
                        <div class="grid gap-6 md:grid-cols-3">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        $t(
                                            "campaign_architect.adjust_messages"
                                        )
                                    }}:
                                    {{
                                        Math.round(
                                            forecastAdjustments.message_count *
                                                100
                                        )
                                    }}%
                                </label>
                                <input
                                    v-model.number="
                                        forecastAdjustments.message_count
                                    "
                                    type="range"
                                    min="0.5"
                                    max="2"
                                    step="0.1"
                                    class="w-full accent-indigo-600"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        $t(
                                            "campaign_architect.adjust_timeline"
                                        )
                                    }}:
                                    {{
                                        Math.round(
                                            forecastAdjustments.timeline * 100
                                        )
                                    }}%
                                </label>
                                <input
                                    v-model.number="
                                        forecastAdjustments.timeline
                                    "
                                    type="range"
                                    min="0.5"
                                    max="2"
                                    step="0.1"
                                    class="w-full accent-indigo-600"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        $t(
                                            "campaign_architect.adjust_audience"
                                        )
                                    }}:
                                    {{
                                        Math.round(
                                            forecastAdjustments.audience_size *
                                                100
                                        )
                                    }}%
                                </label>
                                <input
                                    v-model.number="
                                        forecastAdjustments.audience_size
                                    "
                                    type="range"
                                    min="0.1"
                                    max="2"
                                    step="0.1"
                                    class="w-full accent-indigo-600"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Export Actions -->
                    <div
                        class="rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-800"
                    >
                        <h3
                            class="mb-6 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            🚀 {{ $t("campaign_architect.export_title") }}
                        </h3>

                        <div class="grid gap-4 md:grid-cols-2">
                            <button
                                @click="exportPlan('draft')"
                                class="flex items-center gap-3 rounded-xl border-2 border-gray-200 p-6 text-left transition-all hover:border-indigo-500 hover:bg-indigo-50 dark:border-gray-600 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-2xl dark:bg-gray-700"
                                >
                                    📝
                                </div>
                                <div>
                                    <h4
                                        class="font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.export_as_draft"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.export_draft_hint"
                                            )
                                        }}
                                    </p>
                                </div>
                            </button>

                            <button
                                @click="exportPlan('scheduled')"
                                class="flex items-center gap-3 rounded-xl border-2 border-gray-200 p-6 text-left transition-all hover:border-emerald-500 hover:bg-emerald-50 dark:border-gray-600 dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 text-2xl dark:bg-emerald-900/30"
                                >
                                    🚀
                                </div>
                                <div>
                                    <h4
                                        class="font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.export_scheduled"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.export_scheduled_hint"
                                            )
                                        }}
                                    </p>
                                </div>
                            </button>
                        </div>

                        <div class="mt-6 flex justify-start">
                            <button
                                @click="prevStep"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
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
                                        d="M11 17l-5-5m0 0l5-5m-5 5h12"
                                    />
                                </svg>
                                {{ $t("common.back") }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Previous Plans -->
                <div v-if="wizardStep === 1 && plans.length > 0" class="mt-8">
                    <h3
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t("campaign_architect.recent_plans") }}
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="plan in plans"
                            :key="plan.id"
                            :href="route('campaign-architect.show', plan.id)"
                            class="rounded-xl border border-gray-200 bg-white p-4 transition-all hover:shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                        plan.status === 'exported'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                            : plan.status === 'active'
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                    ]"
                                >
                                    {{ plan.status }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >{{ plan.updated_at }}</span>
                                    <button
                                        @click="openDeleteModal(plan, $event)"
                                        class="rounded p-1 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        :title="$t('common.delete')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <h4
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ plan.name }}
                            </h4>
                            <p
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ plan.total_messages }} messages ·
                                {{ plan.timeline_days }} days
                            </p>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Export Success Modal -->
    <Modal :show="showExportSuccessModal" @close="closeExportModal" max-width="lg">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                    <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">
                    {{ $t('campaign_architect.export_success_title') }}
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ $t('campaign_architect.export_success_description') }}
                </p>
            </div>

            <!-- Created Messages Summary -->
            <div v-if="exportResult" class="mb-6 rounded-xl bg-gray-50 p-4 dark:bg-gray-700/50">
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ exportResult.emails_created || 0 }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('campaign_architect.created_emails') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ exportResult.sms_created || 0 }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('campaign_architect.created_sms') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="mb-6">
                <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">
                    {{ $t('campaign_architect.next_steps_title') }}
                </h4>
                <ol class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li class="flex items-start gap-2">
                        <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">1</span>
                        {{ $t('campaign_architect.next_step_1') }}
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">2</span>
                        {{ $t('campaign_architect.next_step_2') }}
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">3</span>
                        {{ $t('campaign_architect.next_step_3') }}
                    </li>
                </ol>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <button
                    v-if="exportResult?.emails_created > 0"
                    @click="goToList('email')"
                    class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white hover:bg-indigo-700"
                >
                    📧 {{ $t('campaign_architect.go_to_emails') }}
                </button>
                <button
                    v-if="exportResult?.sms_created > 0"
                    @click="goToList('sms')"
                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-700"
                >
                    📱 {{ $t('campaign_architect.go_to_sms') }}
                </button>
                <button
                    @click="closeExportModal"
                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    {{ $t('campaign_architect.stay_here') }}
                </button>
            </div>
        </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" @close="closeDeleteModal" max-width="md">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">
                    {{ $t('campaign_architect.delete_plan_title') }}
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ $t('campaign_architect.delete_plan_confirm') }}
                </p>
            </div>

            <!-- Plan Info -->
            <div v-if="planToDelete" class="mb-6 rounded-xl bg-gray-50 p-4 dark:bg-gray-700/50">
                <h4 class="font-semibold text-gray-900 dark:text-white">{{ planToDelete.name }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ planToDelete.total_messages }} {{ $t('campaign_architect.messages_count') }}
                </p>
            </div>

            <!-- Delete Messages Checkbox -->
            <div class="mb-6">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        v-model="deleteWithMessages"
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $t('campaign_architect.delete_associated_messages') }}
                    </span>
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button
                    @click="closeDeleteModal"
                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    {{ $t('common.cancel') }}
                </button>
                <button
                    @click="confirmDeletePlan"
                    :disabled="isDeleting"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 font-medium text-white hover:bg-red-700 disabled:opacity-50"
                >
                    <span v-if="isDeleting">{{ $t('common.deleting') }}...</span>
                    <span v-else>{{ $t('common.delete') }}</span>
                </button>
            </div>
        </div>
    </Modal>

    <!-- Campaign Limit Reached Modal -->
    <Modal :show="showLimitModal" @close="showLimitModal = false" max-width="md">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="h-8 w-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">
                    {{ $t('campaign_architect.limit_reached_title') }}
                </h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ $t('campaign_architect.campaign_limit_reached', { limit: campaignLimit }) }}
                </p>
            </div>

            <!-- GOLD Upgrade Benefits -->
            <div class="mb-6 rounded-xl bg-gradient-to-r from-yellow-50 to-amber-50 p-4 dark:from-yellow-900/20 dark:to-amber-900/20 border border-yellow-200 dark:border-yellow-800">
                <h4 class="font-semibold text-amber-800 dark:text-amber-300 mb-2">
                    ✨ {{ $t('campaign_architect.gold_benefits_title') }}
                </h4>
                <ul class="text-sm text-amber-700 dark:text-amber-400 space-y-1">
                    <li>• {{ $t('campaign_architect.gold_unlimited') }}</li>
                    <li>• {{ $t('campaign_architect.gold_ab_testing') }}</li>
                    <li>• {{ $t('campaign_architect.gold_ai_models') }}</li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button
                    @click="showLimitModal = false"
                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                >
                    {{ $t('common.close') }}
                </button>
                <Link
                    :href="route('license.index')"
                    class="flex-1 rounded-lg bg-gradient-to-r from-yellow-500 to-amber-500 px-4 py-2.5 font-medium text-white text-center hover:from-yellow-600 hover:to-amber-600"
                >
                    {{ $t('campaign_architect.upgrade_to_gold') }}
                </Link>
            </div>
        </div>
    </Modal>
</template>
