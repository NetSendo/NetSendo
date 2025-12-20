<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    rules: Object,
    filters: Object,
    triggerEvents: Object,
});

const filters = ref({
    status: props.filters?.status || 'all',
    trigger: props.filters?.trigger || '',
    search: props.filters?.search || '',
});

let searchTimeout = null;

watch(filters, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('automations.index'), {
            status: filters.value.status !== 'all' ? filters.value.status : null,
            trigger: filters.value.trigger || null,
            search: filters.value.search || null,
        }, {
            preserveState: true,
            replace: true,
        });
    }, 300);
}, { deep: true });

const toggleStatus = (rule) => {
    router.post(route('automations.toggle-status', rule.id), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const duplicate = (rule) => {
    if (confirm(t('automations.confirm_duplicate'))) {
        router.post(route('automations.duplicate', rule.id));
    }
};

const deleteRule = (rule) => {
    if (confirm(t('automations.confirm_delete'))) {
        router.delete(route('automations.destroy', rule.id));
    }
};

const getTriggerIcon = (trigger) => {
    const icons = {
        subscriber_signup: '📝',
        subscriber_activated: '✅',
        email_opened: '👁️',
        email_clicked: '🖱️',
        subscriber_unsubscribed: '🚪',
        email_bounced: '📨',
        form_submitted: '📋',
        tag_added: '🏷️',
        tag_removed: '🗑️',
        field_updated: '✏️',
    };
    return icons[trigger] || '⚡';
};
</script>

<template>
    <Head :title="$t('automations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    ⚡ {{ $t('automations.title') }}
                </h2>
                <Link
                    :href="route('automations.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('automations.create') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-6 flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('common.status') }}:</label>
                        <select
                            v-model="filters.status"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="all">{{ $t('common.all') }}</option>
                            <option value="active">{{ $t('common.active') }}</option>
                            <option value="inactive">{{ $t('common.inactive') }}</option>
                        </select>
                    </div>

                    <!-- Trigger Filter -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('automations.trigger') }}:</label>
                        <select
                            v-model="filters.trigger"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">{{ $t('common.all') }}</option>
                            <option v-for="(label, key) in triggerEvents" :key="key" :value="key">
                                {{ getTriggerIcon(key) }} {{ label }}
                            </option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-1 items-center gap-2">
                        <input
                            v-model="filters.search"
                            type="text"
                            :placeholder="$t('common.search') + '...'"
                            class="w-full max-w-xs rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                    </div>
                </div>

                <!-- Rules Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('common.name') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('automations.trigger') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('automations.actions_count') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('automations.executions') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('common.status') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-for="rule in rules.data" :key="rule.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div>
                                        <Link
                                            :href="route('automations.edit', rule.id)"
                                            class="font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                        >
                                            {{ rule.name }}
                                        </Link>
                                        <p v-if="rule.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ rule.description }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="inline-flex items-center gap-1">
                                        {{ getTriggerIcon(rule.trigger_event) }}
                                        {{ rule.trigger_event_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                                    {{ rule.actions_count }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <Link
                                        :href="route('automations.logs', rule.id)"
                                        class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ rule.execution_count }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button
                                        @click="toggleStatus(rule)"
                                        :class="[
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                            rule.is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                rule.is_active ? 'translate-x-5' : 'translate-x-0'
                                            ]"
                                        />
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('automations.edit', rule.id)"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            :title="$t('common.edit')"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="duplicate(rule)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            :title="$t('common.duplicate')"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                        <Link
                                            :href="route('automations.logs', rule.id)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            :title="$t('automations.logs')"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="deleteRule(rule)"
                                            class="text-red-400 hover:text-red-600 dark:hover:text-red-300"
                                            :title="$t('common.delete')"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rules.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center">
                                        <svg class="mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <p class="text-lg font-medium">{{ $t('automations.no_rules') }}</p>
                                        <p class="mt-1 text-sm">{{ $t('automations.no_rules_hint') }}</p>
                                        <Link
                                            :href="route('automations.create')"
                                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                        >
                                            {{ $t('automations.create_first') }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="rules.meta && rules.meta.last_page > 1" class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $t('pagination.showing', { from: rules.meta.from, to: rules.meta.to, total: rules.meta.total }) }}
                            </p>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in rules.links"
                                    :key="link.label"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : link.url
                                                ? 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                                : 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500'
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
