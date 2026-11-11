<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
    ref,
    watch,
    reactive,
    onMounted,
    onBeforeUnmount,
    computed,
    nextTick,
} from "vue";
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import QueueStatsModal from "@/Components/QueueStatsModal.vue";
import Panel from "@/Components/Ui/Panel.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import SortMark from "@/Components/Ui/SortMark.vue";
import { useI18n } from "vue-i18n";
import throttle from "lodash/throttle";
import pickBy from "lodash/pickBy";
import axios from "axios";

const props = defineProps({
    messages: Object,
    filters: Object,
    lists: Array,
    groups: Array,
    tags: Array,
    campaignPlans: Array,
    // How many messages each tab holds: { all, draft, scheduled, sent, autoresponder }
    counts: { type: Object, default: () => ({}) },
});

const { t, locale } = useI18n();

// With no filter in the URL, PHP sends an empty list, and a list has a `sort` of its own
// (Array.prototype.sort): reading filters.sort from it gave a function, which ended up in the URL
const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    type: filters.type || "",
    status: filters.status || "",
    list_id: filters.list_id || "",
    group_id: filters.group_id || "",
    tag_id: filters.tag_id || "",
    campaign_plan_id: filters.campaign_plan_id || "",
    // "effective_date" is the server's default: sent or scheduled time, else creation time
    sort: filters.sort || "effective_date",
    direction: filters.direction || "desc",
    per_page: filters.per_page || "30",
});

const messageToDelete = ref(null);
const messageToDuplicate = ref(null);
const duplicatedMessage = ref(null);
const isDuplicating = ref(false);
const togglingMessages = ref(new Set()); // Track which messages are being toggled
const resendingMessages = ref(new Set()); // Track which messages are being resent
const messageToResend = ref(null);
const resendResult = ref(null);
const messageForQueueStats = ref(null);

// Toast notification state
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 5000);
};

// Highlighted message state (for newly created/scheduled messages)
const highlightedMessageId = ref(null);

// List filter search
const listSearch = ref("");
const listDropdownOpen = ref(false);
const listDropdownRef = ref(null);

const filteredLists = computed(() => {
    if (!listSearch.value) return props.lists;
    const search = listSearch.value.toLowerCase();
    return props.lists.filter(
        (list) =>
            list.name.toLowerCase().includes(search) ||
            String(list.id).includes(search),
    );
});

const selectedListName = computed(() => {
    if (!form.list_id) return null;
    const list = props.lists.find((l) => l.id == form.list_id);
    return list ? list.name : null;
});

const selectList = (list) => {
    form.list_id = list.id;
    listSearch.value = "";
    listDropdownOpen.value = false;
};

const clearListFilter = () => {
    form.list_id = "";
    listSearch.value = "";
    listDropdownOpen.value = false;
};

const handleClickOutsideListDropdown = (event) => {
    if (
        listDropdownRef.value &&
        !listDropdownRef.value.contains(event.target)
    ) {
        listDropdownOpen.value = false;
    }
};

// Reactive local state for is_active (to update UI immediately)
const localActiveStates = reactive({});

// Reactive local state for statuses (for real-time updates)
const localStatuses = reactive({});

// Reactive local state for progressively loaded recipient counts
const localRecipientCounts = reactive({});

// Polling interval reference
let statusPollingInterval = null;
let progressiveLoadingInterval = null;

// Initialize local states from props
const getIsActive = (message) => {
    if (localActiveStates[message.id] !== undefined) {
        return localActiveStates[message.id];
    }
    return message.is_active ?? true;
};

// Get message status (with local override for real-time updates)
const getStatus = (message) => {
    return localStatuses[message.id] || message.status;
};

// Fetch updated statuses for scheduled messages
const fetchStatuses = async () => {
    const scheduledMessages = props.messages.data.filter(
        (m) => m.status === "scheduled" && !localStatuses[m.id],
    );

    if (scheduledMessages.length === 0) return;

    try {
        const ids = scheduledMessages.map((m) => m.id);
        const response = await axios.get(route("messages.statuses"), {
            params: { ids: ids.join(",") },
        });

        if (response.data && Array.isArray(response.data)) {
            response.data.forEach((msg) => {
                if (msg.status && msg.status !== "scheduled") {
                    localStatuses[msg.id] = msg.status;
                }
            });
        }
    } catch (error) {
        // Silently fail - polling will retry
    }
};

