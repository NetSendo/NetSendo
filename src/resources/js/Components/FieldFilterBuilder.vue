<script setup>
import { ref, computed, watch } from "vue";
import { debounce } from "lodash";
import axios from "axios";
import FieldValueSelector from "@/Components/FieldValueSelector.vue";

/**
 * Narrows an audience by subscriber custom fields.
 *
 * Used twice on the message form: on the include side it decides who receives
 * the message ("only people whose city is Oświęcim"), on the exclude side it
 * narrows who gets dropped ("from the excluded list, drop only the Kraków
 * people") — the same rows, the opposite meaning.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    match: { type: String, default: "all" },
    // Lists the fields and their values are read from
    listIds: { type: Array, default: () => [] },
    mode: { type: String, default: "include" }, // include | exclude
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "update:match"]);

const fields = ref([]);
const isLoadingFields = ref(false);

const isExclude = computed(() => props.mode === "exclude");
const accent = computed(() => (isExclude.value ? "red" : "indigo"));

const fetchFields = async () => {
    isLoadingFields.value = true;
    try {
        const response = await axios.get(route("messages.audience.fields"), {
            params: { list_ids: props.listIds },
        });
        fields.value = response.data.fields ?? [];
    } catch (error) {
        fields.value = [];
    } finally {
        isLoadingFields.value = false;
    }
};

const debouncedFetchFields = debounce(fetchFields, 200);

watch(() => props.listIds, debouncedFetchFields, { deep: true, immediate: true });

const fieldById = (id) => fields.value.find((field) => field.id === Number(id));

// Rows kept from an earlier edit can point at a field that is no longer offered
// (its list was deselected). Show them, flagged, instead of dropping the row.
const rowField = (row) => fieldById(row.custom_field_id) ?? null;

const operatorsFor = (row) => {
    const field = rowField(row);
    return field?.operators ?? ["any_of", "none_of", "is_set", "is_empty"];
};

const valueMode = (row) => {
    if (["is_set", "is_empty"].includes(row.operator)) return "none";
    if (["any_of", "none_of"].includes(row.operator)) return "list";
    if (row.operator === "between") return "range";
    return "single";
};

const inputType = (row) => {
    const type = rowField(row)?.type ?? row.field_type;
    if (type === "number") return "number";
    if (type === "date") return "date";
    return "text";
};

const update = (rows) => emit("update:modelValue", rows);

const addRow = () => {
    const first = fields.value[0];
    update([
        ...props.modelValue,
        {
            custom_field_id: first?.id ?? null,
            operator: first?.operators?.[0] ?? "any_of",
            values: [],
        },
    ]);
};

const removeRow = (index) => {
    update(props.modelValue.filter((_, i) => i !== index));
};

const patchRow = (index, patch) => {
    update(
        props.modelValue.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
};

const onFieldChange = (index, fieldId) => {
    const field = fieldById(fieldId);
    const current = props.modelValue[index];
    const operators = field?.operators ?? [];
    // Keep the operator when the new field still supports it, otherwise fall
    // back to its first one — and always drop values picked for the old field.
    const operator = operators.includes(current.operator)
        ? current.operator
        : (operators[0] ?? "any_of");

    patchRow(index, {
        custom_field_id: field ? field.id : null,
        operator,
        values: [],
        field_label: field?.label,
        field_type: field?.type,
    });
};

const onOperatorChange = (index, operator) => {
    const current = props.modelValue[index];
    const wasList = ["any_of", "none_of"].includes(current.operator);
    const isList = ["any_of", "none_of"].includes(operator);
    // Multi-value selections do not survive a switch to a single-value operator
    patchRow(index, {
        operator,
        values: wasList === isList ? current.values : [],
    });
};

const setSingleValue = (index, value, position = 0) => {
    const values = [...(props.modelValue[index].values ?? [])];
    values[position] = value;
    patchRow(index, { values });
};

const rowsWithoutValue = computed(() =>
    props.modelValue.filter((row) => {
        if (["is_set", "is_empty"].includes(row.operator)) return false;
        const values = (row.values ?? []).filter((v) => v !== null && v !== "");
        return row.operator === "between" ? values.length < 2 : values.length === 0;
    }),
);

const groupedFields = computed(() => {
    const global = fields.value.filter((field) => field.scope === "global");
    const perList = fields.value.filter((field) => field.scope !== "global");
    return { global, perList };
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{
                    isExclude
                        ? $t("messages.field_filters.exclude_help")
                        : $t("messages.field_filters.include_help")
                }}
            </p>

            <!-- How several conditions combine -->
            <div v-if="modelValue.length > 1" class="flex items-center gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $t("messages.field_filters.match_label") }}
                </span>
                <select
                    :value="match"
                    :disabled="disabled"
                    class="rounded-md border-slate-300 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    @change="$emit('update:match', $event.target.value)"
                >
                    <option value="all">{{ $t("messages.field_filters.match_all") }}</option>
                    <option value="any">{{ $t("messages.field_filters.match_any") }}</option>
                </select>
            </div>
        </div>

        <!-- Conditions -->
        <div
            v-for="(row, index) in modelValue"
            :key="index"
            class="rounded-lg border p-3"
            :class="
                isExclude
                    ? 'border-red-200 bg-red-50/40 dark:border-red-900/50 dark:bg-red-900/10'
                    : 'border-slate-200 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-800/40'
            "
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                <!-- Field -->
                <select
                    :value="row.custom_field_id ?? ''"
                    :disabled="disabled"
                    class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:w-1/3"
                    @change="onFieldChange(index, $event.target.value)"
                >
                    <option value="" disabled>
                        {{ $t("messages.field_filters.select_field") }}
                    </option>
                    <optgroup
                        v-if="groupedFields.global.length"
                        :label="$t('messages.field_filters.global_fields')"
                    >
                        <option
                            v-for="field in groupedFields.global"
                            :key="field.id"
                            :value="field.id"
                        >
                            {{ field.label }}
                        </option>
                    </optgroup>
                    <optgroup
                        v-if="groupedFields.perList.length"
                        :label="$t('messages.field_filters.list_fields')"
                    >
                        <option
                            v-for="field in groupedFields.perList"
                            :key="field.id"
                            :value="field.id"
                        >
                            {{ field.label }}
                            <template v-if="field.contact_list_name">
                                — {{ field.contact_list_name }}
                            </template>
                        </option>
                    </optgroup>
                    <!-- Field stored on the message but not offered for the current lists -->
                    <option
                        v-if="row.custom_field_id && !rowField(row)"
                        :value="row.custom_field_id"
                    >
                        {{ row.field_label || $t("messages.field_filters.unknown_field") }}
                    </option>
                </select>

                <!-- Operator -->
                <select
                    :value="row.operator"
                    :disabled="disabled"
                    class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:w-1/4"
                    @change="onOperatorChange(index, $event.target.value)"
                >
                    <option
                        v-for="operator in operatorsFor(row)"
                        :key="operator"
                        :value="operator"
                    >
                        {{ $t(`messages.field_filters.operators.${operator}`) }}
                    </option>
                </select>

                <!-- Value -->
                <div class="w-full sm:flex-1">
                    <FieldValueSelector
                        v-if="valueMode(row) === 'list'"
                        :model-value="row.values ?? []"
                        :field-id="row.custom_field_id"
                        :field-options="rowField(row)?.options ?? []"
                        :list-ids="listIds"
                        :disabled="disabled || !row.custom_field_id"
                        :accent="accent"
                        @update:model-value="patchRow(index, { values: $event })"
                    />

                    <input
                        v-else-if="valueMode(row) === 'single'"
                        :type="inputType(row)"
                        :value="row.values?.[0] ?? ''"
                        :disabled="disabled"
                        :placeholder="$t('messages.field_filters.value_placeholder')"
                        class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                        @input="setSingleValue(index, $event.target.value)"
                    />

                    <div v-else-if="valueMode(row) === 'range'" class="flex items-center gap-2">
                        <input
                            :type="inputType(row)"
                            :value="row.values?.[0] ?? ''"
                            :disabled="disabled"
                            class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            @input="setSingleValue(index, $event.target.value, 0)"
                        />
                        <span class="text-xs text-slate-400">—</span>
                        <input
                            :type="inputType(row)"
                            :value="row.values?.[1] ?? ''"
                            :disabled="disabled"
                            class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                            @input="setSingleValue(index, $event.target.value, 1)"
                        />
                    </div>

                    <p v-else class="py-2 text-xs text-slate-400">
                        {{ $t("messages.field_filters.no_value_needed") }}
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="disabled"
                    class="self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30"
                    :title="$t('messages.field_filters.remove')"
                    @click="removeRow(index)"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                    </svg>
                </button>
            </div>

            <p
                v-if="row.custom_field_id && !rowField(row)"
                class="mt-2 text-xs text-amber-600 dark:text-amber-400"
            >
                {{ $t("messages.field_filters.field_unavailable") }}
            </p>
        </div>

        <p
            v-if="rowsWithoutValue.length > 0"
            class="text-xs text-amber-600 dark:text-amber-400"
        >
            {{ $t("messages.field_filters.incomplete_warning") }}
        </p>

        <div class="flex items-center gap-3">
            <button
                type="button"
                :disabled="disabled || isLoadingFields || fields.length === 0"
                class="inline-flex items-center gap-1.5 rounded-md border border-dashed px-3 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="
                    isExclude
                        ? 'border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20'
                        : 'border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800'
                "
                @click="addRow"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t("messages.field_filters.add_condition") }}
            </button>

            <span v-if="isLoadingFields" class="text-xs text-slate-400">
                {{ $t("messages.field_filters.loading_fields") }}
            </span>
            <span
                v-else-if="fields.length === 0"
                class="text-xs text-slate-400"
            >
                {{ $t("messages.field_filters.no_fields") }}
            </span>
        </div>
    </div>
</template>
