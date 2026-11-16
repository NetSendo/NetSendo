<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import { useI18n } from "vue-i18n";
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import Panel from "@/Components/Ui/Panel.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import SortMark from "@/Components/Ui/SortMark.vue";
import throttle from "lodash/throttle";
import pickBy from "lodash/pickBy";
import axios from "axios";

const props = defineProps({
    messages: Object,
    filters: Object,
    lists: Array,
    groups: Array,
    campaignPlans: Array,
    // How many messages each tab holds: { all, draft, scheduled, sent, autoresponder }
    counts: { type: Object, default: () => ({}) },
});

const { t, locale } = useI18n();

// With no filter in the URL, PHP used to send an empty list, and a list has a `sort` of its
// own (Array.prototype.sort): reading filters.sort from it gave a function
const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    type: filters.type || "",
    status: filters.status || "",
    list_id: filters.list_id || "",
    campaign_plan_id: filters.campaign_plan_id || "",
    // "effective_date" is the server's default: sent or scheduled time, else creation time
    sort: filters.sort || "effective_date",
    direction: filters.direction || "desc",
    per_page: filters.per_page || "30",
});

const messageToDelete = ref(null);
const togglingMessages = ref(new Set());

// List filter search
const listSearch = ref("");
const listDropdownOpen = ref(false);
const listDropdownRef = ref(null);

const filteredLists = computed(() => {
    if (!listSearch.value) return props.lists;
    const search = listSearch.value.toLowerCase();
    return props.lists.filter(
        (list) => list.name.toLowerCase().includes(search) || String(list.id).includes(search),
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
    if (listDropdownRef.value && !listDropdownRef.value.contains(event.target)) {
        listDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutsideListDropdown);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutsideListDropdown);
});

// Reactive local state for is_active (to update UI immediately)
const localActiveStates = reactive({});

const getIsActive = (message) => {
    if (localActiveStates[message.id] !== undefined) {
        return localActiveStates[message.id];
    }
    return message.is_active ?? true;
};

