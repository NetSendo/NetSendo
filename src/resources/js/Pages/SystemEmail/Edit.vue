<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdvancedEditor from '@/Components/AdvancedEditor.vue';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

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

const displayListName = computed(() => props.list_name || t('system_emails.global_default'));

const form = useForm({
    subject: props.email.subject,
    content: props.email.content,
    is_active: props.email.is_active ?? true,
    list_id: props.list_id,
});

const submit = () => {
    form.put(route('settings.system-emails.update', props.email.id), {
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
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('system_emails.edit_title', { name: email.name }) }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('system_emails.context') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ displayListName }}</span>
                    </p>
                </div>
                <Link
                    :href="route('settings.system-emails.index', { list_id: list_id })"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ t('system_emails.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                
                <!-- Available placeholders -->
                <div class="bg-indigo-50 p-4 shadow sm:rounded-lg dark:bg-indigo-900/20">
                    <p class="text-sm font-medium text-indigo-700 dark:text-indigo-300 mb-2">
                        {{ t('system_emails.available_placeholders') }}:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <code v-for="placeholder in ['[[list-name]]', '[[email]]', '[[first-name]]', '[[last-name]]', '[[date]]', '[[activation-link]]', '[[unsubscribe-link]]']" 
                              :key="placeholder"
                              class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-800 rounded text-xs">
                            {{ placeholder }}
                        </code>
                    </div>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <div>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ t('system_emails.is_active') }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ t('system_emails.is_active_desc') }}</span>
                            </div>
                            <div class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" 
                                    :class="form.is_active ? 'bg-indigo-600' : 'bg-slate-200 dark:bg-slate-700'"
                                    @click="form.is_active = !form.is_active">
                                <span class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" 
                                        :class="{ 'translate-x-5': form.is_active }"></span>
                            </div>
                        </div>

                        <div>
                            <InputLabel for="subject" :value="t('system_emails.subject_label')" />
                            <TextInput
                                id="subject"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.subject"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.subject" />
                        </div>

                        <div>
                            <InputLabel for="content" :value="t('system_emails.content_label')" />
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