// Get recipient count (with local override from progressive loading)
const getRecipientsCount = (message) => {
    if (localRecipientCounts[message.id]?.recipients_count !== undefined) {
        return localRecipientCounts[message.id].recipients_count;
    }
    return message.recipients_count;
};

// Get skipped count (with local override from progressive loading)
const getSkippedCount = (message) => {
    if (localRecipientCounts[message.id]?.skipped_count !== undefined) {
        return localRecipientCounts[message.id].skipped_count;
    }
    return message.skipped_count;
};

// Get queue stats for autoresponder messages
const getQueueStats = (message) => {
    return localRecipientCounts[message.id]?.queue_stats ?? null;
};

// Progressive loading of recipient counts
// Messages that need stats: draft/scheduled (need accurate count) and autoresponders (need skipped_count)
const messagesNeedingStats = ref([]);
let progressiveLoadingIndex = 0;

const fetchRecipientCounts = async (messageIds) => {
    if (messageIds.length === 0) return;

    try {
        const response = await axios.get(route("messages.recipient-counts"), {
            params: { ids: messageIds.join(",") },
        });

        if (response.data && Array.isArray(response.data)) {
            response.data.forEach((msg) => {
                localRecipientCounts[msg.id] = {
                    recipients_count: msg.recipients_count,
                    skipped_count: msg.skipped_count,
                    queue_stats: msg.queue_stats ?? null,
                };
            });
        }
    } catch (error) {
        // Silently fail
    }
};

const startProgressiveLoading = () => {
    // Identify messages that need accurate stats
    messagesNeedingStats.value = props.messages.data
        .filter(
            (m) =>
                // Draft/scheduled broadcasts need accurate recipient count
                (m.type === "broadcast" &&
                    (m.status === "draft" || m.status === "scheduled")) ||
                // Autoresponders need skipped_count
                m.type === "autoresponder",
        )
        .map((m) => m.id);

    if (messagesNeedingStats.value.length === 0) return;

    // Load first 5 immediately
    const firstBatch = messagesNeedingStats.value.slice(0, 5);
    fetchRecipientCounts(firstBatch);
    progressiveLoadingIndex = 5;

    // Load rest progressively every 2 seconds
    if (messagesNeedingStats.value.length > 5) {
        progressiveLoadingInterval = setInterval(() => {
            const nextBatch = messagesNeedingStats.value.slice(
                progressiveLoadingIndex,
                progressiveLoadingIndex + 5,
            );

            if (nextBatch.length === 0) {
                clearInterval(progressiveLoadingInterval);
                progressiveLoadingInterval = null;
                return;
            }

            fetchRecipientCounts(nextBatch);
            progressiveLoadingIndex += 5;
        }, 2000);
    }
};

// Setup polling on mount
onMounted(() => {
    // Start polling if there are scheduled messages
    const hasScheduled = props.messages.data.some(
        (m) => m.status === "scheduled",
    );
    if (hasScheduled) {
        statusPollingInterval = setInterval(fetchStatuses, 15000); // Every 15 seconds
        fetchStatuses(); // Initial fetch
    }

    // Start progressive loading of recipient counts
    startProgressiveLoading();

    // Click outside handler for list dropdown
    document.addEventListener("click", handleClickOutsideListDropdown);

    // Handle highlight from flash session
    const page = usePage();
    const flashHighlightId = page.props.flash?.highlight_message_id;
    const flashSuccess = page.props.flash?.success;

    if (flashHighlightId) {
        highlightedMessageId.value = flashHighlightId;

        // Show toast notification
        if (flashSuccess) {
            showToast(flashSuccess, true);
        }

        // Scroll to highlighted message after a short delay
        nextTick(() => {
            const highlightedRow = document.querySelector(
                `[data-message-id="${flashHighlightId}"]`,
            );
            if (highlightedRow) {
                highlightedRow.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
            }
        });

        // Remove highlight after 6 seconds
        setTimeout(() => {
            highlightedMessageId.value = null;
        }, 6000);
    }
});

// Cleanup on unmount
onBeforeUnmount(() => {
    if (statusPollingInterval) {
        clearInterval(statusPollingInterval);
    }
    if (progressiveLoadingInterval) {
        clearInterval(progressiveLoadingInterval);
    }
    document.removeEventListener("click", handleClickOutsideListDropdown);
});

