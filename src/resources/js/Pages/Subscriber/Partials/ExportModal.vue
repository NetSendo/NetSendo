<script setup>
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import Modal from "@/Components/Modal.vue";

const { t } = useI18n();

const props = defineProps({
    show: { type: Boolean, required: true },
    // Filters currently applied to the table, so "export what I see" is exact.
    filters: { type: Object, default: () => ({}) },
    selectedIds: { type: Array, default: () => [] },
    lists: { type: Array, default: () => [] },
    exportColumns: { type: Object, default: () => ({}) },
    exportUrl: { type: String, required: true },
    totalCount: { type: Number, default: 0 },
});

const emit = defineEmits(["close"]);

const PRESETS = [
    { key: "netsendo", recommended: true },
    { key: "basic" },
    { key: "contact" },
    { key: "marketing" },
    { key: "full" },
    { key: "custom" },
];

// Only the columns the "custom" variant would not pull in automatically are
// worth listing; the rest are shown as read-only chips.
const PRESET_COLUMNS = {
    netsendo: [
        "netsendo_id", "email", "phone", "first_name", "last_name", "gender", "language",
        "timezone", "source", "status", "list_status", "lists", "tags",
        "subscribed_at", "confirmed_at", "unsubscribed_at",
    ],
    basic: ["email", "first_name", "last_name"],
    contact: ["email", "phone", "first_name", "last_name", "gender", "language"],
    marketing: [
        "email", "first_name", "last_name", "lists", "tags", "list_status",
        "subscribed_at", "opens_count", "clicks_count", "last_opened_at", "last_clicked_at",
    ],
    full: [],
    custom: [],
};

const FORMATS = ["csv_excel", "csv", "tsv", "json", "ndjson"];

const preset = ref("netsendo");
const format = ref("csv_excel");
const scope = ref("filtered");
const membership = ref("active");
const dateFormat = ref("iso");
const customFields = ref([]);
const submitting = ref(false);

const hasSelection = computed(() => props.selectedIds.length > 0);

const standardColumns = computed(() => props.exportColumns?.standard ?? []);
const customFieldColumns = computed(() => props.exportColumns?.custom_fields ?? []);

const allColumns = computed(() => [
    ...standardColumns.value.map((key) => ({ key, label: t(`subscribers.export.columns.${key}`) })),
    ...customFieldColumns.value.map((field) => ({
        key: field.key,
        label: field.label || field.name,
    })),
]);

// What the file will actually contain, so the choice is never a guess.
const previewColumns = computed(() => {
    if (preset.value === "custom") {
        return customFields.value;
    }

    const base = preset.value === "full" ? standardColumns.value : PRESET_COLUMNS[preset.value];
    const withCustom = preset.value === "full" || preset.value === "netsendo";

    return withCustom
        ? [...base, ...customFieldColumns.value.map((f) => f.key)]
        : [...base];
});

const columnLabel = (key) => {
    const found = allColumns.value.find((column) => column.key === key);
    return found ? found.label : key;
};

const roundTripSafe = computed(
    () => preset.value === "netsendo" || preset.value === "full",
);

const selectedListName = computed(() => {
    const list = props.lists.find((l) => String(l.id) === String(props.filters?.list_id));
    return list ? list.name : null;
});

const rowsLabel = computed(() => {
    if (scope.value === "selected") {
        return t("subscribers.export.scope.selected_count", { count: props.selectedIds.length });
    }
    return props.totalCount
        ? t("subscribers.export.scope.filtered_count", { count: props.totalCount })
        : t("subscribers.export.scope.filtered_all");
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            scope.value = hasSelection.value ? "selected" : "filtered";
            submitting.value = false;
        }
    },
);

watch(preset, (value) => {
    if (value === "custom" && customFields.value.length === 0) {
        customFields.value = [...PRESET_COLUMNS.netsendo];
    }
});

const toggleColumn = (key) => {
    const index = customFields.value.indexOf(key);
    if (index === -1) {
        customFields.value.push(key);
    } else {
        customFields.value.splice(index, 1);
    }
};

/**
 * The download goes out as a native form POST rather than through Inertia or
 * axios: the response is a streamed file, and a plain form submit lets the
 * browser save it without ever holding the whole export in memory.
 */
