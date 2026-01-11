<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";

// Tab Components
import SubscriberOverviewTab from "./Partials/SubscriberOverviewTab.vue";
import SubscriberMessageHistoryTab from "./Partials/SubscriberMessageHistoryTab.vue";
import SubscriberListHistoryTab from "./Partials/SubscriberListHistoryTab.vue";
import SubscriberPixelDataTab from "./Partials/SubscriberPixelDataTab.vue";
import SubscriberFormsTab from "./Partials/SubscriberFormsTab.vue";
import SubscriberActivityTab from "./Partials/SubscriberActivityTab.vue";

const props = defineProps({
    subscriber: Object,
    statistics: Object,
    listHistory: Array,
    messageHistory: Array,
    pixelData: Object,
    formSubmissions: Array,
    activityLog: Array,
    allTags: Array,
});

const activeTab = ref("overview");

const tabs = computed(() => [
    {
        id: "overview",
        label: t("subscriber_card.tabs.overview"),
        icon: "📊",
        badge: null,
    },
    {
        id: "messages",
        label: t("subscriber_card.tabs.messages"),
        icon: "📧",
        badge: props.statistics?.total_messages_sent || null,
    },
    {
        id: "lists",
        label: t("subscriber_card.tabs.lists"),
        icon: "📋",
        badge: props.listHistory?.length || null,
    },
    {
        id: "pixel",
        label: t("subscriber_card.tabs.pixel"),
        icon: "🔍",
        badge: props.pixelData?.total_events || null,
    },
    {
        id: "forms",
        label: t("subscriber_card.tabs.forms"),
        icon: "📝",
        badge: props.formSubmissions?.length || null,
    },
    {
        id: "activity",
        label: t("subscriber_card.tabs.activity"),
        icon: "📜",
        badge: props.activityLog?.length || null,
    },
]);

const subscriberName = computed(() => {
    if (props.subscriber.first_name || props.subscriber.last_name) {
        return `${props.subscriber.first_name || ""} ${props.subscriber.last_name || ""}`.trim();
    }
    return props.subscriber.email || props.subscriber.phone;
});

const subscriberInitials = computed(() => {
    if (props.subscriber.first_name && props.subscriber.last_name) {
        return (
            props.subscriber.first_name[0] + props.subscriber.last_name[0]
        ).toUpperCase();
    }
    if (props.subscriber.first_name) {
        return props.subscriber.first_name.substring(0, 2).toUpperCase();
    }
    if (props.subscriber.email) {
        return props.subscriber.email.substring(0, 2).toUpperCase();
    }
    return "??";
});

import { useI18n } from "vue-i18n";

const { t } = useI18n();

</script>

<template>
    <Head :title="`${$t('subscriber_card.title')}: ${subscriberName}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('subscribers.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
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
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                </Link>
                <div class="flex-1">
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("subscriber_card.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ subscriber.email || subscriber.phone }}
                    </p>
                </div>
                <Link
                    :href="route('subscribers.edit', subscriber.id)"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
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
                    {{ $t("common.edit") }}
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Quick Stats Row -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ statistics.total_messages_sent }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.messages_sent") }}
                    </div>
                </div>
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ statistics.open_rate }}%
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.open_rate") }}
                    </div>
                </div>
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ statistics.click_rate }}%
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.click_rate") }}
                    </div>
                </div>
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ statistics.engagement_score }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.engagement") }}
                    </div>
                </div>
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ statistics.active_lists_count }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.active_lists") }}
                    </div>
                </div>
                <div
                    class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
                >
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ statistics.devices_count }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("subscriber_card.stats.devices") }}
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div
                class="flex flex-wrap gap-2 border-b border-slate-200 pb-2 dark:border-slate-700"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all',
                        activeTab === tab.id
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                    ]"
                >
                    <span>{{ tab.icon }}</span>
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.badge"
                        class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </div>

            <!-- Tab Content -->
            <div class="min-h-[400px]">
                <SubscriberOverviewTab
                    v-if="activeTab === 'overview'"
                    :subscriber="subscriber"
                    :statistics="statistics"
                    :list-history="listHistory"
                    :all-tags="allTags"
                />

                <SubscriberMessageHistoryTab
                    v-else-if="activeTab === 'messages'"
                    :messages="messageHistory"
                />

                <SubscriberListHistoryTab
                    v-else-if="activeTab === 'lists'"
                    :list-history="listHistory"
                />

                <SubscriberPixelDataTab
                    v-else-if="activeTab === 'pixel'"
                    :pixel-data="pixelData"
                />

                <SubscriberFormsTab
                    v-else-if="activeTab === 'forms'"
                    :form-submissions="formSubmissions"
                />

                <SubscriberActivityTab
                    v-else-if="activeTab === 'activity'"
                    :activity-log="activityLog"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
