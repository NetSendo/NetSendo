<script setup>
import { ref, computed, onMounted } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";
import TaskModal from "@/Components/Crm/TaskModal.vue";
import CalendarGrid from "@/Components/Crm/CalendarGrid.vue";
import Dropdown from "@/Components/Dropdown.vue";
import axios from "axios";

const { formatDate: formatDateBase, locale } = useDateTime();
const props = defineProps({
    tasks: Object,
    counts: Object,
    view: String,
    owners: Array,
    contacts: Array,
    filters: Object,
    calendarConnection: Object,
    calendars: Array,
    zoomConnection: Object,
});

const selectedView = ref(props.view || "today");

// View mode: 'list' or 'calendar'
// Domyślnie pokazuj listę jeśli użytkownik wybrał filtr (filtry działają tylko w widoku listy)
const viewMode = ref(props.view ? "list" : "calendar");

// Calendar events
const calendarEvents = ref([]);
const calendarLoading = ref(false);
const newTaskDate = ref(null);

// Task modal
const showTaskModal = ref(false);
const editingTask = ref(null);

const openNewTaskModal = () => {
    editingTask.value = null;
    showTaskModal.value = true;
};

const openEditTaskModal = (task) => {
    editingTask.value = task;
    showTaskModal.value = true;
};

// Open task modal from calendar with pre-selected date
const openNewTaskModalFromCalendar = (date) => {
    newTaskDate.value = date;
    editingTask.value = null;
    showTaskModal.value = true;
};

// Edit task from calendar by ID
const editTaskById = async (taskId) => {
    // First try to find in the current list view
    let task = props.tasks?.data?.find((t) => t.id === taskId);

    if (task) {
        openEditTaskModal(task);
        return;
    }

    // Try to find in calendar events (create minimal task object from event data)
    const event = calendarEvents.value.find(
        (e) => e.type === "task" && e.task_id === taskId,
    );

    if (event) {
        // Create task object from event data for the modal
        openEditTaskModal({
            id: event.task_id,
            title: event.title,
            description: event.description,
            type: event.task_type,
            priority: event.priority,
            status: event.status,
            due_date: event.start,
            end_date: event.end,
            contact: event.contact,
            crm_contact_id: event.contact?.id || null,
        });
    } else {
        console.error("Task not found in list or calendar events");
    }
};

const onTaskSaved = () => {
    showTaskModal.value = false;
    newTaskDate.value = null;
    router.reload({ only: ["tasks", "counts"] });
    // Refresh calendar events if in calendar view
    if (viewMode.value === "calendar" && lastCalendarDateRange.value) {
        fetchCalendarEvents(lastCalendarDateRange.value);
    }
};

// Fetch calendar events
const lastCalendarDateRange = ref(null);
const fetchCalendarEvents = async ({ from, to }) => {
    lastCalendarDateRange.value = { from, to };
    calendarLoading.value = true;
    try {
        const response = await axios.get("/crm/tasks/calendar-events", {
            params: {
                from: from.toISOString(),
                to: to.toISOString(),
                include_google: props.calendarConnection ? 1 : 0,
            },
        });
        calendarEvents.value = response.data.events || [];
    } catch (e) {
        console.error("Failed to fetch calendar events", e);
        calendarEvents.value = [];
    } finally {
        calendarLoading.value = false;
    }
};

// Switch view mode
const setViewMode = (mode) => {
    viewMode.value = mode;
    // Fetch Google events when switching to list view
    if (
        mode === "list" &&
        props.calendarConnection &&
        !calendarEvents.value.length
    ) {
        const today = new Date();
        const from = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate(),
        );
        const to = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate() + 7,
        );
        fetchCalendarEvents({ from, to });
    }
};

// Google Calendar events (filtered from calendarEvents)
const googleEvents = computed(() => {
    return calendarEvents.value.filter((event) => event.type === "google");
});

// Change view
const changeView = (view) => {
    selectedView.value = view;
    // Automatycznie przełącz na widok listy, ponieważ filtry nie są widoczne w kalendarzu
    viewMode.value = "list";
    router.get("/crm/tasks", { view }, { preserveScroll: true });
};

// Complete task
const completeTask = async (task) => {
    await router.post(
        `/crm/tasks/${task.id}/complete`,
        {},
        { preserveScroll: true },
    );
};

