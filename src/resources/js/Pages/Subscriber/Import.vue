<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    lists: Array,
});

const form = useForm({
    file: null,
    contact_list_id: props.lists.length > 0 ? props.lists[0].id : '',
    separator: ',',
});

const submit = () => {
    form.post(route('subscribers.import.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('file'),
    });
};
</script>

<template>
    <Head :title="$t('subscribers.import.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('subscribers.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('subscribers.import.title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('subscribers.import.subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-2xl space-y-6">
                
                <!-- Format Instructions -->
                <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 dark:border-indigo-900/30 dark:bg-indigo-900/20">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-indigo-800 dark:text-indigo-300">{{ $t('subscribers.import.instructions.title') }}</h3>
                            <div class="mt-2 text-sm text-indigo-700 dark:text-indigo-400">
                                <p class="mb-2">{{ $t('subscribers.import.instructions.line1') }}</p>
                                <p>{{ $t('subscribers.import.instructions.required_columns') }}</p>
                                <ul class="list-inside list-disc">
                                    <li>{{ $t('subscribers.import.instructions.email') }}</li>
                                    <li>{{ $t('subscribers.import.instructions.first_name') }}</li>
                                    <li>{{ $t('subscribers.import.instructions.last_name') }}</li>
                                </ul>
                                <p class="mt-2 text-xs font-mono bg-white/50 dark:bg-black/20 p-2 rounded">
                                    email,first_name,last_name<br>
                                    jan@example.com,Jan,Kowalski<br>
                                    anna@test.com,Anna,Nowak
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <!-- List Selection -->
                        <div>
                            <label for="contact_list_id" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                {{ $t('subscribers.import.fields.target_list') }} <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="contact_list_id"
                                v-model="form.contact_list_id"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                required
                            >
                                <option value="" disabled>{{ $t('subscribers.fields.select_list') }}</option>
                                <option v-for="list in lists" :key="list.id" :value="list.id">
                                    {{ list.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.contact_list_id" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.contact_list_id }}
                            </p>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                             <!-- Separator -->
                            <div>
                                <label for="separator" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('subscribers.import.fields.separator') }}
                                </label>
                                <select
                                    id="separator"
                                    v-model="form.separator"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                >
                                    <option value=",">{{ $t('subscribers.import.fields.comma') }}</option>
                                    <option value=";">{{ $t('subscribers.import.fields.semicolon') }}</option>
                                    <option value="tab">{{ $t('subscribers.import.fields.tab') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- File Input -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                {{ $t('subscribers.import.fields.select_file') }} <span class="text-red-500">*</span>
                            </label>
                            <div class="mt-1 flex justify-center rounded-xl border-2 border-dashed border-slate-300 px-6 pt-5 pb-6 transition-colors hover:border-indigo-400 dark:border-slate-700 dark:hover:border-indigo-500">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-slate-600 dark:text-slate-400">
                                        <label for="file-upload" class="relative cursor-pointer rounded-md bg-transparent font-medium text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            <span>{{ $t('subscribers.import.fields.choose_file') }}</span>
                                            <input id="file-upload" name="file-upload" type="file" class="sr-only" accept=".csv,.txt" @input="form.file = $event.target.files[0]">
                                        </label>
                                        <p class="pl-1">{{ $t('subscribers.import.fields.or_drag_drop') }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-500">
                                        {{ $t('subscribers.import.fields.max_size') }}
                                    </p>
                                    <!-- Selected Filename -->
                                    <p v-if="form.file" class="mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ $t('subscribers.import.fields.selected', { name: form.file.name }) }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="form.errors.file" class="mt-2 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.file }}
                            </p>
                            <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="mt-2 w-full h-1">
                                {{ form.progress.percentage }}%
                            </progress>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                            <Link
                                :href="route('subscribers.index')"
                                class="rounded-xl px-6 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            >
                                {{ $t('common.cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || !form.file"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ form.processing ? $t('subscribers.import.importing') : $t('subscribers.import.import_button') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

