<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSpeechRecognition, getSpeechLang } from '@/Composables/useSpeechRecognition';

const { t, locale } = useI18n();

// Voice dictation
const { isListening, isSupported, transcript, interimTranscript, toggleListening } = useSpeechRecognition();

// Append transcript to prompt when voice recognition completes
watch(transcript, (newTranscript) => {
    if (newTranscript) {
        prompt.value = prompt.value 
            ? prompt.value.trim() + ' ' + newTranscript 
            : newTranscript;
    }
});

const props = defineProps({
    // Current HTML content of the message for context
    currentContent: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'insert-content', 'replace-content']);

// AI state
const prompt = ref('');
const tone = ref('casual');
const isGenerating = ref(false);
const generatedContent = ref(null);
const activeMode = ref('text'); // 'text' or 'template'
const withFormatting = ref(true); // HTML formatting toggle
const copySuccess = ref(false); // Copy feedback

// Comparison modal state (for template mode)
const showComparisonModal = ref(false);

// Model selection state
const integrations = ref([]);
const selectedIntegrationId = ref(null);
const selectedModelId = ref(null);
const loadingModels = ref(false);

// Placeholder selection state
const standardPlaceholders = ref([]);
const customPlaceholders = ref([]);
const selectedCustomPlaceholders = ref([]); // IDs of selected custom placeholders
const showPlaceholderSection = ref(false);
const loadingPlaceholders = ref(false);

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
    loadingPlaceholders.value = true;
    
    try {
        // Fetch AI integrations
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
    
    try {
        // Fetch available placeholders
        const placeholderResponse = await axios.get(route('api.placeholders'));
        const data = placeholderResponse.data;
        
        standardPlaceholders.value = data.standard || [];
        customPlaceholders.value = data.custom || [];
    } catch (error) {
        console.error('Failed to fetch placeholders:', error);
    } finally {
        loadingPlaceholders.value = false;
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

// Tone options (computed for reactivity to language changes)
const toneOptions = computed(() => [
    { value: 'casual', label: t('messages.ai_assistant.tones.casual'), icon: '😊' },
    { value: 'formal', label: t('messages.ai_assistant.tones.formal'), icon: '👔' },
    { value: 'persuasive', label: t('messages.ai_assistant.tones.persuasive'), icon: '🎯' },
]);

// Mode options (computed for reactivity to language changes)
const modeOptions = computed(() => [
    { 
        value: 'text', 
        label: t('messages.ai_assistant.modes.text'),
        description: t('messages.ai_assistant.modes.text_desc'),
        icon: '📝'
    },
    { 
        value: 'template', 
        label: t('messages.ai_assistant.modes.template'),
        description: t('messages.ai_assistant.modes.template_desc'),
        icon: '🎨'
    },
]);

// Quick prompts based on mode
const quickPrompts = computed(() => {
    if (activeMode.value === 'text') {
        return [
            t('messages.ai_assistant.prompts.quick.welcome_sub'),
            t('messages.ai_assistant.prompts.quick.benefits'),
            t('messages.ai_assistant.prompts.quick.thanks_purchase'),
            t('messages.ai_assistant.prompts.quick.cta_contact'),
        ];
    } else {
        return [
            t('messages.ai_assistant.prompts.quick.professional_newsletter'),
            t('messages.ai_assistant.prompts.quick.welcome_client'),
            t('messages.ai_assistant.prompts.quick.promo_list'),
            t('messages.ai_assistant.prompts.quick.cta_end'),
        ];
    }
});

const generatedContentRef = ref(null);

// Generate content
const generateContent = async () => {
    if (!prompt.value || isGenerating.value) return;

    isGenerating.value = true;
    generatedContent.value = null;

    // Build selected placeholders array for API
    const selectedPlaceholders = customPlaceholders.value
        .filter(p => selectedCustomPlaceholders.value.includes(p.id))
        .map(p => ({
            name: p.name,
            label: p.label,
            description: p.description || p.label
        }));

    try {
        const response = await axios.post(route('api.templates.ai.message-content'), {
            prompt: prompt.value,
            mode: activeMode.value,
            current_content: props.currentContent || null,
            tone: tone.value,
            with_formatting: withFormatting.value,
            integration_id: selectedIntegrationId.value,
            model_id: selectedModelId.value,
            placeholders: selectedPlaceholders,
        });

        if (response.data.success) {
            generatedContent.value = {
                type: 'content',
                html: response.data.content,
            };
            
            // For template mode, show comparison modal
            if (activeMode.value === 'template' && props.currentContent) {
                showComparisonModal.value = true;
            } else {
                // Scroll to generated content
                setTimeout(() => {
                    generatedContentRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    } catch (error) {
        console.error('AI generation failed:', error);
        generatedContent.value = {
            type: 'error',
            message: error.response?.data?.error || t('messages.ai_assistant.error_generating'),
        };
        // Scroll to error
        setTimeout(() => {
            generatedContentRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    } finally {
        isGenerating.value = false;
    }
};

// Copy to clipboard
const copyToClipboard = async () => {
    if (!generatedContent.value || generatedContent.value.type === 'error') return;
    
    try {
        // Copy as plain text or HTML based on formatting preference
        const textToCopy = withFormatting.value 
            ? generatedContent.value.html 
            : generatedContent.value.html.replace(/<[^>]*>/g, '');
        
        await navigator.clipboard.writeText(textToCopy);
        copySuccess.value = true;
        setTimeout(() => { copySuccess.value = false; }, 2000);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

// Insert generated content (append to current)
const insertContent = () => {
    if (!generatedContent.value || generatedContent.value.type === 'error') return;
    emit('insert-content', generatedContent.value.html);
    generatedContent.value = null;
    prompt.value = '';
};

// Replace all content with generated
const replaceContent = () => {
    if (!generatedContent.value || generatedContent.value.type === 'error') return;
    emit('replace-content', generatedContent.value.html);
    generatedContent.value = null;
    prompt.value = '';
    showComparisonModal.value = false;
};

// Close comparison and reject
const rejectComparison = () => {
    showComparisonModal.value = false;
};

// Accept comparison
const acceptComparison = () => {
    replaceContent();
};
</script>

<template>
    <!-- Slide-over panel -->
    <div class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="$emit('close')"></div>

        <!-- Panel -->
        <div class="absolute inset-y-0 right-0 flex max-w-full pl-10">
            <div class="w-screen max-w-2xl lg:max-w-3xl">
                <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl dark:bg-slate-900 custom-scrollbar">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3 text-white">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold">{{ $t('messages.ai_assistant.title') }}</h2>
                                <p class="text-sm text-indigo-100">{{ $t('messages.ai_assistant.subtitle') }}</p>
                            </div>
                        </div>
                        <button @click="$emit('close')" class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mode Selection -->
                    <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $t('messages.ai_assistant.select_mode') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                v-for="mode in modeOptions" 
                                :key="mode.value"
                                @click="activeMode = mode.value; generatedContent = null"
                                :class="activeMode === mode.value 
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-300' 
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-all"
                            >
                                <span class="text-xl">{{ mode.icon }}</span>
                                <span class="text-xs font-medium">{{ mode.label }}</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ modeOptions.find(m => m.value === activeMode)?.description }}
                        </p>
                    </div>

                    <!-- Model Selection -->
                    <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $t('messages.ai_assistant.select_model') }}
                        </label>
                        
                        <!-- Loading state -->
                        <div v-if="loadingModels" class="flex items-center gap-2 text-sm text-slate-500">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ $t('common.loading') }}
                        </div>
                        
                        <!-- No integrations -->
                        <div v-else-if="integrations.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ $t('messages.ai_assistant.no_models_available') }}
                            </div>
                        </div>
                        
                        <!-- Integration & Model selectors -->
                        <div v-else class="space-y-2">
                            <!-- Integration selector -->
                            <select
                                v-model="selectedIntegrationId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option v-for="integration in integrations" :key="integration.id" :value="integration.id">
                                    {{ integration.name }}
                                </option>
                            </select>
                            
                            <!-- Model selector -->
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
                    <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                        <!-- Prompt input with voice dictation -->
                        <div class="mb-4">
                            <div class="mb-1 flex items-center justify-between">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('messages.ai_assistant.describe_content') }}
                                </label>
                                <!-- Voice dictation button -->
                                <button
                                    v-if="isSupported"
                                    type="button"
                                    @click="toggleListening(getSpeechLang(locale))"
                                    :title="isListening ? $t('messages.ai_assistant.voice.stop') : $t('messages.ai_assistant.voice.start')"
                                    class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium transition-all"
                                    :class="isListening 
                                        ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' 
                                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-100 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-400'"
                                >
                                    <svg 
                                        class="h-4 w-4" 
                                        :class="{ 'animate-pulse': isListening }"
                                        fill="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                                        <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                                    </svg>
                                    <span v-if="isListening">{{ $t('messages.ai_assistant.voice.listening') }}</span>
                                </button>
                                <span v-else class="text-xs text-slate-400" :title="$t('messages.ai_assistant.voice.not_supported')">
                                    🎤 {{ $t('messages.ai_assistant.voice.not_supported_short') }}
                                </span>
                            </div>
                            <div class="relative">
                                <textarea 
                                    v-model="prompt"
                                    rows="4"
                                    class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:bg-slate-800 dark:text-white custom-scrollbar"
                                    :class="isListening 
                                        ? 'border-red-300 dark:border-red-700' 
                                        : 'border-slate-200 dark:border-slate-700'"
                                    :placeholder="activeMode === 'text' 
                                        ? $t('messages.ai_assistant.prompts.text_placeholder') 
                                        : $t('messages.ai_assistant.prompts.template_placeholder')"
                                ></textarea>
                                <!-- Interim transcript indicator -->
                                <div v-if="interimTranscript" class="absolute bottom-2 left-3 right-3 rounded bg-slate-100 px-2 py-1 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                                    <span class="animate-pulse">🎤</span> {{ interimTranscript }}
                                </div>
                            </div>
                        </div>

                        <!-- Quick prompts -->
                        <div class="mb-4">
                            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ $t('messages.ai_assistant.quick_prompts') }}
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

                        <!-- Tone selector -->
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $t('messages.ai_assistant.select_tone') }}
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
                                    <span class="text-xs font-medium">{{ opt.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Placeholder Selection Section -->
                        <div class="mb-4">
                            <button 
                                @click="showPlaceholderSection = !showPlaceholderSection"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white p-3 text-left transition-colors hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🧩</span>
                                    <div>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                            {{ $t('messages.ai_assistant.placeholders.section_title') }}
                                        </span>
                                        <span v-if="selectedCustomPlaceholders.length > 0" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300">
                                            +{{ selectedCustomPlaceholders.length }}
                                        </span>
                                    </div>
                                </div>
                                <svg 
                                    class="h-4 w-4 text-slate-400 transition-transform" 
                                    :class="{ 'rotate-180': showPlaceholderSection }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <!-- Expandable content -->
                            <div v-if="showPlaceholderSection" class="mt-2 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50">
                                <!-- Loading state -->
                                <div v-if="loadingPlaceholders" class="flex items-center gap-2 text-sm text-slate-500">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    {{ $t('messages.ai_assistant.placeholders.loading') }}
                                </div>
                                
                                <template v-else>
                                    <!-- Standard placeholders (always active) -->
                                    <div>
                                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {{ $t('messages.ai_assistant.placeholders.standard_title') }}
                                        </h4>
                                        <div class="space-y-1">
                                            <div 
                                                v-for="p in standardPlaceholders" 
                                                :key="p.name"
                                                class="flex items-center gap-2 rounded bg-white px-2 py-1.5 text-xs dark:bg-slate-700"
                                            >
                                                <span class="text-emerald-500">✓</span>
                                                <code class="font-mono text-indigo-600 dark:text-indigo-400">[[{{ p.name }}]]</code>
                                                <span class="text-slate-500 dark:text-slate-400">- {{ p.description || p.label }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Custom placeholders (selectable) -->
                                    <div v-if="customPlaceholders.length > 0">
                                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {{ $t('messages.ai_assistant.placeholders.custom_title') }}
                                        </h4>
                                        <div class="space-y-1">
                                            <label 
                                                v-for="p in customPlaceholders" 
                                                :key="p.id"
                                                class="flex cursor-pointer items-center gap-2 rounded bg-white px-2 py-1.5 text-xs transition-colors hover:bg-indigo-50 dark:bg-slate-700 dark:hover:bg-slate-600"
                                            >
                                                <input 
                                                    type="checkbox" 
                                                    :value="p.id"
                                                    v-model="selectedCustomPlaceholders"
                                                    class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                                <code class="font-mono text-indigo-600 dark:text-indigo-400">[[{{ p.name }}]]</code>
                                                <span class="text-slate-500 dark:text-slate-400">- {{ p.description || p.label }}</span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Empty state for custom placeholders -->
                                    <div v-else class="text-center text-xs text-slate-400">
                                        {{ $t('messages.ai_assistant.placeholders.empty_custom') }} 
                                        <a href="/settings/fields" class="text-indigo-500 hover:underline">{{ $t('messages.ai_assistant.placeholders.add_fields') }}</a>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Formatting toggle (for text mode) -->
                        <div v-if="activeMode === 'text'" class="mb-4">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 transition-colors hover:border-indigo-300 dark:border-slate-700 dark:hover:border-indigo-600">
                                <input 
                                    type="checkbox" 
                                    v-model="withFormatting"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ $t('messages.ai_assistant.formatting.label') }}
                                    </span>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ withFormatting ? $t('messages.ai_assistant.formatting.with_html') : $t('messages.ai_assistant.formatting.plain_text') }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <!-- Current content indicator (for template mode) -->
                        <div v-if="activeMode === 'template'" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                            <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span v-if="currentContent && currentContent.length > 50">
                                    {{ $t('messages.ai_assistant.context.modify_existing', { count: currentContent.length.toLocaleString() }) }}
                                </span>
                                <span v-else>
                                    {{ $t('messages.ai_assistant.context.create_new') }}
                                </span>
                            </div>
                        </div>

                        <!-- Generate button -->
                        <button 
                            @click="generateContent"
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
                            {{ isGenerating ? $t('messages.ai_assistant.generating') : $t('messages.ai_assistant.generate') }}
                        </button>

                        <!-- Generated content preview -->
                        <div v-if="generatedContent" class="mt-6" ref="generatedContentRef">
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('messages.ai_assistant.generated_content') }}
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
                            <div v-else class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-slate-800">
                                <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                    <div class="prose prose-sm max-w-none text-slate-700 dark:prose-invert dark:text-slate-200" v-html="generatedContent.html"></div>
                                </div>
                            </div>

                            <!-- Action buttons for TEXT mode -->
                            <div v-if="generatedContent.type !== 'error' && activeMode === 'text'" class="mt-3 grid grid-cols-2 gap-2">
                                <button 
                                    @click="insertContent"
                                    class="flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    {{ $t('messages.ai_assistant.actions.insert') }}
                                </button>
                                <button 
                                    @click="copyToClipboard"
                                    class="flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    <svg v-if="!copySuccess" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ copySuccess ? $t('messages.ai_assistant.actions.copied') : $t('messages.ai_assistant.actions.copy') }}
                                </button>
                            </div>

                            <!-- Action buttons for TEMPLATE mode -->
                            <div v-if="generatedContent.type !== 'error' && activeMode === 'template'" class="mt-3 flex gap-2">
                                <button 
                                    @click="showComparisonModal = true"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-500"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    {{ $t('messages.ai_assistant.actions.compare_replace') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Modal (for template mode) -->
    <Teleport to="body">
        <div 
            v-if="showComparisonModal && generatedContent && generatedContent.type !== 'error'" 
            class="fixed inset-0 z-[60] flex items-center justify-center overflow-hidden bg-black/60 backdrop-blur-sm"
        >
            <div class="m-4 flex h-[90vh] w-full max-w-7xl flex-col rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 dark:border-slate-800">
                    <div class="flex items-center gap-3 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <div>
                            <h2 class="text-lg font-semibold">{{ $t('messages.ai_assistant.comparison.title') }}</h2>
                            <p class="text-sm text-indigo-100">{{ $t('messages.ai_assistant.comparison.subtitle') }}</p>
                        </div>
                    </div>
                    <button @click="rejectComparison" class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Comparison Content -->
                <div class="flex flex-1 overflow-hidden">
                    <!-- Original Content -->
                    <div class="flex w-1/2 flex-col border-r border-slate-200 dark:border-slate-700">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-xs dark:bg-slate-700">1</span>
                                {{ $t('messages.ai_assistant.comparison.original_title') }}
                            </h3>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                            <div v-if="currentContent && currentContent.length > 0" class="prose prose-sm max-w-none dark:prose-invert" v-html="currentContent"></div>
                            <div v-else class="flex h-full items-center justify-center">
                                <p class="text-sm text-slate-400">{{ $t('messages.ai_assistant.comparison.original_empty') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- New Content -->
                    <div class="flex w-1/2 flex-col">
                        <div class="border-b border-slate-200 bg-emerald-50 px-4 py-3 dark:border-slate-700 dark:bg-emerald-900/20">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-200 text-xs dark:bg-emerald-800">2</span>
                                {{ $t('messages.ai_assistant.comparison.new_title') }}
                            </h3>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                            <div class="prose prose-sm max-w-none dark:prose-invert" v-html="generatedContent.html"></div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-slate-700 dark:bg-slate-800">
                    <button 
                        @click="rejectComparison"
                        class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ $t('messages.ai_assistant.comparison.reject') }}
                    </button>
                    <div class="flex gap-3">
                        <button 
                            @click="copyToClipboard"
                            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                        >
                            <svg v-if="!copySuccess" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ copySuccess ? $t('messages.ai_assistant.actions.copied') : $t('messages.ai_assistant.comparison.copy_new') }}
                        </button>
                        <button 
                            @click="acceptComparison"
                            class="flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $t('messages.ai_assistant.comparison.accept_replace') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Always visible, styled scrollbars for the panel */
.custom-scrollbar {
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: #cbd5e1 transparent; /* Firefox */
}

.dark .custom-scrollbar {
    scrollbar-color: #475569 transparent;
}

/* Chrome, Edge, Safari */
.custom-scrollbar::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 5px;
    border: 2px solid transparent;
    background-clip: content-box;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #475569;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #64748b;
}
</style>
