<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    list: Object,
});
</script>

<template>
    <Head :title="list.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ list.name }}
                </h1>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('mailing-lists.edit', list.id)"
                        class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
                    >
                        {{ $t('mailing_lists.edit') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6 md:grid-cols-3">
            <!-- Main Info -->
            <div class="col-span-2 space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                    <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.show_title') }}</h3>
                    <div class="space-y-4">
                        <div v-if="list.description">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.fields.description') }}</label>
                            <p class="mt-1 text-slate-900 dark:text-white">{{ list.description }}</p>
                        </div>
                        
                        <div class="flex gap-8">
                             <div>
                                <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.fields.group') }}</label>
                                <p class="mt-1 text-slate-900 dark:text-white">
                                    {{ list.group ? list.group.name : $t('mailing_lists.fields.no_group') }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.visibility') }}</label>
                                <p class="mt-1 flex items-center gap-2 text-slate-900 dark:text-white">
                                    <span class="inline-flex h-2 w-2 rounded-full" :class="list.is_public ? 'bg-green-500' : 'bg-slate-400'"></span>
                                    {{ list.is_public ? $t('mailing_lists.public') : $t('mailing_lists.private') }}
                                </p>
                            </div>
                        </div>

                        <div v-if="list.tags && list.tags.length">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.fields.tags') }}</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span 
                                    v-for="tag in list.tags" 
                                    :key="tag.id"
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold text-white shadow-sm"
                                    :style="{ backgroundColor: tag.color }"
                                >
                                    {{ tag.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats / Sidebar -->
            <div class="space-y-6">
                 <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                    <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-white">{{ $t('mailing_lists.stats_title') }}</h3>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 last:border-0 last:pb-0 dark:border-slate-800">
                        <span class="text-slate-500 dark:text-slate-400">{{ $t('mailing_lists.table.subscribers') }}</span>
                        <span class="text-xl font-bold text-slate-900 dark:text-white">{{ list.subscribers_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

