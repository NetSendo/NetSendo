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
