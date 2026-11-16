<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import debounce from "lodash/debounce";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";

const props = defineProps({
    // { id, name, description, match, conditions } or null for a new segment
    segment: { type: Object, default: null },
    lists: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    messages: { type: Array, default: () => [] },
    // [{ id, label, type, options, operators }]
    fields: { type: Array, default: () => [] },
});

const { t, locale } = useI18n();
const isEditing = computed(() => !!props.segment?.id);

// Condition types in the order the menu shows them, grouped by what they test
const TYPE_GROUPS = [
    { key: "membership", types: ["on_list", "not_on_list", "has_tag", "not_has_tag"] },
    { key: "data", types: ["field", "joined_within", "joined_before"] },
    {
        key: "engagement",
        types: [
            "opened_within",
            "not_opened_within",
            "clicked_within",
            "not_clicked_within",
            "opened_message",
            "not_opened_message",
            "clicked_message",
            "not_clicked_message",
        ],
    },
];
const LIST_TYPES = ["on_list", "not_on_list"];
const TAG_TYPES = ["has_tag", "not_has_tag"];
const MESSAGE_TYPES = ["opened_message", "not_opened_message", "clicked_message", "not_clicked_message"];
const DAY_TYPES = [
    "opened_within",
    "not_opened_within",
    "clicked_within",
    "not_clicked_within",
    "joined_within",
    "joined_before",
];
const VALUELESS = ["is_set", "is_empty"];

// A field row keeps its values as typed text; "a, b" means either value
const toRow = (condition) => ({
    ...condition,
    valuesText: (condition.values ?? []).join(", "),
    valueFrom: condition.values?.[0] ?? "",
    valueTo: condition.values?.[1] ?? "",
});

const form = useForm({
    name: props.segment?.name ?? "",
    description: props.segment?.description ?? "",
    match: props.segment?.match ?? "all",
    conditions: (props.segment?.conditions ?? [{ type: "opened_within", days: 30 }]).map(toRow),
});

const matchOptions = computed(() => [
    { value: "all", label: t("segments.builder.match_all") },
    { value: "any", label: t("segments.builder.match_any") },
]);

const defaultsFor = (type) => {
    if (LIST_TYPES.includes(type)) return { list_id: props.lists[0]?.id ?? null };
    if (TAG_TYPES.includes(type)) return { tag_id: props.tags[0]?.id ?? null };
    if (MESSAGE_TYPES.includes(type)) return { message_id: props.messages[0]?.id ?? null };
    if (DAY_TYPES.includes(type)) return { days: 30 };
    if (type === "field") {
        const field = props.fields[0];
        return { custom_field_id: field?.id ?? null, operator: field?.operators?.[0] ?? "any_of" };
    }
    return {};
};

const changeType = (row, type) => {
    Object.keys(row).forEach((key) => delete row[key]);
    Object.assign(row, toRow({ type, ...defaultsFor(type) }));
};

const addCondition = () => form.conditions.push(toRow({ type: "on_list", ...defaultsFor("on_list") }));
const removeCondition = (index) => form.conditions.splice(index, 1);

const fieldOf = (row) => props.fields.find((field) => field.id === Number(row.custom_field_id));
const changeField = (row) => {
    const operators = fieldOf(row)?.operators ?? ["any_of"];
    if (!operators.includes(row.operator)) row.operator = operators[0];
};

// What goes to the server: only the keys each type reads
const payloadConditions = () =>
    form.conditions.map((row) => {
        const base = { type: row.type };
        if (LIST_TYPES.includes(row.type)) return { ...base, list_id: row.list_id };
        if (TAG_TYPES.includes(row.type)) return { ...base, tag_id: row.tag_id };
        if (MESSAGE_TYPES.includes(row.type)) return { ...base, message_id: row.message_id };
        if (DAY_TYPES.includes(row.type)) return { ...base, days: Number(row.days) };

        let values = [];
        if (row.operator === "between") values = [row.valueFrom, row.valueTo];
        else if (!VALUELESS.includes(row.operator)) {
            values = String(row.valuesText ?? "")
                .split(",")
                .map((value) => value.trim())
                .filter(Boolean);
        }
        return { ...base, custom_field_id: row.custom_field_id, operator: row.operator, values };
    });

// Live count of the people the conditions describe
const preview = ref(null);
const previewError = ref("");
const previewing = ref(false);

