<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Panel from '@/Components/Ui/Panel.vue';
import SegmentedControl from '@/Components/Ui/SegmentedControl.vue';
import StatTiles from '@/Components/Ui/StatTiles.vue';
import StatusPill from '@/Components/Ui/StatusPill.vue';
import Toast from '@/Components/Ui/Toast.vue';
import { fieldClass, hintClass, labelClass } from '@/Components/Ui/fieldClass';
import { useToast } from '@/Composables/useToast';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
// The outcome of an action is worded here; the server's own message is shown only for a failure
const { toast, showToast, dismiss } = useToast({ flash: 'error' });

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const props = defineProps({
    settings: Object,
    stats: Object,
    recentLogs: Array,
    listsAllowedNow: Array,
    isDispatchAllowed: Boolean,
    isCronConfigured: Boolean,
});

const days = [
    { key: 'monday', label: t('cron.days.monday') },
    { key: 'tuesday', label: t('cron.days.tuesday') },
    { key: 'wednesday', label: t('cron.days.wednesday') },
    { key: 'thursday', label: t('cron.days.thursday') },
    { key: 'friday', label: t('cron.days.friday') },
    { key: 'saturday', label: t('cron.days.saturday') },
    { key: 'sunday', label: t('cron.days.sunday') },
];

const form = useForm({
    volume_per_minute: props.settings?.volume_per_minute || 100,
    daily_maintenance_hour: props.settings?.daily_maintenance_hour || 4,
    schedule: props.settings?.schedule || getDefaultSchedule(),
});

function getDefaultSchedule() {
    const schedule = {};
    days.forEach(day => {
        schedule[day.key] = { enabled: true, start: 0, end: 1440 };
    });
    return schedule;
}

// Pomocnicze funkcje do formatowania czasu
// A time field has no "24:00": the end of the day is shown as 23:59 and kept as 1440
// until the field is changed
function minutesToTime(value) {
    const minutes = Math.min(value, 1439);
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
}

function timeToMinutes(time) {
    const [hours, mins] = time.split(':').map(Number);
    return hours * 60 + mins;
}

function formatDuration(seconds) {
    if (!seconds) return '—';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}

function formatDateTime(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleString(locale.value);
}

const activeTab = ref('settings');

const cronTabs = computed(() =>
    ['settings', 'stats', 'logs', 'instructions'].map((value) => ({ value, label: t(`cron.tabs.${value}`) })),
);

// Whether mail is going out right now: no CRON at all, inside the sending hours, or outside
const dispatchStatus = computed(() => {
    if (!props.isCronConfigured) return { tone: 'danger', label: t('cron.status.not_configured') };
    if (props.isDispatchAllowed) return { tone: 'success', label: t('cron.status.active') };
    return { tone: 'warning', label: t('cron.status.paused') };
});

const statTiles = computed(() => [
    { key: 'runs', label: t('cron.stats.total_runs'), value: number(props.stats?.total_runs) },
    { key: 'ok', label: t('cron.stats.successful'), value: number(props.stats?.successful) },
    { key: 'failed', label: t('cron.stats.failed'), value: number(props.stats?.failed) },
    { key: 'sent', label: t('cron.stats.emails_sent'), value: number(props.stats?.emails_sent) },
]);

const DAY_PRESETS = [
    { key: 'preset_24h', start: 0, end: 1440 },
    { key: 'preset_business', start: 480, end: 1020 },
    { key: 'preset_office', start: 540, end: 1080 },
];

// Webhook settings
const webhookSettings = ref({
    has_token: false,
    token: null,
    webhook_url: '',
});
const webhookLoading = ref(false);
const tokenVisible = ref(false);

async function fetchWebhookSettings() {
    try {
        const response = await fetch(route('settings.cron.webhook'));
        if (response.ok) {
            webhookSettings.value = await response.json();
        }
    } catch (e) {
        // Ignore
    }
}

async function generateToken() {
    webhookLoading.value = true;
    try {
        const response = await fetch(route('settings.cron.webhook.generate'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            const data = await response.json();
            webhookSettings.value = {
                has_token: true,
                token: data.token,
                webhook_url: data.webhook_url,
            };
            tokenVisible.value = true;
        }
    } catch (e) {
        // Ignore
    } finally {
        webhookLoading.value = false;
    }
}

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    showToast(t('common.copied'));
}

