<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    simulation: { type: Object, required: true },
});

// Score color based on value
const scoreColor = computed(() => {
    const score = props.simulation.inbox_score;
    if (score >= 80)
        return {
            ring: "ring-emerald-500",
            text: "text-emerald-500",
            bg: "bg-emerald-100 dark:bg-emerald-900/30",
        };
    if (score >= 60)
        return {
            ring: "ring-blue-500",
            text: "text-blue-500",
            bg: "bg-blue-100 dark:bg-blue-900/30",
        };
    if (score >= 40)
        return {
            ring: "ring-amber-500",
            text: "text-amber-500",
            bg: "bg-amber-100 dark:bg-amber-900/30",
        };
    return {
        ring: "ring-rose-500",
        text: "text-rose-500",
        bg: "bg-rose-100 dark:bg-rose-900/30",
    };
});

// Folder icon and color
const folderInfo = computed(() => {
    const folder = props.simulation.predicted_folder;
    const folders = {
        inbox: {
            icon: "inbox",
            color: "text-emerald-500",
            bg: "bg-emerald-100 dark:bg-emerald-900/30",
        },
        promotions: {
            icon: "tag",
            color: "text-amber-500",
            bg: "bg-amber-100 dark:bg-amber-900/30",
        },
        spam: {
            icon: "trash",
            color: "text-rose-500",
            bg: "bg-rose-100 dark:bg-rose-900/30",
        },
    };
    return folders[folder] || folders.inbox;
});
</script>

<template>
    <Head :title="$t('deliverability.simulation_result')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('deliverability.simulator')"
                    class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700"
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
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("deliverability.simulation_result") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ simulation.domain }} • {{ simulation.analyzed_at }}
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Score Hero -->
            <div
                class="rounded-xl border bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="flex flex-col items-center lg:flex-row lg:items-start lg:gap-12"
                >
                    <!-- Score Circle -->
                    <div class="flex flex-col items-center">
                        <div
                            class="flex h-40 w-40 items-center justify-center rounded-full ring-8"
                            :class="[scoreColor.ring, scoreColor.bg]"
                        >
                            <div class="text-center">
                                <span
                                    class="text-5xl font-bold"
                                    :class="scoreColor.text"
                                    >{{ simulation.inbox_score }}</span
                                >
                                <span class="text-2xl" :class="scoreColor.text"
                                    >%</span
                                >
                            </div>
                        </div>
                        <p
                            class="mt-4 text-lg font-semibold"
                            :class="scoreColor.text"
                        >
                            {{
                                simulation.score_info?.label ||
                                simulation.score_category
                            }}
                        </p>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 mt-6 lg:mt-0">
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                        >
                            {{ simulation.subject }}
                        </h3>

                        <!-- Predicted Folder -->
                        <div class="flex items-center gap-3 mb-6">
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                                >{{
                                    $t("deliverability.predicted_folder")
                                }}:</span
                            >
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium"
                                :class="folderInfo.bg + ' ' + folderInfo.color"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        v-if="
                                            simulation.predicted_folder ===
                                            'inbox'
                                        "
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                    />
                                    <path
                                        v-else-if="
                                            simulation.predicted_folder ===
                                            'promotions'
                                        "
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                    />
                                    <path
                                        v-else
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                                {{
                                    simulation.folder_info?.label ||
                                    simulation.predicted_folder
                                }}
                            </span>
                        </div>

                        <!-- Score Breakdown -->
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="(
                                    value, key
                                ) in simulation.score_breakdown"
                                :key="key"
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-sm text-gray-600 dark:text-gray-400 capitalize"
                                    >{{ key.replace("_", " ") }}</span
                                >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 w-24 rounded-full bg-gray-200 dark:bg-slate-600"
                                    >
                                        <div
                                            class="h-2 rounded-full"
                                            :class="{
                                                'bg-emerald-500': value >= 80,
                                                'bg-blue-500':
                                                    value >= 60 && value < 80,
                                                'bg-amber-500':
                                                    value >= 40 && value < 60,
                                                'bg-rose-500': value < 40,
                                            }"
                                            :style="{ width: value + '%' }"
                                        ></div>
                                    </div>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white w-10"
                                        >{{ value }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provider Predictions -->
            <div
                v-if="simulation.provider_predictions"
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $t("deliverability.provider_predictions") }}
                </h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        v-for="(
                            pred, provider
                        ) in simulation.provider_predictions"
                        :key="provider"
                        class="rounded-lg border p-4 dark:border-slate-600"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span
                                class="font-medium text-gray-900 dark:text-white capitalize"
                                >{{ provider }}</span
                            >
                            <span
                                class="text-sm font-semibold"
                                :class="{
                                    'text-emerald-500': pred.folder === 'inbox',
                                    'text-amber-500':
                                        pred.folder === 'promotions',
                                    'text-rose-500': pred.folder === 'spam',
                                }"
                            >
                                {{ pred.folder }}
                            </span>
                        </div>
                        <div
                            class="h-2 w-full rounded-full bg-gray-200 dark:bg-slate-600"
                        >
                            <div
                                class="h-2 rounded-full bg-indigo-500"
                                :style="{ width: pred.confidence + '%' }"
                            ></div>
                        </div>
                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ pred.confidence }}%
                            {{ $t("deliverability.confidence") }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Issues -->
            <div
                v-if="simulation.issues?.length"
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $t("deliverability.issues_found") }}
                </h3>
                <div class="space-y-3">
                    <div
                        v-for="(issue, index) in simulation.issues"
                        :key="index"
                        class="flex items-start gap-3 rounded-lg border p-4 dark:border-slate-600"
                        :class="{
                            'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-900/20':
                                issue.severity === 'high',
                            'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20':
                                issue.severity === 'medium',
                            'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20':
                                issue.severity === 'low',
                        }"
                    >
                        <svg
                            class="h-5 w-5 flex-shrink-0"
                            :class="{
                                'text-rose-500': issue.severity === 'high',
                                'text-amber-500': issue.severity === 'medium',
                                'text-blue-500': issue.severity === 'low',
                            }"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <div>
                            <p
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{ issue.title }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ issue.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div
                v-if="simulation.recommendations?.length"
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $t("deliverability.recommendations") }}
                </h3>
                <div class="space-y-3">
                    <div
                        v-for="(rec, index) in simulation.recommendations"
                        :key="index"
                        class="flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20"
                    >
                        <svg
                            class="h-5 w-5 flex-shrink-0 text-indigo-500"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <div>
                            <p
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{ rec.title }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ rec.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-4">
                <Link
                    :href="route('deliverability.simulator')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
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
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
                    {{ $t("deliverability.run_new_simulation") }}
                </Link>
                <Link
                    :href="route('deliverability.simulations.index')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-6 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                >
                    {{ $t("deliverability.view_history") }}
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
