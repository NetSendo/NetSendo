<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Head } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CodeBlock from "@/Components/Ui/CodeBlock.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatTiles from "@/Components/Ui/StatTiles.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import { useEcho, usePrivateChannel } from "@/Composables/useEcho";
import { useDateTime } from "@/Composables/useDateTime";

const props = defineProps({
    stats: Object,
    embedCode: String,
    userId: Number,
    baseUrl: String,
    liveVisitors: Array,
    reverbConfig: Object,
});

const { t } = useI18n();
const { locale } = useDateTime();

// Live Visitors State
const visitors = ref(props.liveVisitors || []);
const isEchoConnected = ref(false);

// Initialize Echo with Reverb config
const { isConnected, connectionError, getEcho } = useEcho(props.reverbConfig);

// Subscribe to pixel channel for real-time updates
onMounted(() => {
    // No WebSocket server configured: the list shows what the page loaded with
    if (!props.reverbConfig?.key) {
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
        days.push(date.toLocaleDateString(locale.value, { weekday: "short" }));
        counts.push(eventsByDay[dateStr] || 0);
    }

    return { days, counts };
});

const maxChartValue = computed(() => {
    return Math.max(...chartData.value.counts, 1);
});

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

// The four figures on top
const statTiles = computed(() => [
    { key: "page_views", label: t("pixel.page_views"), value: formatNumber(props.stats?.summary?.page_views) },
    { key: "product_views", label: t("pixel.product_views"), value: formatNumber(props.stats?.summary?.product_views) },
    { key: "add_to_cart", label: t("pixel.add_to_cart"), value: formatNumber(props.stats?.summary?.add_to_cart) },
    { key: "unique_visitors", label: t("pixel.unique_visitors"), value: formatNumber(props.stats?.summary?.unique_visitors) },
]);

// The guide to sending one's own events is long: folded until asked for
const docsExpanded = ref(false);

// The events the pixel understands
const eventTypes = computed(() => [
    { type: "page_view", label: t("pixel.event_type_page_view") },
    { type: "product_view", label: t("pixel.event_type_product_view") },
    { type: "add_to_cart", label: t("pixel.event_type_add_to_cart") },
    { type: "checkout_started", label: t("pixel.event_type_checkout") },
    { type: "purchase", label: t("pixel.event_type_purchase") },
    { type: "custom", label: t("pixel.event_type_custom") },
]);

// Available fields for tracking
const availableFields = computed(() => [
    { name: 'product_id', type: 'string', description: t('pixel.field_product_id') },
    { name: 'product_name', type: 'string', description: t('pixel.field_product_name') },
    { name: 'product_price', type: 'number', description: t('pixel.field_product_price') },
    { name: 'product_category', type: 'string', description: t('pixel.field_product_category') },
    { name: 'product_currency', type: 'string', description: t('pixel.field_product_currency') },
    { name: 'cart_value', type: 'number', description: t('pixel.field_cart_value') },
    { name: 'custom_data', type: 'object', description: t('pixel.field_custom_data') },
]);

// Code examples for copy functionality
const codeExamples = {
    product_view: `NetSendo.push(['track', 'product_view', {
  product_id: '123',
  product_name: 'Premium Course',
  product_price: 299.00,
  product_category: 'Courses'
}]);`,
    add_to_cart: `NetSendo.push(['track', 'add_to_cart', {
  product_id: '123',
  product_name: 'Premium Course',
  product_price: 299.00,
  cart_value: 598.00
}]);`,
    purchase: `NetSendo.push(['track', 'purchase', {
  product_id: 'order-456',
  cart_value: 598.00,
  product_currency: 'PLN'
}]);`,
    identify: `NetSendo.push(['identify', { email: 'user@example.com' }]);`,
    debug: `NetSendo.push(['debug']);`,
};

</script>

