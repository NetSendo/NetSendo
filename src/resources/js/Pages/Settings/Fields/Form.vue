<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    field: {
        type: Object,
        default: null,
    },
    contactList: {
        type: Object,
        default: null,
    },
    fieldTypes: {
        type: Array,
        default: () => [],
    },
    reservedNames: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => !!props.field);

// Form
const form = useForm({
    name: props.field?.name || '',
    label: props.field?.label || '',
    description: props.field?.description || '',
    type: props.field?.type || 'text',
    options: props.field?.options || [''],
    default_value: props.field?.default_value || '',
    is_public: props.field?.is_public ?? true,
    is_required: props.field?.is_required ?? false,
    is_static: props.field?.is_static ?? false,
    contact_list_id: props.contactList?.id || null,
});

// Show options panel for select/radio/checkbox
const showOptionsPanel = computed(() => ['select', 'radio', 'checkbox'].includes(form.type));

// Watch for type changes
watch(() => form.type, (newType) => {
    if (newType === 'checkbox' && form.options.length > 1) {
        form.options = [form.options[0] || ''];
    }
});

// Auto-generate name from label
const autoGenerateName = () => {
    if (!isEditing.value && form.label && !form.name) {
        form.name = form.label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '')
            .substring(0, 50);
    }
};

// Add option
const addOption = () => {
    form.options.push('');
};

// Remove option
const removeOption = (index) => {
    if (form.options.length > 1) {
        form.options.splice(index, 1);
    }
};

// Submit form
const submitForm = () => {
    if (isEditing.value) {
        form.put(route('settings.fields.update', props.field.id));
    } else {
        form.post(route('settings.fields.store'));
    }
};

// Cancel and go back
const cancelRoute = computed(() => {
    if (props.contactList) {
        return route('mailing-lists.edit', { mailing_list: props.contactList.id, tab: 'fields' });
    }
    return route('settings.fields.index');
});

// Validate name format in real-time
const nameError = computed(() => {
    if (!form.name) return null;
    if (!/^[a-z][a-z0-9_]*$/i.test(form.name)) {
        return t('fields.form.name_format_error');
    }
    if (props.reservedNames.includes(form.name.toLowerCase())) {
        return t('fields.form.name_reserved_error');
    }
    return null;
});
</script>

<template>
    <Head :title="isEditing ? $t('fields.form.edit_title') : $t('fields.form.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="cancelRoute"
                    class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-300"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ isEditing ? $t('fields.form.edit_title') : $t('fields.form.create_title') }}
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <form @submit.prevent="submitForm" class="space-y-6">
                <!-- Basic Information Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('fields.form.basic_info') }}
                    </h3>

                    <div class="space-y-4">
                        <!-- Label -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('fields.form.label') }} *
                            </label>
                            <input
                                v-model="form.label"
                                @blur="autoGenerateName"
                                type="text"
                                :placeholder="$t('fields.form.label_placeholder')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                :class="{ 'border-rose-500': form.errors.label }"
                            />
                            <p v-if="form.errors.label" class="mt-1 text-sm text-rose-600">{{ form.errors.label }}</p>
                        </div>

                        <!-- Technical Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('fields.form.name') }} *
                            </label>
                            <div class="relative mt-1">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">[[</span>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="$t('fields.form.name_placeholder')"
                                    class="block w-full rounded-lg border-gray-300 pl-8 pr-8 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    :class="{ 'border-rose-500': form.errors.name || nameError }"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">]]</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('fields.form.name_help') }}
                            </p>
                            <p v-if="nameError" class="mt-1 text-sm text-rose-600">{{ nameError }}</p>
                            <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('fields.form.description') }}
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                :placeholder="$t('fields.form.description_placeholder')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Field Type Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('fields.form.field_type') }}
                    </h3>

                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                        <button
                            v-for="type in fieldTypes"
                            :key="type.value"
                            type="button"
                            @click="form.type = type.value"
                            class="flex flex-col items-center gap-2 rounded-lg border-2 p-4 transition-all"
                            :class="form.type === type.value 
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' 
                                : 'border-gray-200 hover:border-gray-300 dark:border-slate-600 dark:hover:border-slate-500'"
                        >
                            <span class="text-2xl">
                                {{ { text: '📝', number: '🔢', date: '📅', select: '📋', radio: '⭕', checkbox: '☑️' }[type.value] }}
                            </span>
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 text-center">
                                {{ $t(`fields.types.${type.value}`, type.label) }}
                            </span>
                        </button>
                    </div>

                    <!-- Options for select/radio/checkbox -->
                    <div v-if="showOptionsPanel" class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-slate-900/50">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t('fields.form.options') }}
                        </label>
                        <div class="mt-2 space-y-2">
                            <div v-for="(option, index) in form.options" :key="index" class="flex gap-2">
                                <input
                                    v-model="form.options[index]"
                                    type="text"
                                    :placeholder="$t('fields.form.option_placeholder', { n: index + 1 })"
                                    class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <button
                                    v-if="form.options.length > 1"
                                    type="button"
                                    @click="removeOption(index)"
                                    class="rounded-lg p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                v-if="form.type !== 'checkbox'"
                                type="button"
                                @click="addOption"
                                class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ $t('fields.form.add_option') }}
                            </button>
                        </div>
                    </div>

                    <!-- Default Value -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t('fields.form.default_value') }}
                        </label>
                        <input
                            v-model="form.default_value"
                            type="text"
                            :placeholder="$t('fields.form.default_value_placeholder')"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Settings Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('fields.form.settings') }}
                    </h3>

                    <div class="space-y-4">
                        <!-- Is Public -->
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_public"
                                type="checkbox"
                                class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $t('fields.form.is_public') }}</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('fields.form.is_public_help') }}</p>
                            </div>
                        </label>

                        <!-- Is Required -->
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_required"
                                type="checkbox"
                                class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $t('fields.form.is_required') }}</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('fields.form.is_required_help') }}</p>
                            </div>
                        </label>

                        <!-- Is Static -->
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_static"
                                type="checkbox"
                                class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $t('fields.form.is_static') }}</span>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('fields.form.is_static_help') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Link
                        :href="cancelRoute"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600"
                    >
                        {{ $t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || !!nameError"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isEditing ? $t('common.save') : $t('common.create') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
