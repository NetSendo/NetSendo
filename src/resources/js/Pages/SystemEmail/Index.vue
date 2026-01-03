<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    emails: {
        type: Array,
        required: true,
    },
    lists: {
        type: Array,
        default: () => [],
    },
    currentListId: {
        type: [String, Number, null],
        default: null,
    },
});

const selectedList = ref(props.currentListId);
const searchQuery = ref('');
const isDropdownOpen = ref(false);
const togglingEmail = ref(null);

// Filtered lists
const filteredLists = computed(() => {
    if (!searchQuery.value) return props.lists;
    return props.lists.filter(list =>
        list.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Get selected list name
const selectedListName = computed(() => {
    if (!selectedList.value) return t('system_emails.all_lists');
    const list = props.lists.find(l => l.id == selectedList.value);
    return list ? list.name : t('system_emails.select_list');
});

const selectList = (listId) => {
    selectedList.value = listId;
    isDropdownOpen.value = false;
    searchQuery.value = '';
};

const closeDropdown = () => {
    isDropdownOpen.value = false;
    searchQuery.value = '';
};

// Toggle email active status (only for list-specific emails)
const toggleActive = (email) => {
    if (!props.currentListId) return; // Cannot toggle global emails

    togglingEmail.value = email.id;

    router.post(route('settings.system-emails.toggle', { systemEmail: email.id }), {
        list_id: props.currentListId,
    }, {
        preserveScroll: true,
        onFinish: () => {
            togglingEmail.value = null;
        },
    });
};

watch(selectedList, (newVal) => {
    router.visit(route('settings.system-emails.index', { list_id: newVal }), {
        preserveState: true,
        preserveScroll: true,
    });
});
</script>

<template>
    <Head :title="t('system_emails.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('system_emails.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('system_emails.subtitle') }}
                    </p>
                </div>

                <!-- List Selector -->
                <div class="relative w-full sm:w-80">
                    <button
                        @click="isDropdownOpen = !isDropdownOpen"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-left text-sm shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        <span class="block truncate">{{ selectedListName }}</span>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="transform scale-95 opacity-0"
                        enter-to-class="transform scale-100 opacity-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="transform scale-100 opacity-100"
                        leave-to-class="transform scale-95 opacity-0"
                    >
                        <div v-if="isDropdownOpen" class="absolute right-0 z-50 mt-2 w-full origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700">
                            <div class="p-2">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    :placeholder="t('system_emails.search_list')"
                                    class="w-full rounded-md border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400"
                                    @click.stop
                                />
                            </div>
                            <ul class="max-h-60 overflow-auto py-1">
                                <li
                                    @click="selectList(null)"
                                    class="flex cursor-pointer items-center px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                    :class="{ 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300': selectedList === null }"
                                >
                                    <span class="mr-2 text-lg">🌐</span>
                                    {{ t('system_emails.all_lists') }}
                                </li>
                                <li class="my-1 border-t border-gray-100 dark:border-gray-700"></li>
                                <li v-if="filteredLists.length === 0" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('system_emails.no_lists_found') }}
                                </li>
                                <li
                                    v-for="list in filteredLists"
                                    :key="list.id"
                                    @click="selectList(list.id)"
                                    class="cursor-pointer truncate px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                    :class="{ 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300': selectedList == list.id }"
                                >
                                    {{ list.name }}
                                </li>
                            </ul>
                        </div>
                    </transition>

                    <div v-if="isDropdownOpen" class="fixed inset-0 z-40" @click="closeDropdown"></div>
                </div>
            </div>
        </template>

        <div class="py-8 sm:py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

                <div v-if="currentListId" class="mb-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-700 shadow-sm dark:bg-blue-900/30 dark:text-blue-200">
                    <div class="flex items-center">
                        <svg class="mr-2 h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                        {{ t('system_emails.edit_for_list', { name: lists.find(l => l.id == currentListId)?.name }) }}
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden overflow-hidden bg-white shadow-sm sm:block sm:rounded-lg dark:bg-gray-800">
                     <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">{{ t('system_emails.table.name') }}</th>
                                    <th class="px-6 py-4 font-semibold">{{ t('system_emails.table.subject') }}</th>
                                    <th class="px-6 py-4 font-semibold">{{ t('system_emails.table.slug') }}</th>
                                    <th class="px-6 py-4 font-semibold">{{ t('system_emails.table.active') }}</th>
                                    <th class="px-6 py-4 font-semibold">{{ t('system_emails.table.status') }}</th>
                                    <th class="px-6 py-4 text-right font-semibold">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="email in emails" :key="email.id || email.slug" class="group bg-white transition-colors hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                        {{ email.name }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ email.subject }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ email.slug }}
                                        </code>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div v-if="currentListId" class="flex items-center">
                                            <button
                                                @click="toggleActive(email)"
                                                :disabled="togglingEmail === email.id"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:opacity-50"
                                                :class="email.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
                                                :title="email.is_active ? t('system_emails.click_to_disable') : t('system_emails.click_to_enable')"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="email.is_active ? 'translate-x-5' : 'translate-x-0'"
                                                ></span>
                                            </button>
                                        </div>
                                        <span v-else class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="email.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'">
                                            {{ email.is_active ? t('common.yes') : t('common.no') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                              :class="email.is_custom ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'">
                                            {{ email.is_custom ? t('system_emails.customized') : t('system_emails.default') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <Link
                                            :href="route('settings.system-emails.edit', { systemEmail: email.id, list_id: currentListId })"
                                            class="font-medium text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ email.is_custom ? t('common.edit') : t('system_emails.customize') }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="grid gap-4 sm:hidden">
                    <div v-for="email in emails" :key="email.id || email.slug" class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
                        <div class="p-5">
                            <div class="mb-4 flex items-start justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ email.name }}
                                    </h3>
                                    <code class="mt-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ email.slug }}
                                    </code>
                                </div>
                                <span class="inline-flex flex-shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      :class="email.is_custom ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'">
                                    {{ email.is_custom ? t('system_emails.customized') : t('system_emails.default') }}
                                </span>
                            </div>

                            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-gray-300">{{ t('system_emails.table.subject') }}:</span>
                                {{ email.subject }}
                            </div>

                            <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-700">
                                <div class="flex items-center">
                                    <span class="mr-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('system_emails.table.active') }}:</span>
                                    <!-- Toggle for list context -->
                                    <div v-if="currentListId">
                                        <button
                                            @click="toggleActive(email)"
                                            :disabled="togglingEmail === email.id"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:opacity-50"
                                            :class="email.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
                                        >
                                            <span
                                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                :class="email.is_active ? 'translate-x-5' : 'translate-x-0'"
                                            ></span>
                                        </button>
                                    </div>
                                    <span v-else class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="email.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'">
                                        {{ email.is_active ? t('common.yes') : t('common.no') }}
                                    </span>
                                </div>

                                <Link
                                    :href="route('settings.system-emails.edit', { systemEmail: email.id, list_id: currentListId })"
                                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    {{ email.is_custom ? t('common.edit') : t('system_emails.customize') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
