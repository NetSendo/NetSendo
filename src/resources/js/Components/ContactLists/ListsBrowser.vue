<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import debounce from "lodash/debounce";
import pickBy from "lodash/pickBy";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import SortMark from "@/Components/Ui/SortMark.vue";
import { contrastTextColor } from "@/Composables/useColorContrast";

/**
 * The body of "E-mail lists" and "SMS lists": the two pages are the same list of contact lists
 * and differ only in their texts, their routes and the kind of message a list is written to.
 * Filters, the table / cards switch, sorting and paging live here; deleting and copying are
 * dialogs of the page, asked for through events.
 */
const props = defineProps({
    lists: { type: Object, required: true },
    filters: { type: [Object, Array], default: () => ({}) },
    groups: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    // Translation namespace: "mailing_lists" or "sms_lists"
    ns: { type: String, required: true },
    // "email" or "sms": the list type the subscriber list is filtered by
    listType: { type: String, required: true },
    indexRoute: { type: String, required: true },
    createRoute: { type: String, required: true },
    editRoute: { type: String, required: true },
    // Where "write to this list" leads, and what the action is called
    messageRoute: { type: String, required: true },
    messageLabel: { type: String, required: true },
    // localStorage key of the table / cards choice
    viewKey: { type: String, required: true },
});

defineEmits(["delete", "copy"]);

const { locale } = useI18n();

// With no filter in the URL, PHP may send an empty list instead of an object
const filters = Array.isArray(props.filters) ? {} : props.filters || {};

const form = reactive({
    search: filters.search || "",
    group_id: filters.group_id || "",
    tag_id: filters.tag_id || "",
    visibility: filters.visibility || "",
    sort_col: filters.sort_col || "created_at",
    sort_dir: filters.sort_dir || "desc",
    per_page: filters.per_page || "12",
});

watch(
    form,
    debounce(() => {
        router.get(route(props.indexRoute), pickBy(form), {
            preserveState: true,
            replace: true,
        });
    }, 300),
);

const hasFilters = computed(() =>
    Boolean(form.search || form.group_id || form.tag_id || form.visibility),
);

const resetFilters = () => {
    form.search = "";
    form.group_id = "";
    form.tag_id = "";
    form.visibility = "";
};

// A second click on the sorted column turns it around; a new column starts in its natural order
const sort = (column) => {
    if (form.sort_col === column) {
        form.sort_dir = form.sort_dir === "asc" ? "desc" : "asc";
    } else {
        form.sort_col = column;
        form.sort_dir = column === "name" ? "asc" : "desc";
    }
};

const sortState = (column) => (form.sort_col === column ? form.sort_dir : null);
const ariaSort = (column) =>
    form.sort_col === column ? (form.sort_dir === "asc" ? "ascending" : "descending") : "none";

const storedView = () => {
    try {
        return localStorage.getItem(props.viewKey) === "grid" ? "grid" : "list";
    } catch {
        return "list";
    }
};

const viewMode = ref(storedView());

watch(viewMode, (mode) => {
    try {
        localStorage.setItem(props.viewKey, mode);
    } catch {
        // Private mode: the choice lasts for this visit
    }
});

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const subscribersHref = (list) =>
    route("subscribers.index", { list_id: list.id, list_type: props.listType });

const inputClass =
    "min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white";
</script>

