<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";

const { t } = useI18n();

const props = defineProps({
    test: Object,
    results: Object,
});

// Status badge colors
const statusColors = {
    draft: "bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300",
    running:
        "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400",
    paused: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
    completed:
        "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
    cancelled: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
};

// Metric display names
const metricLabels = {
    open_rate: t("ab_tests.metrics.open_rate"),
    click_rate: t("ab_tests.metrics.click_rate"),
    conversion_rate: t("ab_tests.metrics.conversion_rate"),
};

// Sorted variants by performance
const sortedVariants = computed(() => {
    const variants = Object.values(props.results.variants || {});
    const metric = props.test.winning_metric || "open_rate";
    return variants.sort((a, b) => (b[metric] || 0) - (a[metric] || 0));
});

// Get best performing variant
const leadingVariant = computed(() => {
    return sortedVariants.value[0] || null;
});

// Time remaining calculation
const timeRemaining = computed(() => {
    if (!props.test.test_started_at || props.results.duration_elapsed) {
        return null;
    }

    const startTime = new Date(props.test.test_started_at);
    const endTime = new Date(
        startTime.getTime() + props.test.test_duration_hours * 60 * 60 * 1000,
    );
    const now = new Date();
    const remaining = endTime - now;

    if (remaining <= 0) return null;

    const hours = Math.floor(remaining / (1000 * 60 * 60));
    const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));

    return `${hours}h ${minutes}m`;
});

// Select winner modal
const showSelectWinnerModal = ref(false);
const selectedWinnerVariantId = ref(null);
const sendToRemaining = ref(true);
const isSelectingWinner = ref(false);

const openSelectWinnerModal = () => {
    selectedWinnerVariantId.value = leadingVariant.value?.id || null;
    showSelectWinnerModal.value = true;
};

const closeSelectWinnerModal = () => {
    showSelectWinnerModal.value = false;
    selectedWinnerVariantId.value = null;
};

const selectWinner = () => {
    if (!selectedWinnerVariantId.value) return;

    isSelectingWinner.value = true;

    router.post(
        route("ab-tests.select-winner", props.test.id),
        {
            variant_id: selectedWinnerVariantId.value,
            send_to_remaining: sendToRemaining.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeSelectWinnerModal();
            },
            onFinish: () => {
                isSelectingWinner.value = false;
            },
        },
    );
};

// Format percentage
const formatPercent = (value) => {
    if (value === null || value === undefined) return "0%";
    return `${parseFloat(value).toFixed(1)}%`;
};

// Format number
const formatNumber = (value) => {
    if (value === null || value === undefined) return "0";
    return value.toLocaleString();
};
</script>

