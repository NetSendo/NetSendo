<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    lists: Array,
});

const form = useForm({
    email: '',
    first_name: '',
    last_name: '',
    contact_list_id: props.lists.length > 0 ? props.lists[0].id : '',
    status: 'active',
});

const submit = () => {
    form.post(route('subscribers.store'));
};
</script>

<template>
    <Head :title="$t('subscribers.create_title')" />

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
                        {{ $t('subscribers.create_title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('subscribers.create_subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="contact_list_id" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('subscribers.fields.list') }} <span class="text-red-500">*</span>
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

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('subscribers.fields.email') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            placeholder="example@email.com"
                            required
                        >
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                {{ $t('subscribers.fields.first_name') }}
                            </label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            >
                        </div>
                        <div>
                            <label for="last_name" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                                {{ $t('subscribers.fields.last_name') }}
                            </label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-sm font-medium text-slate-900 dark:text-white">
                            {{ $t('subscribers.fields.status') }}
                        </label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="active">{{ $t('subscribers.statuses.active') }}</option>
                            <option value="unsubscribed">{{ $t('subscribers.statuses.unsubscribed') }}</option>
                            <option value="bounced">{{ $t('subscribers.statuses.bounced') }}</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                        <Link
                            :href="route('subscribers.index')"
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
                            <span>{{ form.processing ? $t('common.saving') : $t('subscribers.add_subscriber') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

