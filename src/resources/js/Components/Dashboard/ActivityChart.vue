<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    data: {
        type: Array,
        default: () => []
    }
});

// Sample data if not provided
const chartData = computed(() => {
    if (props.data.length > 0) return props.data;
    
    // Generate sample data for last 7 days
    const days = [];
    const now = new Date();
    for (let i = 6; i >= 0; i--) {
        const date = new Date(now);
        date.setDate(now.getDate() - i);
        days.push({
            label: date.toLocaleDateString(locale.value, { weekday: 'short' }),
            emails: Math.floor(Math.random() * 300) + 100,
            subscribers: Math.floor(Math.random() * 80) + 10,
            opens: Math.floor(Math.random() * 200) + 50
        });
    }
    return days;
});

const maxValue = computed(() => {
    return Math.max(...chartData.value.map(d => Math.max(d.emails, d.subscribers, d.opens)));
});

const getHeight = (value) => {
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
            <div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-700/50">
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
        
        <!-- Chart -->
        <div class="flex h-48 items-end justify-between gap-2">
            <div 
                v-for="(day, index) in chartData" 
                :key="index"
                class="group relative flex flex-1 flex-col items-center"
            >
                <!-- Bar -->
                <div 
                    class="relative w-full max-w-8 overflow-hidden rounded-t-lg transition-all duration-500"
                    :class="colorClasses[activeMetric]"
                    :style="{ height: getHeight(day[activeMetric]) + '%' }"
                >
                    <!-- Tooltip -->
                    <div class="pointer-events-none absolute -top-10 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-800 px-2 py-1 text-xs font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
                        {{ day[activeMetric] }}
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
        <div class="mt-6 flex items-center justify-center gap-6">
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
