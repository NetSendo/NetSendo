<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    pages: {
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

// Filtered lists based on search query
const filteredLists = computed(() => {
    if (!searchQuery.value) return props.lists;
    const query = searchQuery.value.toLowerCase();
    return props.lists.filter(list => 
        list.name.toLowerCase().includes(query)
    );
});

// Get selected list name
const selectedListName = computed(() => {
    if (!selectedList.value) return t('system_pages.all_lists');
    const list = props.lists.find(l => l.id == selectedList.value);
    return list ? list.name : t('system_pages.select_list');
});

// Select a list
const selectList = (listId) => {
    selectedList.value = listId;
    isDropdownOpen.value = false;
    searchQuery.value = '';
};

// Close dropdown
const closeDropdown = () => {
    isDropdownOpen.value = false;
    searchQuery.value = '';
};

watch(selectedList, (newVal) => {
    router.visit(route('settings.system-pages.index', { list_id: newVal }), {
        preserveState: true,
        preserveScroll: true,
    });
});
</script>

<template>
    <Head :title="t('system_pages.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('system_pages.title') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('system_pages.subtitle') }}
                    </p>
                </div>

                <!-- Searchable List Selector -->
                <div class="relative w-full sm:w-80">
                    <button
                        @click="isDropdownOpen = !isDropdownOpen"
                        type="button"
                        class="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-4 py-2 text-left shadow-sm transition-colors hover:bg-gray-50 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        <span class="block truncate">{{ selectedListName }}</span>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        v-if="isDropdownOpen"
                        class="absolute z-50 mt-1 w-full rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800"
                    >
                        <div class="p-2">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('system_pages.search_list')"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                @click.stop
                            />
                        </div>
                        <ul class="max-h-60 overflow-auto py-1">
                            <li
                                @click="selectList(null)"
                                class="cursor-pointer px-4 py-2 text-sm text-gray-900 transition-colors hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedList === null }"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🌐</span>
                                    <span class="font-medium">{{ t('system_pages.all_lists') }}</span>
                                </div>
                            </li>
                            <li class="border-t border-gray-200 dark:border-gray-700"></li>
                            <li v-if="filteredLists.length === 0" class="px-4 py-3 text-center text-sm text-gray-500">
                                {{ t('system_pages.no_lists_found') }}
                            </li>
                            <li
                                v-for="list in filteredLists"
                                :key="list.id"
                                @click="selectList(list.id)"
                                class="cursor-pointer px-4 py-2 text-sm text-gray-900 transition-colors hover:bg-indigo-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedList == list.id }"
                            >
                                <div class="flex items-center justify-between">
                                    <span>{{ list.name }}</span>
                                    <span v-if="selectedList == list.id" class="text-indigo-600 dark:text-indigo-400">✓</span>
                                </div>
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
                            {{ t('system_pages.edit_for_list', { name: lists.find(l => l.id == currentListId)?.name }) }}
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                                <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">{{ t('system_pages.table.name') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ t('system_pages.table.title') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ t('system_pages.table.slug') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ t('system_pages.table.access') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ t('system_pages.table.status') }}</th>
                                        <th scope="col" class="px-6 py-3">{{ t('common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="page in pages" :key="page.id || page.slug" class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            {{ page.name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ page.title }}
                                        </td>
                                        <td class="px-6 py-4 font-mono text-xs">
                                            /{{ page.slug }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="page.access === 'public' ? 'text-green-600' : 'text-orange-500'">
                                                {{ page.access === 'public' ? t('system_pages.access.public') : t('system_pages.access.private') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span :class="page.is_custom ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500'">
                                                {{ page.is_custom ? t('system_pages.customized') : t('system_pages.default') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <Link
                                                :href="route('settings.system-pages.edit', { systemPage: page.id, list_id: currentListId })"
                                                class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                {{ page.is_custom ? t('common.edit') : t('system_pages.customize') }}
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
