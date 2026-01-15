<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const form = useForm({
    name: "",
    domain: "",
    industry: "",
    size: "",
    phone: "",
    website: "",
    address: "",
    notes: "",
});

const industryOptions = [
    "IT / Technologie",
    "E-commerce",
    "Finanse / Bankowość",
    "Marketing / Reklama",
    "Produkcja",
    "Handel detaliczny",
    "Usługi profesjonalne",
    "Edukacja",
    "Zdrowie / Medycyna",
    "Nieruchomości",
    "Transport / Logistyka",
    "Inne",
];

const sizeOptions = [
    { value: "1-10", label: "1-10 pracowników" },
    { value: "11-50", label: "11-50 pracowników" },
    { value: "51-200", label: "51-200 pracowników" },
    { value: "201-500", label: "201-500 pracowników" },
    { value: "500+", label: "Ponad 500 pracowników" },
];

const submit = () => {
    form.post("/crm/companies", {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nowa firma CRM" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/crm/companies" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Nowa firma</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="space-y-6">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nazwa firmy *</label>
                        <input v-model="form.name" type="text" required
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Domain -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Domena (np. firma.pl)</label>
                            <input v-model="form.domain" type="text" placeholder="example.com"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>

                        <!-- Website -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Strona WWW</label>
                            <input v-model="form.website" type="url" placeholder="https://example.com"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Industry -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Branża</label>
                            <select v-model="form.industry"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">-- Wybierz --</option>
                                <option v-for="industry in industryOptions" :key="industry" :value="industry">
                                    {{ industry }}
                                </option>
                            </select>
                        </div>

                        <!-- Size -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Wielkość firmy</label>
                            <select v-model="form.size"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">-- Wybierz --</option>
                                <option v-for="size in sizeOptions" :key="size.value" :value="size.value">
                                    {{ size.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Telefon</label>
                        <input v-model="form.phone" type="tel"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Adres</label>
                        <textarea v-model="form.address" rows="2"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Notatki</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Dodatkowe informacje o firmie..."
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link href="/crm/companies" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        Anuluj
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Utwórz firmę
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
