<script setup>
import { ref, computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    stats: Object,
    settings: Object,
    recentScans: Array,
});

const tabs = [
    {
        id: "overview",
        label: "crm.cardintel.dashboard.tabs.overview",
        icon: "chart-bar",
    },
    { id: "scan", label: "crm.cardintel.dashboard.tabs.scan", icon: "camera" },
    {
        id: "queue",
        label: "crm.cardintel.dashboard.tabs.queue",
        icon: "list-bullet",
    },
    {
        id: "memory",
        label: "crm.cardintel.dashboard.tabs.memory",
        icon: "folder-open",
    },
    {
        id: "settings",
        label: "crm.cardintel.dashboard.tabs.settings",
        icon: "cog-6-tooth",
    },
];

const activeTab = ref("overview");

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
            return "bg-green-100 text-green-800";
        case "processing":
            return "bg-yellow-100 text-yellow-800";
        case "failed":
            return "bg-red-100 text-red-800";
        default:
            return "bg-gray-100 text-gray-800";
    }
};

const getStatusLabel = (status) => {
    if (["completed", "processing", "failed", "pending"].includes(status)) {
        return `crm.cardintel.status.${status}`;
    }
    return status;
};

const getContextColor = (level) => {
    switch (level) {
        case "HIGH":
            return "bg-green-500";
        case "MEDIUM":
            return "bg-yellow-500";
        case "LOW":
            return "bg-gray-400";
        default:
            return "bg-gray-300";
    }
};

const navigateTo = (tab) => {
    if (tab === "overview") {
        activeTab.value = "overview";
    } else if (tab === "scan") {
        activeTab.value = "scan";
    } else {
        router.visit(route(`crm.cardintel.${tab}`));
    }
};
</script>

