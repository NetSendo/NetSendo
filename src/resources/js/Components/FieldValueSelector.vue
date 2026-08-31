<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from "vue";
import { debounce } from "lodash";
import axios from "axios";

/**
 * Picks one or more values of a subscriber custom field.
 *
 * Suggestions are the values subscribers actually hold (most common first, with
 * counts) restricted to the lists in play, so "city" offers Oświęcim before it
 * offers a typo nobody has. Anything can still be typed in by hand — the field
 * may be empty today and filled by tomorrow's import.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    fieldId: { type: [Number, String], default: null },
    // Declared choices of a select/radio field, offered even with zero subscribers
    fieldOptions: { type: Array, default: () => [] },
    listIds: { type: Array, default: () => [] },
    placeholder: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
    accent: { type: String, default: "indigo" }, // indigo | red
});

const emit = defineEmits(["update:modelValue"]);

const search = ref("");
const remoteValues = ref([]);
const isLoading = ref(false);
const isOpen = ref(false);
const inputRef = ref(null);
const rootRef = ref(null);

const selected = computed(() =>
    Array.isArray(props.modelValue) ? props.modelValue : [],
);

const normalize = (value) =>
    String(value ?? "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/ł/g, "l");

// Declared options first (they exist even with no subscribers yet), then the
// values found on subscribers, deduplicated and filtered by what is typed.
const suggestions = computed(() => {
    const seen = new Map();

    props.fieldOptions.forEach((option) => {
        const value = typeof option === "object" ? (option.value ?? option.label) : option;
        if (value === undefined || value === null || value === "") return;
        seen.set(String(value), { value: String(value), count: null });
    });

    remoteValues.value.forEach((row) => {
        seen.set(String(row.value), { value: String(row.value), count: row.count });
    });

    const term = normalize(search.value);
    const rows = [...seen.values()];

    return term
        ? rows.filter((row) => normalize(row.value).includes(term))
        : rows;
});

// Offer to add exactly what was typed when it is not already on the list
const manualValue = computed(() => {
    const typed = search.value.trim();
    if (!typed) return null;
    const exists = suggestions.value.some(
        (row) => row.value.toLowerCase() === typed.toLowerCase(),
    );
    return exists ? null : typed;
});

const fetchValues = async () => {
    if (!props.fieldId) {
        remoteValues.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(route("messages.audience.field-values"), {
            params: {
                field_id: props.fieldId,
                list_ids: props.listIds,
                search: search.value || undefined,
                limit: 50,
            },
        });
        remoteValues.value = response.data.values ?? [];
    } catch (error) {
        remoteValues.value = [];
    } finally {
        isLoading.value = false;
    }
};

const debouncedFetch = debounce(fetchValues, 250);

watch(
    () => [props.fieldId, props.listIds],
    () => {
        remoteValues.value = [];
        if (isOpen.value) fetchValues();
    },
    { deep: true },
);

watch(search, () => {
    if (isOpen.value) debouncedFetch();
});

const open = () => {
    if (props.disabled) return;
    isOpen.value = true;
    if (remoteValues.value.length === 0) fetchValues();
};

const close = () => {
    isOpen.value = false;
    search.value = "";
};

const toggle = (value) => {
    const next = selected.value.includes(value)
        ? selected.value.filter((item) => item !== value)
        : [...selected.value, value];
    emit("update:modelValue", next);
};

const addManual = () => {
    const typed = search.value.trim();
    if (!typed) return;
    if (!selected.value.includes(typed)) {
        emit("update:modelValue", [...selected.value, typed]);
    }
    search.value = "";
    nextTick(() => inputRef.value?.focus());
};

const remove = (value) => {
    emit(
        "update:modelValue",
        selected.value.filter((item) => item !== value),
    );
};

const onEnter = () => {
    if (manualValue.value) {
        addManual();
        return;
    }
    const first = suggestions.value[0];
    if (first) {
        toggle(first.value);
        search.value = "";
    }
};

// Close when the click lands outside the component
const onDocumentClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) close();
};

watch(isOpen, (open) => {
    if (open) {
        document.addEventListener("mousedown", onDocumentClick);
    } else {
        document.removeEventListener("mousedown", onDocumentClick);
    }
});

// A row removed while its dropdown is open must not leave the listener behind
onUnmounted(() => document.removeEventListener("mousedown", onDocumentClick));

const chipClass = computed(() =>
    props.accent === "red"
        ? "bg-red-50 text-red-700 ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800"
        : "bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-800",
);
</script>

<template>
    <div ref="rootRef" class="relative">
        <!-- Selected values -->
        <div v-if="selected.length > 0" class="mb-1.5 flex flex-wrap gap-1">
            <span
                v-for="value in selected"
                :key="value"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                :class="chipClass"
            >
                {{ value }}
                <button
                    type="button"
                    :disabled="disabled"
                    class="opacity-60 hover:opacity-100"
                    @click="remove(value)"
                >
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>

        <input
            ref="inputRef"
            v-model="search"
            type="text"
            :disabled="disabled"
            :placeholder="placeholder || $t('messages.field_filters.value_placeholder')"
            class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
            @focus="open"
            @keydown.enter.prevent="onEnter"
            @keydown.esc="close"
        />

        <!-- Suggestions -->
        <div
            v-if="isOpen"
            class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
        >
            <button
                v-if="manualValue"
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="addManual"
            >
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t("messages.field_filters.add_value", { value: manualValue }) }}
            </button>

            <div v-if="isLoading" class="px-3 py-2 text-sm text-slate-400">
                {{ $t("messages.field_filters.loading_values") }}
            </div>

            <button
                v-for="row in suggestions"
                :key="row.value"
                type="button"
                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-700"
                :class="
                    selected.includes(row.value)
                        ? 'font-medium text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-700 dark:text-slate-200'
                "
                @click="toggle(row.value)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <svg
                        v-if="selected.includes(row.value)"
                        class="h-4 w-4 shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="h-4 w-4 shrink-0" v-else />
                    <span class="truncate">{{ row.value }}</span>
                </span>
                <span
                    v-if="row.count !== null"
                    class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                >
                    {{ row.count }}
                </span>
            </button>

            <div
                v-if="!isLoading && suggestions.length === 0 && !manualValue"
                class="px-3 py-2 text-sm text-slate-400"
            >
                {{ $t("messages.field_filters.no_values") }}
            </div>
        </div>
    </div>
</template>
