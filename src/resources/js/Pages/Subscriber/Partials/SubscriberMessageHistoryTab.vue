<script setup>
const props = defineProps({
    messages: Array,
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
        sent: "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300",
        failed: "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300",
        skipped: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300",
        planned: "bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300",
        queued: "bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300",
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
                        <th class="px-6 py-4">
                            {{ $t("subscriber_card.messages.subject") }}
                        </th>
                        <th class="px-6 py-4">
                            {{ $t("subscriber_card.messages.type") }}
                        </th>
                        <th class="px-6 py-4">
                            {{ $t("subscriber_card.messages.status") }}
                        </th>
                        <th class="px-6 py-4">
                            {{ $t("subscriber_card.messages.sent_at") }}
                        </th>
                        <th class="px-6 py-4 text-center">
                            {{ $t("subscriber_card.messages.opens") }}
                        </th>
                        <th class="px-6 py-4 text-center">
                            {{ $t("subscriber_card.messages.clicks") }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="message in messages"
                        :key="message.id"
                        class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                        <td class="px-6 py-4">
                            <div class="max-w-xs truncate font-medium text-slate-900 dark:text-white">
                                {{ message.subject || "-" }}
                            </div>
                            <div
                                v-if="message.error_message"
                                class="mt-1 max-w-xs truncate text-xs text-red-500"
                            >
                                {{ message.error_message }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium',
                                    message.type === 'sms'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                                ]"
                            >
                                {{ message.type === "sms" ? "📱 SMS" : "📧 Email" }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="[
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                    getStatusClass(message.status),
                                ]"
                            >
                                {{ message.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ formatDate(message.sent_at || message.planned_at) }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                v-if="message.opens_count > 0"
                                class="inline-flex items-center gap-1 text-sm font-medium text-green-600 dark:text-green-400"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path
                                        fill-rule="evenodd"
                                        d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{ message.opens_count }}
                            </span>
                            <span v-else class="text-slate-300 dark:text-slate-600">
                                -
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                v-if="message.clicks_count > 0"
                                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 dark:text-blue-400"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        fill-rule="evenodd"
                                        d="M6.672 1.911a1 1 0 10-1.932.518l.259.966a1 1 0 001.932-.518l-.26-.966zM2.429 4.74a1 1 0 10-.517 1.932l.966.259a1 1 0 00.517-1.932l-.966-.26zm8.814-.569a1 1 0 00-1.415-1.414l-.707.707a1 1 0 101.414 1.415l.708-.708zm-7.071 7.072l.707-.707A1 1 0 003.465 9.12l-.708.707a1 1 0 001.415 1.415zm3.2-5.171a1 1 0 00-1.3 1.3l4 10a1 1 0 001.823.075l1.38-2.759 3.018 3.02a1 1 0 001.414-1.415l-3.019-3.02 2.76-1.379a1 1 0 00-.076-1.822l-10-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                {{ message.clicks_count }}
                            </span>
                            <span v-else class="text-slate-300 dark:text-slate-600">
                                -
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div
            v-if="messages.length === 0"
            class="py-12 text-center"
        >
            <div class="text-4xl">📭</div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ $t("subscriber_card.messages.empty") }}
            </p>
        </div>
    </div>
</template>
