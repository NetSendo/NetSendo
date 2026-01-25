<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    calendarConnection: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["edit-task", "create-task", "date-change"]);

// View mode: 'month' or 'week' (default: week)
const viewMode = ref("week");

// Current date (center of the calendar view)
const currentDate = ref(new Date());

// Hover state for tooltip
const hoveredEvent = ref(null);
const tooltipPosition = ref({ x: 0, y: 0 });

// Ref for week view scrollable container
const weekScrollContainer = ref(null);

// Days of the week (Polish)
const daysOfWeek = ["Pon", "Wt", "Śr", "Czw", "Pt", "Sob", "Nd"];
const daysOfWeekFull = [
    "Poniedziałek",
    "Wtorek",
    "Środa",
    "Czwartek",
    "Piątek",
    "Sobota",
    "Niedziela",
];

// Months (Polish)
const monthNames = [
    "Styczeń",
    "Luty",
    "Marzec",
    "Kwiecień",
    "Maj",
    "Czerwiec",
    "Lipiec",
    "Sierpień",
    "Wrzesień",
    "Październik",
    "Listopad",
    "Grudzień",
];

// Get first day of month adjusted for Monday start
const getFirstDayOfMonth = (date) => {
    const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
    // Convert to Monday-based (0 = Monday, 6 = Sunday)
    return (firstDay.getDay() + 6) % 7;
};

// Get days in month
const getDaysInMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
};

// Get month grid for monthly view
const monthGrid = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();

    const firstDayOffset = getFirstDayOfMonth(currentDate.value);
    const daysInMonth = getDaysInMonth(currentDate.value);
    const daysInPrevMonth = getDaysInMonth(new Date(year, month - 1, 1));

    const grid = [];
    let dayNum = 1;
    let nextMonthDay = 1;

    // Need 6 weeks to fit all possible month layouts
    for (let week = 0; week < 6; week++) {
        const weekDays = [];
        for (let day = 0; day < 7; day++) {
            const cellIndex = week * 7 + day;

            if (cellIndex < firstDayOffset) {
                // Previous month
                const prevDay =
                    daysInPrevMonth - firstDayOffset + cellIndex + 1;
                weekDays.push({
                    day: prevDay,
                    date: new Date(year, month - 1, prevDay),
                    isCurrentMonth: false,
                    isToday: false,
                });
            } else if (dayNum <= daysInMonth) {
                // Current month
                const date = new Date(year, month, dayNum);
                const today = new Date();
                const isToday =
                    date.getDate() === today.getDate() &&
                    date.getMonth() === today.getMonth() &&
                    date.getFullYear() === today.getFullYear();

                weekDays.push({
                    day: dayNum,
                    date: date,
                    isCurrentMonth: true,
                    isToday: isToday,
                });
                dayNum++;
            } else {
                // Next month
                weekDays.push({
                    day: nextMonthDay,
                    date: new Date(year, month + 1, nextMonthDay),
                    isCurrentMonth: false,
                    isToday: false,
                });
                nextMonthDay++;
            }
        }
        grid.push(weekDays);

        // Stop if we've rendered all days and filled the week
        if (dayNum > daysInMonth && weekDays[6].isCurrentMonth === false) {
            break;
        }
    }

    return grid;
});

// Get week grid for weekly view
const weekGrid = computed(() => {
    const date = new Date(currentDate.value);
    // Find Monday of current week
    const dayOfWeek = (date.getDay() + 6) % 7; // Monday = 0
    date.setDate(date.getDate() - dayOfWeek);

    const week = [];
    const today = new Date();

    for (let i = 0; i < 7; i++) {
        const d = new Date(date);
        d.setDate(date.getDate() + i);
        const isToday =
            d.getDate() === today.getDate() &&
            d.getMonth() === today.getMonth() &&
            d.getFullYear() === today.getFullYear();

        week.push({
            day: d.getDate(),
            date: d,
            dayName: daysOfWeekFull[i],
            isToday: isToday,
        });
    }

    return week;
});

// Hours for weekly view (full 24 hours: 00:00 - 23:00)
const hours = computed(() => {
    const h = [];
    for (let i = 0; i < 24; i++) {
        h.push(i.toString().padStart(2, "0") + ":00");
    }
    return h;
});

