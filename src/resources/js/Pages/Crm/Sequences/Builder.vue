<script setup>
import { ref, computed, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import InputError from "@/Components/InputError.vue";
import draggable from "vuedraggable";

const props = defineProps({
    sequence: Object,
    triggerTypes: Array,
    actionTypes: Array,
    taskTypes: Array,
    priorities: Array,
});

const { t } = useI18n();

const isEditing = computed(() => !!props.sequence?.id);

const form = useForm({
    name: props.sequence?.name || "",
    description: props.sequence?.description || "",
    trigger_type: props.sequence?.trigger_type || "manual",
    is_active: props.sequence?.is_active ?? true,
    steps: props.sequence?.steps || [],
});

// Add new step
const addStep = () => {
    form.steps.push({
        id: null,
        position: form.steps.length,
        delay_days: 0,
        delay_hours: 0,
        action_type: "task",
        task_type: "follow_up",
        task_title: "",
        task_description: "",
        task_priority: "medium",
        condition_if_no_response: "continue",
        wait_days_for_response: null,
    });
};

// Remove step
const removeStep = (index) => {
    form.steps.splice(index, 1);
    updatePositions();
};

// Update positions after drag
const updatePositions = () => {
    form.steps.forEach((step, index) => {
        step.position = index;
    });
};

// Submit form
const submit = () => {
    if (isEditing.value) {
        form.put(`/crm/sequences/${props.sequence.id}`, {
            onSuccess: () => {
                router.visit("/crm/sequences");
            },
        });
    } else {
        form.post("/crm/sequences", {
            onSuccess: () => {
                router.visit("/crm/sequences");
            },
        });
    }
};

// Get delay label
const getDelayLabel = (step) => {
    const parts = [];
    if (step.delay_days > 0) {
        parts.push(`${step.delay_days} ${t('crm.sequences.delay.days', 'dni')}`);
    }
    if (step.delay_hours > 0) {
        parts.push(`${step.delay_hours} ${t('crm.sequences.delay.hours', 'godz.')}`);
    }
    return parts.length ? parts.join(" ") : t('crm.sequences.delay.immediate', 'Natychmiast');
};

// Step type icons
const getStepIcon = (actionType) => {
    const icons = {
        task: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
        email: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z",
        sms: "M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z",
        wait_for_response: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
    };
    return icons[actionType] || icons.task;
};
</script>

<template>
    <Head :title="isEditing ? 'Edytuj sekwencję' : 'Nowa sekwencja'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    href="/crm/sequences"
                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ isEditing ? $t('crm.sequences.actions.edit', 'Edytuj sekwencję') : $t('crm.sequences.actions.create', 'Nowa sekwencja') }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <h2
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t('crm.sequences.sections.basic_info', 'Podstawowe informacje') }}
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                        >
                            {{ $t('crm.sequences.fields.name', 'Nazwa sekwencji') }} *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="np. Nowy lead - follow-up"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                        >
                            {{ $t('crm.sequences.fields.trigger', 'Wyzwalacz') }}
                        </label>
                        <select
                            v-model="form.trigger_type"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                            <option
                                v-for="trigger in triggerTypes"
                                :key="trigger.value"
                                :value="trigger.value"
                            >
                                {{ $t('crm.sequences.triggers.' + trigger.value, trigger.label) }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                    >
                        {{ $t('crm.sequences.fields.description', 'Opis (opcjonalnie)') }}
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        :placeholder="$t('crm.sequences.fields.description_placeholder', 'Opisz cel tej sekwencji...')"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <input
                        type="checkbox"
                        id="is_active"
                        v-model="form.is_active"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label
                        for="is_active"
                        class="text-sm text-slate-700 dark:text-slate-300"
                    >
                        {{ $t('crm.sequences.fields.is_active', 'Sekwencja aktywna') }}
                    </label>
                </div>
            </div>

            <!-- Steps Builder -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="mb-4 flex items-center justify-between">
                    <h2
                        class="text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ $t('crm.sequences.sections.steps', 'Kroki sekwencji') }}
                    </h2>
                    <button
                        type="button"
                        @click="addStep"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t('crm.sequences.actions.add_step', 'Dodaj krok') }}
                    </button>
                </div>

                <!-- Steps List -->
                <div v-if="form.steps.length" class="space-y-4">
                    <draggable
                        v-model="form.steps"
                        item-key="position"
                        handle=".drag-handle"
                        @end="updatePositions"
                        class="space-y-4"
                    >
                        <template #item="{ element: step, index }">
                            <div
                                class="relative rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <!-- Step Number & Drag Handle -->
                                <div
                                    class="absolute -left-3 top-4 flex items-center gap-2"
                                >
                                    <div
                                        class="drag-handle cursor-move rounded-lg bg-slate-200 p-1 text-slate-400 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 8h16M4 16h16"
                                            />
                                        </svg>
                                    </div>
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-medium text-white"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                </div>

                                <!-- Remove Button -->
                                <button
                                    type="button"
                                    @click="removeStep(index)"
                                    class="absolute right-2 top-2 rounded-lg p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>

                                <!-- Step Content -->
                                <div class="ml-10 space-y-4">
                                    <!-- Delay -->
                                    <div class="flex items-center gap-4">
                                        <span
                                            class="text-sm text-slate-500 dark:text-slate-400"
                                            >{{ $t('crm.sequences.delay.label', 'Opóźnienie:') }}</span
                                        >
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="step.delay_days"
                                                type="number"
                                                min="0"
                                                max="365"
                                                class="w-20 rounded-lg border-slate-200 text-center focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                            <span class="text-sm text-slate-500"
                                                >{{ $t('crm.sequences.delay.days', 'dni') }}</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="
                                                    step.delay_hours
                                                "
                                                type="number"
                                                min="0"
                                                max="23"
                                                class="w-20 rounded-lg border-slate-200 text-center focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                            <span class="text-sm text-slate-500"
                                                >{{ $t('crm.sequences.delay.hours', 'godz.') }}</span
                                            >
                                        </div>
                                    </div>

                                    <!-- Action Type -->
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2"
                                        >
                                            {{ $t('crm.sequences.step_fields.action_type', 'Typ akcji') }}
                                        </label>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                v-for="actionType in actionTypes"
                                                :key="actionType.value"
                                                type="button"
                                                @click="
                                                    step.action_type =
                                                        actionType.value
                                                "
                                                :class="[
                                                    'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                                                    step.action_type ===
                                                    actionType.value
                                                        ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300',
                                                ]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        :d="
                                                            getStepIcon(
                                                                actionType.value,
                                                            )
                                                        "
                                                    />
                                                </svg>
                                                {{ $t('crm.sequences.steps.' + actionType.value, actionType.label) }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Task Configuration (only for task action) -->
                                    <div
                                        v-if="step.action_type === 'task'"
                                        class="grid gap-4 md:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                            >
                                                {{ $t('crm.sequences.step_fields.task_title', 'Tytuł zadania') }}
                                            </label>
                                            <input
                                                v-model="step.task_title"
                                                type="text"
                                                placeholder="np. Zadzwoń do klienta"
                                                class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                            >
                                                {{ $t('crm.sequences.step_fields.task_type', 'Typ zadania') }}
                                            </label>
                                            <select
                                                v-model="step.task_type"
                                                class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            >
                                                <option
                                                    v-for="taskType in taskTypes"
                                                    :key="taskType.value"
                                                    :value="taskType.value"
                                                >
                                                    {{ $t('crm.sequences.task_types.' + taskType.value, taskType.label) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                            >
                                                {{ $t('crm.sequences.step_fields.priority', 'Priorytet') }}
                                            </label>
                                            <select
                                                v-model="step.task_priority"
                                                class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            >
                                                <option
                                                    v-for="priority in priorities"
                                                    :key="priority.value"
                                                    :value="priority.value"
                                                >
                                                    {{ $t('crm.sequences.priorities.' + priority.value, priority.label) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                            >
                                                {{ $t('crm.sequences.step_fields.if_no_response', 'Jeśli brak odpowiedzi') }}
                                            </label>
                                            <select
                                                v-model="
                                                    step.condition_if_no_response
                                                "
                                                class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            >
                                                <option value="continue">
                                                    {{ $t('crm.sequences.step_options.continue', 'Kontynuuj sekwencję') }}
                                                </option>
                                                <option value="stop">
                                                    {{ $t('crm.sequences.step_options.stop', 'Zatrzymaj sekwencję') }}
                                                </option>
                                                <option value="escalate">
                                                    {{ $t('crm.sequences.step_options.escalate', 'Eskaluj') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Task Description -->
                                    <div v-if="step.action_type === 'task'">
                                        <label
                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                        >
                                            {{ $t('crm.sequences.step_fields.task_description', 'Opis zadania (opcjonalnie)') }}
                                        </label>
                                        <textarea
                                            v-model="step.task_description"
                                            rows="2"
                                            :placeholder="$t('crm.sequences.step_fields.task_description_placeholder', 'Dodatkowe szczegóły...')"
                                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        ></textarea>
                                    </div>
                                </div>

                                <!-- Connection Line -->
                                <div
                                    v-if="index < form.steps.length - 1"
                                    class="absolute -bottom-4 left-10 h-4 w-0.5 bg-slate-300 dark:bg-slate-600"
                                ></div>
                            </div>
                        </template>
                    </draggable>
                </div>

                <!-- Empty Steps State -->
                <div
                    v-else
                    class="rounded-xl border-2 border-dashed border-slate-300 p-8 text-center dark:border-slate-600"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                    </svg>
                    <p class="mt-4 text-slate-500 dark:text-slate-400">
                        {{ $t('crm.sequences.empty_steps', 'Dodaj pierwszy krok do sekwencji') }}
                    </p>
                    <button
                        type="button"
                        @click="addStep"
                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t('crm.sequences.actions.add_step', 'Dodaj krok') }}
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3">
                <Link
                    href="/crm/sequences"
                    class="rounded-xl px-6 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    {{ $t('common.cancel', 'Anuluj') }}
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing || form.steps.length === 0"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                >
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        ></path>
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                    {{ isEditing ? $t('crm.sequences.actions.save_changes', 'Zapisz zmiany') : $t('crm.sequences.actions.create', 'Utwórz sekwencję') }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
