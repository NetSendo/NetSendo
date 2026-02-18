<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import axios from "axios";
import debounce from "lodash/debounce";
import { useI18n } from "vue-i18n";
import BulkActionToolbar from "./Partials/BulkActionToolbar.vue";
import MoveToListModal from "./Partials/MoveToListModal.vue";
import CopyToListModal from "./Partials/CopyToListModal.vue";
import DeleteFromListModal from "./Partials/DeleteFromListModal.vue";
import ColumnSettingsDropdown from "./Partials/ColumnSettingsDropdown.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";

const { t } = useI18n();

const props = defineProps({
    subscribers: Object,
    lists: Array,
    customFields: Array,
    statistics: Object,
    filters: Object,
});

// Search and filter state
const search = ref(props.filters.search || "");
const listId = ref(props.filters.list_id || "");
const listType = ref(props.filters.list_type || "");

// Searchable list dropdown state
const listSearch = ref("");
const listDropdownOpen = ref(false);
const listDropdownRef = ref(null);

// Pagination state
const loadPerPageSetting = () => {
    const saved = localStorage.getItem("subscriberPerPage");
    if (saved && [10, 15, 25, 50, 100, 200].includes(parseInt(saved))) {
        return parseInt(saved);
    }
    return props.filters.per_page || 15;
};
const perPage = ref(loadPerPageSetting());

// Sorting state
const sortBy = ref(props.filters.sort_by || "created_at");
const sortOrder = ref(props.filters.sort_order || "desc");

// Selection state
const selectedIds = ref([]);
const processing = ref(false);

// Toast notification
const toast = ref(null);
function showToast(message, success = true) {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
}

// Check for flash messages on page load
const page = usePage();
if (page.props.flash?.success) {
    showToast(page.props.flash.success, true);
}
if (page.props.flash?.warning) {
    showToast(page.props.flash.warning, false);
}
if (page.props.flash?.error) {
    showToast(page.props.flash.error, false);
}

// Move modal state
const showMoveModal = ref(false);

// Copy/Add modal state
const showCopyModal = ref(false);

// Confirmation modal state
const showDeleteConfirm = ref(false);
const showDeleteFromListConfirm = ref(false);
const showSelectAllConfirm = ref(false);
const pendingSelectAllCount = ref(0);

// Single subscriber delete modal state
const showDeleteSubscriberModal = ref(false);
const subscriberToDelete = ref(null);

// Column visibility state (persisted to localStorage)
const defaultColumns = {
    email: true,
    name: true,
    phone: false,
    language: false,
    status: true,
    list: true,
    created_at: true,
};

const loadColumnSettings = () => {
    const saved = localStorage.getItem("subscriberColumns");
    if (saved) {
        try {
            return { ...defaultColumns, ...JSON.parse(saved) };
        } catch (e) {
            return { ...defaultColumns };
        }
    }
    return { ...defaultColumns };
};

const visibleColumns = ref(loadColumnSettings());

// Column ordering (persisted to localStorage)
const standardColumnDefinitions = [
    {
        key: "email",
        labelKey: "subscribers.table.email",
        sortKey: "email",
        sortable: true,
        type: "email",
    },
    {
        key: "name",
        labelKey: "subscribers.table.name",
        sortKey: "first_name",
        sortable: true,
        type: "name",
    },
    {
        key: "phone",
        labelKey: "subscribers.table.phone",
        sortKey: "phone",
        sortable: true,
        type: "phone",
    },
    {
        key: "language",
        labelKey: "subscribers.table.language",
        sortKey: "language",
        sortable: true,
        type: "language",
    },
    {
        key: "status",
        labelKey: "subscribers.table.status",
        sortable: false,
        type: "status",
    },
    {
        key: "list",
        labelKey: "subscribers.table.list",
        sortable: false,
        type: "list",
    },
    {
        key: "created_at",
        labelKey: "subscribers.table.added_at",
        sortKey: "created_at",
        sortable: true,
        type: "created_at",
    },
];

const getCustomFieldKeys = () =>
    (props.customFields || []).map((field) => `cf_${field.id}`);

