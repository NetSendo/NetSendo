<script setup>
const props = defineProps({
    formSubmissions: Array,
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
        pending: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300",
        confirmed: "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300",
        rejected: "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300",
        error: "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300",
    };
    return classes[status] || "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300";
};

import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<template>
    <div class="rounded-xl bg-white shadow-sm dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr
                        class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400"
                    >
                        <th class="px-6 py-4">{{ $t("subscriber_card.forms.form_name") }}</th>
                        <th class="px-6 py-4">{{ $t("subscriber_card.forms.status") }}</th>
                        <th class="px-6 py-4">{{ $t("subscriber_card.forms.source") }}</th>
                        <th class="px-6 py-4">{{ $t("subscriber_card.forms.submitted_at") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="submission in formSubmissions"
                        :key="submission.id"
                        class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900 dark:text-white">
                                {{ submission.form_name || "-" }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                    getStatusClass(submission.status),
                                ]"
                            >
                                {{ submission.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                            <div v-if="submission.source">{{ submission.source }}</div>
                            <div
                                v-if="submission.referrer"
                                class="max-w-xs truncate text-xs text-slate-400"
                            >
                                {{ submission.referrer }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ formatDate(submission.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div
            v-if="formSubmissions.length === 0"
            class="py-12 text-center"
        >
            <div class="text-4xl">📝</div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ $t("subscriber_card.forms.empty") }}
            </p>
        </div>
    </div>
</template>
