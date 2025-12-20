<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const actions = [
    {
        label: t('dashboard.quick_actions.new_message'),
        href: '/messages/add',
        icon: 'email',
        color: 'indigo'
    },
    {
        label: t('dashboard.quick_actions.add_subscriber'),
        href: '/subscribers/add',
        icon: 'user',
        color: 'emerald'
    },
    {
        label: t('dashboard.quick_actions.import_contacts'),
        href: '/subscribers/import',
        icon: 'upload',
        color: 'amber'
    },
    {
        label: t('dashboard.quick_actions.new_form'),
        href: '/forms/add',
        icon: 'form',
        color: 'purple'
    }
];

const colorClasses = {
    indigo: 'bg-indigo-100 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-900/30 dark:text-indigo-400 dark:group-hover:bg-indigo-600 dark:group-hover:text-white',
    emerald: 'bg-emerald-100 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white dark:bg-emerald-900/30 dark:text-emerald-400 dark:group-hover:bg-emerald-600 dark:group-hover:text-white',
    amber: 'bg-amber-100 text-amber-600 group-hover:bg-amber-600 group-hover:text-white dark:bg-amber-900/30 dark:text-amber-400 dark:group-hover:bg-amber-600 dark:group-hover:text-white',
    purple: 'bg-purple-100 text-purple-600 group-hover:bg-purple-600 group-hover:text-white dark:bg-purple-900/30 dark:text-purple-400 dark:group-hover:bg-purple-600 dark:group-hover:text-white'
};
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800/50">
        <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
            {{ $t('dashboard.quick_actions.title') }}
        </h3>
        
        <div class="grid grid-cols-2 gap-3">
            <Link
                v-for="action in actions"
                :key="action.href"
                :href="action.href"
                class="group flex flex-col items-center gap-3 rounded-xl bg-slate-50 p-4 transition-all duration-200 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-700/50"
            >
                <div 
                    class="flex h-12 w-12 items-center justify-center rounded-xl transition-all duration-200"
                    :class="colorClasses[action.color]"
                >
                    <!-- Email icon -->
                    <svg v-if="action.icon === 'email'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    
                    <!-- User icon -->
                    <svg v-else-if="action.icon === 'user'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    
                    <!-- Upload icon -->
                    <svg v-else-if="action.icon === 'upload'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    
                    <!-- Form icon -->
                    <svg v-else-if="action.icon === 'form'" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                
                <span class="text-center text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ action.label }}
                </span>
            </Link>
        </div>
    </div>
</template>