const submit = () => {
    if (preset.value === "custom" && customFields.value.length === 0) {
        return;
    }

    submitting.value = true;

    const form = document.createElement("form");
    form.method = "POST";
    form.action = props.exportUrl;
    form.style.display = "none";

    const append = (name, value) => {
        if (value === null || value === undefined || value === "") return;
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    append(
        "_token",
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
    );
    append("preset", preset.value);
    append("format", format.value);
    append("scope", scope.value);
    append("membership", membership.value);
    append("date_format", dateFormat.value);

    if (scope.value === "selected") {
        props.selectedIds.forEach((id) => append("ids[]", id));
    } else {
        append("search", props.filters?.search);
        append("list_id", props.filters?.list_id);
        append("list_type", props.filters?.list_type);
    }

    if (preset.value === "custom") {
        customFields.value.forEach((field) => append("fields[]", field));
    }

    document.body.appendChild(form);
    form.submit();

    // The page never navigates, so the dialog has to close itself — and the
    // form stays in the document until the request is on its way.
    setTimeout(() => {
        form.remove();
        submitting.value = false;
        emit("close");
    }, 800);
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <div class="mb-5 flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/50"
                >
                    <svg
                        class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                        />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t("subscribers.export.title") }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t("subscribers.export.subtitle") }}
                    </p>
                </div>
            </div>

            <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
                <!-- Scope -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ t("subscribers.export.scope.label") }}
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-if="hasSelection"
                            type="button"
                            @click="scope = 'selected'"
                            :class="[
                                'rounded-xl border px-3 py-2.5 text-left text-sm transition-colors',
                                scope === 'selected'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <span class="font-medium">{{
                                t("subscribers.export.scope.selected")
                            }}</span>
                            <span class="block text-xs opacity-75">
                                {{
                                    t("subscribers.export.scope.selected_count", {
                                        count: selectedIds.length,
                                    })
                                }}
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="scope = 'filtered'"
                            :class="[
                                'rounded-xl border px-3 py-2.5 text-left text-sm transition-colors',
                                scope === 'filtered'
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <span class="font-medium">{{
                                t("subscribers.export.scope.filtered")
                            }}</span>
                            <span class="block text-xs opacity-75">
                                {{
                                    selectedListName
                                        ? t("subscribers.export.scope.from_list", {
                                              list: selectedListName,
                                          })
                                        : t("subscribers.export.scope.all_lists")
                                }}
                            </span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ rowsLabel }}
                    </p>
                </div>

                <!-- Variant -->
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ t("subscribers.export.variant.label") }}
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="item in PRESETS"
                            :key="item.key"
                            type="button"
                            @click="preset = item.key"
                            :class="[
                                'rounded-xl border px-3 py-2.5 text-left text-sm transition-colors',
                                preset === item.key
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <span class="flex items-center gap-2 font-medium">
                                {{ t(`subscribers.export.variant.${item.key}.name`) }}
                                <span
                                    v-if="item.recommended"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                                >
                                    {{ t("subscribers.export.variant.recommended") }}
                                </span>
                            </span>
                            <span class="mt-0.5 block text-xs opacity-75">
                                {{ t(`subscribers.export.variant.${item.key}.desc`) }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Custom column picker -->
                <div
                    v-if="preset === 'custom'"
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ t("subscribers.export.columns_label") }}
                        </span>
                        <button
                            type="button"
                            class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                            @click="customFields = allColumns.map((c) => c.key)"
                        >
                            {{ t("subscribers.export.select_all") }}
                        </button>
                    </div>
                    <div class="grid max-h-48 gap-1.5 overflow-y-auto sm:grid-cols-2">
                        <label
                            v-for="column in allColumns"
                            :key="column.key"
                            class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400"
                        >
                            <input
                                type="checkbox"
                                :checked="customFields.includes(column.key)"
                                @change="toggleColumn(column.key)"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            {{ column.label }}
                        </label>
                    </div>
                    <p
                        v-if="customFields.length === 0"
                        class="mt-2 text-xs text-rose-600 dark:text-rose-400"
                    >
                        {{ t("subscribers.export.pick_one_column") }}
                    </p>
                </div>

                <!-- Column preview -->
                <div v-else>
                    <span
                        class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ t("subscribers.export.columns_label") }}
                    </span>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="column in previewColumns"
                            :key="column"
                            class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ columnLabel(column) }}
                        </span>
                    </div>
                </div>

                <!-- Format & options -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ t("subscribers.export.format.label") }}
                        </label>
                        <select
                            v-model="format"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option v-for="item in FORMATS" :key="item" :value="item">
                                {{ t(`subscribers.export.format.${item}`) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ t("subscribers.export.membership.label") }}
                        </label>
                        <select
                            v-model="membership"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="active">
                                {{ t("subscribers.export.membership.active") }}
                            </option>
                            <option value="all">
                                {{ t("subscribers.export.membership.all") }}
                            </option>
                            <option value="unsubscribed">
                                {{ t("subscribers.export.membership.unsubscribed") }}
                            </option>
                        </select>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        {{ t("subscribers.export.dates.label") }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in ['iso', 'local']"
                            :key="option"
                            type="button"
                            @click="dateFormat = option"
                            :class="[
                                'rounded-lg border px-3 py-1.5 text-xs transition-colors',
                                dateFormat === option
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                        >
                            {{ t(`subscribers.export.dates.${option}`) }}
                        </button>
                    </div>
                </div>

                <!-- Round-trip note -->
                <div
                    :class="[
                        'rounded-xl border p-3 text-xs',
                        roundTripSafe
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300'
                            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
                    ]"
                >
                    {{
                        roundTripSafe
                            ? t("subscribers.export.round_trip_ok")
                            : t("subscribers.export.round_trip_partial")
                    }}
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ t("common.cancel") }}
                </button>
                <button
                    type="button"
                    @click="submit"
                    :disabled="submitting || (preset === 'custom' && customFields.length === 0)"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-emerald-500 disabled:opacity-50"
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
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                        />
                    </svg>
                    {{
                        submitting
                            ? t("subscribers.export.preparing")
                            : t("subscribers.export.download")
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>
