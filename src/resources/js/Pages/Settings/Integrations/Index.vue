<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    integrations: Array,
    google_redirect_uri: String,
});

const form = useForm({
    name: '',
    client_id: '',
    client_secret: '',
});

const showAddModal = ref(false);

const openAddModal = () => {
    form.reset();
    form.clearErrors();
    showAddModal.value = true;
};

const submit = () => {
    form.post(route('settings.integrations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    // Optional toast
};

const deleteForm = useForm({});
const confirmDelete = (id) => {
    if (confirm(t('integrations.confirm_delete'))) {
        deleteForm.delete(route('settings.integrations.destroy', id));
    }
};
</script>

<template>
    <Head :title="$t('integrations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $t('integrations.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                    <section>
                        <header class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('integrations.google.title') }}</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $t('integrations.google.description') }}
                                </p>
                            </div>
                            <PrimaryButton @click="openAddModal">{{ $t('integrations.google.add') }}</PrimaryButton>
                        </header>

                        <!-- List of Integrations -->
                        <div class="mt-6 space-y-4">
                            <div v-if="integrations.length === 0" class="rounded-md bg-gray-50 p-4 text-center text-sm text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                {{ $t('integrations.google.empty') }}
                            </div>

                            <div v-for="integration in integrations" :key="integration.id" class="flex flex-col gap-4 rounded-lg border border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ integration.name }}</h3>
                                    <p class="text-xs font-mono text-gray-500 dark:text-gray-400 max-w-md truncate">{{ integration.client_id }}</p>
                                    <span 
                                        class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="integration.status === 'active' 
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'"
                                    >
                                        {{ integration.status === 'active' ? $t('integrations.google.connected') : $t('integrations.google.ready') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a
                                        :href="route('settings.integrations.verify', integration.id)"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800"
                                    >
                                        <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24">
                                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                        </svg>
                                        {{ integration.status === 'active' ? $t('integrations.google.reconnect') : $t('integrations.google.connect') }}
                                    </a>
                                    
                                    <DangerButton @click="confirmDelete(integration.id)">{{ $t('common.delete') }}</DangerButton>
                                </div>
                            </div>
                        </div>

                         <!-- Redirect URI Info -->
                         <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-700">
                            <InputLabel :value="$t('integrations.google.redirect_uri')" />
                            <div class="mt-2 flex rounded-md shadow-sm">
                                <div class="relative flex flex-grow items-stretch focus-within:z-10">
                                    <input
                                        type="text"
                                        readonly
                                        :value="props.google_redirect_uri"
                                        class="block w-full rounded-none rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                </div>
                                <button
                                    type="button"
                                    @click="copyToClipboard(props.google_redirect_uri)"
                                    class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    <span>{{ $t('common.copy') || 'Copy' }}</span>
                                </button>
                            </div>
                        </div>

                    </section>
                </div>

            </div>
        </div>

        <!-- Add Modal -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $t('integrations.google.modal_title') }}</h2>
                
                <form @submit.prevent="submit" class="mt-6 space-y-6" autocomplete="off">
                    <div>
                        <InputLabel for="name" :value="$t('integrations.google.name_placeholder')" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="client_id" value="Client ID" />
                        <TextInput id="client_id" type="text" class="mt-1 block w-full" v-model="form.client_id" required autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.client_id" />
                    </div>

                    <div>
                        <InputLabel for="client_secret" value="Client Secret" />
                        <TextInput id="client_secret" type="password" class="mt-1 block w-full" v-model="form.client_secret" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.client_secret" />
                    </div>

                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="showAddModal = false">{{ $t('common.cancel') }}</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">{{ $t('common.save') }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
