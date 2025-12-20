<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import TemplatePreviewCard from '@/Components/TemplatePreviewCard.vue';

const { t } = useI18n();

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    selectedTemplateId: {
        type: [Number, null],
        default: null,
    },
});

const emit = defineEmits(['close', 'select']);

const searchQuery = ref('');
const selectedCategory = ref(null);

// Separate user and starter templates
const userTemplates = computed(() => 
    props.templates.filter(t => t.user_id !== null)
);

const starterTemplates = computed(() => 
    props.templates.filter(t => t.user_id === null && t.is_public)
);

// Category labels
const categoryLabels = {
    'welcome': { label: 'Powitalny', color: '#22c55e' },
    'newsletter': { label: 'Newsletter', color: '#0ea5e9' },
    'promotional': { label: 'Promocja', color: '#ef4444' },
    'ecommerce': { label: 'E-commerce', color: '#f59e0b' },
    'transactional': { label: 'Transakcyjny', color: '#8b5cf6' },
    'notification': { label: 'Powiadomienie', color: '#ec4899' },
};

// Get unique categories from templates
const categories = computed(() => {
    const cats = new Set();
    props.templates.forEach(t => {
        if (t.category) cats.add(t.category);
    });
    return Array.from(cats);
});

// Filter templates based on search and category
const filteredUserTemplates = computed(() => {
    return userTemplates.value.filter(t => {
        const matchesSearch = !searchQuery.value || 
            t.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = !selectedCategory.value || t.category === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

const filteredStarterTemplates = computed(() => {
    return starterTemplates.value.filter(t => {
        const matchesSearch = !searchQuery.value || 
            t.name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesCategory = !selectedCategory.value || t.category === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});

// Select template
const selectTemplate = (template) => {
    emit('select', template);
};
</script>

<template>
    <Teleport to="body">
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
            @click.self="$emit('close')"
        >
            <div class="w-full max-w-5xl rounded-2xl bg-white shadow-xl dark:bg-slate-800 max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $t('messages.template.modal_title') }}
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $t('messages.template.modal_subtitle') }}
                        </p>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
                    <!-- Search -->
                    <div class="relative flex-1 min-w-[200px]">
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="$t('common.search')"
                            class="w-full rounded-lg border border-slate-200 py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>

                    <!-- Category filters -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            @click="selectedCategory = null"
                            :class="selectedCategory === null ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        >
                            {{ $t('common.all') }}
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat"
                            @click="selectedCategory = cat"
                            :class="selectedCategory === cat ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                            class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        >
                            <span 
                                class="h-2 w-2 rounded-full" 
                                :style="{ backgroundColor: categoryLabels[cat]?.color || '#6b7280' }"
                            ></span>
                            {{ categoryLabels[cat]?.label || cat }}
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-4">
                    <!-- User Templates Section -->
                    <div v-if="filteredUserTemplates.length > 0" class="mb-6">
                        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                            </svg>
                            {{ $t('messages.template.your_templates') }}
                        </h4>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div
                                v-for="template in filteredUserTemplates"
                                :key="template.id"
                                @click="selectTemplate(template)"
                                class="group relative cursor-pointer overflow-hidden rounded-xl border-2 bg-white transition-all hover:-translate-y-1 hover:shadow-lg dark:bg-slate-900"
                                :class="selectedTemplateId === template.id 
                                    ? 'border-indigo-500 ring-2 ring-indigo-500/20' 
                                    : 'border-slate-200 dark:border-slate-700 hover:border-indigo-300'"
                            >
                                <!-- Thumbnail -->
                                <div class="relative aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
                                    <TemplatePreviewCard
                                        v-if="template.json_structure"
                                        :blocks="template.json_structure?.blocks || []"
                                        :settings="template.settings || {}"
                                        :scale="0.15"
                                    />
                                    <img 
                                        v-else-if="template.thumbnail" 
                                        :src="`/storage/${template.thumbnail}`" 
                                        :alt="template.name"
                                        class="h-full w-full object-cover object-top"
                                    />
                                    <div v-else class="flex h-full items-center justify-center">
                                        <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    
                                    <!-- Category badge -->
                                    <div v-if="template.category && categoryLabels[template.category]" class="absolute left-2 top-2">
                                        <span 
                                            class="rounded-full px-2 py-0.5 text-[10px] font-medium text-white"
                                            :style="{ backgroundColor: categoryLabels[template.category]?.color }"
                                        >
                                            {{ categoryLabels[template.category].label }}
                                        </span>
                                    </div>

                                    <!-- Selected check -->
                                    <div v-if="selectedTemplateId === template.id" class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-white">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Info -->
                                <div class="p-3">
                                    <h5 class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ template.name }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Starter Templates Section -->
                    <div v-if="filteredStarterTemplates.length > 0">
                        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                            <svg class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                            </svg>
                            {{ $t('messages.template.starter_templates') }}
                        </h4>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div
                                v-for="template in filteredStarterTemplates"
                                :key="'starter-' + template.id"
                                @click="selectTemplate(template)"
                                class="group relative cursor-pointer overflow-hidden rounded-xl border-2 bg-white transition-all hover:-translate-y-1 hover:shadow-lg dark:bg-slate-900"
                                :class="selectedTemplateId === template.id 
                                    ? 'border-indigo-500 ring-2 ring-indigo-500/20' 
                                    : 'border-slate-200 dark:border-slate-700 hover:border-indigo-300'"
                            >
                                <!-- Thumbnail -->
                                <div class="relative aspect-[4/3] w-full overflow-hidden bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-800 dark:to-slate-900">
                                    <TemplatePreviewCard
                                        v-if="template.json_structure"
                                        :blocks="template.json_structure?.blocks || []"
                                        :settings="template.settings || {}"
                                        :scale="0.15"
                                    />
                                    
                                    <!-- Category badge -->
                                    <div v-if="template.category && categoryLabels[template.category]" class="absolute left-2 top-2">
                                        <span 
                                            class="rounded-full px-2 py-0.5 text-[10px] font-medium text-white"
                                            :style="{ backgroundColor: categoryLabels[template.category]?.color }"
                                        >
                                            {{ categoryLabels[template.category].label }}
                                        </span>
                                    </div>

                                    <!-- Starter badge -->
                                    <div class="absolute right-2 top-2">
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                            Premium
                                        </span>
                                    </div>

                                    <!-- Selected check -->
                                    <div v-if="selectedTemplateId === template.id" class="absolute bottom-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-white">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                                
                                <!-- Info -->
                                <div class="p-3">
                                    <h5 class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ template.name }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="filteredUserTemplates.length === 0 && filteredStarterTemplates.length === 0" class="flex flex-col items-center justify-center py-12">
                        <svg class="h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $t('messages.template.no_templates') }}
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-200 p-4 dark:border-slate-700">
                    <button
                        @click="$emit('close')"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                    >
                        {{ $t('common.cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
