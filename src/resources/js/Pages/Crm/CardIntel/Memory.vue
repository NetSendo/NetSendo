<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ContextBadge from "@/Components/CardIntel/ContextBadge.vue";

const props = defineProps({
    records: Object,
    stats: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
};
</script>

<template>
    <Head :title="$t('crm.cardintel.memory.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('crm.cardintel.index')"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg
                        class="w-5 h-5 text-gray-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <div class="flex-1">
                    <h2
                        class="text-xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t("crm.cardintel.memory.header") }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{
                            $t("crm.cardintel.memory.stats", {
                                total: stats.total,
                                synced: stats.synced_to_crm,
                            })
                        }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Info Banner -->
                <div
                    class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900/20 dark:to-purple-900/20 rounded-xl p-4 mb-6 border border-violet-200 dark:border-violet-800"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="w-10 h-10 bg-violet-100 dark:bg-violet-900/50 rounded-lg flex items-center justify-center flex-shrink-0"
                        >
                            <svg
                                class="w-5 h-5 text-violet-600 dark:text-violet-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="font-medium text-violet-900 dark:text-violet-300"
                            >
                                {{
                                    $t("crm.cardintel.memory.info_banner.title")
                                }}
                            </h3>
                            <p
                                class="text-sm text-violet-700 dark:text-violet-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.memory.info_banner.description",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Records Grid -->
                <div
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                    <div
                        v-for="record in records.data"
                        :key="record.id"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-violet-300 dark:hover:border-violet-600 transition-colors"
                    >
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ record.merged_profile?.first_name }}
                                        {{ record.merged_profile?.last_name }}
                                    </h3>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            record.merged_profile?.company ||
                                            record.merged_profile?.email
                                        }}
                                    </p>
                                </div>
                                <ContextBadge
                                    v-if="record.latest_scan?.context"
                                    :level="
                                        record.latest_scan.context.context_level
                                    "
                                    :score="
                                        record.latest_scan.context.quality_score
                                    "
                                    size="sm"
                                />
                            </div>

                            <div class="space-y-2 text-sm">
                                <div
                                    v-if="record.merged_profile?.email"
                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <span class="truncate">{{
                                        record.merged_profile.email
                                    }}</span>
                                </div>
                                <div
                                    v-if="record.merged_profile?.phone"
                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                        />
                                    </svg>
                                    <span>{{
                                        record.merged_profile.phone
                                    }}</span>
                                </div>
                                <div
                                    v-if="record.merged_profile?.position"
                                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <span>{{
                                        record.merged_profile.position
                                    }}</span>
                                </div>
                            </div>

                            <div
                                class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="record.is_synced_to_crm"
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.memory.record.synced",
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.memory.record.memory_only",
                                            )
                                        }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ formatDate(record.updated_at) }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="px-4 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <Link
                                    v-if="record.latest_scan_id"
                                    :href="
                                        route(
                                            'crm.cardintel.show',
                                            record.latest_scan_id,
                                        )
                                    "
                                    class="text-sm text-violet-600 hover:text-violet-700 font-medium"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.memory.record.view_scan",
                                        )
                                    }}
                                    →
                                </Link>
                                <Link
                                    v-if="record.crm_contact_id"
                                    :href="
                                        route(
                                            'crm.contacts.show',
                                            record.crm_contact_id,
                                        )
                                    "
                                    class="text-sm text-gray-600 hover:text-gray-700"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.memory.record.open_crm",
                                        )
                                    }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!records.data?.length"
                    class="bg-white dark:bg-gray-800 rounded-xl p-12 text-center shadow-sm border border-gray-200 dark:border-gray-700"
                >
                    <div
                        class="w-16 h-16 mx-auto mb-4 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center"
                    >
                        <svg
                            class="w-8 h-8 text-gray-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                    </div>
                    <h3
                        class="text-lg font-medium text-gray-900 dark:text-white mb-2"
                    >
                        {{ $t("crm.cardintel.memory.empty.title") }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        {{ $t("crm.cardintel.memory.empty.description") }}
                    </p>
                    <Link
                        :href="route('crm.cardintel.index')"
                        class="inline-block px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700"
                    >
                        {{ $t("crm.cardintel.memory.empty.cta") }}
                    </Link>
                </div>

                <!-- Pagination -->
                <div
                    v-if="records.links?.length > 3"
                    class="mt-6 flex items-center justify-center gap-2"
                >
                    <Link
                        v-for="link in records.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm',
                            link.active
                                ? 'bg-violet-600 text-white'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
