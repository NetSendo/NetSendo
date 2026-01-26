<script setup>
import { ref, computed } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";
import { useDateTime } from "@/Composables/useDateTime";

const { t } = useI18n();
const { formatDate: formatDateBase } = useDateTime();

const props = defineProps({
    forms: Object,
    lists: Array,
    filters: Object,
});

// Toast notification
const toast = ref(null);
function showToast(message, success = true) {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 3000);
}

// Check for flash messages on page load
const page = usePage();
if (page.props.flash?.success) {
    showToast(page.props.flash.success, true);
}

// Filters
const selectedList = ref(props.filters?.list_id || "");
const selectedStatus = ref(props.filters?.status || "");
const searchQuery = ref(props.filters?.search || "");

// Apply filters
function applyFilters() {
    router.get(
        route("forms.index"),
        {
            list_id: selectedList.value || undefined,
            status: selectedStatus.value || undefined,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

// Debounced search
let searchTimeout;
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
}

// Actions
function duplicateForm(form) {
    if (confirm(t("forms.confirm_duplicate"))) {
        router.post(route("forms.duplicate", form.id), {}, {
            onSuccess: () => showToast(t("forms.duplicated")),
        });
    }
}

function deleteForm(form) {
    if (confirm(t("forms.confirm_delete", { name: form.name }))) {
        router.delete(route("forms.destroy", form.id), {
            onSuccess: () => showToast(t("forms.deleted")),
        });
    }
}

function toggleStatus(form) {
    router.post(route("forms.toggle-status", form.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            const message = form.status === 'active'
                ? t("forms.deactivated")
                : t("forms.activated");
            showToast(message);
        },
    });
}

// Status badge
function getStatusBadge(status) {
    switch (status) {
        case "active":
            return { class: "badge-success", label: t("forms.status.active") };
        case "draft":
            return { class: "badge-warning", label: t("forms.status.draft") };
        case "disabled":
            return {
                class: "badge-secondary",
                label: t("forms.status.disabled"),
            };
        default:
            return { class: "badge-secondary", label: status };
    }
}

// Format date
function formatDate(date) {
    if (!date) return "-";
    return formatDateBase(date, null, {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}
</script>

<template>
    <Head :title="t('forms.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ t("forms.title") }}
                </h1>
                <Link
                    :href="route('forms.create')"
                    class="btn-primary inline-flex items-center gap-2 text-white"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ t("forms.create") }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6"
                >
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-2">
                            <div class="relative">
                                <svg
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    type="text"
                                    :placeholder="t('forms.search_placeholder')"
                                    class="input pl-10 w-full"
                                />
                            </div>
                        </div>

                        <!-- List filter -->
                        <div>
                            <select
                                v-model="selectedList"
                                @change="applyFilters"
                                class="input w-full"
                            >
                                <option value="">
                                    {{ t("forms.all_lists") }}
                                </option>
                                <option
                                    v-for="list in lists"
                                    :key="list.id"
                                    :value="list.id"
                                >
                                    {{ list.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Status filter -->
                        <div>
                            <select
                                v-model="selectedStatus"
                                @change="applyFilters"
                                class="input w-full"
                            >
                                <option value="">
                                    {{ t("forms.all_statuses") }}
                                </option>
                                <option value="active">
                                    {{ t("forms.status.active") }}
                                </option>
                                <option value="draft">
                                    {{ t("forms.status.draft") }}
                                </option>
                                <option value="disabled">
                                    {{ t("forms.status.disabled") }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Forms list -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden"
                >
                    <div
                        v-if="forms.data.length === 0"
                        class="p-12 text-center"
                    >
                        <div
                            class="mx-auto w-16 h-16 bg-indigo-100 dark:bg-indigo-900/40 rounded-full flex items-center justify-center"
                        >
                            <svg
                                class="h-8 w-8 text-indigo-500 dark:text-indigo-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mt-4 text-lg font-medium text-gray-900 dark:text-white"
                        >
                            {{ t("forms.empty_title") }}
                        </h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-300">
                            {{ t("forms.empty_description") }}
                        </p>
                        <Link
                            :href="route('forms.create')"
                            class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            {{ t("forms.create_first") }}
                        </Link>
                    </div>

                    <table v-else class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.name") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.list") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.submissions") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.last_submission") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                >
                                    {{ t("forms.table.actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="form in forms.data"
                                :key="form.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <Link
                                        :href="route('forms.edit', form.id)"
                                        class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400"
                                    >
                                        {{ form.name }}
                                    </Link>
                                </td>
                                <td
                                    class="px-6 py-4 text-gray-600 dark:text-gray-400"
                                >
                                    {{ form.contact_list?.name || "-" }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400':
                                                form.status === 'active',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400':
                                                form.status === 'draft',
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400':
                                                form.status === 'disabled',
                                        }"
                                    >
                                        {{ getStatusBadge(form.status).label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-center text-gray-900 dark:text-gray-100 font-medium"
                                >
                                    {{ form.submissions_count || 0 }}
                                </td>
                                <td
                                    class="px-6 py-4 text-gray-600 dark:text-gray-400 text-sm"
                                >
                                    {{ formatDate(form.last_submission_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div
                                        class="flex justify-end items-center gap-2"
                                    >
                                        <Link
                                            :href="route('forms.edit', form.id)"
                                            class="btn-icon text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                            :title="t('common.edit')"
                                        >
                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                />
                                            </svg>
                                        </Link>
                                        <Link
                                            :href="route('forms.code', form.id)"
                                            class="btn-icon text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                            :title="t('forms.get_code')"
                                        >
                                            <svg
                                                class="w-5 h-5"
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
                                        </Link>
                                        <Link
                                            :href="
                                                route('forms.stats', form.id)
                                            "
                                            class="btn-icon text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                            :title="t('forms.stats_btn')"
                                        >
                                            <svg
                                                class="w-5 h-5"
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
                                        </Link>
                                        <!-- Toggle Status Button -->
                                        <button
                                            @click="toggleStatus(form)"
                                            :class="[
                                                'btn-icon',
                                                form.status === 'active'
                                                    ? 'text-orange-500 hover:text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20'
                                                    : 'text-green-500 hover:text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20'
                                            ]"
                                            :title="form.status === 'active' ? t('forms.deactivate') : t('forms.activate')"
                                        >
                                            <!-- Power/Toggle Icon -->
                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    v-if="form.status === 'active'"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                                />
                                                <path
                                                    v-else
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            @click="duplicateForm(form)"
                                            class="btn-icon text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                            :title="t('common.duplicate')"
                                        >
                                            <svg
                                                class="w-5 h-5"
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
                                        </button>
                                        <button
                                            @click="deleteForm(form)"
                                            class="btn-icon text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                            :title="t('common.delete')"
                                        >
                                            <svg
                                                class="w-5 h-5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div
                        v-if="forms.data.length > 0 && forms.last_page > 1"
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ t("common.showing") }} {{ forms.from }}-{{
                                    forms.to
                                }}
                                {{ t("common.of") }} {{ forms.total }}
                            </p>
                            <div class="flex gap-2">
                                <Link
                                    v-if="forms.prev_page_url"
                                    :href="forms.prev_page_url"
                                    class="btn-secondary"
                                >
                                    {{ t("common.previous") }}
                                </Link>
                                <Link
                                    v-if="forms.next_page_url"
                                    :href="forms.next_page_url"
                                    class="btn-secondary"
                                >
                                    {{ t("common.next") }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div
            v-if="toast"
            class="fixed bottom-4 right-4 z-50 max-w-sm w-full"
        >
            <div
                :class="[
                    'rounded-lg shadow-lg p-4 flex items-center gap-3',
                    toast.success
                        ? 'bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-800'
                        : 'bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800'
                ]"
            >
                <svg
                    v-if="toast.success"
                    class="h-5 w-5 text-green-500"
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
                <svg
                    v-else
                    class="h-5 w-5 text-red-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
                <span
                    :class="[
                        'text-sm font-medium',
                        toast.success
                            ? 'text-green-800 dark:text-green-200'
                            : 'text-red-800 dark:text-red-200'
                    ]"
                >
                    {{ toast.message }}
                </span>
                <button
                    @click="toast = null"
                    class="ml-auto text-gray-400 hover:text-gray-600"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
