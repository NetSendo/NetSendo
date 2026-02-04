<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ContextBadge from "@/Components/CardIntel/ContextBadge.vue";

const props = defineProps({
    scans: Object,
    stats: Object,
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("pl-PL", {
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const getStatusColor = (status) => {
    switch (status) {
        case "completed":
            return "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400";
        case "processing":
            return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400";
        case "failed":
            return "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400";
        default:
            return "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400";
    }
};

const getStatusLabel = (status) => {
    if (["completed", "processing", "failed", "pending"].includes(status)) {
        return `crm.cardintel.status.${status}`;
    }
    return status;
};

const getModeLabel = (mode) => {
    if (["manual", "agent", "auto"].includes(mode)) {
        return `crm.cardintel.scan.mode.${mode}`;
    }
    return mode;
};
</script>

<template>
    <Head :title="`${$t('crm.cardintel.queue.title')} - CardIntel`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('crm.cardintel.index')"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors flex-shrink-0"
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
                    <div class="flex-1 min-w-0">
                        <h2
                            class="text-xl font-semibold text-gray-900 dark:text-white truncate"
                        >
                            {{ $t("crm.cardintel.queue.title") }}
                        </h2>
                        <p class="text-sm text-gray-500 truncate">
                            {{
                                $t("crm.cardintel.queue.subtitle", {
                                    count: stats.pending_review,
                                })
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats Bar -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.queue.stats.all") }}
                        </p>
                        <p
                            class="text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ stats.total_scans }}
                        </p>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.queue.stats.pending") }}
                        </p>
                        <p class="text-2xl font-bold text-yellow-600">
                            {{ stats.pending_review }}
                        </p>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.queue.stats.completed") }}
                        </p>
                        <p class="text-2xl font-bold text-green-600">
                            {{ stats.completed }}
                        </p>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.queue.stats.failed") }}
                        </p>
                        <p class="text-2xl font-bold text-red-600">
                            {{ stats.failed }}
                        </p>
                    </div>
                </div>

                <!-- Scans Table -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <!-- Desktop Table -->
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 hidden md:table"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("crm.cardintel.queue.table.card") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("crm.cardintel.queue.table.data") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{
                                        $t("crm.cardintel.queue.table.context")
                                    }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("crm.cardintel.queue.table.mode") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("crm.cardintel.queue.table.status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("crm.cardintel.queue.table.date") }}
                                </th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="scan in scans.data"
                                :key="scan.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="w-16 h-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700"
                                    >
                                        <img
                                            v-if="scan.file_url"
                                            :src="scan.file_url"
                                            alt=""
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            scan.extraction?.fields?.first_name
                                        }}
                                        {{ scan.extraction?.fields?.last_name }}
                                        <span
                                            v-if="
                                                !scan.extraction?.fields
                                                    ?.first_name
                                            "
                                            class="text-gray-400"
                                            >#{{ scan.id }}</span
                                        >
                                    </div>
                                    <div
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            scan.extraction?.fields?.company ||
                                            scan.extraction?.fields?.email ||
                                            "—"
                                        }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <ContextBadge
                                        v-if="scan.context"
                                        :level="scan.context.context_level"
                                        :score="scan.context.quality_score"
                                        size="sm"
                                    />
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ $t(getModeLabel(scan.mode)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="getStatusColor(scan.status)"
                                    >
                                        {{ $t(getStatusLabel(scan.status)) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatDate(scan.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="
                                            route('crm.cardintel.show', scan.id)
                                        "
                                        class="text-violet-600 hover:text-violet-700 text-sm font-medium"
                                    >
                                        {{
                                            $t("crm.cardintel.queue.table.view")
                                        }}
                                        →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile List View -->
                    <div class="md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="scan in scans.data"
                            :key="scan.id"
                            class="p-4 flex flex-col gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 flex-shrink-0"
                                >
                                    <img
                                        v-if="scan.file_url"
                                        :src="scan.file_url"
                                        alt=""
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{
                                                    scan.extraction?.fields
                                                        ?.first_name
                                                }}
                                                {{
                                                    scan.extraction?.fields
                                                        ?.last_name
                                                }}
                                                <span
                                                    v-if="
                                                        !scan.extraction?.fields
                                                            ?.first_name
                                                    "
                                                    class="text-gray-400"
                                                    >#{{ scan.id }}</span
                                                >
                                            </div>
                                            <div
                                                class="text-sm text-gray-500 dark:text-gray-400 truncate"
                                            >
                                                {{
                                                    scan.extraction?.fields
                                                        ?.company ||
                                                    scan.extraction?.fields
                                                        ?.email ||
                                                    "—"
                                                }}
                                            </div>
                                        </div>
                                        <ContextBadge
                                            v-if="scan.context"
                                            :level="scan.context.context_level"
                                            :score="scan.context.quality_score"
                                            size="sm"
                                        />
                                    </div>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="px-2 py-1 text-xs font-medium rounded-full"
                                                :class="getStatusColor(scan.status)"
                                            >
                                                {{ $t(getStatusLabel(scan.status)) }}
                                            </span>
                                            <span
                                                class="text-xs text-gray-400"
                                            >
                                                {{ formatDate(scan.created_at) }}
                                            </span>
                                        </div>
                                        <Link
                                            :href="route('crm.cardintel.show', scan.id)"
                                            class="text-violet-600 hover:text-violet-700 text-sm font-medium"
                                        >
                                            {{ $t("crm.cardintel.queue.table.view") }} →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="!scans.data?.length"
                        class="px-6 py-12 text-center"
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
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.queue.empty.title") }}
                        </p>
                        <Link
                            :href="route('crm.cardintel.index')"
                            class="mt-4 inline-block px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700"
                        >
                            {{ $t("crm.cardintel.queue.empty.cta") }}
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="scans.links?.length > 3"
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{
                                $t("crm.cardintel.queue.pagination", {
                                    from: scans.from,
                                    to: scans.to,
                                    total: scans.total,
                                })
                            }}
                        </p>
                        <div class="flex gap-2">
                            <Link
                                v-for="link in scans.links"
                                :key="link.label"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded text-sm',
                                    link.active
                                        ? 'bg-violet-600 text-white'
                                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
