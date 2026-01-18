<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

import { ref, watch } from 'vue';

const props = defineProps({
    settings: Object,
    mailboxes: Array,
    externalPages: Array,
    globalCronSettings: Object,
});

const currentSettingsTab = ref('subscription');
const saveSuccess = ref(false);

// CRON helpers
const days = [
    { key: 'monday' },
    { key: 'tuesday' },
    { key: 'wednesday' },
    { key: 'thursday' },
    { key: 'friday' },
    { key: 'saturday' },
    { key: 'sunday' },
];

const getDefaultSchedule = () => {
    const schedule = {};
    days.forEach(day => {
        schedule[day.key] = { enabled: true, start: 0, end: 1440 };
    });
    return schedule;
};

const minutesToTime = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
};

const timeToMinutes = (time) => {
    const [hours, mins] = time.split(':').map(Number);
    return hours * 60 + mins;
};

// Helper to migrate legacy flat settings or get deep value
const getSetting = (path, defaultVal) => {
    // Check if we have the new nested structure first
    const parts = path.split('.');
    let current = props.settings;

    // Check if props.settings is the new structure (has 'subscription' key)
    if (props.settings && props.settings.subscription) {
         for (const part of parts) {
            if (current === null || current === undefined) return defaultVal;
            current = current[part];
        }
        return current !== undefined ? current : defaultVal;
    }

    // Fallback migration for old flat structure if it exists
    if (props.settings) {
        if (path === 'subscription.double_optin') return props.settings.double_optin ?? defaultVal;
        if (path === 'subscription.notification_email') return props.settings.notification_email ?? props.settings.subscription_notify_email ?? defaultVal;
        if (path === 'sending.from_name') return props.settings.from_name ?? props.settings.default_from_name ?? defaultVal;
        if (path === 'sending.reply_to') return props.settings.reply_to ?? props.settings.default_reply_to ?? defaultVal;
        if (path.startsWith('sending.company_')) {
            const field = path.replace('sending.', '');
            return props.settings[field] ?? defaultVal;
        }
    }

    return defaultVal;
};

