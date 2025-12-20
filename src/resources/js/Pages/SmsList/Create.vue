<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    groups: Array,
    tags: Array,
});

const form = useForm({
    name: '',
    description: '',
    contact_list_group_id: '',
    tags: [],
    is_public: false,
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
    form.post(route('sms-lists.store'));
};
</script>

<template>
    <Head :title="$t('sms_lists.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('sms-lists.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('sms_lists.create_title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('sms_lists.create_subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('sms_lists.fields.name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            :placeholder="$t('sms_lists.fields.name_placeholder')"
                            required
                        >
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="description" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('sms_lists.fields.description') }}
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            :placeholder="$t('sms_lists.fields.description_placeholder')"
                        ></textarea>
                    </div>

                    <div>
                        <label for="contact_list_group_id" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('sms_lists.fields.group') }}
                        </label>
                        <select
                            id="contact_list_group_id"
                            v-model="form.contact_list_group_id"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="">{{ $t('sms_lists.fields.no_group') }}</option>
                            <option v-for="group in groups" :key="group.id" :value="group.id">
                                {{ group.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('sms_lists.fields.tags') }}
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
                            {{ $t('sms_lists.fields.no_tags') }}
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
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ $t('sms_lists.fields.is_public') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $t('sms_lists.fields.is_public_description') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                        <Link
                            :href="route('sms-lists.index')"
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
                            <span>{{ form.processing ? $t('common.saving') : $t('sms_lists.create_list') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
