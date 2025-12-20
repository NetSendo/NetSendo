<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';

const { t } = useI18n();

const props = defineProps({
    form: Object,
    lists: Array,
    availableFields: Object,
    defaultStyles: Object,
    defaultFields: Array,
});

// Check if editing or creating
const isEditing = computed(() => !!props.form?.id);

// Form data
const formData = useForm({
    name: props.form?.name || '',
    contact_list_id: props.form?.contact_list_id || (props.lists[0]?.id || ''),
    status: props.form?.status || 'draft',
    type: props.form?.type || 'inline',
    fields: props.form?.fields || [...props.defaultFields],
    styles: props.form?.styles || { ...props.defaultStyles },
    layout: props.form?.layout || 'vertical',
    label_position: props.form?.label_position || 'above',
    show_placeholders: props.form?.show_placeholders ?? true,
    double_optin: props.form?.double_optin,
    require_policy: props.form?.require_policy || false,
    policy_url: props.form?.policy_url || '',
    redirect_url: props.form?.redirect_url || '',
    success_message: props.form?.success_message || '',
    error_message: props.form?.error_message || '',
    coregister_lists: props.form?.coregister_lists || [],
    coregister_optional: props.form?.coregister_optional || false,
    captcha_enabled: props.form?.captcha_enabled || false,
    captcha_provider: props.form?.captcha_provider || 'recaptcha_v2',
    captcha_site_key: props.form?.captcha_site_key || '',
    captcha_secret_key: '',
    honeypot_enabled: props.form?.honeypot_enabled ?? true,
});

// UI State
const activeTab = ref('fields');
const selectedField = ref(null);
const previewMode = ref('desktop');
const isSaving = ref(false);
const showSuccess = ref(false);

// Available fields for drag
const availableStandardFields = computed(() => {
    const usedIds = formData.fields.map(f => f.id);
    return props.availableFields.standard.filter(f => !usedIds.includes(f.id) || f.id === 'email');
});

const availableCustomFields = computed(() => {
    const usedIds = formData.fields.map(f => f.id);
    return props.availableFields.custom.filter(f => !usedIds.includes(f.id));
});

// Field management
function addField(field) {
    const newField = {
        ...field,
        order: formData.fields.length + 1,
    };
    formData.fields.push(newField);
}

function removeField(index) {
    if (formData.fields[index].id === 'email') {
        alert(t('forms.builder.email_required'));
        return;
    }
    formData.fields.splice(index, 1);
    if (selectedField.value === index) {
        selectedField.value = null;
    }
}

function selectField(index) {
    selectedField.value = selectedField.value === index ? null : index;
}

function updateField(index, updates) {
    formData.fields[index] = { ...formData.fields[index], ...updates };
}

// Style helpers
function updateStyle(key, value) {
    formData.styles[key] = value;
}

// Save form
async function saveForm() {
    isSaving.value = true;
    
    if (isEditing.value) {
        formData.put(route('forms.update', props.form.id), {
            onSuccess: () => {
                showSuccess.value = true;
                setTimeout(() => showSuccess.value = false, 3000);
            },
            onFinish: () => isSaving.value = false,
        });
    } else {
        formData.post(route('forms.store'), {
            onFinish: () => isSaving.value = false,
        });
    }
}

// Preview styles computed
const previewStyles = computed(() => {
    const s = formData.styles;
    return {
        '--form-bg': s.bgcolor,
        '--form-border': s.border_color,
        '--form-text': s.text_color,
        '--form-radius': `${s.border_radius}px`,
        '--form-padding': `${s.padding}px`,
        '--field-bg': s.field_bgcolor,
        '--field-text': s.field_text,
        '--field-border': s.field_border_color,
        '--field-radius': `${s.field_border_radius}px`,
        '--field-height': `${s.field_height}px`,
        '--field-gap': `${s.fields_vertical_margin}px`,
        '--btn-bg': s.submit_color,
        '--btn-text': s.submit_text_color,
        '--btn-border': s.submit_border_color,
        '--btn-radius': `${s.submit_border_radius}px`,
        '--btn-font-size': `${s.submit_font_size}px`,
    };
});
</script>

