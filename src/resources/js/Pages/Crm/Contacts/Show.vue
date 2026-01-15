<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    contact: Object,
    activities: Array,
    campaignEvents: Object,
    owners: Array,
    companies: Array,
});

// Activity form
const activityForm = useForm({
    type: "note",
    content: "",
});

const addActivity = () => {
    if (!activityForm.content.trim()) return;

    activityForm.post(`/crm/contacts/${props.contact.id}/activity`, {
        preserveScroll: true,
        onSuccess: () => {
            activityForm.reset();
        },
    });
};

// Activity type options
const activityTypes = [
    { value: "note", label: "Notatka", icon: "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" },
    { value: "call", label: "Rozmowa", icon: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" },
    { value: "email", label: "Email", icon: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" },
    { value: "meeting", label: "Spotkanie", icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" },
];

// Get status badge class
const getStatusClass = (status) => {
    const classes = {
        lead: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
        prospect: "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300",
        client: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300",
        dormant: "bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300",
        archived: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
    };
    return classes[status] || classes.lead;
};

// Format date
const formatDate = (date) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("pl-PL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Get activity icon
const getActivityIcon = (type) => {
    const found = activityTypes.find(t => t.value === type);
    return found?.icon || activityTypes[0].icon;
};
</script>

<template>
    <Head :title="`${contact.subscriber?.first_name || ''} ${contact.subscriber?.last_name || contact.subscriber?.email || 'Kontakt'}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/crm/contacts" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 text-xl font-semibold text-white">
                            {{ (contact.subscriber?.first_name?.[0] || contact.subscriber?.email?.[0] || '?').toUpperCase() }}
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                                {{ contact.subscriber?.first_name || '' }} {{ contact.subscriber?.last_name || '' }}
                                <span v-if="!contact.subscriber?.first_name && !contact.subscriber?.last_name">
                                    {{ contact.subscriber?.email }}
                                </span>
                            </h1>
                            <p class="text-slate-500 dark:text-slate-400">
                                {{ contact.position || contact.subscriber?.email }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Zadzwoń
                    </button>
                    <button class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Wyślij email
                    </button>
                </div>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Left Column: Contact Info -->
            <div class="space-y-6">
                <!-- Contact Details -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Dane kontaktu</h2>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Email</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ contact.subscriber?.email || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Telefon</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ contact.subscriber?.phone || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Status</dt>
                            <dd class="mt-1">
                                <span :class="[getStatusClass(contact.status), 'rounded-full px-3 py-1 text-sm font-medium']">
                                    {{ contact.status }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Score</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-500"
                                        :style="{ width: `${Math.min(contact.score, 100)}%` }"></div>
                                </div>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ contact.score }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Źródło</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ contact.source || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Handlowiec</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">{{ contact.owner?.name || 'Nieprzypisany' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Company -->
                <div v-if="contact.company" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Firma</h2>
                    <Link :href="`/crm/companies/${contact.company.id}`" class="block rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-700">
                        <p class="font-medium text-slate-900 dark:text-white">{{ contact.company.name }}</p>
                        <p v-if="contact.company.industry" class="text-sm text-slate-500 dark:text-slate-400">{{ contact.company.industry }}</p>
                    </Link>
                </div>

                <!-- Tags -->
                <div v-if="contact.subscriber?.tags?.length" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Tagi</h2>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="tag in contact.subscriber.tags" :key="tag.id"
                            class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                            {{ tag.name }}
                        </span>
                    </div>
                </div>

                <!-- Marketing Data -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Dane marketingowe</h2>

                    <!-- Email Lists -->
                    <div v-if="campaignEvents?.emailLists?.length" class="mb-4">
                        <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Listy email</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="list in campaignEvents.emailLists" :key="list.id"
                                class="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                {{ list.name }}
                            </span>
                        </div>
                    </div>

                    <!-- SMS Lists -->
                    <div v-if="campaignEvents?.smsLists?.length" class="mb-4">
                        <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Listy SMS</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="list in campaignEvents.smsLists" :key="list.id"
                                class="rounded-full bg-green-100 px-3 py-1 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                {{ list.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Email Stats -->
                    <div class="grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50">
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ campaignEvents?.stats?.emails_opened || 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Otwarcia</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ campaignEvents?.stats?.emails_clicked || 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Kliknięcia</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Timeline -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Quick Add Activity -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Dodaj aktywność</h2>
                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <button v-for="type in activityTypes" :key="type.value"
                                @click="activityForm.type = type.value"
                                :class="[
                                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                                    activityForm.type === type.value
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'
                                ]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="type.icon" />
                                </svg>
                                {{ type.label }}
                            </button>
                        </div>
                        <textarea v-model="activityForm.content" rows="3" placeholder="Dodaj notatkę lub opis aktywności..."
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                        <button @click="addActivity" :disabled="activityForm.processing || !activityForm.content.trim()"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Dodaj
                        </button>
                    </div>
                </div>

                <!-- Open Deals -->
                <div v-if="contact.deals?.length" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Otwarte deale</h2>
                    <div class="space-y-3">
                        <Link v-for="deal in contact.deals" :key="deal.id" :href="`/crm/deals?deal=${deal.id}`"
                            class="flex items-center justify-between rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-700">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ deal.name }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ deal.stage?.name }}</p>
                            </div>
                            <span class="font-semibold text-slate-900 dark:text-white">
                                {{ new Intl.NumberFormat('pl-PL', { style: 'currency', currency: deal.currency }).format(deal.value) }}
                            </span>
                        </Link>
                    </div>
                </div>

                <!-- Pending Tasks -->
                <div v-if="contact.tasks?.length" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 flex items-center justify-between text-lg font-semibold text-slate-900 dark:text-white">
                        Zadania
                        <Link href="/crm/tasks" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                            Zobacz wszystkie →
                        </Link>
                    </h2>
                    <div class="space-y-3">
                        <div v-for="task in contact.tasks" :key="task.id"
                            class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-white">{{ task.title }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ formatDate(task.due_date) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Historia aktywności</h2>
                    <div v-if="activities?.length" class="space-y-4">
                        <div v-for="activity in activities" :key="activity.id" class="flex gap-4">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                                <svg class="h-5 w-5 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActivityIcon(activity.type)" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ activity.type_label || activity.type }}
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
