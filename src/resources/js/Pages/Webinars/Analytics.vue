<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { useDateTime } from '@/Composables/useDateTime';

const { formatNumber: formatNumberBase } = useDateTime();
const props = defineProps({
    webinar: Object,
    stats: Object,
    funnel: Object,
    timeline: Array,
    devices: Object,
});

const formatNumber = (num) => {
    if (!num) return '0';
    return formatNumberBase(num);
};

const formatPercent = (value) => {
    if (!value) return '0%';
    return `${Math.round(value * 100) / 100}%`;
};

const funnelSteps = computed(() => {
    if (!props.funnel) return [];
    return [
        { label: 'webinars.analytics.funnel.registrations', value: props.funnel.registrations || 0, color: 'bg-blue-500' },
        { label: 'webinars.analytics.funnel.attended', value: props.funnel.attended || 0, color: 'bg-green-500' },
        { label: 'webinars.analytics.funnel.engaged', value: props.funnel.engaged || 0, color: 'bg-purple-500' },
        { label: 'webinars.analytics.funnel.converted', value: props.funnel.converted || 0, color: 'bg-indigo-500' },
    ];
});

const maxFunnelValue = computed(() => {
    if (!props.funnel) return 1;
    return Math.max(props.funnel.registrations || 1, 1);
});

const deviceData = computed(() => {
    if (!props.devices) return [];
    return Object.entries(props.devices).map(([name, count]) => ({
        name,
        count,
        percentage: props.stats?.total_views ? (count / props.stats.total_views * 100).toFixed(1) : 0
    }));
});
</script>

<template>
    <Head :title="$t('webinars.analytics.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('webinars.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $t('webinars.analytics.title') }}: {{ webinar.name }}
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('webinars.edit', webinar.id)"
                        class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        {{ $t('webinars.analytics.edit') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.analytics.total_registrations') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(stats?.registrations) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.analytics.total_attended') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(stats?.attended) }}</div>
                        <div class="mt-1 text-sm text-gray-500">{{ formatPercent(stats?.attendance_rate) }} {{ $t('webinars.analytics.attendance_rate') }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.analytics.peak_viewers') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ formatNumber(stats?.peak_viewers) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.analytics.avg_watch_time') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats?.avg_watch_time || '0' }} min</div>
                    </div>
                </div>

                <!-- Conversion Funnel -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6">{{ $t('webinars.analytics.conversion_funnel') }}</h3>
                    <div class="space-y-4">
                        <div v-for="(step, index) in funnelSteps" :key="index" class="relative">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t(step.label) }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatNumber(step.value) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                <div
                                    :class="[step.color, 'h-4 rounded-full transition-all duration-500']"
                                    :style="{ width: `${(step.value / maxFunnelValue * 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Engagement Timeline -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.analytics.engagement_timeline') }}</h3>
                        <div v-if="timeline && timeline.length > 0" class="space-y-2">
                            <div v-for="(point, index) in timeline" :key="index" class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 dark:text-gray-400 w-16">{{ point.time }}</span>
                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div
                                        class="bg-indigo-500 h-2 rounded-full"
                                        :style="{ width: `${point.percentage}%` }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 w-12 text-right">{{ point.viewers }}</span>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                            {{ $t('webinars.analytics.no_timeline_data') }}
                        </div>
                    </div>

                    <!-- Device Breakdown -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.analytics.device_breakdown') }}</h3>
                        <div v-if="deviceData.length > 0" class="space-y-4">
                            <div v-for="device in deviceData" :key="device.name" class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg v-if="device.name === 'desktop'" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <svg v-else-if="device.name === 'mobile'" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ device.name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatNumber(device.count) }}</span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ device.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                            {{ $t('webinars.analytics.no_device_data') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
