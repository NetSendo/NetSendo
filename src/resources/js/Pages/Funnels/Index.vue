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
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    funnels: Object,
    filters: Object,
    statuses: Object,
    triggerTypes: Object,
    // How many funnels each tab holds: { all, active, paused, draft }
    counts: { type: Object, default: () => ({}) },
});

const { t, te, locale } = useI18n();
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    status: filters.status || "",
    trigger_type: filters.trigger_type || "",
    per_page: filters.per_page || "30",
});

watch(
    form,
    debounce(() => {
        router.get(route("funnels.index"), pickBy(form), {
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
    ["all", "active", "paused", "draft"].map((value) => ({
        value,
        label: t(`funnels.views.${value}`),
        count: props.counts?.[value] ?? 0,
    })),
);

const hasFilters = computed(() => Boolean(form.search || form.trigger_type));

const resetFilters = () => {
    form.search = "";
    form.trigger_type = "";
};

// The server names the triggers in Polish only; the translation wins where there is one
const triggerLabel = (type) =>
    te(`funnels.triggers.${type}`) ? t(`funnels.triggers.${type}`) : props.triggerTypes?.[type] || type;

// What exactly starts the funnel: the list, the form or the tag
const triggerSource = (funnel) =>
    funnel.trigger_list?.name || funnel.trigger_form?.name || funnel.trigger_tag || "";

const STATUS_TONES = { active: "success", paused: "warning", draft: "neutral" };

const statusOf = (funnel) => ({
    tone: STATUS_TONES[funnel.status] || "neutral",
    label: STATUS_TONES[funnel.status]
        ? t(`funnels.status.${funnel.status}`)
        : props.statuses?.[funnel.status] || funnel.status,
});

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

// People who left the funnel after finishing it are still counted as completed, so the
// share is capped: it can never read as more than everyone
const completedShare = (funnel) => {
    if (!funnel.subscribers_count) return null;
    return Math.min(100, Math.round((funnel.completed_count / funnel.subscribers_count) * 100));
};

// A funnel with a gap in it is not started: the server says what is missing
const toggleStatus = (funnel) => {
    const wasActive = funnel.status === "active";
    router.post(
        route("funnels.toggle-status", funnel.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => showToast(t(wasActive ? "funnels.paused" : "funnels.activated")),
            onError: (errors) => showToast(Object.values(errors).flat().join(" "), false),
        },
    );
};

// The copy opens in the builder straight away
const duplicateFunnel = (funnel) => {
    router.post(
        route("funnels.duplicate", funnel.id),
        {},
        { onError: (errors) => showToast(Object.values(errors).flat().join(" "), false) },
    );
};

const funnelToDelete = ref(null);
const deleting = ref(false);

const deleteFunnel = () => {
    if (!funnelToDelete.value) return;
    deleting.value = true;
    router.delete(route("funnels.destroy", funnelToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("funnels.deleted")),
        onFinish: () => {
            deleting.value = false;
            funnelToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head :title="$t('funnels.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('funnels.title')" :subtitle="$t('funnels.subtitle')">
                <CreateLink :href="route('funnels.create')">{{ $t("funnels.create") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <SegmentedControl
                v-model="view"
                :options="viewOptions"
                :label="$t('funnels.views.label')"
                class="self-start"
            />

            <Panel flush>
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <SearchField v-model="form.search" :placeholder="$t('funnels.search_placeholder')" />

                    <SelectField v-model="form.trigger_type" :label="$t('funnels.trigger_filter')">
                        <option value="">{{ $t("funnels.all_triggers") }}</option>
                        <option v-for="(label, key) in triggerTypes" :key="key" :value="key">
                            {{ triggerLabel(key) }}
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
                    v-if="funnels.data.length === 0 && (hasFilters || view !== 'all')"
                    :title="$t('funnels.empty_filtered')"
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
                    v-else-if="funnels.data.length === 0"
                    :title="$t('funnels.no_funnels')"
                    :description="$t('funnels.no_funnels_desc')"
                >
                    <CreateLink :href="route('funnels.create')">{{ $t("funnels.create") }}</CreateLink>
                </EmptyState>

                <div v-else class="overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("funnels.table.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("funnels.trigger_filter") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("funnels.table.status") }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium">
                                    {{ $t("funnels.table.subscribers") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium">
                                    {{ $t("funnels.table.completed") }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("funnels.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="funnel in funnels.data"
                                :key="funnel.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3" style="width: 36%">
                                    <Link
                                        :href="route('funnels.edit', funnel.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="funnel.name"
                                    >
                                        {{ funnel.name }}
                                    </Link>
                                </td>

                                <!-- What starts it, and from where -->
                                <td class="max-w-0 px-3 py-3" style="width: 24%">
                                    <p class="truncate text-slate-900 dark:text-slate-100">
                                        {{ triggerLabel(funnel.trigger_type) }}
                                    </p>
                                    <p
                                        v-if="triggerSource(funnel)"
                                        class="truncate text-xs text-slate-600 dark:text-slate-400"
                                        :title="triggerSource(funnel)"
                                    >
                                        {{ triggerSource(funnel) }}
                                    </p>
                                </td>

                                <td class="px-3 py-3">
                                    <StatusPill :tone="statusOf(funnel).tone">
                                        {{ statusOf(funnel).label }}
                                    </StatusPill>
                                </td>

                                <td class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ number(funnel.subscribers_count) }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 text-right">
                                    <p class="tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ number(funnel.completed_count) }}
                                    </p>
                                    <p
                                        v-if="completedShare(funnel) !== null"
                                        class="text-xs tabular-nums text-slate-600 dark:text-slate-400"
                                    >
                                        {{ $t("funnels.completed_share", { percent: completedShare(funnel) }) }}
                                    </p>
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('funnels.stats', funnel.id)"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                        >
                                            {{ $t("funnels.stats_btn") }}
                                        </Link>
                                        <ActionMenu :label="$t('funnels.actions_for', { name: funnel.name })">
                                            <ActionMenuItem :href="route('funnels.edit', funnel.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="toggleStatus(funnel)">
                                                {{
                                                    funnel.status === "active"
                                                        ? $t("funnels.pause")
                                                        : $t("funnels.activate")
                                                }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="duplicateFunnel(funnel)">
                                                {{ $t("common.duplicate") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="funnelToDelete = funnel">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="funnels.total > 0" v-model:per-page="form.per_page" :paginator="funnels" />
            </Panel>
        </div>

        <ConfirmModal
            :show="!!funnelToDelete"
            type="danger"
            :title="$t('funnels.delete_title')"
            :message="$t('funnels.delete_description')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="funnelToDelete = null"
            @confirm="deleteFunnel"
        >
            {{ funnelToDelete?.name }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
