<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";

const { formatDate: formatDateBase, locale } = useDateTime();
const props = defineProps({
    company: Object,
    activities: Array,
});

// Activity form
const activityForm = useForm({
    content: "",
});

const addNote = () => {
    if (!activityForm.content.trim()) return;

    activityForm.post(`/crm/companies/${props.company.id}/note`, {
        preserveScroll: true,
        onSuccess: () => {
            activityForm.reset();
        },
    });
};

// Format date
const formatDate = (date) => {
    if (!date) return "-";
    return formatDateBase(date, null, {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'PLN' }).format(value || 0);
};
</script>

<template>
    <Head :title="company.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/crm/companies" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600">
                            <svg class="h-6 w-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ company.name }}</h1>
                            <p v-if="company.industry" class="text-slate-500 dark:text-slate-400">{{ company.industry }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="`/crm/companies/${company.id}/edit`"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edytuj
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column: Company Info -->
            <div class="space-y-6">
                <!-- Company Details -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Dane firmy</h2>
                    <dl class="space-y-4">
                        <div v-if="company.domain">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Domena</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ company.domain }}</dd>
                        </div>
                        <div v-if="company.website">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Strona WWW</dt>
                            <dd class="mt-1">
                                <a :href="company.website" target="_blank" class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                    {{ company.website }}
                                </a>
                            </dd>
                        </div>
                        <div v-if="company.phone">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Telefon</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ company.phone }}</dd>
                        </div>
                        <div v-if="company.size">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Wielkość</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ company.size }}</dd>
                        </div>
                        <div v-if="company.address">
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Adres</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white whitespace-pre-line">{{ company.address }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Stats -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Statystyki</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ company.contacts?.length || 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Kontakty</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ company.deals?.length || 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Deale</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Add Note -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Dodaj notatkę</h2>
                    <div class="space-y-4">
                        <textarea v-model="activityForm.content" rows="3" placeholder="Dodaj notatkę o firmie..."
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                        <button @click="addNote" :disabled="activityForm.processing || !activityForm.content.trim()"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Dodaj
                        </button>
                    </div>
                </div>

                <!-- Contacts -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Kontakty</h2>
                        <Link href="/crm/contacts/create" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                            Dodaj kontakt →
                        </Link>
                    </div>
                    <div v-if="company.contacts?.length" class="space-y-3">
                        <Link v-for="contact in company.contacts" :key="contact.id" :href="`/crm/contacts/${contact.id}`"
                            class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-700">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-white font-semibold">
                                {{ (contact.subscriber?.first_name?.[0] || contact.subscriber?.email?.[0] || '?').toUpperCase() }}
                            </div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">
                                    {{ contact.subscriber?.first_name || '' }} {{ contact.subscriber?.last_name || '' }}
                                    <span v-if="!contact.subscriber?.first_name && !contact.subscriber?.last_name">
                                        {{ contact.subscriber?.email }}
                                    </span>
                                </p>
                                <p v-if="contact.position" class="text-sm text-slate-500 dark:text-slate-400">{{ contact.position }}</p>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400">
                        Brak kontaktów w tej firmie
                    </div>
                </div>

                <!-- Deals -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Deale</h2>
                        <Link href="/crm/deals" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                            Zobacz wszystkie →
                        </Link>
                    </div>
                    <div v-if="company.deals?.length" class="space-y-3">
                        <div v-for="deal in company.deals" :key="deal.id"
                            class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ deal.name }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ deal.stage?.name }}</p>
                            </div>
                            <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ formatCurrency(deal.value) }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400">
                        Brak deali
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Historia aktywności</h2>
                    <div v-if="activities?.length" class="space-y-4">
                        <div v-for="activity in activities" :key="activity.id" class="flex gap-4">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                                <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ activity.type === 'note' ? 'Notatka' : activity.type }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ formatDate(activity.created_at) }}
                                    </span>
                                </div>
                                <p v-if="activity.content" class="mt-2 text-slate-700 dark:text-slate-300">
                                    {{ activity.content }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    przez {{ activity.created_by?.name || 'System' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400">
                        Brak historii aktywności
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="company.notes" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Notatki</h2>
                    <p class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ company.notes }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
