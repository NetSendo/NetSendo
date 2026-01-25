<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";
import { Head, Link } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";

const { t } = useI18n();
const { formatDate } = useDateTime();

const props = defineProps({
    sequence: Object,
    stats: Object,
    steps_progress: Array,
    recent_enrollments: Array,
});

const getStatusColor = (status) => {
    switch (status) {
        case "active":
            return "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300";
        case "completed":
            return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300";
        case "paused":
            return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300";
        case "cancelled":
            return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300";
        default:
            return "bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300";
    }
};

const getActionTypeLabel = (type) => {
    return t(`crm.sequences.steps.${type}`, type);
};

const getProgressWidth = (count) => {
    if (props.stats.total_enrollments === 0) return "0%";
    return `${(count / props.stats.total_enrollments) * 100}%`;
};
</script>

<template>
    <Head :title="$t('crm.reports.title', { name: sequence.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/crm/sequences"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
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
                        <h1
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ sequence.name }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{
                                $t(
                                    "crm.reports.subtitle",
                                    "Raport skuteczności sekwencji",
                                )
                            }}
                        </p>
                    </div>
                </div>
                <Link
                    :href="`/crm/sequences/${sequence.id}/edit`"
                    class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
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
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                        />
                    </svg>
                    {{ $t("crm.reports.edit_sequence", "Edytuj sekwencję") }}
                </Link>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Enrollments -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        $t(
                            "crm.reports.total_enrollments",
                            "Zapisanych kontaktów",
                        )
                    }}
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"
                >
                    {{ stats.total_enrollments }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    <span class="text-blue-600 dark:text-blue-400"
                        >{{ stats.active }}
                        {{ $t("crm.reports.active", "aktywnych") }}</span
                    >
                    •
                    <span class="text-green-600 dark:text-green-400"
                        >{{ stats.completed }}
                        {{ $t("crm.reports.completed", "ukończonych") }}</span
                    >
                </p>
            </div>

            <!-- Completion Rate -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        $t("crm.reports.completion_rate", "Wskaźnik ukończenia")
                    }}
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400"
                >
                    {{ stats.completion_rate }}%
                </p>
                <div
                    class="mt-2 h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700"
                >
                    <div
                        class="h-2 rounded-full bg-emerald-500"
                        :style="{ width: `${stats.completion_rate}%` }"
                    ></div>
                </div>
            </div>

            <!-- Conversion Rate -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        $t("crm.reports.conversion_rate", "Wskaźnik konwersji")
                    }}
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-violet-600 dark:text-violet-400"
                >
                    {{ stats.conversion_rate }}%
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        $t(
                            "crm.reports.conversions_from",
                            {
                                conversions: stats.conversions,
                                completed: stats.completed,
                            },
                            "{conversions} konwersji z {completed} ukończonych",
                        )
                    }}
                </p>
            </div>

            <!-- Average Completion Time -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <p
                    class="text-sm font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        $t(
                            "crm.reports.avg_completion_time",
                            "Średni czas ukończenia",
                        )
                    }}
                </p>
                <p
                    class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"
                >
                    <template v-if="stats.avg_completion_days"
                        >{{ stats.avg_completion_days }}
                        {{ $t("crm.reports.days", "dni") }}</template
                    >
                    <template v-else>—</template>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ stats.total_tasks_completed }}
                    {{ $t("crm.reports.tasks_completed", "zadań wykonanych") }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Steps Funnel -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <h2
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t("crm.reports.steps_progress", "Postęp po krokach") }}
                </h2>
                <div v-if="steps_progress.length > 0" class="space-y-4">
                    <div
                        v-for="(step, index) in steps_progress"
                        :key="index"
                        class="relative"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span
                                    class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        step.task_title ||
                                        getActionTypeLabel(step.action_type)
                                    }}
                                </span>
                            </div>
                            <span
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ step.reached_count }} /
                                {{ stats.total_enrollments }}
                            </span>
                        </div>
                        <div
                            class="h-3 w-full rounded-full bg-slate-200 dark:bg-slate-700"
                        >
                            <div
                                class="h-3 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
                                :style="{
                                    width: getProgressWidth(step.reached_count),
                                }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div v-else class="py-8 text-center text-slate-500">
                    {{ $t("crm.reports.no_steps", "Brak kroków w sekwencji") }}
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <h2
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    {{
                        $t("crm.reports.recent_enrollments", "Ostatnie zapisy")
                    }}
                </h2>
                <div v-if="recent_enrollments.length > 0" class="space-y-3">
                    <div
                        v-for="enrollment in recent_enrollments"
                        :key="enrollment.id"
                        class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{
                                    enrollment.contact_name?.[0]?.toUpperCase() ||
                                    "?"
                                }}
                            </div>
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ enrollment.contact_name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ formatDate(enrollment.started_at) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="enrollment.converted"
                                class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300"
                            >
                                ✓
                                {{ $t("crm.reports.conversion", "Konwersja") }}
                            </span>
                            <span
                                :class="[
                                    getStatusColor(enrollment.status),
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                ]"
                            >
                                {{ enrollment.status_label }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">
                                {{ enrollment.progress }}%
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="py-8 text-center text-slate-500">
                    {{
                        $t(
                            "crm.reports.no_enrollments",
                            "Brak zapisów do sekwencji",
                        )
                    }}
                </div>
            </div>
        </div>

        <!-- Activity Summary -->
        <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
            <h2
                class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
            >
                {{
                    $t(
                        "crm.reports.activity_summary",
                        "Podsumowanie aktywności",
                    )
                }}
            </h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30"
                        >
                            <svg
                                class="h-5 w-5 text-amber-600 dark:text-amber-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ stats.total_tasks_completed }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{
                                    $t(
                                        "crm.reports.tasks_completed",
                                        "Zadań wykonanych",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30"
                        >
                            <svg
                                class="h-5 w-5 text-blue-600 dark:text-blue-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ stats.total_emails_sent }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{
                                    $t(
                                        "crm.reports.emails_sent",
                                        "Emaili wysłanych",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-700/50">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30"
                        >
                            <svg
                                class="h-5 w-5 text-green-600 dark:text-green-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ stats.total_responses }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ $t("crm.reports.responses", "Odpowiedzi") }}
                                ({{ stats.response_rate }}%)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
