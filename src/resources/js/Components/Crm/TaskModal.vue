<script setup>
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "@/Components/Modal.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    show: Boolean,
    task: {
        type: Object,
        default: null,
    },
    contactId: {
        type: Number,
        default: null,
    },
    dealId: {
        type: Number,
        default: null,
    },
    contacts: {
        type: Array,
        default: () => [],
    },
    owners: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["close", "saved"]);

const isEditing = computed(() => !!props.task?.id);

const form = useForm({
    title: "",
    description: "",
    type: "task",
    priority: "medium",
    due_date: "",
    crm_contact_id: null,
    crm_deal_id: null,
    owner_id: null,
});

// Reset form when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.task) {
            form.title = props.task.title || "";
            form.description = props.task.description || "";
            form.type = props.task.type || "task";
            form.priority = props.task.priority || "medium";
            form.due_date = props.task.due_date?.split("T")[0] || "";
            form.crm_contact_id = props.task.crm_contact_id || null;
            form.crm_deal_id = props.task.crm_deal_id || null;
            form.owner_id = props.task.owner_id || null;
        } else {
            form.reset();
            form.crm_contact_id = props.contactId;
            form.crm_deal_id = props.dealId;
            // Set default due date to today
            form.due_date = new Date().toISOString().split("T")[0];
        }
    }
});

const submit = () => {
    if (isEditing.value) {
        form.put(`/crm/tasks/${props.task.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit("saved");
                emit("close");
            },
        });
    } else {
        form.post("/crm/tasks", {
            preserveScroll: true,
            onSuccess: () => {
                emit("saved");
                emit("close");
            },
        });
    }
};

const taskTypes = [
    { value: "call", label: "Telefon", icon: "M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" },
    { value: "email", label: "Email", icon: "M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" },
    { value: "meeting", label: "Spotkanie", icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" },
    { value: "task", label: "Zadanie", icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" },
    { value: "follow_up", label: "Follow-up", icon: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" },
];

const priorities = [
    { value: "low", label: "Niski", color: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300" },
    { value: "medium", label: "Średni", color: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300" },
    { value: "high", label: "Wysoki", color: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300" },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                    {{ isEditing ? 'Edytuj zadanie' : 'Nowe zadanie' }}
                </h2>
                <button @click="emit('close')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Tytuł zadania *
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        placeholder="np. Zadzwoń do klienta w sprawie oferty"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <!-- Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Typ zadania
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="type in taskTypes"
                            :key="type.value"
                            type="button"
                            @click="form.type = type.value"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition',
                                form.type === type.value
                                    ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300'
                            ]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="type.icon" />
                            </svg>
                            {{ type.label }}
                        </button>
                    </div>
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Priorytet
                    </label>
                    <div class="flex gap-2">
                        <button
                            v-for="priority in priorities"
                            :key="priority.value"
                            type="button"
                            @click="form.priority = priority.value"
                            :class="[
                                'rounded-xl px-4 py-2 text-sm font-medium transition',
                                form.priority === priority.value
                                    ? priority.color + ' ring-2 ring-offset-2 ring-slate-400'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300'
                            ]"
                        >
                            {{ priority.label }}
                        </button>
                    </div>
                </div>

                <!-- Due Date -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Termin wykonania
                    </label>
                    <input
                        v-model="form.due_date"
                        type="date"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                    <InputError :message="form.errors.due_date" class="mt-1" />
                </div>

                <!-- Contact Selection (only if contacts provided and no fixed contactId) -->
                <div v-if="contacts.length && !contactId">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Przypisz do kontaktu
                    </label>
                    <select
                        v-model="form.crm_contact_id"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option :value="null">-- Bez kontaktu --</option>
                        <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                            {{ contact.name }}
                        </option>
                    </select>
                </div>

                <!-- Owner Selection -->
                <div v-if="owners.length">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Przypisz do handlowca
                    </label>
                    <select
                        v-model="form.owner_id"
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option :value="null">-- Automatycznie (ja) --</option>
                        <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                            {{ owner.name }}
                        </option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                        Opis (opcjonalnie)
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Dodatkowe szczegóły dotyczące zadania..."
                        class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        Anuluj
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ isEditing ? 'Zapisz zmiany' : 'Utwórz zadanie' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
