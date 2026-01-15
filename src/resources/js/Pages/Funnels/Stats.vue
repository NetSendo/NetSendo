<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    funnel: Object,
    stats: Object,
});

const activeTab = ref('overview');

// Subscribers management
const subscribers = ref([]);
const subscribersPagination = ref({ current_page: 1, last_page: 1, total: 0 });
const subscribersLoading = ref(false);
const subscribersFilter = ref({ status: '', search: '' });
const selectedSubscriber = ref(null);
const showAdvanceModal = ref(false);
const advanceStepId = ref('');
const actionLoading = ref({});

const loadSubscribers = async (page = 1) => {
    subscribersLoading.value = true;
    try {
        const params = new URLSearchParams();
        params.append('page', page);
        if (subscribersFilter.value.status) params.append('status', subscribersFilter.value.status);
        if (subscribersFilter.value.search) params.append('search', subscribersFilter.value.search);

        const response = await axios.get(route('funnels.subscribers.api', props.funnel.id) + '?' + params.toString());
        subscribers.value = response.data.subscribers;
        subscribersPagination.value = response.data.pagination;
    } catch (error) {
        console.error('Error loading subscribers:', error);
    } finally {
        subscribersLoading.value = false;
    }
};

const pauseSubscriber = async (sub) => {
    actionLoading.value[sub.id] = true;
    try {
        await axios.post(route('funnels.subscribers.pause', [props.funnel.id, sub.id]));
        await loadSubscribers(subscribersPagination.value.current_page);
    } catch (error) {
        console.error('Error pausing subscriber:', error);
    } finally {
        actionLoading.value[sub.id] = false;
    }
};

const resumeSubscriber = async (sub) => {
    actionLoading.value[sub.id] = true;
    try {
        await axios.post(route('funnels.subscribers.resume', [props.funnel.id, sub.id]));
        await loadSubscribers(subscribersPagination.value.current_page);
    } catch (error) {
        console.error('Error resuming subscriber:', error);
    } finally {
        actionLoading.value[sub.id] = false;
    }
};

const openAdvanceModal = (sub) => {
    selectedSubscriber.value = sub;
    advanceStepId.value = '';
    showAdvanceModal.value = true;
};

const advanceSubscriber = async () => {
    if (!advanceStepId.value || !selectedSubscriber.value) return;
    actionLoading.value[selectedSubscriber.value.id] = true;
    try {
        await axios.post(route('funnels.subscribers.advance', [props.funnel.id, selectedSubscriber.value.id]), {
            step_id: advanceStepId.value
        });
        showAdvanceModal.value = false;
        await loadSubscribers(subscribersPagination.value.current_page);
    } catch (error) {
        console.error('Error advancing subscriber:', error);
    } finally {
        actionLoading.value[selectedSubscriber.value.id] = false;
    }
};

const removeSubscriber = async (sub) => {
    if (!confirm('Czy na pewno chcesz usunąć tego subskrybenta z lejka?')) return;
    actionLoading.value[sub.id] = true;
    try {
        await axios.delete(route('funnels.subscribers.remove', [props.funnel.id, sub.id]));
        await loadSubscribers(subscribersPagination.value.current_page);
    } catch (error) {
        console.error('Error removing subscriber:', error);
    } finally {
        actionLoading.value[sub.id] = false;
    }
};

const getStatusBadge = (status) => {
    const badges = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        waiting: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        exited: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return badges[status] || badges.exited;
};

const statusLabels = {
    active: 'Aktywny',
    waiting: 'Oczekuje',
    paused: 'Wstrzymany',
    completed: 'Ukończony',
    exited: 'Opuścił',
};

// Goals management
const goals = ref({ stats: {}, recent: [], by_step: [] });
const goalsLoading = ref(false);

const loadGoals = async () => {
    goalsLoading.value = true;
    try {
        const response = await axios.get(route('funnels.goals.stats', props.funnel.id));
        goals.value = response.data;
    } catch (error) {
        console.error('Error loading goals:', error);
    } finally {
        goalsLoading.value = false;
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value || 0);
};