const form = useForm({
    settings: {
        subscription: {
            double_optin: getSetting('subscription.double_optin', false),
            notification_email: getSetting('subscription.notification_email', ''),
            delete_unconfirmed: getSetting('subscription.delete_unconfirmed', false),
            delete_unconfirmed_after_days: getSetting('subscription.delete_unconfirmed_after_days', 7),
        },
        sending: {
            mailbox_id: getSetting('sending.mailbox_id', null),
            from_name: getSetting('sending.from_name', ''),
            reply_to: getSetting('sending.reply_to', ''),
            company_name: getSetting('sending.company_name', ''),
            company_address: getSetting('sending.company_address', ''),
            company_city: getSetting('sending.company_city', ''),
            company_zip: getSetting('sending.company_zip', ''),

            company_country: getSetting('sending.company_country', ''),
            headers: {
                list_unsubscribe: getSetting('sending.headers.list_unsubscribe', ''),
                list_unsubscribe_post: getSetting('sending.headers.list_unsubscribe_post', ''),
            },
        },
        pages: {
            confirmation: props.settings?.pages?.confirmation || { type: 'system', url: '', external_page_id: null },
            success: props.settings?.pages?.success || { type: 'system', url: '', external_page_id: null },
            error: props.settings?.pages?.error || { type: 'system', url: '', external_page_id: null },
            exists_active: props.settings?.pages?.exists_active || { type: 'system', url: '', external_page_id: null },
            exists_inactive: props.settings?.pages?.exists_inactive || { type: 'system', url: '', external_page_id: null },
            activation_success: props.settings?.pages?.activation_success || { type: 'system', url: '', external_page_id: null },
            activation_error: props.settings?.pages?.activation_error || { type: 'system', url: '', external_page_id: null },
            unsubscribe: props.settings?.pages?.unsubscribe || { type: 'system', url: '', external_page_id: null },
            unsubscribe_confirm: props.settings?.pages?.unsubscribe_confirm || { type: 'system', url: '', external_page_id: null },
            unsubscribe_error: props.settings?.pages?.unsubscribe_error || { type: 'system', url: '', external_page_id: null },
            unsubscribe_link_sent: props.settings?.pages?.unsubscribe_link_sent || { type: 'system', url: '', external_page_id: null },
        },
        cron: {
            volume_per_minute: props.globalCronSettings?.volume_per_minute ?? 100,
            daily_maintenance_hour: props.globalCronSettings?.daily_maintenance_hour ?? 4,
            schedule: props.globalCronSettings?.schedule ?? getDefaultSchedule(),
        },
        advanced: {
            facebook_integration: getSetting('advanced.facebook_integration', ''),
            queue_days: getSetting('advanced.queue_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            bounce_analysis: getSetting('advanced.bounce_analysis', true),
            bounce_scope: getSetting('advanced.bounce_scope', 'list'),
            soft_bounce_threshold: getSetting('advanced.soft_bounce_threshold', 3),
        },
    },
});

const insertHeaderTemplate = (type) => {
    let email = 'unsubscribe@example.com';
    const selectedMailbox = props.mailboxes.find(m => m.id === form.settings.sending.mailbox_id);
    if (selectedMailbox && selectedMailbox.from_email) {
        email = selectedMailbox.from_email;
    }

    if (type === 'list_unsubscribe') {
        form.settings.sending.headers.list_unsubscribe = `<mailto:${email}?subject=unsubscribe>, <[[unsubscribe_url]]>`;
    } else if (type === 'list_unsubscribe_post') {
        form.settings.sending.headers.list_unsubscribe_post = 'List-Unsubscribe=One-Click';
    }
};

// Automate headers population when mailbox changes
watch(() => form.settings.sending.mailbox_id, (newVal) => {
    if (newVal) {
        // Auto-fill List-Unsubscribe if empty or using default example
        if (!form.settings.sending.headers.list_unsubscribe || form.settings.sending.headers.list_unsubscribe.includes('unsubscribe@example.com')) {
             insertHeaderTemplate('list_unsubscribe');
        }
        // Auto-fill List-Unsubscribe-Post if empty
        if (!form.settings.sending.headers.list_unsubscribe_post) {
             insertHeaderTemplate('list_unsubscribe_post');
        }
    }
});

const submit = () => {
    form.post(route('defaults.store'), {
        preserveScroll: true,
        onSuccess: () => {
            saveSuccess.value = true;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 3000);
        }
    });
};
</script>

