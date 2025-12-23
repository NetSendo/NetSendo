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

// Calculate missing critical steps
const missingSteps = computed(() => {
    const steps = [];

    // 1. License
    if (!page.props.license?.active) {
        steps.push({
            id: 'license',
            label: t('onboarding.tracker.license', 'Licencja'),
            route: 'license.index',
            priority: 1
        });
    }

    // 2. AI Integration
    const hasAi = (props.stats?.ai_integrations_count || 0) > 0;
    if (!hasAi) {
        steps.push({
            id: 'ai',
            label: t('onboarding.tracker.ai', 'Integracja AI'),
            route: 'settings.ai-integrations.index',
            priority: 2
        });
    }

    // 3. Mailbox
    const hasMailbox = (props.stats?.mailboxes_count || 0) > 0;
    if (!hasMailbox) {
        steps.push({
            id: 'mailbox',
            label: t('onboarding.tracker.mailbox', 'Skrzynka pocztowa'),
            route: 'settings.mailboxes.index',
            priority: 3
        });
    }

    // 4. CRON (Only if never run)
    const cronRun = props.cronStatus?.has_ever_run;
    if (!cronRun) {
        // Only show checking CRON if we have it here; 
        // Note: The red banner for CRON failure is separate. This is for initial setup.
        steps.push({
            id: 'cron',
            label: t('onboarding.tracker.cron', 'CRON'),
            route: 'settings.cron.index',
            query: { tab: 'instructions' },
            priority: 4
        });
    }

    return steps.sort((a, b) => a.priority - b.priority);
});

// Calculate progress based on inverse of missing
const totalCriticalSteps = 4;
const completedCount = computed(() => totalCriticalSteps - missingSteps.value.length);
const percentage = computed(() => Math.round((completedCount.value / totalCriticalSteps) * 100));

</script>

<template>
    <div v-if="missingSteps.length > 0" class="mb-6 rounded-xl bg-slate-900 shadow-md ring-1 ring-white/10 overflow-hidden">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 gap-4">
            
            <!-- Left: Title & Progress -->
            <div class="flex items-center gap-4">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-500/20 text-indigo-400 ring-1 ring-indigo-500/50">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">
                        {{ t('onboarding.tracker.title', 'Konfiguracja startowa') }}
                    </h3>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="h-1.5 w-24 rounded-full bg-slate-700">
                            <div class="h-1.5 rounded-full bg-indigo-500 transition-all duration-500" :style="{ width: `${percentage}%` }"></div>
                        </div>
                        <span class="text-xs text-slate-400">{{ completedCount }}/{{ totalCriticalSteps }}</span>
                    </div>
                </div>
            </div>

            <!-- Right: Actionable Steps -->
            <div class="flex flex-wrap items-center gap-2">
                <Link 
                    v-for="step in missingSteps" 
                    :key="step.id"
                    :href="route(step.route, step.query || {})"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white ring-1 ring-white/5 hover:ring-white/20"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                    {{ step.label }}
                    <svg class="h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>
    </div>
</template>
