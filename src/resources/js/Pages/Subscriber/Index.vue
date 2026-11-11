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
import ExportModal from "./Partials/ExportModal.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import Panel from "@/Components/Ui/Panel.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import SortMark from "@/Components/Ui/SortMark.vue";

const { t, locale } = useI18n();

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

// Status as a word and a tone: who can be written to, who cannot, and why
const STATUS_TONES = {
    active: "success",
    inactive: "neutral",
    bounced: "danger",
    unsubscribed: "warning",
};

const props = defineProps({
    subscribers: Object,
    lists: Array,
    customFields: Array,
    statistics: Object,
    filters: Object,
    exportColumns: { type: Object, default: () => ({}) },
});

// Search and filter state
const search = ref(props.filters.search || "");
const listId = ref(props.filters.list_id || "");
const listType = ref(props.filters.list_type || "");
const statusFilter = ref(props.filters.status || "");

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

// Export modal state
const showExportModal = ref(false);

// Rows the export would cover when no explicit selection is made. The
// paginator total already reflects the search and list filters, so it is the
// honest number here — the statistics block counts the whole list.
const exportTotal = computed(() => props.subscribers?.total ?? 0);

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
            status: statusFilter.value,
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
    [search, listId, listType, statusFilter],
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

const sortState = (column) => (sortBy.value === column ? sortOrder.value : null);
const ariaSort = (column) =>
    sortBy.value === column ? (sortOrder.value === "asc" ? "ascending" : "descending") : "none";

// The tabs above the list: everyone, or one status
const statusOptions = computed(() => [
    { value: "", label: t("subscribers.views.all") },
    ...["active", "inactive", "bounced", "unsubscribed"].map((status) => ({
        value: status,
        label: t(`subscribers.statuses.${status}`),
    })),
]);

const hasFilters = computed(() => Boolean(search.value || listId.value || listType.value));

// Clears everything that narrows the list, the status tab included
const resetFilters = () => {
    search.value = "";
    listId.value = "";
    listType.value = "";
    statusFilter.value = "";
    listSearch.value = "";
};
</script>