const fetchPreview = debounce(async () => {
    previewing.value = true;
    try {
        const response = await axios.post(route("segments.preview"), {
            match: form.match,
            conditions: payloadConditions(),
        });
        preview.value = response.data;
        previewError.value = "";
    } catch (error) {
        const errors = error.response?.data?.errors;
        previewError.value = errors ? Object.values(errors).flat()[0] : t("segments.builder.preview_failed");
        preview.value = null;
    } finally {
        previewing.value = false;
    }
}, 450);

watch(() => [form.match, form.conditions], fetchPreview, { deep: true, immediate: true });

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const conditionErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith("conditions"))
        .map(([, message]) => message),
);

const submit = () => {
    const options = { preserveScroll: true };
    form.transform((data) => ({ ...data, conditions: payloadConditions() }));
    isEditing.value
        ? form.put(route("segments.update", props.segment.id), options)
        : form.post(route("segments.store"), options);
};
</script>

<template>
    <Head :title="isEditing ? $t('segments.edit') : $t('segments.create')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                :title="isEditing ? $t('segments.edit') : $t('segments.create')"
                :subtitle="$t('segments.builder.subtitle')"
            >
                <Link
                    :href="route('segments.index')"
                    class="inline-flex min-h-10 items-center rounded-xl px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60"
                >
                    {{ $t("common.cancel") }}
                </Link>
            </PageHeader>
        </template>

        <form class="mx-auto grid max-w-screen-xl grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
            <div class="flex min-w-0 flex-col gap-4">
                <Panel :title="$t('segments.builder.details')">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="segment-name" :class="labelClass">{{ $t("segments.builder.name") }}</label>
                            <input
                                id="segment-name"
                                v-model="form.name"
                                type="text"
                                required
                                maxlength="120"
                                :placeholder="$t('segments.builder.name_placeholder')"
                                :class="[fieldClass, 'mt-1']"
                            />
                            <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label for="segment-description" :class="labelClass">
                                {{ $t("segments.builder.description") }}
                            </label>
                            <textarea
                                id="segment-description"
                                v-model="form.description"
                                rows="2"
                                maxlength="1000"
                                :class="[fieldClass, 'mt-1']"
                            />
                        </div>
                    </div>
                </Panel>

                <Panel :title="$t('segments.builder.conditions')">
                    <div class="flex flex-col gap-4">
                        <SegmentedControl
                            v-model="form.match"
                            :options="matchOptions"
                            :label="$t('segments.builder.match_label')"
                            class="self-start"
                        />

                        <ol class="flex flex-col gap-3">
                            <li
                                v-for="(row, index) in form.conditions"
                                :key="index"
                                class="rounded-xl border border-slate-200 p-3 dark:border-slate-700/60"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                    <span
                                        class="w-10 shrink-0 pt-2 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            index === 0
                                                ? $t("segments.builder.where")
                                                : form.match === "all"
                                                  ? $t("segments.builder.and")
                                                  : $t("segments.builder.or")
                                        }}
                                    </span>

                                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-2 md:grid-cols-2">
                                        <select
                                            :value="row.type"
                                            :aria-label="$t('segments.builder.condition_type')"
                                            :class="fieldClass"
                                            @change="changeType(row, $event.target.value)"
                                        >
                                            <optgroup
                                                v-for="group in TYPE_GROUPS"
                                                :key="group.key"
                                                :label="$t(`segments.groups.${group.key}`)"
                                            >
                                                <option v-for="type in group.types" :key="type" :value="type">
                                                    {{ $t(`segments.types.${type}`) }}
                                                </option>
                                            </optgroup>
                                        </select>

                                        <select
                                            v-if="LIST_TYPES.includes(row.type)"
                                            v-model="row.list_id"
                                            :aria-label="$t('segments.builder.list')"
                                            :class="fieldClass"
                                        >
                                            <option v-for="list in lists" :key="list.id" :value="list.id">
                                                {{ list.name }}
                                            </option>
                                        </select>

                                        <select
                                            v-else-if="TAG_TYPES.includes(row.type)"
                                            v-model="row.tag_id"
                                            :aria-label="$t('segments.builder.tag')"
                                            :class="fieldClass"
                                        >
                                            <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                                                {{ tag.name }}
                                            </option>
                                        </select>

                                        <select
                                            v-else-if="MESSAGE_TYPES.includes(row.type)"
                                            v-model="row.message_id"
                                            :aria-label="$t('segments.builder.message')"
                                            :class="fieldClass"
                                        >
                                            <option v-for="message in messages" :key="message.id" :value="message.id">
                                                {{ message.subject }}
                                            </option>
                                        </select>

                                        <label v-else-if="DAY_TYPES.includes(row.type)" class="flex items-center gap-2">
                                            <input
                                                v-model.number="row.days"
                                                type="number"
                                                min="1"
                                                max="3650"
                                                :aria-label="$t('segments.builder.days')"
                                                :class="[fieldClass, 'max-w-28 tabular-nums']"
                                            />
                                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                                {{ $t("segments.builder.days_unit") }}
                                            </span>
                                        </label>

                                        <template v-else-if="row.type === 'field'">
                                            <select
                                                v-model="row.custom_field_id"
                                                :aria-label="$t('segments.builder.field')"
                                                :class="fieldClass"
                                                @change="changeField(row)"
                                            >
                                                <option v-for="field in fields" :key="field.id" :value="field.id">
                                                    {{ field.label }}
                                                </option>
                                            </select>
                                            <select
                                                v-model="row.operator"
                                                :aria-label="$t('segments.builder.operator')"
                                                :class="fieldClass"
                                            >
                                                <option
                                                    v-for="operator in fieldOf(row)?.operators ?? []"
                                                    :key="operator"
                                                    :value="operator"
                                                >
                                                    {{ $t(`messages.field_filters.operators.${operator}`) }}
                                                </option>
                                            </select>
                                            <div
                                                v-if="row.operator === 'between'"
                                                class="grid grid-cols-2 gap-2 md:col-span-2"
                                            >
                                                <input
                                                    v-model="row.valueFrom"
                                                    type="text"
                                                    :aria-label="$t('segments.builder.value_from')"
                                                    :placeholder="$t('segments.builder.value_from')"
                                                    :class="fieldClass"
                                                />
                                                <input
                                                    v-model="row.valueTo"
                                                    type="text"
                                                    :aria-label="$t('segments.builder.value_to')"
                                                    :placeholder="$t('segments.builder.value_to')"
                                                    :class="fieldClass"
                                                />
                                            </div>
                                            <input
                                                v-else-if="!VALUELESS.includes(row.operator)"
                                                v-model="row.valuesText"
                                                type="text"
                                                :aria-label="$t('segments.builder.value')"
                                                :placeholder="$t('segments.builder.value_placeholder')"
                                                :class="[fieldClass, 'md:col-span-2']"
                                            />
                                        </template>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex min-h-10 shrink-0 items-center self-end rounded-xl px-3 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-rose-700 disabled:opacity-40 sm:self-start dark:text-slate-300 dark:hover:bg-slate-700/60 dark:hover:text-rose-300"
                                        :disabled="form.conditions.length === 1"
                                        @click="removeCondition(index)"
                                    >
                                        {{ $t("segments.builder.remove") }}
                                    </button>
                                </div>
                            </li>
                        </ol>

                        <ul v-if="conditionErrors.length" class="flex flex-col gap-1">
                            <li v-for="(message, index) in conditionErrors" :key="index" :class="errorClass">
                                {{ message }}
                            </li>
                        </ul>

                        <button
                            type="button"
                            class="self-start text-sm font-semibold text-indigo-600 hover:text-indigo-500 disabled:opacity-40 dark:text-indigo-400 dark:hover:text-indigo-300"
                            :disabled="form.conditions.length >= 20"
                            @click="addCondition"
                        >
                            + {{ $t("segments.builder.add_condition") }}
                        </button>
                    </div>
                </Panel>
            </div>

            <!-- Live count beside the conditions; under them on a phone -->
            <div class="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
                <Panel :title="$t('segments.builder.preview')">
                    <div class="flex flex-col gap-3">
                        <p
                            class="text-3xl font-semibold tabular-nums text-slate-900 dark:text-white"
                            :class="{ 'opacity-50': previewing }"
                            aria-live="polite"
                        >
                            {{ preview ? number(preview.count) : "—" }}
                        </p>
                        <p v-if="preview" :class="hintClass">
                            {{ $t("segments.builder.of_total", { total: number(preview.total) }) }}
                        </p>
                        <p v-if="previewError" :class="errorClass">{{ previewError }}</p>

                        <div v-if="preview?.sample?.length">
                            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                {{ $t("segments.builder.sample") }}
                            </p>
                            <ul class="mt-1 flex flex-col gap-0.5 text-sm text-slate-800 dark:text-slate-200">
                                <li v-for="email in preview.sample" :key="email" class="truncate" :title="email">
                                    {{ email }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </Panel>

                <Notice tone="info">{{ $t("segments.builder.dynamic_note") }}</Notice>

                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ isEditing ? $t("segments.builder.save") : $t("segments.builder.create") }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