watch(
    form,
    throttle(() => {
        router.get(route("sms.index"), pickBy(form), {
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

const hasFilters = computed(() => Boolean(form.search || form.list_id || form.campaign_plan_id));

// Clears what narrows the list; the chosen tab and the sort order stay
const resetFilters = () => {
    form.search = "";
    form.list_id = "";
    form.campaign_plan_id = "";
};

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const audienceLabel = (message) => {
    const names = message.list_names || [];
    if (names.length === 0) return t("messages.no_list");
    if (names.length === 1) return names[0];
    return t("messages.lists_count", { count: names.length });
};

// A queue is on or off; a broadcast is a draft, scheduled or sent
const statusOf = (message) => {
    if (message.type === "autoresponder") {
        return getIsActive(message)
            ? { tone: "success", label: t("messages.status_active") }
            : { tone: "neutral", label: t("messages.status_inactive") };
    }

    if (message.status === "sent") return { tone: "success", label: t("sms.status_sent") };
    if (message.status === "scheduled") return { tone: "info", label: t("sms.status_scheduled") };
    return { tone: "neutral", label: t("sms.status_draft") };
};

// One date per row, the one that matters for its state
const dateOf = (message) => {
    if (message.type !== "autoresponder" && message.scheduled_at) {
        if (message.status === "sent") {
            return { value: message.scheduled_at, label: t("messages.date_sent") };
        }
        if (message.status === "scheduled") {
            return { value: message.scheduled_at, label: t("messages.date_scheduled") };
        }
    }

    return { value: message.created_at, label: t("messages.date_created") };
};

const confirmDeleteMessage = (message) => {
    messageToDelete.value = message;
};

const deleteMessage = () => {
    if (messageToDelete.value) {
        router.delete(route("sms.destroy", messageToDelete.value.id), {
            onSuccess: () => (messageToDelete.value = null),
        });
    }
};

const closeModal = () => {
    messageToDelete.value = null;
};

// Toggle active status
const toggleActive = async (message) => {
    if (togglingMessages.value.has(message.id)) return;

    togglingMessages.value.add(message.id);
    try {
        const response = await axios.post(route("sms.toggle-active", message.id));
        if (response.data.success) {
            localActiveStates[message.id] = response.data.is_active;
        }
    } catch (error) {
        console.error("Toggle failed:", error);
    } finally {
        togglingMessages.value.delete(message.id);
    }
};
</script>

<template>
    <Head :title="$t('sms.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('sms.title')" :subtitle="$t('sms.subtitle')">
                <Link
                    :href="route('sms.create')"
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
                    {{ $t("sms.new_message") }}
                </Link>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <SegmentedControl
                v-model="view"
                :options="viewOptions"
                :label="$t('messages.views.label')"
                class="self-start"
            />

            <Panel flush>
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <label class="relative min-w-0 flex-1 basis-64">
                        <span class="sr-only">{{ $t("sms.search_placeholder") }}</span>
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
                            :placeholder="$t('sms.search_placeholder')"
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
                            <span class="truncate">{{ selectedListName || $t("sms.all_lists") }}</span>
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
                                :placeholder="$t('sms.list_filter_placeholder')"
                                :aria-label="$t('sms.list_filter_placeholder')"
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
                                    {{ $t("sms.all_lists") }}
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

                    <select
                        v-if="campaignPlans.length"
                        v-model="form.campaign_plan_id"
                        :aria-label="$t('messages.filters.campaign')"
                        class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                    >
                        <option value="">{{ $t("messages.all_campaigns") }}</option>
                        <option v-for="plan in campaignPlans" :key="plan.id" :value="plan.id">
                            {{ plan.name }}
                        </option>
                    </select>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("messages.filters.clear") }}
                    </button>
                </div>

                <!-- Nothing to show -->
                <div
                    v-if="messages.data.length === 0"
                    class="flex flex-col items-center gap-2 border-t border-slate-200 px-5 py-16 text-center dark:border-slate-700/60"
                >
                    <p class="text-sm font-medium text-slate-900 dark:text-white">
                        {{ $t("sms.empty_title") }}
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
                        :href="route('sms.create')"
                        class="mt-2 inline-flex min-h-10 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
                    >
                        {{ $t("sms.new_message") }}
                    </Link>
                </div>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full sm:min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                <th scope="col" class="px-5 py-2.5 font-medium" :aria-sort="ariaSort('subject')">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('subject')">
                                        {{ $t("sms.table.subject") }}
                                        <SortMark :state="sortState('subject')" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("sms.table.audience") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden" :aria-sort="ariaSort('status')">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('status')">
                                        {{ $t("sms.table.status") }}
                                        <SortMark :state="sortState('status')" />
                                    </button>
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden" :aria-sort="ariaSort('effective_date')">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('effective_date')">
                                        {{ $t("common.date") }}
                                        <SortMark :state="sortState('effective_date')" />
                                    </button>
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("sms.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="message in messages.data"
                                :key="message.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <!-- Campaign: its name, then the text people will receive -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[46%]">
                                    <Link
                                        :href="route('sms.edit', message.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="message.subject"
                                    >
                                        {{ message.subject || $t("messages.no_subject") }}
                                    </Link>
                                    <p
                                        v-if="message.content"
                                        class="truncate text-slate-600 dark:text-slate-400"
                                        :title="message.content"
                                    >
                                        {{ message.content }}
                                    </p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-600 dark:text-slate-400">
                                        <span class="tabular-nums">#{{ message.id }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span>
                                            {{
                                                message.type === "broadcast"
                                                    ? $t("sms.type_broadcast")
                                                    : $t("sms.type_autoresponder")
                                            }}
                                            <template v-if="message.type === 'autoresponder'">
                                                {{ $t("sms.type_autoresponder_day", { day: message.day }) }}
                                            </template>
                                        </span>
                                    </p>
                                    <!-- On a phone the status has no column of its own: it sits under the name -->
                                    <div class="mt-1.5 sm:hidden">
                                        <StatusPill :tone="statusOf(message).tone">
                                            {{ statusOf(message).label }}
                                        </StatusPill>
                                    </div>
                                </td>

                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 22%">
                                    <p
                                        class="truncate text-slate-900 dark:text-slate-100"
                                        :title="(message.list_names || []).join(', ')"
                                    >
                                        {{ audienceLabel(message) }}
                                    </p>
                                    <p class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                        {{ number(message.recipients_count) }} {{ $t("messages.recipients") }}
                                    </p>
                                </td>

                                <td class="px-3 py-3 max-sm:hidden">
                                    <StatusPill :tone="statusOf(message).tone">
                                        {{ statusOf(message).label }}
                                    </StatusPill>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 max-sm:hidden">
                                    <p class="tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ dateOf(message).value }}
                                    </p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400">
                                        {{ dateOf(message).label }}
                                    </p>
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('sms.edit', message.id)"
                                            class="max-sm:hidden inline-flex min-h-9 items-center rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                        >
                                            {{ $t("common.edit") }}
                                        </Link>
                                        <ActionMenu :label="$t('messages.actions_for', { subject: message.subject })">
                                            <!-- On a phone the link beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem :href="route('sms.edit', message.id)" class="sm:hidden">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                v-if="message.type === 'autoresponder'"
                                                :disabled="togglingMessages.has(message.id)"
                                                @click="toggleActive(message)"
                                            >
                                                {{
                                                    getIsActive(message)
                                                        ? $t("messages.toggle_deactivate")
                                                        : $t("messages.toggle_activate")
                                                }}
                                            </ActionMenuItem>
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

        <Modal :show="!!messageToDelete" max-width="md" @close="closeModal">
            <div class="flex flex-col gap-4 p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("sms.delete_confirm_title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("sms.delete_confirm_description") }}
                </p>
                <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900 dark:bg-slate-700/60 dark:text-white">
                    {{ messageToDelete?.subject }}
                </p>
                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">{{ $t("common.cancel") }}</SecondaryButton>
                    <DangerButton @click="deleteMessage">{{ $t("sms.delete_button") }}</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
