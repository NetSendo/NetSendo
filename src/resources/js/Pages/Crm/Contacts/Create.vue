<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

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

const submit = () => {
    form.post("/crm/contacts", {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nowy kontakt CRM" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/crm/contacts" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Nowy kontakt</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email *</label>
                            <input v-model="form.email" type="email" required :disabled="!!subscriber"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white disabled:bg-slate-50" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Telefon</label>
                            <input v-model="form.phone" type="tel"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Imię</label>
                            <input v-model="form.first_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nazwisko</label>
                            <input v-model="form.last_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                            <select v-model="form.status"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="lead">Lead</option>
                                <option value="prospect">Prospect</option>
                                <option value="client">Klient</option>
                                <option value="dormant">Uśpiony</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Źródło</label>
                            <input v-model="form.source" type="text" placeholder="np. LinkedIn, Konferencja..."
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Firma</label>
                            <select v-model="form.crm_company_id"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">-- Brak --</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Stanowisko</label>
                            <input v-model="form.position" type="text" placeholder="np. CEO, Marketing Manager..."
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div v-if="owners?.length > 1">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Handlowiec</label>
                        <select v-model="form.owner_id"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="">-- Wybierz --</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                {{ owner.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link href="/crm/contacts" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        Anuluj
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Utwórz kontakt
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