const buildDefaultColumnOrder = (customKeys) => {
    const base = [
        "email",
        "name",
        "phone",
        "language",
        "status",
        "list",
        "created_at",
    ];
    const createdAtIndex = base.indexOf("created_at");
    return [
        ...base.slice(0, createdAtIndex),
        ...customKeys,
        ...base.slice(createdAtIndex),
    ];
};

const insertMissingKeys = (order, missingKeys) => {
    let next = [...order];
    missingKeys.forEach((key) => {
        if (key.startsWith("cf_")) {
            const createdAtIndex = next.indexOf("created_at");
            if (createdAtIndex === -1) {
                next.push(key);
            } else {
                next.splice(createdAtIndex, 0, key);
            }
            return;
        }
        next.push(key);
    });
    return next;
};

const normalizeColumnOrder = (order) => {
    const customKeys = getCustomFieldKeys();
    const defaultOrder = buildDefaultColumnOrder(customKeys);
    const filteredOrder = Array.isArray(order)
        ? order.filter((key) => defaultOrder.includes(key))
        : [];
    if (filteredOrder.length === 0) {
        return defaultOrder;
    }
    const missing = defaultOrder.filter((key) => !filteredOrder.includes(key));
    return missing.length > 0
        ? insertMissingKeys(filteredOrder, missing)
        : filteredOrder;
};

const loadColumnOrder = () => {
    const saved = localStorage.getItem("subscriberColumnOrder");
    if (!saved) {
        return normalizeColumnOrder([]);
    }
    try {
        return normalizeColumnOrder(JSON.parse(saved));
    } catch (e) {
        return normalizeColumnOrder([]);
    }
};

const saveColumnOrder = (order) => {
    localStorage.setItem("subscriberColumnOrder", JSON.stringify(order));
};

const columnOrder = ref(loadColumnOrder());

const isSameOrder = (a, b) =>
    a.length === b.length && a.every((value, index) => value === b[index]);

watch(
    () => props.customFields,
    () => {
        const normalized = normalizeColumnOrder(columnOrder.value);
        if (!isSameOrder(columnOrder.value, normalized)) {
            columnOrder.value = normalized;
            saveColumnOrder(columnOrder.value);
        }
    },
    { deep: true },
);

const customFieldColumns = computed(() =>
    (props.customFields || []).map((field) => ({
        key: `cf_${field.id}`,
        label: field.label || field.name,
        type: "custom",
        fieldId: field.id,
    })),
);

const columnDefinitionMap = computed(() => {
    const map = new Map();
    standardColumnDefinitions.forEach((column) => {
        map.set(column.key, column);
    });
    customFieldColumns.value.forEach((column) => {
        map.set(column.key, column);
    });
    return map;
});

const orderedColumns = computed(() =>
    columnOrder.value
        .map((key) => columnDefinitionMap.value.get(key))
        .filter(Boolean),
);

const visibleOrderedColumns = computed(() =>
    orderedColumns.value.filter((column) => visibleColumns.value[column.key]),
);

const getColumnLabel = (column) =>
    column.labelKey ? t(column.labelKey) : column.label;

const isColumnVisible = (key) => !!visibleColumns.value[key];

// Computed: check if all visible subscribers are selected
const isAllSelected = computed(() => {
    return (
        props.subscribers.data.length > 0 &&
        selectedIds.value.length === props.subscribers.data.length
    );
});

// Computed: check if some (but not all) are selected
const isSomeSelected = computed(() => {
    return (
        selectedIds.value.length > 0 &&
        selectedIds.value.length < props.subscribers.data.length
    );
});

// Computed: filter lists by selected type and search term
const filteredLists = computed(() => {
    let lists = props.lists;
    if (listType.value) {
        lists = lists.filter((list) => list.type === listType.value);
    }
    if (listSearch.value) {
        const term = listSearch.value.toLowerCase();
        lists = lists.filter(
            (list) =>
                list.name.toLowerCase().includes(term) ||
                String(list.id).includes(term),
        );
    }
    return lists;
});

// Get selected list name for dropdown button label
const selectedListName = computed(() => {
    if (!listId.value) return null;
    const list = props.lists.find((l) => l.id == listId.value);
    return list ? list.name : null;
});

// Select a list from the dropdown
const selectList = (list) => {
    listId.value = list.id;
    listSearch.value = "";
    listDropdownOpen.value = false;
};

