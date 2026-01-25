<script setup>
import { ref, watch, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import debounce from "lodash/debounce";

const props = defineProps({
    companies: Array,
    owners: Array,
    subscriber: Object,
});

const form = useForm({
    subscriber_id: props.subscriber?.id || "",
    email: props.subscriber?.email || "",
    first_name: props.subscriber?.first_name || "",
    last_name: props.subscriber?.last_name || "",
    phone: props.subscriber?.phone || "",
    crm_company_id: "",
    owner_id: "",
    status: "lead",
    source: "manual",
    position: "",
});

// Search functionality
const searchQuery = ref("");
const searchResults = ref([]);
const isSearching = ref(false);
const showDropdown = ref(false);
const selectedSubscriber = ref(props.subscriber || null);

// Debounced search function
const performSearch = debounce(async (query) => {
    if (query.length < 2) {
        searchResults.value = [];
        showDropdown.value = false;
        return;
    }

    isSearching.value = true;
    try {
        const response = await axios.get("/crm/contacts/search-subscribers", {
            params: { q: query }
        });
        searchResults.value = response.data.subscribers || [];
        showDropdown.value = searchResults.value.length > 0;
    } catch (error) {
        console.error("Search error:", error);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}, 300);

// Watch search query
watch(searchQuery, (newQuery) => {
    if (selectedSubscriber.value) return; // Don't search if already selected
    performSearch(newQuery);
});

// Select subscriber from search results
const selectSubscriber = (subscriber) => {
    selectedSubscriber.value = subscriber;
    form.subscriber_id = subscriber.id;
    form.email = subscriber.email;
    form.first_name = subscriber.first_name || "";
    form.last_name = subscriber.last_name || "";
    form.phone = subscriber.phone || "";
    searchQuery.value = subscriber.email;
    showDropdown.value = false;
    searchResults.value = [];
};

// Clear selection and reset form
const clearSelection = () => {
    selectedSubscriber.value = null;
    form.subscriber_id = "";
    form.email = "";
    form.first_name = "";
    form.last_name = "";
    form.phone = "";
    searchQuery.value = "";
    showDropdown.value = false;
};

// Handle input blur - delay hiding dropdown to allow click
const handleBlur = () => {
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};

// Handle manual email input (for new contact)
const handleEmailInput = (e) => {
    if (!selectedSubscriber.value) {
        form.email = e.target.value;
        searchQuery.value = e.target.value;
    }
};

// Format subscriber display name
const formatSubscriberName = (subscriber) => {
    const parts = [];
    if (subscriber.first_name) parts.push(subscriber.first_name);
    if (subscriber.last_name) parts.push(subscriber.last_name);
    return parts.length > 0 ? parts.join(" ") : subscriber.email;
};

const submit = () => {
    form.post("/crm/contacts", {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('crm.contacts.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/crm/contacts" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $t('crm.contacts.create_title') }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="space-y-6">
                    <!-- Search / Email field with dropdown -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('crm.contacts.search_or_email', 'Wyszukaj lub wpisz email') }} *
                        </label>

                        <!-- Selected subscriber badge -->
                        <div v-if="selectedSubscriber" class="flex items-center gap-2 mb-2">
                            <div class="flex items-center gap-2 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 px-3 py-2 text-indigo-700 dark:text-indigo-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="font-medium">{{ formatSubscriberName(selectedSubscriber) }}</span>
                                <span class="text-sm opacity-75">({{ selectedSubscriber.email }})</span>
                                <button @click="clearSelection" type="button" class="ml-1 rounded-full p-0.5 hover:bg-indigo-200 dark:hover:bg-indigo-800">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('crm.contacts.existing_subscriber', 'Istniejący subskrybent') }}
                            </span>
                        </div>

                        <!-- Search input -->
                        <div v-if="!selectedSubscriber" class="relative">
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    @input="handleEmailInput"
                                    @focus="showDropdown = searchResults.length > 0"
                                    @blur="handleBlur"
                                    type="text"
                                    required
                                    :placeholder="$t('crm.contacts.search_placeholder', 'Wpisz email lub nazwę...')"
                                    class="mt-1 w-full rounded-xl border-slate-200 pl-10 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg v-if="!isSearching" class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <svg v-else class="h-5 w-5 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Dropdown with search results -->
                            <div v-if="showDropdown && searchResults.length > 0"
                                 class="absolute z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800 max-h-60 overflow-auto">
                                <div class="p-2 text-xs text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    {{ $t('crm.contacts.found_subscribers', 'Znalezieni subskrybenci') }} ({{ searchResults.length }})
                                </div>
                                <button
                                    v-for="subscriber in searchResults"
                                    :key="subscriber.id"
                                    @click="selectSubscriber(subscriber)"
                                    type="button"
                                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-left transition"
                                >
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-white font-semibold">
                                        {{ (subscriber.first_name?.[0] || subscriber.email?.[0] || '?').toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-slate-900 dark:text-white truncate">
                                            {{ formatSubscriberName(subscriber) }}
                                        </p>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                                            {{ subscriber.email }}
                                            <span v-if="subscriber.phone" class="ml-2">• {{ subscriber.phone }}</span>
                                        </p>
                                    </div>
                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Hint text -->
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $t('crm.contacts.search_hint', 'Wpisz min. 2 znaki aby wyszukać istniejącego subskrybenta lub wprowadź nowy email') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.phone') }}</label>
                            <input v-model="form.phone" type="tel"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                        <div></div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.first_name') }}</label>
                            <input v-model="form.first_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.last_name') }}</label>
                            <input v-model="form.last_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.status') }}</label>
                            <select v-model="form.status"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="lead">{{ $t('crm.contacts.status.lead') }}</option>
                                <option value="prospect">{{ $t('crm.contacts.status.prospect') }}</option>
                                <option value="client">{{ $t('crm.contacts.status.client') }}</option>
                                <option value="dormant">{{ $t('crm.contacts.status.dormant') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.source') }}</label>
                            <input v-model="form.source" type="text" :placeholder="$t('crm.contacts.placeholders.source')"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.company') }}</label>
                            <select v-model="form.crm_company_id"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('common.none') }}</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.position') }}</label>
                            <input v-model="form.position" type="text" :placeholder="$t('crm.contacts.placeholders.position')"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div v-if="owners?.length > 1">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.owner') }}</label>
                        <select v-model="form.owner_id"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="">{{ $t('common.select_option') }}</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                {{ owner.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link href="/crm/contacts" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        {{ $t('common.cancel') }}
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ $t('crm.contacts.create_button') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
