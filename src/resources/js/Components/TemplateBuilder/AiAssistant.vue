<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    selectedBlock: Object,
});

const emit = defineEmits(['close', 'insert-content', 'add-block']);

// AI state
const prompt = ref('');
const tone = ref('casual');
const sectionType = ref('promotional');
const isGenerating = ref(false);
const generatedContent = ref(null);
const activeTab = ref('content'); // content, section

// Model selection state
const integrations = ref([]);
const selectedIntegrationId = ref(null);
const selectedModelId = ref(null);
const loadingModels = ref(false);

// Computed: current integration object
const selectedIntegration = computed(() => {
    return integrations.value.find(i => i.id === selectedIntegrationId.value);
});

// Computed: available models for selected integration
const availableModels = computed(() => {
    return selectedIntegration.value?.models || [];
});

// Fetch active integrations on mount
onMounted(async () => {
    loadingModels.value = true;
    try {
        const response = await axios.get(route('api.ai.active-models'));
        integrations.value = response.data.integrations || [];
        
        // Auto-select first integration and its default model
        if (integrations.value.length > 0) {
            selectedIntegrationId.value = integrations.value[0].id;
            const firstIntegration = integrations.value[0];
            selectedModelId.value = firstIntegration.default_model || firstIntegration.models[0]?.id || null;
        }
    } catch (error) {
        console.error('Failed to fetch AI integrations:', error);
    } finally {
        loadingModels.value = false;
    }
});

// Watch integration changes to update model selection
watch(selectedIntegrationId, (newId) => {
    if (newId) {
        const integration = integrations.value.find(i => i.id === newId);
        if (integration) {
            selectedModelId.value = integration.default_model || integration.models[0]?.id || null;
        }
    }
});

// Tone options
const toneOptions = [
    { value: 'casual', icon: '😊' },
    { value: 'formal', icon: '👔' },
    { value: 'persuasive', icon: '🎯' },
];

// Section type options
const sectionTypes = [
    { value: 'promotional' },
    { value: 'welcome' },
    { value: 'newsletter' },
    { value: 'product' },
];

// Generate content
const generateContent = async () => {
    if (!prompt.value || isGenerating.value) return;

    isGenerating.value = true;
    generatedContent.value = null;

    try {
        const response = await axios.post(route('api.templates.ai.content'), {
            prompt: prompt.value,
            block_type: props.selectedBlock?.type || 'text',
            tone: tone.value,
            integration_id: selectedIntegrationId.value,
            model_id: selectedModelId.value,
        });

        if (response.data.success) {
            generatedContent.value = {
                type: 'content',
                html: response.data.content,
            };
        }
    } catch (error) {
        console.error('AI generation failed:', error);
        generatedContent.value = {
            type: 'error',
            message: error.response?.data?.error || t('template_builder.ai_error'),
        };
    } finally {
        isGenerating.value = false;
    }
};

// Generate section
const generateSection = async () => {
    if (!prompt.value || isGenerating.value) return;

    isGenerating.value = true;
    generatedContent.value = null;

    try {
        const response = await axios.post(route('api.templates.ai.section'), {
            description: prompt.value,
            section_type: sectionType.value,
            integration_id: selectedIntegrationId.value,
            model_id: selectedModelId.value,
        });

        if (response.data.success) {
            generatedContent.value = {
                type: 'section',
                data: response.data.section,
            };
        }
    } catch (error) {
        console.error('AI generation failed:', error);
        generatedContent.value = {
            type: 'error',
            message: error.response?.data?.error || t('template_builder.ai_error'),
        };
    } finally {
        isGenerating.value = false;
    }
};

// Insert generated content
const insertContent = () => {
    if (!generatedContent.value) return;

    if (generatedContent.value.type === 'content') {
        emit('insert-content', generatedContent.value.html, props.selectedBlock?.id);
    } else if (generatedContent.value.type === 'section') {
        // Add multiple blocks for section
        const section = generatedContent.value.data;
        
        // Add text block with headline
        emit('add-block', 'text', null);
        setTimeout(() => {
            emit('insert-content', `<h2>${section.headline}</h2>${section.text}`, null);
        }, 100);
        
        // Add button block
        if (section.buttonText) {
            emit('add-block', 'button', null);
        }
    }

    generatedContent.value = null;
    prompt.value = '';
};

// Quick prompts
const quickPrompts = computed(() => {
    return t('template_builder.quick_prompts_list', { returnObjects: true }) || [];
});
</script>