watch(
    form,
    throttle(() => {
        router.get(route("messages.index"), pickBy(form), {
            preserveState: true,
            replace: true,
        });
    }, 300),
);

// A second click on the sorted column turns it around; a new column starts in its natural order
const sort = (field) => {
    if (form.sort === field) {
        form.direction = form.direction === "asc" ? "desc" : "asc";
    } else {
        form.sort = field;
        form.direction = field === "subject" ? "asc" : "desc";
    }
};

const sortState = (field) => (form.sort === field ? form.direction : null);
const ariaSort = (field) =>
    form.sort === field ? (form.direction === "asc" ? "ascending" : "descending") : "none";

// The tabs above the list are one choice that sets two filters: type and, for broadcasts, status
const view = computed({
    get() {
        if (form.type === "autoresponder") return "autoresponder";
        if (form.type === "broadcast" && ["draft", "scheduled", "sent"].includes(form.status)) {
            return form.status;
        }
        return "all";
    },
    set(value) {
        if (value === "all") {
            form.type = "";
            form.status = "";
        } else if (value === "autoresponder") {
            form.type = "autoresponder";
            form.status = "";
        } else {
            form.type = "broadcast";
            form.status = value;
        }
    },
});

const viewOptions = computed(() =>
    ["all", "draft", "scheduled", "sent", "autoresponder"].map((value) => ({
        value,
        label: t(`messages.views.${value}`),
        count: props.counts?.[value] ?? 0,
    })),
);

// Group, tag and campaign are used rarely: they stay folded until one of them is set
const moreFiltersCount = computed(
    () => [form.group_id, form.tag_id, form.campaign_plan_id].filter(Boolean).length,
);
const moreFiltersOpen = ref(moreFiltersCount.value > 0);

const hasFilters = computed(() => Boolean(form.search || form.list_id) || moreFiltersCount.value > 0);

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);
const formatCount = (value) => (value === null || value === undefined ? "—" : number(value));

const audienceLabel = (message) => {
    const names = message.list_names || [];
    if (names.length === 0) return t("messages.no_list");
    if (names.length === 1) return names[0];
    return t("messages.lists_count", { count: names.length });
};

const confirmDeleteMessage = (message) => {
    messageToDelete.value = message;
};

const deleteMessage = () => {
    if (messageToDelete.value) {
        router.delete(route("messages.destroy", messageToDelete.value.id), {
            onSuccess: () => (messageToDelete.value = null),
        });
    }
};

// Clears what narrows the list; the chosen tab and the sort order stay
const resetFilters = () => {
    form.search = "";
    form.list_id = "";
    form.group_id = "";
    form.tag_id = "";
    form.campaign_plan_id = "";
};

const closeModal = () => {
    messageToDelete.value = null;
};

// Duplicate functionality
const confirmDuplicateMessage = (message) => {
    messageToDuplicate.value = message;
};

const duplicateMessage = async () => {
    if (!messageToDuplicate.value || isDuplicating.value) return;

    isDuplicating.value = true;
    try {
        const response = await axios.post(
            route("messages.duplicate", messageToDuplicate.value.id),
        );
        if (response.data.success) {
            duplicatedMessage.value = response.data;
        }
    } catch (error) {
        console.error("Duplicate failed:", error);
    } finally {
        isDuplicating.value = false;
    }
};

const goToEditDuplicate = () => {
    if (duplicatedMessage.value?.redirect_url) {
        router.visit(duplicatedMessage.value.redirect_url);
    }
};

const closeDuplicateModal = () => {
    messageToDuplicate.value = null;
    duplicatedMessage.value = null;
};

const stayOnList = () => {
    closeDuplicateModal();
    router.reload();
};

// Toggle active status
const toggleActive = async (message) => {
    if (togglingMessages.value.has(message.id)) return;

    togglingMessages.value.add(message.id);
    try {
        const response = await axios.post(
            route("messages.toggle-active", message.id),
        );
        if (response.data.success) {
            localActiveStates[message.id] = response.data.is_active;
        }
    } catch (error) {
        console.error("Toggle failed:", error);
    } finally {
        togglingMessages.value.delete(message.id);
    }
};

// Resend functionality
const confirmResendMessage = (message) => {
    messageToResend.value = message;
    resendResult.value = null;
};

