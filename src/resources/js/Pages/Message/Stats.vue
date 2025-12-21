<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  BarElement,
  Title
} from 'chart.js';
import { Doughnut, Bar } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement, Title);

const { t } = useI18n();

const props = defineProps({
    message: Object,
    stats: Object,
    queue_stats: Object,
    recent_activity: Object,
});

const doughnutData = {
    labels: [t('messages.stats.charts.labels.opened'), t('messages.stats.charts.labels.sent_no_open')],
    datasets: [
        {
            backgroundColor: ['#10B981', '#E5E7EB'],
            data: [props.stats.unique_opens, props.stats.sent - props.stats.unique_opens],
        }
    ]
};

const barData = {
    labels: [t('messages.stats.charts.labels.sent'), t('messages.stats.charts.labels.unique_opens'), t('messages.stats.charts.labels.unique_clicks')],
    datasets: [
        {
            label: t('messages.stats.charts.labels.counters'),
            backgroundColor: '#4F46E5',
            data: [props.stats.sent, props.stats.unique_opens, props.stats.unique_clicks]
        }
    ]
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false
};
</script>

<template>
    <Head :title="$t('messages.stats.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $t('messages.stats.header_title', { subject: message.subject }) }}
                </h2>
                <Link :href="route('messages.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    &larr; {{ $t('messages.stats.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center">
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.sent }}</div>
                        <div class="text-sm text-gray-500 uppercase tracking-wide">{{ $t('messages.stats.kpi.sent') }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center">
                        <div class="text-3xl font-bold text-green-600">{{ stats.open_rate }}%</div>
                        <div class="text-sm text-gray-500 uppercase tracking-wide">{{ $t('messages.stats.kpi.open_rate') }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $t('messages.stats.kpi.unique', { count: stats.unique_opens }) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ stats.click_rate }}%</div>
                        <div class="text-sm text-gray-500 uppercase tracking-wide">{{ $t('messages.stats.kpi.click_rate') }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $t('messages.stats.kpi.unique', { count: stats.unique_clicks }) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center">
                        <div class="text-3xl font-bold text-purple-600">{{ stats.click_to_open_rate }}%</div>
                        <div class="text-sm text-gray-500 uppercase tracking-wide">{{ $t('messages.stats.kpi.ctor') }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $t('messages.stats.kpi.clicks_opens') }}</div>
                    </div>
                </div>

                <!-- Queue Progress (for autoresponder/queue messages) -->
                <div v-if="queue_stats && queue_stats.total > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                                {{ $t('messages.stats.queue.title') }}
                            </h3>
                            <span v-if="message.recipients_calculated_at" class="text-xs text-gray-400">
                                {{ $t('messages.stats.queue.last_sync') }}: {{ message.recipients_calculated_at }}
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex h-4 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                                <div 
                                    class="bg-green-500 transition-all duration-300" 
                                    :style="{ width: queue_stats.total > 0 ? (queue_stats.sent / queue_stats.total * 100) + '%' : '0%' }"
                                    :title="$t('messages.stats.queue.sent') + ': ' + queue_stats.sent"
                                ></div>
                                <div 
                                    class="bg-yellow-500 transition-all duration-300" 
                                    :style="{ width: queue_stats.total > 0 ? (queue_stats.queued / queue_stats.total * 100) + '%' : '0%' }"
                                    :title="$t('messages.stats.queue.queued') + ': ' + queue_stats.queued"
                                ></div>
                                <div 
                                    class="bg-blue-500 transition-all duration-300" 
                                    :style="{ width: queue_stats.total > 0 ? (queue_stats.planned / queue_stats.total * 100) + '%' : '0%' }"
                                    :title="$t('messages.stats.queue.planned') + ': ' + queue_stats.planned"
                                ></div>
                                <div 
                                    class="bg-red-500 transition-all duration-300" 
                                    :style="{ width: queue_stats.total > 0 ? (queue_stats.failed / queue_stats.total * 100) + '%' : '0%' }"
                                    :title="$t('messages.stats.queue.failed') + ': ' + queue_stats.failed"
                                ></div>
                                <div 
                                    class="bg-gray-400 transition-all duration-300" 
                                    :style="{ width: queue_stats.total > 0 ? (queue_stats.skipped / queue_stats.total * 100) + '%' : '0%' }"
                                    :title="$t('messages.stats.queue.skipped') + ': ' + queue_stats.skipped"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ queue_stats.planned }}</div>
                                <div class="text-xs text-blue-500 dark:text-blue-300 uppercase">{{ $t('messages.stats.queue.planned') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                                <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ queue_stats.queued }}</div>
                                <div class="text-xs text-yellow-500 dark:text-yellow-300 uppercase">{{ $t('messages.stats.queue.queued') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ queue_stats.sent }}</div>
                                <div class="text-xs text-green-500 dark:text-green-300 uppercase">{{ $t('messages.stats.queue.sent') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ queue_stats.failed }}</div>
                                <div class="text-xs text-red-500 dark:text-red-300 uppercase">{{ $t('messages.stats.queue.failed') }}</div>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                                <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ queue_stats.skipped }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-300 uppercase">{{ $t('messages.stats.queue.skipped') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow h-80">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">{{ $t('messages.stats.charts.effectiveness') }}</h3>
                        <Doughnut :data="doughnutData" :options="chartOptions" />
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow h-80">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">{{ $t('messages.stats.charts.conversion_funnel') }}</h3>
                        <Bar :data="barData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Recent Activity Logs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Opens Log -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">{{ $t('messages.stats.activity.recent_opens') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2">{{ $t('messages.stats.activity.email') }}</th>
                                        <th class="px-4 py-2">{{ $t('messages.stats.activity.time') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(log, i) in recent_activity.opens" :key="i" class="border-b dark:border-gray-700">
                                        <td class="px-4 py-2">{{ log.email }}</td>
                                        <td class="px-4 py-2">{{ log.time }}</td>
                                    </tr>
                                    <tr v-if="recent_activity.opens.length === 0">
                                        <td colspan="2" class="px-4 py-2 text-center">{{ $t('messages.stats.activity.no_data') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Clicks Log -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">{{ $t('messages.stats.activity.recent_clicks') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2">{{ $t('messages.stats.activity.email') }}</th>
                                        <th class="px-4 py-2">{{ $t('messages.stats.activity.url') }}</th>
                                        <th class="px-4 py-2">{{ $t('messages.stats.activity.time') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(log, i) in recent_activity.clicks" :key="i" class="border-b dark:border-gray-700">
                                        <td class="px-4 py-2">{{ log.email }}</td>
                                        <td class="px-4 py-2 truncate max-w-xs">{{ log.url }}</td>
                                        <td class="px-4 py-2">{{ log.time }}</td>
                                    </tr>
                                    <tr v-if="recent_activity.clicks.length === 0">
                                        <td colspan="3" class="px-4 py-2 text-center">{{ $t('messages.stats.activity.no_data') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
