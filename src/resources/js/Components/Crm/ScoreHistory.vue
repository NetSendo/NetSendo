<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const props = defineProps({
    contactId: {
        type: Number,
        required: true,
    },
    currentScore: {
        type: Number,
        default: 0,
    },
});

const loading = ref(true);
const history = ref([]);
const trend = ref("stable");

const loadHistory = async () => {
    try {
        loading.value = true;
        const response = await axios.get(`/crm/contacts/${props.contactId}/score-history`);
        history.value = response.data.history || [];
        trend.value = response.data.trend || "stable";
    } catch (error) {
        console.error("Error loading score history:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadHistory();
});

const getTrendIcon = () => {
    if (trend.value === "positive") return "M13 7h8m0 0v8m0-8l-8 8-4-4-6 6";
    if (trend.value === "negative") return "M13 17h8m0 0V9m0 8l-8-8-4 4-6-6";
    return "M5 12h14";
};

const getTrendClass = () => {
    if (trend.value === "positive") return "text-emerald-500";
    if (trend.value === "negative") return "text-red-500";
    return "text-slate-400";
};

const getPointsClass = (points) => {
    if (points > 0) return "text-emerald-600 dark:text-emerald-400";
    if (points < 0) return "text-red-600 dark:text-red-400";
    return "text-slate-600 dark:text-slate-400";
};

const formatTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return "teraz";
    if (diff < 3600000) return `${Math.floor(diff / 60000)} min temu`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h temu`;
    if (diff < 604800000) return `${Math.floor(diff / 86400000)}d temu`;

    return date.toLocaleDateString("pl-PL", { day: "2-digit", month: "2-digit" });
};
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                Historia Score
            </h2>
            <div class="flex items-center gap-2">
                <svg
                    class="h-5 w-5"
                    :class="getTrendClass()"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="getTrendIcon()"
                    />
                </svg>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ currentScore }}
                </span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <svg class="h-6 w-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- History List -->
        <div v-else-if="history.length > 0" class="space-y-3">
            <div
                v-for="item in history.slice(0, 10)"
                :key="item.id"
                class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3 dark:border-slate-700"
            >
                <div class="flex flex-1 items-center gap-3">
                    <!-- Points Badge -->
                    <span
                        class="rounded-full px-2.5 py-1 text-sm font-bold"
                        :class="getPointsClass(item.points_change)"
                    >
                        {{ item.formatted_points }}
                    </span>

                    <!-- Event Info -->
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ item.rule_name || item.event_label }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ item.score_before }} → {{ item.score_after }}
                        </p>
                    </div>
                </div>

                <!-- Time -->
                <span class="text-xs text-slate-400 dark:text-slate-500">
                    {{ formatTime(item.created_at) }}
                </span>
            </div>

            <!-- Show more link -->
            <div v-if="history.length > 10" class="pt-2 text-center">
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    + {{ history.length - 10 }} więcej wydarzeń
                </span>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-8 text-center">
            <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Brak historii punktacji
            </p>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                Score będzie aktualizowany automatycznie na podstawie aktywności
            </p>
        </div>
    </div>
</template>