<template>
    <Head :title="t('pixel.settings_title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('pixel.settings_title')" :subtitle="t('pixel.settings_subtitle')">
                <StatusPill tone="success">{{ t("pixel.status_active") }}</StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <StatTiles :items="statTiles" :label="t('pixel.settings_title')" />

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <!-- Who is on the site right now -->
                <Panel flush>
                    <template #title>
                        {{ t("pixel.live_visitors") }}
                        <span class="text-sm font-normal tabular-nums text-slate-600 dark:text-slate-400">
                            {{ visitors.length }} {{ t("pixel.visitors_now") }}
                        </span>
                    </template>
                    <template v-if="isEchoConnected" #actions>
                        <StatusPill tone="success">Live</StatusPill>
                    </template>

                    <ul
                        v-if="visitors.length > 0"
                        class="mt-1 divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60"
                    >
                        <li
                            v-for="visitor in visitors.slice(0, 10)"
                            :key="visitor.visitor_token"
                            class="flex items-center justify-between gap-4 px-5 py-2.5 transition-colors"
                            :class="visitor.isNew ? 'bg-emerald-50 dark:bg-emerald-500/10' : ''"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm text-slate-900 dark:text-white">
                                    {{ visitor.page_title || getPagePath(visitor.page_url) }}
                                </p>
                                <p class="truncate font-mono text-xs text-slate-600 dark:text-slate-400">
                                    {{ getPagePath(visitor.page_url) }}
                                </p>
                            </div>
                            <p class="shrink-0 text-right text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                {{ [deviceLabels[visitor.device_type] || visitor.device_type, visitor.browser].filter(Boolean).join(" · ") }}
                                <span class="block">{{ formatTimeAgo(visitor.last_seen) }}</span>
                            </p>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="mt-1 border-t border-slate-200 px-5 py-8 text-center text-sm text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                    >
                        {{ t("pixel.no_visitors") }}
                    </p>
                </Panel>

                <!-- Events per day, the last week -->
                <Panel :title="t('pixel.events_last_7_days')">
                    <div class="flex h-44 items-end gap-2" role="img" :aria-label="t('pixel.events_last_7_days')">
                        <div
                            v-for="(count, index) in chartData.counts"
                            :key="index"
                            class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5"
                        >
                            <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ count }}</span>
                            <div
                                class="w-full max-w-10 rounded-t-md bg-indigo-600 dark:bg-indigo-400"
                                :style="{ height: `${(count / maxChartValue) * 100}%`, minHeight: count > 0 ? '4px' : '2px' }"
                            ></div>
                            <span class="text-xs text-slate-600 dark:text-slate-400">{{ chartData.days[index] }}</span>
                        </div>
                    </div>
                </Panel>
            </div>

            <!-- The snippet that makes all of the above work -->
            <Panel :title="t('pixel.embed_code')">
                <p class="-mt-3 mb-3 text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.embed_code_description") }}</p>
                <CodeBlock :code="embedCode || ''" />
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                    <strong class="font-semibold text-slate-900 dark:text-white">{{ t("pixel.tip") }}:</strong>
                    {{ t("pixel.embed_tip") }}
                </p>
            </Panel>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Panel :title="t('pixel.device_types')">
                    <ul v-if="totalDeviceTypeCount > 0" class="space-y-3">
                        <li v-for="(count, type) in stats?.device_types" :key="type">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-900 dark:text-white">{{ deviceLabels[type] || type }}</span>
                                <span class="tabular-nums text-slate-600 dark:text-slate-400">{{ count }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-indigo-600 dark:bg-indigo-400"
                                    :style="{ width: `${(count / totalDeviceTypeCount) * 100}%` }"
                                ></div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.no_data") }}</p>
                </Panel>

                <Panel :title="t('pixel.top_pages')">
                    <ul v-if="stats?.top_pages?.length" class="space-y-2">
                        <li
                            v-for="(topPage, index) in stats.top_pages.slice(0, 5)"
                            :key="index"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <span class="min-w-0 truncate text-slate-900 dark:text-white" :title="topPage.url">
                                {{ topPage.title || topPage.url }}
                            </span>
                            <span class="shrink-0 tabular-nums text-slate-600 dark:text-slate-400">{{ topPage.views }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.no_data") }}</p>
                </Panel>

                <Panel :title="t('pixel.top_products')">
                    <ul v-if="stats?.top_products?.length" class="space-y-2">
                        <li
                            v-for="(product, index) in stats.top_products.slice(0, 5)"
                            :key="index"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <span class="min-w-0 truncate text-slate-900 dark:text-white">
                                {{ product.product_name || product.product_id }}
                            </span>
                            <span class="shrink-0 tabular-nums text-slate-600 dark:text-slate-400">{{ product.views }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.no_data") }}</p>
                </Panel>
            </div>

            <!-- Sending one's own events: the guide -->
            <Panel :title="t('pixel.custom_events_title')">
                <template #actions>
                    <button
                        type="button"
                        :aria-expanded="docsExpanded"
                        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="docsExpanded = !docsExpanded"
                    >
                        {{ docsExpanded ? t("common.hide") : t("common.show") }}
                    </button>
                </template>
                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.custom_events_description") }}</p>

                <div v-show="docsExpanded" class="mt-5 flex flex-col gap-6">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t("pixel.event_types_title") }}</h3>
                        <dl class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
                            <div
                                v-for="eventType in eventTypes"
                                :key="eventType.type"
                                class="rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700/60"
                            >
                                <dt class="text-sm text-slate-900 dark:text-white">{{ eventType.label }}</dt>
                                <dd class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ eventType.type }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t("pixel.code_examples_title") }}</h3>
                        <div class="mt-2 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <div v-for="example in ['product_view', 'add_to_cart', 'purchase']" :key="example">
                                <p class="mb-1.5 text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.example_" + example) }}</p>
                                <CodeBlock :code="codeExamples[example]" />
                            </div>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <section>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t("pixel.identify_title") }}</h3>
                            <p class="mb-2 mt-1 text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.identify_description") }}</p>
                            <CodeBlock :code="codeExamples.identify" />
                        </section>
                        <section>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t("pixel.debug_title") }}</h3>
                            <p class="mb-2 mt-1 text-sm text-slate-600 dark:text-slate-400">{{ t("pixel.debug_description") }}</p>
                            <CodeBlock :code="codeExamples.debug" />
                        </section>
                    </div>

                    <section>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ t("pixel.available_fields_title") }}</h3>
                        <div class="relative mt-2 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700/60">
                            <table class="w-full min-w-[520px] text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                                    >
                                        <th scope="col" class="px-4 py-2.5 font-medium">{{ t("pixel.field_name") }}</th>
                                        <th scope="col" class="px-3 py-2.5 font-medium">{{ t("pixel.field_type") }}</th>
                                        <th scope="col" class="px-4 py-2.5 font-medium">{{ t("pixel.field_description") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="field in availableFields"
                                        :key="field.name"
                                        class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                                    >
                                        <td class="px-4 py-2 font-mono text-xs text-slate-900 dark:text-white">{{ field.name }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-slate-600 dark:text-slate-400">{{ field.type }}</td>
                                        <td class="px-4 py-2 text-slate-600 dark:text-slate-400">{{ field.description }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </Panel>
        </div>
    </AuthenticatedLayout>
</template>
