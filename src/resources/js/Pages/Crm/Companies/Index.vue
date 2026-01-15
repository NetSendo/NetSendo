<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";

const props = defineProps({
    companies: Object,
    industries: Array,
    filters: Object,
});

const search = ref(props.filters?.search || "");

const applySearch = () => {
    router.get("/crm/companies", { search: search.value || undefined }, { preserveState: true });
};
</script>

<template>
    <Head title="Firmy CRM" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Firmy</h1>
                <Link href="/crm/companies/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Dodaj firmę
                </Link>
            </div>
        </template>

        <!-- Search -->
        <div class="mb-6">
            <div class="relative max-w-md">
                <svg class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input v-model="search" @keyup.enter="applySearch" type="text" placeholder="Szukaj firm..."
                    class="w-full rounded-xl border-slate-200 bg-white py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
            </div>
        </div>

        <!-- Companies Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="company in companies?.data" :key="company.id" :href="`/crm/companies/${company.id}`"
                class="rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-md dark:bg-slate-800">
                <div class="flex items-start justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600">
                        <svg class="h-6 w-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>
                <h3 class="mt-4 font-semibold text-slate-900 dark:text-white">{{ company.name }}</h3>
                <p v-if="company.industry" class="text-sm text-slate-500 dark:text-slate-400">{{ company.industry }}</p>
                <div class="mt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ company.contacts_count || 0 }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z" />
                        </svg>
                        {{ company.deals_count || 0 }}
                    </span>
                </div>
            </Link>
        </div>

        <!-- Empty State -->
        <div v-if="!companies?.data?.length" class="rounded-2xl bg-white py-16 text-center shadow-sm dark:bg-slate-800">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <p class="mt-4 text-slate-500">Brak firm</p>
            <Link href="/crm/companies/create" class="mt-4 inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Dodaj pierwszą firmę
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
