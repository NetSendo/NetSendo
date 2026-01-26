<script setup>
import { ref, computed, watch } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import SearchableSelect from "./SearchableSelect.vue";

const props = defineProps({
    show: Boolean,
    deal: Object,
    stages: Array,
    contacts: Array,
    companies: Array,
    owners: Array,
    currencies: Object,
    defaultCurrency: String,
});

const emit = defineEmits(["close", "updated", "deleted"]);

const isEditing = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const activeTab = ref("details");

const form = useForm({
    name: "",
    crm_stage_id: "",
    crm_contact_id: "",
    crm_company_id: "",
    owner_id: "",
    value: "",
    currency: "",
    expected_close_date: "",
    notes: "",
});

// Initialize form when deal changes
watch(
    () => props.deal,
    (deal) => {
        if (deal) {
            form.name = deal.name || "";
            form.crm_stage_id = deal.crm_stage_id || "";
            form.crm_contact_id = deal.crm_contact_id || "";
            form.crm_company_id = deal.crm_company_id || "";
            form.owner_id = deal.owner_id || "";
            form.value = deal.value || "";
            form.currency = deal.currency || props.defaultCurrency || "EUR";
            form.expected_close_date = deal.expected_close_date || "";
            form.notes = deal.notes || "";
            isEditing.value = false;
        }
    },
    { immediate: true }
);

// Auto-load company when contact is selected
const onContactSelected = (contact) => {
    if (contact && contact.crm_company_id && !form.crm_company_id) {
        form.crm_company_id = contact.crm_company_id;
    }
};

// Filter params for contacts based on selected company
const contactFilterParams = computed(() => {
    if (form.crm_company_id) {
        return { company_id: form.crm_company_id };
    }
    return {};
});

const close = () => {
    isEditing.value = false;
    emit("close");
};

const startEdit = () => {
    isEditing.value = true;
};

const cancelEdit = () => {
    if (props.deal) {
        form.name = props.deal.name || "";
        form.crm_stage_id = props.deal.crm_stage_id || "";
        form.crm_contact_id = props.deal.crm_contact_id || "";
        form.crm_company_id = props.deal.crm_company_id || "";
        form.owner_id = props.deal.owner_id || "";
        form.value = props.deal.value || "";
        form.currency = props.deal.currency || props.defaultCurrency || "EUR";
        form.expected_close_date = props.deal.expected_close_date || "";
        form.notes = props.deal.notes || "";
    }
    isEditing.value = false;
};

