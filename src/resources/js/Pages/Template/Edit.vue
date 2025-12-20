<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TiptapEditor from '@/Components/TiptapEditor.vue';

const props = defineProps({
    template: Object,
});

const form = useForm({
    name: props.template.name,
    content: props.template.content || '',
});

const submit = () => {
    form.put(route('templates.update', props.template.id));
};
</script>

<template>
    <Head :title="$t('templates.edit_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                     <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('templates.edit_title') }}
                    </h1>
                     <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('templates.edit_subtitle') }}
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
                        {{ $t('templates.actions.update') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