<template>
    <Head :title="$t('ab_tests.show.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200"
                    >
                        🧪 {{ $t("ab_tests.show.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            test.message?.subject ||
                            $t("ab_tests.show.no_subject")
                        }}
                    </p>
                </div>
                <Link
                    :href="route('messages.index')"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    &larr; {{ $t("ab_tests.show.back_to_messages") }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Status & Info Card -->
                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                >
                    <div class="p-6">
                        <div
                            class="flex flex-wrap items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-4">
                                <!-- Status Badge -->
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium"
                                    :class="
                                        statusColors[test.status] ||
                                        statusColors.draft
                                    "
                                >
                                    <span
                                        v-if="test.status === 'running'"
                                        class="animate-pulse"
                                        >●</span
                                    >
                                    {{ $t("ab_tests.status." + test.status) }}
                                </span>

                                <!-- Winning Metric -->
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("ab_tests.show.winning_metric") }}:
                                    <span
                                        class="font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            metricLabels[test.winning_metric] ||
                                            test.winning_metric
                                        }}
                                    </span>
                                </div>

                                <!-- Time Remaining -->
                                <div
                                    v-if="
                                        timeRemaining &&
                                        test.status === 'running'
                                    "
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    ⏱️ {{ $t("ab_tests.show.time_remaining") }}:
                                    <span
                                        class="font-medium text-indigo-600 dark:text-indigo-400"
                                        >{{ timeRemaining }}</span
                                    >
                                </div>

                                <!-- Duration elapsed -->
                                <div
                                    v-if="
                                        results.duration_elapsed &&
                                        test.status === 'running'
                                    "
                                    class="text-sm text-amber-600 dark:text-amber-400"
                                >
                                    ⚠️
                                    {{ $t("ab_tests.show.duration_elapsed") }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="test.status === 'running'"
                                    @click="openSelectWinnerModal"
                                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-500"
                                >
                                    🏆 {{ $t("ab_tests.show.select_winner") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistical Significance -->
                <div
                    v-if="results.statistical_significance !== null"
                    class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                >
                    <div class="p-6">
                        <h3
                            class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            📊
                            {{ $t("ab_tests.show.statistical_significance") }}
                        </h3>
                        <div class="flex items-center gap-4">
                            <div
                                class="relative h-4 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="
                                        results.is_significant
                                            ? 'bg-emerald-500'
                                            : 'bg-amber-500'
                                    "
                                    :style="{
                                        width:
                                            Math.min(
                                                results.statistical_significance,
                                                100,
                                            ) + '%',
                                    }"
                                ></div>
                                <!-- Threshold marker -->
                                <div
                                    class="absolute top-0 h-full w-0.5 bg-slate-500"
                                    :style="{
                                        left: test.confidence_threshold + '%',
                                    }"
                                ></div>
                            </div>
                            <div class="min-w-[80px] text-right">
                                <span
                                    class="text-xl font-bold"
                                    :class="
                                        results.is_significant
                                            ? 'text-emerald-600'
                                            : 'text-amber-600'
                                    "
                                >
                                    {{
                                        formatPercent(
                                            results.statistical_significance,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("ab_tests.show.threshold_label") }}:
                            {{ test.confidence_threshold }}%
                            <span
                                v-if="results.is_significant"
                                class="ml-2 text-emerald-600"
                            >
                                ✓ {{ $t("ab_tests.show.significant") }}
                            </span>
                            <span v-else class="ml-2 text-amber-600">
                                {{ $t("ab_tests.show.not_significant") }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Variants Results -->
                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                >
                    <div class="p-6">
                        <h3
                            class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            🧪 {{ $t("ab_tests.show.variants_results") }}
                        </h3>

                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="variant in sortedVariants"
                                :key="variant.id"
                                class="relative rounded-xl border p-4 transition-all"
                                :class="{
                                    'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/20':
                                        variant.is_winner,
                                    'border-indigo-200 bg-indigo-50/50 dark:border-indigo-700 dark:bg-indigo-900/10':
                                        !variant.is_winner &&
                                        variant === leadingVariant,
                                    'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800':
                                        !variant.is_winner &&
                                        variant !== leadingVariant,
                                }"
                            >
                                <!-- Winner Badge -->
                                <div
                                    v-if="variant.is_winner"
                                    class="absolute -right-2 -top-2"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-1 text-xs font-bold text-white shadow-lg"
                                    >
                                        🏆 {{ $t("ab_tests.show.winner") }}
                                    </span>
                                </div>

                                <!-- Leading Badge (when not yet winner) -->
                                <div
                                    v-else-if="
                                        variant === leadingVariant &&
                                        test.status === 'running'
                                    "
                                    class="absolute -right-2 -top-2"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-indigo-500 px-2 py-1 text-xs font-bold text-white shadow-lg"
                                    >
                                        📈 {{ $t("ab_tests.show.leading") }}
                                    </span>
                                </div>

                                <!-- Variant Header -->
                                <div class="mb-4 flex items-center gap-3">
                                    <span
                                        class="flex h-10 w-10 items-center justify-center rounded-full text-lg font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300':
                                                variant.is_control,
                                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300':
                                                !variant.is_control,
                                        }"
                                    >
                                        {{ variant.variant_letter }}
                                    </span>
                                    <div>
                                        <span
                                            class="font-medium text-slate-900 dark:text-white"
                                        >
                                            {{
                                                $t("ab_tests.variants.variant")
                                            }}
                                            {{ variant.variant_letter }}
                                        </span>
                                        <span
                                            v-if="variant.is_control"
                                            class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                        >
                                            {{
                                                $t("ab_tests.variants.control")
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Subject -->
                                <p
                                    class="mb-4 text-sm text-slate-600 dark:text-slate-400 line-clamp-2"
                                >
                                    {{
                                        variant.subject ||
                                        $t("ab_tests.show.no_subject")
                                    }}
                                </p>

                                <!-- Metrics -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div
                                        class="rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                                    >
                                        <div
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ $t("ab_tests.show.sent") }}
                                        </div>
                                        <div
                                            class="text-lg font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ formatNumber(variant.sent) }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                                    >
                                        <div
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ $t("ab_tests.show.opens") }}
                                        </div>
                                        <div
                                            class="text-lg font-bold text-slate-900 dark:text-white"
                                        >
                                            {{
                                                formatNumber(
                                                    variant.unique_opens,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-lg p-3"
                                        :class="{
                                            'bg-emerald-100 dark:bg-emerald-900/30':
                                                test.winning_metric ===
                                                    'open_rate' &&
                                                variant === leadingVariant,
                                            'bg-slate-50 dark:bg-slate-700/50':
                                                !(
                                                    test.winning_metric ===
                                                        'open_rate' &&
                                                    variant === leadingVariant
                                                ),
                                        }"
                                    >
                                        <div
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                $t("ab_tests.metrics.open_rate")
                                            }}
                                        </div>
                                        <div
                                            class="text-lg font-bold"
                                            :class="{
                                                'text-emerald-600 dark:text-emerald-400':
                                                    test.winning_metric ===
                                                        'open_rate' &&
                                                    variant === leadingVariant,
                                                'text-slate-900 dark:text-white':
                                                    !(
                                                        test.winning_metric ===
                                                            'open_rate' &&
                                                        variant ===
                                                            leadingVariant
                                                    ),
                                            }"
                                        >
                                            {{
                                                formatPercent(variant.open_rate)
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-lg p-3"
                                        :class="{
                                            'bg-emerald-100 dark:bg-emerald-900/30':
                                                test.winning_metric ===
                                                    'click_rate' &&
                                                variant === leadingVariant,
                                            'bg-slate-50 dark:bg-slate-700/50':
                                                !(
                                                    test.winning_metric ===
                                                        'click_rate' &&
                                                    variant === leadingVariant
                                                ),
                                        }"
                                    >
                                        <div
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                $t(
                                                    "ab_tests.metrics.click_rate",
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="text-lg font-bold"
                                            :class="{
                                                'text-emerald-600 dark:text-emerald-400':
                                                    test.winning_metric ===
                                                        'click_rate' &&
                                                    variant === leadingVariant,
                                                'text-slate-900 dark:text-white':
                                                    !(
                                                        test.winning_metric ===
                                                            'click_rate' &&
                                                        variant ===
                                                            leadingVariant
                                                    ),
                                            }"
                                        >
                                            {{
                                                formatPercent(
                                                    variant.click_rate,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test info -->
                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
                >
                    <div class="p-6">
                        <h3
                            class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            ℹ️ {{ $t("ab_tests.show.test_info") }}
                        </h3>
                        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("ab_tests.show.sample_size") }}
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ test.sample_percentage }}%
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("ab_tests.show.duration") }}
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ test.test_duration_hours }}h
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("ab_tests.show.auto_select") }}
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        test.auto_select_winner
                                            ? $t("common.yes")
                                            : $t("common.no")
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("ab_tests.show.started_at") }}
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        test.test_started_at
                                            ? new Date(
                                                  test.test_started_at,
                                              ).toLocaleString()
                                            : "-"
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Select Winner Modal -->
        <Modal
            :show="showSelectWinnerModal"
            @close="closeSelectWinnerModal"
            max-width="md"
        >
            <div class="p-6">
                <h3
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    🏆 {{ $t("ab_tests.show.select_winner_title") }}
                </h3>

                <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("ab_tests.show.select_winner_description") }}
                </p>

                <!-- Variant Selection -->
                <div class="mb-4 space-y-2">
                    <label
                        v-for="variant in sortedVariants"
                        :key="variant.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-all"
                        :class="{
                            'border-indigo-500 bg-indigo-50 dark:border-indigo-500 dark:bg-indigo-900/20':
                                selectedWinnerVariantId === variant.id,
                            'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600':
                                selectedWinnerVariantId !== variant.id,
                        }"
                    >
                        <input
                            type="radio"
                            v-model="selectedWinnerVariantId"
                            :value="variant.id"
                            class="text-indigo-600 focus:ring-indigo-500"
                        />
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            {{ variant.variant_letter }}
                        </span>
                        <div class="flex-1">
                            <div
                                class="font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("ab_tests.variants.variant") }}
                                {{ variant.variant_letter }}
                            </div>
                            <div
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ metricLabels[test.winning_metric] }}:
                                {{
                                    formatPercent(variant[test.winning_metric])
                                }}
                            </div>
                        </div>
                        <span
                            v-if="variant === leadingVariant"
                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            {{ $t("ab_tests.show.recommended") }}
                        </span>
                    </label>
                </div>

                <!-- Send to Remaining Toggle -->
                <label
                    class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                >
                    <input
                        type="checkbox"
                        v-model="sendToRemaining"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <div class="font-medium text-slate-900 dark:text-white">
                            {{ $t("ab_tests.show.send_to_remaining") }}
                        </div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">
                            {{
                                $t(
                                    "ab_tests.show.send_to_remaining_description",
                                )
                            }}
                        </div>
                    </div>
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeSelectWinnerModal">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <PrimaryButton
                        @click="selectWinner"
                        :disabled="
                            !selectedWinnerVariantId || isSelectingWinner
                        "
                    >
                        <svg
                            v-if="isSelectingWinner"
                            class="mr-2 h-4 w-4 animate-spin"
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
                        {{
                            isSelectingWinner
                                ? $t("common.processing")
                                : $t("ab_tests.show.confirm_winner")
                        }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
