<script setup>
import { ref, computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
});

// Status styling
const getStatusBadge = (status) => {
    const styles = {
        past: "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300",
        ongoing: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        future: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
        draft: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
        empty: "bg-gray-50 text-gray-400 dark:bg-gray-800 dark:text-gray-500",
    };
    return styles[status] || styles.empty;
};

const getStatusLabel = (status) => {
    return t(`campaign_stats.status.${status}`);
};

// Rate color based on performance
const getRateColor = (rate, type = 'open') => {
    const thresholds = type === 'open'
        ? { good: 25, ok: 15 }
        : { good: 3, ok: 1 };

    if (rate >= thresholds.good) return "text-emerald-600 dark:text-emerald-400";
    if (rate >= thresholds.ok) return "text-amber-600 dark:text-amber-400";
    return "text-red-500 dark:text-red-400";
};

const sortedTags = computed(() => {
    return [...props.tags].sort((a, b) => b.total_sent - a.total_sent);
});
</script>

<template>
    <Head :title="$t('campaign_stats.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $t("campaign_stats.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("campaign_stats.subtitle") }}
                    </p>
                </div>
                <span class="rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-3 py-1 text-xs font-semibold text-white shadow-lg">
                    {{ $t("campaign_stats.ai_badge") }}
                </span>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="tags.length === 0" class="rounded-2xl bg-white p-12 text-center shadow-xl dark:bg-gray-800">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-500">
                        <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $t("campaign_stats.no_tags") }}
                    </h3>
                    <p class="mb-6 text-gray-500 dark:text-gray-400">
                        {{ $t("campaign_stats.no_tags_description") }}
                    </p>
                    <Link :href="route('tags.index')" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:shadow-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        {{ $t("campaign_stats.manage_tags") }}
                    </Link>
                </div>

                <!-- Campaign List -->
                <div v-else class="space-y-4">
                    <div
                        v-for="tag in sortedTags"
                        :key="tag.id"
                        class="group overflow-hidden rounded-xl bg-white shadow-md transition-all hover:shadow-lg dark:bg-gray-800"
                    >
                        <div class="p-6">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <!-- Tag Info -->
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl shadow-sm"
                                        :style="{ backgroundColor: tag.color + '20', color: tag.color }"
                                    >
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                            {{ tag.name }}
                                        </h3>
                                        <div class="mt-1 flex items-center gap-3">
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ tag.messages_count }} {{ $t("campaign_stats.messages") }}
                                            </span>
                                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', getStatusBadge(tag.status)]">
                                                {{ getStatusLabel(tag.status) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="flex items-center gap-8">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.sent") }}</p>
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                                            {{ tag.total_sent.toLocaleString() }}
                                        </p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.open_rate") }}</p>
                                        <p :class="['text-xl font-bold', getRateColor(tag.open_rate, 'open')]">
                                            {{ tag.open_rate }}%
                                        </p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.click_rate") }}</p>
                                        <p :class="['text-xl font-bold', getRateColor(tag.click_rate, 'click')]">
                                            {{ tag.click_rate }}%
                                        </p>
                                    </div>

                                    <!-- Action -->
                                    <Link
                                        :href="route('campaign-stats.show', tag.id)"
                                        class="ml-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                                    >
                                        {{ $t("campaign_stats.view_details") }}
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>

                            <!-- Date Range -->
                            <div v-if="tag.date_range?.start" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $t("campaign_stats.date_range") }}: {{ tag.date_range.start }} — {{ tag.date_range.end }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
