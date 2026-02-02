<script setup>
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    subscriberIds: {
        type: Array,
        required: true,
    },
    type: {
        type: String,
        default: "opens", // 'opens' or 'clicks'
    },
});

const emit = defineEmits(["close", "success"]);

const lists = ref([]);
const searchQuery = ref("");
const loading = ref(false);
const adding = ref(false);
const selectedListId = ref(null);
const successMessage = ref("");
const errorMessage = ref("");

// Fetch lists
const fetchLists = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route("mailing-lists.index"), {
            headers: { Accept: "application/json" },
        });
        lists.value = response.data.allLists || [];
    } catch (error) {
        console.error("Error fetching lists:", error);
        lists.value = [];
    } finally {
        loading.value = false;
    }
};

// Filtered lists based on search
const filteredLists = computed(() => {
    if (!searchQuery.value.trim()) return lists.value;
    const query = searchQuery.value.toLowerCase();
    return lists.value.filter((list) =>
        list.name.toLowerCase().includes(query),
    );
});

// Unique subscriber count
const uniqueCount = computed(() => {
    return [...new Set(props.subscriberIds)].length;
});

// Add to list
const addToList = async () => {
    if (!selectedListId.value || adding.value) return;

    adding.value = true;
    successMessage.value = "";
    errorMessage.value = "";

    try {
        // Use unique IDs only
        const uniqueIds = [...new Set(props.subscriberIds)];

        await axios.post(route("subscribers.bulk-add-to-list"), {
            ids: uniqueIds,
            target_list_id: selectedListId.value,
        });

        const selectedList = lists.value.find(
            (l) => l.id === selectedListId.value,
        );
        successMessage.value = selectedList?.name || "";

        emit("success", {
            listId: selectedListId.value,
            count: uniqueIds.length,
        });

        // Auto-close after success
        setTimeout(() => {
            closeModal();
        }, 1500);
    } catch (error) {
        console.error("Error adding to list:", error);
        errorMessage.value = error.response?.data?.message || "Wystąpił błąd";
    } finally {
        adding.value = false;
    }
};

const closeModal = () => {
    searchQuery.value = "";
    selectedListId.value = null;
    successMessage.value = "";
    errorMessage.value = "";
    emit("close");
};

// Fetch lists when modal opens
watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            fetchLists();
        }
    },
);
</script>

<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-4">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    {{ $t("messages.stats.bulk_add_title") }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        $t("messages.stats.bulk_add_description", {
                            count: uniqueCount,
                        })
                    }}
                </p>
            </div>

            <!-- Success Message -->
            <div
                v-if="successMessage"
                class="mb-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400"
            >
                <svg
                    class="h-5 w-5 flex-shrink-0"
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
                <span>{{
                    $t("messages.stats.bulk_add_success", {
                        list: successMessage,
                    })
                }}</span>
            </div>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                class="mb-4 flex items-center gap-2 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
            >
                <svg
                    class="h-5 w-5 flex-shrink-0"
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

            <!-- Search Input -->
            <div class="mb-4">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="$t('messages.stats.search_lists')"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-8">
                <svg
                    class="h-6 w-6 animate-spin text-indigo-500"
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
            <div
                v-else
                class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <div
                    v-if="filteredLists.length === 0"
                    class="py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    {{
                        searchQuery
                            ? $t("common.no_results")
                            : $t("messages.stats.no_lists")
                    }}
                </div>
                <div
                    v-for="list in filteredLists"
                    :key="list.id"
                    @click="selectedListId = list.id"
                    class="flex cursor-pointer items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                    :class="
                        selectedListId === list.id
                            ? 'bg-indigo-50 dark:bg-indigo-900/20'
                            : ''
                    "
                >
                    <span
                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                        >{{ list.name }}</span
                    >
                    <svg
                        v-if="selectedListId === list.id"
                        class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
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
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="closeModal"
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    {{ $t("common.cancel") }}
                </button>
                <button
                    @click="addToList"
                    :disabled="!selectedListId || adding"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="adding"
                        class="mr-2 h-4 w-4 animate-spin"
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
                    {{
                        adding
                            ? $t("common.adding")
                            : $t("messages.stats.bulk_add_button")
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>
