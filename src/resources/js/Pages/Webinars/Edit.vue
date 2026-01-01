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

const copied = ref(false);
const statusUpdating = ref(false);

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

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.webinar.registration_url);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
};

// Status transitions - what statuses can be changed to from current
const allowedStatusTransitions = computed(() => {
    const transitions = {
        draft: ['scheduled', 'published'],
        scheduled: ['draft', 'live'],
        live: ['ended'],
        ended: ['published', 'draft'],
        published: ['draft'],
    };
    return transitions[props.webinar.status] || [];
});

const changeStatus = (newStatus) => {
    if (statusUpdating.value) return;
    statusUpdating.value = true;

    router.post(route('webinars.update-status', props.webinar.id), {
        status: newStatus,
    }, {
        preserveScroll: true,
        onFinish: () => {
            statusUpdating.value = false;
        },
    });
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
                        v-if="webinar.type === 'auto'"
                        :href="route('webinars.auto.config', webinar.id)"
                        class="inline-flex items-center px-3 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $t('webinars.edit.schedule') }}
                    </Link>
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
                    <!-- Public Registration Link -->
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-medium text-white mb-1">{{ $t('webinars.edit.public_link') }}</h3>
                                <p class="text-indigo-100 text-sm mb-3">{{ $t('webinars.edit.public_link_desc') }}</p>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-white/20 rounded-md px-3 py-2 text-white font-mono text-sm overflow-x-auto">
                                        {{ webinar.registration_url }}
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyLink"
                                        class="inline-flex items-center px-3 py-2 bg-white rounded-md font-semibold text-xs text-indigo-600 uppercase tracking-widest hover:bg-indigo-50 transition-colors"
                                    >
                                        <svg v-if="!copied" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        <svg v-else class="w-4 h-4 mr-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ copied ? $t('webinars.edit.copied') : $t('webinars.edit.copy_link') }}
                                    </button>
                                    <a
                                        :href="webinar.registration_url"
                                        target="_blank"
                                        class="inline-flex items-center px-3 py-2 bg-white/20 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-white/30 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                <div class="mt-1 flex items-center gap-2">
                                    <span :class="['inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium', getStatusColor(webinar.status)]">
                                        {{ statuses[webinar.status] }}
                                    </span>
                                    <div v-if="allowedStatusTransitions.length > 0" class="relative">
                                        <select
                                            @change="changeStatus($event.target.value); $event.target.value = ''"
                                            :disabled="statusUpdating"
                                            class="block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pr-8"
                                        >
                                            <option value="">{{ $t('webinars.edit.change_status') }}</option>
                                            <option v-for="status in allowedStatusTransitions" :key="status" :value="status">
                                                → {{ statuses[status] }}
                                            </option>
                                        </select>
                                        <div v-if="statusUpdating" class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50 rounded-md">
                                            <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="allowedStatusTransitions.length === 0" class="mt-1 text-xs text-gray-500">{{ $t('webinars.edit.no_status_change') }}</p>
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

