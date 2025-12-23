<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Bar } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

const { t } = useI18n();

const props = defineProps({
    data: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const activeMetric = ref('emails');

const metrics = computed(() => [
    { key: 'emails', label: t('dashboard.activity.sent'), color: '#4f46e5', bg: 'bg-indigo-500' }, // indigo-600
    { key: 'subscribers', label: t('dashboard.activity.subscribers'), color: '#059669', bg: 'bg-emerald-500' }, // emerald-600
    { key: 'opens', label: t('dashboard.activity.opens'), color: '#d97706', bg: 'bg-amber-500' } // amber-600
]);

const hasData = computed(() => {
    return props.data && props.data.some(d => d.emails > 0 || d.subscribers > 0 || d.opens > 0);
});

const currentMetricColor = computed(() => {
    return metrics.value.find(m => m.key === activeMetric.value)?.color || '#64748b';
});

const chartData = computed(() => {
    return {
        labels: props.data.map(d => d.label),
        datasets: [
            {
                label: metrics.value.find(m => m.key === activeMetric.value)?.label,
                backgroundColor: currentMetricColor.value,
                borderRadius: 4,
                data: props.data.map(d => d[activeMetric.value] || 0)
            }
        ]
    };
});

const chartOptions = computed(() => {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                titleFont: {
                    size: 13,
                    family: "'Inter', sans-serif"
                },
                bodyFont: {
                    size: 13,
                    family: "'Inter', sans-serif"
                },
                cornerRadius: 8,
                displayColors: false,
                callbacks: {
                    label: (context) => {
                         return `${context.dataset.label}: ${context.raw}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0', // slate-200
                    drawBorder: false,
                },
                ticks: {
                    font: {
                        family: "'Inter', sans-serif",
                        size: 11
                    },
                    color: '#64748b' // slate-500
                },
                border: {
                     display: false
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        family: "'Inter', sans-serif",
                        size: 11
                    },
                    color: '#64748b' // slate-500
                },
                border: {
                     display: false
                }
            }
        }
    };
});
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800/50">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t('dashboard.activity.title') }}
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $t('dashboard.activity.last_7_days') }}
                </p>
            </div>
            
            <!-- Metric selector -->
            <div v-if="hasData" class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-700/50">
                <button
                    v-for="metric in metrics"
                    :key="metric.key"
                    @click="activeMetric = metric.key"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-all duration-200"
                    :class="activeMetric === metric.key 
                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-600 dark:text-white' 
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                >
                    {{ metric.label }}
                </button>
            </div>
        </div>
        
        <!-- Loading State -->
        <div v-if="loading" class="flex h-64 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!hasData" class="flex flex-col items-center py-12 text-center h-64 justify-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700/50 mb-4">
                <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <h4 class="text-lg font-medium text-slate-900 dark:text-white mb-2">
                {{ $t('dashboard.activity.empty_title') }}
            </h4>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $t('dashboard.activity.empty_description') }}
            </p>
        </div>
        
        <!-- Chart -->
        <div v-else class="h-64 w-full">
             <Bar :data="chartData" :options="chartOptions" />
        </div>
        
        <!-- Legend (Custom for better styling) -->
        <div v-if="hasData && !loading" class="mt-4 flex items-center justify-center gap-6">
            <div 
                v-for="metric in metrics" 
                :key="metric.key"
                class="flex items-center gap-2 transition-opacity duration-200"
                :class="{ 'opacity-100': activeMetric === metric.key, 'opacity-50 grayscale hover:opacity-75 hover:grayscale-0 cursor-pointer': activeMetric !== metric.key }"
                @click="activeMetric = metric.key"
            >
                <span 
                    class="h-3 w-3 rounded-full"
                    :class="metric.bg"
                ></span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                    {{ metric.label }}
                </span>
            </div>
        </div>
    </div>
</template>

