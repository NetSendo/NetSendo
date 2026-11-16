<script setup>
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useToast } from "@/Composables/useToast";

defineProps({
    // [{ id, name, description, conditions_count, subscribers_count, messages_count, updated_at }]
    segments: { type: Array, default: () => [] },
});

const { t, locale } = useI18n();
const { toast, showToast, dismiss } = useToast({ flash: true });

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const segmentToDelete = ref(null);
const deleting = ref(false);

const deleteSegment = () => {
    deleting.value = true;
    router.delete(route("segments.destroy", segmentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            segmentToDelete.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="$t('segments.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('segments.title')" :subtitle="$t('segments.subtitle')">
                <CreateLink :href="route('segments.create')">{{ $t("segments.create") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <Panel flush>
                <EmptyState
                    v-if="segments.length === 0"
                    :title="$t('segments.empty_title')"
                    :description="$t('segments.empty_description')"
                >
                    <CreateLink :href="route('segments.create')">{{ $t("segments.create_first") }}</CreateLink>
                </EmptyState>

                <div v-else class="relative overflow-x-auto">
                    <table class="w-full text-sm sm:min-w-[640px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("segments.table.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ $t("segments.table.people") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ $t("segments.table.conditions") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ $t("segments.table.messages") }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("segments.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="segment in segments"
                                :key="segment.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[52%]">
                                    <Link
                                        :href="route('segments.edit', segment.id)"
                                        class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                        :title="segment.name"
                                    >
                                        {{ segment.name }}
                                    </Link>
                                    <p
                                        v-if="segment.description"
                                        class="truncate text-xs text-slate-600 dark:text-slate-400"
                                        :title="segment.description"
                                    >
                                        {{ segment.description }}
                                    </p>
                                    <!-- On a phone the count has no column of its own -->
                                    <p class="mt-1 text-xs tabular-nums text-slate-700 sm:hidden dark:text-slate-300">
                                        {{ $t("segments.people_count", { count: number(segment.subscribers_count) }) }}
                                    </p>
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-medium tabular-nums text-slate-900 dark:text-slate-100 max-sm:hidden"
                                >
                                    {{ number(segment.subscribers_count) }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300 max-sm:hidden">
                                    {{ number(segment.conditions_count) }}
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-700 dark:text-slate-300 max-sm:hidden">
                                    {{ number(segment.messages_count) }}
                                </td>
                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex justify-end">
                                        <ActionMenu :label="$t('segments.actions_for', { name: segment.name })">
                                            <ActionMenuItem :href="route('segments.edit', segment.id)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem :href="route('messages.create', { segment_id: segment.id })">
                                                {{ $t("segments.send_message") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="segmentToDelete = segment">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <p class="text-xs text-slate-600 dark:text-slate-400">{{ $t("segments.how_it_works") }}</p>
        </div>

        <ConfirmModal
            :show="!!segmentToDelete"
            type="danger"
            :title="$t('segments.delete_title')"
            :message="$t('segments.delete_description')"
            :confirm-text="$t('common.delete')"
            :processing="deleting"
            @close="segmentToDelete = null"
            @confirm="deleteSegment"
        >
            {{ segmentToDelete?.name }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
