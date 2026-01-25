<script setup>
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    show: Boolean,
    task: {
        type: Object,
        default: null,
    },
    contactId: {
        type: Number,
        default: null,
    },
    dealId: {
        type: Number,
        default: null,
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    owners: {
        type: Array,
        default: () => [],
    },
    calendarConnection: {
        type: Object,
        default: null,
    },
    calendars: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["close", "saved"]);

const isEditing = computed(() => !!props.task?.id);

const form = useForm({
    title: "",
    description: "",
    type: "task",
    priority: "medium",
    due_date: "",
    due_time: "09:00",
    end_time: "10:00",
    crm_contact_id: null,
    crm_deal_id: null,
    owner_id: null,
    sync_to_calendar: false,
    selected_calendar_id: null,
    // Recurrence
    is_recurring: false,
    recurrence_type: "weekly",
    recurrence_interval: 1,
    recurrence_days: [],
    recurrence_end_date: null,
    recurrence_count: null,
});

// Reset form when modal opens
watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.task) {
                form.title = props.task.title || "";
                form.description = props.task.description || "";
                form.type = props.task.type || "task";
                form.priority = props.task.priority || "medium";
                // Parse date and time from due_date
                if (props.task.due_date) {
                    const dueDateTime = new Date(props.task.due_date);
                    form.due_date = dueDateTime.toISOString().split("T")[0];
                    const hours = dueDateTime.getHours().toString().padStart(2, '0');
                    const minutes = dueDateTime.getMinutes().toString().padStart(2, '0');
                    form.due_time = `${hours}:${minutes}`;
                } else {
                    form.due_date = "";
                    form.due_time = "09:00";
                }
                // Parse end_time from end_date if available
                if (props.task.end_date) {
                    const endDateTime = new Date(props.task.end_date);
                    const endHours = endDateTime.getHours().toString().padStart(2, '0');
                    const endMinutes = endDateTime.getMinutes().toString().padStart(2, '0');
                    form.end_time = `${endHours}:${endMinutes}`;
                } else {
                    // Default: 1 hour after start
                    form.end_time = "10:00";
                }
                form.crm_contact_id = props.task.crm_contact_id || null;
                form.crm_deal_id = props.task.crm_deal_id || null;
                form.owner_id = props.task.owner_id || null;
                form.sync_to_calendar = props.task.sync_to_calendar || false;
                form.selected_calendar_id =
                    props.task.selected_calendar_id || null;
                // Recurrence
                form.is_recurring = props.task.is_recurring || false;
                form.recurrence_type = props.task.recurrence_type || "weekly";
                form.recurrence_interval = props.task.recurrence_interval || 1;
                form.recurrence_days = props.task.recurrence_days || [];
                form.recurrence_end_date =
                    props.task.recurrence_end_date || null;
                form.recurrence_count = props.task.recurrence_count || null;
            } else {
                form.reset();
                form.crm_contact_id = props.contactId;
                form.crm_deal_id = props.dealId;
                // Set default due date to today and time to 09:00
                form.due_date = new Date().toISOString().split("T")[0];
                form.due_time = "09:00";
                form.end_time = "10:00";
                // Auto-enable calendar sync if user has auto_sync enabled
                form.sync_to_calendar =
                    props.calendarConnection?.auto_sync_tasks || false;
                form.selected_calendar_id =
                    props.calendarConnection?.calendar_id || null;
            }
        }
    },
);