<template>
    <Head :title="$t('subscribers.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('subscribers.title')" :subtitle="$t('subscribers.subtitle')">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                    @click="showExportModal = true"
                >
                    {{ $t("subscribers.export.export_button") }}
                </button>
                <Link
                    :href="route('subscribers.import')"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                >
                    {{ $t("subscribers.import.import_button") }}
                </Link>
                <Link
                    :href="route('subscribers.create')"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                >
                    <svg
                        aria-hidden="true"
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        stroke-linecap="round"
                    >
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    {{ $t("subscribers.add_subscriber") }}
                </Link>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                <SegmentedControl
                    v-model="statusFilter"
                    :options="statusOptions"
                    :label="$t('subscribers.table.status')"
                />
                <!-- How many people this is about: the whole base, or the list being looked at -->
                <p class="text-sm tabular-nums text-slate-600 dark:text-slate-400">
                    <template v-if="statistics.list_name">
                        {{
                            t("subscribers.stats_list", {
                                total: number(statistics.total_in_list),
                                list: statistics.list_name,
                                current: subscribers.data.length,
                            })
                        }}
                    </template>
                    <template v-else>
                        {{
                            t("subscribers.stats_total", {
                                total: number(statistics.total_subscribers),
                                lists: statistics.total_lists,
                                current: subscribers.data.length,
                            })
                        }}
                    </template>
                </p>
            </div>

            <Panel flush>
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <label class="relative min-w-0 flex-1 basis-64">
                        <span class="sr-only">{{ $t("subscribers.search_placeholder") }}</span>
                        <svg
                            aria-hidden="true"
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m21 21-4.3-4.3" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            :placeholder="$t('subscribers.search_placeholder')"
                            class="min-h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                        />
                    </label>

                    <select
                        v-model="listType"
                        :aria-label="$t('subscribers.all_list_types')"
                        class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                    >
                        <option value="">{{ $t("subscribers.all_list_types") }}</option>
                        <option value="email">{{ $t("subscribers.list_type_email") }}</option>
                        <option value="sms">{{ $t("subscribers.list_type_sms") }}</option>
                    </select>

                    <!-- List filter: a searchable list, there can be dozens -->
                    <div ref="listDropdownRef" class="relative">
                        <button
                            type="button"
                            aria-haspopup="listbox"
                            :aria-expanded="listDropdownOpen"
                            class="flex min-h-10 max-w-64 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 transition-colors hover:border-slate-400 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:hover:border-slate-500"
                            @click="listDropdownOpen = !listDropdownOpen"
                        >
                            <span class="truncate">{{
                                selectedListName || $t("subscribers.all_lists")
                            }}</span>
                            <svg
                                aria-hidden="true"
                                class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            v-show="listDropdownOpen"
                            class="absolute left-0 z-30 mt-1.5 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                        >
                            <input
                                v-model="listSearch"
                                type="search"
                                :placeholder="$t('subscribers.search_list_placeholder')"
                                :aria-label="$t('subscribers.search_list_placeholder')"
                                class="mb-1 min-h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                                @click.stop
                            />
                            <div role="listbox" class="max-h-64 overflow-y-auto">
                                <button
                                    type="button"
                                    role="option"
                                    :aria-selected="!listId"
                                    class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60"
                                    @click="clearListFilter"
                                >
                                    {{ $t("subscribers.all_lists") }}
                                </button>
                                <button
                                    v-for="list in filteredLists"
                                    :key="list.id"
                                    type="button"
                                    role="option"
                                    :aria-selected="listId == list.id"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700/60"
                                    :class="
                                        listId == list.id
                                            ? 'font-semibold text-slate-900 dark:text-white'
                                            : 'text-slate-700 dark:text-slate-200'
                                    "
                                    @click="selectList(list)"
                                >
                                    <span class="truncate">{{ list.name }}</span>
                                    <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400"
                                        >#{{ list.id }}</span
                                    >
                                </button>
                                <p
                                    v-if="filteredLists.length === 0 && listSearch"
                                    class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400"
                                >
                                    {{ $t("common.no_results") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("common.clear_filters") }}
                    </button>

                    <div class="ml-auto">
                        <ColumnSettingsDropdown
                            :columns="visibleColumns"
                            :ordered-columns="orderedColumns"
                            @toggle="toggleColumn"
                            @reorder="reorderColumns"
                        />
                    </div>
                </div>

                <!-- What can be done with the ticked rows -->
                <BulkActionToolbar
                    v-if="selectedIds.length > 0"
                    :selected-count="selectedIds.length"
                    :processing="processing"
                    :current-list-id="listId"
                    :is-selecting-all-in-list="isSelectingAllInList"
                    @delete="bulkDelete"
                    @move="showMoveModal = true"
                    @copy="bulkCopy"
                    @export="showExportModal = true"
                    @delete-from-list="bulkDeleteFromList"
                    @change-status="bulkChangeStatus"
                    @clear-selection="clearSelection"
                    @select-all-in-list="selectAllInList"
                />

                <!-- Nothing to show -->
                <div
                    v-if="subscribers.data.length === 0"
                    class="flex flex-col items-center gap-2 border-t border-slate-200 px-5 py-16 text-center dark:border-slate-700/60"
                >
                    <p class="text-sm font-medium text-slate-900 dark:text-white">
                        {{ $t("subscribers.empty_state") }}
                    </p>
                    <button
                        v-if="hasFilters || statusFilter"
                        type="button"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("common.clear_filters") }}
                    </button>
                </div>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                <th scope="col" class="w-10 py-2.5 pl-5 pr-2">
                                    <input
                                        type="checkbox"
                                        :aria-label="$t('common.select_all')"
                                        :checked="isAllSelected"
                                        :indeterminate="isSomeSelected"
                                        class="h-4 w-4 accent-indigo-600"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <template
                                    v-for="(column, columnIndex) in orderedColumns"
                                    :key="column?.key || `th-${columnIndex}`"
                                >
                                    <th
                                        v-if="column && isColumnVisible(column.key)"
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                        :aria-sort="column.sortable ? ariaSort(column.sortKey) : undefined"
                                    >
                                        <button
                                            v-if="column.sortable"
                                            type="button"
                                            class="inline-flex items-center gap-1 whitespace-nowrap hover:text-slate-900 dark:hover:text-white"
                                            @click="handleSort(column.sortKey)"
                                        >
                                            {{ getColumnLabel(column) }}
                                            <SortMark :state="sortState(column.sortKey)" />
                                        </button>
                                        <span v-else class="whitespace-nowrap">{{ getColumnLabel(column) }}</span>
                                    </th>
                                </template>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("subscribers.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="subscriber in subscribers.data"
                                :key="subscriber.id"
                                class="border-b border-slate-100 transition-colors last:border-0 dark:border-slate-700/40"
                                :class="
                                    selectedIds.includes(subscriber.id)
                                        ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-700/20'
                                "
                            >
                                <td class="w-10 py-3 pl-5 pr-2">
                                    <input
                                        type="checkbox"
                                        :aria-label="subscriber.email"
                                        :checked="selectedIds.includes(subscriber.id)"
                                        class="h-4 w-4 accent-indigo-600"
                                        @change="toggleSelect(subscriber.id)"
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
                                        class="px-3 py-3"
                                        :class="column.type === 'email' || column.type === 'list' ? 'max-w-64' : ''"
                                    >
                                        <Link
                                            v-if="column.type === 'email'"
                                            :href="route('subscribers.show', subscriber.id)"
                                            :title="subscriber.email"
                                            class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        >
                                            {{ subscriber.email || subscriber.phone || "—" }}
                                        </Link>
                                        <span
                                            v-else-if="column.type === 'name'"
                                            class="text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                [subscriber.first_name, subscriber.last_name]
                                                    .filter(Boolean)
                                                    .join(" ") || "—"
                                            }}
                                        </span>
                                        <span
                                            v-else-if="column.type === 'phone'"
                                            class="whitespace-nowrap tabular-nums text-slate-900 dark:text-slate-100"
                                        >
                                            {{ subscriber.phone || "—" }}
                                        </span>
                                        <StatusPill
                                            v-else-if="column.type === 'status'"
                                            :tone="STATUS_TONES[subscriber.status] || 'neutral'"
                                        >
                                            {{
                                                $te(`subscribers.statuses.${subscriber.status}`)
                                                    ? $t(`subscribers.statuses.${subscriber.status}`)
                                                    : subscriber.status
                                            }}
                                        </StatusPill>
                                        <span
                                            v-else-if="column.type === 'list'"
                                            class="block truncate text-slate-600 dark:text-slate-400"
                                            :title="(subscriber.lists || []).join(', ')"
                                        >
                                            {{
                                                subscriber.lists && subscriber.lists.length
                                                    ? subscriber.lists.join(", ")
                                                    : "—"
                                            }}
                                        </span>
                                        <span
                                            v-else-if="column.type === 'custom'"
                                            class="text-slate-900 dark:text-slate-100"
                                        >
                                            {{ subscriber.custom_fields?.[column.key] || "—" }}
                                        </span>
                                        <span
                                            v-else-if="column.type === 'created_at'"
                                            class="whitespace-nowrap tabular-nums text-slate-600 dark:text-slate-400"
                                        >
                                            {{ subscriber.created_at }}
                                        </span>
                                        <span
                                            v-else-if="column.type === 'language'"
                                            class="text-slate-900 dark:text-slate-100"
                                        >
                                            {{ subscriber.language ? subscriber.language.toUpperCase() : "—" }}
                                        </span>
                                    </td>
                                </template>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end">
                                        <ActionMenu :label="$t('subscribers.actions_for', { email: subscriber.email || subscriber.phone })">
                                            <ActionMenuItem :href="route('subscribers.show', subscriber.id)">
                                                {{ $t("subscriber_card.title") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem :href="route('subscribers.edit', subscriber.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                :disabled="subscriber.has_crm_contact"
                                                @click="addToCrm(subscriber)"
                                            >
                                                {{
                                                    subscriber.has_crm_contact
                                                        ? $t("subscribers.crm_already_exists")
                                                        : $t("subscribers.add_to_crm")
                                                }}
                                            </ActionMenuItem>
                                            <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                            <ActionMenuItem danger @click="deleteSubscriber(subscriber)">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination
                    v-if="subscribers.total > 0"
                    :paginator="subscribers"
                    :per-page="perPage"
                    :per-page-options="[10, 15, 25, 50, 100, 200]"
                    @update:per-page="updatePerPage"
                />
            </Panel>
        </div>

        <MoveToListModal
            :show="showMoveModal"
            :selected-count="selectedIds.length"
            :lists="lists"
            :current-list-id="listId"
            :processing="processing"
            @close="showMoveModal = false"
            @move="bulkMove"
        />

        <ExportModal
            :show="showExportModal"
            :filters="filters"
            :selected-ids="selectedIds"
            :lists="lists"
            :export-columns="exportColumns"
            :export-url="route('subscribers.export')"
            :total-count="exportTotal"
            @close="showExportModal = false"
        />

        <CopyToListModal
            :show="showCopyModal"
            :selected-count="selectedIds.length"
            :lists="lists"
            :processing="processing"
            @close="showCopyModal = false"
            @submit="submitCopy"
        />

        <ConfirmModal
            :show="showDeleteConfirm"
            type="danger"
            :title="t('subscribers.bulk.delete_modal_title')"
            :message="t('subscribers.bulk.delete_modal_desc', { count: selectedIds.length })"
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
            :message="t('subscribers.bulk.select_all_modal_desc', { count: pendingSelectAllCount })"
            :confirm-text="t('subscribers.confirm_proceed')"
            @close="
                showSelectAllConfirm = false;
                selectedIds = [];
            "
            @confirm="showSelectAllConfirm = false"
        />

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

        <!-- What the last action did -->
        <Teleport to="body">
            <Transition
                enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    role="status"
                    class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-lg dark:bg-slate-800 dark:text-white"
                    :class="
                        toast.success
                            ? 'border-emerald-200 dark:border-emerald-500/30'
                            : 'border-rose-200 dark:border-rose-500/30'
                    "
                >
                    <span
                        aria-hidden="true"
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="toast.success ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-rose-600 dark:bg-rose-400'"
                    ></span>
                    <span class="min-w-0 flex-1">{{ toast.message }}</span>
                    <button
                        type="button"
                        :aria-label="$t('common.close')"
                        class="-mr-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700/60"
                        @click="toast = null"
                    >
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
