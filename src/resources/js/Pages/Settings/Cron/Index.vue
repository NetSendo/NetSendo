<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    settings: Object,
    stats: Object,
    recentLogs: Array,
    listsAllowedNow: Array,
    isDispatchAllowed: Boolean,
});

const days = [
    { key: 'monday', label: t('cron.days.monday') },
    { key: 'tuesday', label: t('cron.days.tuesday') },
    { key: 'wednesday', label: t('cron.days.wednesday') },
    { key: 'thursday', label: t('cron.days.thursday') },
    { key: 'friday', label: t('cron.days.friday') },
    { key: 'saturday', label: t('cron.days.saturday') },
    { key: 'sunday', label: t('cron.days.sunday') },
];

const form = useForm({
    volume_per_minute: props.settings?.volume_per_minute || 100,
    daily_maintenance_hour: props.settings?.daily_maintenance_hour || 4,
    schedule: props.settings?.schedule || getDefaultSchedule(),
});

function getDefaultSchedule() {
    const schedule = {};
    days.forEach(day => {
        schedule[day.key] = { enabled: true, start: 0, end: 1440 };
    });
    return schedule;
}

// Pomocnicze funkcje do formatowania czasu
function minutesToTime(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
}

function timeToMinutes(time) {
    const [hours, mins] = time.split(':').map(Number);
    return hours * 60 + mins;
}

function formatDuration(seconds) {
    if (!seconds) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const { locale } = useI18n();
    return new Date(dateString).toLocaleString(locale.value);
}

const activeTab = ref('settings');

// Webhook settings
const webhookSettings = ref({
    has_token: false,
    token: null,
    webhook_url: '',
});
const webhookLoading = ref(false);
const tokenVisible = ref(false);
const copySuccess = ref(false);

async function fetchWebhookSettings() {
    try {
        const response = await fetch(route('settings.cron.webhook'));
        if (response.ok) {
            webhookSettings.value = await response.json();
        }
    } catch (e) {
        // Ignore
    }
}

async function generateToken() {
    webhookLoading.value = true;
    try {
        const response = await fetch(route('settings.cron.webhook.generate'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            const data = await response.json();
            webhookSettings.value = {
                has_token: true,
                token: data.token,
                webhook_url: data.webhook_url,
            };
            tokenVisible.value = true;
        }
    } catch (e) {
        // Ignore
    } finally {
        webhookLoading.value = false;
    }
}

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        copySuccess.value = true;
        setTimeout(() => copySuccess.value = false, 2000);
    } catch (e) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copySuccess.value = true;
        setTimeout(() => copySuccess.value = false, 2000);
    }
}

// Check for tab query parameter on mount
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['settings', 'stats', 'logs', 'instructions'].includes(tab)) {
        activeTab.value = tab;
    }
    
    // Load webhook settings
    fetchWebhookSettings();
});

function submit() {
    form.post(route('settings.cron.store'), {
        preserveScroll: true,
    });
}

function refreshStats() {
    router.reload({ only: ['stats', 'recentLogs', 'listsAllowedNow', 'isDispatchAllowed'] });
}

