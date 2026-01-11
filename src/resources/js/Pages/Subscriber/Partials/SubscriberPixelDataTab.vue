<script setup>
import { ref  } from "vue";

const props = defineProps({
    pixelData: Object,
});

const activeSection = ref("visits");

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

import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4">
        <!-- Stats Summary -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900">
                <div class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ pixelData.total_events }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.total_events") }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ pixelData.total_page_views }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.page_views") }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {{ pixelData.custom_events?.length || 0 }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.custom_events") }}
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ pixelData.devices?.length || 0 }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.devices") }}
                </div>
            </div>
        </div>

        <!-- Section Tabs -->
        <div class="flex gap-2">
            <button
                @click="activeSection = 'visits'"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition-all',
                    activeSection === 'visits'
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                ]"
            >
                🌐 {{ $t("subscriber_card.pixel.visits_tab") }}
            </button>
            <button
                @click="activeSection = 'events'"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition-all',
                    activeSection === 'events'
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                ]"
            >
                ⚡ {{ $t("subscriber_card.pixel.events_tab") }}
            </button>
            <button
                @click="activeSection = 'devices'"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition-all',
                    activeSection === 'devices'
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800',
                ]"
            >
                💻 {{ $t("subscriber_card.pixel.devices_tab") }}
            </button>
        </div>

        <!-- Page Visits -->
        <div
            v-if="activeSection === 'visits'"
            class="rounded-xl bg-white shadow-sm dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.page_url") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.page_title") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.referrer") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.visited_at") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="visit in pixelData.page_visits"
                            :key="visit.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-6 py-4">
                                <a
                                    :href="visit.url"
                                    target="_blank"
                                    class="max-w-xs truncate text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    {{ visit.url }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ visit.title || "-" }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ visit.referrer || "-" }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ formatDate(visit.visited_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="pixelData.page_visits?.length === 0"
                class="py-12 text-center"
            >
                <div class="text-4xl">🌐</div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.no_visits") }}
                </p>
            </div>
        </div>

        <!-- Custom Events -->
        <div
            v-if="activeSection === 'events'"
            class="rounded-xl bg-white shadow-sm dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400"
                        >
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.event_type") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.event_category") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.event_data") }}</th>
                            <th class="px-6 py-4">{{ $t("subscriber_card.pixel.event_date") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="event in pixelData.custom_events"
                            :key="event.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/50 dark:text-purple-300"
                                >
                                    {{ event.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                {{ event.category || "-" }}
                            </td>
                            <td class="px-6 py-4">
                                <code
                                    v-if="event.data"
                                    class="max-w-xs truncate rounded bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800"
                                >
                                    {{ JSON.stringify(event.data).substring(0, 50) }}...
                                </code>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ formatDate(event.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="pixelData.custom_events?.length === 0"
                class="py-12 text-center"
            >
                <div class="text-4xl">⚡</div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.no_events") }}
                </p>
            </div>
        </div>

        <!-- Devices -->
        <div
            v-if="activeSection === 'devices'"
            class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900"
        >
            <div v-if="pixelData.devices?.length > 0" class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="device in pixelData.devices"
                    :key="device.id"
                    class="rounded-lg border border-slate-100 p-4 dark:border-slate-800"
                >
                    <div class="flex items-start gap-3">
                        <span
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-lg text-lg',
                                device.device_type === 'mobile'
                                    ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                                    : device.device_type === 'tablet'
                                      ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400'
                                      : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                        >
                            {{
                                device.device_type === "mobile"
                                    ? "📱"
                                    : device.device_type === "tablet"
                                      ? "📲"
                                      : "💻"
                            }}
                        </span>
                        <div class="flex-1">
                            <div class="font-medium text-slate-900 dark:text-white">
                                {{ device.device_label }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ device.os }} {{ device.os_version }}
                            </div>
                            <div class="mt-2 flex gap-4 text-xs text-slate-400">
                                <span>
                                    {{ $t("subscriber_card.pixel.first_seen") }}:
                                    {{ formatDate(device.first_seen_at) }}
                                </span>
                                <span>
                                    {{ $t("subscriber_card.pixel.last_seen") }}:
                                    {{ formatDate(device.last_seen_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="py-12 text-center"
            >
                <div class="text-4xl">💻</div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("subscriber_card.pixel.no_devices") }}
                </p>
            </div>
        </div>
    </div>
</template>
