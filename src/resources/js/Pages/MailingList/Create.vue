<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    defaultSettings: Object,
    groups: Array,
    tags: Array,
    mailboxes: Array,
    externalPages: Array,
});

const currentTab = ref('general');
const currentSettingsTab = ref('subscription');

// Helper to get default setting value
const getDefault = (section, key, fallback) => {
    return props.defaultSettings?.[section]?.[key] ?? fallback;
};

// Helper to get page default
const getPageDefault = (key) => {
    return props.defaultSettings?.pages?.[key] || { type: 'system', url: '', external_page_id: null };
};

const form = useForm({
    name: '',
    description: '',
    contact_list_group_id: '',
    tags: [],
    is_public: true,
    settings: {
        subscription: {
            double_optin: getDefault('subscription', 'double_optin', false),
            notification_email: getDefault('subscription', 'notification_email', ''),
            delete_unconfirmed: getDefault('subscription', 'delete_unconfirmed', false),
        },
        sending: {
            mailbox_id: getDefault('sending', 'mailbox_id', null),
            from_name: getDefault('sending', 'from_name', ''),
            reply_to: getDefault('sending', 'reply_to', ''),
            company_name: getDefault('sending', 'company_name', ''),
            company_address: getDefault('sending', 'company_address', ''),
            company_city: getDefault('sending', 'company_city', ''),
            company_zip: getDefault('sending', 'company_zip', ''),
            company_country: getDefault('sending', 'company_country', ''),
        },
        pages: {
            confirmation: getPageDefault('confirmation'),
            success: getPageDefault('success'),
            error: getPageDefault('error'),
            exists_active: getPageDefault('exists_active'),
            exists_inactive: getPageDefault('exists_inactive'),
            activation_success: getPageDefault('activation_success'),
            activation_error: getPageDefault('activation_error'),
            unsubscribe: getPageDefault('unsubscribe'),
            unsubscribe_confirm: getPageDefault('unsubscribe_confirm'),
            unsubscribe_error: getPageDefault('unsubscribe_error'),
            unsubscribe_link_sent: getPageDefault('unsubscribe_link_sent'),
        },
        advanced: {
            facebook_integration: getDefault('advanced', 'facebook_integration', ''),
            queue_days: getDefault('advanced', 'queue_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            bounce_analysis: getDefault('advanced', 'bounce_analysis', true),
        },
    },
});

const toggleTag = (tagId) => {
    const index = form.tags.indexOf(tagId);
    if (index === -1) {
        form.tags.push(tagId);
    } else {
        form.tags.splice(index, 1);
    }
};

const submit = () => {
    form.post(route('mailing-lists.store'));
};
</script>

<template>
    <Head :title="$t('mailing_lists.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('mailing-lists.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('mailing_lists.create_title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('mailing_lists.create_subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-4xl">
                 <!-- Tabs Navigation -->
                <div class="mb-6 flex space-x-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/50">
                    <button
                        v-for="tab in ['general', 'settings']"
                        :key="tab"
                        @click="currentTab = tab"
                        class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all"
                        :class="currentTab === tab 
                            ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-400' 
                            : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    >
                        {{ tab === 'general' ? $t('mailing_lists.tabs.general') : $t('mailing_lists.tabs.settings') }}
                    </button>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                         <!-- General Tab -->
                        <div v-show="currentTab === 'general'" class="space-y-6">
                            <div>
                                <label for="name" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('mailing_lists.fields.name') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                    :placeholder="$t('mailing_lists.fields.name_placeholder')"
                                    required
                                >
                                <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label for="description" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('mailing_lists.fields.description') }}
                                </label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                    :placeholder="$t('mailing_lists.fields.description_placeholder')"
                                ></textarea>
                            </div>

                            <div>
                                <label for="contact_list_group_id" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('mailing_lists.fields.group') }}
                                </label>
                                <select
                                    id="contact_list_group_id"
                                    v-model="form.contact_list_group_id"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                >
                                    <option value="">{{ $t('mailing_lists.fields.no_group') }}</option>
                                    <option v-for="group in groups" :key="group.id" :value="group.id">
                                        {{ group.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('mailing_lists.fields.tags') }}
                                </label>
                                <div class="flex flex-wrap gap-2">
                                     <div 
                                        v-for="tag in tags" 
                                        :key="tag.id"
                                        @click="toggleTag(tag.id)"
                                        class="cursor-pointer rounded-full px-3 py-1 text-sm font-medium text-white transition-all hover:scale-105 select-none"
                                        :class="{ 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-slate-900': form.tags.includes(tag.id) }"
                                        :style="{ backgroundColor: tag.color, opacity: form.tags.includes(tag.id) ? 1 : 0.6 }"
                                    >
                                        {{ tag.name }}
                                    </div>
                                </div>
                                <p v-if="tags.length === 0" class="text-xs text-slate-500 mt-1">
                                    {{ $t('mailing_lists.fields.no_tags') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900" 
                                     :class="form.is_public ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                     @click="form.is_public = !form.is_public">
                                    <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" 
                                          :class="{ 'translate-x-5': form.is_public }"></span>
                                </div>
                                <div>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.fields.is_public') }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.fields.is_public_description') }}</span>
                                </div>
                            </div>
                        </div>

                         <!-- Settings Tab -->
                        <!-- Settings Tab -->
                        <div v-show="currentTab === 'settings'" class="flex flex-col gap-6 lg:flex-row">
                            <!-- Sidebar -->
                            <div class="w-full lg:w-1/4">
                                <nav class="flex flex-col space-y-1">
                                    <button
                                        v-for="tab in ['subscription', 'sending', 'pages', 'advanced']"
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

                            <!-- Content -->
                            <div class="flex-1 lg:pl-6">
                                
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
                                                {{ $t('mailing_lists.mailbox_defaults_label') }}
                                            </label>
                                            <p class="mb-3 text-xs text-slate-500">{{ $t('mailing_lists.mailbox_defaults_desc') }}</p>
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
                                                    {{ $t('mailing_lists.settings.pages.types.external_page') }}
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
                                                <option :value="undefined" disabled>{{ $t('mailing_lists.settings.pages.select_page') }}</option>
                                                <option v-for="ep in props.externalPages" :key="ep.id" :value="ep.id">
                                                    {{ ep.name }}
                                                </option>
                                            </select>
                                        </div>
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
                                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.settings.bounce_section') }}</span>
                                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.settings.bounce_desc') }}</span>
                                            </div>
                                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900" 
                                                    :class="form.settings.advanced.bounce_analysis ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                                    @click="form.settings.advanced.bounce_analysis = !form.settings.advanced.bounce_analysis">
                                                <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" 
                                                        :class="{ 'translate-x-5': form.settings.advanced.bounce_analysis }"></span>
                                            </div>
                                        </div>
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
                                            {{ $t('mailing_lists.settings.queue_days_label') }}
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
                                        <p class="mt-1 text-xs text-slate-500">{{ $t('mailing_lists.settings.queue_days_help') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                            <Link
                                :href="route('mailing-lists.index')"
                                class="rounded-xl px-6 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            >
                                {{ $t('common.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ form.processing ? $t('common.saving') : $t('mailing_lists.create_list') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

