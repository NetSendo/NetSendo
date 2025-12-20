<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import throttle from 'lodash/throttle';
import pickBy from 'lodash/pickBy';

const props = defineProps({
    messages: Object,
    filters: Object,
    lists: Array,
    groups: Array,
});

const form = reactive({
    search: props.filters.search || '',
    type: props.filters.type || '',
    list_id: props.filters.list_id || '',
    group_id: props.filters.group_id || '',
    sort: props.filters.sort || 'created_at',
    direction: props.filters.direction || 'desc',
});

const messageToDelete = ref(null);

watch(form, throttle(() => {
    router.get(route('sms.index'), pickBy(form), {
        preserveState: true,
        replace: true,
    });
}, 300));

const sort = (field) => {
    form.sort = field;
    form.direction = form.direction === 'asc' ? 'desc' : 'asc';
};

const confirmDeleteMessage = (message) => {
    messageToDelete.value = message;
};

const deleteMessage = () => {
    if (messageToDelete.value) {
        router.delete(route('sms.destroy', messageToDelete.value.id), {
            onSuccess: () => messageToDelete.value = null,
        });
    }
};

const resetFilters = () => {
    form.search = '';
    form.type = '';
    form.list_id = '';
    form.group_id = '';
    form.sort = 'created_at';
    form.direction = 'desc';
};

const closeModal = () => {
    messageToDelete.value = null;
};
</script>

<template>
    <Head :title="$t('sms.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('sms.title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('sms.subtitle') }}
                    </p>
                </div>
                <Link
                    :href="route('sms.create')"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('sms.new_message') }}
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="mt-4 flex flex-wrap items-center gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    v-model="form.search"
                    type="text"
                    :placeholder="$t('sms.search_placeholder')"
                    class="block w-full rounded-lg border-slate-300 pl-10 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>

            <!-- List Filter -->
            <select
                v-model="form.list_id"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t('sms.all_lists') }}</option>
                <option v-for="list in lists" :key="list.id" :value="list.id">
                    {{ list.name }}
                </option>
            </select>

            <!-- Group Filter -->
            <select
                v-model="form.group_id"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t('messages.all_groups') }}</option>
                <option v-for="group in groups" :key="group.id" :value="group.id">
                    {{ group.name }}
                </option>
            </select>

            <!-- Type Filter -->
            <select
                v-model="form.type"
                class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option value="">{{ $t('sms.all_types') }}</option>
                <option value="broadcast">{{ $t('sms.type_broadcast') }}</option>
                <option value="autoresponder">{{ $t('sms.type_autoresponder') }}</option>
            </select>

             <!-- Reset -->
             <button 
                v-if="form.search || form.list_id || form.type || form.group_id"
                @click="resetFilters"
                class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            >
                 {{ $t('common.clear') }}
            </button>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-500 dark:text-slate-400">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <tr>
                            <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700" @click="sort('subject')">
                                <div class="flex items-center gap-1">
                                    {{ $t('sms.table.subject') }}
                                    <span v-if="form.sort === 'subject'">{{ form.direction === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700" @click="sort('type')">
                                <div class="flex items-center gap-1">
                                    {{ $t('sms.table.type') }}
                                    <span v-if="form.sort === 'type'">{{ form.direction === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3">{{ $t('sms.table.audience') }}</th>
                            <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700" @click="sort('status')">
                                 <div class="flex items-center gap-1">
                                    {{ $t('sms.table.status') }}
                                    <span v-if="form.sort === 'status'">{{ form.direction === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700" @click="sort('created_at')">
                                 <div class="flex items-center gap-1">
                                    {{ $t('sms.table.created_at') }}
                                    <span v-if="form.sort === 'created_at'">{{ form.direction === 'asc' ? '↑' : '↓' }}</span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right">{{ $t('sms.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="message in messages.data" :key="message.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                {{ message.subject }}
                            </td>
                            <td class="px-6 py-4">
                                <span 
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400': message.type === 'broadcast',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400': message.type === 'autoresponder'
                                    }"
                                >
                                    {{ message.type === 'broadcast' ? $t('sms.type_broadcast') : $t('sms.type_autoresponder') }}
                                    <span v-if="message.type === 'autoresponder'" class="ml-1 opacity-75">{{ $t('sms.type_autoresponder_day', { day: message.day }) }}</span>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                {{ message.list_name }}
                            </td>
                            <td class="px-6 py-4">
                                <span 
                                    class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': message.status === 'sent',
                                        'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300': message.status === 'draft',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': message.status === 'scheduled'
                                    }"
                                >
                                    {{ message.status === 'sent' ? $t('sms.status_sent') : (message.status === 'scheduled' ? $t('sms.status_scheduled') : $t('sms.status_draft')) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                {{ message.created_at }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link 
                                        :href="route('sms.edit', message.id)"
                                        class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        :title="$t('common.edit')"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button 
                                        @click="confirmDeleteMessage(message)"
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
                        <tr v-if="messages.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ $t('sms.empty_title') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="messages.links.length > 3" class="flex items-center justify-center border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                <div class="flex gap-1">
                     <Link
                        v-for="(link, i) in messages.links"
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

        <!-- Delete Confirmation Modal -->
        <Modal :show="!!messageToDelete" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-slate-900 dark:text-white">
                    {{ $t('sms.delete_confirm_title') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $t('sms.delete_confirm_description') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal"> {{ $t('common.cancel') }} </SecondaryButton>
                    <DangerButton @click="deleteMessage"> {{ $t('sms.delete_button') }} </DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
