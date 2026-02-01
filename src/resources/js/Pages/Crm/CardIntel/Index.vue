<script setup>
import { ref, computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";
import axios from "axios";

const props = defineProps({
    stats: Object,
    settings: Object,
    recentScans: Array,
});

const { t } = useI18n();

// Processing mode for current scan (initialized from settings)
const selectedMode = ref(props.settings?.default_mode || "manual");
const isSavingMode = ref(false);

// File upload state
const selectedFiles = ref([]);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadMode = ref("single"); // 'single' or 'batch'
const uploadError = ref(null);
const isDragging = ref(false);

// Handle file selection from input
const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    if (files.length === 0) return;

    uploadError.value = null;

    if (uploadMode.value === "single") {
        selectedFiles.value = [files[0]];
        // Auto-upload for single file mode
        uploadFiles();
    } else {
        selectedFiles.value = [...selectedFiles.value, ...files];
    }
};

// Handle drag and drop
const handleDragOver = (event) => {
    event.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const handleDrop = (event) => {
    event.preventDefault();
    isDragging.value = false;

    const files = Array.from(event.dataTransfer.files);
    if (files.length === 0) return;

    uploadError.value = null;

    if (uploadMode.value === "single") {
        selectedFiles.value = [files[0]];
        uploadFiles();
    } else {
        selectedFiles.value = [...selectedFiles.value, ...files];
    }
};

// Remove file from selection (batch mode)
const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

// Clear all selected files
const clearFiles = () => {
    selectedFiles.value = [];
    uploadError.value = null;
};

// Upload files to backend
const uploadFiles = async () => {
    if (selectedFiles.value.length === 0) return;

    isUploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = null;

    try {
        for (let i = 0; i < selectedFiles.value.length; i++) {
            const file = selectedFiles.value[i];
            const formData = new FormData();
            formData.append("file", file);
            formData.append("mode", selectedMode.value);

            const response = await axios.post(
                route("crm.cardintel.scan"),
                formData,
                {
                    headers: { "Content-Type": "multipart/form-data" },
                },
            );

            uploadProgress.value = Math.round(
                ((i + 1) / selectedFiles.value.length) * 100,
            );

            if (response.data.success && response.data.scan) {
                // For single file mode, redirect immediately to the scan details
                if (uploadMode.value === "single") {
                    router.visit(
                        route("crm.cardintel.show", response.data.scan.id),
                    );
                    return;
                }
            } else if (!response.data.success) {
                uploadError.value =
                    response.data.message ||
                    t("crm.cardintel.scan.upload.error");
            }
        }

        // For batch mode, redirect to queue after all uploads
        if (uploadMode.value === "batch") {
            router.visit(route("crm.cardintel.queue"));
        }
    } catch (error) {
        console.error("Upload error:", error);
        uploadError.value =
            error.response?.data?.message ||
            t("crm.cardintel.scan.upload.error");
    } finally {
        isUploading.value = false;
        selectedFiles.value = [];
    }
};

// Format file size for display
const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
};

const getModeDescription = (mode) => {
    switch (mode) {
        case "manual":
            return t("crm.cardintel.settings.mode.manual_desc");
        case "agent":
            return t("crm.cardintel.settings.mode.agent_desc");
        case "auto":
            return t("crm.cardintel.settings.mode.auto_desc");
        default:
            return "";
    }
};

