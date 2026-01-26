<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    companies: Object,
    industries: Array,
    filters: Object,
});

const search = ref(props.filters?.search || "");

const applySearch = () => {
    router.get(
        "/crm/companies",
        { search: search.value || undefined },
        { preserveState: true },
    );
};

// Delete modal state
const showDeleteModal = ref(false);
const companyToDelete = ref(null);
const deleteWithContacts = ref(false);
const isDeleting = ref(false);

const openDeleteModal = (company, event) => {
    event.preventDefault();
    event.stopPropagation();
    companyToDelete.value = company;
    deleteWithContacts.value = false;
    showDeleteModal.value = true;
};

const deleteCompany = () => {
    if (!companyToDelete.value) return;
    isDeleting.value = true;
    router.delete(`/crm/companies/${companyToDelete.value.id}`, {
        data: { delete_contacts: deleteWithContacts.value },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
            companyToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head :title="$t('crm.companies.title_page', 'Firmy CRM')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $t("crm.companies.title", "Firmy") }}
                </h1>
                <Link
                    href="/crm/companies/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ $t("crm.companies.add_company", "Dodaj firmę") }}
                </Link>
            </div>
        </template>

        <!-- Search -->
        <div class="mb-6">
            <div class="relative max-w-md">
                <svg
                    class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    v-model="search"
                    @keyup.enter="applySearch"
                    type="text"
                    :placeholder="
                        $t('crm.companies.search_placeholder', 'Szukaj firm...')
                    "
                    class="w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>
        </div>

        <!-- Companies Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="company in companies?.data"
                :key="company.id"
                class="relative rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-md dark:bg-slate-800"
            >
                <Link
                    :href="`/crm/companies/${company.id}`"
                    class="absolute inset-0 z-0"
                />
                <div class="relative z-10 pointer-events-none">
                    <div class="flex items-start justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600"
                        >
                            <svg
                                class="h-6 w-6 text-slate-600 dark:text-slate-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                />
                            </svg>
                        </div>
                        <button
                            @click="openDeleteModal(company, $event)"
                            class="pointer-events-auto rounded-lg p-2 text-slate-400 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
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
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                    <h3
                        class="mt-4 font-semibold text-slate-900 dark:text-white"
                    >
                        {{ company.name }}
                    </h3>
                    <p
                        v-if="company.industry"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ company.industry }}
                    </p>
                    <div
                        class="mt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400"
                    >
                        <span class="flex items-center gap-1">
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
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            {{ company.contacts_count || 0 }}
                        </span>
                        <span class="flex items-center gap-1">
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
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z"
                                />
                            </svg>
                            {{ company.deals_count || 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="!companies?.data?.length"
            class="rounded-2xl bg-white py-16 text-center shadow-sm dark:bg-slate-800"
        >
            <svg
                class="mx-auto h-12 w-12 text-slate-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                />
            </svg>
            <p class="mt-4 text-slate-500">
                {{ $t("crm.companies.no_companies", "Brak firm") }}
            </p>
            <Link
                href="/crm/companies/create"
                class="mt-4 inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700"
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
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                {{ $t("crm.companies.add_first", "Dodaj pierwszą firmę") }}
            </Link>
        </div>

        <!-- Delete Company Modal -->
        <Modal
            :show="showDeleteModal"
            @close="showDeleteModal = false"
            max-width="md"
        >
            <div class="p-6">
                <div class="flex items-start gap-4 mb-4">
                    <div
                        class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/50"
                    >
                        <svg
                            class="h-6 w-6 text-red-600 dark:text-red-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-white mb-2"
                        >
                            Usuń firmę "{{ companyToDelete?.name }}"
                        </h3>
                        <p
                            class="text-sm text-slate-600 dark:text-slate-400 mb-4"
                        >
                            Co chcesz zrobić z powiązanymi kontaktami?
                        </p>

                        <div class="space-y-3">
                            <label
                                class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                                :class="
                                    !deleteWithContacts
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                        : 'border-slate-200 dark:border-slate-700'
                                "
                            >
                                <input
                                    type="radio"
                                    v-model="deleteWithContacts"
                                    :value="false"
                                    class="mt-0.5"
                                />
                                <div>
                                    <p
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        Zachowaj kontakty
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Kontakty zostaną odłączone od firmy.
                                    </p>
                                </div>
                            </label>
                            <label
                                class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                                :class="
                                    deleteWithContacts
                                        ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                        : 'border-slate-200 dark:border-slate-700'
                                "
                            >
                                <input
                                    type="radio"
                                    v-model="deleteWithContacts"
                                    :value="true"
                                    class="mt-0.5"
                                />
                                <div>
                                    <p
                                        class="font-medium text-red-700 dark:text-red-300"
                                    >
                                        Usuń również kontakty
                                    </p>
                                    <p
                                        v-if="companyToDelete?.contacts_count"
                                        class="text-sm text-red-600 dark:text-red-400"
                                    >
                                        {{
                                            companyToDelete.contacts_count
                                        }}
                                        kontaktów zostanie usuniętych.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        :disabled="isDeleting"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50"
                    >
                        Anuluj
                    </button>
                    <button
                        type="button"
                        @click="deleteCompany"
                        :disabled="isDeleting"
                        class="rounded-xl px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-500 text-white disabled:opacity-50"
                    >
                        <span v-if="isDeleting">Usuwanie...</span>
                        <span v-else>Usuń firmę</span>
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
