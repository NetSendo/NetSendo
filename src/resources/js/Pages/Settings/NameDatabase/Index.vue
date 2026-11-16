<script setup>
import { ref, computed, watch } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SearchField from "@/Components/Ui/SearchField.vue";
import SelectField from "@/Components/Ui/SelectField.vue";
import StatTiles from "@/Components/Ui/StatTiles.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";

const { t, locale } = useI18n();
// The server words every outcome in the user's language (names.created, …): shown as it comes
const { toast, dismiss } = useToast({ flash: true });

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

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
const perPage = ref(props.filters?.per_page || "50");

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
            per_page: String(perPage.value) !== "50" ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

watch([countryFilter, genderFilter, sourceFilter, perPage], () => applyFilters());

const hasFilters = computed(() =>
    Boolean(search.value || countryFilter.value || genderFilter.value || sourceFilter.value),
);

// The watchers send the cleared set
const resetFilters = () => {
    countryFilter.value = "";
    genderFilter.value = "";
    sourceFilter.value = "";
    search.value = "";
};

const messagesOf = (target) => [...new Set(Object.values(target.errors).filter(Boolean))];

const statTiles = computed(() => [
    { key: "total", label: t("names.stats_total"), value: number(props.stats?.total) },
    { key: "system", label: t("names.stats_system"), value: number(props.stats?.system) },
    { key: "user", label: t("names.stats_user"), value: number(props.stats?.user) },
    {
        key: "countries",
        label: t("names.stats_countries"),
        value: number(Object.keys(props.stats?.by_country || {}).length),
    },
]);

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
    form.clearErrors();
    form.country = countryFilter.value || "PL";
    showAddModal.value = true;
};

