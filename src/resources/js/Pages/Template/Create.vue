<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TiptapEditor from '@/Components/TiptapEditor.vue';

const { t } = useI18n();

const form = useForm({
    name: '',
    content: t('templates.default_content'),
});

const submit = () => {
    form.post(route('templates.store'));
};
</script>

<template>
    <Head :title="$t('templates.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                     <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('templates.create_title') }}
                    </h1>
                     <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('templates.create_subtitle') }}
                    </p>
                </div>
                 <Link
                    :href="route('templates.index')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                >
                    {{ $t('templates.back_to_list') }}
                </Link>
            </div>
        </template>

        <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900">
            <form @submit.prevent="submit" class="space-y-6">
                
                <!-- Name -->
                <div>
                    <InputLabel for="name" :value="$t('templates.fields.name')" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        :placeholder="$t('templates.fields.name_placeholder')"
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <!-- Editor -->
                <div>
                    <InputLabel for="content" :value="$t('templates.fields.content')" class="mb-2" />
                    <TiptapEditor v-model="form.content" />
                     <InputError class="mt-2" :message="form.errors.content" />
                </div>

                <div class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <Link
                        :href="route('templates.index')"
                        class="text-sm text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                    >
                        {{ $t('common.cancel') }}
                    </Link>

                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ $t('templates.actions.create') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