const getGoalTypeIcon = (type) => {
    const icons = {
        purchase: '💳',
        signup: '✍️',
        page_visit: '👁️',
        tag_added: '🏷️',
        custom: '⚙️',
        webhook: '🔗',
    };
    return icons[type] || '🎯';
};

const completionRate = computed(() => {
    if (props.stats.total_subscribers === 0) return 0;
    return Math.round((props.stats.completed / props.stats.total_subscribers) * 100);
});

const getStepIcon = (type) => {
    const icons = {
        start: '🚀',
        email: '✉️',
        delay: '⏱️',
        condition: '🔀',
        action: '⚡',
        sms: '📱',
        wait_until: '📅',
        goal: '🏆',
        split: '🎯',
        end: '🏁',
    };
    return icons[type] || '📌';
};

const getStepColor = (type) => {
    const colors = {
        start: 'bg-green-500',
        email: 'bg-blue-500',
        delay: 'bg-yellow-500',
        condition: 'bg-purple-500',
        action: 'bg-orange-500',
        sms: 'bg-pink-500',
        wait_until: 'bg-teal-500',
        goal: 'bg-amber-500',
        split: 'bg-rose-500',
        end: 'bg-gray-500',
    };
    return colors[type] || 'bg-gray-500';
};

const formatTime = (minutes) => {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 1440) return `${Math.round(minutes / 60)}h`;
    return `${Math.round(minutes / 1440)}d`;
};

const getConversionColor = (rate) => {
    if (rate >= 80) return 'text-green-600 dark:text-green-400';
    if (rate >= 50) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};

const getDropOffColor = (dropOff, total) => {
    if (total === 0) return 'text-gray-500';
    const rate = (dropOff / total) * 100;
    if (rate > 30) return 'text-red-600 dark:text-red-400';
    if (rate > 15) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-gray-500 dark:text-gray-400';
};
</script>

