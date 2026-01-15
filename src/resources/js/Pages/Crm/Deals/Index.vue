<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    pipeline: Object,
    stages: Array,
    pipelines: Array,
    contacts: Array,
    companies: Array,
    owners: Array,
});

// New deal form
const showNewDealModal = ref(false);
const form = useForm({
    name: "",
    crm_pipeline_id: props.pipeline?.id || "",
    crm_stage_id: props.stages?.[0]?.id || "",
    crm_contact_id: "",
    crm_company_id: "",
    owner_id: "",
    value: "",
    currency: "PLN",
    expected_close_date: "",
    notes: "",
});

const submitDeal = () => {
    form.post("/crm/deals", {
        preserveScroll: true,
        onSuccess: () => {
            showNewDealModal.value = false;
            form.reset();
        },
    });
};

// Drag and drop
const draggedDeal = ref(null);
const dragOverStageId = ref(null);

const handleDragStart = (deal) => {
    draggedDeal.value = deal;
};

const handleDragOver = (event) => {
    event.preventDefault();
};

const handleDragEnter = (stageId) => {
    if (draggedDeal.value) {
        dragOverStageId.value = stageId;
    }
};

const handleDragLeave = (event, stageId) => {
    // Only reset if we're leaving the column entirely (not entering a child element)
    const relatedTarget = event.relatedTarget;
    const currentTarget = event.currentTarget;
    if (!currentTarget.contains(relatedTarget)) {
        dragOverStageId.value = null;
    }
};

const handleDrop = (stageId) => {
    if (draggedDeal.value && draggedDeal.value.crm_stage_id !== stageId) {
        router.put(`/crm/deals/${draggedDeal.value.id}/stage`, {
            crm_stage_id: stageId,
        }, {
            preserveScroll: true,
        });
    }
    draggedDeal.value = null;
    dragOverStageId.value = null;
};

const handleDragEnd = () => {
    draggedDeal.value = null;
    dragOverStageId.value = null;
};

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value || 0); // TODO: Currency from user settings
};

// Stage total value
const stageTotal = (stage) => {
    return stage.deals?.reduce((sum, deal) => sum + (deal.value || 0), 0) || 0;
};
</script>

<template>
    <Head :title="$t('crm.deals.title', 'Lejek sprzedaży')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $t('crm.deals.title', 'Lejek sprzedaży') }}</h1>
                    <select v-if="pipelines?.length > 1"
                        class="rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        @change="(e) => router.get('/crm/deals', { pipeline_id: e.target.value })">
                        <option v-for="p in pipelines" :key="p.id" :value="p.id" :selected="p.id === pipeline?.id">
                            {{ p.name }}
                        </option>
                    </select>
                </div>
                <button @click="showNewDealModal = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('crm.deals.new_deal', 'Nowy deal') }}
                </button>
            </div>
        </template>

        <!-- Kanban Board -->
        <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 200px);">
            <div v-for="stage in stages" :key="stage.id"
                :class="[
                    'flex w-80 flex-shrink-0 flex-col rounded-2xl transition-all duration-200',
                    dragOverStageId === stage.id && draggedDeal?.crm_stage_id !== stage.id
                        ? 'bg-indigo-100 ring-2 ring-indigo-500 ring-opacity-50 dark:bg-indigo-900/30 dark:ring-indigo-400'
                        : 'bg-slate-100 dark:bg-slate-800/50'
                ]"
                @dragover="handleDragOver"
                @dragenter="handleDragEnter(stage.id)"
                @dragleave="(e) => handleDragLeave(e, stage.id)"
                @drop="handleDrop(stage.id)">
                <!-- Stage Header -->
                <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: stage.color || '#6b7280' }"></div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ $t('crm.deals.stages.' + stage.name.toLowerCase(), stage.name) }}</h3>
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                            {{ stage.deals?.length || 0 }}
                        </span>
                    </div>
                    <span class="text-sm text-slate-500">{{ formatCurrency(stageTotal(stage)) }}</span>
                </div>

                <!-- Deals -->
                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    <div v-for="deal in stage.deals" :key="deal.id"
                        class="cursor-grab rounded-xl bg-white p-4 shadow-sm transition hover:shadow-md dark:bg-slate-800"
                        draggable="true"
                        @dragstart="handleDragStart(deal)"
                        @dragend="handleDragEnd">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-white">{{ $t('crm.deals.new_deal') }}</h3>
                        <p v-if="deal.contact?.subscriber" class="mt-1 text-sm text-slate-500">
                            {{ deal.contact.subscriber.first_name }} {{ deal.contact.subscriber.last_name }}
                        </p>
                        <p v-if="deal.company" class="text-sm text-slate-500">
                            {{ deal.company.name }}
                        </p>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ formatCurrency(deal.value) }}
                            </span>
                            <span v-if="deal.owner" class="text-xs text-slate-400">
                                {{ deal.owner.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!stage.deals?.length" class="flex h-32 items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                        {{ $t('crm.deals.drag_here') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state when no stages -->
        <div v-if="!stages?.length" class="rounded-2xl bg-white py-16 text-center shadow-sm dark:bg-slate-800">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
            </svg>
            <p class="mt-4 text-slate-500 dark:text-slate-400">{{ $t('crm.deals.no_stages') }}</p>
        </div>

        <!-- New Deal Modal -->
        <div v-if="showNewDealModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showNewDealModal = false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('crm.deals.new_deal', 'Nowy deal') }}</h2>
                    <button @click="showNewDealModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitDeal" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.deal_name', 'Nazwa deala') }} *</label>
                        <input v-model="form.name" type="text" required
                            class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.form.stage_label', 'Etap') }}</label>
                            <select v-model="form.crm_stage_id"
                                class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.form.value_label', { currency: 'PLN' }, 'Wartość (PLN)') }}</label>
                            <input v-model="form.value" type="number" min="0" step="0.01"
                                class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.form.contact_label') }}</label>
                            <select v-model="form.crm_contact_id"
                                class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('crm.deals.form.none_option') }}</option>
                                <option v-for="contact in contacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.form.company_label') }}</label>
                            <select v-model="form.crm_company_id"
                                class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('crm.deals.form.none_option') }}</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.deals.form.notes_label', 'Notatki') }}</label>
                        <textarea v-model="form.notes" rows="2"
                            class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showNewDealModal = false"
                            class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                            {{ $t('common.cancel') }}
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ $t('crm.deals.create_deal', 'Utwórz deal') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
