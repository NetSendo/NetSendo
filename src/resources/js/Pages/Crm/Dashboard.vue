<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";

const { formatDate, getCurrentDateFormatted, locale } = useDateTime();

const props = defineProps({
    overdueTasks: Array,
    todayTasks: Array,
    upcomingTasks: Array,
    recentActivities: Array,
    hotLeads: Array,
    stats: Object,
});

// Format currency
const formatCurrency = (value, currency = "PLN") => {
    return new Intl.NumberFormat(locale.value, {
        style: "currency",
        currency: currency,
    }).format(value);
};

// Format date with time
const formatDateWithTime = (date) => {
    if (!date) return "-";
    return formatDate(date, null, {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Get priority class
const getPriorityClass = (priority) => {
    switch (priority) {
        case "high":
            return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300";
        case "medium":
            return "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300";
        case "low":
            return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300";
        default:
            return "bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300";
    }
};

// Get activity icon
const getActivityIcon = (type) => {
    const icons = {
        note: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
        call: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z",
        email: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
        meeting:
            "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
        task_completed: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
        stage_changed: "M13 7l5 5m0 0l-5 5m5-5H6",
        deal_created: "M12 6v6m0 0v6m0-6h6m-6 0H6",
        deal_won:
            "M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z",
        deal_lost:
            "M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z",
    };
    return icons[type] || icons.note;
};

// Get activity content with translation support for legacy logs
import { useI18n } from "vue-i18n";
const { t } = useI18n();

const getActivityContent = (activity) => {
    if (!activity.content) {
        return activity.type_label;
    }

    // Handle legacy Polish strings for completed tasks
    if (activity.type === "task_completed") {
        const polishPrefix = "Ukończono zadanie: ";
        if (activity.content.startsWith(polishPrefix)) {
            const title = activity.content.substring(polishPrefix.length);
            return t("crm.activities.log.task_completed", { title });
        }
    }

    // Handle legacy Polish strings for sent emails
    if (activity.type === "email") {
        const polishPrefix = "Wysłano email: ";
        if (activity.content && activity.content.startsWith(polishPrefix)) {
            const subject = activity.content.substring(polishPrefix.length);
            return t("crm.activities.log.email_sent", { subject });
        }
    }

    return activity.content;
};
</script>

<template>
    <Head title="CRM Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $t("crm.dashboard.title", "Dashboard CRM") }}
                </h1>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    {{ getCurrentDateFormatted() }}
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Contacts -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("crm.contacts.title", "Kontakty") }}
                        </p>
                        <p
                            class="mt-1 text-3xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ stats?.contacts?.total || 0 }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ stats?.contacts?.leads || 0 }}
                            {{ $t("crm.contacts.leads", "leadów") }} •
                            {{ stats?.contacts?.clients || 0 }}
                            {{ $t("crm.contacts.clients", "klientów") }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Open Deals -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{
                                $t("crm.dashboard.open_deals", "Otwarte deale")
                            }}
                        </p>
                        <p
                            class="mt-1 text-3xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ stats?.deals?.open || 0 }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ formatCurrency(stats?.deals?.open_value || 0) }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Won This Month -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{
                                $t(
                                    "crm.dashboard.won_this_month",
                                    "Wygrane (miesiąc)",
                                )
                            }}
                        </p>
                        <p
                            class="mt-1 text-3xl font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ stats?.deals?.won_this_month || 0 }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{
                                formatCurrency(
                                    stats?.deals?.won_value_this_month || 0,
                                )
                            }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-green-600 dark:text-green-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Tasks -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("crm.dashboard.tasks_due_today", "Zadania") }}
                        </p>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span
                                v-if="stats?.tasks?.overdue > 0"
                                class="text-2xl font-bold text-red-600 dark:text-red-400"
                            >
                                {{ stats?.tasks?.overdue }}
                            </span>
                            <span
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ stats?.tasks?.today || 0 }}
                            </span>
                        </div>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <span
                                v-if="stats?.tasks?.overdue > 0"
                                class="text-red-600 dark:text-red-400"
                                >{{ stats?.tasks?.overdue }}
                                {{
                                    $t(
                                        "crm.dashboard.tasks_overdue_label",
                                        "zaległe",
                                    )
                                }}</span
                            >
                            <span v-else>{{
                                $t("crm.dashboard.for_today", "na dziś")
                            }}</span>
                            • {{ stats?.tasks?.upcoming || 0 }}
                            {{ $t("crm.dashboard.upcoming", "nadchodzące") }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-amber-600 dark:text-amber-400"
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
                </div>
            </div>

            <!-- Follow-ups -->
            <Link
                href="/crm/sequences"
                class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800 transition hover:ring-2 hover:ring-violet-500/50 cursor-pointer"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("crm.dashboard.follow_ups", "Follow-upy") }}
                        </p>
                        <p
                            class="mt-1 text-3xl font-bold text-violet-600 dark:text-violet-400"
                        >
                            {{ stats?.follow_ups?.active_enrollments || 0 }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <span
                                v-if="stats?.follow_ups?.due_today > 0"
                                class="text-violet-600 dark:text-violet-400 font-medium"
                                >{{ stats?.follow_ups?.due_today }}
                                {{
                                    $t(
                                        "crm.dashboard.due_today",
                                        "do wykonania dziś",
                                    )
                                }}</span
                            >
                            <span v-else
                                >{{ stats?.follow_ups?.sequences_active || 0 }}
                                {{
                                    $t(
                                        "crm.dashboard.active_sequences",
                                        "aktywnych sekwencji",
                                    )
                                }}</span
                            >
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30"
                    >
                        <svg
                            class="h-6 w-6 text-violet-600 dark:text-violet-400"
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
                    </div>
                </div>
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Tasks Section (Left 2 columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Overdue Tasks -->
                <div
                    v-if="overdueTasks?.length > 0"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold text-red-600 dark:text-red-400"
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
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            {{
                                $t(
                                    "crm.dashboard.tasks_overdue",
                                    "Zaległe zadania",
                                )
                            }}
                        </h2>
                        <Link
                            href="/crm/tasks?view=overdue"
                            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            {{ $t("common.view_all", "Zobacz wszystkie") }} →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="task in overdueTasks"
                            :key="task.id"
                            class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    :class="[
                                        getPriorityClass(task.priority),
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                    ]"
                                >
                                    {{ task.priority }}
                                </span>
                                <div>
                                    <p
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ task.title }}
                                    </p>
                                    <p
                                        v-if="task.contact"
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            task.contact.subscriber?.first_name
                                        }}
                                        {{ task.contact.subscriber?.last_name }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ formatDateWithTime(task.due_date) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Today's Tasks -->
                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            <svg
                                class="h-5 w-5 text-amber-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                                />
                            </svg>
                            {{
                                $t(
                                    "crm.dashboard.upcoming_tasks",
                                    "Zadania na dziś",
                                )
                            }}
                        </h2>
                        <Link
                            href="/crm/tasks"
                            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            {{ $t("common.view_all", "Zobacz wszystkie") }} →
                        </Link>
                    </div>
                    <div v-if="todayTasks?.length > 0" class="space-y-3">
                        <div
                            v-for="task in todayTasks"
                            :key="task.id"
                            :class="[
                                'flex items-center justify-between rounded-xl border p-4',
                                task.source === 'google'
                                    ? 'border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/10'
                                    : 'border-slate-200 dark:border-slate-700',
                            ]"
                        >
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Google Calendar badge -->
                                <span
                                    v-if="task.source === 'google'"
                                    class="flex-shrink-0 flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M19.5 3h-3V1.5h-1.5V3h-6V1.5H7.5V3h-3C3.675 3 3 3.675 3 4.5v15c0 .825.675 1.5 1.5 1.5h15c.825 0 1.5-.675 1.5-1.5v-15c0-.825-.675-1.5-1.5-1.5zm0 16.5h-15V9h15v10.5zm0-12h-15v-3h15v3z"
                                        />
                                    </svg>
                                    Google
                                </span>
                                <!-- Priority badge for CRM tasks -->
                                <span
                                    v-else
                                    :class="[
                                        getPriorityClass(task.priority),
                                        'flex-shrink-0 rounded-full px-2 py-1 text-xs font-medium',
                                    ]"
                                >
                                    {{ task.priority }}
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="font-medium text-slate-900 dark:text-white truncate"
                                    >
                                        {{ task.title }}
                                    </p>
                                    <p
                                        v-if="task.contact"
                                        class="text-sm text-slate-500 dark:text-slate-400 truncate"
                                    >
                                        {{
                                            task.contact.subscriber?.first_name
                                        }}
                                        {{ task.contact.subscriber?.last_name }}
                                    </p>
                                    <p
                                        v-else-if="task.location"
                                        class="text-sm text-slate-500 dark:text-slate-400 truncate"
                                    >
                                        📍 {{ task.location }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <!-- Meet button -->
                                <a
                                    v-if="task.google_meet_link"
                                    :href="task.google_meet_link"
                                    target="_blank"
                                    class="flex items-center gap-1 rounded-lg bg-green-100 px-2.5 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300 dark:hover:bg-green-900/60 transition"
                                    @click.stop
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                                        />
                                    </svg>
                                    Meet
                                </a>
                                <!-- Zoom button -->
                                <a
                                    v-if="task.zoom_meeting_link"
                                    :href="task.zoom_meeting_link"
                                    target="_blank"
                                    class="flex items-center gap-1 rounded-lg bg-blue-100 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/60 transition"
                                    @click.stop
                                >
                                    <svg
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                    >
                                        <path
                                            d="M4 4h10a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm14 3l4-2v10l-4-2V7z"
                                        />
                                    </svg>
                                    Zoom
                                </a>
                                <span
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ formatDateWithTime(task.due_date) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="py-8 text-center text-slate-500 dark:text-slate-400"
                    >
                        <svg
                            class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600"
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
                        <p class="mt-2">
                            {{
                                $t(
                                    "crm.dashboard.no_tasks_today",
                                    "Brak zadań na dziś",
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Hot Leads -->
                <div
                    v-if="hotLeads?.length > 0"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            <svg
                                class="h-5 w-5 text-orange-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"
                                />
                            </svg>
                            {{ $t("crm.dashboard.hot_leads", "Gorące leady") }}
                        </h2>
                        <Link
                            href="/crm/contacts?status=lead"
                            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                        >
                            {{ $t("common.view_all", "Zobacz wszystkie") }} →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <Link
                            v-for="lead in hotLeads"
                            :key="lead.id"
                            :href="`/crm/contacts/${lead.id}`"
                            class="flex items-center justify-between rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-indigo-700 dark:hover:bg-slate-700/50"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-red-500 text-white font-semibold"
                                >
                                    {{
                                        (
                                            lead.subscriber?.first_name?.[0] ||
                                            lead.subscriber?.email?.[0] ||
                                            "?"
                                        ).toUpperCase()
                                    }}
                                </div>
                                <div>
                                    <p
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ lead.subscriber?.first_name }}
                                        {{
                                            lead.subscriber?.last_name ||
                                            lead.subscriber?.email
                                        }}
                                    </p>
                                    <p
                                        v-if="lead.company"
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ lead.company.name }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full bg-orange-100 px-2 py-1 text-xs font-bold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                                >
                                    Score: {{ lead.score }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Recent Activities (Right column) -->
            <div class="lg:col-span-1">
                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{
                            $t(
                                "crm.dashboard.recent_activities",
                                "Ostatnie aktywności",
                            )
                        }}
                    </h2>
                    <div v-if="recentActivities?.length > 0" class="space-y-4">
                        <div
                            v-for="activity in recentActivities"
                            :key="activity.id"
                            class="flex gap-3"
                        >
                            <div
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700"
                            >
                                <svg
                                    class="h-4 w-4 text-slate-600 dark:text-slate-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="getActivityIcon(activity.type)"
                                    />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm text-slate-900 dark:text-white"
                                >
                                    {{ getActivityContent(activity) }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        formatDateWithTime(activity.created_at)
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="py-8 text-center text-slate-500 dark:text-slate-400"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.dashboard.no_activities",
                                    "Brak aktywności",
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