<template>
    <Panel flush>
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 px-5 pb-4 pt-2">
            <label class="relative min-w-0 flex-1 basis-64">
                <span class="sr-only">{{ $t(`${ns}.search_placeholder`) }}</span>
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
                    :placeholder="$t(`${ns}.search_placeholder`)"
                    class="w-full pl-9 placeholder:text-slate-500 dark:placeholder:text-slate-400"
                    :class="inputClass"
                />
            </label>

            <select
                v-if="groups.length"
                v-model="form.group_id"
                :aria-label="$t(`${ns}.all_groups`)"
                class="max-w-56"
                :class="inputClass"
            >
                <option value="">{{ $t(`${ns}.all_groups`) }}</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ "—".repeat(group.depth) }}{{ group.depth > 0 ? " " : "" }}{{ group.name }}
                </option>
            </select>

            <select
                v-if="tags.length"
                v-model="form.tag_id"
                :aria-label="$t(`${ns}.all_tags`)"
                class="max-w-48"
                :class="inputClass"
            >
                <option value="">{{ $t(`${ns}.all_tags`) }}</option>
                <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                    {{ tag.name }}
                </option>
            </select>

            <select
                v-model="form.visibility"
                :aria-label="$t('lists_browser.visibility')"
                :class="inputClass"
            >
                <option value="">{{ $t("lists_browser.visibility_all") }}</option>
                <option value="public">{{ $t(`${ns}.public`) }}</option>
                <option value="private">{{ $t(`${ns}.private`) }}</option>
            </select>

            <button
                v-if="hasFilters"
                type="button"
                class="min-h-10 px-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                @click="resetFilters"
            >
                {{ $t(`${ns}.clear_filters`) }}
            </button>

            <!-- Table or cards -->
            <div
                role="group"
                :aria-label="$t('lists_browser.view')"
                class="ml-auto inline-flex rounded-xl border border-slate-300 p-0.5 dark:border-slate-600"
            >
                <button
                    v-for="mode in ['list', 'grid']"
                    :key="mode"
                    type="button"
                    :aria-pressed="viewMode === mode"
                    :aria-label="$t(`${ns}.${mode}_view`)"
                    :title="$t(`${ns}.${mode}_view`)"
                    class="flex h-9 w-9 items-center justify-center rounded-[10px] transition-colors"
                    :class="
                        viewMode === mode
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
                    "
                    @click="viewMode = mode"
                >
                    <svg
                        aria-hidden="true"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        viewBox="0 0 24 24"
                    >
                        <path v-if="mode === 'list'" d="M4 6h16M4 12h16M4 18h16" />
                        <path
                            v-else
                            d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 15h6v4h-6z"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Nothing to show -->
        <div
            v-if="lists.data.length === 0"
            class="flex flex-col items-center gap-2 border-t border-slate-200 px-5 py-16 text-center dark:border-slate-700/60"
        >
            <template v-if="hasFilters">
                <p class="text-sm font-medium text-slate-900 dark:text-white">
                    {{ $t("common.no_results") }}
                </p>
                <button
                    type="button"
                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    @click="resetFilters"
                >
                    {{ $t(`${ns}.clear_filters`) }}
                </button>
            </template>
            <template v-else>
                <p class="text-sm font-medium text-slate-900 dark:text-white">
                    {{ $t(`${ns}.empty_title`) }}
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t(`${ns}.empty_description`) }}
                </p>
                <Link
                    :href="route(createRoute)"
                    class="mt-2 inline-flex min-h-10 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
                >
                    {{ $t(`${ns}.create_list`) }}
                </Link>
            </template>
        </div>

        <!-- Table -->
        <div
            v-else-if="viewMode === 'list'"
            class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60"
        >
            <table class="w-full sm:min-w-[760px] text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                        <th scope="col" class="px-5 py-2.5 font-medium" :aria-sort="ariaSort('name')">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('name')">
                                {{ $t(`${ns}.table.name`) }}
                                <SortMark :state="sortState('name')" />
                            </button>
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                            {{ $t("lists_browser.visibility") }}
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                            {{ $t(`${ns}.table.groups_tags`) }}
                        </th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium" :aria-sort="ariaSort('subscribers_count')">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('subscribers_count')">
                                {{ $t(`${ns}.table.subscribers`) }}
                                <SortMark :state="sortState('subscribers_count')" />
                            </button>
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden" :aria-sort="ariaSort('created_at')">
                            <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white" @click="sort('created_at')">
                                {{ $t(`${ns}.table.created_at`) }}
                                <SortMark :state="sortState('created_at')" />
                            </button>
                        </th>
                        <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                            <span class="sr-only">{{ $t(`${ns}.table.actions`) }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="list in lists.data"
                        :key="list.id"
                        class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                    >
                        <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[38%]">
                            <Link
                                :href="route(editRoute, list.id)"
                                class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                :title="list.name"
                            >
                                {{ list.name }}
                            </Link>
                            <p
                                v-if="list.description"
                                class="truncate text-slate-600 dark:text-slate-400"
                                :title="list.description"
                            >
                                {{ list.description }}
                            </p>
                            <p class="mt-0.5 text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                #{{ list.id }}
                            </p>
                        </td>
                        <td class="px-3 py-3 max-sm:hidden">
                            <StatusPill :tone="list.is_public ? 'info' : 'neutral'">
                                {{ list.is_public ? $t(`${ns}.public`) : $t(`${ns}.private`) }}
                            </StatusPill>
                        </td>
                        <td class="px-3 py-3 max-sm:hidden">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span v-if="list.group" class="text-slate-900 dark:text-slate-100">
                                    {{ list.group.name }}
                                </span>
                                <span
                                    v-for="tag in list.tags"
                                    :key="tag.id"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :style="{ backgroundColor: tag.color, color: contrastTextColor(tag.color) }"
                                >
                                    {{ tag.name }}
                                </span>
                                <span
                                    v-if="!list.group && !list.tags?.length"
                                    class="text-slate-600 dark:text-slate-400"
                                    >—</span
                                >
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <Link
                                :href="subscribersHref(list)"
                                :title="$t(`${ns}.view_subscribers`)"
                                class="font-medium tabular-nums text-slate-900 underline decoration-slate-300 underline-offset-4 hover:text-indigo-600 hover:decoration-indigo-400 dark:text-white dark:decoration-slate-600 dark:hover:text-indigo-300"
                            >
                                {{ number(list.subscribers_count) }}
                            </Link>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 dark:text-slate-400 max-sm:hidden">
                            {{ list.created_at }}
                        </td>
                        <td class="py-3 pl-3 pr-5">
                            <div class="flex items-center justify-end">
                                <ActionMenu :label="$t('lists_browser.actions_for', { name: list.name })">
                                    <ActionMenuItem :href="subscribersHref(list)">
                                        {{ $t(`${ns}.view_subscribers`) }}
                                    </ActionMenuItem>
                                    <ActionMenuItem :href="route(messageRoute, { list_id: list.id })">
                                        {{ messageLabel }}
                                    </ActionMenuItem>
                                    <ActionMenuItem :href="route(editRoute, list.id)">
                                        {{ $t(`${ns}.edit`) }}
                                    </ActionMenuItem>
                                    <ActionMenuItem @click="$emit('copy', list)">
                                        {{ $t(`${ns}.copy`) }}
                                    </ActionMenuItem>
                                    <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                    <ActionMenuItem danger @click="$emit('delete', list)">
                                        {{ $t(`${ns}.delete`) }}
                                    </ActionMenuItem>
                                </ActionMenu>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards -->
        <ul
            v-else
            class="grid grid-cols-1 gap-4 border-t border-slate-200 p-5 sm:grid-cols-2 xl:grid-cols-3 dark:border-slate-700/60"
        >
            <li
                v-for="list in lists.data"
                :key="list.id"
                class="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700/60"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <Link
                            :href="route(editRoute, list.id)"
                            class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                            :title="list.name"
                        >
                            {{ list.name }}
                        </Link>
                        <p class="line-clamp-2 text-sm text-slate-600 dark:text-slate-400">
                            {{ list.description || $t(`${ns}.no_description`) }}
                        </p>
                    </div>
                    <ActionMenu :label="$t('lists_browser.actions_for', { name: list.name })">
                        <ActionMenuItem :href="subscribersHref(list)">
                            {{ $t(`${ns}.view_subscribers`) }}
                        </ActionMenuItem>
                        <ActionMenuItem :href="route(messageRoute, { list_id: list.id })">
                            {{ messageLabel }}
                        </ActionMenuItem>
                        <ActionMenuItem :href="route(editRoute, list.id)">
                            {{ $t(`${ns}.edit`) }}
                        </ActionMenuItem>
                        <ActionMenuItem @click="$emit('copy', list)">
                            {{ $t(`${ns}.copy`) }}
                        </ActionMenuItem>
                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                        <ActionMenuItem danger @click="$emit('delete', list)">
                            {{ $t(`${ns}.delete`) }}
                        </ActionMenuItem>
                    </ActionMenu>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 text-sm">
                    <StatusPill :tone="list.is_public ? 'info' : 'neutral'">
                        {{ list.is_public ? $t(`${ns}.public`) : $t(`${ns}.private`) }}
                    </StatusPill>
                    <span v-if="list.group" class="text-slate-700 dark:text-slate-300">
                        {{ list.group.name }}
                    </span>
                    <span
                        v-for="tag in list.tags"
                        :key="tag.id"
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :style="{ backgroundColor: tag.color, color: contrastTextColor(tag.color) }"
                    >
                        {{ tag.name }}
                    </span>
                </div>

                <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-100 pt-3 text-sm dark:border-slate-700/40">
                    <Link
                        :href="subscribersHref(list)"
                        class="text-slate-600 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300"
                    >
                        <span class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{
                            number(list.subscribers_count)
                        }}</span>
                        {{ $t(`${ns}.table.subscribers`).toLowerCase() }}
                    </Link>
                    <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                        #{{ list.id }} · {{ list.created_at }}
                    </span>
                </div>
            </li>
        </ul>

        <Pagination
            v-if="lists.total > 0"
            v-model:per-page="form.per_page"
            :paginator="lists"
            :per-page-options="[12, 24, 48, 96]"
        />
    </Panel>
</template>
