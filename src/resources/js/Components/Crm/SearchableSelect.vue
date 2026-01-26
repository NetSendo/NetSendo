<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";

const props = defineProps({
    modelValue: [String, Number],
    searchUrl: { type: String, required: true },
    placeholder: { type: String, default: "Wyszukaj..." },
    initialOptions: { type: Array, default: () => [] },
    displayKey: { type: String, default: "name" },
    valueKey: { type: String, default: "id" },
    filterParams: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "selected"]);

const searchQuery = ref("");
const options = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const selectedOption = ref(null);
const containerRef = ref(null);

let debounceTimer = null;

// Find initial selected option
onMounted(() => {
    if (props.modelValue && props.initialOptions.length) {
        selectedOption.value = props.initialOptions.find(
            (opt) => opt[props.valueKey] === props.modelValue
        );
    }
});

// Watch for external value changes
watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            selectedOption.value = null;
            searchQuery.value = "";
        } else if (props.initialOptions.length) {
            const found = props.initialOptions.find(
                (opt) => opt[props.valueKey] === newVal
            );
            if (found) selectedOption.value = found;
        }
    }
);

// Search with debounce
const handleSearch = (event) => {
    searchQuery.value = event.target.value;

    if (debounceTimer) clearTimeout(debounceTimer);

    debounceTimer = setTimeout(() => {
        performSearch();
    }, 300);
};

const performSearch = async () => {
    if (searchQuery.value.length < 1) {
        options.value = props.initialOptions.slice(0, 20);
        return;
    }

    isLoading.value = true;
    try {
        const params = new URLSearchParams({
            q: searchQuery.value,
            ...props.filterParams,
        });

        const response = await fetch(`${props.searchUrl}?${params}`);
        const data = await response.json();
        options.value = data;
    } catch (error) {
        console.error("Search failed:", error);
        options.value = [];
    } finally {
        isLoading.value = false;
    }
};

const openDropdown = () => {
    if (props.disabled) return;
    isOpen.value = true;
    options.value = props.initialOptions.slice(0, 20);
};

const selectOption = (option) => {
    selectedOption.value = option;
    emit("update:modelValue", option[props.valueKey]);
    emit("selected", option);
    isOpen.value = false;
    searchQuery.value = "";
};

const clearSelection = () => {
    selectedOption.value = null;
    emit("update:modelValue", null);
    emit("selected", null);
    searchQuery.value = "";
};

const getDisplayValue = (option) => {
    if (!option) return "";
    if (typeof props.displayKey === "function") {
        return props.displayKey(option);
    }
    return option[props.displayKey] || "";
};

// Close on outside click
const handleClickOutside = (event) => {
    if (containerRef.value && !containerRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div ref="containerRef" class="relative">
        <!-- Selected value display / Input -->
        <div
            v-if="selectedOption && !isOpen"
            @click="openDropdown"
            class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-2.5 dark:border-slate-700 dark:bg-slate-900"
            :class="{ 'opacity-50 cursor-not-allowed': disabled }"
        >
            <span class="text-slate-900 dark:text-white">
                {{ getDisplayValue(selectedOption) }}
            </span>
            <button
                v-if="!disabled"
                @click.stop="clearSelection"
                class="ml-2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Search input -->
        <div v-else class="relative">
            <input
                type="text"
                :value="searchQuery"
                @input="handleSearch"
                @focus="openDropdown"
                :placeholder="placeholder"
                :disabled="disabled"
                class="w-full rounded-xl border-slate-200 pr-10 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            />
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <svg v-if="isLoading" class="h-5 w-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <div v-if="isLoading" class="px-4 py-3 text-center text-sm text-slate-500">
                    Ładowanie...
                </div>
                <div v-else-if="options.length === 0" class="px-4 py-3 text-center text-sm text-slate-500">
                    {{ searchQuery ? 'Brak wyników' : 'Wpisz aby wyszukać' }}
                </div>
                <ul v-else class="py-1">
                    <li
                        v-for="option in options"
                        :key="option[valueKey]"
                        @click="selectOption(option)"
                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 dark:text-slate-200 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                    >
                        <slot name="option" :option="option">
                            {{ getDisplayValue(option) }}
                            <span v-if="option.email" class="ml-2 text-xs text-slate-400">
                                {{ option.email }}
                            </span>
                        </slot>
                    </li>
                </ul>
            </div>
        </Transition>
    </div>
</template>