// Clear list filter
const clearListFilter = () => {
    listId.value = "";
    listSearch.value = "";
    listDropdownOpen.value = false;
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (
        listDropdownRef.value &&
        !listDropdownRef.value.contains(event.target)
    ) {
        listDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});

// Toggle column visibility
const toggleColumn = (column) => {
    visibleColumns.value[column] = !visibleColumns.value[column];
    localStorage.setItem(
        "subscriberColumns",
        JSON.stringify(visibleColumns.value),
    );
};

const applyVisibleOrder = (newVisibleKeys) => {
    const visibleSet = new Set(newVisibleKeys);
    let visibleIndex = 0;
    columnOrder.value = columnOrder.value.map((key) => {
        if (!visibleSet.has(key)) {
            return key;
        }
        const nextKey = newVisibleKeys[visibleIndex];
        visibleIndex += 1;
        return nextKey;
    });
    saveColumnOrder(columnOrder.value);
};

const reorderColumns = ({ from, to }) => {
    const visibleKeys = visibleOrderedColumns.value.map((column) => column.key);
    const fromIndex = visibleKeys.indexOf(from);
    const toIndex = visibleKeys.indexOf(to);
    if (fromIndex === -1 || toIndex === -1) {
        return;
    }
    const [moved] = visibleKeys.splice(fromIndex, 1);
    visibleKeys.splice(toIndex, 0, moved);
    applyVisibleOrder(visibleKeys);
};

// Toggle all selection
const toggleSelectAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.subscribers.data.map((s) => s.id);
    }
};

// Toggle single selection
const toggleSelect = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

// Clear selection helper
const clearSelection = () => {
    selectedIds.value = [];
};

// Select all in current list
const isSelectingAllInList = ref(false);
const selectAllInList = async () => {
    if (!listId.value) return;

    isSelectingAllInList.value = true;
    try {
        const response = await axios.get(route("subscribers.list-ids"), {
            params: { list_id: listId.value },
        });
        pendingSelectAllCount.value = response.data.count;
        selectedIds.value = response.data.ids;
        // Show confirmation modal with count
        showSelectAllConfirm.value = true;
    } catch (error) {
        console.error("Error fetching all subscriber IDs:", error);
    } finally {
        isSelectingAllInList.value = false;
    }
};

// Sort handler
const handleSort = (column) => {
    if (sortBy.value === column) {
        sortOrder.value = sortOrder.value === "asc" ? "desc" : "asc";
    } else {
        sortBy.value = column;
        sortOrder.value = "desc";
    }
    applyFilters();
};

// Apply filters with debounce for search
const applyFilters = () => {
    router.get(
        route("subscribers.index"),
        {
            search: search.value,
            list_id: listId.value,
            list_type: listType.value,
            sort_by: sortBy.value,
            sort_order: sortOrder.value,
            per_page: perPage.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

// Update per-page preference
const updatePerPage = (value) => {
    perPage.value = value;
    localStorage.setItem("subscriberPerPage", value);
    applyFilters();
};

// Watch for search/filter changes
watch(
    [search, listId, listType],
    debounce(() => {
        applyFilters();
    }, 300),
);

// Clear selection when data changes (e.g., after bulk action)
watch(
    () => props.subscribers.data,
    () => {
        selectedIds.value = [];
    },
);

// Bulk delete action
const bulkDelete = () => {
    if (selectedIds.value.length === 0) return;
    showDeleteConfirm.value = true;
};

const confirmBulkDelete = () => {
    if (selectedIds.value.length === 0) return;

    processing.value = true;
    router.post(
        route("subscribers.bulk-delete"),
        {
            ids: selectedIds.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedIds.value = [];
                showDeleteConfirm.value = false;
            },
        },
    );
};

// Bulk move action
const bulkMove = ({ source_list_id, target_list_id }) => {
    if (selectedIds.value.length === 0) return;

    processing.value = true;
    router.post(
        route("subscribers.bulk-move"),
        {
            ids: selectedIds.value,
            source_list_id,
            target_list_id,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedIds.value = [];
                showMoveModal.value = false;
            },
        },
    );
};

// Bulk status change action
const bulkChangeStatus = (status) => {
    if (selectedIds.value.length === 0 || !status) return;

    processing.value = true;
    router.post(
        route("subscribers.bulk-status"),
        {
            ids: selectedIds.value,
            status,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedIds.value = [];
            },
        },
    );
};

