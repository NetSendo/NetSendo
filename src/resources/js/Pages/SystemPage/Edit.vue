<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdvancedEditor from '@/Components/AdvancedEditor.vue';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const { t } = useI18n();

const props = defineProps({
    page: {
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
    placeholders: {
        type: Object,
        default: () => ({ standard: [], system: [], custom: [] }),
    },
});

const displayListName = computed(() => props.list_name || t('system_pages.global_default'));

const form = useForm({
    title: props.page.title,
    content: props.page.content,
    slug: props.page.slug,
    access: props.page.access || 'public',
    list_id: props.list_id,
});

const copiedPlaceholder = ref(null);

const copyToClipboard = async (placeholder) => {
    try {
        await navigator.clipboard.writeText(placeholder);
        copiedPlaceholder.value = placeholder;
        setTimeout(() => {
            copiedPlaceholder.value = null;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const submit = () => {
    form.put(route('settings.system-pages.update', props.page.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('system_pages.edit_title', { name: page.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('system_pages.edit_title', { name: page.name }) }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('system_pages.context') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ displayListName }}</span>
                    </p>
                </div>
                <Link
                    :href="route('settings.system-pages.index', { list_id: list_id })"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ t('system_pages.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Info about URL -->
                <div class="bg-blue-50 p-4 shadow sm:rounded-lg dark:bg-blue-900/20">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>{{ t('system_pages.current_url') }}:</strong>
                        <code class="ml-2 px-2 py-0.5 bg-blue-100 dark:bg-blue-800 rounded">/p/{{ form.slug }}</code>
                    </p>
                </div>

                <!-- Available Placeholders Section -->
                <div class="bg-indigo-50 p-4 shadow sm:rounded-lg dark:bg-indigo-900/20">
                    <p class="text-sm font-medium text-indigo-700 dark:text-indigo-300 mb-3">
                        {{ t('system_pages.available_placeholders') }}
                        <span class="text-xs font-normal ml-2 text-indigo-500 dark:text-indigo-400">
                            {{ t('system_pages.click_to_copy') }}
                        </span>
                    </p>

                    <!-- Standard Fields -->
                    <div v-if="placeholders.standard?.length" class="mb-3">
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                            {{ t('system_pages.standard_fields') }}
                        </span>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button
                                v-for="ph in placeholders.standard"
                                :key="ph.name"
                                type="button"
                                @click="copyToClipboard(ph.placeholder)"
                                class="px-2 py-1 bg-indigo-100 dark:bg-indigo-800 rounded text-xs font-mono cursor-pointer hover:bg-indigo-200 dark:hover:bg-indigo-700 transition-colors"
                                :class="{ 'ring-2 ring-green-500 bg-green-100 dark:bg-green-800': copiedPlaceholder === ph.placeholder }"
                                :title="ph.description"
                            >
                                {{ ph.placeholder }}
                                <span v-if="copiedPlaceholder === ph.placeholder" class="ml-1 text-green-600 dark:text-green-400">✓</span>
                            </button>
                        </div>
                    </div>

                    <!-- System Placeholders -->
                    <div v-if="placeholders.system?.length" class="mb-3">
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                            {{ t('system_pages.system_placeholders') }}
                        </span>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button
                                v-for="ph in placeholders.system"
                                :key="ph.name"
                                type="button"
                                @click="copyToClipboard(ph.placeholder)"
                                class="px-2 py-1 bg-amber-100 dark:bg-amber-800 rounded text-xs font-mono cursor-pointer hover:bg-amber-200 dark:hover:bg-amber-700 transition-colors"
                                :class="{ 'ring-2 ring-green-500 bg-green-100 dark:bg-green-800': copiedPlaceholder === ph.placeholder }"
                                :title="ph.description"
                            >
                                {{ ph.placeholder }}
                                <span v-if="copiedPlaceholder === ph.placeholder" class="ml-1 text-green-600 dark:text-green-400">✓</span>
                            </button>
                        </div>
                    </div>

                    <!-- Custom Fields -->
                    <div v-if="placeholders.custom?.length">
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                            {{ t('system_pages.custom_fields') }}
                        </span>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button
                                v-for="ph in placeholders.custom"
                                :key="ph.id"
                                type="button"
                                @click="copyToClipboard(ph.placeholder)"
                                class="px-2 py-1 bg-purple-100 dark:bg-purple-800 rounded text-xs font-mono cursor-pointer hover:bg-purple-200 dark:hover:bg-purple-700 transition-colors"
                                :class="{ 'ring-2 ring-green-500 bg-green-100 dark:bg-green-800': copiedPlaceholder === ph.placeholder }"
                                :title="ph.label"
                            >
                                {{ ph.placeholder }}
                                <span v-if="copiedPlaceholder === ph.placeholder" class="ml-1 text-green-600 dark:text-green-400">✓</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!placeholders.custom?.length && !placeholders.standard?.length" class="text-sm text-indigo-500 dark:text-indigo-400">
                        {{ t('system_pages.no_placeholders') }}
                    </div>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                    <form @submit.prevent="submit" class="space-y-6">

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <InputLabel for="title" :value="t('system_pages.page_title_label')" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.title"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <div>
                                <InputLabel for="slug" :value="t('system_pages.slug_label')" />
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        /p/
                                    </span>
                                    <TextInput
                                        id="slug"
                                        type="text"
                                        class="block w-full rounded-l-none"
                                        v-model="form.slug"
                                        :disabled="!list_id"
                                        :placeholder="page.slug"
                                    />
                                </div>
                                <p v-if="!list_id" class="mt-1 text-xs text-gray-500">
                                    {{ t('system_pages.slug_readonly_global') }}
                                </p>
                                <InputError class="mt-2" :message="form.errors.slug" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="access" :value="t('system_pages.access_label')" />
                            <select
                                id="access"
                                v-model="form.access"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="public">{{ t('system_pages.access.public') }}</option>
                                <option value="private">{{ t('system_pages.access.private') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ t('system_pages.access_help') }}
                            </p>
                        </div>

                        <div>
                            <InputLabel for="content" :value="t('system_pages.content_label')" />
                            <div class="mt-1">
                                <AdvancedEditor v-model="form.content" min-height="400px" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.content" />
                        </div>

                        <input type="hidden" :value="form.list_id" />

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">{{ t('common.save') }}</PrimaryButton>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">{{ t('common.saved') }}</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

