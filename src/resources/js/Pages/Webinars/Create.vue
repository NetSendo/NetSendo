<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    types: Object,
    lists: Array,
    defaultSettings: Object,
});

const form = useForm({
    name: '',
    description: '',
    type: 'live',
    scheduled_at: '',
    target_list_id: '',
    registration_tag: '',
    attended_tag: '',
    missed_tag: '',
    video_url: '',
    youtube_live_id: '',
    settings: { ...props.defaultSettings },
});

const submit = () => {
    form.post(route('webinars.store'));
};

const typeDescriptions = {
    live: props.t ? props.t('webinars.create.types.live') : 'Live Broadcast via YouTube. Interactive real-time chat with attendees.',
    auto: props.t ? props.t('webinars.create.types.auto') : 'Pre-recorded webinar played automatically. Simulated chat for higher engagement.',
    hybrid: props.t ? props.t('webinars.create.types.hybrid') : 'Recording with live join-in option. Flexible mix of both formats.',
};
</script>

<template>
    <Head title="Nowy Webinar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $t('webinars.create.title') }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Type Selection -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.create.type_selection') }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div
                                v-for="(label, type) in types"
                                :key="type"
                                @click="form.type = type"
                                :class="[
                                    'relative rounded-lg border p-4 cursor-pointer transition-all',
                                    form.type === type
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 ring-2 ring-indigo-500'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <svg v-if="type === 'live'" :class="['h-8 w-8', form.type === type ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <svg v-else-if="type === 'auto'" :class="['h-8 w-8', form.type === type ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <svg v-else :class="['h-8 w-8', form.type === type ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <div>
                                        <p :class="['font-medium', form.type === type ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-white']">
                                            {{ label }}
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ typeDescriptions[type] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.create.basic_info') }}</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.name') }}</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    :placeholder="$t('webinars.create.name_placeholder')"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.description') }}</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    :placeholder="$t('webinars.create.description_placeholder')"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>

                            <div v-if="form.type === 'live'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.start_date') }}</label>
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
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.create.video_settings') }}</h3>

                        <div class="space-y-4">
                            <div v-if="form.type === 'live'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.youtube_id') }}</label>
                                <input
                                    v-model="form.youtube_live_id"
                                    type="text"
                                    placeholder="np. dQw4w9WgXcQ"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.create.youtube_id_help') }}</p>
                            </div>

                            <div v-else>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.video_url') }}</label>
                                <input
                                    v-model="form.video_url"
                                    type="url"
                                    placeholder="https://..."
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.create.video_url_help') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact List Integration -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.create.integration') }}</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.target_list') }}</label>
                                <select
                                    v-model="form.target_list_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">{{ $t('webinars.create.no_list') }}</option>
                                    <option v-for="list in lists" :key="list.id" :value="list.id">
                                        {{ list.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.create.list_help') }}</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.tags.registration') }}</label>
                                    <input
                                        v-model="form.registration_tag"
                                        type="text"
                                        placeholder="webinar-rejestracja"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.tags.attended') }}</label>
                                    <input
                                        v-model="form.attended_tag"
                                        type="text"
                                        placeholder="webinar-uczestnik"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.create.tags.missed') }}</label>
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

                    <!-- Submit -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="route('webinars.index')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
                        >
                            {{ $t('webinars.create.cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                        >
                            {{ $t('webinars.create.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
