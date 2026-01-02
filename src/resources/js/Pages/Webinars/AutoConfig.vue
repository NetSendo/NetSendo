<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    webinar: Object,
    scheduleTypes: Object,
    daysOfWeek: Object,
    chatScriptsPreview: Array,
    userTimezone: String,
    webinarTimezone: String,
    timezones: Array,
});

const schedule = props.webinar.schedule || {};

const form = useForm({
    schedule_type: schedule.schedule_type || 'recurring',
    days_of_week: schedule.days_of_week || [1, 2, 3, 4, 5],  // Mon-Fri default
    times_of_day: schedule.times_of_day || ['10:00'],
    fixed_dates: schedule.fixed_dates || [],
    start_delay_minutes: schedule.start_delay_minutes || 15,
    available_slots: schedule.available_slots || [10, 30, 60],
    start_date: schedule.start_date || '',
    end_date: schedule.end_date || '',
    max_sessions_per_day: schedule.max_sessions_per_day || null,
    max_attendees_per_session: schedule.max_attendees_per_session || null,
    timezone: schedule.timezone || null, // null means inherit
    is_active: schedule.is_active !== false,
});

const saving = ref(false);
const nextSessions = ref([]);
const newTime = ref('');
const newFixedDate = ref('');
const newSlotMinutes = ref('');

// Save schedule
const saveSchedule = async () => {
    saving.value = true;
    try {
        const response = await axios.post(route('webinars.auto.schedule', props.webinar.id), form.data());
        nextSessions.value = response.data.next_sessions || [];
    } catch (error) {
        console.error('Error saving schedule:', error);
    } finally {
        saving.value = false;
    }
};

// Add time slot
const addTime = () => {
    if (newTime.value && !form.times_of_day.includes(newTime.value)) {
        form.times_of_day.push(newTime.value);
        form.times_of_day.sort();
        newTime.value = '';
    }
};

// Remove time slot
const removeTime = (index) => {
    form.times_of_day.splice(index, 1);
};

// Toggle day of week
const toggleDay = (dayIndex) => {
    const index = form.days_of_week.indexOf(dayIndex);
    if (index > -1) {
        form.days_of_week.splice(index, 1);
    } else {
        form.days_of_week.push(dayIndex);
        form.days_of_week.sort();
    }
};

// Add fixed date
const addFixedDate = () => {
    if (newFixedDate.value && !form.fixed_dates.includes(newFixedDate.value)) {
        form.fixed_dates.push(newFixedDate.value);
        form.fixed_dates.sort();
        newFixedDate.value = '';
    }
};

// Remove fixed date
const removeFixedDate = (index) => {
    form.fixed_dates.splice(index, 1);
};

// Add evergreen slot
const addSlot = () => {
    const minutes = parseInt(newSlotMinutes.value);
    if (minutes > 0 && !form.available_slots.includes(minutes)) {
        form.available_slots.push(minutes);
        form.available_slots.sort((a, b) => a - b);
        newSlotMinutes.value = '';
    }
};

// Remove evergreen slot
const removeSlot = (index) => {
    form.available_slots.splice(index, 1);
};

