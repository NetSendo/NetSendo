<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { countries } from "@/data/countries.js";
import axios from "axios";

const form = useForm({
    name: "",
    country: "",
    nip: "",
    regon: "",
    domain: "",
    industry: "",
    size: "",
    phone: "",
    website: "",
    address: "",
    notes: "",
});

const isLookingUp = ref(false);
const lookupError = ref("");
const lookupSuccess = ref("");

const isPoland = computed(() => form.country === "PL");

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

const lookupCompany = async () => {
    if (!form.nip && !form.regon) {
        lookupError.value = "Wprowadź NIP lub REGON";
        return;
    }

    isLookingUp.value = true;
    lookupError.value = "";
    lookupSuccess.value = "";

    try {
        const params = {};
        if (form.nip) params.nip = form.nip;
        else if (form.regon) params.regon = form.regon;

        const response = await axios.get("/crm/companies/lookup", { params });

        if (response.data.success && response.data.data) {
            const data = response.data.data;

            if (data.name) form.name = data.name;
            if (data.address) form.address = data.address;
            if (data.phone) form.phone = data.phone;
            if (data.website) form.website = data.website;
            if (data.industry) form.industry = data.industry;
            if (data.nip && !form.nip) form.nip = data.nip;
            if (data.regon && !form.regon) form.regon = data.regon;

            lookupSuccess.value = "Dane pobrane pomyślnie";
        }
    } catch (error) {
        if (error.response?.status === 404) {
            lookupError.value = "Nie znaleziono firmy o podanym NIP/REGON";
        } else if (error.response?.data?.message) {
            lookupError.value = error.response.data.message;
        } else {
            lookupError.value = "Wystąpił błąd podczas pobierania danych";
        }
    } finally {
        isLookingUp.value = false;
    }
};

const submit = () => {
    form.post("/crm/companies", {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('crm.companies.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/crm/companies" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $t('crm.companies.create_title') }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="space-y-6">
                    <!-- Country -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.country') }}</label>
                        <select v-model="form.country"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="">{{ $t('common.select_option') }}</option>
                            <option v-for="country in countries" :key="country.code" :value="country.code">
                                {{ country.flag }} {{ country.name }}
                            </option>
                        </select>
                    </div>

                    <!-- NIP & REGON (only for Poland) -->
                    <div v-if="isPoland" class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20">
                        <div class="mb-3 flex items-center gap-2">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ $t('crm.companies.polish_company_lookup') }}</span>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.nip') }}</label>
                                <input v-model="form.nip" type="text" maxlength="10" placeholder="0000000000"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                                <p v-if="form.errors.nip" class="mt-1 text-sm text-red-600">{{ form.errors.nip }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.regon') }}</label>
                                <input v-model="form.regon" type="text" maxlength="14" placeholder="000000000"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                                <p v-if="form.errors.regon" class="mt-1 text-sm text-red-600">{{ form.errors.regon }}</p>
                            </div>
                        </div>

                        <button type="button" @click="lookupCompany" :disabled="isLookingUp || (!form.nip && !form.regon)"
                            class="mt-3 flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <svg v-if="isLookingUp" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ $t('crm.companies.fetch_data') }}
                        </button>

                        <p v-if="lookupError" class="mt-2 text-sm text-red-600">{{ lookupError }}</p>
                        <p v-if="lookupSuccess" class="mt-2 text-sm text-green-600">{{ lookupSuccess }}</p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.name_required') }}</label>
                        <input v-model="form.name" type="text" required
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Domain -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.domain') }}</label>
                            <input v-model="form.domain" type="text" placeholder="example.com"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>

                        <!-- Website -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.website') }}</label>
                            <input v-model="form.website" type="url" placeholder="https://example.com"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <!-- Industry -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.industry') }}</label>
                            <select v-model="form.industry"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('common.select_option') }}</option>
                                <option v-for="industry in industryOptions" :key="industry" :value="industry">
                                    {{ industry }}
                                </option>
                            </select>
                        </div>

                        <!-- Size -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.size') }}</label>
                            <select v-model="form.size"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('common.select_option') }}</option>
                                <option v-for="size in sizeOptions" :key="size.value" :value="size.value">
                                    {{ size.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.phone') }}</label>
                        <input v-model="form.phone" type="tel"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.address') }}</label>
                        <textarea v-model="form.address" rows="2"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.companies.fields.notes') }}</label>
                        <textarea v-model="form.notes" rows="3" :placeholder="$t('crm.companies.placeholders.notes')"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link href="/crm/companies" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        {{ $t('common.cancel') }}
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ $t('crm.companies.create_button') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
