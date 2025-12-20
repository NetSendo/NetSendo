<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    funnel: Object,
    stats: Object,
});

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
        end: 'bg-gray-500',
    };
    return colors[type] || 'bg-gray-500';
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                        </div>
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

            <!-- Step-by-step breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ t('funnels.stats.step_breakdown') }}
                    </h3>
                </div>
                
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div
                        v-for="(step, index) in stats.step_stats"
                        :key="step.id"
                        class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-750"
                    >
                        <!-- Step number -->
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-300">
                            {{ index + 1 }}
                        </div>
                        
                        <!-- Step icon -->
                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-xl', getStepColor(step.type)]">
                            {{ getStepIcon(step.type) }}
                        </div>
                        
                        <!-- Step info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ step.name }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ step.type }}
                            </p>
                        </div>
                        
                        <!-- At step -->
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ step.at_step }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('funnels.stats.at_step') }}</p>
                        </div>
                        
                        <!-- Completed -->
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ step.completed }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('funnels.stats.passed') }}</p>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="w-32">
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

            <!-- Funnel info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mt-8">
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
    </AuthenticatedLayout>
</template>
