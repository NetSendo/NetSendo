<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    webinar: Object,
    types: Object,
    statuses: Object,
    lists: Array,
});

const form = useForm({
    name: props.webinar.name || '',
    description: props.webinar.description || '',
    scheduled_at: props.webinar.scheduled_at ? props.webinar.scheduled_at.slice(0, 16) : '',
    target_list_id: props.webinar.target_list_id || '',
    registration_tag: props.webinar.registration_tag || '',
    attended_tag: props.webinar.attended_tag || '',
    missed_tag: props.webinar.missed_tag || '',
    video_url: props.webinar.video_url || '',
    youtube_live_id: props.webinar.youtube_live_id || '',
    thumbnail_url: props.webinar.thumbnail_url || '',
    settings: props.webinar.settings || {},
});

const submit = () => {
    form.put(route('webinars.update', props.webinar.id));
};

const deleteWebinar = () => {
    if (confirm($t('webinars.edit.delete_confirm'))) {
        router.delete(route('webinars.destroy', props.webinar.id));
    }
};

const duplicateWebinar = () => {
    router.post(route('webinars.duplicate', props.webinar.id));
};

const getStatusColor = (status) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        live: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        ended: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
    return colors[status] || colors.draft;
};
</script>

<template>
    <Head :title="$t('webinars.edit.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('webinars.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $t('webinars.edit.title') }}
                    </h2>
                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(webinar.status)]">
                        {{ statuses[webinar.status] }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('webinars.studio', webinar.id)"
                        class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        {{ $t('webinars.edit.studio') }}
                    </Link>
                    <Link
                        :href="route('webinars.analytics', webinar.id)"
                        class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        {{ $t('webinars.edit.analytics') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.edit.basic_info') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.name') }}</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.description') }}</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.type') }}</label>
                                <div class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-600 rounded-md text-gray-700 dark:text-gray-300">
                                    {{ types[webinar.type] }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.edit.type_readonly') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.status') }}</label>
                                <div class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-600 rounded-md text-gray-700 dark:text-gray-300">
                                    {{ statuses[webinar.status] }}
                                </div>
                            </div>

                            <div v-if="webinar.type === 'live'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.scheduled_at') }}</label>
                                <input
                                    v-model="form.scheduled_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Video Settings -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.edit.video_settings') }}</h3>

                        <div class="space-y-4">
                            <div v-if="webinar.type === 'live'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.youtube_id') }}</label>
                                <input
                                    v-model="form.youtube_live_id"
                                    type="text"
                                    placeholder="np. dQw4w9WgXcQ"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.edit.youtube_id_help') }}</p>
                            </div>

                            <div v-else>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.video_url') }}</label>
                                <input
                                    v-model="form.video_url"
                                    type="url"
                                    placeholder="https://..."
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.edit.video_url_help') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.thumbnail_url') }}</label>
                                <input
                                    v-model="form.thumbnail_url"
                                    type="url"
                                    placeholder="https://..."
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Contact List Integration -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.edit.integration') }}</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.target_list') }}</label>
                                <select
                                    v-model="form.target_list_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">{{ $t('webinars.edit.no_list') }}</option>
                                    <option v-for="list in lists" :key="list.id" :value="list.id">
                                        {{ list.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.tags.registration') }}</label>
                                    <input
                                        v-model="form.registration_tag"
                                        type="text"
                                        placeholder="webinar-rejestracja"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.tags.attended') }}</label>
                                    <input
                                        v-model="form.attended_tag"
                                        type="text"
                                        placeholder="webinar-uczestnik"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.edit.tags.missed') }}</label>
                                    <input
                                        v-model="form.missed_tag"
                                        type="text"
                                        placeholder="webinar-nieobecny"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="duplicateWebinar"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600"
                            >
                                {{ $t('webinars.edit.duplicate') }}
                            </button>
                            <button
                                type="button"
                                @click="deleteWebinar"
                                class="inline-flex items-center px-4 py-2 bg-red-100 dark:bg-red-900/30 border border-transparent rounded-md font-semibold text-xs text-red-700 dark:text-red-400 uppercase tracking-widest hover:bg-red-200 dark:hover:bg-red-900/50"
                            >
                                {{ $t('webinars.edit.delete') }}
                            </button>
                        </div>
                        <div class="flex items-center gap-4">
                            <Link
                                :href="route('webinars.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                            >
                                {{ $t('webinars.edit.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                            >
                                {{ $t('webinars.edit.save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