// Format minutes for display
const formatMinutes = (minutes) => {
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`;
    }
    return `${minutes} min`;
};

// Fetch next sessions preview
const fetchNextSessions = async () => {
    try {
        const response = await axios.get(route('webinars.auto.sessions', props.webinar.id));
        nextSessions.value = response.data.sessions || [];
    } catch (error) {
        console.error('Error fetching sessions:', error);
    }
};

// Load next sessions on mount
fetchNextSessions();
</script>

<template>
    <Head :title="$t('webinars.autoconfig.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('webinars.edit', webinar.id)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $t('webinars.autoconfig.title') }}: {{ webinar.name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Schedule Type -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.schedule_type') }}</h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label v-for="(label, value) in scheduleTypes" :key="value" class="cursor-pointer">
                            <input type="radio" v-model="form.schedule_type" :value="value" class="sr-only peer">
                            <div class="p-4 rounded-lg border-2 border-gray-200 dark:border-gray-600 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/20 text-center transition-all hover:bg-gray-50 dark:hover:bg-gray-700">
                                <p class="font-medium text-gray-900 dark:text-white">{{ label }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Timezone Settings -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.timezone') }}</h3>
                    <div class="max-w-xl">
                        <select
                            v-model="form.timezone"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">{{ $t('webinars.timezone_default') }} ({{ webinarTimezone || 'UTC' }})</option>
                            <option v-for="tz in timezones" :key="tz" :value="tz">
                                {{ tz }}
                            </option>
                        </select>
                        <p class="mt-2 text-sm text-gray-500">{{ $t('webinars.timezone_help') }}</p>
                    </div>
                </div>

                <!-- Recurring Schedule Settings -->
                <div v-if="form.schedule_type === 'recurring'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.recurring_settings') }}</h3>

                    <!-- Days of Week -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $t('webinars.autoconfig.days_of_week') }}</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(dayName, dayIndex) in daysOfWeek"
                                :key="dayIndex"
                                type="button"
                                @click="toggleDay(dayIndex)"
                                :class="[
                                    'px-4 py-2 rounded-lg font-medium transition-all',
                                    form.days_of_week.includes(dayIndex)
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ dayName }}
                            </button>
                        </div>
                    </div>

                    <!-- Times of Day -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            {{ $t('webinars.autoconfig.times_of_day') }}
                            <span class="text-gray-500 font-normal ml-2">{{ $t('webinars.autoconfig.times_hint') }}</span>
                        </label>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <div
                                v-for="(time, index) in form.times_of_day"
                                :key="index"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-200 rounded-lg"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ time }}
                                <button
                                    type="button"
                                    @click="removeTime(index)"
                                    class="ml-1 text-indigo-600 dark:text-indigo-400 hover:text-red-600 dark:hover:text-red-400"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <input
                                type="time"
                                v-model="newTime"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <button
                                type="button"
                                @click="addTime"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ $t('webinars.autoconfig.add_time') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Fixed Dates Settings -->
                <div v-if="form.schedule_type === 'fixed'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.fixed_dates') }}</h3>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <div
                            v-for="(dateTime, index) in form.fixed_dates"
                            :key="index"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 rounded-lg"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ dateTime }}
                            <button
                                type="button"
                                @click="removeFixedDate(index)"
                                class="ml-1 text-green-600 dark:text-green-400 hover:text-red-600 dark:hover:text-red-400"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <input
                            type="datetime-local"
                            v-model="newFixedDate"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <button
                            type="button"
                            @click="addFixedDate"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ $t('webinars.autoconfig.add_date') }}
                        </button>
                    </div>
                </div>

                <!-- On-Demand Settings -->
                <div v-if="form.schedule_type === 'on_demand'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.on_demand_settings') }}</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ $t('webinars.autoconfig.start_delay') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="number"
                                v-model="form.start_delay_minutes"
                                min="1"
                                max="120"
                                class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <span class="text-gray-600 dark:text-gray-400">{{ $t('webinars.autoconfig.minutes') }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">{{ $t('webinars.autoconfig.start_delay_hint') }}</p>
                    </div>
                </div>

                <!-- Evergreen Settings -->
                <div v-if="form.schedule_type === 'evergreen'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.evergreen_settings') }}</h3>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                            {{ $t('webinars.autoconfig.available_slots') }}
                        </label>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <div
                                v-for="(minutes, index) in form.available_slots"
                                :key="index"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200 rounded-lg"
                            >
                                {{ formatMinutes(minutes) }}
                                <button
                                    type="button"
                                    @click="removeSlot(index)"
                                    class="ml-1 text-purple-600 dark:text-purple-400 hover:text-red-600 dark:hover:text-red-400"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <input
                                type="number"
                                v-model="newSlotMinutes"
                                min="1"
                                placeholder="np. 15"
                                class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <span class="text-gray-600 dark:text-gray-400 self-center">min</span>
                            <button
                                type="button"
                                @click="addSlot"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ $t('webinars.autoconfig.add_slot') }}
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">{{ $t('webinars.autoconfig.evergreen_hint') }}</p>
                    </div>
                </div>

                <!-- Date Range & Limits -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.limits') }}</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('webinars.autoconfig.start_date') }}</label>
                            <input
                                type="date"
                                v-model="form.start_date"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('webinars.autoconfig.end_date') }}</label>
                            <input
                                type="date"
                                v-model="form.end_date"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('webinars.autoconfig.max_sessions_per_day') }}</label>
                            <input
                                type="number"
                                v-model="form.max_sessions_per_day"
                                min="1"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('webinars.autoconfig.max_attendees') }}</label>
                            <input
                                type="number"
                                v-model="form.max_attendees_per_session"
                                min="1"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div class="mt-4 flex items-center">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            id="is-active"
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        >
                        <label for="is-active" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            {{ $t('webinars.autoconfig.is_active') }}
                        </label>
                    </div>
                </div>

                <!-- Next Sessions Preview -->
                <div v-if="nextSessions.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.autoconfig.next_sessions') }}</h3>

                    <ul class="space-y-2">
                        <li v-for="(session, index) in nextSessions" :key="index" class="flex items-center gap-3 text-gray-600 dark:text-gray-300">
                            <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ session }}
                        </li>
                    </ul>
                </div>

                <!-- Save Button -->
                <div class="flex justify-end gap-4">
                    <Link
                        :href="route('webinars.edit', webinar.id)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                    >
                        {{ $t('webinars.autoconfig.cancel') }}
                    </Link>
                    <button
                        type="button"
                        @click="saveSchedule"
                        :disabled="saving"
                        class="inline-flex items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                    >
                        <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $t('webinars.autoconfig.save') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