// Update mode and save to backend
const updateMode = async (mode) => {
    selectedMode.value = mode;
    isSavingMode.value = true;

    try {
        await axios.post(route("crm.cardintel.settings.update"), {
            default_mode: mode,
        });
    } catch (error) {
        console.error("Failed to save mode:", error);
        // Revert on error
        selectedMode.value = props.settings?.default_mode || "manual";
    } finally {
        isSavingMode.value = false;
    }
};

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
                        class="px-3 py-1 text-xs font-medium rounded-full transition-colors"
                        :class="
                            selectedMode === 'auto'
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : selectedMode === 'agent'
                                  ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                                  : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        "
                    >
                        {{
                            selectedMode === "auto"
                                ? $t("crm.cardintel.scan.mode.auto")
                                : selectedMode === "agent"
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

                            <!-- Upload Mode Selection -->
                            <div class="mb-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.scan.upload_mode.label",
                                        )
                                    }}
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        @click="
                                            uploadMode = 'single';
                                            clearFiles();
                                        "
                                        class="relative flex flex-col items-center p-4 rounded-xl border-2 transition-all"
                                        :class="[
                                            uploadMode === 'single'
                                                ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500',
                                        ]"
                                    >
                                        <svg
                                            class="w-6 h-6 mb-2"
                                            :class="
                                                uploadMode === 'single'
                                                    ? 'text-violet-600'
                                                    : 'text-gray-400'
                                            "
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        <span
                                            class="text-sm font-medium"
                                            :class="
                                                uploadMode === 'single'
                                                    ? 'text-violet-700 dark:text-violet-400'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            "
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload_mode.single",
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="text-xs mt-1 text-center"
                                            :class="
                                                uploadMode === 'single'
                                                    ? 'text-violet-600 dark:text-violet-400'
                                                    : 'text-gray-500 dark:text-gray-400'
                                            "
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload_mode.single_desc",
                                                )
                                            }}
                                        </span>
                                        <div
                                            v-if="uploadMode === 'single'"
                                            class="absolute top-2 right-2 w-5 h-5 bg-violet-500 rounded-full flex items-center justify-center"
                                        >
                                            <svg
                                                class="w-3 h-3 text-white"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            uploadMode = 'batch';
                                            clearFiles();
                                        "
                                        class="relative flex flex-col items-center p-4 rounded-xl border-2 transition-all"
                                        :class="[
                                            uploadMode === 'batch'
                                                ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500',
                                        ]"
                                    >
                                        <svg
                                            class="w-6 h-6 mb-2"
                                            :class="
                                                uploadMode === 'batch'
                                                    ? 'text-violet-600'
                                                    : 'text-gray-400'
                                            "
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
                                        <span
                                            class="text-sm font-medium"
                                            :class="
                                                uploadMode === 'batch'
                                                    ? 'text-violet-700 dark:text-violet-400'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            "
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload_mode.batch",
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="text-xs mt-1 text-center"
                                            :class="
                                                uploadMode === 'batch'
                                                    ? 'text-violet-600 dark:text-violet-400'
                                                    : 'text-gray-500 dark:text-gray-400'
                                            "
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload_mode.batch_desc",
                                                )
                                            }}
                                        </span>
                                        <div
                                            v-if="uploadMode === 'batch'"
                                            class="absolute top-2 right-2 w-5 h-5 bg-violet-500 rounded-full flex items-center justify-center"
                                        >
                                            <svg
                                                class="w-3 h-3 text-white"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div
                                class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
                                :class="[
                                    isDragging
                                        ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                        : 'border-gray-300 dark:border-gray-600 hover:border-violet-400 dark:hover:border-violet-500',
                                    isUploading
                                        ? 'opacity-50 pointer-events-none'
                                        : '',
                                ]"
                                @dragover="handleDragOver"
                                @dragleave="handleDragLeave"
                                @drop="handleDrop"
                            >
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="hidden"
                                    id="file-upload"
                                    :multiple="uploadMode === 'batch'"
                                    @change="handleFileSelect"
                                />
                                <label
                                    for="file-upload"
                                    class="cursor-pointer block"
                                >
                                    <!-- Uploading State -->
                                    <div v-if="isUploading" class="space-y-4">
                                        <svg
                                            class="w-12 h-12 mx-auto text-violet-500 animate-spin"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            ></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                        <p
                                            class="text-gray-600 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload.uploading",
                                                )
                                            }}
                                        </p>
                                        <div
                                            class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2"
                                        >
                                            <div
                                                class="bg-violet-600 h-2 rounded-full transition-all"
                                                :style="{
                                                    width: uploadProgress + '%',
                                                }"
                                            ></div>
                                        </div>
                                        <p class="text-sm text-gray-500">
                                            {{ uploadProgress }}%
                                        </p>
                                    </div>

                                    <!-- Default Upload State -->
                                    <div v-else>
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
                                        <p
                                            class="text-gray-600 dark:text-gray-400"
                                        >
                                            <span
                                                class="text-violet-600 dark:text-violet-400 font-medium"
                                                >{{
                                                    $t(
                                                        "crm.cardintel.scan.upload.cta",
                                                    )
                                                }}</span
                                            >
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
                                    </div>
                                </label>
                            </div>

                            <!-- Error Message -->
                            <div
                                v-if="uploadError"
                                class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg"
                            >
                                <div class="flex items-center gap-2">
                                    <svg
                                        class="w-5 h-5 text-red-500"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <p
                                        class="text-red-700 dark:text-red-400 text-sm"
                                    >
                                        {{ uploadError }}
                                    </p>
                                </div>
                            </div>

                            <!-- Selected Files List (Batch Mode) -->
                            <div
                                v-if="
                                    uploadMode === 'batch' &&
                                    selectedFiles.length > 0
                                "
                                class="mt-6"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <h4
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.scan.upload.selected_files",
                                            )
                                        }}
                                        ({{ selectedFiles.length }})
                                    </h4>
                                    <button
                                        type="button"
                                        @click="clearFiles"
                                        class="text-sm text-red-600 hover:text-red-700 dark:text-red-400"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.scan.upload.clear_all",
                                            )
                                        }}
                                    </button>
                                </div>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    <div
                                        v-for="(file, index) in selectedFiles"
                                        :key="index"
                                        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                    >
                                        <div
                                            class="flex items-center gap-3 min-w-0"
                                        >
                                            <svg
                                                class="w-8 h-8 text-violet-500 flex-shrink-0"
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
                                            <div class="min-w-0">
                                                <p
                                                    class="text-sm font-medium text-gray-900 dark:text-white truncate"
                                                >
                                                    {{ file.name }}
                                                </p>
                                                <p
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{
                                                        formatFileSize(
                                                            file.size,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            @click="removeFile(index)"
                                            class="p-1 text-gray-400 hover:text-red-500 transition-colors"
                                        >
                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Upload Button for Batch Mode -->
                                <button
                                    type="button"
                                    @click="uploadFiles"
                                    :disabled="
                                        isUploading ||
                                        selectedFiles.length === 0
                                    "
                                    class="mt-4 w-full px-4 py-3 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                >
                                    <svg
                                        class="w-5 h-5"
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
                                    {{
                                        $t(
                                            "crm.cardintel.scan.upload.process_all",
                                        )
                                    }}
                                    ({{ selectedFiles.length }})
                                </button>
                            </div>

                            <!-- Instructions -->
                            <div
                                class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg"
                            >
                                <div class="flex gap-3">
                                    <svg
                                        class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <div
                                        class="text-sm text-blue-700 dark:text-blue-400"
                                    >
                                        <p class="font-medium mb-1">
                                            {{
                                                $t(
                                                    "crm.cardintel.scan.upload.instructions_title",
                                                )
                                            }}
                                        </p>
                                        <ul
                                            class="list-disc list-inside space-y-1 text-blue-600 dark:text-blue-300"
                                        >
                                            <li>
                                                {{
                                                    $t(
                                                        "crm.cardintel.scan.upload.instruction_1",
                                                    )
                                                }}
                                            </li>
                                            <li>
                                                {{
                                                    $t(
                                                        "crm.cardintel.scan.upload.instruction_2",
                                                    )
                                                }}
                                            </li>
                                            <li>
                                                {{
                                                    $t(
                                                        "crm.cardintel.scan.upload.instruction_3",
                                                    )
                                                }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Mode Selection -->
                            <div class="mt-6">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                                >
                                    {{ $t("crm.cardintel.scan.mode.label") }}
                                </label>
                                <div class="grid grid-cols-3 gap-3">
                                    <button
                                        v-for="mode in [
                                            'manual',
                                            'agent',
                                            'auto',
                                        ]"
                                        :key="mode"
                                        type="button"
                                        :disabled="isSavingMode"
                                        @click="updateMode(mode)"
                                        class="relative flex flex-col items-center p-4 rounded-xl border-2 transition-all"
                                        :class="[
                                            selectedMode === mode
                                                ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500',
                                            isSavingMode
                                                ? 'opacity-50 cursor-wait'
                                                : '',
                                        ]"
                                    >
                                        <span
                                            class="text-sm font-medium"
                                            :class="
                                                selectedMode === mode
                                                    ? 'text-violet-700 dark:text-violet-400'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            "
                                        >
                                            {{
                                                mode === "manual"
                                                    ? $t(
                                                          "crm.cardintel.scan.mode.manual",
                                                      )
                                                    : mode === "agent"
                                                      ? $t(
                                                            "crm.cardintel.scan.mode.agent",
                                                        )
                                                      : $t(
                                                            "crm.cardintel.scan.mode.auto",
                                                        )
                                            }}
                                        </span>
                                        <span
                                            class="text-xs mt-1 text-center"
                                            :class="
                                                selectedMode === mode
                                                    ? 'text-violet-600 dark:text-violet-400'
                                                    : 'text-gray-500 dark:text-gray-400'
                                            "
                                        >
                                            {{ getModeDescription(mode) }}
                                        </span>
                                        <div
                                            v-if="selectedMode === mode"
                                            class="absolute top-2 right-2 w-5 h-5 bg-violet-500 rounded-full flex items-center justify-center"
                                        >
                                            <svg
                                                class="w-3 h-3 text-white"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>
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