// Check for tab query parameter on mount
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['settings', 'stats', 'logs', 'instructions'].includes(tab)) {
        activeTab.value = tab;
    }

    // Load webhook settings
    fetchWebhookSettings();
});

function submit() {
    form.post(route('settings.cron.store'), {
        preserveScroll: true,
        onSuccess: () => showToast(t('cron.settings_saved')),
    });
}

function refreshStats() {
    router.reload({ only: ['stats', 'recentLogs', 'listsAllowedNow', 'isDispatchAllowed'] });
}

const LOG_TONES = { running: 'info', success: 'success', failed: 'danger' };

// Commands to copy: a quiet block that scrolls sideways instead of wrapping
const codeBlockClass =
    'mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-200';
const codeInlineClass =
    'rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100';
</script>

<template>
    <Head :title="t('cron.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('cron.title')">
                <StatusPill :tone="dispatchStatus.tone">{{ dispatchStatus.label }}</StatusPill>
                <SecondaryButton type="button" @click="refreshStats">{{ t('cron.actions.refresh') }}</SecondaryButton>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <SegmentedControl v-model="activeTab" :options="cronTabs" :label="t('cron.title')" class="self-start" />

            <!-- Settings: how fast mail goes out, and during which hours of which days -->
            <form v-if="activeTab === 'settings'" class="flex flex-col gap-4" @submit.prevent="submit">
                <Panel :title="t('cron.settings.title')">
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">
                        <div>
                            <label for="cron-volume" :class="labelClass">{{ t('cron.settings.volume_per_minute') }}</label>
                            <input
                                id="cron-volume"
                                v-model="form.volume_per_minute"
                                type="number"
                                min="1"
                                max="10000"
                                class="mt-1"
                                :class="[fieldClass, { '!border-rose-500': form.errors.volume_per_minute }]"
                            />
                            <p v-if="form.errors.volume_per_minute" class="mt-1 text-sm text-rose-700 dark:text-rose-300">
                                {{ form.errors.volume_per_minute }}
                            </p>
                            <p v-else :class="hintClass">{{ t('cron.settings.volume_help') }}</p>
                        </div>
                        <div>
                            <label for="cron-maintenance" :class="labelClass">{{ t('cron.settings.maintenance_hour') }}</label>
                            <select id="cron-maintenance" v-model="form.daily_maintenance_hour" class="mt-1" :class="fieldClass">
                                <option v-for="h in 24" :key="h - 1" :value="h - 1">
                                    {{ (h - 1).toString().padStart(2, '0') }}:00
                                </option>
                            </select>
                            <p :class="hintClass">{{ t('cron.settings.maintenance_help') }}</p>
                        </div>
                    </div>
                </Panel>

                <Panel flush :title="t('cron.schedule.title')">
                    <p class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400">{{ t('cron.schedule.description') }}</p>
                    <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60">
                        <li
                            v-for="day in days"
                            :key="day.key"
                            class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-3"
                        >
                            <label class="flex w-36 shrink-0 items-center gap-2.5">
                                <input
                                    v-model="form.schedule[day.key].enabled"
                                    type="checkbox"
                                    class="h-4 w-4 accent-indigo-600"
                                />
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ day.label }}</span>
                            </label>

                            <template v-if="form.schedule[day.key].enabled">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        {{ t('cron.schedule.from') }}
                                        <input
                                            type="time"
                                            :value="minutesToTime(form.schedule[day.key].start)"
                                            class="!w-auto !py-1"
                                            :class="fieldClass"
                                            @input="form.schedule[day.key].start = timeToMinutes($event.target.value)"
                                        />
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                        {{ t('cron.schedule.to') }}
                                        <input
                                            type="time"
                                            :value="minutesToTime(form.schedule[day.key].end)"
                                            class="!w-auto !py-1"
                                            :class="fieldClass"
                                            @input="form.schedule[day.key].end = timeToMinutes($event.target.value)"
                                        />
                                    </label>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 sm:ml-auto">
                                    <button
                                        v-for="preset in DAY_PRESETS"
                                        :key="preset.key"
                                        type="button"
                                        class="min-h-8 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        @click="form.schedule[day.key] = { enabled: true, start: preset.start, end: preset.end }"
                                    >
                                        {{ t('cron.schedule.' + preset.key) }}
                                    </button>
                                </div>
                            </template>
                            <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                                {{ t('cron.schedule.day_disabled') }}
                            </p>
                        </li>
                    </ul>
                </Panel>

                <div class="flex justify-end">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? t('cron.actions.saving') : t('cron.actions.save') }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- The last 24 hours -->
            <template v-if="activeTab === 'stats'">
                <StatTiles :items="statTiles" :label="t('cron.tabs.stats')" />

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <Panel flush :title="t('cron.lists.allowed_now')">
                        <ul
                            v-if="listsAllowedNow.length > 0"
                            class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60"
                        >
                            <li
                                v-for="list in listsAllowedNow"
                                :key="list.id"
                                class="flex items-center justify-between gap-4 px-5 py-2.5 text-sm"
                            >
                                <span class="min-w-0 truncate text-slate-900 dark:text-white">{{ list.name }}</span>
                                <span class="shrink-0 tabular-nums text-slate-600 dark:text-slate-400">
                                    {{ number(list.volume_per_minute) }}/min
                                </span>
                            </li>
                        </ul>
                        <p v-else class="border-t border-slate-200 px-5 py-6 text-sm text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                            {{ t('cron.lists.no_active') }}
                        </p>
                    </Panel>

                    <Panel :title="t('cron.stats.info_title')">
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.stats.last_run') }}</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">{{ formatDateTime(settings.last_run) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.stats.last_daily_run') }}</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">{{ formatDateTime(settings.last_daily_run) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.stats.global_limit') }}</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">{{ number(settings.volume_per_minute) }}/min</dd>
                            </div>
                            <div>
                                <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.stats.avg_duration') }}</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">{{ formatDuration(stats?.avg_duration) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.stats.maintenance_at') }}</dt>
                                <dd class="tabular-nums text-slate-900 dark:text-white">
                                    {{ String(settings.daily_maintenance_hour ?? 0).padStart(2, '0') }}:00
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                </div>
            </template>

            <!-- What ran, when, and how it ended -->
            <Panel v-if="activeTab === 'logs'" flush :title="t('cron.logs.title')">
                <p
                    v-if="!recentLogs || recentLogs.length === 0"
                    class="border-t border-slate-200 px-5 py-10 text-center text-sm text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                >
                    {{ t('cron.logs.no_logs') }}
                </p>
                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full text-sm sm:min-w-[760px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ t('cron.logs.job_name') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t('cron.logs.status') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t('cron.logs.started_at') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">{{ t('cron.logs.duration') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ t('cron.logs.emails_sent') }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium max-sm:hidden">
                                    {{ t('cron.logs.emails_failed') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in recentLogs"
                                :key="log.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                            >
                                <td class="max-w-0 px-5 py-2.5 max-sm:w-full sm:w-[30%]">
                                    <p class="truncate font-mono text-xs text-slate-900 dark:text-white" :title="log.job_name">
                                        {{ log.job_name }}
                                    </p>
                                    <!-- On a phone the status and the time have no columns of their own -->
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 sm:hidden">
                                        <StatusPill :tone="LOG_TONES[log.status] || 'neutral'">
                                            {{ t('cron.status.' + log.status, log.status) }}
                                        </StatusPill>
                                        <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                            {{ formatDateTime(log.started_at) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 max-sm:hidden">
                                    <StatusPill :tone="LOG_TONES[log.status] || 'neutral'">
                                        {{ t('cron.status.' + log.status, log.status) }}
                                    </StatusPill>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ formatDateTime(log.started_at) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ formatDuration(log.duration) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ number(log.emails_sent) }}
                                </td>
                                <td
                                    class="py-2.5 pl-3 pr-5 text-right tabular-nums max-sm:hidden"
                                    :class="log.emails_failed ? 'font-medium text-rose-700 dark:text-rose-300' : 'text-slate-600 dark:text-slate-400'"
                                >
                                    {{ number(log.emails_failed) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- How to make the server run the scheduler every minute -->
            <template v-if="activeTab === 'instructions'">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
                        {{ t('cron.instructions.importance_title') }}
                    </p>
                    <p class="mt-0.5 text-sm text-amber-900 dark:text-amber-200">
                        {{ t('cron.instructions.importance_text') }}
                    </p>
                </div>

                <Panel :title="t('cron.instructions.docker_title')">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('cron.instructions.docker_text') }}</p>
                    <pre :class="codeBlockClass"># {{ t('cron.instructions.docker_manual') }}
docker compose exec -u dev app php artisan schedule:run

# {{ t('cron.instructions.docker_list') }}
docker compose exec -u dev app php artisan schedule:list</pre>
                </Panel>

                <Panel :title="t('cron.instructions.linux_title')">
                    <i18n-t keypath="cron.instructions.linux_text" tag="p" class="text-sm text-slate-600 dark:text-slate-400">
                        <template #command>
                            <code :class="codeInlineClass">crontab -e</code>
                        </template>
                    </i18n-t>
                    <pre :class="codeBlockClass">* * * * * cd /ścieżka-do-projektu && php artisan schedule:run >> /dev/null 2>&1</pre>
                    <p class="mt-3 text-sm text-amber-800 dark:text-amber-300">
                        {{ t('cron.instructions.linux_warning', { path: '/ścieżka-do-projektu' }) }}
                    </p>
                </Panel>

                <Panel :title="t('cron.instructions.supervisor_title')">
                    <i18n-t keypath="cron.instructions.supervisor_text" tag="p" class="text-sm text-slate-600 dark:text-slate-400">
                        <template #file>
                            <code :class="codeInlineClass">/etc/supervisor/conf.d/netsendo-scheduler.conf</code>
                        </template>
                    </i18n-t>
                    <pre :class="codeBlockClass">[program:netsendo-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php /ścieżka/artisan schedule:run; sleep 60; done"
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/netsendo-scheduler.log</pre>
                    <pre :class="codeBlockClass"># {{ t('cron.instructions.supervisor_after') }}
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start netsendo-scheduler</pre>
                </Panel>

                <Panel :title="t('cron.instructions.cpanel_title')">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                        <li>{{ t('cron.instructions.cpanel_step1') }}</li>
                        <li>{{ t('cron.instructions.cpanel_step2', { section: 'Cron Jobs' }) }}</li>
                        <li>{{ t('cron.instructions.cpanel_step3') }}</li>
                    </ol>
                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div
                            v-for="unit in ['minute', 'hour', 'day', 'month', 'weekday']"
                            :key="unit"
                            class="flex items-center gap-2"
                        >
                            <dt class="text-slate-600 dark:text-slate-400">{{ t('cron.instructions.cpanel_' + unit) }}</dt>
                            <dd class="font-mono font-semibold text-slate-900 dark:text-white">*</dd>
                        </div>
                    </dl>
                    <pre :class="codeBlockClass">cd /home/user/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1</pre>
                </Panel>

                <!-- No shell access: an outside scheduler calls a web address every minute -->
                <Panel :title="t('cron.instructions.automation_title')">
                    <img
                        src="/images/integrations/n8n-make-zapier-dark.png"
                        alt="n8n, Make, Zapier"
                        class="mb-3 h-12 object-contain dark:hidden"
                    />
                    <img
                        src="/images/integrations/n8n-make-zapier-light.png"
                        alt="n8n, Make, Zapier"
                        class="mb-3 hidden h-12 object-contain dark:block"
                    />
                    <i18n-t keypath="cron.instructions.automation_text" tag="p" class="text-sm text-slate-600 dark:text-slate-400">
                        <template #bold>
                            <strong class="font-semibold text-slate-900 dark:text-white">Webhook / API</strong>
                        </template>
                    </i18n-t>

                    <div class="mt-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700/60">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ t('cron.webhook.title') }}</p>

                        <div v-if="!webhookSettings.has_token" class="mt-2 flex flex-wrap items-center gap-3">
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('cron.webhook.no_token') }}</p>
                            <PrimaryButton type="button" :disabled="webhookLoading" @click="generateToken">
                                {{ webhookLoading ? t('cron.webhook.generating') : t('cron.webhook.generate') }}
                            </PrimaryButton>
                        </div>

                        <div v-else class="mt-3 space-y-3">
                            <div>
                                <label for="cron-webhook-url" :class="labelClass">{{ t('cron.webhook.url_label') }}</label>
                                <div class="mt-1 flex gap-2">
                                    <input
                                        id="cron-webhook-url"
                                        readonly
                                        :value="webhookSettings.webhook_url"
                                        class="min-w-0 font-mono !text-xs"
                                        :class="fieldClass"
                                    />
                                    <SecondaryButton type="button" class="shrink-0" @click="copyToClipboard(webhookSettings.webhook_url)">
                                        {{ t('common.copy') }}
                                    </SecondaryButton>
                                </div>
                            </div>
                            <div v-if="webhookSettings.token">
                                <label for="cron-webhook-token" :class="labelClass">{{ t('cron.webhook.token_label') }}</label>
                                <div class="mt-1 flex gap-2">
                                    <input
                                        id="cron-webhook-token"
                                        readonly
                                        :type="tokenVisible ? 'text' : 'password'"
                                        :value="webhookSettings.token"
                                        class="min-w-0 font-mono !text-xs"
                                        :class="fieldClass"
                                    />
                                    <SecondaryButton type="button" class="shrink-0" @click="tokenVisible = !tokenVisible">
                                        {{ tokenVisible ? t('common.hide') : t('common.show') }}
                                    </SecondaryButton>
                                    <SecondaryButton type="button" class="shrink-0" @click="copyToClipboard(webhookSettings.token)">
                                        {{ t('common.copy') }}
                                    </SecondaryButton>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <SecondaryButton type="button" :disabled="webhookLoading" @click="generateToken">
                                    {{ webhookLoading ? t('cron.webhook.generating') : t('cron.webhook.generate_new') }}
                                </SecondaryButton>
                                <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('cron.webhook.token_warning') }}</span>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="font-medium text-slate-900 dark:text-white">n8n</dt>
                            <i18n-t keypath="cron.instructions.n8n_note" tag="dd" class="text-slate-600 dark:text-slate-400">
                                <template #node><code :class="codeInlineClass">HTTP Request</code></template>
                                <template #trigger><code :class="codeInlineClass">Cron</code></template>
                            </i18n-t>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-900 dark:text-white">Make (Integromat)</dt>
                            <i18n-t keypath="cron.instructions.make_note" tag="dd" class="text-slate-600 dark:text-slate-400">
                                <template #module><code :class="codeInlineClass">HTTP &gt; Make a request</code></template>
                            </i18n-t>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-900 dark:text-white">Zapier</dt>
                            <i18n-t keypath="cron.instructions.zapier_note" tag="dd" class="text-slate-600 dark:text-slate-400">
                                <template #trigger><code :class="codeInlineClass">Schedule</code></template>
                                <template #action><code :class="codeInlineClass">Webhooks by Zapier</code></template>
                            </i18n-t>
                        </div>
                    </dl>

                    <pre :class="codeBlockClass"># cURL
curl -X POST "{{ webhookSettings.webhook_url }}" \
     -H "X-Cron-Token: YOUR_TOKEN"

# token in the address
curl "{{ webhookSettings.webhook_url }}?token=YOUR_TOKEN"</pre>
                </Panel>

                <Panel :title="t('cron.instructions.forge_title')">
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="font-medium text-slate-900 dark:text-white">Laravel Forge</dt>
                            <dd class="text-slate-600 dark:text-slate-400">{{ t('cron.instructions.forge_text') }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-900 dark:text-white">Laravel Vapor</dt>
                            <dd class="text-slate-600 dark:text-slate-400">{{ t('cron.instructions.vapor_text') }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel :title="t('cron.verification.title')">
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-400">
                        <li v-for="step in 4" :key="step">{{ t('cron.verification.step' + step) }}</li>
                    </ol>
                </Panel>
            </template>
        </div>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
