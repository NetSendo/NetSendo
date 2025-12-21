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
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('system_emails.subtitle') }}
                    </p>
                </div>

                <!-- List Selector -->
                <div class="relative w-full sm:w-80">
                    <button
                        @click="isDropdownOpen = !isDropdownOpen"
                        type="button"
                        class="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-4 py-2 text-left shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <span class="block truncate">{{ selectedListName }}</span>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="isDropdownOpen" class="absolute z-50 mt-1 w-full rounded-md bg-white shadow-lg dark:bg-gray-800">
                        <div class="p-2">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('system_emails.search_list')"
                                class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                @click.stop
                            />
                        </div>
                        <ul class="max-h-60 overflow-auto py-1">
                            <li
                                @click="selectList(null)"
                                class="cursor-pointer px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-gray-700"
                                :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedList === null }"
                            >
                                <span class="text-lg mr-2">🌐</span>{{ t('system_emails.all_lists') }}
                            </li>
                            <li class="border-t dark:border-gray-700"></li>
                            <li v-if="filteredLists.length === 0" class="px-4 py-3 text-center text-sm text-gray-500">
                                {{ t('system_emails.no_lists_found') }}
                            </li>
                            <li
                                v-for="list in filteredLists"
                                :key="list.id"
                                @click="selectList(list.id)"
                                class="cursor-pointer px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-gray-700"
                                :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedList == list.id }"
                            >
                                {{ list.name }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="isDropdownOpen" class="fixed inset-0 z-40" @click="closeDropdown"></div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <div v-if="currentListId" class="mb-4 rounded-md bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-200">
                            {{ t('system_emails.edit_for_list', { name: lists.find(l => l.id == currentListId)?.name }) }}
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">{{ t('system_emails.table.name') }}</th>
                                        <th class="px-6 py-3">{{ t('system_emails.table.subject') }}</th>
                                        <th class="px-6 py-3">{{ t('system_emails.table.slug') }}</th>
                                        <th class="px-6 py-3">{{ t('system_emails.table.active') }}</th>
                                        <th class="px-6 py-3">{{ t('system_emails.table.status') }}</th>
                                        <th class="px-6 py-3">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="email in emails" :key="email.id || email.slug" class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            {{ email.name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ email.subject }}
                                        </td>
                                        <td class="px-6 py-4 font-mono text-xs">
                                            {{ email.slug }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <!-- Toggle switch for list-specific emails only -->
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
                                            <!-- Static display for global emails (cannot toggle) -->
                                            <span v-else :class="email.is_active ? 'text-green-600' : 'text-red-500'">
                                                {{ email.is_active ? t('common.yes') : t('common.no') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="email.is_custom ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500'">
                                                {{ email.is_custom ? t('system_emails.customized') : t('system_emails.default') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <Link
                                                :href="route('settings.system-emails.edit', { systemEmail: email.id, list_id: currentListId })"
                                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                {{ email.is_custom ? t('common.edit') : t('system_emails.customize') }}
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
