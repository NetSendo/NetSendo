<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdvancedEditor from '@/Components/AdvancedEditor.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    list_id: {
        type: [String, Number, null],
        default: null,
    },
    list_name: {
        type: String,
        default: () => t('settings.system_messages.global_default'),
    },
});

const form = useForm({
    title: props.message.title,
    content: props.message.content,
    list_id: props.list_id,
});

const submit = () => {
    form.put(route('settings.system-messages.update', props.message.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('settings.system_messages.edit_title', { name: message.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('settings.system_messages.edit_title', { name: message.name }) }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('settings.system_messages.context') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ list_name }}</span>
                    </p>
                </div>
                <Link
                    :href="route('settings.system-messages.index', { list_id: list_id })"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ t('settings.system_messages.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <div>
                            <InputLabel for="title" :value="t('settings.system_messages.page_title_label')" />
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
                            <InputLabel for="content" :value="t('settings.system_messages.content_label')" />
                            <div class="mt-1">
                                <AdvancedEditor v-model="form.content" min-height="400px" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.content" />
                        </div>

                        <!-- Hidden context field -->
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
