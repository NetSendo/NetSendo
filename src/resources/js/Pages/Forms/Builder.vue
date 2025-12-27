<script setup>
import { ref, computed, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";
import draggable from "vuedraggable";
import Modal from "@/Components/Modal.vue";

const { t } = useI18n();

const props = defineProps({
    form: Object,
    lists: Array,
    availableFields: Object,
    defaultStyles: Object,
    defaultFields: Array,
    designPresets: Object,
});

// Check if editing or creating
const isEditing = computed(() => !!props.form?.id);

// Form data
const formData = useForm({
    name: props.form?.name || "",
    contact_list_id: props.form?.contact_list_id || props.lists[0]?.id || "",
    status: props.form?.status || "draft",
    type: props.form?.type || "inline",
    fields: props.form?.fields || [...props.defaultFields],
    styles: props.form?.styles || { ...props.defaultStyles },
    layout: props.form?.layout || "vertical",
    label_position: props.form?.label_position || "above",
    show_placeholders: props.form?.show_placeholders ?? true,
    double_optin: props.form?.double_optin,
    require_policy: props.form?.require_policy || false,
    policy_url: props.form?.policy_url || "",
    redirect_url: props.form?.redirect_url || "",
    use_list_redirect: props.form?.use_list_redirect || false,
    success_message: props.form?.success_message || "",
    error_message: props.form?.error_message || "",
    coregister_lists: props.form?.coregister_lists || [],
    coregister_optional: props.form?.coregister_optional || false,
    captcha_enabled: props.form?.captcha_enabled || false,
    captcha_provider: props.form?.captcha_provider || "recaptcha_v2",
    captcha_site_key: props.form?.captcha_site_key || "",
    captcha_secret_key: "",
    honeypot_enabled: props.form?.honeypot_enabled ?? true,
});

// UI State
const activeTab = ref("fields");
const selectedField = ref(null);
const previewMode = ref("desktop");
const isSaving = ref(false);
const showSuccess = ref(false);
const activeStyleSection = ref("container");
const showErrorModal = ref(false);
const errorMessage = ref("");

// Available fields for drag
const availableStandardFields = computed(() => {
    const usedIds = formData.fields.map((f) => f.id);
    return props.availableFields.standard.filter(
        (f) => !usedIds.includes(f.id) || f.id === "email"
    );
});

const availableCustomFields = computed(() => {
    const usedIds = formData.fields.map((f) => f.id);
    return props.availableFields.custom.filter((f) => !usedIds.includes(f.id));
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
    if (formData.fields[index].id === "email") {
        alert(t("forms.builder.email_required"));
        return;
    }
    formData.fields.splice(index, 1);
    if (selectedField.value === index) {
        selectedField.value = null;
    }
}

function selectField(index) {
    selectedField.value = selectedField.value === index ? null : index;
    activeTab.value = "fields";
}

// Apply design preset
function applyPreset(presetKey) {
    if (presetKey === "default") {
        formData.styles = { ...props.defaultStyles };
    } else {
        const preset = props.designPresets[presetKey];
        if (preset) {
            formData.styles = { ...props.defaultStyles, ...preset.styles };
        }
    }
}

// Save form
async function saveForm() {
    if (!formData.name) {
        errorMessage.value = t("forms.builder.title_required");
        showErrorModal.value = true;
        return;
    }

    isSaving.value = true;

    // Transform empty strings to null for URL fields
    const transformData = (data) => {
        return {
            ...data,
            redirect_url: data.redirect_url || null,
            policy_url: data.policy_url || null,
            success_message: data.success_message || null,
            error_message: data.error_message || null,
        };
    };

    // Debug log the form data
    console.log("Saving form data:", JSON.parse(JSON.stringify(formData)));

    if (isEditing.value) {
        formData
            .transform(transformData)
            .put(route("forms.update", props.form.id), {
                onSuccess: () => {
                    showSuccess.value = true;
                    setTimeout(() => (showSuccess.value = false), 3000);
                },
                onError: (errors) => {
                    console.error("Form update errors:", errors);
                    alert(
                        "Błąd walidacji: " +
                            Object.values(errors).flat().join(", ")
                    );
                },
                onFinish: () => (isSaving.value = false),
            });
    } else {
        formData.transform(transformData).post(route("forms.store"), {
            onSuccess: () => {
                console.log("Form created successfully");
            },
            onError: (errors) => {
                console.error("Form create errors:", errors);
                alert(
                    "Błąd walidacji: " + Object.values(errors).flat().join(", ")
                );
            },
            onFinish: () => (isSaving.value = false),
        });
    }
}

// Hex to RGBA for preview
function hexToRgba(hex, opacity) {
    if (!hex) return "transparent";
    hex = hex.replace("#", "");
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${(opacity ?? 100) / 100})`;
}

// Preview styles computed
const previewContainerStyle = computed(() => {
    const s = formData.styles;
    const bgOpacity = s.bgcolor_opacity ?? 100;

    let background = hexToRgba(s.bgcolor, bgOpacity);
    if (s.gradient_enabled) {
        background = `linear-gradient(${
            s.gradient_direction || "to bottom right"
        }, ${s.gradient_from || "#6366F1"}, ${s.gradient_to || "#8B5CF6"})`;
    }

    let boxShadow = "none";
    if (s.shadow_enabled) {
        const shadowColor = hexToRgba(
            s.shadow_color || "#000000",
            s.shadow_opacity || 15
        );
        boxShadow = `${s.shadow_x || 0}px ${s.shadow_y || 10}px ${
            s.shadow_blur || 20
        }px ${shadowColor}`;
    }

    return {
        background,
        borderColor: s.border_color,
        borderWidth: s.border_width + "px",
        borderStyle: "solid",
        borderRadius: s.border_radius + "px",
        padding: s.padding + "px",
        boxShadow,
        backdropFilter: bgOpacity < 100 ? "blur(10px)" : "none",
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
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
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
                    <select
                        v-model="formData.status"
                        class="input text-sm py-1.5"
                    >
                        <option value="draft">
                            {{ t("forms.status.draft") }}
                        </option>
                        <option value="active">
                            {{ t("forms.status.active") }}
                        </option>
                        <option value="disabled">
                            {{ t("forms.status.disabled") }}
                        </option>
                    </select>

                    <!-- Save button -->
                    <button
                        @click="saveForm"
                        :disabled="isSaving || formData.processing"
                        class="btn-primary inline-flex items-center gap-2"
                    >
                        <svg
                            v-if="isSaving"
                            class="animate-spin w-4 h-4"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <svg
                            v-else-if="showSuccess"
                            class="w-4 h-4 text-green-300"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        {{ t("common.save") }}
                    </button>
                </div>
            </div>
        </template>

        <div class="h-[calc(100vh-140px)] flex">
            <!-- Left sidebar: Field library -->
            <div
                class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto p-4"
            >
                <h3
                    class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3"
                >
                    {{ t("forms.builder.available_fields") }}
                </h3>

                <!-- Standard fields -->
                <div class="space-y-2 mb-6">
                    <div
                        v-for="field in availableStandardFields"
                        :key="field.id"
                        @click="addField(field)"
                        class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors group"
                    >
                        <div
                            class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ field.label }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ field.type }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Custom fields -->
                <div v-if="availableCustomFields.length > 0">
                    <h4
                        class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2"
                    >
                        {{ t("forms.builder.custom_fields") }}
                    </h4>
                    <div class="space-y-2">
                        <div
                            v-for="field in availableCustomFields"
                            :key="field.id"
                            @click="addField(field)"
                            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors"
                        >
                            <div
                                class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center text-purple-600 dark:text-purple-400"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ field.label }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ field.type }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Design Presets -->
                <div
                    class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700"
                >
                    <h3
                        class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3"
                    >
                        Presety designu
                    </h3>
                    <div class="space-y-2">
                        <button
                            v-for="(preset, key) in designPresets"
                            :key="key"
                            @click="applyPreset(key)"
                            class="w-full text-left p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-all group"
                        >
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400"
                            >
                                {{ preset.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ preset.description }}
                            </p>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Center: Preview -->
            <div
                class="flex-1 overflow-y-auto p-8"
                :class="
                    formData.styles.bgcolor_opacity < 100
                        ? 'bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100 dark:from-gray-900 dark:via-purple-900/20 dark:to-gray-900'
                        : 'bg-gray-100 dark:bg-gray-900'
                "
            >
                <div class="max-w-lg mx-auto">
                    <!-- Preview mode toggle -->
                    <div class="flex justify-center mb-4">
                        <div
                            class="inline-flex bg-white dark:bg-gray-800 rounded-lg p-1 shadow-sm"
                        >
                            <button
                                @click="previewMode = 'desktop'"
                                :class="[
                                    'px-3 py-1.5 rounded-md text-sm transition-colors',
                                    previewMode === 'desktop'
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                        : 'text-gray-600 dark:text-gray-400',
                                ]"
                            >
                                Desktop
                            </button>
                            <button
                                @click="previewMode = 'mobile'"
                                :class="[
                                    'px-3 py-1.5 rounded-md text-sm transition-colors',
                                    previewMode === 'mobile'
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                        : 'text-gray-600 dark:text-gray-400',
                                ]"
                            >
                                Mobile
                            </button>
                        </div>
                    </div>

                    <!-- Form preview -->
                    <div
                        :class="[
                            'transition-all duration-300 mx-auto',
                            previewMode === 'mobile' ? 'max-w-sm' : 'max-w-lg',
                        ]"
                    >
                        <div
                            class="form-preview"
                            :style="previewContainerStyle"
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
                                            selectedField === index
                                                ? 'ring-2 ring-indigo-500 ring-offset-2'
                                                : 'hover:ring-2 hover:ring-gray-300',
                                        ]"
                                    >
                                        <!-- Drag handle -->
                                        <div
                                            class="drag-handle absolute -left-8 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 cursor-grab p-1"
                                        >
                                            <svg
                                                class="w-4 h-4 text-gray-400"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"
                                                />
                                            </svg>
                                        </div>

                                        <!-- Remove button -->
                                        <button
                                            v-if="element.id !== 'email'"
                                            @click.stop="removeField(index)"
                                            class="absolute -right-2 -top-2 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow-lg"
                                        >
                                            <svg
                                                class="w-3 h-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>
                                        </button>

                                        <!-- Field content -->
                                        <div class="p-1">
                                            <label
                                                v-if="
                                                    formData.label_position !==
                                                    'hidden'
                                                "
                                                class="block text-sm font-medium mb-1"
                                                :style="{
                                                    color: formData.styles
                                                        .text_color,
                                                    fontSize:
                                                        formData.styles
                                                            .label_font_size +
                                                        'px',
                                                    fontWeight:
                                                        formData.styles
                                                            .label_font_weight,
                                                }"
                                            >
                                                {{ element.label }}
                                                <span
                                                    v-if="element.required"
                                                    class="text-red-500"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                type="text"
                                                disabled
                                                :placeholder="
                                                    formData.show_placeholders
                                                        ? element.placeholder
                                                        : ''
                                                "
                                                class="w-full transition-all"
                                                :style="{
                                                    backgroundColor:
                                                        formData.styles
                                                            .field_bgcolor,
                                                    color: formData.styles
                                                        .field_text,
                                                    borderColor:
                                                        formData.styles
                                                            .field_border_color,
                                                    borderRadius:
                                                        formData.styles
                                                            .field_border_radius +
                                                        'px',
                                                    height:
                                                        formData.styles
                                                            .field_height +
                                                        'px',
                                                    padding: '8px 12px',
                                                    border:
                                                        '1px solid ' +
                                                        formData.styles
                                                            .field_border_color,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </template>
                            </draggable>

                            <!-- Submit button preview -->
                            <div
                                class="mt-4"
                                :style="{
                                    textAlign: formData.styles.submit_align,
                                }"
                            >
                                <button
                                    disabled
                                    class="transition-all hover:scale-[1.02]"
                                    :style="{
                                        backgroundColor:
                                            formData.styles.submit_color,
                                        color: formData.styles
                                            .submit_text_color,
                                        borderColor:
                                            formData.styles.submit_border_color,
                                        borderRadius:
                                            formData.styles
                                                .submit_border_radius + 'px',
                                        padding:
                                            formData.styles.submit_padding_v +
                                            'px ' +
                                            formData.styles.submit_padding_h +
                                            'px',
                                        fontSize:
                                            formData.styles.submit_font_size +
                                            'px',
                                        fontWeight:
                                            formData.styles.submit_font_weight,
                                        width: formData.styles.submit_full_width
                                            ? '100%'
                                            : 'auto',
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
            <div
                class="w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto"
            >
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
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700',
                        ]"
                    >
                        {{ t("forms.builder.tab_" + tab) }}
                    </button>
                </div>

                <div class="p-4">
                    <!-- Fields tab -->
                    <div v-if="activeTab === 'fields'">
                        <div v-if="selectedField !== null" class="space-y-4">
                            <h4
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ t("forms.builder.edit_field") }}:
                                {{ formData.fields[selectedField].label }}
                            </h4>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ t("forms.builder.field_label") }}
                                </label>
                                <input
                                    v-model="
                                        formData.fields[selectedField].label
                                    "
                                    type="text"
                                    class="input w-full"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ t("forms.builder.field_placeholder") }}
                                </label>
                                <input
                                    v-model="
                                        formData.fields[selectedField]
                                            .placeholder
                                    "
                                    type="text"
                                    class="input w-full"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    Ten tekst pojawi się w polu jako podpowiedź
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    v-model="
                                        formData.fields[selectedField].required
                                    "
                                    type="checkbox"
                                    :id="'required-' + selectedField"
                                    class="checkbox"
                                />
                                <label
                                    :for="'required-' + selectedField"
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ t("forms.builder.field_required") }}
                                </label>
                            </div>
                        </div>
                        <div
                            v-else
                            class="text-center text-gray-500 dark:text-gray-400 py-8"
                        >
                            <svg
                                class="w-12 h-12 mx-auto mb-3 opacity-50"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                                />
                            </svg>
                            <p>{{ t("forms.builder.select_field_hint") }}</p>
                        </div>
                    </div>

                    <!-- Styles tab -->
                    <div v-if="activeTab === 'styles'" class="space-y-6">
                        <!-- Style section selector -->
                        <div class="flex flex-wrap gap-2 mb-4">
                            <button
                                v-for="section in [
                                    'container',
                                    'fields',
                                    'button',
                                    'effects',
                                ]"
                                :key="section"
                                @click="activeStyleSection = section"
                                :class="[
                                    'px-3 py-1.5 text-xs font-medium rounded-full transition-colors',
                                    activeStyleSection === section
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200',
                                ]"
                            >
                                {{
                                    section === "container"
                                        ? "Kontener"
                                        : section === "fields"
                                        ? "Pola"
                                        : section === "button"
                                        ? "Przycisk"
                                        : "Efekty"
                                }}
                            </button>
                        </div>

                        <!-- Container styles -->
                        <div
                            v-if="activeStyleSection === 'container'"
                            class="space-y-4"
                        >
                            <!-- Transparent container toggle -->
                            <div
                                class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p
                                            class="text-sm font-medium text-indigo-900 dark:text-indigo-100"
                                        >
                                            Przezroczysty kontener
                                        </p>
                                        <p
                                            class="text-xs text-indigo-600 dark:text-indigo-400"
                                        >
                                            Tylko pola i przycisk
                                        </p>
                                    </div>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            class="sr-only peer"
                                            :checked="
                                                formData.styles
                                                    .bgcolor_opacity === 0 &&
                                                formData.styles.border_width ===
                                                    0
                                            "
                                            @change="
                                                (e) => {
                                                    if (e.target.checked) {
                                                        formData.styles.bgcolor_opacity = 0;
                                                        formData.styles.border_width = 0;
                                                        formData.styles.padding = 0;
                                                        formData.styles.shadow_enabled = false;
                                                    } else {
                                                        formData.styles.bgcolor_opacity = 100;
                                                        formData.styles.border_width = 1;
                                                        formData.styles.padding = 24;
                                                    }
                                                }
                                            "
                                        />
                                        <div
                                            class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"
                                        ></div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor tła</label
                                >
                                <div class="flex gap-2">
                                    <input
                                        v-model="formData.styles.bgcolor"
                                        type="color"
                                        class="w-12 h-8 rounded cursor-pointer border-0"
                                    />
                                    <input
                                        v-model="formData.styles.bgcolor"
                                        type="text"
                                        class="input flex-1 text-xs font-mono"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Przezroczystość tła:
                                    {{
                                        formData.styles.bgcolor_opacity ?? 100
                                    }}%</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.bgcolor_opacity
                                    "
                                    type="range"
                                    min="0"
                                    max="100"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor tekstu</label
                                >
                                <input
                                    v-model="formData.styles.text_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor ramki</label
                                >
                                <input
                                    v-model="formData.styles.border_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Grubość ramki:
                                    {{ formData.styles.border_width }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.border_width
                                    "
                                    type="range"
                                    min="0"
                                    max="4"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Zaokrąglenie:
                                    {{ formData.styles.border_radius }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.border_radius
                                    "
                                    type="range"
                                    min="0"
                                    max="32"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Padding:
                                    {{ formData.styles.padding }}px</label
                                >
                                <input
                                    v-model.number="formData.styles.padding"
                                    type="range"
                                    min="0"
                                    max="48"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Fields styles -->
                        <div
                            v-if="activeStyleSection === 'fields'"
                            class="space-y-4"
                        >
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Tło pola</label
                                >
                                <input
                                    v-model="formData.styles.field_bgcolor"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor tekstu pola</label
                                >
                                <input
                                    v-model="formData.styles.field_text"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor placeholdera</label
                                >
                                <input
                                    v-model="formData.styles.placeholder_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Ramka pola</label
                                >
                                <input
                                    v-model="formData.styles.field_border_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Ramka pola (focus)</label
                                >
                                <input
                                    v-model="
                                        formData.styles
                                            .field_border_color_active
                                    "
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Wysokość pola:
                                    {{ formData.styles.field_height }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.field_height
                                    "
                                    type="range"
                                    min="32"
                                    max="56"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Zaokrąglenie pól:
                                    {{
                                        formData.styles.field_border_radius
                                    }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.field_border_radius
                                    "
                                    type="range"
                                    min="0"
                                    max="16"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Button styles -->
                        <div
                            v-if="activeStyleSection === 'button'"
                            class="space-y-4"
                        >
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Tekst przycisku</label
                                >
                                <input
                                    v-model="formData.styles.submit_text"
                                    type="text"
                                    class="input w-full"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor przycisku</label
                                >
                                <input
                                    v-model="formData.styles.submit_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor tekstu przycisku</label
                                >
                                <input
                                    v-model="formData.styles.submit_text_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Kolor hover</label
                                >
                                <input
                                    v-model="formData.styles.submit_hover_color"
                                    type="color"
                                    class="w-full h-8 rounded cursor-pointer"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Zaokrąglenie:
                                    {{
                                        formData.styles.submit_border_radius
                                    }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.submit_border_radius
                                    "
                                    type="range"
                                    min="0"
                                    max="24"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1"
                                    >Rozmiar czcionki:
                                    {{
                                        formData.styles.submit_font_size
                                    }}px</label
                                >
                                <input
                                    v-model.number="
                                        formData.styles.submit_font_size
                                    "
                                    type="range"
                                    min="12"
                                    max="20"
                                    class="w-full accent-indigo-500"
                                />
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="formData.styles.submit_full_width"
                                    type="checkbox"
                                    id="btn-full-width"
                                    class="checkbox"
                                />
                                <label
                                    for="btn-full-width"
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >Pełna szerokość</label
                                >
                            </div>
                        </div>

                        <!-- Effects (shadow, gradient, animation) -->
                        <div
                            v-if="activeStyleSection === 'effects'"
                            class="space-y-6"
                        >
                            <!-- Shadow -->
                            <div
                                class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <h5
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        Cień
                                    </h5>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            v-model="
                                                formData.styles.shadow_enabled
                                            "
                                            type="checkbox"
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"
                                        ></div>
                                    </label>
                                </div>
                                <div
                                    v-if="formData.styles.shadow_enabled"
                                    class="space-y-3"
                                >
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 mb-1"
                                            >Kolor cienia</label
                                        >
                                        <input
                                            v-model="
                                                formData.styles.shadow_color
                                            "
                                            type="color"
                                            class="w-full h-8 rounded cursor-pointer"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 mb-1"
                                            >Przezroczystość:
                                            {{
                                                formData.styles.shadow_opacity
                                            }}%</label
                                        >
                                        <input
                                            v-model.number="
                                                formData.styles.shadow_opacity
                                            "
                                            type="range"
                                            min="0"
                                            max="100"
                                            class="w-full accent-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 mb-1"
                                            >Rozmycie:
                                            {{
                                                formData.styles.shadow_blur
                                            }}px</label
                                        >
                                        <input
                                            v-model.number="
                                                formData.styles.shadow_blur
                                            "
                                            type="range"
                                            min="0"
                                            max="60"
                                            class="w-full accent-indigo-500"
                                        />
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label
                                                class="block text-xs text-gray-500 mb-1"
                                                >Offset X:
                                                {{
                                                    formData.styles.shadow_x
                                                }}px</label
                                            >
                                            <input
                                                v-model.number="
                                                    formData.styles.shadow_x
                                                "
                                                type="range"
                                                min="-30"
                                                max="30"
                                                class="w-full accent-indigo-500"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs text-gray-500 mb-1"
                                                >Offset Y:
                                                {{
                                                    formData.styles.shadow_y
                                                }}px</label
                                            >
                                            <input
                                                v-model.number="
                                                    formData.styles.shadow_y
                                                "
                                                type="range"
                                                min="-30"
                                                max="30"
                                                class="w-full accent-indigo-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gradient -->
                            <div
                                class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <h5
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        Gradient
                                    </h5>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            v-model="
                                                formData.styles.gradient_enabled
                                            "
                                            type="checkbox"
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"
                                        ></div>
                                    </label>
                                </div>
                                <div
                                    v-if="formData.styles.gradient_enabled"
                                    class="space-y-3"
                                >
                                    <div>
                                        <label
                                            class="block text-xs text-gray-500 mb-1"
                                            >Kierunek</label
                                        >
                                        <select
                                            v-model="
                                                formData.styles
                                                    .gradient_direction
                                            "
                                            class="input w-full text-sm"
                                        >
                                            <option value="to right">
                                                Prawo →
                                            </option>
                                            <option value="to left">
                                                Lewo ←
                                            </option>
                                            <option value="to bottom">
                                                Dół ↓
                                            </option>
                                            <option value="to top">
                                                Góra ↑
                                            </option>
                                            <option value="to bottom right">
                                                Prawy dół ↘
                                            </option>
                                            <option value="to bottom left">
                                                Lewy dół ↙
                                            </option>
                                            <option value="to top right">
                                                Prawy górny ↗
                                            </option>
                                            <option value="to top left">
                                                Lewy górny ↖
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label
                                                class="block text-xs text-gray-500 mb-1"
                                                >Kolor od</label
                                            >
                                            <input
                                                v-model="
                                                    formData.styles
                                                        .gradient_from
                                                "
                                                type="color"
                                                class="w-full h-8 rounded cursor-pointer"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                class="block text-xs text-gray-500 mb-1"
                                                >Kolor do</label
                                            >
                                            <input
                                                v-model="
                                                    formData.styles.gradient_to
                                                "
                                                type="color"
                                                class="w-full h-8 rounded cursor-pointer"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Animation -->
                            <div
                                class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <h5
                                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        Animacja
                                    </h5>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            v-model="
                                                formData.styles
                                                    .animation_enabled
                                            "
                                            type="checkbox"
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"
                                        ></div>
                                    </label>
                                </div>
                                <div v-if="formData.styles.animation_enabled">
                                    <select
                                        v-model="formData.styles.animation_type"
                                        class="input w-full text-sm"
                                    >
                                        <option value="fadeIn">Fade In</option>
                                        <option value="slideUp">
                                            Slide Up
                                        </option>
                                        <option value="bounce">Bounce</option>
                                        <option value="pulse">
                                            Pulse (hover)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings tab -->
                    <div v-if="activeTab === 'settings'" class="space-y-6">
                        <!-- List selection -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ t("forms.builder.target_list") }}
                            </label>
                            <select
                                v-model="formData.contact_list_id"
                                class="input w-full"
                            >
                                <option
                                    v-for="list in lists"
                                    :key="list.id"
                                    :value="list.id"
                                >
                                    {{ list.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Layout -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ t("forms.builder.label_position") }}
                            </label>
                            <select
                                v-model="formData.label_position"
                                class="input w-full"
                            >
                                <option value="above">
                                    {{ t("forms.builder.label_above") }}
                                </option>
                                <option value="left">
                                    {{ t("forms.builder.label_left") }}
                                </option>
                                <option value="hidden">
                                    {{ t("forms.builder.label_hidden") }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                v-model="formData.show_placeholders"
                                type="checkbox"
                                id="show-placeholders"
                                class="checkbox"
                            />
                            <label
                                for="show-placeholders"
                                class="text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ t("forms.builder.show_placeholders") }}
                            </label>
                        </div>

                        <!-- Redirect settings -->
                        <div
                            class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg space-y-3"
                        >
                            <h4
                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                            >
                                Przekierowanie po zapisie
                            </h4>

                            <div class="flex items-center gap-2">
                                <input
                                    v-model="formData.use_list_redirect"
                                    type="checkbox"
                                    id="use-list-redirect"
                                    class="checkbox"
                                />
                                <label
                                    for="use-list-redirect"
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    Użyj ustawień listy
                                </label>
                            </div>

                            <div v-if="!formData.use_list_redirect">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ t("forms.builder.redirect_url") }}
                                </label>
                                <input
                                    v-model="formData.redirect_url"
                                    type="url"
                                    class="input w-full"
                                    placeholder="https://..."
                                />
                            </div>

                            <p class="text-xs text-gray-500">
                                {{
                                    formData.use_list_redirect
                                        ? "Przekierowanie będzie zależeć od ustawień Double Opt-in listy"
                                        : "Własny adres URL przekierowania po zapisie"
                                }}
                            </p>
                        </div>

                        <!-- Messages -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ t("forms.builder.success_message") }}
                            </label>
                            <textarea
                                v-model="formData.success_message"
                                rows="2"
                                class="input w-full"
                                :placeholder="
                                    t('forms.builder.success_default')
                                "
                            ></textarea>
                        </div>

                        <!-- Anti-spam -->
                        <div>
                            <h4
                                class="font-medium text-gray-900 dark:text-gray-100 mb-3"
                            >
                                {{ t("forms.builder.anti_spam") }}
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="formData.honeypot_enabled"
                                        type="checkbox"
                                        id="honeypot"
                                        class="checkbox"
                                    />
                                    <label
                                        for="honeypot"
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ t("forms.builder.honeypot") }}
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="formData.captcha_enabled"
                                        type="checkbox"
                                        id="captcha"
                                        class="checkbox"
                                    />
                                    <label
                                        for="captcha"
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ t("forms.builder.enable_captcha") }}
                                    </label>
                                </div>
                                <div
                                    v-if="formData.captcha_enabled"
                                    class="pl-6 space-y-2"
                                >
                                    <select
                                        v-model="formData.captcha_provider"
                                        class="input w-full"
                                    >
                                        <option value="recaptcha_v2">
                                            reCAPTCHA v2
                                        </option>
                                        <option value="recaptcha_v3">
                                            reCAPTCHA v3
                                        </option>
                                        <option value="hcaptcha">
                                            hCaptcha
                                        </option>
                                        <option value="turnstile">
                                            Cloudflare Turnstile
                                        </option>
                                    </select>
                                    <input
                                        v-model="formData.captcha_site_key"
                                        type="text"
                                        class="input w-full"
                                        :placeholder="
                                            t('forms.builder.site_key')
                                        "
                                    />
                                    <input
                                        v-model="formData.captcha_secret_key"
                                        type="password"
                                        class="input w-full"
                                        :placeholder="
                                            t('forms.builder.secret_key')
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <Modal :show="showErrorModal" @close="showErrorModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ t("common.error") }}
            </h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ errorMessage }}
            </p>
            <div class="mt-6 flex justify-end">
                <button @click="showErrorModal = false" class="btn-primary">
                    {{ t("common.close") }}
                </button>
            </div>
        </div>
    </Modal>
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
