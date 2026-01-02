<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    page: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.page?.name ?? '',
    url: props.page?.url ?? '',
    is_redirect: props.page?.is_redirect ?? false,
    shared_fields: props.page?.shared_fields ?? [],
    custom_fields: props.page?.custom_fields ?? [],
});

const { t } = useI18n();

const systemFields = computed(() => [
    { key: 'email', label: 'Email' },
    { key: 'first_name', label: t('common.first_name') || 'First Name' },
    { key: 'last_name', label: t('common.last_name') || 'Last Name' },
    { key: 'phone', label: t('common.phone') || 'Phone' },
    { key: 'id', label: 'ID' },
]);

const toggleField = (field) => {
    const index = form.shared_fields.indexOf(field);
    if (index === -1) {
        form.shared_fields.push(field);
    } else {
        form.shared_fields.splice(index, 1);
    }
};

const submit = () => {
    if (props.page) {
        form.put(route('external-pages.update', props.page.id));
    } else {
        form.post(route('external-pages.store'));
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <InputLabel for="name" :value="$t('external_pages.fields.name')" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                required
                autofocus
            />
            <InputError :message="form.errors.name" class="mt-2" />
        </div>

        <div>
            <InputLabel for="url" :value="$t('external_pages.fields.url')" />
            <TextInput
                id="url"
                v-model="form.url"
                type="url"
                class="mt-1 block w-full"
                placeholder="https://example.com/thank-you"
                required
            />
            <InputError :message="form.errors.url" class="mt-2" />
            <p class="text-sm text-gray-500 mt-1">{{ $t('external_pages.hints.url_fetch') }}</p>
        </div>

        <div class="block">
            <label class="flex items-center">
                <Checkbox v-model="form.is_redirect" name="is_redirect" :checked="form.is_redirect" />
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $t('external_pages.fields.redirect_mode') }}</span>
            </label>
            <p class="text-xs text-gray-500 mt-1 ml-6">
                {{ $t('external_pages.hints.proxy_mode') }}
            </p>
        </div>

        <div class="border-t pt-4">
             <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('external_pages.sections.shared_fields') }}</h3>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="field in systemFields" :key="field.key" class="flex items-center">
                    <input
                        type="checkbox"
                        :id="'field-'+field.key"
                        :value="field.key"
                        :checked="form.shared_fields.includes(field.key)"
                        @change="toggleField(field.key)"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    >
                    <label :for="'field-'+field.key" class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ field.label }}</label>
                </div>
             </div>
        </div>

         <div class="border-t pt-4">
             <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $t('external_pages.sections.custom_fields') }}</h3>
             <!-- For now simple text area to input keys, comma separated -->
             <div class="text-sm text-gray-500 mb-2">{{ $t('external_pages.hints.custom_fields') }}</div>
             <TextInput
                id="custom_fields"
                :model-value="form.custom_fields ? form.custom_fields.join(', ') : ''"
                @update:model-value="val => form.custom_fields = val.split(',').map(s => s.trim()).filter(s => s)"
                type="text"
                class="mt-1 block w-full"
                placeholder="city, age, interest"
            />
        </div>


        <div class="flex items-center gap-4">
            <PrimaryButton :disabled="form.processing">{{ $t('external_pages.actions.save') }}</PrimaryButton>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">{{ $t('external_pages.actions.saved') }}</p>
            </Transition>
        </div>
    </form>
</template>
