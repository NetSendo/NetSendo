<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ActionMenu from '@/Components/Ui/ActionMenu.vue';
import ActionMenuItem from '@/Components/Ui/ActionMenuItem.vue';
import CreateLink from '@/Components/Ui/CreateLink.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import FormDialog from '@/Components/Ui/FormDialog.vue';
import Notice from '@/Components/Ui/Notice.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Panel from '@/Components/Ui/Panel.vue';
import SecretField from '@/Components/Ui/SecretField.vue';
import StatusPill from '@/Components/Ui/StatusPill.vue';
import Toast from '@/Components/Ui/Toast.vue';
import { errorClass, fieldClass, hintClass, labelClass } from '@/Components/Ui/fieldClass';
import { useToast } from '@/Composables/useToast';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();
const page = usePage();
// The server words every outcome (added, updated, refused and why): shown as it comes, in
// the corner of the page or in the footer of the open dialog
const { toast, dismiss } = useToast({ flash: true });

const formErrorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);

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
    form.clearErrors();
    dismiss();
    form.is_default = props.stores.length === 0;
    testResult.value = null;
    showFormModal.value = true;
};

// Open modal for editing store
const editStore = (store) => {
    editingStore.value = store;
    form.clearErrors();
    dismiss();
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

// A refused save (the shop does not answer, the address is taken) comes back as an ordinary
// page with an error message: the dialog stays open so the entries are not lost
const closeUnlessRefused = () => {
    if (!page.props.flash?.error) closeModal();
};

// Save settings (create or update)
const saveSettings = () => {
    if (editingStore.value) {
        form.put(route('settings.woocommerce.update', editingStore.value.id), {
            preserveScroll: true,
            onSuccess: closeUnlessRefused,
        });
    } else {
        form.post(route('settings.woocommerce.store'), {
            preserveScroll: true,
            onSuccess: closeUnlessRefused,
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

// What the shop reported about itself: its currency and its WooCommerce version
const storeFacts = (store) =>
    [
        store.store_info?.currency ? `${t('settings.woocommerce.currency')}: ${store.store_info.currency}` : null,
        store.store_info?.wc_version ? `WooCommerce ${store.store_info.wc_version}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

// Get connected stores count
const connectedCount = computed(() => props.stores.filter(s => s.is_connected).length);
</script>

<template>
    <Head :title="$t('settings.woocommerce.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('settings.woocommerce.title')" :subtitle="$t('settings.woocommerce.subtitle')">
                <CreateLink @click="openNewStoreModal">{{ $t('settings.woocommerce.add_store') }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <Panel flush>
                <EmptyState
                    v-if="stores.length === 0"
                    class="!border-t-0"
                    :title="$t('settings.woocommerce.stores_empty')"
                    :description="$t('settings.woocommerce.stores_empty_desc')"
                >
                    <CreateLink @click="openNewStoreModal">{{ $t('settings.woocommerce.add_store') }}</CreateLink>
                </EmptyState>

                <div v-else class="relative -mt-3 overflow-x-auto">
                    <table class="w-full text-sm sm:min-w-[860px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t('settings.woocommerce.store_name') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ $t('common.status') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t('settings.woocommerce.plugin_version') }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t('common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="store in stores"
                                :key="store.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <!-- The shop: its name here, its address, what it reported about itself -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[40%]">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="min-w-0 truncate text-left font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                            :title="store.display_name || store.name"
                                            @click="editStore(store)"
                                        >
                                            {{ store.display_name || store.name }}
                                        </button>
                                        <StatusPill v-if="store.is_default" tone="info" class="shrink-0 max-sm:hidden">
                                            {{ $t('settings.woocommerce.default_store') }}
                                        </StatusPill>
                                    </div>
                                    <a
                                        :href="store.store_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block truncate text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        :title="$t('settings.woocommerce.open_store')"
                                    >
                                        {{ store.store_url }}
                                    </a>
                                    <p v-if="storeFacts(store)" class="mt-0.5 truncate text-xs text-slate-600 dark:text-slate-400">
                                        {{ storeFacts(store) }}
                                    </p>
                                    <!-- On a phone the status has no column of its own and the badge
                                         would squeeze the name: both sit under it -->
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 sm:hidden">
                                        <StatusPill :tone="store.is_connected ? 'success' : 'neutral'">
                                            {{ store.is_connected ? $t('settings.woocommerce.connected') : $t('settings.woocommerce.not_connected') }}
                                        </StatusPill>
                                        <StatusPill v-if="store.is_default" tone="info">
                                            {{ $t('settings.woocommerce.default_store') }}
                                        </StatusPill>
                                    </div>
                                </td>

                                <td class="px-3 py-3 max-sm:hidden">
                                    <StatusPill :tone="store.is_connected ? 'success' : 'neutral'">
                                        {{ store.is_connected ? $t('settings.woocommerce.connected') : $t('settings.woocommerce.not_connected') }}
                                    </StatusPill>
                                    <!-- The answer of the last test run from this page -->
                                    <p
                                        v-if="storeTestResults[store.id]"
                                        role="status"
                                        class="mt-1 text-xs font-medium"
                                        :class="
                                            storeTestResults[store.id].success
                                                ? 'text-emerald-700 dark:text-emerald-300'
                                                : 'text-rose-700 dark:text-rose-300'
                                        "
                                        :title="storeTestResults[store.id].error || ''"
                                    >
                                        {{
                                            storeTestResults[store.id].success
                                                ? $t('settings.woocommerce.test_success')
                                                : $t('settings.woocommerce.test_failed')
                                        }}
                                    </p>
                                    <p
                                        v-else-if="store.connection_verified_at"
                                        class="mt-1 text-xs tabular-nums text-slate-600 dark:text-slate-400"
                                    >
                                        {{ $t('settings.woocommerce.verified_at') }}: {{ store.connection_verified_at }}
                                    </p>
                                </td>

                                <td class="px-3 py-3 max-sm:hidden">
                                    <template v-if="store.plugin_version">
                                        <p class="tabular-nums text-slate-900 dark:text-slate-100">v{{ store.plugin_version }}</p>
                                        <p v-if="store.update_available" class="text-xs text-amber-800 dark:text-amber-300">
                                            {{ $t('settings.woocommerce.update_to') }} v{{ store.latest_version }}
                                        </p>
                                        <p
                                            v-if="store.plugin_is_stale"
                                            class="text-xs text-amber-800 dark:text-amber-300"
                                            :title="$t('settings.woocommerce.stale_plugin_hint')"
                                        >
                                            {{ $t('settings.woocommerce.stale_connection') }}
                                        </p>
                                    </template>
                                    <span v-else class="text-slate-600 dark:text-slate-400">—</span>
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                            @click="editStore(store)"
                                        >
                                            {{ $t('common.edit') }}
                                        </button>
                                        <ActionMenu :label="$t('settings.woocommerce.actions_for', { name: store.display_name || store.name })">
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="editStore(store)">
                                                {{ $t('common.edit') }}
                                            </ActionMenuItem>
                                            <template v-if="store.is_connected">
                                                <ActionMenuItem :disabled="testingStoreId === store.id" @click="testStoreConnection(store)">
                                                    {{
                                                        testingStoreId === store.id
                                                            ? $t('settings.woocommerce.testing')
                                                            : $t('settings.woocommerce.test_connection')
                                                    }}
                                                </ActionMenuItem>
                                                <ActionMenuItem v-if="!store.is_default" @click="setDefault(store)">
                                                    {{ $t('settings.woocommerce.set_as_default') }}
                                                </ActionMenuItem>
                                                <ActionMenuItem @click="disconnectStore(store)">
                                                    {{ $t('settings.woocommerce.disconnect') }}
                                                </ActionMenuItem>
                                            </template>
                                            <ActionMenuItem v-else @click="reconnectStore(store)">
                                                {{ $t('settings.woocommerce.reconnect') }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="deleteStore(store)">
                                                {{ $t('settings.woocommerce.delete_store') }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- What a connected shop is good for -->
            <Panel :title="$t('settings.woocommerce.features_title')">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="feature in ['import', 'recently_viewed', 'templates', 'sync']" :key="feature">
                        <dt class="font-medium text-slate-900 dark:text-white">
                            {{ $t('settings.woocommerce.feature_' + feature) }}
                        </dt>
                        <dd class="mt-1 text-slate-600 dark:text-slate-400">
                            {{ $t('settings.woocommerce.feature_' + feature + '_desc') }}
                        </dd>
                    </div>
                </dl>
            </Panel>
        </div>

        <!-- A shop: what to call it, where it is, the keys WooCommerce issued for it -->
        <FormDialog
            :show="showFormModal"
            :title="editingStore ? $t('settings.woocommerce.edit_store') : $t('settings.woocommerce.add_store')"
            :processing="form.processing"
            :submit-label="$t('settings.woocommerce.save_settings')"
            :errors="formErrorMessages"
            :toast="toast"
            @close="closeModal"
            @submit="saveSettings"
        >
            <div class="space-y-4">
                <div>
                    <label for="woo-name" :class="labelClass">{{ $t('settings.woocommerce.store_name') }}</label>
                    <input
                        id="woo-name"
                        v-model="form.name"
                        type="text"
                        required
                        :placeholder="$t('settings.woocommerce.store_name_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                    <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                    <p v-else :class="hintClass">{{ $t('settings.woocommerce.store_name_help') }}</p>
                </div>

                <div>
                    <label for="woo-url" :class="labelClass">{{ $t('settings.woocommerce.store_url') }}</label>
                    <input
                        id="woo-url"
                        v-model="form.store_url"
                        type="url"
                        required
                        :placeholder="$t('settings.woocommerce.store_url_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.store_url }]"
                    />
                    <p v-if="form.errors.store_url" :class="errorClass">{{ form.errors.store_url }}</p>
                    <p v-else :class="hintClass">{{ $t('settings.woocommerce.store_url_help') }}</p>
                </div>

                <div>
                    <label for="woo-key" :class="labelClass">{{ $t('settings.woocommerce.consumer_key') }}</label>
                    <input
                        id="woo-key"
                        v-model="form.consumer_key"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :required="!editingStore"
                        :placeholder="editingStore?.consumer_key || 'ck_…'"
                        class="mt-1 font-mono"
                        :class="[fieldClass, { '!border-rose-500': form.errors.consumer_key }]"
                    />
                    <p v-if="form.errors.consumer_key" :class="errorClass">{{ form.errors.consumer_key }}</p>
                    <p v-else-if="editingStore" :class="hintClass">
                        {{ $t('settings.woocommerce.credentials_update_hint') }}
                    </p>
                </div>

                <SecretField
                    id="woo-secret"
                    v-model="form.consumer_secret"
                    :label="$t('settings.woocommerce.consumer_secret')"
                    placeholder="cs_…"
                    :error="form.errors.consumer_secret"
                />

                <label class="flex items-center gap-2.5">
                    <input v-model="form.is_default" type="checkbox" class="h-4 w-4 accent-indigo-600" />
                    <span class="text-sm text-slate-800 dark:text-slate-200">{{ $t('settings.woocommerce.set_as_default') }}</span>
                </label>

                <Notice tone="info" :title="$t('settings.woocommerce.api_keys_title')">
                    <p>{{ $t('settings.woocommerce.api_keys_help') }}</p>
                    <p class="mt-1 font-mono text-xs">WooCommerce → Settings → Advanced → REST API</p>
                </Notice>

                <!-- Try the keys before saving them -->
                <div class="flex flex-col gap-3">
                    <SecondaryButton
                        type="button"
                        class="self-start"
                        :disabled="testingConnection || !form.store_url || !form.consumer_key || !form.consumer_secret"
                        @click="testConnection"
                    >
                        {{ testingConnection ? $t('settings.woocommerce.testing') : $t('settings.woocommerce.test_connection') }}
                    </SecondaryButton>
                    <Notice
                        v-if="testResult"
                        :tone="testResult.success ? 'success' : 'danger'"
                        :title="
                            testResult.success
                                ? $t('settings.woocommerce.connection_success')
                                : $t('settings.woocommerce.connection_failed')
                        "
                    >
                        <p v-if="testResult.error">{{ testResult.error }}</p>
                        <p v-if="testResult.store_info?.currency">
                            {{ $t('settings.woocommerce.currency') }}: {{ testResult.store_info.currency }}
                        </p>
                    </Notice>
                </div>
            </div>
        </FormDialog>

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

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="showFormModal ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
