<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useEcho, usePrivateChannel } from "@/Composables/useEcho";

const props = defineProps({
    stats: Object,
    embedCode: String,
    userId: Number,
    baseUrl: String,
    liveVisitors: Array,
    reverbConfig: Object,
});

const { t } = useI18n();
const page = usePage();

// Live Visitors State
const visitors = ref(props.liveVisitors || []);
const isEchoConnected = ref(false);

// Initialize Echo with Reverb config
const { isConnected, connectionError, getEcho } = useEcho(props.reverbConfig);

// Subscribe to pixel channel for real-time updates
onMounted(() => {
    if (!props.reverbConfig?.key) {
        console.warn('[PixelSettings] No Reverb config, skipping WebSocket');
        return;
    }

    // Wait for Echo to connect, then subscribe
    const checkConnection = setInterval(() => {
        const echo = getEcho();
        if (echo) {
            clearInterval(checkConnection);
            isEchoConnected.value = true;

            // Subscribe to private channel
            echo.private(`pixel.${props.userId}`)
                .listen('.visitor.active', (event) => {
                    console.log('[PixelSettings] Visitor active:', event);
                    handleVisitorActive(event);
                });
        }
    }, 500);

    // Cleanup after 10 seconds if not connected
    setTimeout(() => clearInterval(checkConnection), 10000);
});

onUnmounted(() => {
    const echo = getEcho();
    if (echo) {
        echo.leave(`pixel.${props.userId}`);
    }
});

// Handle incoming visitor event
const handleVisitorActive = (event) => {
    const existingIndex = visitors.value.findIndex(
        (v) => v.visitor_token === event.visitor_token
    );

    const visitorData = {
        visitor_token: event.visitor_token,
        page_url: event.page_url,
        page_title: event.page_title,
        device_type: event.device_type,
        browser: event.browser,
        last_seen: event.timestamp,
        isNew: true, // For animation
    };

    if (existingIndex >= 0) {
        visitors.value[existingIndex] = visitorData;
    } else {
        visitors.value.unshift(visitorData);
    }

    // Remove "isNew" flag after animation
    setTimeout(() => {
        const idx = visitors.value.findIndex(
            (v) => v.visitor_token === event.visitor_token
        );
        if (idx >= 0) {
            visitors.value[idx].isNew = false;
        }
    }, 2000);
};

// Format time ago
const formatTimeAgo = (timestamp) => {
    if (!timestamp) return "";
    const seconds = Math.floor(Date.now() / 1000 - timestamp);
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    return `${Math.floor(seconds / 3600)}h`;
};

// Extract page path from URL
const getPagePath = (url) => {
    try {
        const urlObj = new URL(url);
        return urlObj.pathname || "/";
    } catch {
        return url;
    }
};

const copiedCode = ref(false);

const copyEmbedCode = () => {
    navigator.clipboard.writeText(props.embedCode);
    copiedCode.value = true;
    setTimeout(() => {
        copiedCode.value = false;
    }, 2000);
};

// Format large numbers
const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "K";
    return num?.toString() || "0";
};

// Prepare chart data
const chartData = computed(() => {
    const days = [];
    const counts = [];
    const eventsByDay = props.stats?.events_by_day || {};

    // Generate last 7 days
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split("T")[0];
        days.push(date.toLocaleDateString("pl-PL", { weekday: "short" }));
        counts.push(eventsByDay[dateStr] || 0);
    }

    return { days, counts };
});

const maxChartValue = computed(() => {
    return Math.max(...chartData.value.counts, 1);
});

// Device type colors
const deviceColors = {
    desktop: "bg-blue-500",
    mobile: "bg-green-500",
    tablet: "bg-purple-500",
};

const deviceLabels = {
    desktop: "Desktop",
    mobile: "Mobile",
    tablet: "Tablet",
};

const totalDeviceTypeCount = computed(() => {
    return Object.values(props.stats?.device_types || {}).reduce(
        (a, b) => a + b,
        0
    );
});
</script>

