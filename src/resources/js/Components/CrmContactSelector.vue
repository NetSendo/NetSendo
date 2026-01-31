<script setup>
import { ref, computed, watch } from "vue";
import { debounce } from "lodash";
import axios from "axios";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    preloadedContacts: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: "Kontakty CRM",
    },
    helpText: {
        type: String,
        default: "",
    },
    placeholder: {
        type: String,
        default: "Szukaj kontaktów CRM...",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

// State
const searchQuery = ref("");
const searchResults = ref([]);
const isLoading = ref(false);
const showDropdown = ref(false);
const selectedContacts = ref([]);

// Initialize from preloadedContacts
watch(
    () => props.preloadedContacts,
    (newVal) => {
        if (newVal && newVal.length > 0) {
            selectedContacts.value = [...newVal];
        }
    },
    { immediate: true },
);

// Computed
const selectedIds = computed(() => selectedContacts.value.map((c) => c.id));

// Search function with debounce
const performSearch = debounce(async (query) => {
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get("/messages/search-crm-contacts", {
            params: {
                q: query,
                exclude: selectedIds.value,
            },
        });
        searchResults.value = response.data.contacts || [];
    } catch (error) {
        console.error("Error searching CRM contacts:", error);
        searchResults.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

// Watch for search input
watch(searchQuery, (newQuery) => {
    if (newQuery.length >= 2) {
        showDropdown.value = true;
        performSearch(newQuery);
    } else {
        showDropdown.value = false;
        searchResults.value = [];
    }
});

// Add contact
const addContact = (contact) => {
    if (!selectedIds.value.includes(contact.id)) {
        selectedContacts.value.push(contact);
        emit("update:modelValue", selectedIds.value);
    }
    searchQuery.value = "";
    showDropdown.value = false;
    searchResults.value = [];
};

// Remove contact
const removeContact = (contactId) => {
    selectedContacts.value = selectedContacts.value.filter(
        (c) => c.id !== contactId,
    );
    emit("update:modelValue", selectedIds.value);
};

// Get status badge class
const getStatusClass = (status) => {
    const classes = {
        lead: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300",
        customer:
            "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300",
        prospect:
            "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300",
        cold: "bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300",
    };
    return classes[status] || classes.cold;
};

// Get display name
const getDisplayName = (contact) => {
    return contact.full_name || contact.email;
};

// Close dropdown on outside click
const handleOutsideClick = () => {
    showDropdown.value = false;
};
</script>

<template>
    <div class="relative" v-click-outside="handleOutsideClick">
        <!-- Label -->
        <div
            v-if="label"
            class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300"
        >
            {{ label }}
        </div>

        <!-- Help text -->
        <p
            v-if="helpText"
            class="mb-2 text-xs text-slate-500 dark:text-slate-400"
        >
            {{ helpText }}
        </p>

        <!-- Search input -->
        <div class="relative">
            <input
                v-model="searchQuery"
                type="text"
                :placeholder="placeholder"
                :disabled="disabled"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 pr-10 text-sm placeholder-slate-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:placeholder-slate-500"
                @focus="searchQuery.length >= 2 && (showDropdown = true)"
            />
            <div
                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
            >
                <svg
                    v-if="isLoading"
                    class="h-4 w-4 animate-spin text-slate-400"
                    xmlns="http://www.w3.org/2000/svg"
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
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                        clip-rule="evenodd"
                    />
                </svg>
            </div>
        </div>

        <!-- Search results dropdown -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="
                    showDropdown &&
                    (searchResults.length > 0 ||
                        isLoading ||
                        searchQuery.length >= 2)
                "
                class="absolute z-50 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <ul
                    v-if="searchResults.length > 0"
                    class="max-h-60 overflow-y-auto py-1"
                >
                    <li
                        v-for="contact in searchResults"
                        :key="contact.id"
                        @click="addContact(contact)"
                        class="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300"
                            >
                                {{
                                    (
                                        contact.first_name?.[0] ||
                                        contact.email[0]
                                    ).toUpperCase()
                                }}
                            </div>
                            <div>
                                <div
                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ getDisplayName(contact) }}
                                </div>
                                <div
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ contact.email }}
                                    <span v-if="contact.company" class="ml-1"
                                        >• {{ contact.company }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <span
                            v-if="contact.status"
                            :class="getStatusClass(contact.status)"
                            class="rounded px-2 py-0.5 text-xs font-medium"
                        >
                            {{ contact.status }}
                        </span>
                    </li>
                </ul>
                <div
                    v-else-if="isLoading"
                    class="px-4 py-3 text-center text-sm text-slate-500"
                >
                    Szukam...
                </div>
                <div
                    v-else-if="searchQuery.length >= 2"
                    class="px-4 py-3 text-center text-sm text-slate-500"
                >
                    Nie znaleziono kontaktów
                </div>
            </div>
        </Transition>

        <!-- Selected contacts -->
        <div
            v-if="selectedContacts.length > 0"
            class="mt-3 flex flex-wrap gap-2"
        >
            <div
                v-for="contact in selectedContacts"
                :key="contact.id"
                class="flex items-center gap-2 rounded-full bg-primary-100 py-1 pl-3 pr-1 text-sm text-primary-800 dark:bg-slate-700 dark:text-slate-100 dark:border dark:border-slate-600"
            >
                <span>{{ getDisplayName(contact) }}</span>
                <button
                    type="button"
                    @click="removeContact(contact.id)"
                    :disabled="disabled"
                    class="rounded-full p-1 hover:bg-primary-200 dark:hover:bg-slate-600"
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
            </div>
        </div>

        <!-- Selected count -->
        <div
            v-if="selectedContacts.length > 0"
            class="mt-2 text-xs text-slate-500 dark:text-slate-400"
        >
            {{
                $t("messages.fields.selected_crm_contacts", {
                    count: selectedContacts.length,
                })
            }}
        </div>
    </div>
</template>
