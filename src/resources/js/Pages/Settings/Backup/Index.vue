<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ActionMenu from '@/Components/Ui/ActionMenu.vue';
import ActionMenuItem from '@/Components/Ui/ActionMenuItem.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Panel from '@/Components/Ui/Panel.vue';
import Toast from '@/Components/Ui/Toast.vue';
import { useToast } from '@/Composables/useToast';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '@/Composables/useDateTime';

const { t } = useI18n();
const { formatDate: formatDateBase } = useDateTime();

const props = defineProps({
    backups: Array,
    disk: String,
    restoreSupported: { type: Boolean, default: false },
    currentOperation: { type: Object, default: null },
    lastOperation: { type: Object, default: null },
});

const onlyDb = ref(true);
const processing = ref(false);

// A create/restore is in progress when the server reports a running operation.
const isRunning = computed(() => !!props.currentOperation);
const runningType = computed(() => props.currentOperation?.type ?? null);

// What the server says after a request (queued, deleted, refused) is shown as it comes
const { toast, dismiss } = useToast({ flash: true });

// ---- Progress polling -------------------------------------------------------
// While an operation runs we refresh only the operation/list props so the user
// sees live progress and the result without a blocking request.
let pollTimer = null;

function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(() => {
        router.reload({
            only: ['backups', 'currentOperation', 'lastOperation'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(isRunning, (running) => (running ? startPolling() : stopPolling()));
onMounted(() => { if (isRunning.value) startPolling(); });
onUnmounted(stopPolling);

// ---- Result banner ----------------------------------------------------------
const lastResult = computed(() => {
    const op = props.lastOperation;
    if (!op || isRunning.value) return null;

    const ok = op.status === 'success';
    const key = `backup.result_${ok ? 'success' : 'failed'}_${op.type}`;
    return {
        ok,
        title: t(key),
        message: op.message || null,
        safetyBackup: op.type === 'restore' && ok ? op.safety_backup : null,
    };
});

// ---- Actions ----------------------------------------------------------------
function createBackup() {
    if (isRunning.value) return;
    router.post(route('settings.backup.create'), { only_db: onlyDb.value }, {
        preserveScroll: true,
    });
}

function downloadBackup(filename) {
    window.location.href = route('settings.backup.download', filename);
}

// Restore confirmation
const restoreTarget = ref(null);
function askRestore(filename) {
    restoreTarget.value = filename;
}
function confirmRestore() {
    if (!restoreTarget.value) return;
    processing.value = true;
    router.post(route('settings.backup.restore', restoreTarget.value), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            restoreTarget.value = null;
        },
    });
}

// Delete confirmation
const deleteTarget = ref(null);
function askDelete(filename) {
    deleteTarget.value = filename;
}
function confirmDelete() {
    if (!deleteTarget.value) return;
    processing.value = true;
    router.delete(route('settings.backup.destroy', deleteTarget.value), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            deleteTarget.value = null;
        },
    });
}

function formatDate(date) {
    return formatDateBase(date, null, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="t('backup.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('backup.title')">
                <PrimaryButton type="button" :disabled="isRunning" @click="createBackup">
                    {{ isRunning && runningType === 'create' ? t('backup.creating') : t('backup.create') }}
                </PrimaryButton>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <!-- A backup or a restore is running: the page refreshes itself until it is done -->
            <div
                v-if="isRunning"
                role="status"
                class="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 dark:border-indigo-500/30 dark:bg-indigo-500/10"
            >
                <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
                    {{ runningType === 'restore' ? t('backup.running_restore') : t('backup.running_create') }}
                </p>
                <p class="mt-0.5 text-sm text-indigo-900 dark:text-indigo-200">
                    {{ t('backup.running_note') }}
                </p>
            </div>

            <!-- How the last one ended -->
            <div
                v-else-if="lastResult"
                :role="lastResult.ok ? 'status' : 'alert'"
                class="rounded-2xl border px-5 py-4"
                :class="
                    lastResult.ok
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                        : 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10'
                "
            >
                <p
                    class="text-sm font-medium"
                    :class="lastResult.ok ? 'text-emerald-900 dark:text-emerald-200' : 'text-rose-900 dark:text-rose-200'"
                >
                    {{ lastResult.title }}
                </p>
                <p
                    v-if="lastResult.message"
                    class="mt-0.5 break-words text-sm"
                    :class="lastResult.ok ? 'text-emerald-900 dark:text-emerald-200' : 'text-rose-900 dark:text-rose-200'"
                >
                    {{ lastResult.message }}
                </p>
                <p v-if="lastResult.safetyBackup" class="mt-0.5 break-words text-sm text-emerald-900 dark:text-emerald-200">
                    {{ t('backup.safety_backup_note', { name: lastResult.safetyBackup }) }}
                </p>
            </div>

            <Panel flush :title="t('backup.list_title')">
                <div class="flex flex-col gap-3 px-5 pb-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('backup.info_text') }}</p>
                    <!-- What the next backup takes -->
                    <label class="flex items-center gap-2.5">
                        <input
                            v-model="onlyDb"
                            type="checkbox"
                            :disabled="isRunning"
                            class="h-4 w-4 accent-indigo-600"
                        />
                        <span class="text-sm text-slate-800 dark:text-slate-200">{{ t('backup.only_db') }}</span>
                    </label>
                </div>

                <EmptyState v-if="backups.length === 0" :title="t('backup.no_backups')">
                    <PrimaryButton type="button" :disabled="isRunning" @click="createBackup">
                        {{ t('backup.create_first') }}
                    </PrimaryButton>
                </EmptyState>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full text-sm sm:min-w-[680px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ t('backup.filename') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ t('backup.size') }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">{{ t('backup.date') }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ t('backup.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="backup in backups"
                                :key="backup.name"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[52%]">
                                    <p class="truncate font-mono text-xs text-slate-900 dark:text-white" :title="backup.name">
                                        {{ backup.name }}
                                    </p>
                                    <!-- On a phone the size and the date have no columns of their own -->
                                    <p class="mt-0.5 text-xs tabular-nums text-slate-600 sm:hidden dark:text-slate-400">
                                        {{ backup.size_human }}
                                        <span aria-hidden="true">·</span>
                                        {{ formatDate(backup.date) }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums text-slate-900 max-sm:hidden dark:text-slate-100">
                                    {{ backup.size_human }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ formatDate(backup.date) }}
                                </td>
                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                            @click="downloadBackup(backup.name)"
                                        >
                                            {{ t('backup.download') }}
                                        </button>
                                        <ActionMenu :label="t('backup.actions_for', { name: backup.name })">
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="downloadBackup(backup.name)">
                                                {{ t('backup.download') }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                v-if="restoreSupported"
                                                :disabled="isRunning"
                                                @click="askRestore(backup.name)"
                                            >
                                                {{ t('backup.restore') }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger :disabled="isRunning" @click="askDelete(backup.name)">
                                                {{ t('backup.delete') }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>
        </div>

        <!-- Restore confirmation -->
        <ConfirmModal
            :show="restoreTarget !== null"
            type="danger"
            :title="t('backup.restore_confirm_title')"
            :message="`${t('backup.restore_confirm_message')} ${t('backup.restore_safety_note')}`"
            :confirm-text="t('backup.restore')"
            :processing="processing"
            @close="restoreTarget = null"
            @confirm="confirmRestore"
        >
            {{ restoreTarget }}
        </ConfirmModal>

        <!-- Delete confirmation -->
        <ConfirmModal
            :show="deleteTarget !== null"
            type="danger"
            :title="t('backup.delete_confirm_title')"
            :message="t('backup.confirm_delete')"
            :confirm-text="t('backup.delete')"
            :processing="processing"
            @close="deleteTarget = null"
            @confirm="confirmDelete"
        >
            {{ deleteTarget }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