const resendMessage = async () => {
    if (
        !messageToResend.value ||
        resendingMessages.value.has(messageToResend.value.id)
    )
        return;

    resendingMessages.value.add(messageToResend.value.id);
    try {
        const response = await axios.post(
            route("messages.resend", messageToResend.value.id),
        );
        resendResult.value = response.data;
    } catch (error) {
        resendResult.value = {
            success: false,
            message:
                error.response?.data?.message || t("messages.resend_failed"),
        };
    } finally {
        resendingMessages.value.delete(messageToResend.value.id);
    }
};

const closeResendModal = () => {
    messageToResend.value = null;
    resendResult.value = null;
    if (resendResult.value?.success) {
        router.reload();
    }
};

const closeResendAndReload = () => {
    messageToResend.value = null;
    resendResult.value = null;
    router.reload();
};

// A queue is on or off; a broadcast is a draft, scheduled or sent
const statusOf = (message) => {
    if (message.type === "autoresponder") {
        return getIsActive(message)
            ? { tone: "success", label: t("messages.status_active") }
            : { tone: "neutral", label: t("messages.status_inactive") };
    }

    const status = getStatus(message);
    if (status === "sent") return { tone: "success", label: t("messages.status_sent") };
    if (status === "scheduled") return { tone: "info", label: t("messages.status_scheduled") };
    return { tone: "neutral", label: t("messages.status_draft") };
};

// One date per row, the one that matters for its state
const dateOf = (message) => {
    const status = getStatus(message);

    if (message.type !== "autoresponder" && message.scheduled_at) {
        if (status === "sent") {
            return { value: message.scheduled_at, label: t("messages.date_sent") };
        }
        if (status === "scheduled") {
            return { value: message.scheduled_at, label: t("messages.date_scheduled") };
        }
    }

    return { value: message.created_at, label: t("messages.date_created") };
};
</script>