<template>
    <!-- Slide-over panel -->
    <div class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="$emit('close')"></div>

        <!-- Panel -->
        <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
            <div class="w-screen max-w-md">
                <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl dark:bg-slate-900">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3 text-white">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">{{ $t('template_builder.ai_assistant') }}</h2>
                                <p class="text-sm text-indigo-100">{{ $t('template_builder.ai_subtitle') }}</p>
                            </div>
                        </div>
                        <button @click="$emit('close')" class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-slate-200 dark:border-slate-800">
                        <button 
                            @click="activeTab = 'content'"
                            :class="activeTab === 'content' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500'"
                            class="flex-1 border-b-2 px-4 py-3 text-sm font-medium"
                        >
                            {{ $t('template_builder.generate_content') }}
                        </button>
                        <button 
                            @click="activeTab = 'section'"
                            :class="activeTab === 'section' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500'"
                            class="flex-1 border-b-2 px-4 py-3 text-sm font-medium"
                        >
                            {{ $t('template_builder.generate_section') }}
                        </button>
                    </div>

                    <!-- Model Selection -->
                    <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $t('messages.ai_assistant.select_model') || 'Model AI' }}
                        </label>
                        
                        <!-- Loading state -->
                        <div v-if="loadingModels" class="flex items-center gap-2 text-sm text-slate-500">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ $t('template_builder.ai_loading') }}
                        </div>
                        
                        <!-- No integrations -->
                        <div v-else-if="integrations.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                            {{ $t('template_builder.ai_no_integrations') }}
                        </div>
                        
                        <!-- Integration & Model selectors -->
                        <div v-else class="space-y-2">
                            <select
                                v-model="selectedIntegrationId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option v-for="integration in integrations" :key="integration.id" :value="integration.id">
                                    {{ integration.name }}
                                </option>
                            </select>
                            
                            <select
                                v-model="selectedModelId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option v-for="model in availableModels" :key="model.id" :value="model.id">
                                    {{ model.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <!-- Prompt input -->
                        <div class="mb-4">
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $t('template_builder.describe_what_you_need') }}
                            </label>
                            <textarea 
                                v-model="prompt"
                                rows="4"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                :placeholder="$t('template_builder.ai_prompt_placeholder')"
                            ></textarea>
                        </div>

                        <!-- Quick prompts -->
                        <div class="mb-4">
                            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ $t('template_builder.quick_prompts') }}
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    v-for="qp in quickPrompts" 
                                    :key="qp"
                                    @click="prompt = qp"
                                    class="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-600 transition-colors hover:bg-indigo-100 hover:text-indigo-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-900/50"
                                >
                                    {{ qp }}
                                </button>
                            </div>
                        </div>

                        <!-- Tone selector (for content tab) -->
                        <div v-if="activeTab === 'content'" class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $t('template_builder.select_tone') }}
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button 
                                    v-for="opt in toneOptions" 
                                    :key="opt.value"
                                    @click="tone = opt.value"
                                    :class="tone === opt.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                    class="flex flex-col items-center gap-1 rounded-lg border p-3 transition-all"
                                >
                                    <span class="text-xl">{{ opt.icon }}</span>
                                    <span class="text-xs font-medium">{{ $t(`template_builder.tone_${opt.value}`) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Section type selector (for section tab) -->
                        <div v-if="activeTab === 'section'" class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $t('template_builder.section_type') }}
                            </label>
                            <select 
                                v-model="sectionType"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option v-for="st in sectionTypes" :key="st.value" :value="st.value">
                                    {{ $t(`template_builder.section_types.${st.value}`) }}
                                </option>
                            </select>
                        </div>

                        <!-- Generate button -->
                        <button 
                            @click="activeTab === 'content' ? generateContent() : generateSection()"
                            :disabled="!prompt || isGenerating"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-3 text-sm font-medium text-white transition-all hover:from-indigo-600 hover:to-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg v-if="isGenerating" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ isGenerating ? $t('template_builder.generating') : $t('template_builder.generate') }}
                        </button>

                        <!-- Generated content preview -->
                        <div v-if="generatedContent" class="mt-6">
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('template_builder.generated_content') }}
                                </label>
                                <button 
                                    @click="generatedContent = null"
                                    class="text-xs text-slate-400 hover:text-slate-600"
                                >
                                    {{ $t('common.clear') }}
                                </button>
                            </div>

                            <!-- Error state -->
                            <div v-if="generatedContent.type === 'error'" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                                {{ generatedContent.message }}
                            </div>

                            <!-- Content preview -->
                            <div v-else-if="generatedContent.type === 'content'" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div class="prose prose-sm max-w-none dark:prose-invert" v-html="generatedContent.html"></div>
                            </div>

                            <!-- Section preview -->
                            <div v-else-if="generatedContent.type === 'section'" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                                <div>
                                    <label class="text-xs text-slate-500">{{ $t('template_builder.headline_label') }}</label>
                                    <p class="font-bold text-slate-900 dark:text-white">{{ generatedContent.data.headline }}</p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">{{ $t('template_builder.content_label') }}</label>
                                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="generatedContent.data.text"></div>
                                </div>
                                <div v-if="generatedContent.data.buttonText">
                                    <label class="text-xs text-slate-500">{{ $t('template_builder.button_label') }}</label>
                                    <p class="text-indigo-600 dark:text-indigo-400">{{ generatedContent.data.buttonText }}</p>
                                </div>
                            </div>

                            <!-- Insert button -->
                            <button 
                                v-if="generatedContent.type !== 'error'"
                                @click="insertContent"
                                class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-500"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ $t('template_builder.insert_content') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