// Reschedule quick actions
const rescheduleTask = async (task, days) => {
    const newDate = new Date();
    newDate.setDate(newDate.getDate() + days);
    await router.post(
        `/crm/tasks/${task.id}/reschedule`,
        {
            due_date: newDate.toISOString().split("T")[0],
        },
        { preserveScroll: true },
    );
};

// Create follow-up
const createFollowUp = async (task, days) => {
    await router.post(
        `/crm/tasks/${task.id}/follow-up`,
        {
            days: days,
        },
        { preserveScroll: true },
    );
};

// Delete task
const deleteTask = async (task) => {
    if (confirm("Czy na pewno chcesz usunąć to zadanie?")) {
        await router.delete(`/crm/tasks/${task.id}`, { preserveScroll: true });
    }
};

// Format date
const formatDate = (date) => {
    if (!date) return "-";
    return formatDateBase(date, null, {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
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

// Priority label
const getPriorityLabel = (priority) => {
    const labels = {
        high: "Wysoki",
        medium: "Średni",
        low: "Niski",
    };
    return labels[priority] || priority;
};

// Type labels
const getTypeLabel = (type) => {
    const labels = {
        call: "Telefon",
        email: "Email",
        meeting: "Spotkanie",
        task: "Zadanie",
        follow_up: "Follow-up",
    };
    return labels[type] || type;
};

// Type icons
const getTypeIcon = (type) => {
    const icons = {
        call: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z",
        email: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
        meeting:
            "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
        task: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
        follow_up: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    };
    return icons[type] || icons.task;
};

// Category badge
const getCategoryBadge = (task) => {
    if (task.is_follow_up) {
        return {
            label: "Follow-up",
            class: "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300",
            icon: "🔄",
        };
    }
    if (task.reminder_at) {
        return {
            label: "Przypomnienie",
            class: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300",
            icon: "⏰",
        };
    }
    if (task.priority === "high") {
        return {
            label: "Ważne",
            class: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
            icon: "❗",
        };
    }
    return null;
};

// Fetch Google Calendar events on mount if in list view mode
onMounted(() => {
    if (viewMode.value === "list" && props.calendarConnection) {
        const today = new Date();
        const from = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate(),
        );
        const to = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate() + 7,
        );
        fetchCalendarEvents({ from, to });
    }
});
</script>

<template>
    <Head :title="$t('crm.tasks.title', 'Zadania CRM')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $t("crm.tasks.title", "Zadania") }}
                </h1>
                <div class="flex items-center gap-3">
                    <Link
                        href="/crm/sequences"
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
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                        {{ $t("crm.sequences.title", "Sekwencje") }}
                    </Link>
                    <button
                        @click="openNewTaskModal"
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
                        {{ $t("crm.tasks.add", "Dodaj zadanie") }}
                    </button>
                </div>
            </div>
        </template>

        <!-- View Tabs -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                @click="changeView('overdue')"
                :class="[
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'overdue'
                        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                        : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300',
                ]"
            >
                {{ $t("crm.tasks.filter_overdue", "Zaległe") }}
                <span
                    v-if="counts?.overdue"
                    class="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white"
                    >{{ counts.overdue }}</span
                >
            </button>
            <button
                @click="changeView('today')"
                :class="[
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'today'
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                        : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300',
                ]"
            >
                {{ $t("crm.tasks.filter_today", "Na dziś") }}
                <span
                    v-if="counts?.today"
                    class="rounded-full bg-amber-600 px-2 py-0.5 text-xs text-white"
                    >{{ counts.today }}</span
                >
            </button>
            <button
                @click="changeView('upcoming')"
                :class="[
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'upcoming'
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300',
                ]"
            >
                {{ $t("crm.tasks.filter_upcoming", "Nadchodzące") }}
                <span
                    v-if="counts?.upcoming"
                    class="rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white"
                    >{{ counts.upcoming }}</span
                >
            </button>
            <button
                @click="changeView('completed')"
                :class="[
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                    selectedView === 'completed'
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                        : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300',
                ]"
            >
                {{ $t("crm.tasks.filter_completed", "Zakończone") }}
            </button>

            <!-- Spacer -->
            <div class="flex-1"></div>

            <!-- View Mode Toggle -->
            <div class="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-700">
                <button
                    @click="setViewMode('list')"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        viewMode === 'list'
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                    ]"
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
                            d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                    </svg>
                    {{ $t("crm.tasks.list_view", "Lista") }}
                </button>
                <button
                    @click="setViewMode('calendar')"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        viewMode === 'calendar'
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                    ]"
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
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    {{ $t("crm.tasks.calendar_view", "Kalendarz") }}
                </button>
            </div>
        </div>

        <!-- Calendar View -->
        <CalendarGrid
            v-if="viewMode === 'calendar'"
            :events="calendarEvents"
            :calendar-connection="calendarConnection"
            @edit-task="editTaskById"
            @create-task="openNewTaskModalFromCalendar"
            @date-change="fetchCalendarEvents"
        />

        <!-- Loading indicator for calendar -->
        <div
            v-if="viewMode === 'calendar' && calendarLoading"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/10"
        >
            <div class="rounded-xl bg-white p-4 shadow-lg dark:bg-slate-800">
                <svg
                    class="h-8 w-8 animate-spin text-indigo-600"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
            </div>
        </div>

        <!-- Tasks List -->
        <div
            v-if="viewMode === 'list'"
            class="rounded-2xl bg-white shadow-sm dark:bg-slate-800"
        >
            <div
                v-if="tasks?.data?.length"
                class="divide-y divide-slate-200 dark:divide-slate-700"
            >
                <div
                    v-for="task in tasks.data"
                    :key="task.id"
                    class="group flex items-center gap-4 p-4 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    <!-- Complete Button -->
                    <button
                        v-if="task.status !== 'completed'"
                        @click="completeTask(task)"
                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 border-slate-300 transition hover:border-green-500 hover:bg-green-50 dark:border-slate-600 dark:hover:border-green-400 dark:hover:bg-green-900/30"
                        title="Oznacz jako ukończone"
                    >
                        <svg
                            class="h-3 w-3 text-transparent group-hover:text-green-500"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                    <div
                        v-else
                        class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                    >
                        <svg
                            class="h-3 w-3 text-green-600 dark:text-green-400"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>

                    <!-- Type Icon -->
                    <div
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700"
                    >
                        <svg
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="getTypeIcon(task.type)"
                            />
                        </svg>
                    </div>

                    <!-- Task Content (Clickable for edit) -->
                    <div
                        class="flex-1 min-w-0 cursor-pointer"
                        @click="openEditTaskModal(task)"
                    >
                        <div class="flex items-center gap-2">
                            <p
                                :class="[
                                    'font-medium',
                                    task.status === 'completed'
                                        ? 'text-slate-400 line-through dark:text-slate-500'
                                        : 'text-slate-900 dark:text-white',
                                ]"
                            >
                                {{ task.title }}
                            </p>
                            <!-- Category Badge -->
                            <span
                                v-if="getCategoryBadge(task)"
                                :class="[
                                    getCategoryBadge(task).class,
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                ]"
                            >
                                {{ getCategoryBadge(task).icon }}
                                {{ getCategoryBadge(task).label }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <span
                                class="text-xs text-slate-500 dark:text-slate-400"
                                >{{ getTypeLabel(task.type) }}</span
                            >
                            <span
                                v-if="task.contact?.subscriber"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                • {{ task.contact.subscriber.first_name }}
                                {{ task.contact.subscriber.last_name }}
                            </span>
                        </div>
                    </div>

                    <!-- Priority Badge -->
                    <span
                        :class="[
                            getPriorityClass(task.priority),
                            'rounded-full px-2 py-1 text-xs font-medium',
                        ]"
                    >
                        {{ getPriorityLabel(task.priority) }}
                    </span>

                    <!-- Due Date -->
                    <span
                        :class="[
                            'text-sm whitespace-nowrap',
                            selectedView === 'overdue'
                                ? 'text-red-600 dark:text-red-400 font-medium'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                    >
                        {{ formatDate(task.due_date) }}
                    </span>

                    <!-- View Contact Link -->
                    <Link
                        v-if="task.contact"
                        :href="`/crm/contacts/${task.contact.id}`"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                        title="Zobacz kontakt"
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

                    <!-- Google Meet Link -->
                    <a
                        v-if="task.google_meet_link"
                        :href="task.google_meet_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-medium text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30"
                        title="Dołącz do Google Meet"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM8 17v-2h2v-2H8v-2h2V9H8V7h4v10H8zm8-2v2h-4v-2h4z"
                            />
                        </svg>
                        Meet
                    </a>

                    <!-- Zoom Meeting Link -->
                    <a
                        v-if="task.zoom_meeting_link"
                        :href="task.zoom_meeting_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                        title="Dołącz do Zoom"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M4 4h10a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm14 3l4-2v10l-4-2V7z"
                            />
                        </svg>
                        Zoom
                    </a>

                    <!-- Actions Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
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
                                        d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="py-1">
                                <button
                                    @click="openEditTaskModal(task)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                    Edytuj
                                </button>
                                <div
                                    class="border-t border-slate-200 dark:border-slate-600 my-1"
                                ></div>
                                <button
                                    @click="rescheduleTask(task, 1)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    Przełóż na jutro
                                </button>
                                <button
                                    @click="rescheduleTask(task, 3)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    Przełóż o 3 dni
                                </button>
                                <button
                                    @click="rescheduleTask(task, 7)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    Przełóż o tydzień
                                </button>
                                <div
                                    class="border-t border-slate-200 dark:border-slate-600 my-1"
                                ></div>
                                <button
                                    @click="createFollowUp(task, 3)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                    Utwórz follow-up (+3 dni)
                                </button>
                                <div
                                    class="border-t border-slate-200 dark:border-slate-600 my-1"
                                ></div>
                                <button
                                    @click="deleteTask(task)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
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
                                    Usuń
                                </button>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>
            <div v-else class="py-16 text-center">
                <svg
                    class="mx-auto h-12 w-12 text-slate-300"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="mt-4 text-slate-500">
                    {{
                        $t(
                            "crm.task.empty.description",
                            "Brak zadań w tej kategorii",
                        )
                    }}
                </p>
                <button
                    @click="openNewTaskModal"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
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
                    {{ $t("crm.task.empty.button", "Dodaj pierwsze zadanie") }}
                </button>
            </div>
        </div>

        <!-- Google Calendar Events Section (in list view) -->
        <div
            v-if="
                viewMode === 'list' && calendarConnection && googleEvents.length
            "
            class="mt-6 rounded-2xl bg-white shadow-sm dark:bg-slate-800"
        >
            <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                <h3
                    class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    <svg
                        class="h-5 w-5 text-blue-500"
                        fill="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                        />
                    </svg>
                    {{
                        $t(
                            "crm.tasks.google_calendar_events",
                            "Wydarzenia z Google Calendar",
                        )
                    }}
                    <span
                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                    >
                        {{ googleEvents.length }}
                    </span>
                </h3>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                <div
                    v-for="event in googleEvents"
                    :key="event.id"
                    class="flex items-center gap-4 p-4 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                >
                    <!-- Google Calendar Icon -->
                    <div
                        class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30"
                    >
                        <svg
                            class="h-4 w-4 text-blue-600 dark:text-blue-400"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                            />
                        </svg>
                    </div>

                    <!-- Event Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p
                                class="font-medium text-slate-900 dark:text-white truncate"
                            >
                                {{ event.title }}
                            </p>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                            >
                                📅 Google
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <span
                                v-if="event.location"
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                📍 {{ event.location }}
                            </span>
                        </div>
                    </div>

                    <!-- Event Time -->
                    <span
                        class="text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                    >
                        {{ formatDate(event.start) }}
                    </span>

                    <!-- Google Meet Link -->
                    <a
                        v-if="event.google_meet_link"
                        :href="event.google_meet_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50"
                        title="Dołącz do Google Meet"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                            />
                        </svg>
                        Dołącz do Meet
                    </a>

                    <!-- Zoom Meeting Link -->
                    <a
                        v-if="event.zoom_meeting_link"
                        :href="event.zoom_meeting_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50"
                        title="Dołącz do Zoom"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M4 4h10a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm14 3l4-2v10l-4-2V7z"
                            />
                        </svg>
                        Dołącz do Zoom
                    </a>
                </div>
            </div>
        </div>

        <!-- Task Modal -->
        <TaskModal
            :show="showTaskModal"
            :task="editingTask"
            :contacts="contacts"
            :owners="owners"
            :calendar-connection="calendarConnection"
            :calendars="calendars"
            :zoom-connection="zoomConnection"
            @close="showTaskModal = false"
            @saved="onTaskSaved"
        />
    </AuthenticatedLayout>
</template>
