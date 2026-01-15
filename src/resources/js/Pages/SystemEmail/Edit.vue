<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, Link } from "@inertiajs/vue3";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import AdvancedEditor from "@/Components/AdvancedEditor.vue";
import { useI18n } from "vue-i18n";
import { computed, ref, nextTick } from "vue";

const { t } = useI18n();

const props = defineProps({
    email: {
        type: Object,
        required: true,
    },
    list_id: {
        type: [String, Number, null],
        default: null,
    },
    list_name: {
        type: String,
        default: null,
    },
});

const displayListName = computed(
    () => props.list_name || t("system_emails.global_default")
);

const form = useForm({
    subject: props.email.subject,
    content: props.email.content,
    is_active: props.email.is_active ?? true,
    list_id: props.list_id,
});

// Referencja do edytora i pola subject
const advancedEditorRef = ref(null);
const subjectInputRef = ref(null);
const subjectCursorPos = ref(0);

// Stan skopiowania
const copiedPlaceholder = ref(null);

// Dostępne placeholdery z opisami
const placeholders = [
    { code: '[[list-name]]', label: t('system_emails.placeholders.list_name') || 'Nazwa listy' },
    { code: '[[email]]', label: t('system_emails.placeholders.email') || 'Adres email' },
    { code: '[[first-name]]', label: t('system_emails.placeholders.first_name') || 'Imię' },
    { code: '[[!fname]]', label: t('system_emails.placeholders.fname_vocative') || 'Imię w wołaczu' },
    { code: '[[last-name]]', label: t('system_emails.placeholders.last_name') || 'Nazwisko' },
    { code: '[[date]]', label: t('system_emails.placeholders.date') || 'Data' },
    { code: '[[activation-link]]', label: t('system_emails.placeholders.activation_link') || 'Link aktywacyjny' },
    { code: '[[unsubscribe-link]]', label: t('system_emails.placeholders.unsubscribe_link') || 'Link wypisania' },
];

// Śledzenie pozycji kursora w polu subject
const updateSubjectCursor = (event) => {
    subjectCursorPos.value = event.target.selectionStart;
};

// Wstaw placeholder do treści (edytora)
const insertPlaceholderToContent = (code) => {
    if (advancedEditorRef.value?.insertAtCursor) {
        advancedEditorRef.value.insertAtCursor(code);
    }
};

// Wstaw placeholder do tematu
const insertPlaceholderToSubject = (code) => {
    const input = subjectInputRef.value?.$el || subjectInputRef.value;
    if (input) {
        const start = subjectCursorPos.value;
        const text = form.subject || "";
        form.subject = text.substring(0, start) + code + text.substring(start);
        nextTick(() => {
            input.focus();
            const newPos = start + code.length;
            input.setSelectionRange(newPos, newPos);
            subjectCursorPos.value = newPos;
        });
    }
};

// Kopiuj do schowka
const copyToClipboard = async (code) => {
    await navigator.clipboard.writeText(code);
    copiedPlaceholder.value = code;
    setTimeout(() => {
        copiedPlaceholder.value = null;
    }, 2000);
};

const submit = () => {
    form.put(route("settings.system-emails.update", props.email.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('system_emails.edit_title', { name: email.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{
                            t("system_emails.edit_title", { name: email.name })
                        }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t("system_emails.context") }}
                        <span
                            class="font-medium text-gray-700 dark:text-gray-300"
                            >{{ displayListName }}</span
                        >
                    </p>
                </div>
                <Link
                    :href="
                        route('settings.system-emails.index', {
                            list_id: list_id,
                        })
                    "
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ t("system_emails.back_to_list") }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Available placeholders - interactive -->
                <div
                    class="bg-indigo-50 p-4 shadow sm:rounded-lg dark:bg-indigo-900/20"
                >
                    <div class="flex items-center justify-between mb-3">
                        <p
                            class="text-sm font-medium text-indigo-700 dark:text-indigo-300"
                        >
                            {{ t("system_emails.available_placeholders") }}:
                        </p>
                        <span class="text-xs text-indigo-600 dark:text-indigo-400">
                            {{ t("system_emails.click_to_insert") || "Kliknij aby wstawić" }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="placeholder in placeholders"
                            :key="placeholder.code"
                            class="group relative"
                        >
                            <!-- Main placeholder button -->
                            <div
                                class="flex items-center gap-1 rounded-lg bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-700 shadow-sm overflow-hidden"
                            >
                                <!-- Insert to content button -->
                                <button
                                    type="button"
                                    @click="insertPlaceholderToContent(placeholder.code)"
                                    class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition-colors"
                                    :title="t('system_emails.insert_to_content') || 'Wstaw do treści'"
                                >
                                    <code class="font-mono">{{ placeholder.code }}</code>
                                </button>

                                <!-- Divider -->
                                <div class="w-px h-6 bg-indigo-200 dark:bg-indigo-700"></div>

                                <!-- Action buttons -->
                                <div class="flex items-center">
                                    <!-- Insert to subject -->
                                    <button
                                        type="button"
                                        @click="insertPlaceholderToSubject(placeholder.code)"
                                        class="p-1.5 text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-200 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition-colors"
                                        :title="t('system_emails.insert_to_subject') || 'Wstaw do tematu'"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                        </svg>
                                    </button>

                                    <!-- Copy to clipboard -->
                                    <button
                                        type="button"
                                        @click="copyToClipboard(placeholder.code)"
                                        class="p-1.5 text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-200 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition-colors"
                                        :title="t('common.copy') || 'Kopiuj'"
                                    >
                                        <svg v-if="copiedPlaceholder !== placeholder.code" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Tooltip with label -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-slate-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                {{ placeholder.label }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800"
                >
                    <form @submit.prevent="submit" class="space-y-6">
                        <div
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                        >
                            <div>
                                <span
                                    class="block text-sm font-medium text-slate-900 dark:text-white"
                                    >{{ t("system_emails.is_active") }}</span
                                >
                                <span
                                    class="block text-xs text-slate-500 dark:text-slate-400"
                                    >{{
                                        t("system_emails.is_active_desc")
                                    }}</span
                                >
                            </div>
                            <div
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                                :class="
                                    form.is_active
                                        ? 'bg-indigo-600'
                                        : 'bg-slate-200 dark:bg-slate-700'
                                "
                                @click="form.is_active = !form.is_active"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="{ 'translate-x-5': form.is_active }"
                                ></span>
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                for="subject"
                                :value="t('system_emails.subject_label')"
                            />
                            <TextInput
                                id="subject"
                                ref="subjectInputRef"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.subject"
                                required
                                @click="updateSubjectCursor"
                                @keyup="updateSubjectCursor"
                                @focus="updateSubjectCursor"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.subject"
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="content"
                                :value="t('system_emails.content_label')"
                            />
                            <div class="mt-1">
                                <AdvancedEditor
                                    ref="advancedEditorRef"
                                    v-model="form.content"
                                    min-height="400px"
                                />
                            </div>
                            <InputError
                                class="mt-2"
                                :message="form.errors.content"
                            />
                        </div>

                        <input type="hidden" :value="form.list_id" />

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">{{
                                t("common.save")
                            }}</PrimaryButton>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-if="form.recentlySuccessful"
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ t("common.saved") }}
                                </p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
