<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    tasks: Object,
    counts: Object,
    view: String,
    owners: Array,
    contacts: Array,
    filters: Object,
});

const selectedView = ref(props.view || "today");

// Change view
const changeView = (view) => {
    selectedView.value = view;
    router.get("/crm/tasks", { view }, { preserveScroll: true });
};

// Complete task
const completeTask = async (task) => {
    await router.post(`/crm/tasks/${task.id}/complete`, {}, { preserveScroll: true });
};

// Format date
const formatDate = (date) => {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("pl-PL", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    }); // TODO: Use user locale
};

// Priority class
const getPriorityClass = (priority) => {
    const classes = {
        high: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
        medium: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300",
        low: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300",
    };
    return classes[priority] || classes.medium;
};

// Type icons
const getTypeIcon = (type) => {
    const icons = {
        call: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z",
        email: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
        meeting: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
        task: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
        follow_up: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    };
    return icons[type] || icons.task;
};
</script>

<template>
    <Head :title="$t('crm.tasks.title', 'Zadania CRM')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $t('crm.tasks.title', 'Zadania') }}</h1>
            </div>
        </template>

        <!-- View Tabs -->
        <div class="mb-6 flex gap-2">
            <button @click="changeView('overdue')"
                :class="['flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'overdue' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300']">
                {{ $t('crm.tasks.filter_overdue', 'Zaległe') }}
                <span v-if="counts?.overdue" class="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white">{{ counts.overdue }}</span>
            </button>
            <button @click="changeView('today')"
                :class="['flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'today' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300']">
                {{ $t('crm.tasks.filter_today', 'Na dziś') }}
                <span v-if="counts?.today" class="rounded-full bg-amber-600 px-2 py-0.5 text-xs text-white">{{ counts.today }}</span>
            </button>
            <button @click="changeView('upcoming')"
                :class="['flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'upcoming' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300']">
                {{ $t('crm.tasks.filter_upcoming', 'Nadchodzące') }}
                <span v-if="counts?.upcoming" class="rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white">{{ counts.upcoming }}</span>
            </button>
        </div>

        <!-- Tasks List -->
        <div class="rounded-2xl bg-white shadow-sm dark:bg-slate-800">
            <div v-if="tasks?.data?.length" class="divide-y divide-slate-200 dark:divide-slate-700">
                <div v-for="task in tasks.data" :key="task.id" class="flex items-center gap-4 p-4 transition hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <button @click="completeTask(task)" class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 border-slate-300 transition hover:border-indigo-500 hover:bg-indigo-50 dark:border-slate-600">
                        <svg class="h-3 w-3 text-transparent transition hover:text-indigo-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTypeIcon(task.type)" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 dark:text-white">{{ task.title }}</p>
                        <p v-if="task.contact?.subscriber" class="text-sm text-slate-500 dark:text-slate-400">
                            {{ task.contact.subscriber.first_name }} {{ task.contact.subscriber.last_name }}
                        </p>
                    </div>
                    <span :class="[getPriorityClass(task.priority), 'rounded-full px-2 py-1 text-xs font-medium']">
                        {{ task.priority }}
                    </span>
                    <span :class="['text-sm', selectedView === 'overdue' ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400']">
                        {{ formatDate(task.due_date) }}
                    </span>
                    <Link v-if="task.contact" :href="`/crm/contacts/${task.contact.id}`" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </Link>
                </div>
            </div>
            <div v-else class="py-16 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-4 text-slate-500">{{ $t('crm.tasks.empty_category', 'Brak zadań w tej kategorii') }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
