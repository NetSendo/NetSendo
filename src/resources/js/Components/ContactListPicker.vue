<script setup>
import { ref, computed, nextTick } from "vue";

const props = defineProps({
    // Array of ids when multiple, a single id (or "") when not.
    modelValue: {
        type: [Array, Number, String],
        default: () => [],
    },
    lists: {
        type: Array,
        default: () => [],
    },
    error: {
        type: String,
        default: "",
    },
    multiple: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(["update:modelValue"]);

const searchQuery = ref("");
const listTypeFilter = ref("all"); // 'all', 'email', 'sms'
const searchInput = ref(null);

// Normalized view of the selection so the rest of the component does not care
// whether it runs in single or multiple mode.
const selectedIds = computed(() => {
    if (props.multiple) {
        return Array.isArray(props.modelValue) ? props.modelValue : [];
    }
    return props.modelValue === "" ||
        props.modelValue === null ||
        props.modelValue === undefined
        ? []
        : [props.modelValue];
});

// Strip diacritics so "wielkanoc" also matches "Wielkanoc/Boże Narodzenie"
const normalize = (value) =>
    String(value ?? "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/ł/g, "l");

const typeMatchedLists = computed(() => {
    if (listTypeFilter.value === "all") return props.lists;
    return props.lists.filter((list) => list.type === listTypeFilter.value);
});

// Search matches the list name or its ID — every whitespace separated term
// has to match, so "hydraulik plan" finds "Hydraulik Zapis Klienci Plan".
const filteredLists = computed(() => {
    const terms = normalize(searchQuery.value.replace(/#/g, ""))
        .split(/\s+/)
        .filter(Boolean);

    if (terms.length === 0) return typeMatchedLists.value;

    const matches = typeMatchedLists.value.filter((list) => {
        const haystack = `${list.id} ${normalize(list.name)}`;
        return terms.every((term) => haystack.includes(term));
    });

    // Searching "205" also matches list #1205 — put the exact ID hit on top.
    const exactId =
        terms.length === 1 && /^\d+$/.test(terms[0]) ? Number(terms[0]) : null;
    if (exactId === null) return matches;

    return [...matches].sort((a, b) => (b.id === exactId) - (a.id === exactId));
});

const selectedLists = computed(() =>
    props.lists.filter((list) => selectedIds.value.includes(list.id)),
);

const typeCounts = computed(() => ({
    all: props.lists.length,
    email: props.lists.filter((list) => list.type === "email").length,
    sms: props.lists.filter((list) => list.type === "sms").length,
}));

const allVisibleSelected = computed(
    () =>
        filteredLists.value.length > 0 &&
        filteredLists.value.every((list) =>
            selectedIds.value.includes(list.id),
        ),
);

const isSelected = (id) => selectedIds.value.includes(id);

const toggle = (id) => {
    if (!props.multiple) {
        emit("update:modelValue", id);
        return;
    }
    if (isSelected(id)) {
        emit(
            "update:modelValue",
            selectedIds.value.filter((selectedId) => selectedId !== id),
        );
        return;
    }
    emit("update:modelValue", [...selectedIds.value, id]);
};

const selectVisible = () => {
    const visibleIds = filteredLists.value.map((list) => list.id);
    emit("update:modelValue", [
        ...new Set([...selectedIds.value, ...visibleIds]),
    ]);
};

const deselectVisible = () => {
    const visibleIds = new Set(filteredLists.value.map((list) => list.id));
    emit(
        "update:modelValue",
        selectedIds.value.filter((id) => !visibleIds.has(id)),
    );
};

const clearSelection = () =>
    emit("update:modelValue", props.multiple ? [] : "");

const clearSearch = async () => {
    searchQuery.value = "";
    await nextTick();
    searchInput.value?.focus();
};
</script>

<template>
    <div>
        <!-- List Type Filter Buttons -->
        <div class="mb-3 flex flex-wrap gap-2">
            <button
                type="button"
                @click="listTypeFilter = 'all'"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    listTypeFilter === 'all'
                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                ]"
            >
                {{ $t("common.all") }} ({{ typeCounts.all }})
            </button>
            <button
                type="button"
                @click="listTypeFilter = 'email'"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    listTypeFilter === 'email'
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                ]"
            >
                📧 Email ({{ typeCounts.email }})
            </button>
            <button
                type="button"
                @click="listTypeFilter = 'sms'"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    listTypeFilter === 'sms'
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                ]"
            >
                📱 SMS ({{ typeCounts.sms }})
            </button>
        </div>

        <!-- Search by name or ID -->
        <div class="relative mb-2">
            <svg
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"
                />
            </svg>
            <input
                ref="searchInput"
                v-model="searchQuery"
                type="search"
                :placeholder="$t('subscribers.search_list_placeholder')"
                class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-9 pr-9 text-sm placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                @keydown.esc.prevent="clearSearch"
            />
            <button
                v-if="searchQuery"
                type="button"
                @click="clearSearch"
                class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                :aria-label="$t('common.clear')"
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

        <!-- Counters + bulk actions -->
        <div
            class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400"
        >
            <span>
                {{
                    $t("subscribers.lists_visible_count", {
                        shown: filteredLists.length,
                        total: lists.length,
                    })
                }}
                <template v-if="multiple">
                    ·
                    {{
                        $t("subscribers.lists_selected_count", {
                            count: selectedIds.length,
                        })
                    }}
                </template>
            </span>
            <span class="flex items-center gap-3">
                <button
                    v-if="multiple && filteredLists.length > 0"
                    type="button"
                    @click="
                        allVisibleSelected ? deselectVisible() : selectVisible()
                    "
                    class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                >
                    {{
                        allVisibleSelected
                            ? $t("subscribers.deselect_visible_lists")
                            : $t("subscribers.select_visible_lists")
                    }}
                </button>
                <button
                    v-if="multiple && selectedIds.length > 0"
                    type="button"
                    @click="clearSelection"
                    class="font-medium text-slate-500 hover:underline dark:text-slate-400"
                >
                    {{ $t("common.clear") }}
                </button>
            </span>
        </div>

        <!-- Selected lists as removable chips (stay visible while filtering) -->
        <div
            v-if="selectedLists.length > 0"
            class="mb-2 flex flex-wrap gap-1.5"
        >
            <span
                v-for="list in selectedLists"
                :key="`chip-${list.id}`"
                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 py-1 pl-2 pr-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
            >
                <span class="opacity-60">#{{ list.id }}</span>
                {{ list.name }}
                <button
                    v-if="multiple"
                    type="button"
                    @click="toggle(list.id)"
                    class="flex h-4 w-4 items-center justify-center rounded transition-colors hover:bg-indigo-200 dark:hover:bg-indigo-800"
                    :aria-label="$t('common.remove')"
                >
                    <svg
                        class="h-3 w-3"
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
            </span>
        </div>

        <!-- Scrollable, searchable list -->
        <div
            class="max-h-72 overflow-y-auto rounded-xl border bg-slate-50 dark:bg-slate-800"
            :class="
                error
                    ? 'border-red-400 dark:border-red-500'
                    : 'border-slate-200 dark:border-slate-700'
            "
        >
            <p
                v-if="filteredLists.length === 0"
                class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400"
            >
                {{ $t("subscribers.no_lists_found") }}
            </p>
            <label
                v-for="list in filteredLists"
                :key="list.id"
                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-3 py-2 text-sm transition-colors last:border-b-0 hover:bg-white dark:border-slate-700/60 dark:hover:bg-slate-700/40"
                :class="
                    isSelected(list.id)
                        ? 'bg-indigo-50/70 dark:bg-indigo-900/20'
                        : ''
                "
            >
                <input
                    :type="multiple ? 'checkbox' : 'radio'"
                    :name="multiple ? undefined : 'contact-list-choice'"
                    :checked="isSelected(list.id)"
                    @change="toggle(list.id)"
                    :class="[
                        'border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-slate-600 dark:bg-slate-900',
                        multiple ? 'rounded' : 'rounded-full',
                    ]"
                />
                <span
                    class="w-14 shrink-0 font-mono text-xs text-slate-400 dark:text-slate-500"
                    >#{{ list.id }}</span
                >
                <span class="flex-1 truncate text-slate-900 dark:text-white">{{
                    list.name
                }}</span>
                <span
                    :class="[
                        'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase',
                        list.type === 'sms'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                    ]"
                    >{{ list.type === "sms" ? "SMS" : "Email" }}</span
                >
            </label>
        </div>

        <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>