<template>
    <Head :title="`${t('funnels.stats_title')} - ${funnel.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button
                        @click="router.visit(route('funnels.index'))"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ funnel.name }}
                    </h1>
                    <span :class="[
                        'px-3 py-1 rounded-full text-sm font-medium',
                        funnel.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                        funnel.status === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                    ]">
                        {{ funnel.status }}
                    </span>
                </div>
                <Link
                    :href="route('funnels.edit', funnel.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ t('funnels.edit_funnel') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <!-- Tab Navigation -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8">
                    <button
                        @click="activeTab = 'overview'"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'overview'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400'
                        ]"
                    >
                        {{ t('funnels.stats.overview') || 'Przegląd' }}
                    </button>
                    <button
                        @click="activeTab = 'steps'"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'steps'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400'
                        ]"
                    >
                        {{ t('funnels.stats.step_breakdown') || 'Kroki' }}
                    </button>
                    <button
                        v-if="stats.ab_tests && stats.ab_tests.length > 0"
                        @click="activeTab = 'abtests'"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'abtests'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400'
                        ]"
                    >
                        {{ t('funnels.stats.ab_tests') || 'Testy A/B' }}
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                            {{ stats.ab_tests.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'subscribers'; loadSubscribers()"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'subscribers'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400'
                        ]"
                    >
                        👥 {{ t('funnels.stats.subscribers') || 'Subskrybenci' }}
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ stats.total_subscribers }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'goals'; loadGoals()"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'goals'
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400'
                        ]"
                    >
                        🏆 {{ t('funnels.stats.goals') || 'Cele' }}
                    </button>
                </nav>
            </div>

            <!-- Overview Tab -->
            <div v-show="activeTab === 'overview'">
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.stats.total_subscribers') }}</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ stats.total_subscribers }}</p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.stats.active_subscribers') }}</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ stats.active_subscribers }}</p>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.stats.completed') }}</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ stats.completed }}</p>
                            </div>
                            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900/30">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.stats.completion_rate') }}</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ completionRate }}%</p>
                            </div>
                            <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Time Metrics -->
                <div v-if="stats.time_metrics" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        ⏱️ {{ t('funnels.stats.time_metrics') || 'Metryki czasowe' }}
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ formatTime(stats.time_metrics.avg_completion_time) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.avg_time') || 'Średni czas' }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                                {{ formatTime(stats.time_metrics.min_completion_time) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.min_time') || 'Najszybszy' }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ formatTime(stats.time_metrics.median_completion_time) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.median_time') || 'Mediana' }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">
                                {{ formatTime(stats.time_metrics.max_completion_time) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.max_time') || 'Najdłuższy' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Completion Progress -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ t('funnels.stats.progress') }}
                    </h3>
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold inline-block text-indigo-600 dark:text-indigo-400">
                                    {{ t('funnels.stats.completion_rate') }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-semibold inline-block text-indigo-600 dark:text-indigo-400">
                                    {{ completionRate }}%
                                </span>
                            </div>
                        </div>
                        <div class="overflow-hidden h-3 text-xs flex rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                            <div
                                :style="{ width: completionRate + '%' }"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-500"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Funnel info -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ t('funnels.stats.info') }}
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.builder.trigger_type') }}</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ funnel.trigger_type }}</dd>
                        </div>
                        <div v-if="funnel.trigger_list">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.builder.trigger_list') }}</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ funnel.trigger_list.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('funnels.stats.steps_count') }}</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ stats.steps_count }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Steps Tab -->
            <div v-show="activeTab === 'steps'">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ t('funnels.stats.step_breakdown') }}
                        </h3>
                    </div>

                    <!-- Table header -->
                    <div class="hidden md:grid grid-cols-8 gap-4 px-6 py-3 bg-gray-50 dark:bg-gray-750 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <div class="col-span-3">{{ t('funnels.stats.step') || 'Krok' }}</div>
                        <div class="text-center">{{ t('funnels.stats.at_step') || 'Na kroku' }}</div>
                        <div class="text-center">{{ t('funnels.stats.passed') || 'Przeszło' }}</div>
                        <div class="text-center">{{ t('funnels.stats.drop_off') || 'Odpady' }}</div>
                        <div class="text-center">{{ t('funnels.stats.conversion') || 'Konwersja' }}</div>
                        <div>{{ t('funnels.stats.progress') || 'Postęp' }}</div>
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div
                            v-for="(step, index) in stats.step_stats"
                            :key="step.id"
                            class="grid grid-cols-1 md:grid-cols-8 gap-4 items-center px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-750"
                        >
                            <!-- Step info (spans 3 cols) -->
                            <div class="col-span-3 flex items-center gap-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {{ index + 1 }}
                                </div>
                                <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-xl', getStepColor(step.type)]">
                                    {{ getStepIcon(step.type) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ step.name }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ step.type }}</p>
                                </div>
                            </div>

                            <!-- At step -->
                            <div class="text-center">
                                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ step.at_step }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">{{ t('funnels.stats.at_step') }}</p>
                            </div>

                            <!-- Completed -->
                            <div class="text-center">
                                <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ step.completed }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">{{ t('funnels.stats.passed') }}</p>
                            </div>

                            <!-- Drop-off -->
                            <div class="text-center">
                                <p :class="['text-xl font-bold', getDropOffColor(step.drop_off, stats.total_subscribers)]">
                                    {{ step.drop_off }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">{{ t('funnels.stats.drop_off') || 'Odpady' }}</p>
                            </div>

                            <!-- Conversion rate -->
                            <div class="text-center">
                                <p :class="['text-xl font-bold', getConversionColor(step.conversion_rate)]">
                                    {{ step.conversion_rate }}%
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">{{ t('funnels.stats.conversion') || 'Konwersja' }}</p>
                            </div>

                            <!-- Progress bar -->
                            <div class="w-full">
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div
                                        :style="{ width: (stats.total_subscribers > 0 ? (step.completed / stats.total_subscribers) * 100 : 0) + '%' }"
                                        class="h-full bg-green-500 transition-all duration-300"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!stats.step_stats || stats.step_stats.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ t('funnels.stats.no_data') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.no_data_desc') }}</p>
                    </div>
                </div>
            </div>

            <!-- A/B Tests Tab -->
            <div v-show="activeTab === 'abtests'" v-if="stats.ab_tests && stats.ab_tests.length > 0">
                <div class="space-y-6">
                    <div
                        v-for="test in stats.ab_tests"
                        :key="test.id"
                        class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                    >
                        <!-- Test header -->
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    🎯 {{ test.name }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('funnels.stats.metric') || 'Metryka' }}: {{ test.winning_metric }}
                                </p>
                            </div>
                            <span :class="[
                                'px-3 py-1 rounded-full text-sm font-medium',
                                test.status === 'running' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                test.status === 'completed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            ]">
                                {{ test.status }}
                            </span>
                        </div>

                        <!-- Test overview -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-750 grid grid-cols-2 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ test.total_enrollments }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.total_enrollments') || 'Uczestników' }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ test.overall_conversion_rate }}%</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.stats.overall_conversion') || 'Konwersja ogółem' }}</p>
                            </div>
                        </div>

                        <!-- Variants comparison -->
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div
                                v-for="variant in test.variants"
                                :key="variant.id"
                                :class="[
                                    'px-6 py-4 grid grid-cols-5 gap-4 items-center',
                                    variant.is_winner ? 'bg-green-50 dark:bg-green-900/10' : ''
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <span v-if="variant.is_winner" class="text-green-500">🏆</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ variant.name }}</span>
                                    <span class="text-sm text-gray-500">({{ variant.weight }}%)</span>
                                </div>
                                <div class="text-center">
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ variant.enrollments }}</p>
                                    <p class="text-xs text-gray-500">{{ t('funnels.stats.enrollments') || 'Zapisanych' }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ variant.conversions }}</p>
                                    <p class="text-xs text-gray-500">{{ t('funnels.stats.conversions') || 'Konwersji' }}</p>
                                </div>
                                <div class="text-center">
                                    <p :class="['text-xl font-bold', getConversionColor(variant.conversion_rate)]">
                                        {{ variant.conversion_rate }}%
                                    </p>
                                    <p class="text-xs text-gray-500">{{ t('funnels.stats.conversion_rate') || 'Konwersja' }}</p>
                                </div>
                                <div>
                                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div
                                            :style="{ width: variant.conversion_rate + '%' }"
                                            :class="[
                                                'h-full transition-all duration-300',
                                                variant.is_winner ? 'bg-green-500' : 'bg-indigo-500'
                                            ]"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscribers Tab -->
            <div v-show="activeTab === 'subscribers'">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <!-- Filters -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-4 items-center">
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="subscribersFilter.search"
                                @keyup.enter="loadSubscribers(1)"
                                type="text"
                                :placeholder="t('common.search') || 'Szukaj email...'"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <select
                            v-model="subscribersFilter.status"
                            @change="loadSubscribers(1)"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">{{ t('common.all_statuses') || 'Wszystkie statusy' }}</option>
                            <option value="active">Aktywny</option>
                            <option value="waiting">Oczekuje</option>
                            <option value="paused">Wstrzymany</option>
                            <option value="completed">Ukończony</option>
                            <option value="exited">Opuścił</option>
                        </select>
                        <button
                            @click="loadSubscribers(1)"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                        >
                            {{ t('common.filter') || 'Filtruj' }}
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="subscribersLoading" class="px-6 py-12 text-center">
                        <svg class="animate-spin mx-auto h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Ładowanie...</p>
                    </div>

                    <!-- Table -->
                    <div v-else-if="subscribers.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-750">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktualny krok</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ukończone</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Akcje</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="sub in subscribers" :key="sub.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ sub.email }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ sub.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusBadge(sub.status)]">
                                            {{ statusLabels[sub.status] || sub.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="sub.current_step" class="flex items-center gap-2">
                                            <span>{{ getStepIcon(sub.current_step.type) }}</span>
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ sub.current_step.name }}</span>
                                        </div>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ sub.steps_completed }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Pause/Resume -->
                                            <button
                                                v-if="sub.status === 'active' || sub.status === 'waiting'"
                                                @click="pauseSubscriber(sub)"
                                                :disabled="actionLoading[sub.id]"
                                                class="p-1.5 text-yellow-600 hover:bg-yellow-100 dark:hover:bg-yellow-900/30 rounded-lg transition-colors"
                                                title="Wstrzymaj"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                            <button
                                                v-if="sub.status === 'paused'"
                                                @click="resumeSubscriber(sub)"
                                                :disabled="actionLoading[sub.id]"
                                                class="p-1.5 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition-colors"
                                                title="Wznów"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            </button>
                                            <!-- Advance -->
                                            <button
                                                v-if="sub.status !== 'completed' && sub.status !== 'exited'"
                                                @click="openAdvanceModal(sub)"
                                                class="p-1.5 text-indigo-600 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 rounded-lg transition-colors"
                                                title="Przesuń do kroku"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                                            </button>
                                            <!-- Remove -->
                                            <button
                                                v-if="sub.status !== 'exited'"
                                                @click="removeSubscriber(sub)"
                                                :disabled="actionLoading[sub.id]"
                                                class="p-1.5 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                                title="Usuń z lejka"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Brak subskrybentów</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">W tym lejku nie ma jeszcze żadnych subskrybentów.</p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="subscribersPagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Strona {{ subscribersPagination.current_page }} z {{ subscribersPagination.last_page }} ({{ subscribersPagination.total }} subskrybentów)
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="loadSubscribers(subscribersPagination.current_page - 1)"
                                :disabled="subscribersPagination.current_page <= 1"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Poprzednia
                            </button>
                            <button
                                @click="loadSubscribers(subscribersPagination.current_page + 1)"
                                :disabled="subscribersPagination.current_page >= subscribersPagination.last_page"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Następna
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goals Tab -->
            <div v-show="activeTab === 'goals'">
                <!-- Loading -->
                <div v-if="goalsLoading" class="px-6 py-12 text-center">
                    <svg class="animate-spin mx-auto h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div v-else>
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Konwersje</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ goals.stats.total_conversions || 0 }}</p>
                                </div>
                                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                                    <span class="text-2xl">🎯</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Przychód</p>
                                    <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">{{ formatCurrency(goals.stats.total_revenue) }}</p>
                                </div>
                                <div class="p-3 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                    <span class="text-2xl">💰</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ostatnie 7 dni</p>
                                    <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ goals.stats.recent || 0 }}</p>
                                </div>
                                <div class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                    <span class="text-2xl">📈</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Goals by Step -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-8" v-if="goals.by_step && goals.by_step.length > 0">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Cele według kroków</h3>
                        </div>
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div v-for="goal in goals.by_step" :key="goal.step_id" class="px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">{{ getGoalTypeIcon(goal.goal_type) }}</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ goal.step_name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ goal.goal_type }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ goal.count }} konwersji</p>
                                    <p class="text-sm text-green-600 dark:text-green-400">{{ formatCurrency(goal.revenue) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Conversions -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ostatnie konwersje</h3>
                        </div>
                        <div v-if="goals.recent && goals.recent.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div v-for="conv in goals.recent" :key="conv.id" class="px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">{{ getGoalTypeIcon(conv.goal_type) }}</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ conv.email }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ conv.goal_name }} • {{ conv.step_name }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p v-if="conv.value > 0" class="font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(conv.value) }}</p>
                                    <p class="text-xs text-gray-400">{{ new Date(conv.converted_at).toLocaleString('pl-PL') }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-6 py-12 text-center">
                            <span class="text-4xl">🏆</span>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Brak konwersji</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">W tym lejku nie ma jeszcze żadnych konwersji celów.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advance Modal -->
            <Teleport to="body">
                <div v-if="showAdvanceModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="fixed inset-0 bg-black/50" @click="showAdvanceModal = false"></div>
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                Przesuń do kroku
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                Wybierz krok, do którego chcesz przenieść subskrybenta {{ selectedSubscriber?.email }}
                            </p>
                            <select
                                v-model="advanceStepId"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 mb-4"
                            >
                                <option value="">-- Wybierz krok --</option>
                                <option v-for="step in funnel.steps" :key="step.id" :value="step.id">
                                    {{ getStepIcon(step.type) }} {{ step.name }}
                                </option>
                            </select>
                            <div class="flex justify-end gap-3">
                                <button
                                    @click="showAdvanceModal = false"
                                    class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                >
                                    Anuluj
                                </button>
                                <button
                                    @click="advanceSubscriber"
                                    :disabled="!advanceStepId || actionLoading[selectedSubscriber?.id]"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors disabled:opacity-50"
                                >
                                    Przesuń
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>