<template>
    <Head :title="$t('messages.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('messages.title')" :subtitle="$t('messages.subtitle')">
                <Link
                    :href="route('messages.create')"
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
                    {{ $t("messages.new_message") }}
                </Link>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <!-- What kind of message: the question people come to this list with -->
            <SegmentedControl
                v-model="view"
                :options="viewOptions"
                :label="$t('messages.views.label')"
                class="self-start"
            />

            <Panel flush>
                <!-- Filters -->
                <div class="flex flex-col gap-3 px-5 pb-4 pt-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="relative min-w-0 flex-1 basis-64">
                            <span class="sr-only">{{ $t("messages.search_placeholder") }}</span>
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
                                v-model="form.search"
                                type="search"
                                :placeholder="$t('messages.search_placeholder')"
                                class="min-h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                            />
                        </label>

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
                                    selectedListName || $t("messages.all_lists")
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
                                v-if="listDropdownOpen"
                                class="absolute left-0 z-30 mt-1.5 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                            >
                                <input
                                    v-model="listSearch"
                                    type="search"
                                    :placeholder="$t('messages.list_filter_placeholder')"
                                    :aria-label="$t('messages.list_filter_placeholder')"
                                    class="mb-1 min-h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                                    @click.stop
                                />
                                <div role="listbox" class="max-h-64 overflow-y-auto">
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="!form.list_id"
                                        class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60"
                                        @click="clearListFilter"
                                    >
                                        {{ $t("messages.all_lists") }}
                                    </button>
                                    <button
                                        v-for="list in filteredLists"
                                        :key="list.id"
                                        type="button"
                                        role="option"
                                        :aria-selected="form.list_id == list.id"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700/60"
                                        :class="
                                            form.list_id == list.id
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
                            type="button"
                            :aria-expanded="moreFiltersOpen"
                            aria-controls="message-more-filters"
                            class="flex min-h-10 items-center gap-2 rounded-xl border px-3 text-sm font-medium transition-colors"
                            :class="
                                moreFiltersOpen || moreFiltersCount
                                    ? 'border-slate-400 bg-slate-50 text-slate-900 dark:border-slate-500 dark:bg-slate-700/60 dark:text-white'
                                    : 'border-slate-300 text-slate-700 hover:border-slate-400 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-500'
                            "
                            @click="moreFiltersOpen = !moreFiltersOpen"
                        >
                            <svg
                                aria-hidden="true"
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4" />
                            </svg>
                            {{ $t("messages.filters.more") }}
                            <span
                                v-if="moreFiltersCount"
                                class="rounded-full bg-slate-900 px-1.5 text-xs font-semibold tabular-nums text-white dark:bg-white dark:text-slate-900"
                                >{{ moreFiltersCount }}</span
                            >
                        </button>

                        <button
                            v-if="hasFilters"
                            type="button"
                            class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="resetFilters"
                        >
                            {{ $t("messages.filters.clear") }}
                        </button>
                    </div>

                    <div
                        v-show="moreFiltersOpen"
                        id="message-more-filters"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-3"
                    >
                        <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t("messages.filters.group") }}
                            <select
                                v-model="form.group_id"
                                class="min-h-10 rounded-xl border border-slate-300 bg-white text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                            >
                                <option value="">{{ $t("messages.all_groups") }}</option>
                                <option v-for="group in groups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t("messages.filters.tag") }}
                            <select
                                v-model="form.tag_id"
                                class="min-h-10 rounded-xl border border-slate-300 bg-white text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                            >
                                <option value="">{{ $t("messages.all_tags") }}</option>
                                <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                                    {{ tag.name }}
                                </option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t("messages.filters.campaign") }}
                            <select
                                v-model="form.campaign_plan_id"
                                class="min-h-10 rounded-xl border border-slate-300 bg-white text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                            >
                                <option value="">{{ $t("messages.all_campaigns") }}</option>
                                <option v-for="plan in campaignPlans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <!-- Nothing to show -->
                <div
                    v-if="messages.data.length === 0"
                    class="flex flex-col items-center gap-2 border-t border-slate-200 px-5 py-16 text-center dark:border-slate-700/60"
                >
                    <p class="text-sm font-medium text-slate-900 dark:text-white">
                        {{ hasFilters || view !== "all" ? $t("messages.empty_title") : $t("messages.empty_first") }}
                    </p>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("messages.filters.clear") }}
                    </button>
                    <Link
                        v-else-if="view === 'all'"
                        :href="route('messages.create')"
                        class="mt-2 inline-flex min-h-10 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
                    >
                        {{ $t("messages.new_message") }}
                    </Link>
                </div>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full sm:min-w-[820px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                <th
                                    scope="col"
                                    class="px-5 py-2.5 font-medium"
                                    :aria-sort="ariaSort('subject')"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('subject')">
                                        {{ $t("messages.table.message") }}
                                        <SortMark :state="sortState('subject')" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("messages.table.audience") }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 font-medium max-sm:hidden"
                                    :aria-sort="ariaSort('status')"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('status')">
                                        {{ $t("messages.table.status") }}
                                        <SortMark :state="sortState('status')" />
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2.5 font-medium max-sm:hidden"
                                    :aria-sort="ariaSort('effective_date')"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('effective_date')">
                                        {{ $t("common.date") }}
                                        <SortMark :state="sortState('effective_date')" />
                                    </button>
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("messages.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="message in messages.data"
                                :key="message.id"
                                :data-message-id="message.id"
                                class="border-b border-slate-100 transition-colors last:border-0 dark:border-slate-700/40"
                                :class="
                                    highlightedMessageId === message.id
                                        ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-700/20'
                                "
                            >
                                <!-- Message -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[44%]">
                                    <Link
                                        :href="route('messages.edit', message.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="message.subject"
                                    >
                                        {{ message.subject || $t("messages.no_subject") }}
                                    </Link>
                                    <p
                                        v-if="message.preheader"
                                        class="truncate text-slate-600 dark:text-slate-400"
                                    >
                                        {{ message.preheader }}
                                    </p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-600 dark:text-slate-400">
                                        <span class="tabular-nums">#{{ message.id }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>
                                            {{
                                                message.type === "broadcast"
                                                    ? $t("messages.type_broadcast")
                                                    : $t("messages.type_autoresponder")
                                            }}
                                            <template v-if="message.type === 'autoresponder'">
                                                {{ $t("messages.type_autoresponder_day", { day: message.day }) }}
                                            </template>
                                        </span>
                                        <template v-if="message.pdf_attachments?.length">
                                            <span aria-hidden="true">·</span>
                                            <span :title="message.pdf_attachments.join('\n')">
                                                {{ $t("messages.has_pdf_attachments", { count: message.pdf_attachments.length }) }}
                                            </span>
                                        </template>
                                        <template v-if="message.ab_test">
                                            <span aria-hidden="true">·</span>
                                            <Link
                                                :href="route('ab-tests.show', message.ab_test.id)"
                                                :title="$t('messages.ab_test_view_results')"
                                                class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >
                                                {{ $t("messages.ab_test_badge") }}
                                                <template v-if="message.ab_test.status === 'running'">
                                                    ({{ $t("messages.ab_test_running") }})
                                                </template>
                                            </Link>
                                        </template>
                                    </p>
                                    <!-- On a phone the status has no column of its own: it sits under the name -->
                                    <div class="mt-1.5 sm:hidden">
                                        <StatusPill :tone="statusOf(message).tone">
                                            {{ statusOf(message).label }}
                                        </StatusPill>
                                    </div>
                                </td>

                                <!-- Audience -->
                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 22%">
                                    <p
                                        class="truncate text-slate-900 dark:text-slate-100"
                                        :title="(message.list_names || []).join(', ')"
                                    >
                                        {{ audienceLabel(message) }}
                                    </p>
                                    <p class="flex flex-wrap gap-x-2 text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                        <!-- A queue reports progress: sent of all, and what is still waiting -->
                                        <template v-if="message.type === 'autoresponder' && getQueueStats(message)">
                                            <span>
                                                {{ $t("messages.queue_sent_count", { count: number(getQueueStats(message).sent) }) }}
                                                / {{ formatCount(getRecipientsCount(message)) }}
                                            </span>
                                            <span v-if="getQueueStats(message).planned > 0">
                                                {{ $t("messages.queue_planned_count", { count: number(getQueueStats(message).planned) }) }}
                                            </span>
                                            <span v-if="getQueueStats(message).queued > 0">
                                                {{ $t("messages.queue_queued_count", { count: number(getQueueStats(message).queued) }) }}
                                            </span>
                                            <span
                                                v-if="getQueueStats(message).failed > 0"
                                                class="font-semibold text-rose-700 dark:text-rose-300"
                                            >
                                                {{ $t("messages.queue_failed_count", { count: number(getQueueStats(message).failed) }) }}
                                            </span>
                                        </template>
                                        <span v-else>
                                            {{ formatCount(getRecipientsCount(message)) }}
                                            {{ $t("messages.recipients") }}
                                        </span>
                                        <span
                                            v-if="message.type === 'autoresponder' && getSkippedCount(message) > 0"
                                            class="font-semibold text-amber-800 dark:text-amber-300"
                                            :title="$t('messages.skipped_hint')"
                                        >
                                            {{ $t("messages.queue_missed_count", { count: number(getSkippedCount(message)) }) }}
                                        </span>
                                    </p>
                                </td>

                                <!-- Status -->
                                <td class="px-3 py-3 max-sm:hidden">
                                    <StatusPill :tone="statusOf(message).tone">
                                        {{ statusOf(message).label }}
                                    </StatusPill>
                                </td>

                                <!-- Date: when it went out, when it will, or when it was written -->
                                <td class="whitespace-nowrap px-3 py-3 max-sm:hidden">
                                    <p class="tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ dateOf(message).value }}
                                    </p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400">
                                        {{ dateOf(message).label }}
                                    </p>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            v-if="message.status === 'sent' || message.status === 'scheduled'"
                                            :href="route('messages.stats', message.id)"
                                            class="max-sm:hidden inline-flex min-h-9 items-center rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                        >
                                            {{ $t("messages.table.stats") }}
                                        </Link>
                                        <ActionMenu :label="$t('messages.actions_for', { subject: message.subject })">
                                            <!-- On a phone the link beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem
                                                v-if="message.status === 'sent' || message.status === 'scheduled'"
                                                :href="route('messages.stats', message.id)"
                                                class="sm:hidden"
                                            >
                                                {{ $t("messages.table.stats") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem :href="route('messages.edit', message.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="confirmDuplicateMessage(message)">
                                                {{ $t("messages.duplicate_button") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                v-if="message.type === 'broadcast' && (message.status === 'sent' || message.status === 'scheduled')"
                                                :disabled="resendingMessages.has(message.id)"
                                                @click="confirmResendMessage(message)"
                                            >
                                                {{ $t("messages.resend_button") }}
                                            </ActionMenuItem>
                                            <template v-if="message.type === 'autoresponder'">
                                                <ActionMenuItem @click="messageForQueueStats = message">
                                                    {{ $t("queue_stats.title") }}
                                                </ActionMenuItem>
                                                <ActionMenuItem
                                                    :disabled="togglingMessages.has(message.id)"
                                                    @click="toggleActive(message)"
                                                >
                                                    {{
                                                        getIsActive(message)
                                                            ? $t("messages.toggle_deactivate")
                                                            : $t("messages.toggle_activate")
                                                    }}
                                                </ActionMenuItem>
                                            </template>
                                            <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                            <ActionMenuItem danger @click="confirmDeleteMessage(message)">
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
                    v-if="messages.total > 0"
                    v-model:per-page="form.per_page"
                    :paginator="messages"
                />
            </Panel>
        </div>

        <!-- Delete -->
        <Modal :show="!!messageToDelete" max-width="md" @close="closeModal">
            <div class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("messages.delete_confirm_title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("messages.delete_confirm_description") }}
                </p>
                <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900 dark:bg-slate-700/60 dark:text-white">
                    {{ messageToDelete?.subject }}
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">{{ $t("common.cancel") }}</SecondaryButton>
                    <DangerButton @click="deleteMessage">{{ $t("messages.delete_button") }}</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Duplicate -->
        <Modal :show="!!messageToDuplicate" max-width="md" @close="closeDuplicateModal">
            <div v-if="!duplicatedMessage" class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("messages.duplicate_confirm_title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("messages.duplicate_confirm_description") }}
                </p>
                <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900 dark:bg-slate-700/60 dark:text-white">
                    {{ messageToDuplicate?.subject }}
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeDuplicateModal">{{ $t("common.cancel") }}</SecondaryButton>
                    <PrimaryButton :disabled="isDuplicating" @click="duplicateMessage">
                        {{ isDuplicating ? $t("messages.duplicating") : $t("messages.duplicate_button") }}
                    </PrimaryButton>
                </div>
            </div>
            <div v-else class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("messages.duplicate_success_title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{
                        $t("messages.duplicate_success_description", {
                            prefix: $t("messages.copy_prefix"),
                            subject: messageToDuplicate?.subject,
                        })
                    }}
                </p>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton @click="stayOnList">{{ $t("messages.stay_on_list") }}</SecondaryButton>
                    <PrimaryButton @click="goToEditDuplicate">{{ $t("messages.go_to_edit_duplicate") }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Resend -->
        <Modal :show="!!messageToResend" max-width="md" @close="closeResendModal">
            <div v-if="!resendResult" class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("messages.resend_confirm_title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("messages.resend_confirm_description") }}
                </p>
                <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900 dark:bg-slate-700/60 dark:text-white">
                    {{ messageToResend?.subject }}
                </p>
                <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                    <strong>{{ $t("messages.resend_note_title") }}:</strong>
                    {{ $t("messages.resend_note_description") }}
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeResendModal">{{ $t("common.cancel") }}</SecondaryButton>
                    <PrimaryButton
                        :disabled="resendingMessages.has(messageToResend?.id)"
                        @click="resendMessage"
                    >
                        {{
                            resendingMessages.has(messageToResend?.id)
                                ? $t("messages.resending")
                                : $t("messages.resend_button")
                        }}
                    </PrimaryButton>
                </div>
            </div>
            <div v-else class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{
                        resendResult.success
                            ? $t("messages.resend_success_title")
                            : $t("messages.resend_error_title")
                    }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ resendResult.message }}</p>
                <p
                    v-if="resendResult.success && resendResult.new_recipients"
                    class="text-sm font-medium text-slate-900 dark:text-white"
                >
                    {{ $t("messages.resend_new_recipients", { count: resendResult.new_recipients }) }}
                </p>
                <div class="flex justify-end">
                    <PrimaryButton @click="closeResendAndReload">{{ $t("common.ok") }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <QueueStatsModal
            :show="!!messageForQueueStats"
            :message="messageForQueueStats"
            @close="messageForQueueStats = null"
            @sent="messageForQueueStats = null"
        />

        <!-- Confirmation after saving or scheduling a message -->
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
                    class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg"
                    :class="
                        toast.success
                            ? 'border-emerald-200 bg-white text-slate-900 dark:border-emerald-500/30 dark:bg-slate-800 dark:text-white'
                            : 'border-rose-200 bg-white text-slate-900 dark:border-rose-500/30 dark:bg-slate-800 dark:text-white'
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