<template>
    <AuthenticatedLayout :title="t('pixel.settings_title')">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ t("pixel.settings_title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ t("pixel.settings_subtitle") }}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                    >
                        <span
                            class="w-2 h-2 mr-1 bg-green-500 rounded-full animate-pulse"
                        ></span>
                        {{ t("pixel.status_active") }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Live Visitors Panel -->
                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-lg shadow-lg overflow-hidden">
                    <div class="px-6 py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="relative">
                                        <div class="h-12 w-12 rounded-full bg-white/20 flex items-center justify-center">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </div>
                                        <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-green-400 animate-ping"></span>
                                        <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-green-400"></span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-white">{{ t("pixel.live_visitors") }}</h3>
                                    <p class="text-emerald-100">
                                        <span class="text-2xl font-bold">{{ visitors.length }}</span>
                                        <span class="ml-1 text-sm">{{ t("pixel.visitors_now") }}</span>
                                    </p>
                                </div>
                            </div>
                            <div v-if="isEchoConnected" class="flex items-center text-emerald-100 text-sm">
                                <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                                Live
                            </div>
                        </div>

                        <!-- Visitors List -->
                        <div v-if="visitors.length > 0" class="mt-4 space-y-2 max-h-48 overflow-y-auto">
                            <div
                                v-for="visitor in visitors.slice(0, 10)"
                                :key="visitor.visitor_token"
                                class="flex items-center justify-between bg-white/10 rounded-lg px-3 py-2 transition-all duration-300"
                                :class="{ 'ring-2 ring-white/50 scale-[1.02]': visitor.isNew }"
                            >
                                <div class="flex items-center space-x-3">
                                    <!-- Device Icon -->
                                    <div class="flex-shrink-0">
                                        <svg v-if="visitor.device_type === 'mobile'" class="h-5 w-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else-if="visitor.device_type === 'tablet'" class="h-5 w-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="h-5 w-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <!-- Page Info -->
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-white truncate max-w-[200px]">
                                            {{ visitor.page_title || getPagePath(visitor.page_url) }}
                                        </p>
                                        <p class="text-xs text-emerald-200 truncate max-w-[200px]">
                                            {{ getPagePath(visitor.page_url) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2 text-xs text-emerald-200">
                                    <span v-if="visitor.browser" class="hidden sm:inline">{{ visitor.browser }}</span>
                                    <span>{{ formatTimeAgo(visitor.last_seen) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- No Visitors -->
                        <div v-else class="mt-4 text-center py-4">
                            <p class="text-emerald-100 text-sm">{{ t("pixel.no_visitors") }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Overview -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <!-- Page Views -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-6 w-6 text-blue-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt
                                            class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{ t("pixel.page_views") }}
                                        </dt>
                                        <dd
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatNumber(
                                                    stats?.summary?.page_views
                                                )
                                            }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Views -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-6 w-6 text-purple-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt
                                            class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{ t("pixel.product_views") }}
                                        </dt>
                                        <dd
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatNumber(
                                                    stats?.summary
                                                        ?.product_views
                                                )
                                            }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add to Cart -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-6 w-6 text-green-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt
                                            class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{ t("pixel.add_to_cart") }}
                                        </dt>
                                        <dd
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatNumber(
                                                    stats?.summary?.add_to_cart
                                                )
                                            }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unique Visitors -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-6 w-6 text-indigo-500"
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
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt
                                            class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{ t("pixel.unique_visitors") }}
                                        </dt>
                                        <dd
                                            class="text-lg font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatNumber(
                                                    stats?.summary
                                                        ?.unique_visitors
                                                )
                                            }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Embed Code Card -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="px-4 py-5 sm:p-6">
                            <h3
                                class="text-lg font-medium leading-6 text-gray-900 dark:text-white flex items-center"
                            >
                                <svg
                                    class="h-5 w-5 mr-2 text-indigo-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                                    />
                                </svg>
                                {{ t("pixel.embed_code") }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t("pixel.embed_code_description") }}
                            </p>

                            <div class="mt-4">
                                <div class="relative">
                                    <pre
                                        class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto font-mono"
                                    ><code>{{ embedCode }}</code></pre>
                                    <button
                                        @click="copyEmbedCode"
                                        class="absolute top-2 right-2 p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-white transition-colors"
                                    >
                                        <svg
                                            v-if="!copiedCode"
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4 text-green-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div
                                class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg"
                            >
                                <p
                                    class="text-xs text-blue-700 dark:text-blue-300"
                                >
                                    <strong>{{ t("pixel.tip") }}:</strong>
                                    {{ t("pixel.embed_tip") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Chart -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="px-4 py-5 sm:p-6">
                            <h3
                                class="text-lg font-medium leading-6 text-gray-900 dark:text-white flex items-center"
                            >
                                <svg
                                    class="h-5 w-5 mr-2 text-green-500"
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
                                {{ t("pixel.events_last_7_days") }}
                            </h3>

                            <!-- Simple bar chart -->
                            <div
                                class="mt-4 flex items-end justify-between h-32 space-x-2"
                            >
                                <div
                                    v-for="(count, index) in chartData.counts"
                                    :key="index"
                                    class="flex-1 flex flex-col items-center"
                                >
                                    <div
                                        class="w-full bg-indigo-500 rounded-t transition-all duration-300"
                                        :style="{
                                            height: `${
                                                (count / maxChartValue) * 100
                                            }%`,
                                            minHeight:
                                                count > 0 ? '4px' : '2px',
                                        }"
                                    ></div>
                                    <span
                                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                        >{{ chartData.days[index] }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Device Types -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="px-4 py-5 sm:p-6">
                            <h3
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                {{ t("pixel.device_types") }}
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(count, type) in stats?.device_types"
                                    :key="type"
                                    class="flex items-center"
                                >
                                    <div
                                        class="w-24 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ deviceLabels[type] || type }}
                                    </div>
                                    <div class="flex-1 mx-3">
                                        <div
                                            class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden"
                                        >
                                            <div
                                                :class="deviceColors[type]"
                                                class="h-full rounded-full transition-all duration-300"
                                                :style="{
                                                    width: `${
                                                        (count /
                                                            totalDeviceTypeCount) *
                                                        100
                                                    }%`,
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                    <div
                                        class="w-12 text-right text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ count }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Pages -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="px-4 py-5 sm:p-6">
                            <h3
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                {{ t("pixel.top_pages") }}
                            </h3>
                            <div class="space-y-2">
                                <div
                                    v-for="(
                                        page, index
                                    ) in stats?.top_pages?.slice(0, 5)"
                                    :key="index"
                                    class="flex items-center justify-between py-1"
                                >
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400 truncate max-w-[200px]"
                                        :title="page.url"
                                    >
                                        {{ page.title || page.url }}
                                    </span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white ml-2"
                                    >
                                        {{ page.views }}
                                    </span>
                                </div>
                                <div
                                    v-if="!stats?.top_pages?.length"
                                    class="text-sm text-gray-500 dark:text-gray-400 text-center py-4"
                                >
                                    {{ t("pixel.no_data") }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg"
                    >
                        <div class="px-4 py-5 sm:p-6">
                            <h3
                                class="text-sm font-medium text-gray-900 dark:text-white mb-4"
                            >
                                {{ t("pixel.top_products") }}
                            </h3>
                            <div class="space-y-2">
                                <div
                                    v-for="(
                                        product, index
                                    ) in stats?.top_products?.slice(0, 5)"
                                    :key="index"
                                    class="flex items-center justify-between py-1"
                                >
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400 truncate max-w-[200px]"
                                    >
                                        {{
                                            product.product_name ||
                                            product.product_id
                                        }}
                                    </span>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white ml-2"
                                    >
                                        {{ product.views }}
                                    </span>
                                </div>
                                <div
                                    v-if="!stats?.top_products?.length"
                                    class="text-sm text-gray-500 dark:text-gray-400 text-center py-4"
                                >
                                    {{ t("pixel.no_data") }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Events Help -->
                <div
                    class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-lg overflow-hidden"
                >
                    <div class="px-6 py-8 sm:px-8 sm:py-10 text-white">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg
                                    class="h-8 w-8 text-indigo-200"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium">
                                    {{ t("pixel.custom_events_title") }}
                                </h3>
                                <p class="mt-1 text-sm text-indigo-100">
                                    {{ t("pixel.custom_events_description") }}
                                </p>
                                <pre
                                    class="mt-4 p-3 bg-black/20 rounded-lg text-xs text-indigo-100 overflow-x-auto"
                                ><code>NetSendo.push(['track', 'product_view', {
  product_id: '123',
  product_name: 'Premium Course',
  product_price: 299.00
}]);</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
