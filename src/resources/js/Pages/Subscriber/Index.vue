<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    subscribers: Object,
    lists: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const listId = ref(props.filters.list_id || '');

watch([search, listId], debounce(([newSearch, newListId]) => {
    router.get(route('subscribers.index'), {
        search: newSearch,
        list_id: newListId,
    }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const deleteSubscriber = (subscriber) => {
    if (confirm(t('subscribers.confirm_delete', { email: subscriber.email }))) {
        router.delete(route('subscribers.destroy', subscriber.id));
    }
};
</script>

<template>
    <Head :title="$t('subscribers.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('subscribers.title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('subscribers.subtitle') }}
                    </p>
                </div>
                <Link
                    :href="route('subscribers.create')"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    {{ $t('subscribers.add_subscriber') }}
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 pl-10 pr-4 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500"
                        :placeholder="$t('subscribers.search_placeholder')"
                    >
                </div>
            </div>
            <div>
                <select
                    v-model="listId"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                >
                    <option value="">{{ $t('subscribers.all_lists') }}</option>
                    <option v-for="list in lists" :key="list.id" :value="list.id">
                        {{ list.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-500 dark:text-slate-400">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <tr>
                            <th scope="col" class="px-6 py-3">{{ $t('subscribers.table.email') }}</th>
                            <th scope="col" class="px-6 py-3">{{ $t('subscribers.table.name') }}</th>
                            <th scope="col" class="px-6 py-3">{{ $t('subscribers.table.status') }}</th>
                            <th scope="col" class="px-6 py-3">{{ $t('subscribers.table.list') }}</th>
                            <th scope="col" class="px-6 py-3">{{ $t('subscribers.table.added_at') }}</th>
                            <th scope="col" class="px-6 py-3 text-right">{{ $t('subscribers.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                {{ subscriber.email }}
                            </td>
                            <td class="px-6 py-4">
                                {{ subscriber.first_name }} {{ subscriber.last_name }}
                            </td>
                            <td class="px-6 py-4">
                                <span 
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': subscriber.status === 'active',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': subscriber.status === 'unsubscribed',
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': subscriber.status === 'inactive',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': subscriber.status === 'bounced'
                                    }"
                                >
                                    {{ $t(`subscribers.statuses.${subscriber.status}`) || subscriber.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                {{ subscriber.lists && subscriber.lists.length ? subscriber.lists.join(', ') : '-' }}
                            </td>
                            <td class="px-6 py-4">
                                {{ subscriber.created_at }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link 
                                        :href="route('subscribers.edit', subscriber.id)"
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        :title="$t('common.edit')"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button 
                                        @click="deleteSubscriber(subscriber)"
                                        class="text-slate-400 hover:text-red-600 dark:hover:text-red-400"
                                        :title="$t('common.delete')"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="subscribers.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ $t('subscribers.empty_state') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div v-if="subscribers.links.length > 3" class="flex items-center justify-between border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                <div class="flex gap-1">
                    <Link
                        v-for="(link, i) in subscribers.links"
                        :key="i"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1 text-sm"
                        :class="{
                            'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400': link.active,
                            'text-slate-500 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800': !link.active && link.url,
                            'opacity-50 cursor-not-allowed': !link.url
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

