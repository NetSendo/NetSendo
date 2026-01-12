<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    stores: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showFormModal = ref(false);
const editingStore = ref(null);

// Disconnect modal state
const showDisconnectModal = ref(false);
const storeToDisconnect = ref(null);
const disconnecting = ref(false);

// Delete modal state
const showDeleteModal = ref(false);
const storeToDelete = ref(null);
const deleting = ref(false);

// Per-store connection test state
const testingStoreId = ref(null);
const storeTestResults = ref({});

// Form for saving settings
const form = useForm({
    name: '',
    store_url: '',
    consumer_key: '',
    consumer_secret: '',
    is_default: false,
});

// Test connection state (for modal form)
const testingConnection = ref(false);
const testResult = ref(null);

// Open modal for new store
const openNewStoreModal = () => {
    editingStore.value = null;
    form.reset();
    form.is_default = props.stores.length === 0;
    testResult.value = null;
    showFormModal.value = true;
};

// Open modal for editing store
const editStore = (store) => {
    editingStore.value = store;
    form.name = store.name || '';
    form.store_url = store.store_url;
    form.consumer_key = '';
    form.consumer_secret = '';
    form.is_default = store.is_default;
    testResult.value = null;
    showFormModal.value = true;
};

// Close modal
const closeModal = () => {
    showFormModal.value = false;
    editingStore.value = null;
    form.reset();
    testResult.value = null;
};

// Test connection (for modal form)
const testConnection = async () => {
    if (!form.store_url || !form.consumer_key || !form.consumer_secret) {
        testResult.value = { success: false, error: t('settings.woocommerce.fill_all_fields') };
        return;
    }

    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await axios.post(route('settings.woocommerce.test'), {
            store_url: form.store_url,
            consumer_key: form.consumer_key,
            consumer_secret: form.consumer_secret,
        });

        testResult.value = response.data;
    } catch (error) {
        testResult.value = {
            success: false,
            error: error.response?.data?.message || error.message,
        };
    } finally {
        testingConnection.value = false;
    }
};

// Test connection for existing store (on list)
const testStoreConnection = async (store) => {
    testingStoreId.value = store.id;

    try {
        const response = await axios.post(route('settings.woocommerce.test'), {
            store_id: store.id,
        });

        storeTestResults.value[store.id] = response.data;
    } catch (error) {
        storeTestResults.value[store.id] = {
            success: false,
            error: error.response?.data?.message || error.message,
        };
    } finally {
        testingStoreId.value = null;
    }
};