// Open edit modal
const openEditModal = (name) => {
    editingName.value = name;
    form.clearErrors();
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

const matchingTiles = computed(() => {
    const stats = genderMatchingStats.value;
    return [
        { key: "total", label: t("names.gender_matching.stat_total"), value: number(stats?.subscriber_stats?.total) },
        { key: "without", label: t("names.gender_matching.stat_without_gender"), value: number(stats?.preview?.total_without_gender) },
        { key: "matchable", label: t("names.gender_matching.stat_matchable"), value: number(stats?.preview?.matchable_count) },
        { key: "unmatchable", label: t("names.gender_matching.stat_unmatchable"), value: number(stats?.preview?.unmatchable_count) },
    ];
});

const matchingResultFigures = computed(() => {
    const results = genderMatchingResults.value || {};
    return [
        { key: "matched", label: t("names.gender_matching.matched"), value: number(results.matched || results.matched_count) },
        { key: "unmatched", label: t("names.gender_matching.unmatched"), value: number(results.unmatched || results.unmatched_count) },
        { key: "errors", label: t("common.errors"), value: number(results.errors || results.error_count) },
    ];
});

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
            <PageHeader :title="t('names.title')" :subtitle="t('names.subtitle')">
                <SecondaryButton type="button" @click="showImportModal = true">{{ t("names.import") }}</SecondaryButton>
                <SecondaryButton type="button" @click="exportNames('all')">{{ t("names.export") }}</SecondaryButton>
                <CreateLink @click="openAddModal">{{ t("names.add_name") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <StatTiles :items="statTiles" :label="t('names.title')" />

            <Panel flush>
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <SearchField v-model="search" :placeholder="t('names.search_placeholder')" />
                    <SelectField v-model="countryFilter" :label="t('names.column_country')">
                        <option value="">{{ t("names.all_countries") }}</option>
                        <option v-for="(label, code) in countries" :key="code" :value="code">{{ label }}</option>
                    </SelectField>
                    <SelectField v-model="genderFilter" :label="t('names.column_gender')">
                        <option value="">{{ t("names.all_genders") }}</option>
                        <option value="male">{{ t("names.gender_male") }}</option>
                        <option value="female">{{ t("names.gender_female") }}</option>
                        <option value="neutral">{{ t("names.gender_neutral") }}</option>
                    </SelectField>
                    <SelectField v-model="sourceFilter" :label="t('names.column_source')">
                        <option value="">{{ t("names.all_sources") }}</option>
                        <option value="system">{{ t("names.source_system") }}</option>
                        <option value="user">{{ t("names.source_user") }}</option>
                    </SelectField>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ t("messages.filters.clear") }}
                    </button>
                </div>

                <EmptyState v-if="!names?.data?.length" :title="t('names.no_names')">
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ t("messages.filters.clear") }}
                    </button>
                </EmptyState>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full text-sm sm:min-w-[760px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ t("names.column_name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t("names.column_vocative") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ t("names.column_gender") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t("names.column_country") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t("names.column_source") }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ t("names.column_actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="name in names.data"
                                :key="name.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-white">
                                    {{ name.name }}
                                    <!-- On a phone the vocative has no column of its own -->
                                    <p v-if="name.vocative" class="text-xs font-normal text-slate-600 sm:hidden dark:text-slate-400">
                                        {{ name.vocative }}
                                    </p>
                                </td>
                                <td class="px-3 py-2.5 text-slate-900 max-sm:hidden dark:text-slate-100">
                                    {{ name.vocative || "—" }}
                                </td>
                                <td class="px-3 py-2.5 text-slate-900 dark:text-slate-100">{{ genderLabel(name.gender) }}</td>
                                <td class="px-3 py-2.5 text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ countries[name.country] || name.country }}
                                </td>
                                <td class="px-3 py-2.5 max-sm:hidden">
                                    <StatusPill :tone="name.source === 'system' ? 'neutral' : 'info'">
                                        {{ name.source === "system" ? t("names.source_system") : t("names.source_user") }}
                                    </StatusPill>
                                </td>
                                <td class="py-1.5 pl-3 pr-5">
                                    <!-- The built-in names are read-only; one's own can be changed -->
                                    <div v-if="name.source === 'user'" class="flex items-center justify-end">
                                        <ActionMenu :label="t('names.actions_for', { name: name.name })">
                                            <ActionMenuItem @click="openEditModal(name)">{{ t("common.edit") }}</ActionMenuItem>
                                            <ActionMenuItem danger @click="confirmDelete(name)">{{ t("common.delete") }}</ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="names?.total > 0"
                    v-model:per-page="perPage"
                    :paginator="names"
                    :per-page-options="[25, 50, 100, 200]"
                />
            </Panel>

            <!-- Fill in the gender of subscribers who have none, from their first names -->
            <Panel :title="t('names.gender_matching.title')">
                <template #actions>
                    <button
                        type="button"
                        :aria-expanded="showGenderMatchingSection"
                        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="toggleGenderMatchingSection"
                    >
                        {{ showGenderMatchingSection ? t("common.hide") : t("common.show") }}
                    </button>
                </template>

                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">{{ t("names.gender_matching.description") }}</p>

                <div v-if="showGenderMatchingSection" class="mt-4 flex flex-col gap-4">
                    <p v-if="genderMatchingLoading" role="status" class="text-sm text-slate-600 dark:text-slate-400">
                        {{ t("common.loading") }}
                    </p>

                    <template v-else-if="genderMatchingStats">
                        <label class="flex flex-wrap items-center gap-3 text-sm text-slate-800 dark:text-slate-200">
                            {{ t("names.gender_matching.country_label") }}
                            <SelectField
                                v-model="matchingCountry"
                                :label="t('names.gender_matching.country_label')"
                                @update:model-value="fetchGenderMatchingStats"
                            >
                                <option v-for="(label, code) in countries" :key="code" :value="code">{{ label }}</option>
                            </SelectField>
                        </label>

                        <StatTiles :items="matchingTiles" :label="t('names.gender_matching.title')" />

                        <p
                            v-if="genderMatchingStats.preview?.by_gender"
                            class="text-sm tabular-nums text-slate-600 dark:text-slate-400"
                        >
                            {{ t("names.gender_matching.breakdown") }}:
                            {{ number(genderMatchingStats.preview.by_gender.male) }} {{ t("names.gender_male") }},
                            {{ number(genderMatchingStats.preview.by_gender.female) }} {{ t("names.gender_female") }}
                        </p>

                        <!-- The job runs in the background: how far it is -->
                        <div v-if="genderMatchingRunning && genderMatchingProgress" role="status" class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-900 dark:text-white">
                                    {{ t("names.gender_matching.processing") }}…
                                </span>
                                <span class="tabular-nums text-slate-600 dark:text-slate-400">
                                    {{ number(genderMatchingProgress.processed) }} / {{ number(genderMatchingProgress.total) }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-[width] dark:bg-indigo-400"
                                    :style="{ width: `${genderMatchingProgress.progress || 0}%` }"
                                ></div>
                            </div>
                            <p class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                {{ t("names.gender_matching.matched") }}: {{ number(genderMatchingProgress.matched) }}
                                <span aria-hidden="true">·</span>
                                {{ t("names.gender_matching.unmatched") }}: {{ number(genderMatchingProgress.unmatched) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <template v-if="genderMatchingStats.preview?.matchable_count > 0 && !genderMatchingRunning">
                                <PrimaryButton type="button" @click="runGenderMatching(true)">
                                    {{ t("names.gender_matching.run_matching") }}
                                </PrimaryButton>
                                <SecondaryButton type="button" @click="showMatchingPreviewModal = true">
                                    {{ t("names.gender_matching.preview") }}
                                </SecondaryButton>
                            </template>
                            <p
                                v-if="genderMatchingStats.preview?.matchable_count === 0"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                {{ t("names.gender_matching.no_subscribers") }}
                            </p>
                        </div>
                    </template>
                </div>
            </Panel>

            <!-- What the names are for -->
            <Panel :title="t('names.help_title')">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("names.help_desc") }}</p>
                <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ t("names.syntax_title") }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    <code
                        class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                        v-pre
                        >{{chciałbym|chciałabym}}</code
                    >
                    → {{ t("names.syntax_result") }}
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ t("names.syntax_desc") }}</p>
            </Panel>
        </div>

        <!-- A name of one's own: the name, how to address the person, the gender, the country -->
        <FormDialog
            :show="showAddModal"
            :title="editingName ? t('names.edit_name') : t('names.add_name')"
            :processing="form.processing"
            :errors="messagesOf(form)"
            @close="showAddModal = false"
            @submit="submitForm"
        >
            <div class="space-y-4">
                <div>
                    <label for="name-name" :class="labelClass">{{ t("names.field_name") }}</label>
                    <input
                        id="name-name"
                        v-model="form.name"
                        type="text"
                        required
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                    <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="name-vocative" :class="labelClass">{{ t("names.field_vocative") }}</label>
                    <input
                        id="name-vocative"
                        v-model="form.vocative"
                        type="text"
                        :placeholder="t('names.vocative_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.vocative }]"
                    />
                    <p v-if="form.errors.vocative" :class="errorClass">{{ form.errors.vocative }}</p>
                    <p v-else :class="hintClass">{{ t("names.vocative_help") }}</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name-gender" :class="labelClass">{{ t("names.field_gender") }}</label>
                        <select id="name-gender" v-model="form.gender" class="mt-1" :class="fieldClass">
                            <option value="male">{{ t("names.gender_male") }}</option>
                            <option value="female">{{ t("names.gender_female") }}</option>
                            <option value="neutral">{{ t("names.gender_neutral") }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="name-country" :class="labelClass">{{ t("names.field_country") }}</label>
                        <select id="name-country" v-model="form.country" class="mt-1" :class="fieldClass">
                            <option v-for="(label, code) in countries" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </FormDialog>

        <FormDialog
            :show="showImportModal"
            :title="t('names.import_title')"
            :processing="importForm.processing"
            :submit-label="t('names.import')"
            :errors="messagesOf(importForm)"
            @close="showImportModal = false"
            @submit="submitImport"
        >
            <div class="space-y-4">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("names.import_desc") }}</p>
                <div>
                    <label for="names-file" :class="labelClass">{{ t("names.field_file") }}</label>
                    <input
                        id="names-file"
                        type="file"
                        accept=".csv,.txt"
                        required
                        class="mt-1 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-800 dark:file:bg-slate-700 dark:file:text-slate-100"
                        :class="fieldClass"
                        @change="handleFileChange"
                    />
                </div>
                <div>
                    <label for="names-import-country" :class="labelClass">{{ t("names.field_country") }}</label>
                    <select id="names-import-country" v-model="importForm.country" class="mt-1" :class="fieldClass">
                        <option v-for="(label, code) in countries" :key="code" :value="code">{{ label }}</option>
                    </select>
                </div>
            </div>
        </FormDialog>

        <ConfirmModal
            :show="showDeleteModal"
            type="danger"
            :title="t('names.delete_title')"
            :message="t('names.delete_confirm', { name: deletingName?.name })"
            :confirm-text="t('common.delete')"
            @close="showDeleteModal = false"
            @confirm="deleteName"
        />

        <!-- Who would get which gender, before anything is changed -->
        <Modal :show="showMatchingPreviewModal && !!genderMatchingStats?.preview" max-width="2xl" @close="showMatchingPreviewModal = false">
            <div class="flex max-h-[calc(100vh-3rem)] flex-col">
                <div class="px-6 pb-3 pt-6">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("names.gender_matching.preview_title") }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("names.gender_matching.preview_desc", { count: genderMatchingStats?.preview?.matchable_count }) }}
                    </p>
                </div>
                <div class="relative min-h-0 flex-1 overflow-auto border-y border-slate-200 dark:border-slate-700/60">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-6 py-2.5 font-medium">{{ t("common.email") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ t("subscribers.fields.first_name") }}</th>
                                <th scope="col" class="px-6 py-2.5 font-medium">
                                    {{ t("names.gender_matching.detected_gender") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="sub in genderMatchingStats?.preview?.matchable"
                                :key="sub.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                            >
                                <td class="px-6 py-2 text-slate-900 dark:text-white">{{ sub.email }}</td>
                                <td class="px-3 py-2 text-slate-900 dark:text-slate-100">{{ sub.first_name }}</td>
                                <td class="px-6 py-2 text-slate-900 dark:text-slate-100">{{ genderLabel(sub.detected_gender) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        <template v-if="genderMatchingStats?.preview?.matchable_count > 50">
                            {{ t("names.gender_matching.showing_first_50") }}
                        </template>
                    </p>
                    <div class="flex items-center gap-3">
                        <SecondaryButton type="button" @click="showMatchingPreviewModal = false">
                            {{ t("common.close") }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            @click="
                                showMatchingPreviewModal = false;
                                runGenderMatching(true);
                            "
                        >
                            {{ t("names.gender_matching.run_matching") }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- How the matching ended -->
        <Modal
            :show="showMatchingResultsModal && !!genderMatchingResults"
            max-width="lg"
            @close="
                showMatchingResultsModal = false;
                clearProgress();
            "
        >
            <div class="flex flex-col gap-4 p-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("names.gender_matching.results_title") }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("names.gender_matching.results_desc") }}
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-center">
                    <div
                        v-for="figure in matchingResultFigures"
                        :key="figure.key"
                        class="rounded-xl border border-slate-200 px-3 py-3 dark:border-slate-700/60"
                    >
                        <dd class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ figure.value }}</dd>
                        <dt class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ figure.label }}</dt>
                    </div>
                </dl>
                <div class="flex justify-end">
                    <PrimaryButton
                        type="button"
                        @click="
                            showMatchingResultsModal = false;
                            clearProgress();
                        "
                    >
                        {{ t("common.close") }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
