<script setup>
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    show: Boolean,
    contact: Object,
    companies: {
        type: Array,
        default: () => [],
    },
    owners: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["close", "saved"]);

const form = useForm({
    crm_company_id: "",
    owner_id: "",
    status: "lead",
    source: "",
    position: "",
    score: 0,
    first_name: "",
    last_name: "",
    phone: "",
});

// Watch for contact changes and populate form
watch(
    () => props.contact,
    (contact) => {
        if (contact) {
            form.crm_company_id = contact.crm_company_id || "";
            form.owner_id = contact.owner_id || "";
            form.status = contact.status || "lead";
            form.source = contact.source || "";
            form.position = contact.position || "";
            form.score = contact.score || 0;
            form.first_name = contact.subscriber?.first_name || "";
            form.last_name = contact.subscriber?.last_name || "";
            form.phone = contact.subscriber?.phone || "";
        }
    },
    { immediate: true }
);

const close = () => {
    emit("close");
};

const submit = () => {
    form.patch(`/crm/contacts/${props.contact.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit("saved");
            close();
        },
    });
};

// Status options
const statusOptions = [
    { value: "lead", label: "crm.contacts.status.lead" },
    { value: "prospect", label: "crm.contacts.status.prospect" },
    { value: "client", label: "crm.contacts.status.client" },
    { value: "dormant", label: "crm.contacts.status.dormant" },
    { value: "archived", label: "crm.contacts.status.archived" },
];
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
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 backdrop-blur-sm p-4"
                @click.self="close"
            >
                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <div
                        v-if="show"
                        class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800"
                    >
                        <!-- Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{ $t("crm.contacts.edit_title", "Edytuj kontakt") }}
                            </h2>
                            <button
                                @click="close"
                                class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            >
                                <svg
                                    class="h-5 w-5"
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

                        <!-- Form -->
                        <form @submit.prevent="submit" class="space-y-4">
                            <!-- Name fields -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.first_name", "Imię") }}
                                    </label>
                                    <input
                                        v-model="form.first_name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.last_name", "Nazwisko") }}
                                    </label>
                                    <input
                                        v-model="form.last_name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ $t("crm.contacts.fields.phone", "Telefon") }}
                                </label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <!-- Status and Score -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.status", "Status") }}
                                    </label>
                                    <select
                                        v-model="form.status"
                                        class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ $t(option.label) }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.score", "Score") }}:
                                        {{ form.score }}
                                    </label>
                                    <input
                                        v-model.number="form.score"
                                        type="range"
                                        min="0"
                                        max="100"
                                        class="mt-2 w-full accent-indigo-600"
                                    />
                                </div>
                            </div>

                            <!-- Position and Source -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.position", "Stanowisko") }}
                                    </label>
                                    <input
                                        v-model="form.position"
                                        type="text"
                                        :placeholder="$t('crm.contacts.placeholders.position', 'np. CEO')"
                                        class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        {{ $t("crm.contacts.fields.source", "Źródło") }}
                                    </label>
                                    <input
                                        v-model="form.source"
                                        type="text"
                                        :placeholder="$t('crm.contacts.placeholders.source', 'np. Webinar')"
                                        class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Company -->
                            <div v-if="companies?.length > 0">
                                <label
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ $t("crm.contacts.fields.company", "Firma") }}
                                </label>
                                <select
                                    v-model="form.crm_company_id"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="">
                                        {{ $t("common.none", "Brak") }}
                                    </option>
                                    <option
                                        v-for="company in companies"
                                        :key="company.id"
                                        :value="company.id"
                                    >
                                        {{ company.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Owner -->
                            <div v-if="owners?.length > 1">
                                <label
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{ $t("crm.contacts.fields.owner", "Handlowiec") }}
                                </label>
                                <select
                                    v-model="form.owner_id"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="">
                                        {{ $t("common.select_option", "Wybierz...") }}
                                    </option>
                                    <option
                                        v-for="owner in owners"
                                        :key="owner.id"
                                        :value="owner.id"
                                    >
                                        {{ owner.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Error message -->
                            <div
                                v-if="Object.keys(form.errors).length"
                                class="rounded-xl bg-red-50 p-4 dark:bg-red-900/20"
                            >
                                <p
                                    v-for="(error, key) in form.errors"
                                    :key="key"
                                    class="text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ error }}
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    @click="close"
                                    class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                                >
                                    {{ $t("common.cancel", "Anuluj") }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ $t("common.save", "Zapisz") }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
