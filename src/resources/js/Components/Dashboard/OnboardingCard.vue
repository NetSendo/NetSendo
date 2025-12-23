<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({})
    },
    cronStatus: {
        type: Object,
        default: () => ({})
    }
});

// Calculate steps status
const steps = computed(() => {
    // 1. License Active
    const licenseActive = page.props.license?.active;

    // 2. CRON Configured (has ever run)
    const cronActive = props.cronStatus?.has_ever_run;

    // 3. Profile Completed (checking if name is set - basic check)
    const profileCompleted = !!page.props.auth.user.name;

    // 4. Contact List Created
    const listCreated = (props.stats?.contact_lists_count || 0) > 0;

    // 5. Subscribers Added
    const subscribersAdded = (props.stats?.subscribers?.total || 0) > 0;

    // 6. First Campaign Sent
    const campaignSent = (props.stats?.emails_sent?.total || 0) > 0;

    return [
        {
            id: 'license',
            title: t('onboarding.license.title', 'Aktywacja licencji'),
            description: t('onboarding.license.desc', 'Aktywuj licencję, aby odblokować pełne możliwości'),
            completed: licenseActive,
            route: 'license.index',
            icon: 'KeyIcon'
        },
        {
            id: 'cron',
            title: t('onboarding.cron.title', 'Konfiguracja CRON'),
            description: t('onboarding.cron.desc', 'Uruchom zadania w tle do wysyłki emaili'),
            completed: cronActive,
            route: 'settings.cron.index',
            icon: 'ClockIcon',
            query: { tab: 'instructions' }
        },
        {
            id: 'profile',
            title: t('onboarding.profile.title', 'Uzupełnij profil'),
            description: t('onboarding.profile.desc', 'Skonfiguruj swoje dane nadawcy'),
            completed: profileCompleted,
            route: 'profile.edit',
            icon: 'UserIcon'
        },
        {
            id: 'list',
            title: t('onboarding.list.title', 'Stwórz listę'),
            description: t('onboarding.list.desc', 'Utwórz pierwszą listę kontaktów'),
            completed: listCreated,
            route: 'mailing-lists.index', // Changed from lists.index to match resource route
            icon: 'ListBulletIcon'
        },
        {
            id: 'subscribers',
            title: t('onboarding.subscribers.title', 'Dodaj odbiorców'),
            description: t('onboarding.subscribers.desc', 'Importuj lub dodaj pierwszych odbiorców'),
            completed: subscribersAdded,
            route: 'subscribers.index',
            icon: 'UsersIcon'
        },
        {
            id: 'campaign',
            title: t('onboarding.campaign.title', 'Wyślij kampanię'),
            description: t('onboarding.campaign.desc', 'Stwórz i wyślij swój pierwszy newsletter'),
            completed: campaignSent,
            route: 'messages.index',
            icon: 'PaperAirplaneIcon'
        }
    ];
});

// Progress calculation
const progress = computed(() => {
    const total = steps.value.length;
    const completed = steps.value.filter(s => s.completed).length;
    return {
        current: completed,
        total: total,
        percentage: Math.round((completed / total) * 100)
    };
});

// All steps completed?
const isComplete = computed(() => progress.value.percentage === 100);
</script>

<template>
    <div v-if="!isComplete" class="mb-8 overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-900/5 dark:bg-slate-800 dark:ring-white/10">
        <!-- Header -->
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-blue-50 px-6 py-5 dark:border-slate-700 dark:from-slate-800 dark:to-slate-800">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-blue-600 bg-clip-text text-transparent dark:from-indigo-400 dark:to-blue-400">
                        {{ t('onboarding.title', 'Centrum Startu') }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ t('onboarding.subtitle', 'Wykonaj te kroki, aby w pełni uruchomić NetSendo') }}
                    </p>
                </div>
                
                <!-- Progress Stats -->
                <div class="flex items-center gap-4 bg-white/50 px-4 py-2 rounded-xl border border-slate-200/50 dark:bg-slate-700/50 dark:border-slate-600">
                    <div class="flex items-center gap-3">
                        <div class="relative h-12 w-12 flex-shrink-0">
                            <svg class="h-full w-full -rotate-90 transform text-slate-200 dark:text-slate-600" viewBox="0 0 36 36">
                                <!-- Background Circle -->
                                <path
                                    class="stroke-current"
                                    stroke-width="3"
                                    fill="none"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                />
                                <!-- Progress Circle -->
                                <path
                                    class="stroke-indigo-600 transition-all duration-1000 ease-out dark:stroke-indigo-400"
                                    :stroke-dasharray="`${progress.percentage}, 100`"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    fill="none"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-200">
                                {{ progress.percentage }}%
                            </div>
                        </div>
                        <div class="text-sm">
                            <span class="font-bold text-slate-900 dark:text-white">{{ progress.current }}/{{ progress.total }}</span>
                            <span class="text-slate-500 dark:text-slate-400"> {{ t('onboarding.steps_completed', 'kroków') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Steps Grid -->
        <div class="grid divide-y divide-slate-100 dark:divide-slate-700 md:grid-cols-2 md:divide-x md:divide-y-0 lg:grid-cols-3 lg:divide-y">
            <Link 
                v-for="step in steps" 
                :key="step.id"
                :href="route(step.route, step.query || {})"
                class="group relative flex items-start gap-4 p-6 transition-all hover:bg-slate-50 dark:hover:bg-slate-700/50"
                :class="{ 'opacity-50 grayscale hover:opacity-100 hover:grayscale-0': step.completed }"
            >
                <!-- Status Icon -->
                <div class="flex-shrink-0">
                    <div 
                        class="flex h-10 w-10 items-center justify-center rounded-lg ring-1 ring-inset transition-colors"
                        :class="step.completed 
                            ? 'bg-emerald-50 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-400/20' 
                            : 'bg-white text-slate-500 ring-slate-200 group-hover:bg-indigo-50 group-hover:text-indigo-600 group-hover:ring-indigo-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700 dark:group-hover:bg-indigo-900/30 dark:group-hover:text-indigo-400'"
                    >
                        <svg v-if="step.completed" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        
                        <!-- Dynamic Icons based on step type -->
                        <svg v-else-if="step.icon === 'KeyIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <svg v-else-if="step.icon === 'ClockIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else-if="step.icon === 'UserIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <svg v-else-if="step.icon === 'ListBulletIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 17.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                        <svg v-else-if="step.icon === 'UsersIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <svg v-else-if="step.icon === 'PaperAirplaneIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </div>
                </div>

                <!-- Text Content -->
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white" :class="{'line-through text-slate-400 dark:text-slate-500': step.completed}">
                        {{ step.title }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ step.description }}
                    </p>
                    
                    <!-- Action Link (if not completed) -->
                    <div v-if="!step.completed" class="mt-3 flex items-center text-sm font-medium text-indigo-600 group-hover:text-indigo-500 dark:text-indigo-400">
                        <span>{{ t('onboarding.start_now', 'Rozpocznij') }}</span>
                        <svg class="ml-1 h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </Link>
        </div>
    </div>
</template>
