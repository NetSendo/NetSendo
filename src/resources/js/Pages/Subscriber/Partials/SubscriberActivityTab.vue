<script setup>
const props = defineProps({
    activityLog: Array,
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

// Get action icon
const getActionIcon = (action) => {
    if (action.includes("created")) return "➕";
    if (action.includes("updated")) return "✏️";
    if (action.includes("deleted")) return "🗑️";
    if (action.includes("unsubscribed")) return "🚫";
    if (action.includes("imported")) return "📥";
    return "📋";
};

// Get action color
const getActionClass = (action) => {
    if (action.includes("created")) return "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300";
    if (action.includes("deleted")) return "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300";
    if (action.includes("unsubscribed")) return "bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300";
    return "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300";
};

import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<template>
    <div class="rounded-xl bg-white shadow-sm dark:bg-slate-900">
        <div v-if="activityLog.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800">
            <div
                v-for="log in activityLog"
                :key="log.id"
                class="flex items-start gap-4 p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"
            >
                <!-- Icon -->
                <span
                    :class="[
                        'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-lg',
                        getActionClass(log.action),
                    ]"
                >
                    {{ getActionIcon(log.action) }}
                </span>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-900 dark:text-white">
                            {{ log.action_name }}
                        </span>
                        <span
                            class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ log.action }}
                        </span>
                    </div>

                    <!-- Properties -->
                    <div
                        v-if="log.properties"
                        class="mt-2 rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-800"
                    >
                        <div
                            v-for="(value, key) in log.properties"
                            :key="key"
                            class="flex gap-2"
                        >
                            <span class="font-medium text-slate-600 dark:text-slate-400">
                                {{ key }}:
                            </span>
                            <span class="text-slate-900 dark:text-white">
                                {{ value }}
                            </span>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-400">
                        <span>{{ formatDate(log.created_at) }}</span>
                        <span v-if="log.ip_address">IP: {{ log.ip_address }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="py-12 text-center"
        >
            <div class="text-4xl">📜</div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ $t("subscriber_card.activity.empty") }}
            </p>
        </div>
    </div>
</template>
