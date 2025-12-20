<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    // Current HTML content of the message
    currentContent: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select-subject', 'close']);

// State
const isOpen = ref(false);
const hint = ref('');
const isGenerating = ref(false);
const suggestions = ref([]);
const error = ref('');

// Model selection state
const integrations = ref([]);
const selectedIntegrationId = ref(null);
const selectedModelId = ref(null);
const loadingModels = ref(false);

// Computed: all models flattened with provider info
const allModels = computed(() => {
    const result = [];
    for (const integration of integrations.value) {
        for (const model of integration.models) {
            result.push({
                integrationId: integration.id,
                modelId: model.id,
                label: `${integration.name}: ${model.name}`,
            });
        }
    }
    return result;
});

// Computed: combined selection key
const selectedKey = computed({
    get: () => {
        if (!selectedIntegrationId.value || !selectedModelId.value) return null;
        return `${selectedIntegrationId.value}:${selectedModelId.value}`;
    },
    set: (val) => {
        if (val) {
            const colonIndex = val.indexOf(':');
            if (colonIndex > -1) {
                selectedIntegrationId.value = parseInt(val.substring(0, colonIndex));
                selectedModelId.value = val.substring(colonIndex + 1);
            }
        }
    },
});

// Fetch active integrations
const fetchModels = async () => {
    if (integrations.value.length > 0) return; // Already fetched
    
    loadingModels.value = true;
    try {
        const response = await axios.get(route('api.ai.active-models'));
        integrations.value = response.data.integrations || [];
        
        // Auto-select first model
        if (integrations.value.length > 0) {
            const first = integrations.value[0];
            selectedIntegrationId.value = first.id;
            selectedModelId.value = first.default_model || first.models[0]?.id || null;
        }
    } catch (err) {
        console.error('Failed to fetch AI integrations:', err);
    } finally {
        loadingModels.value = false;
    }
};

// Generate subject suggestions
const generateSubjects = async () => {
    if (!props.currentContent || props.currentContent.length < 20) {
        error.value = t('messages.ai_assistant.error_missing_content');
        return;
    }

    isGenerating.value = true;
    error.value = '';
    suggestions.value = [];

    try {
        const response = await axios.post(route('api.templates.ai.subject'), {
            content: props.currentContent,
            count: 3,
            hint: hint.value || null,
            integration_id: selectedIntegrationId.value,
            model_id: selectedModelId.value,
        });

        if (response.data.success) {
            suggestions.value = response.data.subjects;
        }
    } catch (err) {
        console.error('Subject generation failed:', err);
        error.value = err.response?.data?.error || t('messages.ai_assistant.error_generic');
    } finally {
        isGenerating.value = false;
    }
};

// Select a suggestion
const selectSubject = (subject) => {
    emit('select-subject', subject);
    isOpen.value = false;
    suggestions.value = [];
    hint.value = '';
};

// Toggle dropdown
const toggle = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchModels(); // Fetch models when opening
    }
    if (!isOpen.value) {
        suggestions.value = [];
        error.value = '';
    }
};
</script>

<template>
    <div class="relative">
        <!-- AI Button -->
        <button
            type="button"
            @click="toggle"
            class="flex items-center justify-center p-1.5 text-purple-500 transition-colors rounded-md hover:bg-purple-50 hover:text-purple-600 dark:hover:bg-purple-900/20"
            :title="$t('messages.ai_assistant.subject_assistant')"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div 
            v-if="isOpen" 
            class="absolute right-0 top-full z-50 mt-2 w-80 rounded-lg border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-800"
        >
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium text-slate-900 dark:text-white">
                    ✨ {{ $t('messages.ai_assistant.subject_assistant') }}
                </h4>
                <button @click="isOpen = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Hint input -->
            <div class="mb-3">
                <input
                    v-model="hint"
                    type="text"
                    maxlength="500"
                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    :placeholder="$t('messages.ai_assistant.subject_hint_placeholder')"
                    @keyup.enter="generateSubjects"
                />
                <div class="mt-1 flex justify-between text-xs text-slate-400">
                    <span>{{ $t('messages.ai_assistant.characters_limit', { count: hint.length }) }}</span>
                </div>
            </div>
            
            <!-- Content status indicator -->
            <div class="mb-3 text-xs">
                <div v-if="currentContent && currentContent.length >= 20" class="flex items-center gap-1 text-emerald-500">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    {{ $t('messages.ai_assistant.content_status_ok', { count: currentContent.length.toLocaleString() }) }}
                </div>
                <div v-else class="flex items-center gap-1 text-amber-500">
                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $t('messages.ai_assistant.content_status_error', { count: currentContent ? currentContent.length : 0 }) }}
                </div>
            </div>
            
            <!-- Model selector (compact) -->
            <div class="mb-3">
                <select
                    v-if="allModels.length > 0"
                    v-model="selectedKey"
                    class="w-full rounded-md border border-slate-200 px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                >
                    <option v-for="model in allModels" :key="model.integrationId + ':' + model.modelId" :value="model.integrationId + ':' + model.modelId">
                        {{ model.label }}
                    </option>
                </select>
                <div v-else-if="loadingModels" class="text-xs text-slate-400">{{ $t('messages.ai_assistant.loading_models') }}</div>
            </div>

            <!-- Generate button -->
            <button
                @click="generateSubjects"
                :disabled="isGenerating"
                class="mb-3 flex w-full items-center justify-center gap-2 rounded-md bg-gradient-to-r from-indigo-500 to-purple-600 px-3 py-2 text-sm font-medium text-white transition-all hover:from-indigo-600 hover:to-purple-700 disabled:opacity-50"
            >
                <svg v-if="isGenerating" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                {{ isGenerating ? $t('messages.ai_assistant.generating') : $t('messages.ai_assistant.generate_button') }}
            </button>

            <!-- Error -->
            <div v-if="error" class="mb-3 rounded-md border border-red-200 bg-red-50 p-2 text-xs text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                {{ error }}
            </div>

            <!-- Suggestions -->
            <div v-if="suggestions.length > 0" class="space-y-2">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $t('messages.ai_assistant.click_to_use') }}</p>
                <button
                    v-for="(subject, index) in suggestions"
                    :key="index"
                    @click="selectSubject(subject)"
                    class="flex w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-700 transition-colors hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-200 dark:hover:border-indigo-600 dark:hover:bg-indigo-900/30"
                >
                    <span class="flex-shrink-0 text-indigo-500">{{ index + 1 }}.</span>
                    <span class="flex-1">{{ subject }}</span>
                </button>
            </div>

            <!-- Empty state info -->
            <p v-if="!isGenerating && suggestions.length === 0 && !error" class="text-center text-xs text-slate-400 dark:text-slate-500">
                {{ $t('messages.ai_assistant.empty_info') }}
            </p>
        </div>
    </div>
</template>