const submit = () => {
    if (isEditing.value) {
        form.put(`/crm/tasks/${props.task.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit("saved");
                emit("close");
            },
        });
    } else {
        form.post("/crm/tasks", {
            preserveScroll: true,
            onSuccess: () => {
                emit("saved");
                emit("close");
            },
        });
    }
};

// Recurrence helpers
const recurrenceEndType = ref("never");

// Watch for recurrence end type changes
watch(recurrenceEndType, (newVal) => {
    if (newVal === "never") {
        form.recurrence_end_date = null;
        form.recurrence_count = null;
    } else if (newVal === "date") {
        form.recurrence_count = null;
    } else if (newVal === "count") {
        form.recurrence_end_date = null;
        if (!form.recurrence_count) {
            form.recurrence_count = 10;
        }
    }
});

// Initialize recurrence end type from task data
watch(
    () => props.task,
    (task) => {
        if (task) {
            if (task.recurrence_count) {
                recurrenceEndType.value = "count";
            } else if (task.recurrence_end_date) {
                recurrenceEndType.value = "date";
            } else {
                recurrenceEndType.value = "never";
            }
        }
    },
    { immediate: true },
);

const toggleRecurrenceDay = (day) => {
    const index = form.recurrence_days.indexOf(day);
    if (index === -1) {
        form.recurrence_days.push(day);
    } else {
        form.recurrence_days.splice(index, 1);
    }
};

const taskTypes = [
    {
        value: "call",
        label: "crm.reminders.types.call",
        icon: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z",
    },
    {
        value: "email",
        label: "crm.reminders.types.email",
        icon: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
    },
    {
        value: "meeting",
        label: "crm.reminders.types.meeting",
        icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
    },
    {
        value: "task",
        label: "crm.reminders.types.other",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
    },
    {
        value: "follow_up",
        label: "crm.reminders.types.follow_up",
        icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    },
];

const priorities = [
    {
        value: "low",
        label: "crm.reminders.priorities.low",
        color: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300",
    },
    {
        value: "medium",
        label: "crm.reminders.priorities.medium",
        color: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300",
    },
    {
        value: "high",
        label: "crm.reminders.priorities.high",
        color: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300",
    },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2
                    class="text-xl font-semibold text-slate-900 dark:text-white"
                >
                    {{
                        isEditing
                            ? $t("crm.task.title.edit")
                            : $t("crm.task.title.new")
                    }}
                </h2>
                <button
                    @click="emit('close')"
                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"
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

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Title -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t("crm.task.fields.title") }}
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        :placeholder="$t('crm.task.fields.title_placeholder')"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <!-- Type Selection -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2"
                    >
                        {{ $t("crm.task.fields.type") }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="type in taskTypes"
                            :key="type.value"
                            type="button"
                            @click="form.type = type.value"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                                form.type === type.value
                                    ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300',
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
                                    :d="type.icon"
                                />
                            </svg>
                            {{ $t(type.label) }}
                        </button>
                    </div>
                </div>

                <!-- Priority -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2"
                    >
                        {{ $t("crm.task.fields.priority") }}
                    </label>
                    <div class="flex gap-2">
                        <button
                            v-for="priority in priorities"
                            :key="priority.value"
                            type="button"
                            @click="form.priority = priority.value"
                            :class="[
                                'rounded-xl px-4 py-2 text-sm font-medium transition',
                                form.priority === priority.value
                                    ? priority.color +
                                      ' ring-2 ring-offset-2 ring-slate-400'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300',
                            ]"
                        >
                            {{ $t(priority.label) }}
                        </button>
                    </div>
                </div>

                <!-- Due Date and Time -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t("crm.task.fields.due_date") }}
                    </label>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="flex-1 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:[color-scheme:dark]"
                        />
                        <div class="flex items-center gap-1">
                            <input
                                v-model="form.due_time"
                                type="time"
                                class="w-28 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:[color-scheme:dark]"
                            />
                            <span class="text-slate-400 dark:text-slate-500">–</span>
                            <input
                                v-model="form.end_time"
                                type="time"
                                class="w-28 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:[color-scheme:dark]"
                            />
                        </div>
                    </div>
                    <InputError :message="form.errors.due_date" class="mt-1" />
                    <InputError :message="form.errors.due_time" class="mt-1" />
                    <InputError :message="form.errors.end_time" class="mt-1" />
                </div>

                <!-- Recurrence Settings -->
                <div
                    class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50 dark:bg-slate-800/50"
                >
                    <div class="flex items-center gap-3">
                        <label
                            class="relative inline-flex items-center cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="form.is_recurring"
                                class="sr-only peer"
                            />
                            <div
                                class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"
                            ></div>
                        </label>
                        <div>
                            <span
                                class="font-medium text-slate-900 dark:text-white"
                                >{{ $t("crm.recurrence.is_recurring") }}</span
                            >
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("crm.recurrence.helper_text") }}
                            </p>
                        </div>
                    </div>

                    <div v-if="form.is_recurring" class="mt-4 space-y-4">
                        <!-- Frequency Type -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                >
                                    {{ $t("crm.recurrence.frequency.label") }}
                                </label>
                                <div class="flex gap-2">
                                    <input
                                        v-model.number="
                                            form.recurrence_interval
                                        "
                                        type="number"
                                        min="1"
                                        max="99"
                                        class="w-20 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-center"
                                    />
                                    <select
                                        v-model="form.recurrence_type"
                                        class="flex-1 rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="daily">
                                            {{
                                                $t(
                                                    "crm.recurrence.frequency.daily",
                                                    form.recurrence_interval,
                                                )
                                            }}
                                        </option>
                                        <option value="weekly">
                                            {{
                                                $t(
                                                    "crm.recurrence.frequency.weekly",
                                                    form.recurrence_interval,
                                                )
                                            }}
                                        </option>
                                        <option value="monthly">
                                            {{
                                                $t(
                                                    "crm.recurrence.frequency.monthly",
                                                    form.recurrence_interval,
                                                )
                                            }}
                                        </option>
                                        <option value="yearly">
                                            {{
                                                $t(
                                                    "crm.recurrence.frequency.yearly",
                                                    form.recurrence_interval,
                                                )
                                            }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Days of Week (for weekly) -->
                        <div v-if="form.recurrence_type === 'weekly'">
                            <label
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2"
                            >
                                {{ $t("crm.recurrence.days_of_week") }}
                            </label>
                            <div class="flex gap-1">
                                <button
                                    v-for="day in [
                                        {
                                            value: 1,
                                            label: $t(
                                                'crm.recurrence.days.mon',
                                            ),
                                        },
                                        {
                                            value: 2,
                                            label: $t(
                                                'crm.recurrence.days.tue',
                                            ),
                                        },
                                        {
                                            value: 3,
                                            label: $t(
                                                'crm.recurrence.days.wed',
                                            ),
                                        },
                                        {
                                            value: 4,
                                            label: $t(
                                                'crm.recurrence.days.thu',
                                            ),
                                        },
                                        {
                                            value: 5,
                                            label: $t(
                                                'crm.recurrence.days.fri',
                                            ),
                                        },
                                        {
                                            value: 6,
                                            label: $t(
                                                'crm.recurrence.days.sat',
                                            ),
                                        },
                                        {
                                            value: 0,
                                            label: $t(
                                                'crm.recurrence.days.sun',
                                            ),
                                        },
                                    ]"
                                    :key="day.value"
                                    type="button"
                                    @click="toggleRecurrenceDay(day.value)"
                                    :class="[
                                        'w-10 h-10 rounded-lg text-sm font-medium transition',
                                        form.recurrence_days.includes(day.value)
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600',
                                    ]"
                                >
                                    {{ day.label }}
                                </button>
                            </div>
                        </div>

                        <!-- End Condition -->
                        <div>
                            <label
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2"
                            >
                                {{ $t("crm.recurrence.end_condition.label") }}
                            </label>
                            <div class="space-y-2">
                                <label
                                    class="flex items-center gap-2 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        v-model="recurrenceEndType"
                                        value="never"
                                        class="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                        >{{
                                            $t(
                                                "crm.recurrence.end_condition.never",
                                            )
                                        }}</span
                                    >
                                </label>
                                <label
                                    class="flex items-center gap-2 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        v-model="recurrenceEndType"
                                        value="date"
                                        class="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                        >{{
                                            $t(
                                                "crm.recurrence.end_condition.date",
                                            )
                                        }}</span
                                    >
                                    <input
                                        v-if="recurrenceEndType === 'date'"
                                        v-model="form.recurrence_end_date"
                                        type="date"
                                        class="ml-2 rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-sm"
                                    />
                                </label>
                                <label
                                    class="flex items-center gap-2 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        v-model="recurrenceEndType"
                                        value="count"
                                        class="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                        >{{
                                            $t(
                                                "crm.recurrence.end_condition.count",
                                            )
                                        }}</span
                                    >
                                    <input
                                        v-if="recurrenceEndType === 'count'"
                                        v-model.number="form.recurrence_count"
                                        type="number"
                                        min="1"
                                        max="999"
                                        class="w-20 ml-1 rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-sm text-center"
                                    />
                                    <span
                                        v-if="recurrenceEndType === 'count'"
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            $t(
                                                "crm.recurrence.end_condition.occurrences",
                                                form.recurrence_count,
                                            )
                                        }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Selection (only if contacts provided and no fixed contactId) -->
                <div v-if="contacts.length && !contactId">
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t("crm.task.fields.contact") }}
                    </label>
                    <select
                        v-model="form.crm_contact_id"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option :value="null">
                            {{ $t("crm.task.fields.contact_placeholder") }}
                        </option>
                        <option
                            v-for="contact in contacts"
                            :key="contact.id"
                            :value="contact.id"
                        >
                            {{ contact.name }}
                        </option>
                    </select>
                </div>

                <!-- Owner Selection -->
                <div v-if="owners.length">
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t("crm.task.fields.owner") }}
                    </label>
                    <select
                        v-model="form.owner_id"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option :value="null">
                            {{ $t("crm.task.fields.owner_auto") }}
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

                <!-- Description -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t("crm.task.fields.description") }}
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        :placeholder="
                            $t('crm.task.fields.description_placeholder')
                        "
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                    <InputError
                        :message="form.errors.description"
                        class="mt-1"
                    />
                </div>

                <!-- Google Calendar Sync -->
                <div
                    v-if="calendarConnection"
                    class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50 dark:bg-slate-800/50"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <svg
                            class="h-5 w-5 text-blue-500"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M19.5 22.5h-15A2.25 2.25 0 012.25 20.25V6.75A2.25 2.25 0 014.5 4.5h15a2.25 2.25 0 012.25 2.25v13.5a2.25 2.25 0 01-2.25 2.25zM7.5 2.25v3M16.5 2.25v3M3.75 9h16.5"
                            />
                        </svg>
                        <span class="font-medium text-slate-900 dark:text-white"
                            >Google Calendar</span
                        >
                        <span
                            class="text-xs text-slate-500 dark:text-slate-400"
                        >
                            ({{ calendarConnection.connected_email }})
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <label
                            class="relative inline-flex items-center cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="form.sync_to_calendar"
                                class="sr-only peer"
                            />
                            <div
                                class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-indigo-600"
                            ></div>
                        </label>
                        <span
                            class="text-sm text-slate-700 dark:text-slate-300"
                        >
                            {{ $t("crm.task.calendar.sync") }}
                        </span>
                    </div>

                    <div
                        v-if="form.sync_to_calendar && calendars.length > 1"
                        class="mt-3"
                    >
                        <label
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                        >
                            {{ $t("crm.task.calendar.select_calendar") }}
                        </label>
                        <select
                            v-model="form.selected_calendar_id"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-sm"
                        >
                            <option :value="null">
                                {{ $t("crm.task.calendar.default") }} ({{
                                    calendarConnection.calendar_id === "primary"
                                        ? $t("crm.task.calendar.primary")
                                        : calendarConnection.calendar_id
                                }})
                            </option>
                            <option
                                v-for="cal in calendars"
                                :key="cal.id"
                                :value="cal.id"
                            >
                                {{ cal.summary }}
                            </option>
                        </select>
                    </div>

                    <p
                        v-if="task?.google_calendar_event_id"
                        class="mt-2 text-xs text-green-600 dark:text-green-400 flex items-center gap-1"
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
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        {{ $t("crm.task.calendar.synced") }}
                    </p>
                </div>

                <!-- Actions -->
                <div
                    class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700"
                >
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        {{ $t("crm.task.actions.cancel") }}
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
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
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                            ></path>
                        </svg>
                        <svg
                            v-else
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        {{
                            isEditing
                                ? $t("crm.task.actions.save")
                                : $t("crm.task.actions.create")
                        }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
