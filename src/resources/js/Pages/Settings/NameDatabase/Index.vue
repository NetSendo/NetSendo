<script setup>
import { ref, computed, watch } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const { t } = useI18n();

const props = defineProps({
    names: Object,
    stats: Object,
    countries: Object,
    filters: Object,
});

// Filters
const search = ref(props.filters?.search || "");
const countryFilter = ref(props.filters?.country || "");
const genderFilter = ref(props.filters?.gender || "");
const sourceFilter = ref(props.filters?.source || "");

// Modal states
const showAddModal = ref(false);
const showImportModal = ref(false);
const showDeleteModal = ref(false);
const editingName = ref(null);
const deletingName = ref(null);

// Form for adding/editing names
const form = useForm({
    name: "",
    vocative: "",
    gender: "male",
    country: "PL",
});

// Import form
const importForm = useForm({
    file: null,
    country: "PL",
});

// Apply filters
const applyFilters = () => {
    router.get(
        route("settings.names.index"),
        {
            search: search.value || undefined,
            country: countryFilter.value || undefined,
            gender: genderFilter.value || undefined,
            source: sourceFilter.value || undefined,
        },
        { preserveState: true }
    );
};

// Debounced search
let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

// Open add modal
const openAddModal = () => {
    editingName.value = null;
    form.reset();
    form.country = countryFilter.value || "PL";
    showAddModal.value = true;
};

// Open edit modal
const openEditModal = (name) => {
    editingName.value = name;
    form.name = name.name;
    form.vocative = name.vocative || "";
    form.gender = name.gender;
    form.country = name.country;
    showAddModal.value = true;
};

// Submit form
const submitForm = () => {
    if (editingName.value) {
        form.put(route("settings.names.update", editingName.value.id), {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route("settings.names.store"), {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
            },
        });
    }
};

// Confirm delete
const confirmDelete = (name) => {
    deletingName.value = name;
    showDeleteModal.value = true;
};

// Delete name
const deleteName = () => {
    router.delete(route("settings.names.destroy", deletingName.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingName.value = null;
        },
    });
};

// Handle file import
const handleFileChange = (event) => {
    importForm.file = event.target.files[0];
};

const submitImport = () => {
    importForm.post(route("settings.names.import"), {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
};

// Export names
const exportNames = (source = "all") => {
    window.open(
        route("settings.names.export", {
            source,
            country: countryFilter.value || undefined,
        }),
        "_blank"
    );
};

// Gender label
const genderLabel = (gender) => {
    const labels = {
        male: t("names.gender_male"),
        female: t("names.gender_female"),
        neutral: t("names.gender_neutral"),
    };
    return labels[gender] || gender;
};

// Gender badge class
const genderBadgeClass = (gender) => {
    const classes = {
        male: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
        female: "bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300",
        neutral: "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300",
    };
    return classes[gender] || classes.neutral;
};

// ========== Gender Matching Section ==========
const showGenderMatchingSection = ref(false);
const genderMatchingStats = ref(null);
const genderMatchingLoading = ref(false);
const genderMatchingRunning = ref(false);
const genderMatchingProgress = ref(null);
const genderMatchingResults = ref(null);
const showMatchingResultsModal = ref(false);
const showMatchingPreviewModal = ref(false);
const matchingCountry = ref("PL");

// Fetch gender matching stats
const fetchGenderMatchingStats = async () => {
    genderMatchingLoading.value = true;
    try {
        const response = await fetch(
            route("settings.names.gender-matching.stats", { country: matchingCountry.value })
        );
        const data = await response.json();
        genderMatchingStats.value = data;

        // Check if there's a running job
        if (data.job_progress && data.job_progress.status === 'running') {
            genderMatchingRunning.value = true;
            genderMatchingProgress.value = data.job_progress;
            pollProgress();
        }
    } catch (error) {
        console.error("Failed to fetch gender matching stats:", error);
    } finally {
        genderMatchingLoading.value = false;
    }
};

// Show gender matching section and load stats
const toggleGenderMatchingSection = () => {
    showGenderMatchingSection.value = !showGenderMatchingSection.value;
    if (showGenderMatchingSection.value && !genderMatchingStats.value) {
        fetchGenderMatchingStats();
    }
};

// Run gender matching
const runGenderMatching = async (async = true) => {
    genderMatchingRunning.value = true;
    genderMatchingProgress.value = null;
    genderMatchingResults.value = null;

    try {
        const response = await fetch(route("settings.names.gender-matching.run"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                country: matchingCountry.value,
                async: async,
            }),
        });

        const data = await response.json();

        if (async && data.status === "queued") {
            // Start polling for progress
            pollProgress();
        } else if (!async && data.results) {
            // Synchronous result
            genderMatchingResults.value = data.results;
            showMatchingResultsModal.value = true;
            genderMatchingRunning.value = false;
            fetchGenderMatchingStats();
        }
    } catch (error) {
        console.error("Failed to run gender matching:", error);
        genderMatchingRunning.value = false;
    }
};

