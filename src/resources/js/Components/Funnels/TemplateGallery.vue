<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    show: Boolean,
    templates: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Object,
        default: () => ({}),
    },
    lists: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);

const selectedCategory = ref(null);
const selectedTemplate = ref(null);
const funnelName = ref('');
const selectedListId = ref('');
const step = ref('browse'); // 'browse' or 'configure'

const filteredTemplates = computed(() => {
    if (!selectedCategory.value) return props.templates;
    return props.templates.filter(t => t.category === selectedCategory.value);
});

const getCategoryIcon = (category) => {
    const icons = {
        welcome: '👋',
        reengagement: '🔄',
        launch: '🚀',
        cart_abandonment: '🛒',
        webinar: '🎥',
        onboarding: '📚',
        sales: '💰',
        custom: '📋',
    };
    return icons[category] || '📋';
};

const selectTemplate = (template) => {
    selectedTemplate.value = template;
    funnelName.value = template.name;
    step.value = 'configure';
};

const goBack = () => {
    step.value = 'browse';
    selectedTemplate.value = null;
};

const confirm = () => {
    if (!selectedTemplate.value || !funnelName.value) return;

    emit('select', {
        templateId: selectedTemplate.value.id,
        name: funnelName.value,
        listId: selectedListId.value || null,
    });
};

const close = () => {
    step.value = 'browse';
    selectedTemplate.value = null;
    funnelName.value = '';
    selectedListId.value = '';
    emit('close');
};

watch(() => props.show, (val) => {
    if (!val) {
        step.value = 'browse';
        selectedTemplate.value = null;
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="close"></div>

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative w-full max-w-4xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl transform transition-all">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <button
                                    v-if="step === 'configure'"
                                    @click="goBack"
                                    class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ step === 'browse' ? (t('funnels.templates.title') || 'Szablony lejków') : (t('funnels.templates.configure') || 'Konfiguracja') }}
                                </h2>
                            </div>
                            <button @click="close" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Browse step -->
                        <div v-if="step === 'browse'" class="p-6">
                            <!-- Category filter -->
                            <div class="flex flex-wrap gap-2 mb-6">
                                <button
                                    @click="selectedCategory = null"
                                    :class="[
                                        'px-4 py-2 rounded-full text-sm font-medium transition-colors',
                                        !selectedCategory
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                    ]"
                                >
                                    {{ t('common.all') || 'Wszystkie' }}
                                </button>
                                <button
                                    v-for="(label, key) in categories"
                                    :key="key"
                                    @click="selectedCategory = key"
                                    :class="[
                                        'px-4 py-2 rounded-full text-sm font-medium transition-colors',
                                        selectedCategory === key
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                    ]"
                                >
                                    {{ getCategoryIcon(key) }} {{ label }}
                                </button>
                            </div>

                            <!-- Templates grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[400px] overflow-y-auto">
                                <div
                                    v-for="template in filteredTemplates"
                                    :key="template.id"
                                    @click="selectTemplate(template)"
                                    class="relative p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl hover:border-indigo-500 dark:hover:border-indigo-500 cursor-pointer transition-all group"
                                >
                                    <!-- Featured badge -->
                                    <div v-if="template.is_featured" class="absolute -top-2 -right-2 px-2 py-1 bg-amber-500 text-white text-xs font-bold rounded-full">
                                        ⭐ Featured
                                    </div>

                                    <!-- Icon -->
                                    <div class="text-3xl mb-3">
                                        {{ getCategoryIcon(template.category) }}
                                    </div>

                                    <!-- Name -->
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        {{ template.name }}
                                    </h3>

                                    <!-- Description -->
                                    <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-3">
                                        {{ template.description || 'Brak opisu' }}
                                    </p>

                                    <!-- Meta -->
                                    <div class="flex items-center gap-4 text-xs text-gray-400">
                                        <span class="flex items-center gap-1">
                                            📊 {{ template.step_count }} {{ t('funnels.stats.steps') || 'kroków' }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            👥 {{ template.uses_count }} {{ t('funnels.templates.uses') || 'użyć' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-if="filteredTemplates.length === 0" class="text-center py-12">
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ t('funnels.templates.no_templates') || 'Brak szablonów w tej kategorii' }}
                                </p>
                            </div>
                        </div>

                        <!-- Configure step -->
                        <div v-if="step === 'configure'" class="p-6">
                            <div class="max-w-md mx-auto space-y-6">
                                <!-- Selected template preview -->
                                <div class="p-4 bg-gray-50 dark:bg-gray-750 rounded-xl flex items-center gap-4">
                                    <div class="text-3xl">
                                        {{ getCategoryIcon(selectedTemplate?.category) }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ selectedTemplate?.name }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ selectedTemplate?.step_count }} kroków
                                        </p>
                                    </div>
                                </div>

                                <!-- Funnel name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ t('funnels.builder.name') || 'Nazwa lejka' }} *
                                    </label>
                                    <input
                                        v-model="funnelName"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500"
                                        :placeholder="t('funnels.builder.name_placeholder') || 'Mój nowy lejek'"
                                    />
                                </div>

                                <!-- Trigger list -->
                                <div v-if="lists.length > 0">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ t('funnels.builder.trigger_list') || 'Lista (opcjonalnie)' }}
                                    </label>
                                    <select
                                        v-model="selectedListId"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    >
                                        <option value="">{{ t('common.select') || 'Wybierz...' }}</option>
                                        <option v-for="list in lists" :key="list.id" :value="list.id">
                                            {{ list.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Info -->
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ t('funnels.templates.configure_info') || 'Po utworzeniu możesz edytować lejek i przypisać własne emaile.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                            <button
                                @click="close"
                                class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                {{ t('common.cancel') || 'Anuluj' }}
                            </button>
                            <button
                                v-if="step === 'configure'"
                                @click="confirm"
                                :disabled="!funnelName"
                                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors"
                            >
                                {{ t('funnels.templates.create_funnel') || 'Utwórz lejek' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