<template>
    <Head :title="$t('crm.cardintel.dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg"
                    >
                        <svg
                            class="w-5 h-5 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2
                            class="text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            CardIntel Agent
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("crm.cardintel.dashboard.subtitle") }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 text-xs font-medium rounded-full"
                        :class="
                            settings.default_mode === 'auto'
                                ? 'bg-green-100 text-green-800'
                                : settings.default_mode === 'agent'
                                  ? 'bg-yellow-100 text-yellow-800'
                                  : 'bg-gray-100 text-gray-800'
                        "
                    >
                        {{
                            settings.default_mode === "auto"
                                ? $t("crm.cardintel.scan.mode.auto")
                                : settings.default_mode === "agent"
                                  ? $t("crm.cardintel.scan.mode.agent")
                                  : $t("crm.cardintel.scan.mode.manual")
                        }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Tab Navigation -->
                <div
                    class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 mb-6"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="navigateTo(tab.id)"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-all',
                            activeTab === tab.id
                                ? 'bg-white dark:bg-gray-700 text-violet-600 dark:text-violet-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white',
                        ]"
                    >
                        <span>{{ $t(tab.label) }}</span>
                    </button>
                </div>

                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'" class="space-y-6">
                    <!-- Stats Grid -->
                    <div
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
                    >
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.dashboard.stats.total_scans",
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-gray-900 dark:text-white mt-1"
                                    >
                                        {{ stats.total_scans }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-violet-100 dark:bg-violet-900/30 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6 text-violet-600 dark:text-violet-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.dashboard.stats.pending_review",
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1"
                                    >
                                        {{ stats.pending_review }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6 text-yellow-600 dark:text-yellow-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.dashboard.stats.memory",
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-gray-900 dark:text-white mt-1"
                                    >
                                        {{ stats.contacts_created }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6 text-blue-600 dark:text-blue-400"
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
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.dashboard.stats.in_crm",
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1"
                                    >
                                        {{ stats.synced_to_crm }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6 text-green-600 dark:text-green-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Scans -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.dashboard.recent_scans.title",
                                    )
                                }}
                            </h3>
                        </div>
                        <div
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <template
                                v-if="recentScans && recentScans.length > 0"
                            >
                                <Link
                                    v-for="scan in recentScans"
                                    :key="scan.id"
                                    :href="route('crm.cardintel.show', scan.id)"
                                    class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                >
                                    <div
                                        class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 flex-shrink-0"
                                    >
                                        <img
                                            v-if="scan.file_url"
                                            :src="scan.file_url"
                                            alt="Wizytówka"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="font-medium text-gray-900 dark:text-white truncate"
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
                                                        ?.first_name &&
                                                    !scan.extraction?.fields
                                                        ?.last_name
                                                "
                                                class="text-gray-400"
                                            >
                                                {{
                                                    $t(
                                                        "crm.cardintel.dashboard.recent_scans.scan_label",
                                                    )
                                                }}
                                                #{{ scan.id }}
                                            </span>
                                        </p>
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{
                                                scan.extraction?.fields
                                                    ?.company ||
                                                scan.extraction?.fields
                                                    ?.email ||
                                                $t(
                                                    "crm.cardintel.dashboard.recent_scans.no_data",
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="scan.context?.context_level"
                                            class="w-2 h-2 rounded-full"
                                            :class="
                                                getContextColor(
                                                    scan.context.context_level,
                                                )
                                            "
                                            :title="scan.context.context_level"
                                        ></div>
                                        <span
                                            class="px-2 py-1 text-xs font-medium rounded-full"
                                            :class="getStatusColor(scan.status)"
                                        >
                                            {{
                                                $t(getStatusLabel(scan.status))
                                            }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            {{ formatDate(scan.created_at) }}
                                        </span>
                                    </div>
                                </Link>
                            </template>
                            <div v-else class="px-6 py-12 text-center">
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
                                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                        />
                                    </svg>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{
                                        $t(
                                            "crm.cardintel.dashboard.recent_scans.empty",
                                        )
                                    }}
                                </p>
                                <button
                                    @click="activeTab = 'scan'"
                                    class="mt-4 px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.dashboard.recent_scans.scan_first",
                                        )
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scan Tab -->
                <div v-if="activeTab === 'scan'" class="space-y-6">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8"
                    >
                        <div class="max-w-xl mx-auto">
                            <div class="text-center mb-8">
                                <div
                                    class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-violet-100 to-purple-100 dark:from-violet-900/30 dark:to-purple-900/30 rounded-full flex items-center justify-center"
                                >
                                    <svg
                                        class="w-8 h-8 text-violet-600 dark:text-violet-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                </div>
                                <h3
                                    class="text-xl font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $t("crm.cardintel.scan.title") }}
                                </h3>
                                <p
                                    class="text-gray-500 dark:text-gray-400 mt-2"
                                >
                                    {{ $t("crm.cardintel.scan.description") }}
                                </p>
                            </div>

                            <!-- Upload Area -->
                            <div
                                class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center hover:border-violet-400 dark:hover:border-violet-500 transition-colors cursor-pointer"
                            >
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="hidden"
                                    id="file-upload"
                                />
                                <label for="file-upload" class="cursor-pointer">
                                    <svg
                                        class="w-12 h-12 mx-auto text-gray-400 mb-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                                        />
                                    </svg>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <span>{{
                                            $t("crm.cardintel.scan.upload.cta")
                                        }}</span>
                                        {{
                                            $t(
                                                "crm.cardintel.scan.upload.or_drag",
                                            )
                                        }}
                                    </p>
                                    <p class="text-sm text-gray-400 mt-2">
                                        {{
                                            $t(
                                                "crm.cardintel.scan.upload.formats",
                                            )
                                        }}
                                    </p>
                                </label>
                            </div>

                            <!-- Mode Selection -->
                            <div class="mt-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{ $t("crm.cardintel.scan.mode.label") }}
                                </label>
                                <div class="grid grid-cols-3 gap-3">
                                    <button
                                        class="px-4 py-3 rounded-lg border-2 border-violet-500 bg-violet-50 dark:bg-violet-900/20 text-violet-700 dark:text-violet-400 text-sm font-medium"
                                    >
                                        {{
                                            $t("crm.cardintel.scan.mode.manual")
                                        }}
                                    </button>
                                    <button
                                        class="px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 text-sm font-medium hover:border-gray-400"
                                    >
                                        {{
                                            $t("crm.cardintel.scan.mode.agent")
                                        }}
                                    </button>
                                    <button
                                        class="px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 text-sm font-medium hover:border-gray-400"
                                    >
                                        {{ $t("crm.cardintel.scan.mode.auto") }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