// Bulk copy to list action
const bulkCopy = () => {
    showCopyModal.value = true;
};

// Handle copy submit
const submitCopy = (data) => {
    if (selectedIds.value.length === 0) return;

    processing.value = true;
    router.post(
        route("subscribers.bulk-copy"),
        {
            ids: selectedIds.value,
            target_list_id: data.target_list_id,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedIds.value = [];
                showCopyModal.value = false;
            },
        },
    );
};

// Bulk delete from list action
const bulkDeleteFromList = () => {
    if (selectedIds.value.length === 0 || !listId.value) return;
    showDeleteFromListConfirm.value = true;
};

const confirmBulkDeleteFromList = () => {
    if (selectedIds.value.length === 0 || !listId.value) return;

    processing.value = true;
    router.post(
        route("subscribers.bulk-delete-from-list"),
        {
            ids: selectedIds.value,
            list_id: listId.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedIds.value = [];
                showDeleteFromListConfirm.value = false;
            },
        },
    );
};

// Single delete action - open modal
const deleteSubscriber = (subscriber) => {
    subscriberToDelete.value = subscriber;
    showDeleteSubscriberModal.value = true;
};

// Handle delete from selected lists
const handleDeleteFromLists = ({ subscriber_id, list_ids }) => {
    processing.value = true;
    router.post(
        route("subscribers.advanced-delete", subscriber_id),
        {
            list_ids: list_ids,
            gdpr_forget: false,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                showDeleteSubscriberModal.value = false;
                subscriberToDelete.value = null;
            },
        },
    );
};

// Handle complete GDPR deletion
const handleDeleteCompletely = ({ subscriber_id }) => {
    processing.value = true;
    router.post(
        route("subscribers.advanced-delete", subscriber_id),
        {
            gdpr_forget: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                showDeleteSubscriberModal.value = false;
                subscriberToDelete.value = null;
            },
        },
    );
};

// Quick add subscriber to CRM as lead
const addToCrm = (subscriber) => {
    if (subscriber.has_crm_contact) {
        showToast(t("subscribers.crm_already_exists"), false);
        return;
    }
    router.post(
        route("subscribers.add-to-crm", subscriber.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(t("subscribers.crm_added_success"), true);
            },
            onError: () => {
                showToast(t("subscribers.crm_add_failed"), false);
            },
        },
    );
};

// Get sort icon class
const getSortIcon = (column) => {
    if (sortBy.value !== column) return "opacity-0 group-hover:opacity-50";
    return sortOrder.value === "asc" ? "rotate-180" : "";
};
</script>