const saveDeal = () => {
    form.put(`/crm/deals/${props.deal.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
            emit("updated");
        },
    });
};

const confirmDelete = () => {
    showDeleteConfirm.value = true;
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
};

const deleteDeal = () => {
    isDeleting.value = true;
    router.delete(`/crm/deals/${props.deal.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            emit("deleted");
            close();
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const formatCurrency = (value, currency = null) => {
    const currencyCode = currency || props.defaultCurrency || "EUR";
    return new Intl.NumberFormat("pl-PL", {
        style: "currency",
        currency: currencyCode,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value || 0);
};

const formatDate = (date) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("pl-PL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    });
};

const getStageColor = (stage) => {
    return stage?.color || "#6b7280";
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show && deal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="close"
            >
                <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-3 w-3 rounded-full"
                                :style="{ backgroundColor: getStageColor(deal.stage) }"
                            ></div>
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                                    {{ deal.name }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{ deal.stage?.name || "Brak etapu" }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="!isEditing"
                                @click="startEdit"
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                @click="close"
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="max-h-[70vh] overflow-y-auto p-6">
                        <!-- View Mode -->
                        <div v-if="!isEditing" class="space-y-6">
                            <!-- Value -->
                            <div class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 p-4 text-center text-white">
                                <p class="text-sm opacity-90">Wartość</p>
                                <p class="text-3xl font-bold">
                                    {{ formatCurrency(deal.value, deal.currency) }}
                                </p>
                            </div>

                            <!-- Details Grid -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Kontakt</p>
                                    <p class="mt-1 text-slate-900 dark:text-white">
                                        {{ deal.contact?.subscriber?.first_name }} {{ deal.contact?.subscriber?.last_name }}
                                        <span v-if="!deal.contact" class="text-slate-400">-</span>
                                    </p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Firma</p>
                                    <p class="mt-1 text-slate-900 dark:text-white">
                                        {{ deal.company?.name || "-" }}
                                    </p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Właściciel</p>
                                    <p class="mt-1 text-slate-900 dark:text-white">
                                        {{ deal.owner?.name || "-" }}
                                    </p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Przewidywane zamknięcie</p>
                                    <p class="mt-1 text-slate-900 dark:text-white">
                                        {{ formatDate(deal.expected_close_date) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div v-if="deal.notes" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Notatki</p>
                                <p class="mt-2 whitespace-pre-wrap text-slate-900 dark:text-white">
                                    {{ deal.notes }}
                                </p>
                            </div>

                            <!-- Delete button -->
                            <div class="flex justify-end pt-4">
                                <button
                                    @click="confirmDelete"
                                    :disabled="isDeleting"
                                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Usuń deal
                                </button>
                            </div>
                        </div>

                        <!-- Edit Mode -->
                        <form v-else @submit.prevent="saveDeal" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Nazwa deala *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Etap
                                    </label>
                                    <select
                                        v-model="form.crm_stage_id"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option v-for="stage in stages" :key="stage.id" :value="stage.id">
                                            {{ stage.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Wartość
                                    </label>
                                    <div class="mt-1 flex gap-2">
                                        <input
                                            v-model="form.value"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        />
                                        <select
                                            v-model="form.currency"
                                            class="w-24 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option v-for="(name, code) in currencies" :key="code" :value="code">
                                                {{ code }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Kontakt
                                    </label>
                                    <SearchableSelect
                                        v-model="form.crm_contact_id"
                                        search-url="/crm/contacts/search"
                                        placeholder="Wyszukaj kontakt..."
                                        :initial-options="contacts"
                                        :filter-params="contactFilterParams"
                                        @selected="onContactSelected"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Firma
                                    </label>
                                    <SearchableSelect
                                        v-model="form.crm_company_id"
                                        search-url="/crm/companies/search"
                                        placeholder="Wyszukaj firmę..."
                                        :initial-options="companies"
                                        class="mt-1"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Właściciel
                                    </label>
                                    <select
                                        v-model="form.owner_id"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="">-- Brak --</option>
                                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                            {{ owner.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Przewidywane zamknięcie
                                    </label>
                                    <input
                                        v-model="form.expected_close_date"
                                        type="date"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Notatki
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                ></textarea>
                            </div>

                            <div class="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    @click="cancelEdit"
                                    class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    Anuluj
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Zapisz zmiany
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Delete Confirmation Modal -->
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4"
                @click.self="cancelDelete"
            >
                <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                    <div class="p-6">
                        <!-- Warning Icon -->
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                            <svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <!-- Title -->
                        <h3 class="mt-4 text-center text-lg font-bold text-slate-900 dark:text-white">
                            Usuń szansę sprzedaży
                        </h3>

                        <!-- Description -->
                        <p class="mt-2 text-center text-slate-600 dark:text-slate-400">
                            Czy na pewno chcesz usunąć szansę sprzedaży <span class="font-semibold text-slate-900 dark:text-white">"{{ deal?.name }}"</span>?
                            Ta operacja jest nieodwracalna.
                        </p>

                        <!-- Buttons -->
                        <div class="mt-6 flex gap-3">
                            <button
                                @click="cancelDelete"
                                class="flex-1 rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                            >
                                Anuluj
                            </button>
                            <button
                                @click="deleteDeal"
                                :disabled="isDeleting"
                                class="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                            >
                                <span v-if="isDeleting" class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Usuwanie...
                                </span>
                                <span v-else>Tak, usuń</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