// Save settings (create or update)
const saveSettings = () => {
    if (editingStore.value) {
        form.put(route('settings.woocommerce.update', editingStore.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('settings.woocommerce.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

// Set store as default
const setDefault = (store) => {
    router.post(route('settings.woocommerce.set-default', store.id), {}, {
        preserveScroll: true,
    });
};

// Disconnect store - show modal
const disconnectStore = (store) => {
    storeToDisconnect.value = store;
    showDisconnectModal.value = true;
};

// Confirm disconnect
const confirmDisconnect = () => {
    if (!storeToDisconnect.value) return;

    disconnecting.value = true;
    router.post(route('settings.woocommerce.disconnect', storeToDisconnect.value.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            disconnecting.value = false;
            showDisconnectModal.value = false;
            storeToDisconnect.value = null;
        },
    });
};

// Reconnect store
const reconnectStore = (store) => {
    router.post(route('settings.woocommerce.reconnect', store.id), {}, {
        preserveScroll: true,
    });
};

// Delete store - show modal
const deleteStore = (store) => {
    storeToDelete.value = store;
    showDeleteModal.value = true;
};

// Confirm delete
const confirmDelete = () => {
    if (!storeToDelete.value) return;

    deleting.value = true;
    router.delete(route('settings.woocommerce.destroy', storeToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            showDeleteModal.value = false;
            storeToDelete.value = null;
        },
    });
};

// Get connected stores count
const connectedCount = computed(() => props.stores.filter(s => s.is_connected).length);
</script>

<template>
    <Head :title="$t('settings.woocommerce.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/30">
                                <svg viewBox="0 0 24 24" fill="#96588a" class="h-8 w-8">
                                    <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407zM1.533 5.72h20.934c.847 0 1.533.686 1.533 1.532v.47a.407.407 0 0 1-.406.406h-5.01a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H9.767a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 1 0 10.496V7.252c0-.847.686-1.533 1.533-1.533z"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                                    {{ $t('settings.woocommerce.title') }}
                                </h1>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $t('settings.woocommerce.subtitle') }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="openNewStoreModal"
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-500"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ $t('settings.woocommerce.add_store') }}
                        </button>
                    </div>
                </div>

                <!-- Stores List -->
                <div v-if="stores.length > 0" class="space-y-4">
                    <div
                        v-for="store in stores"
                        :key="store.id"
                        class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50 dark:bg-slate-800 dark:ring-slate-700/50"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-4">
                                <!-- Status Icon -->
                                <div :class="[
                                    'flex h-12 w-12 items-center justify-center rounded-xl',
                                    store.is_connected
                                        ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                        : 'bg-slate-100 dark:bg-slate-700'
                                ]">
                                    <svg v-if="store.is_connected" class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829" />
                                    </svg>
                                </div>

                                <!-- Store Info -->
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-slate-900 dark:text-white">
                                            {{ store.display_name || store.name }}
                                        </h3>
                                        <span v-if="store.is_default" class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                            {{ $t('settings.woocommerce.default_store') }}
                                        </span>
                                        <span v-if="store.is_connected" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            {{ $t('settings.woocommerce.connected') }}
                                        </span>
                                        <span v-else class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                            {{ $t('settings.woocommerce.not_connected') }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <a
                                            :href="store.store_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-sm text-purple-600 hover:text-purple-500 dark:text-purple-400 dark:hover:text-purple-300 hover:underline inline-flex items-center gap-1"
                                            :title="$t('settings.woocommerce.open_store')"
                                        >
                                            {{ store.store_url }}
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                        <!-- Test Result Indicator -->
                                        <span v-if="storeTestResults[store.id]" :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                            storeTestResults[store.id].success
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                        ]">
                                            <svg v-if="storeTestResults[store.id].success" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            {{ storeTestResults[store.id].success ? $t('settings.woocommerce.test_success') : $t('settings.woocommerce.test_failed') }}
                                        </span>
                                    </div>
                                    <div v-if="store.store_info" class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                                        <span v-if="store.store_info.currency">{{ $t('settings.woocommerce.currency') }}: {{ store.store_info.currency }}</span>
                                        <span v-if="store.store_info.wc_version">WooCommerce: {{ store.store_info.wc_version }}</span>
                                    </div>
                                    <!-- Plugin Version Info -->
                                    <div v-if="store.plugin_version" class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                            </svg>
                                            {{ $t('settings.woocommerce.plugin_version') }}: v{{ store.plugin_version }}
                                        </span>
                                        <span
                                            v-if="store.update_available"
                                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            {{ $t('settings.woocommerce.update_to') }} v{{ store.latest_version }}
                                        </span>
                                        <span
                                            v-if="store.plugin_is_stale"
                                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                            :title="$t('settings.woocommerce.stale_plugin_hint')"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            {{ $t('settings.woocommerce.stale_connection') }}
                                        </span>
                                    </div>
                                    <p v-if="store.connection_verified_at" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                        {{ $t('settings.woocommerce.verified_at') }}: {{ store.connection_verified_at }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="!store.is_default && store.is_connected"
                                    @click="setDefault(store)"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/20"
                                    :title="$t('settings.woocommerce.set_as_default')"
                                >
                                    {{ $t('settings.woocommerce.set_as_default') }}
                                </button>
                                <!-- Test Connection Button -->
                                <button
                                    v-if="store.is_connected"
                                    @click="testStoreConnection(store)"
                                    :disabled="testingStoreId === store.id"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/20 disabled:opacity-50"
                                    :title="$t('settings.woocommerce.test_connection')"
                                >
                                    <svg v-if="testingStoreId === store.id" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span v-else>{{ $t('settings.woocommerce.test_store') }}</span>
                                </button>
                                <button
                                    @click="editStore(store)"
                                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                                    :title="$t('common.edit')"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="store.is_connected"
                                    @click="disconnectStore(store)"
                                    class="rounded-lg p-2 text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20"
                                    :title="$t('settings.woocommerce.disconnect')"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829" />
                                    </svg>
                                </button>
                                <button
                                    v-else
                                    @click="reconnectStore(store)"
                                    class="rounded-lg p-2 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                                    :title="$t('settings.woocommerce.reconnect')"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteStore(store)"
                                    class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    :title="$t('settings.woocommerce.delete_store')"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-2xl bg-white p-12 text-center shadow-sm ring-1 ring-slate-200/50 dark:bg-slate-800 dark:ring-slate-700/50">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                        <svg viewBox="0 0 24 24" fill="#96588a" class="h-8 w-8">
                            <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ $t('settings.woocommerce.stores_empty') }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('settings.woocommerce.stores_empty_desc') }}
                    </p>
                    <button
                        @click="openNewStoreModal"
                        class="mt-6 inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-500"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t('settings.woocommerce.add_store') }}
                    </button>
                </div>

                <!-- Features Info -->
                <div class="mt-8 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50 p-8 dark:from-purple-900/20 dark:to-pink-900/20">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                        {{ $t('settings.woocommerce.features_title') }}
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">📦</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_import') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_import_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">👁️</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_recently_viewed') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_recently_viewed_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">📧</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_templates') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_templates_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800/50 text-lg">🔄</div>
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $t('settings.woocommerce.feature_sync') }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('settings.woocommerce.feature_sync_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Store Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div class="relative w-full max-w-lg rounded-2xl bg-white p-8 shadow-2xl dark:bg-slate-800">
                        <!-- Modal Header -->
                        <div class="mb-6 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                {{ editingStore ? $t('settings.woocommerce.edit_store') : $t('settings.woocommerce.add_store') }}
                            </h2>
                            <button
                                @click="closeModal"
                                class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Form -->
                        <form @submit.prevent="saveSettings" class="space-y-5">
                            <!-- Store Name -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    {{ $t('settings.woocommerce.store_name') }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    :placeholder="$t('settings.woocommerce.store_name_placeholder')"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('settings.woocommerce.store_name_help') }}
                                </p>
                            </div>

                            <!-- Store URL -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    {{ $t('settings.woocommerce.store_url') }}
                                </label>
                                <input
                                    v-model="form.store_url"
                                    type="url"
                                    required
                                    :placeholder="$t('settings.woocommerce.store_url_placeholder')"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('settings.woocommerce.store_url_help') }}
                                </p>
                            </div>

                            <!-- Consumer Key -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    {{ $t('settings.woocommerce.consumer_key') }}
                                </label>
                                <input
                                    v-model="form.consumer_key"
                                    type="text"
                                    :required="!editingStore"
                                    :placeholder="editingStore?.consumer_key || 'ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white font-mono"
                                />
                                <p v-if="editingStore" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('settings.woocommerce.credentials_update_hint') }}
                                </p>
                            </div>

                            <!-- Consumer Secret -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    {{ $t('settings.woocommerce.consumer_secret') }}
                                </label>
                                <input
                                    v-model="form.consumer_secret"
                                    type="password"
                                    :required="!editingStore"
                                    placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white font-mono"
                                />
                            </div>

                            <!-- Set as Default -->
                            <div class="flex items-center gap-3">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    id="is_default"
                                    class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900"
                                />
                                <label for="is_default" class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ $t('settings.woocommerce.set_as_default') }}
                                </label>
                            </div>

                            <!-- Help text -->
                            <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-900/20">
                                <div class="flex gap-3">
                                    <svg class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div class="text-sm text-amber-700 dark:text-amber-300">
                                        <p class="font-medium mb-1">{{ $t('settings.woocommerce.api_keys_title') }}</p>
                                        <p>{{ $t('settings.woocommerce.api_keys_help') }}</p>
                                        <p class="mt-2 font-mono text-xs bg-amber-100 dark:bg-amber-900/30 px-2 py-1 rounded inline-block">
                                            WooCommerce → Settings → Advanced → REST API
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Test Result -->
                            <div v-if="testResult" :class="[
                                'rounded-xl p-4',
                                testResult.success
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20'
                                    : 'bg-red-50 dark:bg-red-900/20'
                            ]">
                                <div class="flex items-start gap-3">
                                    <svg v-if="testResult.success" class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p :class="testResult.success ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'" class="font-medium">
                                            {{ testResult.success ? $t('settings.woocommerce.connection_success') : $t('settings.woocommerce.connection_failed') }}
                                        </p>
                                        <p v-if="testResult.error" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                            {{ testResult.error }}
                                        </p>
                                        <div v-if="testResult.store_info" class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">
                                            <span v-if="testResult.store_info.currency">{{ $t('settings.woocommerce.currency') }}: {{ testResult.store_info.currency }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="testConnection"
                                    :disabled="testingConnection || !form.store_url || !form.consumer_key || !form.consumer_secret"
                                    class="inline-flex items-center gap-2 rounded-xl border border-purple-200 bg-white px-5 py-2.5 text-sm font-medium text-purple-700 transition-colors hover:bg-purple-50 disabled:opacity-50 disabled:cursor-not-allowed dark:border-purple-700 dark:bg-transparent dark:text-purple-400 dark:hover:bg-purple-900/20"
                                >
                                    <svg v-if="testingConnection" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    {{ testingConnection ? $t('settings.woocommerce.testing') : $t('settings.woocommerce.test_connection') }}
                                </button>

                                <button
                                    type="submit"
                                    :disabled="form.processing || !form.name || !form.store_url || (!editingStore && (!form.consumer_key || !form.consumer_secret))"
                                    class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    {{ form.processing ? $t('common.saving') : $t('settings.woocommerce.save_settings') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Disconnect Confirmation Modal -->
        <ConfirmModal
            :show="showDisconnectModal"
            :title="$t('settings.woocommerce.disconnect_modal_title')"
            :message="$t('settings.woocommerce.disconnect_modal_message', { name: storeToDisconnect?.display_name || storeToDisconnect?.name || '' })"
            :confirm-text="$t('settings.woocommerce.disconnect')"
            type="warning"
            :processing="disconnecting"
            @close="showDisconnectModal = false; storeToDisconnect = null"
            @confirm="confirmDisconnect"
        />

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            :title="$t('settings.woocommerce.delete_modal_title')"
            :message="$t('settings.woocommerce.delete_modal_message', { name: storeToDelete?.display_name || storeToDelete?.name || '' })"
            :confirm-text="$t('settings.woocommerce.delete_store')"
            type="danger"
            :processing="deleting"
            @close="showDeleteModal = false; storeToDelete = null"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