<template>
    <Head :title="isEditing ? t('forms.edit') : t('forms.create')" />

    <AuthenticatedLayout>
        <!-- Header -->
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button 
                        @click="router.visit(route('forms.index'))"
                        class="btn-icon"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </button>
                    <input 
                        v-model="formData.name"
                        type="text"
                        :placeholder="t('forms.builder.name_placeholder')"
                        class="text-xl font-bold bg-transparent border-none focus:ring-0 p-0 text-gray-900 dark:text-gray-100"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <!-- Status selector -->
                    <select v-model="formData.status" class="input text-sm py-1.5">
                        <option value="draft">{{ t('forms.status.draft') }}</option>
                        <option value="active">{{ t('forms.status.active') }}</option>
                        <option value="disabled">{{ t('forms.status.disabled') }}</option>
                    </select>
                    
                    <!-- Save button -->
                    <button 
                        @click="saveForm"
                        :disabled="isSaving || formData.processing"
                        class="btn-primary inline-flex items-center gap-2"
                    >
                        <svg v-if="isSaving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <svg v-else-if="showSuccess" class="w-4 h-4 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ t('common.save') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="h-[calc(100vh-140px)] flex">
            <!-- Left sidebar: Field library -->
            <div class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto p-4">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                    {{ t('forms.builder.available_fields') }}
                </h3>
                
                <!-- Standard fields -->
                <div class="space-y-2 mb-6">
                    <div 
                        v-for="field in availableStandardFields" 
                        :key="field.id"
                        @click="addField(field)"
                        class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors group"
                    >
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ field.label }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ field.type }}</p>
                        </div>
                    </div>
                </div>

                <!-- Custom fields -->
                <div v-if="availableCustomFields.length > 0">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        {{ t('forms.builder.custom_fields') }}
                    </h4>
                    <div class="space-y-2">
                        <div 
                            v-for="field in availableCustomFields" 
                            :key="field.id"
                            @click="addField(field)"
                            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors"
                        >
                            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center text-purple-600 dark:text-purple-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ field.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ field.type }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Center: Preview -->
            <div class="flex-1 bg-gray-100 dark:bg-gray-900 overflow-y-auto p-8">
                <div class="max-w-lg mx-auto">
                    <!-- Preview mode toggle -->
                    <div class="flex justify-center mb-4">
                        <div class="inline-flex bg-white dark:bg-gray-800 rounded-lg p-1 shadow-sm">
                            <button 
                                @click="previewMode = 'desktop'"
                                :class="['px-3 py-1.5 rounded-md text-sm transition-colors', previewMode === 'desktop' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400']"
                            >
                                Desktop
                            </button>
                            <button 
                                @click="previewMode = 'mobile'"
                                :class="['px-3 py-1.5 rounded-md text-sm transition-colors', previewMode === 'mobile' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400']"
                            >
                                Mobile
                            </button>
                        </div>
                    </div>

                    <!-- Form preview -->
                    <div 
                        :class="['transition-all duration-300 mx-auto', previewMode === 'mobile' ? 'max-w-sm' : 'max-w-lg']"
                        :style="previewStyles"
                    >
                        <div 
                            class="form-preview rounded-xl border shadow-lg"
                            :style="{
                                backgroundColor: formData.styles.bgcolor,
                                borderColor: formData.styles.border_color,
                                borderRadius: formData.styles.border_radius + 'px',
                                padding: formData.styles.padding + 'px',
                            }"
                        >
                            <!-- Draggable fields -->
                            <draggable 
                                v-model="formData.fields"
                                item-key="id"
                                handle=".drag-handle"
                                ghost-class="opacity-50"
                                class="space-y-4"
                            >
                                <template #item="{ element, index }">
                                    <div 
                                        @click="selectField(index)"
                                        :class="[
                                            'field-wrapper relative group rounded-lg transition-all cursor-pointer',
                                            selectedField === index ? 'ring-2 ring-indigo-500 ring-offset-2' : 'hover:ring-2 hover:ring-gray-300'
                                        ]"
                                    >
                                        <!-- Drag handle -->
                                        <div class="drag-handle absolute -left-8 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 cursor-grab p-1">
                                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                                            </svg>
                                        </div>

                                        <!-- Remove button -->
                                        <button 
                                            v-if="element.id !== 'email'"
                                            @click.stop="removeField(index)"
                                            class="absolute -right-2 -top-2 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow-lg"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>

                                        <!-- Field content -->
                                        <div class="p-1">
                                            <label 
                                                v-if="formData.label_position !== 'hidden'"
                                                class="block text-sm font-medium mb-1"
                                                :style="{ 
                                                    color: formData.styles.text_color,
                                                    fontSize: formData.styles.label_font_size + 'px',
                                                    fontWeight: formData.styles.label_font_weight,
                                                }"
                                            >
                                                {{ element.label }}
                                                <span v-if="element.required" class="text-red-500">*</span>
                                            </label>
                                            <input 
                                                type="text"
                                                disabled
                                                :placeholder="formData.show_placeholders ? element.placeholder : ''"
                                                class="w-full transition-all"
                                                :style="{
                                                    backgroundColor: formData.styles.field_bgcolor,
                                                    color: formData.styles.field_text,
                                                    borderColor: formData.styles.field_border_color,
                                                    borderRadius: formData.styles.field_border_radius + 'px',
                                                    height: formData.styles.field_height + 'px',
                                                    padding: '8px 12px',
                                                    border: '1px solid ' + formData.styles.field_border_color,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </template>
                            </draggable>

                            <!-- Submit button preview -->
                            <div class="mt-4" :style="{ textAlign: formData.styles.submit_align }">
                                <button 
                                    disabled
                                    class="transition-all"
                                    :style="{
                                        backgroundColor: formData.styles.submit_color,
                                        color: formData.styles.submit_text_color,
                                        borderColor: formData.styles.submit_border_color,
                                        borderRadius: formData.styles.submit_border_radius + 'px',
                                        padding: formData.styles.submit_padding_v + 'px ' + formData.styles.submit_padding_h + 'px',
                                        fontSize: formData.styles.submit_font_size + 'px',
                                        fontWeight: formData.styles.submit_font_weight,
                                        width: formData.styles.submit_full_width ? '100%' : 'auto',
                                        border: '1px solid',
                                    }"
                                >
                                    {{ formData.styles.submit_text }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right sidebar: Settings -->
            <div class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto">
                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button 
                        v-for="tab in ['fields', 'styles', 'settings']"
                        :key="tab"
                        @click="activeTab = tab"
                        :class="[
                            'flex-1 py-3 text-sm font-medium transition-colors',
                            activeTab === tab 
                                ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400' 
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700'
                        ]"
                    >
                        {{ t('forms.builder.tab_' + tab) }}
                    </button>
                </div>

                <div class="p-4">
                    <!-- Fields tab -->
                    <div v-if="activeTab === 'fields'">
                        <div v-if="selectedField !== null" class="space-y-4">
                            <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                {{ t('forms.builder.edit_field') }}: {{ formData.fields[selectedField].label }}
                            </h4>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('forms.builder.field_label') }}
                                </label>
                                <input 
                                    v-model="formData.fields[selectedField].label"
                                    type="text"
                                    class="input w-full"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ t('forms.builder.field_placeholder') }}
                                </label>
                                <input 
                                    v-model="formData.fields[selectedField].placeholder"
                                    type="text"
                                    class="input w-full"
                                />
                            </div>

                            <div class="flex items-center gap-2">
                                <input 
                                    v-model="formData.fields[selectedField].required"
                                    type="checkbox"
                                    :id="'required-' + selectedField"
                                    class="checkbox"
                                />
                                <label :for="'required-' + selectedField" class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ t('forms.builder.field_required') }}
                                </label>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                            </svg>
                            <p>{{ t('forms.builder.select_field_hint') }}</p>
                        </div>
                    </div>

                    <!-- Styles tab -->
                    <div v-if="activeTab === 'styles'" class="space-y-6">
                        <!-- Form container -->
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">{{ t('forms.builder.form_container') }}</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.background_color') }}</label>
                                    <input v-model="formData.styles.bgcolor" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.border_color') }}</label>
                                    <input v-model="formData.styles.border_color" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.border_radius') }}</label>
                                    <input v-model.number="formData.styles.border_radius" type="range" min="0" max="24" class="w-full">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.padding') }}</label>
                                    <input v-model.number="formData.styles.padding" type="range" min="8" max="48" class="w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Fields -->
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">{{ t('forms.builder.field_styles') }}</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.field_background') }}</label>
                                    <input v-model="formData.styles.field_bgcolor" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.field_border') }}</label>
                                    <input v-model="formData.styles.field_border_color" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.field_height') }}</label>
                                    <input v-model.number="formData.styles.field_height" type="range" min="32" max="56" class="w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Submit button -->
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">{{ t('forms.builder.button_styles') }}</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.button_text') }}</label>
                                    <input v-model="formData.styles.submit_text" type="text" class="input w-full">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.button_color') }}</label>
                                    <input v-model="formData.styles.submit_color" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">{{ t('forms.builder.button_text_color') }}</label>
                                    <input v-model="formData.styles.submit_text_color" type="color" class="w-full h-8 rounded cursor-pointer">
                                </div>
                                <div class="flex items-center gap-2">
                                    <input v-model="formData.styles.submit_full_width" type="checkbox" id="btn-full-width" class="checkbox">
                                    <label for="btn-full-width" class="text-sm text-gray-700 dark:text-gray-300">{{ t('forms.builder.full_width') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings tab -->
                    <div v-if="activeTab === 'settings'" class="space-y-6">
                        <!-- List selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('forms.builder.target_list') }}
                            </label>
                            <select v-model="formData.contact_list_id" class="input w-full">
                                <option v-for="list in lists" :key="list.id" :value="list.id">
                                    {{ list.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Layout -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('forms.builder.label_position') }}
                            </label>
                            <select v-model="formData.label_position" class="input w-full">
                                <option value="above">{{ t('forms.builder.label_above') }}</option>
                                <option value="left">{{ t('forms.builder.label_left') }}</option>
                                <option value="hidden">{{ t('forms.builder.label_hidden') }}</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <input v-model="formData.show_placeholders" type="checkbox" id="show-placeholders" class="checkbox">
                            <label for="show-placeholders" class="text-sm text-gray-700 dark:text-gray-300">
                                {{ t('forms.builder.show_placeholders') }}
                            </label>
                        </div>

                        <!-- Messages -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('forms.builder.success_message') }}
                            </label>
                            <textarea v-model="formData.success_message" rows="2" class="input w-full" :placeholder="t('forms.builder.success_default')"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('forms.builder.redirect_url') }}
                            </label>
                            <input v-model="formData.redirect_url" type="url" class="input w-full" placeholder="https://...">
                        </div>

                        <!-- Anti-spam -->
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-3">{{ t('forms.builder.anti_spam') }}</h4>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <input v-model="formData.honeypot_enabled" type="checkbox" id="honeypot" class="checkbox">
                                    <label for="honeypot" class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ t('forms.builder.honeypot') }}
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input v-model="formData.captcha_enabled" type="checkbox" id="captcha" class="checkbox">
                                    <label for="captcha" class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ t('forms.builder.enable_captcha') }}
                                    </label>
                                </div>
                                <div v-if="formData.captcha_enabled" class="pl-6 space-y-2">
                                    <select v-model="formData.captcha_provider" class="input w-full">
                                        <option value="recaptcha_v2">reCAPTCHA v2</option>
                                        <option value="recaptcha_v3">reCAPTCHA v3</option>
                                        <option value="hcaptcha">hCaptcha</option>
                                        <option value="turnstile">Cloudflare Turnstile</option>
                                    </select>
                                    <input v-model="formData.captcha_site_key" type="text" class="input w-full" :placeholder="t('forms.builder.site_key')">
                                    <input v-model="formData.captcha_secret_key" type="password" class="input w-full" :placeholder="t('forms.builder.secret_key')">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.input {
    @apply bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors;
}

.checkbox {
    @apply w-4 h-4 text-indigo-600 bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 transition-colors;
}

.btn-primary {
    @apply bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-icon {
    @apply p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors;
}
</style>