// Poll for job progress
let progressInterval = null;
const pollProgress = () => {
    if (progressInterval) clearInterval(progressInterval);

    progressInterval = setInterval(async () => {
        try {
            const response = await fetch(route("settings.names.gender-matching.progress"));
            const data = await response.json();

            if (data.status === "no_job") {
                clearInterval(progressInterval);
                genderMatchingRunning.value = false;
                return;
            }

            genderMatchingProgress.value = data;

            if (data.status === "completed") {
                clearInterval(progressInterval);
                genderMatchingRunning.value = false;
                genderMatchingResults.value = data;
                showMatchingResultsModal.value = true;
                fetchGenderMatchingStats();
            } else if (data.status === "failed") {
                clearInterval(progressInterval);
                genderMatchingRunning.value = false;
            }
        } catch (error) {
            console.error("Failed to fetch progress:", error);
        }
    }, 1500);
};

// Clear progress cache
const clearProgress = async () => {
    try {
        await fetch(route("settings.names.gender-matching.clear"), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        genderMatchingProgress.value = null;
        genderMatchingResults.value = null;
    } catch (error) {
        console.error("Failed to clear progress:", error);
    }
};
</script>

<template>
    <Head :title="t('names.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ t("names.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t("names.subtitle") }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="showImportModal = true"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {{ t("names.import") }}
                    </button>
                    <button
                        @click="exportNames('all')"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ t("names.export") }}
                    </button>
                    <button
                        @click="openAddModal"
                        class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2 text-sm font-medium text-white shadow-lg transition hover:from-emerald-600 hover:to-teal-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ t("names.add_name") }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Help Section -->
            <div class="rounded-xl bg-gradient-to-r from-blue-500/10 to-purple-500/10 p-6 dark:from-blue-500/5 dark:to-purple-500/5">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ t("names.help_title") }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                            {{ t("names.help_desc") }}
                        </p>
                        <div class="mt-4 rounded-lg bg-slate-900/5 p-4 dark:bg-slate-800/50">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ t("names.syntax_title") }}:</p>
                            <code class="mt-2 block text-sm text-emerald-600 dark:text-emerald-400">
                                <span v-pre>{{chciałbym|chciałabym}}</span> → {{ t("names.syntax_result") }}
                            </code>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ t("names.syntax_desc") }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ stats?.total?.toLocaleString() || 0 }}
                    </div>
                    <div class="text-sm text-slate-500">{{ t("names.stats_total") }}</div>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ stats?.system?.toLocaleString() || 0 }}
                    </div>
                    <div class="text-sm text-slate-500">{{ t("names.stats_system") }}</div>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ stats?.user?.toLocaleString() || 0 }}
                    </div>
                    <div class="text-sm text-slate-500">{{ t("names.stats_user") }}</div>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Object.keys(stats?.by_country || {}).length }}
                    </div>
                    <div class="text-sm text-slate-500">{{ t("names.stats_countries") }}</div>
                </div>
            </div>

            <!-- Gender Matching Section -->
            <div class="rounded-xl bg-gradient-to-r from-purple-500/10 to-pink-500/10 p-6 shadow-sm dark:from-purple-500/5 dark:to-pink-500/5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-500">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                {{ t("names.gender_matching.title") }}
                            </h3>
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                {{ t("names.gender_matching.description") }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="toggleGenderMatchingSection"
                        class="inline-flex items-center gap-2 rounded-lg bg-purple-500 px-4 py-2 text-sm font-medium text-white shadow transition hover:bg-purple-600"
                    >
                        <svg v-if="!showGenderMatchingSection" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                        {{ showGenderMatchingSection ? t("common.hide") : t("common.show") }}
                    </button>
                </div>

                <!-- Expanded section -->
                <div v-if="showGenderMatchingSection" class="mt-6 space-y-4">
                    <!-- Loading state -->
                    <div v-if="genderMatchingLoading" class="flex items-center justify-center py-8">
                        <svg class="h-8 w-8 animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <!-- Stats loaded -->
                    <template v-else-if="genderMatchingStats">
                        <!-- Country selector -->
                        <div class="flex items-center gap-4">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.gender_matching.country_label") }}:
                            </label>
                            <select
                                v-model="matchingCountry"
                                @change="fetchGenderMatchingStats"
                                class="rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-purple-500 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option v-for="(label, code) in countries" :key="code" :value="code">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Stats cards -->
                        <div class="grid gap-4 sm:grid-cols-4">
                            <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-slate-800">
                                <div class="text-2xl font-bold text-slate-900 dark:text-white">
                                    {{ genderMatchingStats.subscriber_stats?.total?.toLocaleString() || 0 }}
                                </div>
                                <div class="text-sm text-slate-500">{{ t("names.gender_matching.stat_total") }}</div>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-slate-800">
                                <div class="text-2xl font-bold text-amber-600">
                                    {{ genderMatchingStats.preview?.total_without_gender?.toLocaleString() || 0 }}
                                </div>
                                <div class="text-sm text-slate-500">{{ t("names.gender_matching.stat_without_gender") }}</div>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-slate-800">
                                <div class="text-2xl font-bold text-emerald-600">
                                    {{ genderMatchingStats.preview?.matchable_count?.toLocaleString() || 0 }}
                                </div>
                                <div class="text-sm text-slate-500">{{ t("names.gender_matching.stat_matchable") }}</div>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm dark:bg-slate-800">
                                <div class="text-2xl font-bold text-red-600">
                                    {{ genderMatchingStats.preview?.unmatchable_count?.toLocaleString() || 0 }}
                                </div>
                                <div class="text-sm text-slate-500">{{ t("names.gender_matching.stat_unmatchable") }}</div>
                            </div>
                        </div>

                        <!-- By gender breakdown -->
                        <div v-if="genderMatchingStats.preview?.by_gender" class="flex items-center gap-4 text-sm">
                            <span class="text-slate-500">{{ t("names.gender_matching.breakdown") }}:</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                                </svg>
                                {{ genderMatchingStats.preview.by_gender.male || 0 }} {{ t("names.gender_male") }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-pink-100 px-2 py-1 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" />
                                </svg>
                                {{ genderMatchingStats.preview.by_gender.female || 0 }} {{ t("names.gender_female") }}
                            </span>
                        </div>

                        <!-- Job progress bar -->
                        <div v-if="genderMatchingRunning && genderMatchingProgress" class="rounded-lg bg-white p-4 shadow-sm dark:bg-slate-800">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    {{ t("names.gender_matching.processing") }}...
                                </span>
                                <span class="text-slate-500">
                                    {{ genderMatchingProgress.processed || 0 }} / {{ genderMatchingProgress.total || 0 }}
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-300"
                                    :style="{ width: `${genderMatchingProgress.progress || 0}%` }"
                                ></div>
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
                                <span>{{ t("names.gender_matching.matched") }}: {{ genderMatchingProgress.matched || 0 }}</span>
                                <span>{{ t("names.gender_matching.unmatched") }}: {{ genderMatchingProgress.unmatched || 0 }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-4">
                            <button
                                v-if="genderMatchingStats.preview?.matchable_count > 0 && !genderMatchingRunning"
                                @click="runGenderMatching(true)"
                                class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 px-4 py-2 text-sm font-medium text-white shadow-lg transition hover:from-purple-600 hover:to-pink-600"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ t("names.gender_matching.run_matching") }}
                            </button>
                            <button
                                v-if="genderMatchingStats.preview?.matchable_count > 0 && !genderMatchingRunning"
                                @click="showMatchingPreviewModal = true"
                                class="inline-flex items-center gap-2 rounded-lg border border-purple-300 px-4 py-2 text-sm font-medium text-purple-700 transition hover:bg-purple-50 dark:border-purple-700 dark:text-purple-300 dark:hover:bg-purple-900/20"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ t("names.gender_matching.preview") }}
                            </button>
                            <span v-if="genderMatchingStats.preview?.matchable_count === 0" class="text-sm text-slate-500">
                                {{ t("names.gender_matching.no_subscribers") }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                <div class="flex-1 min-w-[200px]">
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('names.search_placeholder')"
                        class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm placeholder-slate-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-500"
                    />
                </div>
                <select
                    v-model="countryFilter"
                    @change="applyFilters"
                    class="rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">{{ t("names.all_countries") }}</option>
                    <option v-for="(label, code) in countries" :key="code" :value="code">
                        {{ label }}
                    </option>
                </select>
                <select
                    v-model="genderFilter"
                    @change="applyFilters"
                    class="rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">{{ t("names.all_genders") }}</option>
                    <option value="male">{{ t("names.gender_male") }}</option>
                    <option value="female">{{ t("names.gender_female") }}</option>
                    <option value="neutral">{{ t("names.gender_neutral") }}</option>
                </select>
                <select
                    v-model="sourceFilter"
                    @change="applyFilters"
                    class="rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option value="">{{ t("names.all_sources") }}</option>
                    <option value="system">{{ t("names.source_system") }}</option>
                    <option value="user">{{ t("names.source_user") }}</option>
                </select>
            </div>

            <!-- Names Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_name") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_vocative") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_gender") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_country") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_source") }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ t("names.column_actions") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="name in names?.data" :key="name.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900 dark:text-white capitalize">
                                {{ name.name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400 capitalize">
                                {{ name.vocative || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-medium', genderBadgeClass(name.gender)]">
                                    {{ genderLabel(name.gender) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ countries[name.country] || name.country }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                <span v-if="name.source === 'system'" class="text-slate-400">
                                    {{ t("names.source_system") }}
                                </span>
                                <span v-else class="text-emerald-600 dark:text-emerald-400">
                                    {{ t("names.source_user") }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <template v-if="name.source === 'user'">
                                    <button
                                        @click="openEditModal(name)"
                                        class="mr-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        {{ t("common.edit") }}
                                    </button>
                                    <button
                                        @click="confirmDelete(name)"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        {{ t("common.delete") }}
                                    </button>
                                </template>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="!names?.data?.length">
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                {{ t("names.no_names") }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="names?.links?.length > 3" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-700/50">
                    <div class="text-sm text-slate-500">
                        {{ t("common.showing") }} {{ names.from }} - {{ names.to }} {{ t("common.of") }} {{ names.total }}
                    </div>
                    <div class="flex gap-1">
                        <button
                            v-for="link in names.links"
                            :key="link.label"
                            @click="link.url && router.get(link.url, {}, { preserveState: true })"
                            :disabled="!link.url"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                link.active ? 'bg-emerald-500 text-white' : 'text-slate-600 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-600',
                                !link.url ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ editingName ? t("names.edit_name") : t("names.add_name") }}
                    </h3>
                    <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_name") }}
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_vocative") }}
                            </label>
                            <input
                                v-model="form.vocative"
                                type="text"
                                :placeholder="t('names.vocative_placeholder')"
                                class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p class="mt-1 text-xs text-slate-500">{{ t("names.vocative_help") }}</p>
                            <p v-if="form.errors.vocative" class="mt-1 text-sm text-red-500">{{ form.errors.vocative }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_gender") }}
                            </label>
                            <select
                                v-model="form.gender"
                                class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option value="male">{{ t("names.gender_male") }}</option>
                                <option value="female">{{ t("names.gender_female") }}</option>
                                <option value="neutral">{{ t("names.gender_neutral") }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_country") }}
                            </label>
                            <select
                                v-model="form.country"
                                class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option v-for="(label, code) in countries" :key="code" :value="code">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showAddModal = false"
                                class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                {{ t("common.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-600 disabled:opacity-50"
                            >
                                {{ form.processing ? t("common.saving") : t("common.save") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Import Modal -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("names.import_title") }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">{{ t("names.import_desc") }}</p>
                    <form @submit.prevent="submitImport" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_file") }}
                            </label>
                            <input
                                type="file"
                                accept=".csv,.txt"
                                @change="handleFileChange"
                                required
                                class="mt-1 w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-emerald-700 hover:file:bg-emerald-100 dark:text-slate-400 dark:file:bg-emerald-900/30 dark:file:text-emerald-400"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t("names.field_country") }}
                            </label>
                            <select
                                v-model="importForm.country"
                                class="mt-1 w-full rounded-lg border-slate-200 bg-slate-50 text-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option v-for="(label, code) in countries" :key="code" :value="code">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showImportModal = false"
                                class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                {{ t("common.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="importForm.processing"
                                class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-600 disabled:opacity-50"
                            >
                                {{ importForm.processing ? t("common.importing") : t("names.import") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("names.delete_title") }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ t("names.delete_confirm", { name: deletingName?.name }) }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="showDeleteModal = false"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ t("common.cancel") }}
                        </button>
                        <button
                            @click="deleteName"
                            class="rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-600"
                        >
                            {{ t("common.delete") }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Gender Matching Preview Modal -->
        <Teleport to="body">
            <div v-if="showMatchingPreviewModal && genderMatchingStats?.preview" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800 max-h-[80vh] overflow-hidden flex flex-col">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("names.gender_matching.preview_title") }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ t("names.gender_matching.preview_desc", { count: genderMatchingStats.preview.matchable_count }) }}
                    </p>

                    <div class="mt-4 flex-1 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-700/50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">{{ t("common.email") }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">{{ t("subscribers.fields.first_name") }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">{{ t("names.gender_matching.detected_gender") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-for="sub in genderMatchingStats.preview.matchable" :key="sub.id">
                                    <td class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400">{{ sub.email }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white capitalize">{{ sub.first_name }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-medium', genderBadgeClass(sub.detected_gender)]">
                                            {{ genderLabel(sub.detected_gender) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="genderMatchingStats.preview.matchable_count > 50" class="mt-2 text-center text-xs text-slate-400">
                            {{ t("names.gender_matching.showing_first_50") }}
                        </p>
                    </div>

                    <div class="mt-4 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <button
                            @click="showMatchingPreviewModal = false"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ t("common.close") }}
                        </button>
                        <button
                            @click="showMatchingPreviewModal = false; runGenderMatching(true)"
                            class="rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 px-4 py-2 text-sm font-medium text-white transition hover:from-purple-600 hover:to-pink-600"
                        >
                            {{ t("names.gender_matching.run_matching") }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Gender Matching Results Modal -->
        <Teleport to="body">
            <div v-if="showMatchingResultsModal && genderMatchingResults" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                            <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                {{ t("names.gender_matching.results_title") }}
                            </h3>
                            <p class="text-sm text-slate-500">
                                {{ t("names.gender_matching.results_desc") }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-3 gap-4">
                        <div class="rounded-lg bg-emerald-50 p-4 text-center dark:bg-emerald-900/20">
                            <div class="text-2xl font-bold text-emerald-600">{{ genderMatchingResults.matched || genderMatchingResults.matched_count || 0 }}</div>
                            <div class="text-sm text-slate-500">{{ t("names.gender_matching.matched") }}</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-4 text-center dark:bg-amber-900/20">
                            <div class="text-2xl font-bold text-amber-600">{{ genderMatchingResults.unmatched || genderMatchingResults.unmatched_count || 0 }}</div>
                            <div class="text-sm text-slate-500">{{ t("names.gender_matching.unmatched") }}</div>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4 text-center dark:bg-red-900/20">
                            <div class="text-2xl font-bold text-red-600">{{ genderMatchingResults.errors || genderMatchingResults.error_count || 0 }}</div>
                            <div class="text-sm text-slate-500">{{ t("common.errors") }}</div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="showMatchingResultsModal = false; clearProgress()"
                            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-600"
                        >
                            {{ t("common.close") }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

