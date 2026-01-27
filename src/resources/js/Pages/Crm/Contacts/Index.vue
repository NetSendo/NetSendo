<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import EditContactModal from "@/Components/Crm/EditContactModal.vue";

const props = defineProps({
    contacts: Object,
    companies: Array,
    owners: Array,
    tags: Array,
    filters: Object,
});

const search = ref(props.filters?.search || "");
const selectedStatus = ref(props.filters?.status || "");
const selectedOwner = ref(props.filters?.owner_id || "");
const selectedCompany = ref(props.filters?.company_id || "");

// Status options
const statusOptions = [
    { value: "", label: "crm.contacts.all_statuses" },
    { value: "lead", label: "crm.contacts.status.lead" },
    { value: "prospect", label: "crm.contacts.status.prospect" },
    { value: "client", label: "crm.contacts.status.client" },
    { value: "dormant", label: "crm.contacts.status.dormant" },
    { value: "archived", label: "crm.contacts.status.archived" },
];

// Apply filters
const applyFilters = () => {
    router.get(
        "/crm/contacts",
        {
            search: search.value || undefined,
            status: selectedStatus.value || undefined,
            owner_id: selectedOwner.value || undefined,
            company_id: selectedCompany.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Get status badge class
const getStatusClass = (status) => {
    const classes = {
        lead: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
        prospect:
            "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300",
        client: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300",
        dormant:
            "bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300",
        archived:
            "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
    };
    return classes[status] || classes.lead;
};

// Get status label
const getStatusLabel = (status) => {
    const labels = {
        lead: "crm.contacts.status.lead",
        prospect: "crm.contacts.status.prospect",
        client: "crm.contacts.status.client",
        dormant: "crm.contacts.status.dormant",
        archived: "crm.contacts.status.archived",
    };
    return labels[status] || status;
};

// Delete modal state
const showDeleteModal = ref(false);
const contactToDelete = ref(null);
const isDeleting = ref(false);

// Edit modal state
const showEditModal = ref(false);
const contactToEdit = ref(null);

const openEditModal = (contact) => {
    contactToEdit.value = contact;
    showEditModal.value = true;
};

const onContactEdited = () => {
    router.reload({ only: ["contacts"] });
};

const openDeleteModal = (contact) => {
    contactToDelete.value = contact;
    showDeleteModal.value = true;
};

const deleteContact = () => {
    if (!contactToDelete.value) return;
    isDeleting.value = true;
    router.delete(`/crm/contacts/${contactToDelete.value.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
            contactToDelete.value = null;
        },
    });
};

const getContactName = (contact) => {
    if (contact?.subscriber?.first_name || contact?.subscriber?.last_name) {
        return `${contact.subscriber.first_name || ""} ${contact.subscriber.last_name || ""}`.trim();
    }
    return contact?.subscriber?.email || "kontakt";
};
</script>

<template>
    <Head :title="$t('crm.contacts.title', 'Kontakty CRM')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $t("crm.contacts.title", "Kontakty") }}
                </h1>
                <Link
                    href="/crm/contacts/create"
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
                    {{ $t("crm.contacts.create_button") }}
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-6 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800">
            <div class="flex flex-wrap items-center gap-4">
                <!-- Search -->
                <div class="relative flex-1 min-w-[200px]">
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
                        @keyup.enter="applyFilters"
                        type="text"
                        :placeholder="
                            $t(
                                'crm.contacts.search_placeholder',
                                'Szukaj po email...',
                            )
                        "
                        class="w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-4 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>

                <!-- Status filter -->
                <select
                    v-model="selectedStatus"
                    @change="applyFilters"
                    class="rounded-xl border-slate-200 bg-white py-2 pl-3 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ $t(option.label) }}
                    </option>
                </select>

                <!-- Owner filter -->
                <select
                    v-if="owners?.length > 1"
                    v-model="selectedOwner"
                    @change="applyFilters"
                    class="rounded-xl border-slate-200 bg-white py-2 pl-3 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                >
                    <option value="">
                        {{ $t("crm.contacts.all_owners", "Wszyscy handlowcy") }}
                    </option>
                    <option
                        v-for="owner in owners"
                        :key="owner.id"
                        :value="owner.id"
                    >
                        {{ owner.name }}
                    </option>
                </select>

                <!-- Company filter -->
                <select
                    v-if="companies?.length > 0"
                    v-model="selectedCompany"
                    @change="applyFilters"
                    class="rounded-xl border-slate-200 bg-white py-2 pl-3 pr-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                >
                    <option value="">
                        {{
                            $t("crm.contacts.all_companies", "Wszystkie firmy")
                        }}
                    </option>
                    <option
                        v-for="company in companies"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.name }}
                    </option>
                </select>

                <button
                    @click="applyFilters"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
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
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                        />
                    </svg>
                    {{ $t("common.filter", "Filtruj") }}
                </button>
            </div>
        </div>

        <!-- Contacts Table -->
        <div
            class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-800"
        >
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead
                        class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50"
                    >
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t("crm.contacts.table_contact", "Kontakt")
                                }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("crm.contacts.table_company", "Firma") }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Status
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Score
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t(
                                        "crm.contacts.fields.owner",
                                        "Handlowiec",
                                    )
                                }}
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("crm.contacts.table_actions", "Akcje") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <tr
                            v-for="contact in contacts?.data"
                            :key="contact.id"
                            class="transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <Link
                                    :href="`/crm/contacts/${contact.id}`"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-white font-semibold"
                                    >
                                        {{
                                            (
                                                contact.subscriber
                                                    ?.first_name?.[0] ||
                                                contact.subscriber
                                                    ?.email?.[0] ||
                                                "?"
                                            ).toUpperCase()
                                        }}
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-slate-900 dark:text-white"
                                        >
                                            {{
                                                contact.subscriber
                                                    ?.first_name || ""
                                            }}
                                            {{
                                                contact.subscriber?.last_name ||
                                                ""
                                            }}
                                            <span
                                                v-if="
                                                    !contact.subscriber
                                                        ?.first_name &&
                                                    !contact.subscriber
                                                        ?.last_name
                                                "
                                                class="text-slate-500"
                                            >
                                                {{ contact.subscriber?.email }}
                                            </span>
                                        </p>
                                        <p
                                            class="text-sm text-slate-500 dark:text-slate-400"
                                        >
                                            {{ contact.subscriber?.email }}
                                        </p>
                                    </div>
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    v-if="contact.company"
                                    class="text-sm text-slate-900 dark:text-white"
                                >
                                    {{ contact.company.name }}
                                </span>
                                <span v-else class="text-sm text-slate-400"
                                    >—</span
                                >
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        getStatusClass(contact.status),
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                    ]"
                                >
                                    {{ $t(getStatusLabel(contact.status)) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                                    >
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-500"
                                            :style="{
                                                width: `${Math.min(contact.score, 100)}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <span
                                        class="text-sm text-slate-600 dark:text-slate-400"
                                        >{{ contact.score }}</span
                                    >
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    v-if="contact.owner"
                                    class="text-sm text-slate-900 dark:text-white"
                                >
                                    {{ contact.owner.name }}
                                </span>
                                <span v-else class="text-sm text-slate-400"
                                    >—</span
                                >
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <button
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                                        :title="
                                            $t(
                                                'crm.contacts.actions.call',
                                                'Zadzwoń',
                                            )
                                        "
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
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                                        :title="
                                            $t(
                                                'crm.contacts.actions.email',
                                                'Email',
                                            )
                                        "
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
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="openEditModal(contact)"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                                        :title="
                                            $t(
                                                'crm.contacts.actions.edit',
                                                'Edytuj',
                                            )
                                        "
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
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                            />
                                        </svg>
                                    </button>
                                    <Link
                                        :href="`/crm/contacts/${contact.id}`"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                                        :title="
                                            $t(
                                                'crm.contacts.actions.details',
                                                'Szczegóły',
                                            )
                                        "
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
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="openDeleteModal(contact)"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        :title="
                                            $t(
                                                'crm.contacts.actions.delete',
                                                'Usuń',
                                            )
                                        "
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="!contacts?.data?.length" class="py-16 text-center">
                <svg
                    class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                </svg>
                <p class="mt-4 text-slate-500 dark:text-slate-400">
                    {{ $t("crm.contacts.empty_title", "Brak kontaktów") }}
                </p>
                <Link
                    href="/crm/contacts/create"
                    class="mt-4 inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
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
                    {{
                        $t(
                            "crm.contacts.create_first",
                            "Dodaj pierwszy kontakt",
                        )
                    }}
                </Link>
            </div>

            <!-- Pagination -->
            <div
                v-if="contacts?.links && contacts.data?.length"
                class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 dark:border-slate-700 dark:bg-slate-900/50"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{
                        $t(
                            "crm.contacts.pagination",
                            {
                                from: contacts.from,
                                to: contacts.to,
                                total: contacts.total,
                            },
                            "Wyświetlanie {from}-{to} z {total} kontaktów",
                        )
                    }}
                </p>
                <div class="flex items-center gap-2">
                    <Link
                        v-for="link in contacts.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-lg px-3 py-1 text-sm transition',
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-600 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Delete Contact Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            :title="`Usuń kontakt: ${getContactName(contactToDelete)}`"
            message="Czy na pewno chcesz usunąć ten kontakt z CRM? Subskrybent (email) pozostanie w systemie marketingowym."
            confirm-text="Usuń kontakt"
            type="danger"
            :processing="isDeleting"
            @close="showDeleteModal = false"
            @confirm="deleteContact"
        />

        <!-- Edit Contact Modal -->
        <EditContactModal
            :show="showEditModal"
            :contact="contactToEdit"
            :companies="companies"
            :owners="owners"
            @close="showEditModal = false"
            @saved="onContactEdited"
        />
    </AuthenticatedLayout>
</template>