<template>
    <Head :title="$t('navigation.default_settings')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('navigation.default_settings') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('mailing_lists.default_settings_subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-5xl">
                <!-- Info Banner -->
                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="text-sm">
                            <p class="font-medium text-blue-800 dark:text-blue-200">
                                {{ $t('defaults.global_settings_title') }}
                            </p>
                            <p class="mt-1 text-blue-700 dark:text-blue-300">
                                {{ $t('defaults.global_settings_desc') }}
                                <a :href="route('mailing-lists.index')" class="font-medium underline hover:text-blue-900 dark:hover:text-blue-100">
                                    {{ $t('defaults.go_to_lists') }}
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                    <form @submit.prevent="submit" class="flex flex-col gap-8 lg:flex-row">


                        <!-- Settings Sidebar -->
                        <div class="w-full lg:w-1/4">
                            <nav class="flex flex-col space-y-1">
                                <button
                                    v-for="tab in ['subscription', 'sending', 'pages', 'cron', 'advanced']"
                                    :key="tab"
                                    @click="currentSettingsTab = tab"
                                    type="button"
                                    class="flex items-center rounded-lg px-4 py-2 text-left text-sm font-medium transition-colors"
                                    :class="currentSettingsTab === tab
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
                                >
                                    {{ $t(`mailing_lists.settings.tabs.${tab}`) }}
                                </button>
                            </nav>
                        </div>

                        <!-- Content Area -->
                        <div class="flex-1 space-y-6 lg:pl-6">

                             <!-- Subscription Settings -->
                            <div v-show="currentSettingsTab === 'subscription'" class="space-y-6">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                    {{ $t('mailing_lists.settings.subscription_section') }}
                                </h3>

                                <div class="space-y-4">
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <div>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.settings.double_optin') }}</span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.double_optin_desc') }}</span>
                                        </div>
                                        <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                                :class="form.settings.subscription.double_optin ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                                @click="form.settings.subscription.double_optin = !form.settings.subscription.double_optin">
                                            <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="{ 'translate-x-5': form.settings.subscription.double_optin }"></span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <div>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.settings.delete_unconfirmed') }}</span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.delete_unconfirmed_desc') }}</span>
                                        </div>
                                        <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                                :class="form.settings.subscription.delete_unconfirmed ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                                @click="form.settings.subscription.delete_unconfirmed = !form.settings.subscription.delete_unconfirmed">
                                            <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="{ 'translate-x-5': form.settings.subscription.delete_unconfirmed }"></span>
                                        </div>
                                    </div>

                                    <!-- Delete unconfirmed after days -->
                                    <div
                                        v-if="form.settings.subscription.delete_unconfirmed"
                                        class="ml-4 border-l-2 border-indigo-200 pl-4 dark:border-indigo-800"
                                    >
                                        <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $t('mailing_lists.settings.delete_unconfirmed_after_days') }}
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="form.settings.subscription.delete_unconfirmed_after_days"
                                                type="number"
                                                min="1"
                                                max="365"
                                                class="w-24 rounded-xl border-slate-200 bg-slate-50 px-4 py-2 text-center placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                            />
                                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.delete_unconfirmed_days_label') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                            {{ $t('mailing_lists.settings.delete_unconfirmed_after_days_help') }}
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $t('mailing_lists.settings.notification_email') }}
                                        </label>
                                        <input
                                            v-model="form.settings.subscription.notification_email"
                                            type="email"
                                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                            :placeholder="$t('mailing_lists.settings.notification_email_placeholder')"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Sending Settings -->
                            <div v-show="currentSettingsTab === 'sending'" class="space-y-8">
                                <div>
                                    <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                        {{ $t('mailing_lists.settings.sending_section') }}
                                    </h3>

                                     <!-- Default Mailbox Select -->
                                    <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <label class="mb-1 block text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $t('mailing_lists.settings.mailbox_defaults_label') }}
                                        </label>
                                        <p class="mb-3 text-xs text-slate-500">{{ $t('mailing_lists.settings.mailbox_defaults_desc') }}</p>
                                        <select
                                            v-model="form.settings.sending.mailbox_id"
                                            class="block w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:border-indigo-400"
                                        >
                                            <option :value="null">{{ $t('mailing_lists.settings.mailbox_defaults_none') }}</option>
                                            <option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id">
                                                {{ mailbox.name }} ({{ mailbox.from_email }})
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $t('mailing_lists.settings.from_name') }}
                                            </label>
                                            <input
                                                v-model="form.settings.sending.from_name"
                                                type="text"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                :placeholder="$t('mailing_lists.settings.from_name_placeholder')"
                                            >
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $t('mailing_lists.settings.reply_to') }}
                                            </label>
                                            <input
                                                v-model="form.settings.sending.reply_to"
                                                type="email"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                :placeholder="$t('mailing_lists.settings.reply_to_placeholder')"
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                        {{ $t('mailing_lists.settings.company_section') }}
                                    </h3>
                                    <div class="grid gap-6">
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $t('mailing_lists.settings.company_name') }}
                                            </label>
                                            <input
                                                v-model="form.settings.sending.company_name"
                                                type="text"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                            >
                                        </div>
                                        <div>
                                            <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $t('mailing_lists.settings.company_address') }}
                                            </label>
                                            <input
                                                v-model="form.settings.sending.company_address"
                                                type="text"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                            >
                                        </div>
                                        <div class="grid gap-6 md:grid-cols-3">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $t('mailing_lists.settings.company_city') }}
                                                </label>
                                                <input
                                                    v-model="form.settings.sending.company_city"
                                                    type="text"
                                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                >
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $t('mailing_lists.settings.company_zip') }}
                                                </label>
                                                <input
                                                    v-model="form.settings.sending.company_zip"
                                                    type="text"
                                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                >
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $t('mailing_lists.settings.company_country') }}
                                                </label>
                                                <input
                                                    v-model="form.settings.sending.company_country"
                                                    type="text"
                                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div>
                                    <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                        {{ $t('mailing_lists.settings.headers_section') }}
                                    </h3>
                                    <div class="grid gap-6">
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-sm font-medium text-slate-900 dark:text-white">
                                                    List-Unsubscribe
                                                </label>
                                                <button type="button" @click="insertHeaderTemplate('list_unsubscribe')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    {{ $t('mailing_lists.settings.insert_template') }}
                                                </button>
                                            </div>
                                            <input
                                                v-model="form.settings.sending.headers.list_unsubscribe"
                                                type="text"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                placeholder="<mailto:...>, <https://...>"
                                            >
                                            <p class="mt-1 text-xs text-slate-500">{{ $t('mailing_lists.settings.headers_help') }}</p>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-sm font-medium text-slate-900 dark:text-white">
                                                    List-Unsubscribe-Post
                                                </label>
                                                <button type="button" @click="insertHeaderTemplate('list_unsubscribe_post')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    {{ $t('mailing_lists.settings.insert_template') }}
                                                </button>
                                            </div>
                                            <input
                                                v-model="form.settings.sending.headers.list_unsubscribe_post"
                                                type="text"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                                placeholder="List-Unsubscribe=One-Click"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                             <!-- Pages Settings -->
                            <div v-show="currentSettingsTab === 'pages'" class="space-y-6">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                    {{ $t('mailing_lists.settings.pages_section') }}
                                </h3>

                                <div v-for="(page, key) in form.settings.pages" :key="key" class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                    <div class="mb-3 flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-slate-900 dark:text-white">{{ $t(`mailing_lists.settings.pages.${key}`) }}</h4>
                                            <p v-if="page.type === 'system'" class="text-xs text-slate-500">
                                                {{ $t(`mailing_lists.settings.pages.defaults.${key}`) }}
                                            </p>
                                        </div>
                                        <div class="flex rounded-lg bg-white p-1 shadow-sm dark:bg-slate-900">
                                            <button
                                                @click="page.type = 'system'"
                                                type="button"
                                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                                :class="page.type === 'system' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30' : 'text-slate-500 hover:text-slate-700'"
                                            >
                                                {{ $t('mailing_lists.settings.pages.types.system') }}
                                            </button>
                                            <button
                                                @click="page.type = 'custom'"
                                                type="button"
                                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                                :class="page.type === 'custom' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30' : 'text-slate-500 hover:text-slate-700'"
                                            >
                                                {{ $t('mailing_lists.settings.pages.types.custom') }}
                                            </button>
                                            <button
                                                @click="page.type = 'external'"
                                                type="button"
                                                class="rounded px-3 py-1 text-xs font-medium transition-colors"
                                                :class="page.type === 'external' ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30' : 'text-slate-500 hover:text-slate-700'"
                                            >
                                                {{ $t('mailing_lists.settings.pages.types.external') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="page.type === 'custom'" class="mt-3">
                                        <input
                                            v-model="page.url"
                                            type="url"
                                            class="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900"
                                            placeholder="https://"
                                        >
                                    </div>

                                    <div v-if="page.type === 'external'" class="mt-3">
                                        <select
                                            v-model="page.external_page_id"
                                            class="block w-full rounded-lg border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option :value="null" disabled>{{ $t('mailing_lists.settings.pages.select_page') }}</option>
                                            <option v-for="ep in externalPages" :key="ep.id" :value="ep.id">
                                                {{ ep.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CRON Settings -->
                            <div v-show="currentSettingsTab === 'cron'" class="space-y-6">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                    {{ $t('defaults.cron_section_title') }}
                                </h3>

                                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                    <div class="flex items-start gap-3">
                                        <svg class="h-5 w-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ $t('cron.settings.global_note_title') }}</p>
                                            <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                                                {{ $t('cron.settings.global_note_text') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $t('defaults.volume_per_minute_label') }}
                                    </label>
                                    <input
                                        v-model.number="form.settings.cron.volume_per_minute"
                                        type="number"
                                        min="1"
                                        max="10000"
                                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        placeholder="np. 100"
                                    >
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('cron.settings.volume_help') }}</p>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $t('cron.settings.maintenance_hour') }}
                                    </label>
                                    <select
                                        v-model.number="form.settings.cron.daily_maintenance_hour"
                                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option v-for="h in 24" :key="h-1" :value="h-1">{{ String(h-1).padStart(2, '0') }}:00</option>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('cron.settings.maintenance_help') }}</p>
                                </div>

                                <!-- Weekly schedule -->
                                <div>
                                    <label class="mb-3 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $t('cron.schedule.title') }}
                                    </label>
                                    <div class="space-y-3">
                                        <div
                                            v-for="day in days"
                                            :key="day.key"
                                            class="flex flex-wrap items-center gap-4 p-3 bg-slate-100 dark:bg-slate-700/50 rounded-lg"
                                        >
                                            <div class="w-28">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        v-model="form.settings.cron.schedule[day.key].enabled"
                                                        class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                                    />
                                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                                        {{ $t('common.days.' + day.key) }}
                                                    </span>
                                                </label>
                                            </div>

                                            <div
                                                v-if="form.settings.cron.schedule[day.key].enabled"
                                                class="flex items-center gap-3 flex-1"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <label class="text-xs text-slate-500">{{ $t('cron.schedule.from') }}:</label>
                                                    <input
                                                        type="time"
                                                        :value="minutesToTime(form.settings.cron.schedule[day.key].start)"
                                                        @input="form.settings.cron.schedule[day.key].start = timeToMinutes($event.target.value)"
                                                        class="px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                                                    />
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <label class="text-xs text-slate-500">{{ $t('cron.schedule.to') }}:</label>
                                                    <input
                                                        type="time"
                                                        :value="minutesToTime(form.settings.cron.schedule[day.key].end)"
                                                        @input="form.settings.cron.schedule[day.key].end = timeToMinutes($event.target.value)"
                                                        class="px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                                                    />
                                                </div>

                                                <!-- Quick presets -->
                                                <div class="flex gap-1">
                                                    <button
                                                        type="button"
                                                        @click="form.settings.cron.schedule[day.key] = { enabled: true, start: 0, end: 1440 }"
                                                        class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-600 rounded hover:bg-slate-300 dark:hover:bg-slate-500"
                                                    >
                                                        {{ $t('cron.schedule.preset_24h') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="form.settings.cron.schedule[day.key] = { enabled: true, start: 480, end: 1020 }"
                                                        class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-600 rounded hover:bg-slate-300 dark:hover:bg-slate-500"
                                                    >
                                                        {{ $t('cron.schedule.preset_business') }}
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                v-else
                                                class="flex-1 text-sm text-slate-400 dark:text-slate-500 italic"
                                            >
                                                {{ $t('cron.schedule.day_disabled') }}
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">{{ $t('cron.schedule.description') }}</p>
                                </div>
                            </div>


                            <!-- Advanced Settings -->
                            <div v-show="currentSettingsTab === 'advanced'" class="space-y-6">
                                <h3 class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                                    {{ $t('mailing_lists.settings.advanced_section') }}
                                </h3>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.settings.advanced.bounce_title') }}</span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.advanced.bounce_desc') }}</span>
                                        </div>
                                        <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                                :class="form.settings.advanced.bounce_analysis ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                                @click="form.settings.advanced.bounce_analysis = !form.settings.advanced.bounce_analysis">
                                            <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="{ 'translate-x-5': form.settings.advanced.bounce_analysis }"></span>
                                        </div>
                                    </div>

                                    <!-- Extended options when bounce_analysis is enabled -->
                                    <template v-if="form.settings.advanced.bounce_analysis">
                                        <!-- Bounce Scope -->
                                        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-3">
                                                {{ $t('mailing_lists.settings.advanced.bounce_scope_label') }}
                                            </label>
                                            <div class="flex flex-col gap-3 sm:flex-row sm:gap-6">
                                                <label class="flex items-start gap-3 cursor-pointer group">
                                                    <input type="radio" v-model="form.settings.advanced.bounce_scope" value="list" class="mt-0.5 w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600"/>
                                                    <div>
                                                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-indigo-600">{{ $t('mailing_lists.settings.advanced.bounce_scope_list') }}</span>
                                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.advanced.bounce_scope_list_desc') }}</span>
                                                    </div>
                                                </label>
                                                <label class="flex items-start gap-3 cursor-pointer group">
                                                    <input type="radio" v-model="form.settings.advanced.bounce_scope" value="global" class="mt-0.5 w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600"/>
                                                    <div>
                                                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-indigo-600">{{ $t('mailing_lists.settings.advanced.bounce_scope_global') }}</span>
                                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.advanced.bounce_scope_global_desc') }}</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Soft Bounce Threshold -->
                                        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                                            <label class="block text-sm font-medium text-slate-900 dark:text-white mb-2">{{ $t('mailing_lists.settings.advanced.soft_bounce_threshold_label') }}</label>
                                            <input v-model.number="form.settings.advanced.soft_bounce_threshold" type="number" min="1" max="10" class="w-24 rounded-lg border-slate-200 bg-white px-3 py-2 text-center placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"/>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.advanced.soft_bounce_threshold_help') }}</p>
                                        </div>
                                    </template>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $t('mailing_lists.settings.advanced.facebook_label') }}
                                    </label>
                                    <input
                                        v-model="form.settings.advanced.facebook_integration"
                                        type="text"
                                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                        :placeholder="$t('mailing_lists.settings.advanced.facebook_placeholder')"
                                    >
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $t('mailing_lists.settings.advanced.queue_days_label') }}
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <div v-for="day in ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']" :key="day"
                                            class="cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-medium transition-all"
                                            :class="form.settings.advanced.queue_days.includes(day)
                                                ? 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'"
                                            @click="() => {
                                                const idx = form.settings.advanced.queue_days.indexOf(day);
                                                if (idx === -1) form.settings.advanced.queue_days.push(day);
                                                else form.settings.advanced.queue_days.splice(idx, 1);
                                            }"
                                        >
                                            {{ $t('common.shorthand_days.' + day.substring(0, 3)) }}
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('mailing_lists.settings.advanced.queue_days_help') }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                                <!-- Success notification -->
                                <Transition
                                    enter-active-class="transition ease-out duration-300"
                                    enter-from-class="transform opacity-0 translate-x-4"
                                    enter-to-class="transform opacity-100 translate-x-0"
                                    leave-active-class="transition ease-in duration-200"
                                    leave-from-class="transform opacity-100 translate-x-0"
                                    leave-to-class="transform opacity-0 translate-x-4"
                                >
                                    <div v-if="saveSuccess" class="flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ $t('common.saved') }}
                                    </div>
                                </Transition>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                                >
                                    <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ form.processing ? $t('common.saving') : $t('common.save') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