// Get events for a specific date
const getEventsForDate = (date) => {
    const dateStr = date.toISOString().split("T")[0];

    return props.events.filter((event) => {
        if (!event.start) return false;
        const eventDate = new Date(event.start).toISOString().split("T")[0];
        return eventDate === dateStr;
    });
};

// Get current month label
const currentMonthLabel = computed(() => {
    return `${monthNames[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`;
});

// Get current week label
const currentWeekLabel = computed(() => {
    const week = weekGrid.value;
    const firstDay = week[0].date;
    const lastDay = week[6].date;

    if (firstDay.getMonth() === lastDay.getMonth()) {
        return `${firstDay.getDate()} - ${lastDay.getDate()} ${monthNames[firstDay.getMonth()]} ${firstDay.getFullYear()}`;
    } else {
        return `${firstDay.getDate()} ${monthNames[firstDay.getMonth()].substring(0, 3)} - ${lastDay.getDate()} ${monthNames[lastDay.getMonth()].substring(0, 3)} ${lastDay.getFullYear()}`;
    }
});

// Navigation
const goToToday = () => {
    currentDate.value = new Date();
    emitDateChange();
};

const goPrev = () => {
    const date = new Date(currentDate.value);
    if (viewMode.value === "month") {
        date.setMonth(date.getMonth() - 1);
    } else {
        date.setDate(date.getDate() - 7);
    }
    currentDate.value = date;
    emitDateChange();
};

const goNext = () => {
    const date = new Date(currentDate.value);
    if (viewMode.value === "month") {
        date.setMonth(date.getMonth() + 1);
    } else {
        date.setDate(date.getDate() + 7);
    }
    currentDate.value = date;
    emitDateChange();
};

// Emit date change for parent to fetch events
const emitDateChange = () => {
    let from, to;

    if (viewMode.value === "month") {
        const year = currentDate.value.getFullYear();
        const month = currentDate.value.getMonth();
        from = new Date(year, month - 1, 20);
        to = new Date(year, month + 1, 10);
    } else {
        const week = weekGrid.value;
        from = new Date(week[0].date);
        from.setHours(0, 0, 0, 0);
        to = new Date(week[6].date);
        to.setHours(23, 59, 59, 999);
    }

    emit("date-change", { from, to });
};

// Switch view mode
const setViewMode = (mode) => {
    viewMode.value = mode;
    emitDateChange();
};

// Click handlers
const handleDayClick = (date) => {
    emit("create-task", date);
};

const handleEventClick = (event, e) => {
    e.stopPropagation();
    hoveredEvent.value = null; // Close tooltip on click
    if (event.type === "task" && event.task_id) {
        emit("edit-task", event.task_id);
    }
};

// Event hover handlers
const handleEventMouseEnter = (event, e) => {
    hoveredEvent.value = event;
    const rect = e.target.getBoundingClientRect();
    tooltipPosition.value = {
        x: rect.left + rect.width / 2,
        y: rect.top,
    };
};

const handleEventMouseLeave = () => {
    hoveredEvent.value = null;
};

// Get tooltip positioning style (safely handles window access)
const getTooltipStyle = () => {
    const windowWidth =
        typeof window !== "undefined" ? window.innerWidth : 1200;
    return {
        left: `${Math.min(tooltipPosition.value.x - 144, windowWidth - 300)}px`,
        top: `${Math.max(tooltipPosition.value.y - 10, 10)}px`,
        transform: "translateY(-100%)",
    };
};

