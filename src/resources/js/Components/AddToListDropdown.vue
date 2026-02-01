<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";

const props = defineProps({
    subscriberId: {
        type: Number,
        required: true,
    },
    subscriberEmail: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["added"]);

const isOpen = ref(false);
const lists = ref([]);
const searchQuery = ref("");
const loading = ref(false);
const addingToList = ref(null);
const successMessage = ref("");
const errorMessage = ref("");

// Fetch lists on mount
const fetchLists = async () => {
    loading.value = true;
    try {
        // Fetch from mailing-lists index (returns allLists)
        const response = await axios.get(route("mailing-lists.index"), {
            headers: { Accept: "application/json" },
        });
        // The response contains 'allLists' array with id and name
        lists.value = response.data.allLists || [];
    } catch (error) {
        console.error("Error fetching lists:", error);
        lists.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchLists();
});

// Filtered lists based on search
const filteredLists = computed(() => {
    if (!searchQuery.value.trim()) return lists.value;
    const query = searchQuery.value.toLowerCase();
    return lists.value.filter((list) =>
        list.name.toLowerCase().includes(query),
    );
});

// Toggle dropdown
const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = "";
        successMessage.value = "";
        errorMessage.value = "";
    }
};

// Close dropdown when clicking outside
const closeOnClickOutside = (e) => {
    if (!e.target.closest(".add-to-list-dropdown")) {
        isOpen.value = false;
    }
};

const closeOnEscape = (e) => {
    if (e.key === "Escape") {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", closeOnClickOutside);
    document.addEventListener("keydown", closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener("click", closeOnClickOutside);
    document.removeEventListener("keydown", closeOnEscape);
});

// Add subscriber to list
const addToList = async (listId, listName) => {
    addingToList.value = listId;
    successMessage.value = "";
    errorMessage.value = "";

    try {
        await axios.post(route("subscribers.bulk-add-to-list"), {
            ids: [props.subscriberId],
            target_list_id: listId,
        });

        successMessage.value = listName;
        emit("added", { listId, listName, subscriberId: props.subscriberId });

        // Auto-close after success
        setTimeout(() => {
            isOpen.value = false;
            successMessage.value = "";
        }, 1500);
    } catch (error) {
        console.error("Error adding to list:", error);
        errorMessage.value = error.response?.data?.message || "Wystąpił błąd";
    } finally {
        addingToList.value = null;
    }
};
</script>

<template>
    <div class="add-to-list-dropdown relative inline-block">
        <!-- Trigger Button -->
        <button
            @click.stop="toggleDropdown"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/20 transition-colors"
            :title="$t('messages.stats.add_to_list')"
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
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                />
            </svg>
            <span class="hidden sm:inline">{{
                $t("messages.stats.add_to_list")
            }}</span>
        </button>

        <!-- Dropdown Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-slate-800 dark:ring-slate-700"
            >
                <div class="p-3">
                    <!-- Header -->
                    <div
                        class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        {{ $t("messages.stats.select_list") }}
                    </div>

                    <!-- Search Input -->
                    <div class="mb-2">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="$t('messages.stats.search_lists')"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-500"
                            @click.stop
                        />
                    </div>

                    <!-- Success Message -->
                    <div
                        v-if="successMessage"
                        class="mb-2 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400"
                    >
                        <svg
                            class="h-4 w-4 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        <span
                            >{{ $t("messages.stats.added_to_list") }}:
                            {{ successMessage }}</span
                        >
                    </div>

                    <!-- Error Message -->
                    <div
                        v-if="errorMessage"
                        class="mb-2 flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                    >
                        <svg
                            class="h-4 w-4 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span>{{ errorMessage }}</span>
                    </div>

                    <!-- Loading State -->
                    <div
                        v-if="loading"
                        class="flex items-center justify-center py-4"
                    >
                        <svg
                            class="h-5 w-5 animate-spin text-indigo-500"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </div>

                    <!-- Lists -->
                    <div v-else class="max-h-48 overflow-y-auto">
                        <div
                            v-if="filteredLists.length === 0"
                            class="py-3 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                searchQuery
                                    ? $t("common.no_results")
                                    : $t("messages.stats.no_lists")
                            }}
                        </div>
                        <button
                            v-for="list in filteredLists"
                            :key="list.id"
                            @click.stop="addToList(list.id, list.name)"
                            :disabled="addingToList === list.id"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            <span class="truncate">{{ list.name }}</span>
                            <svg
                                v-if="addingToList === list.id"
                                class="h-4 w-4 animate-spin text-indigo-500"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <svg
                                v-else
                                class="h-4 w-4 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