const statusColors = {
    running: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>
    <Head :title="t('cron.title', 'Ustawienia CRON')" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    ⏰ {{ t('cron.title', 'Ustawienia CRON') }}
                </h2>
                <div class="flex items-center gap-3">
                    <!-- Status indicator -->
                    <span :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        isDispatchAllowed 
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                    ]">
                        {{ isDispatchAllowed ? '🟢 ' + t('cron.status.active') : '🟡 ' + t('cron.status.paused') }}
                    </span>
                    <button @click="refreshStats" class="btn btn-outline btn-sm">
                        🔄 {{ t('cron.actions.refresh') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                            <button
                                @click="activeTab = 'settings'"
                                :class="[
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                    activeTab === 'settings'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                ]"
                            >
                                ⚙️ {{ t('cron.tabs.settings') }}
                            </button>
                            <button
                                @click="activeTab = 'stats'"
                                :class="[
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                    activeTab === 'stats'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                ]"
                            >
                                📊 {{ t('cron.tabs.stats') }}
                            </button>
                            <button
                                @click="activeTab = 'logs'"
                                :class="[
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                    activeTab === 'logs'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                ]"
                            >
                                📋 {{ t('cron.tabs.logs') }}
                            </button>
                            <button
                                @click="activeTab = 'instructions'"
                                :class="[
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                    activeTab === 'instructions'
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                ]"
                            >
                                📖 {{ t('cron.tabs.instructions') }}
                            </button>
                    </nav>
                </div>

                <!-- Settings Tab -->
                <div v-if="activeTab === 'settings'" class="space-y-6">
                    <form @submit.prevent="submit">
                        <!-- Podstawowe ustawienia -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                📧 {{ t('cron.settings.title') }}
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ t('cron.settings.volume_per_minute') }}
                                    </label>
                                    <input 
                                        type="number" 
                                        v-model="form.volume_per_minute"
                                        min="1"
                                        max="10000"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('cron.settings.volume_help') }}
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ t('cron.settings.maintenance_hour') }}
                                    </label>
                                    <select 
                                        v-model="form.daily_maintenance_hour"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option v-for="h in 24" :key="h-1" :value="h-1">
                                            {{ (h-1).toString().padStart(2, '0') }}:00
                                        </option>
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ t('cron.settings.maintenance_help') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Harmonogram tygodniowy -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                📅 {{ t('cron.schedule.title') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                {{ t('cron.schedule.description') }}
                            </p>

                            <div class="space-y-4">
                                <div 
                                    v-for="day in days" 
                                    :key="day.key"
                                    class="flex flex-wrap items-center gap-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                                >
                                    <div class="w-32">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                v-model="form.schedule[day.key].enabled"
                                                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                            />
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ day.label }}
                                            </span>
                                        </label>
                                    </div>

                                    <div 
                                        v-if="form.schedule[day.key].enabled"
                                        class="flex items-center gap-4 flex-1"
                                    >
                                        <div class="flex items-center gap-2">
                                            <label class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.schedule.from') }}:</label>
                                            <input 
                                                type="time" 
                                                :value="minutesToTime(form.schedule[day.key].start)"
                                                @input="form.schedule[day.key].start = timeToMinutes($event.target.value)"
                                                class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <label class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.schedule.to') }}:</label>
                                            <input 
                                                type="time" 
                                                :value="minutesToTime(form.schedule[day.key].end)"
                                                @input="form.schedule[day.key].end = timeToMinutes($event.target.value)"
                                                class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>
                                        
                                        <div class="flex gap-1">
                                            <button 
                                                type="button"
                                                @click="form.schedule[day.key] = { enabled: true, start: 0, end: 1440 }"
                                                class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500"
                                            >
                                                {{ t('cron.schedule.preset_24h') }}
                                            </button>
                                            <button 
                                                type="button"
                                                @click="form.schedule[day.key] = { enabled: true, start: 480, end: 1020 }"
                                                class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500"
                                            >
                                                {{ t('cron.schedule.preset_business') }}
                                            </button>
                                            <button 
                                                type="button"
                                                @click="form.schedule[day.key] = { enabled: true, start: 540, end: 1080 }"
                                                class="px-2 py-1 text-xs bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500"
                                            >
                                                {{ t('cron.schedule.preset_office') }}
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div 
                                        v-else
                                        class="flex-1 text-sm text-gray-400 dark:text-gray-500 italic"
                                    >
                                        {{ t('cron.schedule.day_disabled') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end">
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                {{ form.processing ? t('cron.actions.saving') : '💾 ' + t('cron.actions.save') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Tab -->
                <div v-if="activeTab === 'stats'" class="space-y-6">
                    <!-- Statystyki 24h -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {{ stats.total_runs || 0 }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.stats.total_runs') }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ stats.successful || 0 }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.stats.successful') }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                {{ stats.failed || 0 }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.stats.failed') }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ stats.emails_sent || 0 }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.stats.emails_sent') }}</div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                            <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                                {{ formatDuration(stats.avg_duration) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('cron.stats.avg_duration') }}</div>
                        </div>
                    </div>

                    <!-- Listy dozwolone teraz -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            📋 {{ t('cron.lists.allowed_now') }}
                        </h3>
                        
                        <div v-if="listsAllowedNow.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div 
                                v-for="list in listsAllowedNow" 
                                :key="list.id"
                                class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg"
                            >
                                <span class="font-medium text-gray-900 dark:text-white">{{ list.name }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ list.volume_per_minute }}/min
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            {{ t('cron.lists.no_active') }}
                        </div>
                    </div>

                    <!-- Informacje o konfiguracji -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            ℹ️ {{ t('cron.stats.info_title') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.stats.last_run') }}:</span>
                                <span class="font-medium dark:text-white">{{ formatDateTime(settings.last_run) }}</span>
                            </div>
                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.stats.last_daily_run') }}:</span>
                                <span class="font-medium dark:text-white">{{ formatDateTime(settings.last_daily_run) }}</span>
                            </div>
                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.stats.global_limit') }}:</span>
                                <span class="font-medium dark:text-white">{{ settings.volume_per_minute }}/min</span>
                            </div>
                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.stats.maintenance_at') }}:</span>
                                <span class="font-medium dark:text-white">{{ settings.daily_maintenance_hour }}:00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div v-if="activeTab === 'logs'">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                📋 {{ t('cron.logs.title') }}
                            </h3>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.job_name') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.status') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.started_at') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.duration') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.emails_sent') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                            {{ t('cron.logs.emails_failed') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="log in recentLogs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ log.job_name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 text-xs rounded-full', statusColors[log.status]]">
                                                {{ t('cron.status.' + log.status, log.status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDateTime(log.started_at) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDuration(log.duration) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">
                                            {{ log.emails_sent }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">
                                            {{ log.emails_failed }}
                                        </td>
                                    </tr>
                                    <tr v-if="!recentLogs || recentLogs.length === 0">
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            {{ t('cron.logs.no_logs') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Instructions Tab -->
                <div v-if="activeTab === 'instructions'" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ t('cron.instructions.importance_title') }}</p>
                                <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                                    {{ t('cron.instructions.importance_text') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Docker (Development) -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            🐳 {{ t('cron.instructions.docker_title') }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            {{ t('cron.instructions.docker_text') }}
                        </p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto">
                            <pre># {{ t('cron.instructions.docker_manual') }}
docker compose exec -u dev app php artisan schedule:run

# {{ t('cron.instructions.docker_list') }}
docker compose exec -u dev app php artisan schedule:list</pre>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            🐧 {{ t('cron.instructions.linux_title') }}
                        </h3>
                        <i18n-t keypath="cron.instructions.linux_text" tag="p" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <template #command>
                                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">crontab -e</code>
                            </template>
                        </i18n-t>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto">
                            <pre>* * * * * cd /ścieżka-do-projektu && php artisan schedule:run >> /dev/null 2>&1</pre>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            ⚠️ {{ t('cron.instructions.linux_warning', { path: '/ścieżka-do-projektu' }) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            ⚙️ {{ t('cron.instructions.supervisor_title') }}
                        </h3>
                        <i18n-t keypath="cron.instructions.supervisor_text" tag="p" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <template #file>
                                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">/etc/supervisor/conf.d/netsendo-scheduler.conf</code>
                            </template>
                        </i18n-t>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto">
                            <pre>[program:netsendo-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php /ścieżka/artisan schedule:run; sleep 60; done"
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/netsendo-scheduler.log</pre>
                        </div>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto mt-3">
                            <pre># {{ t('cron.instructions.supervisor_after') }}
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start netsendo-scheduler</pre>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            🖥️ {{ t('cron.instructions.cpanel_title') }}
                        </h3>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-decimal list-inside">
                            <li>{{ t('cron.instructions.cpanel_step1') }}</li>
                            <li>{{ t('cron.instructions.cpanel_step2', { section: 'Cron Jobs' }) }}</li>
                            <li>{{ t('cron.instructions.cpanel_step3') }}</li>
                        </ol>
                        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 mt-3">
                            <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.instructions.cpanel_minute') }}:</span>
                                <span class="font-mono">*</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.instructions.cpanel_hour') }}:</span>
                                <span class="font-mono">*</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.instructions.cpanel_day') }}:</span>
                                <span class="font-mono">*</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.instructions.cpanel_month') }}:</span>
                                <span class="font-mono">*</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ t('cron.instructions.cpanel_weekday') }}:</span>
                                <span class="font-mono">*</span>
                            </div>
                            <div class="bg-gray-900 rounded p-2 font-mono text-xs text-green-400">
                                cd /home/user/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
                            </div>
                        </div>
                    </div>

                    <!-- Automation Tools (n8n, Make, Zapier) -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="mb-4">
                            <!-- Logo - dark mode uses light version, light mode uses dark version -->
                            <img 
                                src="/images/integrations/n8n-make-zapier-dark.png" 
                                alt="n8n, Make, Zapier" 
                                class="h-16 object-contain dark:hidden"
                            />
                            <img 
                                src="/images/integrations/n8n-make-zapier-light.png" 
                                alt="n8n, Make, Zapier" 
                                class="h-16 object-contain hidden dark:block"
                            />
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                            🔗 {{ t('cron.instructions.automation_title') }}
                        </h3>
                        <i18n-t keypath="cron.instructions.automation_text" tag="p" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <template #bold>
                                <strong>Webhook / API</strong>
                            </template>
                        </i18n-t>
                        
                        <!-- Webhook Configuration -->
                        <div class="space-y-4">
                            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                                    {{ t('cron.webhook.title') }}
                                </h4>
                                
                                <!-- Token not generated -->
                                <div v-if="!settings.webhook_token" class="text-center py-6 bg-gray-50 dark:bg-gray-700/50 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                        {{ t('cron.webhook.no_token') }}
                                    </p>
                                    <button 
                                        @click="generateToken" 
                                        :disabled="webhookLoading"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-50 transition-colors font-medium"
                                    >
                                        {{ webhookLoading ? t('cron.webhook.generating') : t('cron.webhook.generate') }}
                                    </button>
                                </div>
                            
                            <!-- Token generated -->
                            <div v-else class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
                                            {{ t('cron.webhook.url_label') }}
                                        </label>
                                        <div class="flex gap-2">
                                            <input 
                                                readonly 
                                                :value="webhookSettings.webhook_url"
                                                class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm font-mono dark:text-white"
                                            />
                                            <button @click="copyToClipboard(webhookSettings.webhook_url)" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500">📋</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">
                                            {{ t('cron.webhook.token_label') }}
                                        </label>
                                        <div class="flex gap-2">
                                            <input 
                                                readonly 
                                                :value="settings.webhook_token"
                                                class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-sm font-mono dark:text-white"
                                            />
                                            <button @click="copyToClipboard(settings.webhook_token)" class="px-3 py-2 bg-gray-200 dark:bg-gray-600 rounded hover:bg-gray-300 dark:hover:bg-gray-500">📋</button>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 pt-2">
                                        <button 
                                            @click="generateToken" 
                                            :disabled="webhookLoading"
                                            class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            {{ webhookLoading ? t('cron.webhook.generating') : t('cron.webhook.generate_new') }}
                                        </button>
                                        <span class="text-xs text-gray-400 italic">
                                            {{ t('cron.webhook.token_warning') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="space-y-3 text-sm">
                            <div class="border-l-4 border-indigo-500 pl-3">
                                <strong class="text-gray-900 dark:text-white">n8n:</strong>
                                <i18n-t keypath="cron.instructions.n8n_note" tag="p" class="text-gray-600 dark:text-gray-400">
                                    <template #node>
                                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">HTTP Request</code>
                                    </template>
                                    <template #trigger>
                                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Cron</code>
                                    </template>
                                </i18n-t>
                            </div>
                            <div class="border-l-4 border-purple-500 pl-3">
                                <strong class="text-gray-900 dark:text-white">Make (Integromat):</strong>
                                <i18n-t keypath="cron.instructions.make_note" tag="p" class="text-gray-600 dark:text-gray-400">
                                    <template #module>
                                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">HTTP > Make a request</code>
                                    </template>
                                </i18n-t>
                            </div>
                            <div class="border-l-4 border-orange-500 pl-3">
                                <strong class="text-gray-900 dark:text-white">Zapier:</strong>
                                <i18n-t keypath="cron.instructions.zapier_note" tag="p" class="text-gray-600 dark:text-gray-400">
                                    <template #trigger>
                                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Schedule</code>
                                    </template>
                                    <template #action>
                                        <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">Webhooks by Zapier</code>
                                    </template>
                                </i18n-t>
                            </div>
                        </div>
                        
                        <!-- Example Request -->
                        <div class="mt-4 bg-gray-900 rounded-lg p-4 font-mono text-sm text-green-400 overflow-x-auto">
                            <pre># cURL example
curl -X POST "{{ webhookSettings.webhook_url }}" \
     -H "X-Cron-Token: YOUR_TOKEN"

# Or with query parameter
curl "{{ webhookSettings.webhook_url }}?token=YOUR_TOKEN"</pre>
                        </div>
                    </div>

                    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-indigo-900 dark:text-indigo-200 mb-4 flex items-center gap-2">
                            🚀 {{ t('cron.instructions.forge_title') }}
                        </h3>
                        <div class="space-y-4 text-sm text-indigo-700 dark:text-indigo-300">
                            <p>
                                <strong>Laravel Forge:</strong> {{ t('cron.instructions.forge_text') }}
                            </p>
                            <p>
                                <strong>Laravel Vapor:</strong> {{ t('cron.instructions.vapor_text') }}
                            </p>
                        </div>
                    </div>

                    <!-- Verification -->
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-green-900 dark:text-green-200 mb-4 flex items-center gap-2">
                            ✅ {{ t('cron.verification.title') }}
                        </h3>
                        <ul class="space-y-3 text-sm text-green-700 dark:text-green-300">
                            <li class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">1.</span>
                                <span>{{ t('cron.verification.step1') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">2.</span>
                                <span>{{ t('cron.verification.step2') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">3.</span>
                                <span>{{ t('cron.verification.step3') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">4.</span>
                                <span>{{ t('cron.verification.step4') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.btn {
    @apply px-3 py-1.5 rounded font-medium text-sm transition-colors;
}
.btn-outline {
    @apply border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700;
}
.btn-sm {
    @apply px-2 py-1 text-xs;
}
</style>