// Format time for display
const formatEventTime = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleTimeString("pl-PL", {
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Get priority label
const getPriorityLabel = (priority) => {
    const labels = {
        high: "Wysoki",
        medium: "Średni",
        low: "Niski",
    };
    return labels[priority] || priority;
};

// Get type label
const getTypeLabelText = (type) => {
    const labels = {
        call: "Telefon",
        email: "Email",
        meeting: "Spotkanie",
        task: "Zadanie",
        follow_up: "Follow-up",
    };
    return labels[type] || type;
};

// Get event position for weekly view
const getEventStyle = (event, dayDate) => {
    if (!event.start) return {};

    const startDate = new Date(event.start);
    const hour = startDate.getHours();
    const minute = startDate.getMinutes();

    // Calculate position (midnight is top, each hour = 48px)
    const top = hour * 48 + (minute / 60) * 48;

    // Calculate height (default 1 hour)
    let height = 48;
    if (event.end) {
        const endDate = new Date(event.end);
        const durationMinutes =
            (endDate.getTime() - startDate.getTime()) / (1000 * 60);
        height = Math.max(24, (durationMinutes / 60) * 48);
    }

    return {
        top: `${top}px`,
        height: `${height}px`,
    };
};

// Priority icons
const getPriorityIcon = (priority) => {
    switch (priority) {
        case "high":
            return "!";
        case "medium":
            return "•";
        case "low":
            return "○";
        default:
            return "";
    }
};

// Task type icons
const getTypeIcon = (type) => {
    const icons = {
        call: "📞",
        email: "✉️",
        meeting: "👥",
        task: "✓",
        follow_up: "🔄",
    };
    return icons[type] || "📌";
};

// Initial fetch and scroll to 8:00 AM
onMounted(() => {
    emitDateChange();
    // Scroll to 8:00 AM in week view (8 hours * 48px per hour = 384px)
    if (viewMode.value === "week") {
        setTimeout(() => {
            if (weekScrollContainer.value) {
                weekScrollContainer.value.scrollTop = 8 * 48;
            }
        }, 100);
    }
});

// Watch view mode changes
watch(viewMode, () => {
    emitDateChange();
});
</script>

<template>
    <div class="calendar-container">
        <!-- Calendar Header -->
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800"
        >
            <!-- Navigation -->
            <div class="flex items-center gap-2">
                <button
                    @click="goPrev"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                    :title="
                        viewMode === 'month'
                            ? 'Poprzedni miesiąc'
                            : 'Poprzedni tydzień'
                    "
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
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </button>

                <button
                    @click="goToToday"
                    class="rounded-lg bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50"
                >
                    Dziś
                </button>

                <button
                    @click="goNext"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                    :title="
                        viewMode === 'month'
                            ? 'Następny miesiąc'
                            : 'Następny tydzień'
                    "
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
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </button>

                <h2
                    class="ml-2 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    {{
                        viewMode === "month"
                            ? currentMonthLabel
                            : currentWeekLabel
                    }}
                </h2>
            </div>

            <!-- View Toggle -->
            <div class="flex rounded-lg bg-slate-100 p-1 dark:bg-slate-700">
                <button
                    @click="setViewMode('month')"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition',
                        viewMode === 'month'
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                    ]"
                >
                    Miesiąc
                </button>
                <button
                    @click="setViewMode('week')"
                    :class="[
                        'rounded-md px-3 py-1.5 text-sm font-medium transition',
                        viewMode === 'week'
                            ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                    ]"
                >
                    Tydzień
                </button>
            </div>

            <!-- Google Calendar indicator -->
            <div
                v-if="calendarConnection"
                class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
            >
                <svg
                    class="h-4 w-4 text-green-500"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                    />
                </svg>
                <span>{{ calendarConnection.connected_email }}</span>
            </div>
        </div>

        <!-- Monthly View -->
        <div
            v-if="viewMode === 'month'"
            class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800"
        >
            <!-- Days header -->
            <div
                class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div
                    v-for="day in daysOfWeek"
                    :key="day"
                    class="px-2 py-3 text-center text-sm font-medium text-slate-600 dark:text-slate-400"
                >
                    {{ day }}
                </div>
            </div>

            <!-- Calendar grid -->
            <div class="grid grid-cols-7">
                <template
                    v-for="(week, weekIndex) in monthGrid"
                    :key="weekIndex"
                >
                    <div
                        v-for="(day, dayIndex) in week"
                        :key="`${weekIndex}-${dayIndex}`"
                        @click="handleDayClick(day.date)"
                        :class="[
                            'min-h-[120px] cursor-pointer border-b border-r border-slate-200 p-2 transition dark:border-slate-700',
                            day.isCurrentMonth
                                ? 'bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700/50'
                                : 'bg-slate-50/50 hover:bg-slate-100/50 dark:bg-slate-800/30 dark:hover:bg-slate-700/30',
                            dayIndex === 6 ? 'border-r-0' : '',
                        ]"
                    >
                        <!-- Day number -->
                        <div
                            :class="[
                                'mb-1 flex h-7 w-7 items-center justify-center rounded-full text-sm font-medium',
                                day.isToday
                                    ? 'bg-indigo-600 text-white'
                                    : day.isCurrentMonth
                                      ? 'text-slate-900 dark:text-white'
                                      : 'text-slate-400 dark:text-slate-500',
                            ]"
                        >
                            {{ day.day }}
                        </div>

                        <!-- Events -->
                        <div class="space-y-1">
                            <div
                                v-for="event in getEventsForDate(
                                    day.date,
                                ).slice(0, 3)"
                                :key="event.id"
                                @click="handleEventClick(event, $event)"
                                @mouseenter="
                                    handleEventMouseEnter(event, $event)
                                "
                                @mouseleave="handleEventMouseLeave"
                                :style="{ backgroundColor: event.color + '20' }"
                                :class="[
                                    'cursor-pointer truncate rounded px-1.5 py-0.5 text-xs font-medium transition hover:opacity-80 hover:shadow-sm',
                                    event.is_completed
                                        ? 'line-through opacity-60'
                                        : '',
                                ]"
                            >
                                <span
                                    class="mr-1 inline-block h-1.5 w-1.5 rounded-full"
                                    :style="{ backgroundColor: event.color }"
                                ></span>
                                <span
                                    :style="{ color: event.color }"
                                    class="dark:brightness-125"
                                >
                                    {{ event.type === "google" ? "📅 " : ""
                                    }}{{ event.title }}
                                </span>
                            </div>
                            <div
                                v-if="getEventsForDate(day.date).length > 3"
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                +{{
                                    getEventsForDate(day.date).length - 3
                                }}
                                więcej
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Weekly View -->
        <div
            v-if="viewMode === 'week'"
            class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800"
        >
            <!-- Days header -->
            <div
                class="grid grid-cols-8 border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div
                    class="border-r border-slate-200 px-2 py-3 dark:border-slate-700"
                >
                    <!-- Empty corner -->
                </div>
                <div
                    v-for="day in weekGrid"
                    :key="day.date.toISOString()"
                    :class="[
                        'px-2 py-3 text-center',
                        day.isToday ? 'bg-indigo-50 dark:bg-indigo-900/20' : '',
                    ]"
                >
                    <div
                        class="text-sm font-medium text-slate-600 dark:text-slate-400"
                    >
                        {{ day.dayName.substring(0, 3) }}
                    </div>
                    <div
                        :class="[
                            'mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full text-lg font-semibold',
                            day.isToday
                                ? 'bg-indigo-600 text-white'
                                : 'text-slate-900 dark:text-white',
                        ]"
                    >
                        {{ day.day }}
                    </div>
                </div>
            </div>

            <!-- Time grid -->
            <div
                ref="weekScrollContainer"
                class="max-h-[600px] overflow-y-auto"
            >
                <div class="grid grid-cols-8">
                    <!-- Time column -->
                    <div
                        class="border-r border-slate-200 dark:border-slate-700"
                    >
                        <div
                            v-for="hour in hours"
                            :key="hour"
                            class="h-12 border-b border-slate-100 px-2 py-1 text-right text-xs text-slate-500 dark:border-slate-700/50 dark:text-slate-400"
                        >
                            {{ hour }}
                        </div>
                    </div>

                    <!-- Day columns -->
                    <div
                        v-for="day in weekGrid"
                        :key="day.date.toISOString()"
                        @click="handleDayClick(day.date)"
                        :class="[
                            'relative cursor-pointer border-r border-slate-200 dark:border-slate-700',
                            day.isToday
                                ? 'bg-indigo-50/30 dark:bg-indigo-900/10'
                                : '',
                        ]"
                    >
                        <!-- Hour lines -->
                        <div
                            v-for="hour in hours"
                            :key="hour"
                            class="h-12 border-b border-slate-100 dark:border-slate-700/50"
                        ></div>

                        <!-- Events positioned absolutely -->
                        <div
                            v-for="event in getEventsForDate(day.date)"
                            :key="event.id"
                            @click="handleEventClick(event, $event)"
                            @mouseenter="handleEventMouseEnter(event, $event)"
                            @mouseleave="handleEventMouseLeave"
                            class="absolute left-0.5 right-0.5 cursor-pointer overflow-hidden rounded px-1 py-0.5 text-xs font-medium shadow-sm transition hover:opacity-90 hover:shadow-md hover:z-10"
                            :style="{
                                ...getEventStyle(event, day.date),
                                backgroundColor: event.color + '30',
                                borderLeft: `3px solid ${event.color}`,
                            }"
                        >
                            <div
                                class="truncate"
                                :style="{ color: event.color }"
                                :class="{
                                    'line-through opacity-60':
                                        event.is_completed,
                                }"
                            >
                                {{
                                    event.type === "google"
                                        ? "📅 "
                                        : getTypeIcon(event.task_type) + " "
                                }}
                                {{ event.title }}
                            </div>
                            <div
                                v-if="event.contact"
                                class="truncate text-slate-500 dark:text-slate-400"
                            >
                                {{ event.contact.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div
            class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-400"
        >
            <div class="flex items-center gap-1">
                <span
                    class="inline-block h-3 w-3 rounded-full"
                    style="background-color: #ef4444"
                ></span>
                Wysoki priorytet
            </div>
            <div class="flex items-center gap-1">
                <span
                    class="inline-block h-3 w-3 rounded-full"
                    style="background-color: #f59e0b"
                ></span>
                Średni priorytet
            </div>
            <div class="flex items-center gap-1">
                <span
                    class="inline-block h-3 w-3 rounded-full"
                    style="background-color: #3b82f6"
                ></span>
                Niski priorytet
            </div>
            <div class="flex items-center gap-1">
                <span
                    class="inline-block h-3 w-3 rounded-full"
                    style="background-color: #10b981"
                ></span>
                Ukończone
            </div>
            <div v-if="calendarConnection" class="flex items-center gap-1">
                <span
                    class="inline-block h-3 w-3 rounded-full"
                    style="background-color: #4285f4"
                ></span>
                Google Calendar
            </div>
        </div>

        <!-- Event Tooltip/Popover -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-1"
            >
                <div
                    v-if="hoveredEvent && hoveredEvent.type === 'task'"
                    class="fixed z-50 w-72 rounded-xl bg-white p-4 shadow-xl ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"
                    :style="getTooltipStyle()"
                    @mouseenter="hoveredEvent = hoveredEvent"
                    @mouseleave="handleEventMouseLeave"
                >
                    <!-- Header with type icon and title -->
                    <div class="flex items-start gap-3 mb-3">
                        <div
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg text-lg"
                            :style="{
                                backgroundColor: hoveredEvent.color + '20',
                                color: hoveredEvent.color,
                            }"
                        >
                            {{ getTypeIcon(hoveredEvent.task_type) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4
                                class="font-semibold text-slate-900 dark:text-white truncate"
                            >
                                {{ hoveredEvent.title }}
                            </h4>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        getTypeLabelText(hoveredEvent.task_type)
                                    }}
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300':
                                            hoveredEvent.priority === 'high',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300':
                                            hoveredEvent.priority === 'medium',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300':
                                            hoveredEvent.priority === 'low',
                                    }"
                                >
                                    {{
                                        getPriorityLabel(hoveredEvent.priority)
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="space-y-2 text-sm">
                        <!-- Time -->
                        <div
                            class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
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
                            <span
                                >{{ formatEventTime(hoveredEvent.start) }} -
                                {{ formatEventTime(hoveredEvent.end) }}</span
                            >
                        </div>

                        <!-- Contact -->
                        <div
                            v-if="hoveredEvent.contact"
                            class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
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
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                            <span>{{ hoveredEvent.contact.name }}</span>
                        </div>

                        <!-- Description preview -->
                        <div
                            v-if="hoveredEvent.description"
                            class="text-slate-500 dark:text-slate-400 line-clamp-2"
                        >
                            {{ hoveredEvent.description }}
                        </div>
                    </div>

                    <!-- Click hint -->
                    <div
                        class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700 text-center"
                    >
                        <span
                            class="text-xs text-indigo-600 dark:text-indigo-400 font-medium"
                        >
                            🖊️ Kliknij aby edytować
                        </span>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.calendar-container {
    width: 100%;
}
</style>
