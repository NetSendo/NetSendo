<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

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

const chartData = computed(() => props.data);

const hasData = computed(() => {
    return chartData.value.some(d => d.emails > 0 || d.subscribers > 0 || d.opens > 0);
});

const maxValue = computed(() => {
    if (!hasData.value) return 1;
    return Math.max(...chartData.value.map(d => Math.max(d.emails || 0, d.subscribers || 0, d.opens || 0)));
});

const getHeight = (value) => {
    if (!value || maxValue.value === 0) return 0;
    return (value / maxValue.value) * 100;
};

const activeMetric = ref('emails');

const metrics = computed(() => [
    { key: 'emails', label: t('dashboard.activity.sent'), color: 'indigo' },
    { key: 'subscribers', label: t('dashboard.activity.subscribers'), color: 'emerald' },
    { key: 'opens', label: t('dashboard.activity.opens'), color: 'amber' }
]);

const colorClasses = {
    emails: 'bg-gradient-to-t from-indigo-600 to-indigo-400',
    subscribers: 'bg-gradient-to-t from-emerald-600 to-emerald-400',
    opens: 'bg-gradient-to-t from-amber-600 to-amber-400'
};
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800/50">
        <div class="mb-6 flex items-center justify-between">
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
        <div v-if="loading" class="flex h-48 items-end justify-between gap-2">
            <div v-for="i in 7" :key="i" class="flex flex-1 flex-col items-center">
                <div 
                    class="w-full max-w-8 rounded-t-lg bg-slate-200 dark:bg-slate-700 animate-pulse"
                    :style="{ height: (30 + Math.random() * 50) + '%' }"
                ></div>
                <div class="mt-2 h-3 w-8 bg-slate-200 dark:bg-slate-700 rounded animate-pulse"></div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!hasData" class="flex flex-col items-center py-8 text-center">
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
        <div v-else class="flex h-48 items-end justify-between gap-2">
            <div 
                v-for="(day, index) in chartData" 
                :key="index"
                class="group relative flex flex-1 flex-col items-center"
            >
                <!-- Bar -->
                <div 
                    class="relative w-full max-w-8 overflow-hidden rounded-t-lg transition-all duration-500"
                    :class="colorClasses[activeMetric]"
                    :style="{ height: getHeight(day[activeMetric]) + '%', minHeight: day[activeMetric] > 0 ? '4px' : '0' }"
                >
                    <!-- Tooltip -->
                    <div class="pointer-events-none absolute -top-10 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-800 px-2 py-1 text-xs font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
                        {{ day[activeMetric] || 0 }}
                    </div>
                    
                    <!-- Shine effect -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                </div>
                
                <!-- Label -->
                <span class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ day.label }}
                </span>
            </div>
        </div>
        
        <!-- Legend -->
        <div v-if="hasData" class="mt-6 flex items-center justify-center gap-6">
            <div 
                v-for="metric in metrics" 
                :key="metric.key"
                class="flex items-center gap-2"
            >
                <span 
                    class="h-3 w-3 rounded-full"
                    :class="{
                        'bg-indigo-500': metric.key === 'emails',
                        'bg-emerald-500': metric.key === 'subscribers',
                        'bg-amber-500': metric.key === 'opens'
                    }"
                ></span>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    {{ metric.label }}
                </span>
            </div>
        </div>
    </div>
</template>

