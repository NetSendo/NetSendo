<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import debounce from "lodash/debounce";
import pickBy from "lodash/pickBy";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SearchField from "@/Components/Ui/SearchField.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import SelectField from "@/Components/Ui/SelectField.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useDateTime } from "@/Composables/useDateTime";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    forms: Object,
    lists: Array,
    filters: Object,
    // How many forms each tab holds: { all, active, draft, disabled }
    counts: { type: Object, default: () => ({}) },
});

const { t, locale } = useI18n();
const { formatDate: formatDateBase } = useDateTime();
// The outcome of each action is worded here, in the user's language; the server's own
// message is shown only when something went wrong
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    status: filters.status || "",
    list_id: filters.list_id || "",
    per_page: filters.per_page || "30",
});

watch(
    form,
    debounce(() => {
        router.get(route("forms.index"), pickBy(form), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300),
);

const view = computed({
    get: () => form.status || "all",
    set: (value) => (form.status = value === "all" ? "" : value),
});

const viewOptions = computed(() =>
    ["all", "active", "draft", "disabled"].map((value) => ({
        value,
        label: t(`forms.views.${value}`),
        count: props.counts?.[value] ?? 0,
    })),
);

const hasFilters = computed(() => Boolean(form.search || form.list_id));

// Clears what narrows the list; the chosen tab stays
const resetFilters = () => {
    form.search = "";
    form.list_id = "";
};

const STATUS_TONES = { active: "success", draft: "neutral", disabled: "warning" };

const statusOf = (item) => ({
    tone: STATUS_TONES[item.status] || "neutral",
    label: STATUS_TONES[item.status] ? t(`forms.status.${item.status}`) : item.status,
});

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const formatDate = (date) =>
    formatDateBase(date, null, {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });

const toggleStatus = (item) => {
    const wasActive = item.status === "active";
    router.post(
        route("forms.toggle-status", item.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => showToast(t(wasActive ? "forms.deactivated" : "forms.activated")),
        },
    );
};

// The copy opens in the editor straight away
const duplicateForm = (item) => {
    router.post(route("forms.duplicate", item.id));
};

const formToDelete = ref(null);
const deleting = ref(false);

const deleteForm = () => {
    if (!formToDelete.value) return;
    deleting.value = true;
    router.delete(route("forms.destroy", formToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("forms.deleted")),
        onFinish: () => {
            deleting.value = false;
            formToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head :title="$t('forms.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('forms.title')" :subtitle="$t('forms.subtitle')">
                <CreateLink :href="route('forms.create')">{{ $t("forms.create") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <SegmentedControl
                v-model="view"
                :options="viewOptions"
                :label="$t('forms.views.label')"
                class="self-start"
            />

            <Panel flush>
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <SearchField v-model="form.search" :placeholder="$t('forms.search_placeholder')" />

                    <SelectField
                        v-if="lists.length"
                        v-model="form.list_id"
                        :label="$t('forms.table.list')"
                        class="sm:max-w-64"
                    >
                        <option value="">{{ $t("forms.all_lists") }}</option>
                        <option v-for="list in lists" :key="list.id" :value="list.id">
                            {{ list.name }}
                        </option>
                    </SelectField>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("messages.filters.clear") }}
                    </button>
                </div>

                <EmptyState
                    v-if="forms.data.length === 0 && (hasFilters || view !== 'all')"
                    :title="$t('forms.empty_filtered')"
                >
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="resetFilters"
                    >
                        {{ $t("messages.filters.clear") }}
                    </button>
                </EmptyState>

                <EmptyState
                    v-else-if="forms.data.length === 0"
                    :title="$t('forms.empty_title')"
                    :description="$t('forms.empty_description')"
                >
                    <CreateLink :href="route('forms.create')">{{ $t("forms.create_first") }}</CreateLink>
                </EmptyState>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full sm:min-w-[760px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("forms.table.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ $t("forms.table.status") }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ $t("forms.table.submissions") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("forms.table.last_submission") }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("forms.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in forms.data"
                                :key="item.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <!-- The form and the list it feeds -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[44%]">
                                    <Link
                                        :href="route('forms.edit', item.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="item.name"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <p
                                        class="truncate text-xs text-slate-600 dark:text-slate-400"
                                        :title="item.contact_list?.name"
                                    >
                                        {{ item.contact_list?.name || $t("forms.no_list") }}
                                    </p>
                                    <!-- On a phone the status has no column of its own: it sits under the name -->
                                    <div class="mt-1.5 sm:hidden">
                                        <StatusPill :tone="statusOf(item).tone">
                                            {{ statusOf(item).label }}
                                        </StatusPill>
                                    </div>
                                </td>

                                <td class="px-3 py-3 max-sm:hidden">
                                    <StatusPill :tone="statusOf(item).tone">
                                        {{ statusOf(item).label }}
                                    </StatusPill>
                                </td>

                                <td class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100 max-sm:hidden">
                                    {{ number(item.submissions_count) }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 max-sm:hidden">
                                    <span
                                        v-if="item.last_submission_at"
                                        class="tabular-nums text-slate-900 dark:text-slate-100"
                                    >
                                        {{ formatDate(item.last_submission_at) }}
                                    </span>
                                    <span v-else class="text-slate-600 dark:text-slate-400">
                                        {{ $t("forms.no_submissions") }}
                                    </span>
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('forms.code', item.id)"
                                            class="max-sm:hidden inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                        >
                                            {{ $t("forms.get_code") }}
                                        </Link>
                                        <ActionMenu :label="$t('forms.actions_for', { name: item.name })">
                                            <!-- On a phone the link beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem :href="route('forms.code', item.id)" class="sm:hidden">
                                                {{ $t("forms.get_code") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem :href="route('forms.edit', item.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem :href="route('forms.stats', item.id)">
                                                {{ $t("forms.stats_btn") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="toggleStatus(item)">
                                                {{
                                                    item.status === "active"
                                                        ? $t("forms.deactivate")
                                                        : $t("forms.activate")
                                                }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="duplicateForm(item)">
                                                {{ $t("common.duplicate") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="formToDelete = item">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="forms.total > 0" v-model:per-page="form.per_page" :paginator="forms" />
            </Panel>
        </div>

        <ConfirmModal
            :show="!!formToDelete"
            type="danger"
            :title="$t('forms.delete_title')"
            :message="$t('forms.delete_description')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="formToDelete = null"
            @confirm="deleteForm"
        >
            {{ formToDelete?.name }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
