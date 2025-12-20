<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    campaigns: {
        type: Array,
        default: () => []
    }
});

// Sample data if not provided
const displayCampaigns = props.campaigns.length > 0 ? props.campaigns : [
    { id: 1, name: 'Newsletter Tygodniowy #45', date: '2024-12-15', status: 'sent', opens: 1234, clicks: 456 },
    { id: 2, name: 'Promocja Świąteczna', date: '2024-12-14', status: 'sent', opens: 2341, clicks: 789 },
    { id: 3, name: 'Podsumowanie Roku', date: '2024-12-20', status: 'scheduled', opens: 0, clicks: 0 },
    { id: 4, name: 'Nowy Produkt - Zapowiedź', date: '2024-12-18', status: 'draft', opens: 0, clicks: 0 },
];

const statusClasses = {
    sent: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    scheduled: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400'
};

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' });
};

const formatNumber = (num) => {
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'k';
    }
    return num;
};
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800/50">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t('dashboard.recent_campaigns.title') }}
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $t('dashboard.recent_campaigns.description') }}
                </p>
            </div>
            
            <Link 
                href="/messages"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t('dashboard.recent_campaigns.view_all') }} →
            </Link>
        </div>
        
        <div class="space-y-3">
            <div 
                v-for="campaign in displayCampaigns" 
                :key="campaign.id"
                class="group flex items-center justify-between rounded-xl bg-slate-50 p-4 transition-all duration-200 hover:bg-slate-100 dark:bg-slate-800/50 dark:hover:bg-slate-700/50"
            >
                <div class="flex items-center gap-4">
                    <!-- Icon -->
                    <div 
                        class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :class="{
                            'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400': campaign.status === 'sent',
                            'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400': campaign.status === 'scheduled',
                            'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400': campaign.status === 'draft'
                        }"
                    >
                        <svg v-if="campaign.status === 'sent'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else-if="campaign.status === 'scheduled'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{ campaign.name }}
                        </h4>
                        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ formatDate(campaign.date) }}</span>
                            <span 
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusClasses[campaign.status]"
                            >
                                {{ $t(`dashboard.recent_campaigns.status.${campaign.status}`) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats (only for sent campaigns) -->
                <div v-if="campaign.status === 'sent'" class="flex items-center gap-6 text-sm">
                    <div class="text-center">
                        <div class="font-semibold text-slate-900 dark:text-white">
                            {{ formatNumber(campaign.opens) }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('dashboard.recent_campaigns.opens') }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-slate-900 dark:text-white">
                            {{ formatNumber(campaign.clicks) }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('dashboard.recent_campaigns.clicks') }}
                        </div>
                    </div>
                </div>
                
                <!-- Arrow for non-sent -->
                <svg 
                    v-else
                    class="h-5 w-5 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>
    </div>
</template>