<template>
    <Head :title="$t('subscribers.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("subscribers.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("subscribers.subtitle") }}
                    </p>
                </div>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <Link
                    :href="route('subscribers.import')"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 sm:flex-none"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                        />
                    </svg>
                    {{ $t("subscribers.import.import_button") }}
                </Link>
                <Link
                    :href="route('subscribers.create')"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 sm:flex-none"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                        />
                    </svg>
                    {{ $t("subscribers.add_subscriber") }}
                </Link>
            </div>
        </template>

        <!-- Statistics Display -->
        <div class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            <template v-if="statistics.list_name">
                {{
                    t("subscribers.stats_list", {
                        total: statistics.total_in_list,
                        list: statistics.list_name,
                        current: subscribers.data.length,
                    })
                }}
            </template>
            <template v-else>
                {{
                    t("subscribers.stats_total", {
                        total: statistics.total_subscribers,
                        lists: statistics.total_lists,
                        current: subscribers.data.length,
                    })
                }}
            </template>
        </div>

        <!-- Filters -->
        <div
            class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div class="lg:col-span-2">
                <div class="relative">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                    >
                        <svg
                            class="h-5 w-5 text-slate-400"
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
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-4 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500"
                        :placeholder="$t('subscribers.search_placeholder')"
                    />
                </div>
            </div>
            <div>
                <select
                    v-model="listType"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <option value="">
                        {{ $t("subscribers.all_list_types") }}
                    </option>
                    <option value="email">
                        {{ $t("subscribers.list_type_email") }}
                    </option>
                    <option value="sms">
                        {{ $t("subscribers.list_type_sms") }}
                    </option>
                </select>
            </div>
            <div ref="listDropdownRef" class="relative">
                <button
                    @click="listDropdownOpen = !listDropdownOpen"
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-left text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <span class="truncate">
                        <template v-if="selectedListName">
                            {{ selectedListName }}
                            <span class="ml-1 text-xs text-slate-400"
                                >#{{ listId }}</span
                            >
                        </template>
                        <template v-else>
                            {{ $t("subscribers.all_lists") }}
                        </template>
                    </span>
                    <svg
                        class="ml-2 h-4 w-4 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </button>
                <div
                    v-show="listDropdownOpen"
                    class="absolute left-0 z-50 mt-1 w-72 rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="p-2">
                        <input
                            v-model="listSearch"
                            type="text"
                            class="w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-1.5 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
                            :placeholder="
                                $t('subscribers.search_list_placeholder')
                            "
                            @click.stop
                        />
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <button
                            @click="clearListFilter"
                            class="w-full px-3 py-2 text-left text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
                            :class="{
                                'bg-indigo-50 dark:bg-indigo-900/30': !listId,
                            }"
                        >
                            {{ $t("subscribers.all_lists") }}
                        </button>
                        <button
                            v-for="list in filteredLists"
                            :key="list.id"
                            @click="selectList(list)"
                            class="w-full px-3 py-2 text-left text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
                            :class="{
                                'bg-indigo-50 dark:bg-indigo-900/30':
                                    listId == list.id,
                            }"
                        >
                            <span class="font-medium">{{ list.name }}</span>
                            <span
                                class="ml-2 text-xs text-slate-400 dark:text-slate-500"
                                >#{{ list.id }}</span
                            >
                        </button>
                        <div
                            v-if="filteredLists.length === 0 && listSearch"
                            class="px-3 py-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("common.no_results") }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 sm:flex-nowrap">
                <!-- Per-page selector -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600 dark:text-slate-400"
                        >{{ $t("subscribers.per_page") }}:</label
                    >
                    <select
                        :value="perPage"
                        @change="updatePerPage($event.target.value)"
                        class="rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option :value="200">200</option>
                    </select>
                </div>
                <ColumnSettingsDropdown
                    :columns="visibleColumns"
                    :ordered-columns="orderedColumns"
                    @toggle="toggleColumn"
                    @reorder="reorderColumns"
                />
            </div>
        </div>

        <!-- Bulk Action Toolbar -->
        <BulkActionToolbar
            v-if="selectedIds.length > 0"
            :selected-count="selectedIds.length"
            :processing="processing"
            :current-list-id="listId"
            :is-selecting-all-in-list="isSelectingAllInList"
            @delete="bulkDelete"
            @move="showMoveModal = true"
            @copy="bulkCopy"
            @delete-from-list="bulkDeleteFromList"
            @change-status="bulkChangeStatus"
            @clear-selection="clearSelection"
            @select-all-in-list="selectAllInList"
        />

        <!-- Table -->
        <div
            class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-sm text-slate-500 dark:text-slate-400"
                >
                    <thead
                        class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <tr>
                            <!-- Checkbox column -->
                            <th scope="col" class="w-12 px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    :indeterminate="isSomeSelected"
                                    @change="toggleSelectAll"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                            </th>
                            <template
                                v-for="(column, columnIndex) in orderedColumns"
                                :key="column?.key || `th-${columnIndex}`"
                            >
                                <th
                                    v-if="column && isColumnVisible(column.key)"
                                    scope="col"
                                    class="px-6 py-3"
                                >
                                    <button
                                        v-if="column.sortable"
                                        @click="handleSort(column.sortKey)"
                                        class="group flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400"
                                    >
                                        {{ getColumnLabel(column) }}
                                        <svg
                                            class="h-4 w-4 transition-all"
                                            :class="getSortIcon(column.sortKey)"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </button>
                                    <span v-else>
                                        {{ getColumnLabel(column) }}
                                    </span>
                                </th>
                            </template>
                            <!-- Actions -->
                            <th scope="col" class="px-6 py-3 text-right">
                                {{ $t("subscribers.table.actions") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="subscriber in subscribers.data"
                            :key="subscriber.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            :class="{
                                'bg-indigo-50/50 dark:bg-indigo-900/20':
                                    selectedIds.includes(subscriber.id),
                            }"
                        >
                            <!-- Checkbox -->
                            <td class="w-12 px-4 py-4">
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedIds.includes(subscriber.id)
                                    "
                                    @change="toggleSelect(subscriber.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                            </td>
                            <template
                                v-for="(column, columnIndex) in orderedColumns"
                                :key="
                                    column?.key
                                        ? `td-${column.key}-${subscriber.id}`
                                        : `td-${columnIndex}-${subscriber.id}`
                                "
                            >
                                <td
                                    v-if="column && isColumnVisible(column.key)"
                                    :class="[
                                        'px-6 py-4',
                                        column.type === 'email'
                                            ? 'font-medium text-slate-900 dark:text-white'
                                            : '',
                                    ]"
                                >
                                    <template v-if="column.type === 'email'">
                                        <Link
                                            :href="
                                                route(
                                                    'subscribers.show',
                                                    subscriber.id,
                                                )
                                            "
                                            class="text-indigo-600 hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                            :title="$t('subscriber_card.title')"
                                        >
                                            {{ subscriber.email }}
                                        </Link>
                                    </template>
                                    <template
                                        v-else-if="column.type === 'name'"
                                    >
                                        {{ subscriber.first_name }}
                                        {{ subscriber.last_name }}
                                    </template>
                                    <template
                                        v-else-if="column.type === 'phone'"
                                    >
                                        {{ subscriber.phone || "-" }}
                                    </template>
                                    <template
                                        v-else-if="column.type === 'status'"
                                    >
                                        <span
                                            class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400':
                                                    subscriber.status ===
                                                    'active',
                                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400':
                                                    subscriber.status ===
                                                    'unsubscribed',
                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300':
                                                    subscriber.status ===
                                                    'inactive',
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400':
                                                    subscriber.status ===
                                                    'bounced',
                                            }"
                                        >
                                            {{
                                                $t(
                                                    `subscribers.statuses.${subscriber.status}`,
                                                ) || subscriber.status
                                            }}
                                        </span>
                                    </template>
                                    <template
                                        v-else-if="column.type === 'list'"
                                    >
                                        {{
                                            subscriber.lists &&
                                            subscriber.lists.length
                                                ? subscriber.lists.join(", ")
                                                : "-"
                                        }}
                                    </template>
                                    <template
                                        v-else-if="column.type === 'custom'"
                                    >
                                        {{
                                            subscriber.custom_fields?.[
                                                column.key
                                            ] || "-"
                                        }}
                                    </template>
                                    <template
                                        v-else-if="column.type === 'created_at'"
                                    >
                                        {{ subscriber.created_at }}
                                    </template>
                                    <template
                                        v-else-if="column.type === 'language'"
                                    >
                                        <span
                                            v-if="subscriber.language"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                                        >
                                            🌐
                                            {{
                                                subscriber.language?.toUpperCase()
                                            }}
                                        </span>
                                        <span v-else class="text-slate-400"
                                            >—</span
                                        >
                                    </template>
                                </td>
                            </template>
                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- View Card -->
                                    <Link
                                        :href="
                                            route(
                                                'subscribers.show',
                                                subscriber.id,
                                            )
                                        "
                                        class="text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400"
                                        :title="$t('subscriber_card.title')"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
                                            />
                                        </svg>
                                    </Link>
                                    <!-- Edit -->
                                    <Link
                                        :href="
                                            route(
                                                'subscribers.edit',
                                                subscriber.id,
                                            )
                                        "
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        :title="$t('common.edit')"
                                    >
                                        <svg
                                            class="h-5 w-5"
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
                                    <!-- Add to CRM -->
                                    <button
                                        @click="addToCrm(subscriber)"
                                        :class="[
                                            subscriber.has_crm_contact
                                                ? 'text-green-500 dark:text-green-400 cursor-default'
                                                : 'text-slate-400 hover:text-purple-600 dark:hover:text-purple-400',
                                        ]"
                                        :title="
                                            subscriber.has_crm_contact
                                                ? $t(
                                                      'subscribers.crm_already_exists',
                                                  )
                                                : $t('subscribers.add_to_crm')
                                        "
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="deleteSubscriber(subscriber)"
                                        class="text-slate-400 hover:text-red-600 dark:hover:text-red-400"
                                        :title="$t('common.delete')"
                                    >
                                        <svg
                                            class="h-5 w-5"
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
                        <tr v-if="subscribers.data.length === 0">
                            <td
                                :colspan="visibleOrderedColumns.length + 2"
                                class="px-6 py-8 text-center text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("subscribers.empty_state") }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="subscribers.links.length > 3"
                class="flex items-center justify-between border-t border-slate-100 px-6 py-4 dark:border-slate-800"
            >
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("common.showing") }} {{ subscribers.from }} -
                    {{ subscribers.to }} {{ $t("common.of") }}
                    {{ subscribers.total }}
                </div>
                <div class="flex gap-1">
                    <Link
                        v-for="(link, i) in subscribers.links"
                        :key="i"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1 text-sm"
                        :class="{
                            'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400':
                                link.active,
                            'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800':
                                !link.active && link.url,
                            'opacity-50 cursor-not-allowed': !link.url,
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Move Modal -->
        <MoveToListModal
            :show="showMoveModal"
            :selected-count="selectedIds.length"
            :lists="lists"
            :current-list-id="listId"
            :processing="processing"
            @close="showMoveModal = false"
            @move="bulkMove"
        />

        <!-- Copy To List Modal -->
        <CopyToListModal
            :show="showCopyModal"
            :selected-count="selectedIds.length"
            :lists="lists"
            :processing="processing"
            @close="showCopyModal = false"
            @submit="submitCopy"
        />

        <!-- Confirmation Modals -->
        <ConfirmModal
            :show="showDeleteConfirm"
            type="danger"
            :title="t('subscribers.bulk.delete_modal_title')"
            :message="
                t('subscribers.bulk.delete_modal_desc', {
                    count: selectedIds.length,
                })
            "
            :confirm-text="t('subscribers.bulk.delete')"
            :processing="processing"
            @close="showDeleteConfirm = false"
            @confirm="confirmBulkDelete"
        />

        <ConfirmModal
            :show="showDeleteFromListConfirm"
            type="warning"
            :title="t('subscribers.bulk.delete_from_list_modal_title')"
            :message="t('subscribers.bulk.delete_from_list_modal_desc')"
            :confirm-text="t('subscribers.bulk.delete_from_list')"
            :processing="processing"
            @close="showDeleteFromListConfirm = false"
            @confirm="confirmBulkDeleteFromList"
        />

        <ConfirmModal
            :show="showSelectAllConfirm"
            type="info"
            :title="t('subscribers.bulk.select_all_modal_title')"
            :message="
                t('subscribers.bulk.select_all_modal_desc', {
                    count: pendingSelectAllCount,
                })
            "
            :confirm-text="t('subscribers.confirm_proceed')"
            @close="
                showSelectAllConfirm = false;
                selectedIds = [];
            "
            @confirm="showSelectAllConfirm = false"
        />

        <!-- Delete Subscriber Modal -->
        <DeleteFromListModal
            :show="showDeleteSubscriberModal"
            :subscriber="subscriberToDelete"
            :lists="lists"
            :current-list-id="listId"
            :processing="processing"
            @close="
                showDeleteSubscriberModal = false;
                subscriberToDelete = null;
            "
            @delete-from-lists="handleDeleteFromLists"
            @delete-completely="handleDeleteCompletely"
        />

        <!-- Toast Notification -->
        <div v-if="toast" class="fixed bottom-4 right-4 z-50 max-w-sm w-full">
            <div
                :class="[
                    'rounded-lg shadow-lg p-4 flex items-center gap-3',
                    toast.success
                        ? 'bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-800'
                        : 'bg-amber-50 dark:bg-amber-900/50 border border-amber-200 dark:border-amber-800',
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
                    class="h-5 w-5 text-amber-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <span
                    :class="[
                        'text-sm font-medium',
                        toast.success
                            ? 'text-green-800 dark:text-green-200'
                            : 'text-amber-800 dark:text-amber-200',
                    ]"
                >
                    {{ toast.message }}
                </span>
                <button
                    @click="toast = null"
                    class="ml-auto text-gray-400 hover:text-gray-600"
                >
                    <svg
                        class="h-4 w-4"
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
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
