<script setup>
const props = defineProps({
    listHistory: Array,
});

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleDateString("pl-PL", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Status badge styling
const getStatusClass = (status) => {
    const classes = {
        active: "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300",
        unsubscribed: "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300",
        bounced: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300",
    };
    return classes[status] || "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300";
};

import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4">
        <!-- Timeline View -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
            <h4
                class="mb-6 text-sm font-semibold text-slate-900 dark:text-white"
            >
                {{ $t("subscriber_card.list_history.title") }}
            </h4>

            <div v-if="listHistory.length > 0" class="relative">
                <!-- Timeline line -->
                <div
                    class="absolute left-4 top-0 h-full w-0.5 bg-slate-200 dark:bg-slate-700"
                ></div>

                <!-- Timeline items -->
                <div class="space-y-6">
                    <div
                        v-for="(item, index) in listHistory"
                        :key="item.list_id"
                        class="relative pl-10"
                    >
                        <!-- Timeline dot -->
                        <div
                            :class="[
                                'absolute left-2 top-1 h-5 w-5 rounded-full border-2 border-white dark:border-slate-900',
                                item.status === 'active'
                                    ? 'bg-green-500'
                                    : item.status === 'unsubscribed'
                                      ? 'bg-red-500'
                                      : 'bg-yellow-500',
                            ]"
                        ></div>

                        <!-- Card -->
                        <div
                            class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            :class="[
                                                'flex h-6 w-6 items-center justify-center rounded text-xs',
                                                item.list_type === 'sms'
                                                    ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                                                    : 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400',
                                            ]"
                                        >
                                            {{ item.list_type === "sms" ? "📱" : "📧" }}
                                        </span>
                                        <span
                                            class="font-medium text-slate-900 dark:text-white"
                                        >
                                            {{ item.list_name }}
                                        </span>
                                    </div>

                                    <!-- Events -->
                                    <div class="mt-3 space-y-2 text-sm">
                                        <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <span>
                                                {{ $t("subscriber_card.list_history.subscribed") }}:
                                                {{ formatDate(item.subscribed_at) }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="item.unsubscribed_at"
                                            class="flex items-center gap-2 text-red-600 dark:text-red-400"
                                        >
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <span>
                                                {{ $t("subscriber_card.list_history.unsubscribed") }}:
                                                {{ formatDate(item.unsubscribed_at) }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="item.source"
                                            class="flex items-center gap-2 text-slate-500 dark:text-slate-400"
                                        >
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <span>
                                                {{ $t("subscriber_card.list_history.source") }}:
                                                {{ item.source }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        getStatusClass(item.status),
                                    ]"
                                >
                                    {{ item.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="py-12 text-center"
            >
                <div class="text-4xl">📋</div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.list_history.empty") }}
                </p>
            </div>
        </div>
    </div>
</template>
