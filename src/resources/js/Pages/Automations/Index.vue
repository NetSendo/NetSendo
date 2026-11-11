<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
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
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    rules: Object,
    filters: Object,
    triggerEvents: Object,
    // How many automations each tab holds: { all, active, inactive }
    counts: { type: Object, default: () => ({}) },
});

const { t, te, locale } = useI18n();
const page = usePage();
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    status: ["active", "inactive"].includes(filters.status) ? filters.status : "",
    trigger: filters.trigger || "",
    per_page: filters.per_page || "30",
});

watch(
    form,
    debounce(() => {
        router.get(route("automations.index"), pickBy(form), {
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
    ["all", "active", "inactive"].map((value) => ({
        value,
        label: t(`automations.views.${value}`),
        count: props.counts?.[value] ?? 0,
    })),
);

const hasFilters = computed(() => Boolean(form.search || form.trigger));

const resetFilters = () => {
    form.search = "";
    form.trigger = "";
};

// The server names the triggers in Polish, some with a pictogram in front; the translation
// wins where there is one, and the pictogram goes either way
const triggerLabel = (key, fallback) =>
    te(`automations.triggers.${key}`)
        ? t(`automations.triggers.${key}`)
        : String(fallback || key).replace(/^[^\p{L}\p{N}]+/u, "");

// The built-in automations were seeded with a pictogram in front of the name
const ruleName = (rule) =>
    rule.is_system ? rule.name.replace(/^[^\p{L}\p{N}]+/u, "") || rule.name : rule.name;

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

// One switch at a time per row: a second click waits for the answer to the first
const toggling = ref(new Set());

const toggleStatus = (rule) => {
    if (toggling.value.has(rule.id)) return;
    const wasActive = rule.is_active;
    toggling.value.add(rule.id);
    router.post(
        route("automations.toggle-status", rule.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () =>
                showToast(t(wasActive ? "automations.deactivated" : "automations.activated")),
            onFinish: () => toggling.value.delete(rule.id),
        },
    );
};

// The copy opens in the builder straight away
const duplicateRule = (rule) => {
    router.post(route("automations.duplicate", rule.id));
};

const ruleToDelete = ref(null);
const deleting = ref(false);

const deleteRule = () => {
    if (!ruleToDelete.value) return;
    deleting.value = true;
    router.delete(route("automations.destroy", ruleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("automations.deleted")),
        onFinish: () => {
            deleting.value = false;
            ruleToDelete.value = null;
        },
    });
};

const showRestore = ref(false);
const restoring = ref(false);

// The server knows how many were missing, so the outcome is its sentence
const restoreDefaults = () => {
    restoring.value = true;
    router.post(
        route("automations.restore-defaults"),
        {},
        {
            preserveScroll: true,
            onSuccess: () => showToast(page.props.flash?.success || page.props.flash?.info),
            onFinish: () => {
                restoring.value = false;
                showRestore.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('automations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('automations.title')" :subtitle="$t('automations.subtitle')">
                <ActionMenu :label="$t('automations.more_actions')">
                    <ActionMenuItem @click="showRestore = true">
                        {{ $t("automations.restore_defaults") }}
                    </ActionMenuItem>
                </ActionMenu>
                <CreateLink :href="route('automations.create')">
                    {{ $t("automations.create") }}
                </CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <SegmentedControl
                v-model="view"
                :options="viewOptions"
                :label="$t('automations.views.label')"
                class="self-start"
            />

            <Panel flush>
                <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
                    <SearchField v-model="form.search" :placeholder="$t('automations.search_placeholder')" />

                    <SelectField
                        v-model="form.trigger"
                        :label="$t('automations.trigger_filter')"
                        class="sm:max-w-72"
                    >
                        <option value="">{{ $t("automations.all_triggers") }}</option>
                        <option v-for="(label, key) in triggerEvents" :key="key" :value="key">
                            {{ triggerLabel(key, label) }}
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
                    v-if="rules.data.length === 0 && (hasFilters || view !== 'all')"
                    :title="$t('automations.empty_filtered')"
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
                    v-else-if="rules.data.length === 0"
                    :title="$t('automations.no_rules')"
                    :description="$t('automations.no_rules_hint')"
                >
                    <CreateLink :href="route('automations.create')">
                        {{ $t("automations.create_first") }}
                    </CreateLink>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-transparent dark:text-slate-200 dark:hover:bg-slate-700/60"
                        @click="showRestore = true"
                    >
                        {{ $t("automations.restore_defaults") }}
                    </button>
                </EmptyState>

                <div v-else class="overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("automations.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">
                                    {{ $t("automations.trigger_filter") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium">
                                    {{ $t("automations.steps") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("automations.executions") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("automations.enabled") }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("common.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rule in rules.data"
                                :key="rule.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3" style="width: 38%">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="route('automations.edit', rule.id)"
                                            class="min-w-0 truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                            :title="ruleName(rule)"
                                        >
                                            {{ ruleName(rule) }}
                                        </Link>
                                        <StatusPill v-if="rule.is_system" tone="info" class="shrink-0">
                                            {{ $t("automations.default") }}
                                        </StatusPill>
                                    </div>
                                    <p
                                        v-if="rule.description"
                                        class="truncate text-xs text-slate-600 dark:text-slate-400"
                                        :title="rule.description"
                                    >
                                        {{ rule.description }}
                                    </p>
                                </td>

                                <td class="max-w-0 px-3 py-3" style="width: 22%">
                                    <p
                                        class="truncate text-slate-900 dark:text-slate-100"
                                        :title="triggerLabel(rule.trigger_event, rule.trigger_event_label)"
                                    >
                                        {{ triggerLabel(rule.trigger_event, rule.trigger_event_label) }}
                                    </p>
                                </td>

                                <td class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ number(rule.actions_count) }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-3">
                                    <p class="tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ number(rule.execution_count) }}
                                    </p>
                                    <p class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                        {{
                                            rule.last_executed_at
                                                ? $t("automations.last_run", { date: rule.last_executed_at })
                                                : $t("automations.never_run")
                                        }}
                                    </p>
                                </td>

                                <td class="px-3 py-3">
                                    <Switch
                                        :model-value="rule.is_active"
                                        :label="$t('automations.switch_label', { name: ruleName(rule) })"
                                        :disabled="toggling.has(rule.id)"
                                        @update:model-value="toggleStatus(rule)"
                                    />
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="route('automations.logs', rule.id)"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                        >
                                            {{ $t("automations.logs") }}
                                        </Link>
                                        <ActionMenu :label="$t('automations.actions_for', { name: ruleName(rule) })">
                                            <ActionMenuItem :href="route('automations.edit', rule.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem @click="duplicateRule(rule)">
                                                {{ $t("common.duplicate") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="ruleToDelete = rule">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="rules.total > 0" v-model:per-page="form.per_page" :paginator="rules" />
            </Panel>
        </div>

        <ConfirmModal
            :show="!!ruleToDelete"
            type="danger"
            :title="$t('automations.delete_title')"
            :message="$t('automations.delete_description')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="ruleToDelete = null"
            @confirm="deleteRule"
        >
            {{ ruleToDelete ? ruleName(ruleToDelete) : "" }}
        </ConfirmModal>

        <ConfirmModal
            :show="showRestore"
            type="info"
            :title="$t('automations.restore_title')"
            :message="$t('automations.confirm_restore')"
            :confirm-text="$t('automations.restore')"
            :processing="restoring"
            @close="showRestore = false"
            @confirm="restoreDefaults"
        />

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
