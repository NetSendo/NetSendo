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
});

const form = reactive({
    search: props.filters.search || "",
    type: props.filters.type || "",
    list_id: props.filters.list_id || "",
    group_id: props.filters.group_id || "",
    tag_id: props.filters.tag_id || "",
    campaign_plan_id: props.filters.campaign_plan_id || "",
    sort: props.filters.sort || "created_at",
    direction: props.filters.direction || "desc",
    per_page: props.filters.per_page || "30",
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

const sort = (field) => {
    form.sort = field;
    form.direction = form.direction === "asc" ? "desc" : "asc";
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

const resetFilters = () => {
    form.search = "";
    form.type = "";
    form.list_id = "";
    form.group_id = "";
    form.tag_id = "";
    form.campaign_plan_id = "";
    form.sort = "created_at";
    form.direction = "desc";
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
                error.response?.data?.message ||
                "Wystąpił błąd podczas ponownego wysyłania.",
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

const getAttachmentTooltip = (message, trans) => {
    if (!message.pdf_attachments || message.pdf_attachments.length === 0)
        return "";

    // Fallback to simple English if trans is missing (shouldn't happen)
    const t = trans || ((key, params) => `${params.count} PDF files`);

    const countText = t("messages.has_pdf_attachments", {
        count: message.pdf_attachments.length,
    });
    const filesText = message.pdf_attachments.join("\n");

    return `${countText}\n${filesText}`;
};
</script>

<template>
    <Head :title="$t('messages.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("messages.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("messages.subtitle") }}
                    </p>
                </div>
                <Link
                    :href="route('messages.create')"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ $t("messages.new_message") }}
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div
            class="mt-4 flex flex-wrap items-center gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900"
        >
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
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
                    v-model="form.search"
                    type="text"
                    :placeholder="$t('messages.search_placeholder')"
                    class="block w-full rounded-lg border-slate-300 pl-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>

            <!-- List Filter (Searchable Dropdown) -->
            <div class="relative" ref="listDropdownRef">
                <div
                    @click="listDropdownOpen = !listDropdownOpen"
                    class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm cursor-pointer min-w-[180px] dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <span v-if="selectedListName" class="flex-1 truncate">{{
                        selectedListName
                    }}</span>
                    <span
                        v-else
                        class="flex-1 text-slate-500 dark:text-slate-400"
                        >{{ $t("messages.all_lists") }}</span
                    >
                    <button
                        v-if="form.list_id"
                        @click.stop="clearListFilter"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
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
                    <svg
                        class="h-4 w-4 text-slate-400"
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
                </div>
                <div
                    v-if="listDropdownOpen"
                    class="absolute z-50 mt-1 w-full min-w-[250px] rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="p-2">
                        <input
                            v-model="listSearch"
                            type="text"
                            :placeholder="
                                $t('messages.list_filter_placeholder')
                            "
                            class="block w-full rounded-md border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            @click.stop
                        />
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <button
                            @click="clearListFilter"
                            class="w-full px-3 py-2 text-left text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
                            :class="{
                                'bg-indigo-50 dark:bg-indigo-900/30':
                                    !form.list_id,
                            }"
                        >
                            {{ $t("messages.all_lists") }}
                        </button>
                        <button
                            v-for="list in filteredLists"
                            :key="list.id"
                            @click="selectList(list)"
                            class="w-full px-3 py-2 text-left text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
                            :class="{
                                'bg-indigo-50 dark:bg-indigo-900/30':
                                    form.list_id == list.id,
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

            <!-- Group Filter -->
            <select
                v-model="form.group_id"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t("messages.all_groups") }}</option>
                <option
                    v-for="group in groups"
                    :key="group.id"
                    :value="group.id"
                >
                    {{ group.name }}
                </option>
            </select>

            <!-- Tag Filter -->
            <select
                v-model="form.tag_id"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t("messages.all_tags") }}</option>
                <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                    {{ tag.name }}
                </option>
            </select>

            <!-- Type Filter -->
            <select
                v-model="form.type"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t("messages.all_types") }}</option>
                <option value="broadcast">
                    {{ $t("messages.type_broadcast") }}
                </option>
                <option value="autoresponder">
                    {{ $t("messages.type_autoresponder") }}
                </option>
            </select>

            <!-- Per Page -->
            <select
                v-model="form.per_page"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="10">10 {{ $t("common.per_page") }}</option>
                <option value="30">30 {{ $t("common.per_page") }}</option>
                <option value="50">50 {{ $t("common.per_page") }}</option>
                <option value="100">100 {{ $t("common.per_page") }}</option>
            </select>

            <!-- Campaign Plan Filter -->
            <select
                v-model="form.campaign_plan_id"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t("messages.all_campaigns") }}</option>
                <option
                    v-for="plan in campaignPlans"
                    :key="plan.id"
                    :value="plan.id"
                >
                    {{ plan.name }}
                </option>
            </select>

            <!-- Reset -->
            <button
                v-if="
                    form.search ||
                    form.list_id ||
                    form.type ||
                    form.group_id ||
                    form.tag_id ||
                    form.campaign_plan_id
                "
                @click="resetFilters"
                class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
                {{ $t("common.clear") }}
            </button>
        </div>

        <div
            class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-sm text-slate-500 dark:text-slate-400"
                >
                    <thead
                        class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700"
                                @click="sort('id')"
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("messages.table.message_id") }}
                                    <span v-if="form.sort === 'id'">{{
                                        form.direction === "asc" ? "↑" : "↓"
                                    }}</span>
                                </div>
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700"
                                @click="sort('subject')"
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("messages.table.subject") }}
                                    <span v-if="form.sort === 'subject'">{{
                                        form.direction === "asc" ? "↑" : "↓"
                                    }}</span>
                                </div>
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700"
                                @click="sort('type')"
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("messages.table.type") }}
                                    <span v-if="form.sort === 'type'">{{
                                        form.direction === "asc" ? "↑" : "↓"
                                    }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3">
                                {{ $t("messages.table.audience") }}
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700"
                                @click="sort('status')"
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("messages.table.status") }}
                                    <span v-if="form.sort === 'status'">{{
                                        form.direction === "asc" ? "↑" : "↓"
                                    }}</span>
                                </div>
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700"
                                @click="sort('created_at')"
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("messages.table.created_at") }}
                                    <span v-if="form.sort === 'created_at'">{{
                                        form.direction === "asc" ? "↑" : "↓"
                                    }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right">
                                {{ $t("messages.table.actions") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="message in messages.data"
                            :key="message.id"
                            :data-message-id="message.id"
                            :class="[
                                'hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all duration-500',
                                highlightedMessageId === message.id
                                    ? 'bg-indigo-50 dark:bg-indigo-900/20 ring-2 ring-indigo-400 ring-inset animate-pulse'
                                    : '',
                            ]"
                        >
                            <td
                                class="px-6 py-4 text-slate-500 dark:text-slate-400 font-mono text-xs"
                            >
                                {{ message.id }}
                            </td>
                            <td
                                class="px-6 py-4 font-medium text-slate-900 dark:text-white"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div>
                                        {{ message.subject }}
                                        <div
                                            v-if="message.preheader"
                                            class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-xs"
                                        >
                                            {{ message.preheader }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            message.pdf_attachments &&
                                            message.pdf_attachments.length > 0
                                        "
                                        class="flex-shrink-0 cursor-help"
                                        :title="
                                            getAttachmentTooltip(message, $t)
                                        "
                                    >
                                        <svg
                                            class="h-5 w-5 text-red-500"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v.5zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5v1.5H19v2.5h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400':
                                            message.type === 'broadcast',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400':
                                            message.type === 'autoresponder',
                                    }"
                                >
                                    {{
                                        message.type === "broadcast"
                                            ? $t("messages.type_broadcast")
                                            : $t("messages.type_autoresponder")
                                    }}
                                    <span
                                        v-if="message.type === 'autoresponder'"
                                        class="ml-1 opacity-75"
                                        >{{
                                            $t(
                                                "messages.type_autoresponder_day",
                                                { day: message.day },
                                            )
                                        }}</span
                                    >
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span
                                        class="font-medium text-slate-900 dark:text-white"
                                        >{{ message.list_name }}</span
                                    >
                                    <!-- Autoresponder: show detailed queue stats -->
                                    <template
                                        v-if="
                                            message.type === 'autoresponder' &&
                                            getQueueStats(message)
                                        "
                                    >
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            <span
                                                class="text-emerald-600 dark:text-emerald-400 font-medium"
                                            >
                                                {{
                                                    $t(
                                                        "messages.queue_sent_count",
                                                        {
                                                            count: getQueueStats(
                                                                message,
                                                            ).sent,
                                                        },
                                                    )
                                                }}
                                            </span>
                                            /
                                            {{
                                                getRecipientsCount(
                                                    message,
                                                )?.toLocaleString() ?? "-"
                                            }}
                                            {{ $t("messages.recipients") }}
                                        </span>
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap gap-x-2"
                                        >
                                            <span
                                                v-if="
                                                    getQueueStats(message)
                                                        .planned > 0
                                                "
                                                class="text-blue-500"
                                            >
                                                {{
                                                    $t(
                                                        "messages.queue_planned_count",
                                                        {
                                                            count: getQueueStats(
                                                                message,
                                                            ).planned,
                                                        },
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    getQueueStats(message)
                                                        .queued > 0
                                                "
                                                class="text-indigo-500"
                                            >
                                                {{
                                                    $t(
                                                        "messages.queue_queued_count",
                                                        {
                                                            count: getQueueStats(
                                                                message,
                                                            ).queued,
                                                        },
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    getQueueStats(message)
                                                        .failed > 0
                                                "
                                                class="text-red-500 font-medium"
                                            >
                                                {{
                                                    $t(
                                                        "messages.queue_failed_count",
                                                        {
                                                            count: getQueueStats(
                                                                message,
                                                            ).failed,
                                                        },
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    getSkippedCount(message) > 0
                                                "
                                                class="text-orange-500 font-medium"
                                                :title="
                                                    $t('messages.skipped_hint')
                                                "
                                            >
                                                {{
                                                    $t(
                                                        "messages.queue_missed_count",
                                                        {
                                                            count: getSkippedCount(
                                                                message,
                                                            ),
                                                        },
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </template>
                                    <!-- Autoresponder without stats yet / Broadcast -->
                                    <span
                                        v-else
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            getRecipientsCount(
                                                message,
                                            )?.toLocaleString() ?? "-"
                                        }}
                                        {{ $t("messages.recipients") }}
                                        <span
                                            v-if="
                                                message.type ===
                                                    'autoresponder' &&
                                                getSkippedCount(message) > 0
                                            "
                                            class="text-orange-500 font-medium"
                                            :title="$t('messages.skipped_hint')"
                                        >
                                            ({{ getSkippedCount(message) }}
                                            {{ $t("messages.skipped") }})
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <!-- Queue/Autoresponder: show active/inactive status -->
                                <span
                                    v-if="message.type === 'autoresponder'"
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400':
                                            getIsActive(message),
                                        'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300':
                                            !getIsActive(message),
                                    }"
                                >
                                    {{
                                        getIsActive(message)
                                            ? $t("messages.status_active")
                                            : $t("messages.status_inactive")
                                    }}
                                </span>
                                <!-- Broadcast: show sent/scheduled/draft -->
                                <span
                                    v-else
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400':
                                            getStatus(message) === 'sent',
                                        'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300':
                                            getStatus(message) === 'draft',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400':
                                            getStatus(message) === 'scheduled',
                                    }"
                                >
                                    {{
                                        getStatus(message) === "sent"
                                            ? $t("messages.status_sent")
                                            : getStatus(message) === "scheduled"
                                              ? $t("messages.status_scheduled")
                                              : $t("messages.status_draft")
                                    }}
                                </span>
                                <!-- Show scheduled time for scheduled messages -->
                                <div
                                    v-if="
                                        message.type !== 'autoresponder' &&
                                        getStatus(message) === 'scheduled' &&
                                        message.scheduled_at
                                    "
                                    class="text-xs text-slate-500 dark:text-slate-400 mt-1"
                                >
                                    📅 {{ message.scheduled_at }}
                                </div>
                                <!-- Show sent date for sent messages -->
                                <div
                                    v-if="
                                        message.type !== 'autoresponder' &&
                                        getStatus(message) === 'sent' &&
                                        message.scheduled_at
                                    "
                                    class="text-xs text-slate-500 dark:text-slate-400 mt-1"
                                >
                                    ✉️ {{ message.scheduled_at }}
                                </div>
                                <!-- A/B Test indicator -->
                                <div v-if="message.ab_test" class="mt-1">
                                    <Link
                                        :href="
                                            route(
                                                'ab-tests.show',
                                                message.ab_test.id,
                                            )
                                        "
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium cursor-pointer hover:ring-2 hover:ring-offset-1 transition-all"
                                        :class="{
                                            'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 hover:ring-purple-400':
                                                message.ab_test.status ===
                                                'running',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 hover:ring-amber-400':
                                                message.ab_test.status ===
                                                    'draft' ||
                                                message.ab_test.status ===
                                                    'paused',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:ring-emerald-400':
                                                message.ab_test.status ===
                                                'completed',
                                            'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 hover:ring-slate-400':
                                                message.ab_test.status ===
                                                'cancelled',
                                        }"
                                        :title="
                                            $t('messages.ab_test_view_results')
                                        "
                                    >
                                        🧪 {{ $t("messages.ab_test_badge") }}
                                        <span
                                            v-if="
                                                message.ab_test.status ===
                                                'running'
                                            "
                                            class="animate-pulse"
                                            >●</span
                                        >
                                    </Link>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                {{ message.created_at }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- Toggle Active (only for autoresponder/queue type) -->
                                    <button
                                        v-if="message.type === 'autoresponder'"
                                        @click="toggleActive(message)"
                                        class="relative"
                                        :class="[
                                            getIsActive(message)
                                                ? 'text-emerald-500 hover:text-emerald-600 dark:text-emerald-400 dark:hover:text-emerald-300'
                                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                                        ]"
                                        :title="
                                            getIsActive(message)
                                                ? $t(
                                                      'messages.toggle_deactivate',
                                                  )
                                                : $t('messages.toggle_activate')
                                        "
                                        :disabled="
                                            togglingMessages.has(message.id)
                                        "
                                    >
                                        <svg
                                            v-if="
                                                togglingMessages.has(message.id)
                                            "
                                            class="h-5 w-5 animate-spin"
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
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                            ></path>
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                v-if="getIsActive(message)"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                            <path
                                                v-else
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                    </button>
                                    <!-- Queue Stats button (only for autoresponder/queue type) -->
                                    <button
                                        v-if="message.type === 'autoresponder'"
                                        @click="messageForQueueStats = message"
                                        class="text-slate-400 hover:text-amber-600 dark:hover:text-amber-400"
                                        :title="$t('queue_stats.title')"
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
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </button>
                                    <Link
                                        :href="
                                            route('messages.edit', message.id)
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
                                    <Link
                                        v-if="
                                            message.status === 'sent' ||
                                            message.status === 'scheduled'
                                        "
                                        :href="
                                            route('messages.stats', message.id)
                                        "
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        :title="$t('messages.table.stats')"
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
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                            />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="
                                            confirmDuplicateMessage(message)
                                        "
                                        class="text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400"
                                        :title="$t('messages.duplicate_button')"
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
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </button>
                                    <!-- Resend button (only for broadcast messages) -->
                                    <button
                                        v-if="
                                            message.type === 'broadcast' &&
                                            (message.status === 'sent' ||
                                                message.status === 'scheduled')
                                        "
                                        @click="confirmResendMessage(message)"
                                        class="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400"
                                        :title="$t('messages.resend_button')"
                                        :disabled="
                                            resendingMessages.has(message.id)
                                        "
                                    >
                                        <svg
                                            v-if="
                                                resendingMessages.has(
                                                    message.id,
                                                )
                                            "
                                            class="h-5 w-5 animate-spin"
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
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                            ></path>
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="confirmDeleteMessage(message)"
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
                        <tr v-if="messages.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-8 text-center text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("messages.empty_title") }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="messages.links.length > 3"
                class="flex items-center justify-center border-t border-slate-100 px-6 py-4 dark:border-slate-800"
            >
                <div class="flex gap-1">
                    <Link
                        v-for="(link, i) in messages.links"
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

        <!-- Delete Confirmation Modal -->
        <Modal :show="!!messageToDelete" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-slate-900 dark:text-white">
                    {{ $t("messages.delete_confirm_title") }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("messages.delete_confirm_description") }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <DangerButton @click="deleteMessage">
                        {{ $t("messages.delete_button") }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Duplicate Confirmation Modal -->
        <Modal
            :show="!!messageToDuplicate"
            @close="closeDuplicateModal"
            max-width="md"
        >
            <div class="p-6">
                <!-- Initial state: confirm duplication -->
                <template v-if="!duplicatedMessage">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
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
                        </div>
                        <div>
                            <h2
                                class="text-lg font-semibold text-slate-900 dark:text-white"
                            >
                                {{ $t("messages.duplicate_confirm_title") }}
                            </h2>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t("messages.duplicate_confirm_description")
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                        <p
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ messageToDuplicate?.subject }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeDuplicateModal">
                            {{ $t("common.cancel") }}
                        </SecondaryButton>
                        <PrimaryButton
                            @click="duplicateMessage"
                            :disabled="isDuplicating"
                            class="bg-emerald-600 hover:bg-emerald-500"
                        >
                            <svg
                                v-if="isDuplicating"
                                class="mr-2 h-4 w-4 animate-spin"
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                ></path>
                            </svg>
                            {{
                                isDuplicating
                                    ? $t("messages.duplicating")
                                    : $t("messages.duplicate_button")
                            }}
                        </PrimaryButton>
                    </div>
                </template>

                <!-- Success state: choose next action -->
                <template v-else>
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                        >
                            <svg
                                class="h-8 w-8 text-emerald-600 dark:text-emerald-400"
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
                        </div>
                        <h2
                            class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            {{ $t("messages.duplicate_success_title") }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                $t("messages.duplicate_success_description", {
                                    prefix: $t("messages.copy_prefix"),
                                    subject: messageToDuplicate?.subject,
                                })
                            }}
                        </p>
                    </div>

                    <div class="mt-6 flex flex-col gap-3">
                        <button
                            @click="goToEditDuplicate"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-500"
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
                            {{ $t("messages.go_to_edit_duplicate") }}
                        </button>
                        <button
                            @click="stayOnList"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ $t("messages.stay_on_list") }}
                        </button>
                    </div>
                </template>
            </div>
        </Modal>

        <!-- Resend Confirmation Modal -->
        <Modal
            :show="!!messageToResend"
            @close="closeResendModal"
            max-width="md"
        >
            <div class="p-6">
                <!-- Initial state: confirm resend -->
                <template v-if="!resendResult">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </div>
                        <div>
                            <h2
                                class="text-lg font-semibold text-slate-900 dark:text-white"
                            >
                                {{ $t("messages.resend_confirm_title") }}
                            </h2>
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("messages.resend_confirm_description") }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                        <p
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ messageToResend?.subject }}
                        </p>
                    </div>

                    <div
                        class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 dark:bg-amber-900/20 dark:border-amber-800"
                    >
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <strong
                                >{{ $t("messages.resend_note_title") }}:</strong
                            >
                            {{ $t("messages.resend_note_description") }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeResendModal">
                            {{ $t("common.cancel") }}
                        </SecondaryButton>
                        <PrimaryButton
                            @click="resendMessage"
                            :disabled="
                                resendingMessages.has(messageToResend?.id)
                            "
                            class="bg-blue-600 hover:bg-blue-500"
                        >
                            <svg
                                v-if="
                                    resendingMessages.has(messageToResend?.id)
                                "
                                class="mr-2 h-4 w-4 animate-spin"
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
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                ></path>
                            </svg>
                            {{
                                resendingMessages.has(messageToResend?.id)
                                    ? $t("messages.resending")
                                    : $t("messages.resend_button")
                            }}
                        </PrimaryButton>
                    </div>
                </template>

                <!-- Result state -->
                <template v-else>
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full"
                            :class="
                                resendResult.success
                                    ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                    : 'bg-red-100 dark:bg-red-900/30'
                            "
                        >
                            <svg
                                v-if="resendResult.success"
                                class="h-8 w-8 text-emerald-600 dark:text-emerald-400"
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
                                class="h-8 w-8 text-red-600 dark:text-red-400"
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
                        </div>
                        <h2
                            class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            {{
                                resendResult.success
                                    ? $t("messages.resend_success_title")
                                    : $t("messages.resend_error_title")
                            }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ resendResult.message }}
                        </p>
                        <p
                            v-if="
                                resendResult.success &&
                                resendResult.new_recipients
                            "
                            class="mt-2 text-sm text-emerald-600 dark:text-emerald-400"
                        >
                            {{
                                $t("messages.resend_new_recipients", {
                                    count: resendResult.new_recipients,
                                })
                            }}
                        </p>
                    </div>

                    <div class="mt-6">
                        <button
                            @click="closeResendAndReload"
                            class="flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-indigo-500"
                        >
                            {{ $t("common.ok") }}
                        </button>
                    </div>
                </template>
            </div>
        </Modal>

        <!-- Queue Stats Modal -->
        <QueueStatsModal
            :show="!!messageForQueueStats"
            :message="messageForQueueStats"
            @close="messageForQueueStats = null"
            @sent="messageForQueueStats = null"
        />

        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg"
                    :class="[
                        toast.success
                            ? 'bg-emerald-500 text-white'
                            : 'bg-red-500 text-white',
                    ]"
                >
                    <svg
                        v-if="toast.success"
                        class="h-5 w-5"
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
                        class="h-5 w-5"
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
                    <span class="font-medium">{{ toast.message }}</span>
                    <button
                        @click="toast = null"
                        class="ml-2 rounded-full p-1 hover:bg-white/20"
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
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
