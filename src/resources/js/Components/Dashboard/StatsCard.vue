<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true
    },
    value: {
        type: [String, Number],
        required: true
    },
    trend: {
        type: Number,
        default: null
    },
    trendLabel: {
        type: String,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    },
    color: {
        type: String,
        default: 'indigo',
        validator: (value) => ['indigo', 'emerald', 'amber', 'rose', 'purple', 'cyan'].includes(value)
    }
});

const colorClasses = {
    indigo: {
        bg: 'bg-indigo-500/10',
        text: 'text-indigo-500',
        icon: 'bg-gradient-to-br from-indigo-500 to-purple-600'
    },
    emerald: {
        bg: 'bg-emerald-500/10',
        text: 'text-emerald-500',
        icon: 'bg-gradient-to-br from-emerald-500 to-teal-600'
    },
    amber: {
        bg: 'bg-amber-500/10',
        text: 'text-amber-500',
        icon: 'bg-gradient-to-br from-amber-500 to-orange-600'
    },
    rose: {
        bg: 'bg-rose-500/10',
        text: 'text-rose-500',
        icon: 'bg-gradient-to-br from-rose-500 to-pink-600'
    },
    purple: {
        bg: 'bg-purple-500/10',
        text: 'text-purple-500',
        icon: 'bg-gradient-to-br from-purple-500 to-violet-600'
    },
    cyan: {
        bg: 'bg-cyan-500/10',
        text: 'text-cyan-500',
        icon: 'bg-gradient-to-br from-cyan-500 to-blue-600'
    }
};
</script>

<template>
    <div class="group relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-lg dark:bg-slate-800/50 dark:hover:bg-slate-800">
        <!-- Background decoration -->
        <div 
            class="absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-50 blur-2xl transition-all duration-300 group-hover:opacity-70"
            :class="colorClasses[color].bg"
        ></div>
        
        <div class="relative flex items-start justify-between">
            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                    {{ title }}
                </p>
                
                <div v-if="loading" class="h-9 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                <p v-else class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ value }}
                </p>
                
                <div v-if="trend !== null" class="flex items-center gap-2">
                    <span 
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="trend >= 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'"
                    >
                        <svg 
                            class="h-3 w-3" 
                            :class="trend >= 0 ? '' : 'rotate-180'"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                        </svg>
                        {{ Math.abs(trend) }}%
                    </span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">
                        {{ trendLabel || $t('dashboard.stats.vs_last_week') }}
                    </span>
                </div>
            </div>
            
            <div 
                class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-lg"
                :class="colorClasses[color].icon"
            >
                <slot name="icon">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </slot>
            </div>
        </div>
    </div>
</template>
