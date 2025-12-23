<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    }
});

const stats = ref(null);
const cronStatus = ref(null);
const loading = ref(true);

const fetchStats = async () => {
    loading.value = true;
    try {
        const [statsRes, cronRes] = await Promise.all([
            fetch(route('api.dashboard.stats')),
            fetch(route('settings.cron.status'))
        ]);
        
        if (statsRes.ok) stats.value = await statsRes.json();
        if (cronRes.ok) cronStatus.value = await cronRes.json();
    } catch (e) {
        console.error('Failed to fetch onboarding stats', e);
    } finally {
        loading.value = false;
    }
};

// Fetch when opened
import { watch } from 'vue';
watch(() => props.show, (newVal) => {
    if (newVal) fetchStats();
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

// Calculate steps status
const steps = computed(() => {
    const licenseActive = page.props.license?.active;
    const cronActive = cronStatus.value?.has_ever_run;
    const profileCompleted = !!page.props.auth.user.name;
    const listCreated = (stats.value?.contact_lists_count || 0) > 0;
    const subscribersAdded = (stats.value?.subscribers?.total || 0) > 0;
    const campaignSent = (stats.value?.emails_sent?.total || 0) > 0;

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
            route: 'mailing-lists.index',
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

const progress = computed(() => {
    const total = steps.value.length;
    const completed = steps.value.filter(s => s.completed).length;
    return {
        current: completed,
        total: total,
        percentage: Math.round((completed / total) * 100)
    };
});
</script>

<template>
    <Modal :show="show" @close="close" max-width="4xl">
        <div class="relative bg-white dark:bg-slate-900">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-6 py-5 dark:from-slate-800 dark:to-slate-800 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ t('onboarding.title', 'Centrum Startu') }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ t('onboarding.subtitle', 'Wykonaj te kroki, aby w pełni uruchomić NetSendo') }}
                        </p>
                    </div>
                    <button @click="close" class="text-slate-400 hover:text-slate-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Progress Bar -->
                <div class="mb-8">
                     <div class="flex items-center justify-between text-sm mb-2">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ progress.percentage }}% ukończono</span>
                        <span class="text-slate-500">{{ progress.current }} z {{ progress.total }} kroków</span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-700">
                        <div class="h-2 rounded-full bg-indigo-600 transition-all duration-500" :style="{ width: `${progress.percentage}%` }"></div>
                    </div>
                </div>

                <!-- Steps Grid -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Link 
                        v-for="step in steps" 
                        :key="step.id"
                        :href="route(step.route, step.query || {})"
                        class="group relative flex flex-col gap-3 rounded-xl border p-4 transition-all hover:border-indigo-500/50 hover:shadow-lg hover:shadow-indigo-500/10"
                        :class="step.completed 
                            ? 'border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-800/50 opacity-75' 
                            : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800'"
                        @click="close"
                    >
                        <div class="flex items-center justify-between">
                            <div 
                                class="flex h-10 w-10 items-center justify-center rounded-lg ring-1 ring-inset"
                                :class="step.completed 
                                    ? 'bg-emerald-50 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-400/20' 
                                    : 'bg-indigo-50 text-indigo-600 ring-indigo-500/20 dark:bg-indigo-900/20 dark:text-indigo-400 dark:ring-indigo-400/20'"
                            >
                                <svg v-if="step.completed" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <!-- Icons -->
                                <svg v-else-if="step.icon === 'KeyIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                                <svg v-else-if="step.icon === 'ClockIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <svg v-else-if="step.icon === 'UserIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                <svg v-else-if="step.icon === 'ListBulletIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 17.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                <svg v-else-if="step.icon === 'UsersIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                <svg v-else-if="step.icon === 'PaperAirplaneIcon'" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                            </div>
                            <svg v-if="!step.completed" class="h-5 w-5 text-slate-300 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white" :class="{'line-through text-slate-500': step.completed}">
                                {{ step.title }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ step.description }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </Modal>
</template>
