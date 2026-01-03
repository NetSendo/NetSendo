<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TemplatePreviewCard from '@/Components/TemplatePreviewCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    templates: Object,
    starterTemplates: {
        type: Array,
        default: () => [],
    },
    categories: Array,
    filters: Object,
});

const searchQuery = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category ? Number(props.filters.category) : null);
const showDeleteConfirm = ref(null);
const showStarterTemplates = ref(true);

// Apply filters
const applyFilters = () => {
    router.get(route('templates.index'), {
        search: searchQuery.value || undefined,
        category: selectedCategory.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Handle search
const handleSearch = () => {
    applyFilters();
};

// Handle category filter
const handleCategoryFilter = (categoryId) => {
    selectedCategory.value = categoryId;
    applyFilters();
};

// Quick actions
const handleDuplicate = (template) => {
    router.post(route('templates.duplicate', template.id));
};

// Use starter template (duplicate to user account)
const useStarterTemplate = (template) => {
    router.post(route('templates.duplicate', template.id));
};

const handleDelete = (template) => {
    showDeleteConfirm.value = template.id;
};

const confirmDelete = (templateId) => {
    router.delete(route('templates.destroy', templateId));
    showDeleteConfirm.value = null;
};

// Get category color
const getCategoryColor = (categorySlug) => {
    const category = props.categories?.find(c => c.slug === categorySlug);
    return category?.color || '#6366f1';
};

// Category labels for starter templates
const categoryLabels = {
    'welcome': { label: t('templates.starter.categories.welcome'), color: '#22c55e' },
    'newsletter': { label: t('templates.starter.categories.newsletter'), color: '#0ea5e9' },
    'promotional': { label: t('templates.starter.categories.promotional'), color: '#ef4444' },
    'ecommerce': { label: t('templates.starter.categories.ecommerce'), color: '#f59e0b' },
    'transactional': { label: t('templates.starter.categories.transactional'), color: '#8b5cf6' },
    'notification': { label: t('templates.starter.categories.notification'), color: '#6366f1' },
};

// Filter starter templates by selected category
const filteredStarterTemplates = computed(() => {
    if (!selectedCategory.value) {
        return props.starterTemplates;
    }
    // Try to find category by ID and match by slug for starter templates
    const selectedCat = props.categories?.find(c => c.id === selectedCategory.value);
    if (!selectedCat) return props.starterTemplates;
    return props.starterTemplates.filter(t => t.category === selectedCat.slug);
});
</script>

<template>
    <Head :title="$t('templates.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('templates.title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('templates.subtitle') }}
                    </p>
                </div>
                <Link
                    :href="route('templates.create')"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition-all hover:from-indigo-500 hover:to-purple-500 hover:shadow-indigo-500/40 sm:w-auto sm:py-2.5"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('templates.new_template') }}
                </Link>
            </div>
        </template>

        <!-- Filters Bar -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Search -->
            <div class="relative w-full sm:max-w-xs">
                <svg class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    type="text"
                    :placeholder="$t('common.search')"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder-slate-500"
                />
            </div>

            <!-- Category filter -->
            <div class="flex flex-wrap gap-2">
                <button
                    @click="handleCategoryFilter(null)"
                    :class="selectedCategory === null ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium transition-colors dark:border-slate-700"
                >
                    {{ $t('common.all') }}
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="handleCategoryFilter(cat.id)"
                    :class="selectedCategory === cat.id ? 'ring-2 ring-offset-2' : 'hover:bg-slate-50 dark:hover:bg-slate-700'"
                    class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-all dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    :style="selectedCategory === cat.id ? { borderColor: cat.color, '--tw-ring-color': cat.color } : {}"
                >
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Starter Templates Section -->
        <div v-if="filteredStarterTemplates.length > 0 && showStarterTemplates" class="mb-8">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('templates.starter.title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('templates.starter.subtitle') }}</p>
                    </div>
                </div>
                <button
                    @click="showStarterTemplates = false"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                    :title="$t('common.close')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div
                    v-for="template in filteredStarterTemplates"
                    :key="'starter-' + template.id"
                    class="group relative overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200/50 transition-all hover:-translate-y-1 hover:shadow-lg dark:bg-slate-900 dark:ring-slate-700/50"
                >
                    <!-- Thumbnail -->
                    <div class="relative aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
                        <TemplatePreviewCard
                            :blocks="template.json_structure?.blocks || []"
                            :settings="template.settings"
                            :thumbnail="template.thumbnail"
                            class="h-full w-full transition-transform group-hover:scale-105"
                        />

                        <!-- Category badge -->
                        <div v-if="template.category && categoryLabels[template.category]" class="absolute left-2 top-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                :style="{ backgroundColor: categoryLabels[template.category].color }"
                            >
                                {{ categoryLabels[template.category].label }}
                            </span>
                        </div>

                        <!-- Starter badge -->
                        <div class="absolute right-2 top-2">
                            <span class="flex items-center gap-1 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                {{ $t('templates.starter.premium') }}
                            </span>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                            {{ template.name }}
                        </h3>
                        <button
                            @click="useStarterTemplate(template)"
                            class="mt-2 w-full rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 px-3 py-1.5 text-xs font-semibold text-white transition-all hover:from-amber-400 hover:to-orange-400"
                        >
                            {{ $t('templates.starter.use_template') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collapsed Starter Templates Toggle -->
        <button
            v-if="filteredStarterTemplates.length > 0 && !showStarterTemplates"
            @click="showStarterTemplates = true"
            class="mb-6 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 py-3 text-sm font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
            </svg>
            {{ $t('templates.starter.show_templates') }} ({{ filteredStarterTemplates.length }})
        </button>

        <!-- Empty State -->
        <div v-if="templates.data.length === 0 && starterTemplates.length === 0" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white py-20 text-center dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30">
                <svg class="h-10 w-10 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('templates.empty_title') }}</h3>
            <p class="mt-2 max-w-sm text-slate-500 dark:text-slate-400">{{ $t('templates.empty_description') }}</p>
            <Link
                :href="route('templates.create')"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition-all hover:from-indigo-500 hover:to-purple-500"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ $t('templates.start') }}
            </Link>
        </div>

        <!-- Templates Grid -->
        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="template in templates.data"
                :key="template.id"
                class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/50 transition-all hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-500/10 dark:bg-slate-900 dark:ring-slate-700/50"
            >
                <!-- Thumbnail -->
                <Link :href="route('templates.edit', template.id)" class="relative block aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
                    <TemplatePreviewCard
                        :blocks="template.json_structure?.blocks || []"
                        :settings="template.settings"
                        :thumbnail="template.thumbnail"
                        class="h-full w-full transition-transform group-hover:scale-105"
                    />

                    <!-- Category badge -->
                    <div v-if="template.category_data" class="absolute left-3 top-3">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm"
                            :style="{ backgroundColor: template.category_data.color + 'dd' }"
                        >
                            {{ template.category_data.name }}
                        </span>
                    </div>

                    <!-- Builder badge -->
                    <div v-if="template.has_blocks" class="absolute right-3 top-3">
                        <span class="flex items-center gap-1 rounded-full bg-emerald-500/90 px-2 py-1 text-xs font-medium text-white backdrop-blur-sm">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Builder
                        </span>
                    </div>
                </Link>

                <!-- Info -->
                <div class="flex flex-1 flex-col justify-between p-4">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                            <Link :href="route('templates.edit', template.id)">
                                {{ template.name }}
                            </Link>
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('templates.updated_at') }} {{ template.updated_at }}
                        </p>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <Link
                            :href="route('templates.edit', template.id)"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-100 px-3 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-indigo-100 hover:text-indigo-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-400 sm:py-2 sm:text-xs"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ $t('common.edit') }}
                        </Link>
                        <button
                            @click.prevent="handleDuplicate(template)"
                            class="flex items-center justify-center rounded-lg bg-slate-100 p-3 text-slate-500 transition-colors hover:bg-indigo-100 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-400 sm:p-2"
                            :title="$t('templates.duplicate')"
                        >
                            <svg class="h-5 w-5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <button
                            @click.prevent="handleDelete(template)"
                            class="flex items-center justify-center rounded-lg bg-slate-100 p-3 text-slate-500 transition-colors hover:bg-red-100 hover:text-red-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-red-900/30 dark:hover:text-red-400 sm:p-2"
                            :title="$t('common.delete')"
                        >
                            <svg class="h-5 w-5 sm:h-4 sm:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Delete Confirmation -->
                <div v-if="showDeleteConfirm === template.id" class="absolute inset-0 flex items-center justify-center bg-slate-900/90 backdrop-blur-sm">
                    <div class="p-6 text-center">
                        <p class="mb-4 text-sm text-white">{{ $t('templates.confirm_delete') }}</p>
                        <div class="flex justify-center gap-3">
                            <button
                                @click="showDeleteConfirm = null"
                                class="rounded-lg bg-slate-600 px-4 py-2 text-xs font-medium text-white hover:bg-slate-500"
                            >
                                {{ $t('common.cancel') }}
                            </button>
                            <button
                                @click="confirmDelete(template.id)"
                                class="rounded-lg bg-red-600 px-4 py-2 text-xs font-medium text-white hover:bg-red-500"
                            >
                                {{ $t('common.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="templates.links.length > 3" class="mt-8 flex items-center justify-center">
            <div class="flex gap-1">
                <Link
                    v-for="(link, i) in templates.links"
                    :key="i"
                    :href="link.url || '#'"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium transition-colors"
                    :class="{
                        'bg-indigo-600 text-white shadow-md shadow-indigo-500/30': link.active,
                        'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700': !link.active && link.url,
                        'opacity-50 cursor-not-allowed bg-white dark:bg-slate-800': !link.url
                    }"
                    v-html="link.label"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
