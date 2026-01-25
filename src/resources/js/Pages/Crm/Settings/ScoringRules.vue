<script setup>
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    rules: Array,
    eventTypes: Object,
    operators: Object,
});

const { t } = useI18n();

// Toast notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 3000);
};

// Modal state
const showModal = ref(false);
const editingRule = ref(null);

const form = useForm({
    event_type: "email_opened",
    name: "",
    description: "",
    points: 5,
    condition_field: "",
    condition_operator: "",
    condition_value: "",
    cooldown_minutes: 60,
    max_daily_occurrences: null,
    is_active: true,
    priority: 10,
});

const openCreateModal = () => {
    editingRule.value = null;
    form.reset();
    form.event_type = "email_opened";
    form.points = 5;
    form.cooldown_minutes = 60;
    form.is_active = true;
    form.priority = 10;
    showModal.value = true;
};

const openEditModal = (rule) => {
    editingRule.value = rule;
    form.event_type = rule.event_type;
    form.name = rule.name;
    form.description = rule.description || "";
    form.points = rule.points;
    form.condition_field = rule.condition_field || "";
    form.condition_operator = rule.condition_operator || "";
    form.condition_value = rule.condition_value || "";
    form.cooldown_minutes = rule.cooldown_minutes || 0;
    form.max_daily_occurrences = rule.max_daily_occurrences;
    form.is_active = rule.is_active;
    form.priority = rule.priority || 0;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingRule.value = null;
    form.reset();
};

const submitForm = () => {
    if (editingRule.value) {
        form.put(`/crm/scoring/rules/${editingRule.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast("Reguła została zaktualizowana");
            },
        });
    } else {
        form.post("/crm/scoring/rules", {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast("Reguła została dodana");
            },
        });
    }
};

const toggleRule = (rule) => {
    router.post(`/crm/scoring/rules/${rule.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast(`Reguła ${rule.is_active ? "wyłączona" : "włączona"}`);
        },
    });
};

const deleteRule = (rule) => {
    if (!confirm("Czy na pewno chcesz usunąć tę regułę?")) return;

    router.delete(`/crm/scoring/rules/${rule.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showToast("Reguła została usunięta");
        },
    });
};

const resetDefaults = () => {
    if (!confirm("Czy na pewno chcesz zresetować wszystkie reguły do domyślnych? Obecne reguły zostaną usunięte.")) return;

    router.post("/crm/scoring/reset-defaults", {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast("Reguły zostały zresetowane do domyślnych");
        },
    });
};

// Group rules by event type for display
const groupedRules = computed(() => {
    const groups = {};
    props.rules.forEach((rule) => {
        if (!groups[rule.event_type]) {
            groups[rule.event_type] = [];
        }
        groups[rule.event_type].push(rule);
    });
    return groups;
});

const getPointsClass = (points) => {
    if (points > 0) return "text-emerald-600 dark:text-emerald-400";
    if (points < 0) return "text-red-600 dark:text-red-400";
    return "text-slate-600 dark:text-slate-400";
};
</script>

<template>
    <Head title="Reguły Scoringu" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/crm"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                            Reguły Scoringu
                        </h1>
                        <p class="text-slate-500 dark:text-slate-400">
                            Konfiguruj automatyczną punktację leadów
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="resetDefaults"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Resetuj do domyślnych
                    </button>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Dodaj regułę
                    </button>
                </div>
            </div>
        </template>

        <!-- Rules List -->
        <div class="space-y-6">
            <div
                v-for="(rules, eventType) in groupedRules"
                :key="eventType"
                class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
            >
                <h2 class="mb-4 flex items-center gap-3 text-lg font-semibold text-slate-900 dark:text-white">
                    <span class="rounded-lg bg-indigo-100 px-3 py-1 text-sm font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                        {{ eventTypes[eventType] || eventType }}
                    </span>
                    <span class="text-sm font-normal text-slate-500">
                        {{ rules.length }} {{ rules.length === 1 ? 'reguła' : 'reguł' }}
                    </span>
                </h2>

                <div class="space-y-3">
                    <div
                        v-for="rule in rules"
                        :key="rule.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 p-4 transition dark:border-slate-700"
                        :class="{ 'opacity-50': !rule.is_active }"
                    >
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <h3 class="font-medium text-slate-900 dark:text-white">
                                    {{ rule.name }}
                                </h3>
                                <span
                                    class="rounded-full px-2 py-0.5 text-sm font-bold"
                                    :class="getPointsClass(rule.points)"
                                >
                                    {{ rule.points > 0 ? '+' : '' }}{{ rule.points }} pkt
                                </span>
                                <span
                                    v-if="rule.condition_value"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                >
                                    Warunkowa
                                </span>
                            </div>
                            <p v-if="rule.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ rule.description }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="rule.cooldown_minutes > 0" class="rounded bg-slate-100 px-2 py-1 dark:bg-slate-700">
                                    Cooldown: {{ rule.cooldown_minutes }} min
                                </span>
                                <span v-if="rule.max_daily_occurrences" class="rounded bg-slate-100 px-2 py-1 dark:bg-slate-700">
                                    Max dziennie: {{ rule.max_daily_occurrences }}
                                </span>
                                <span v-if="rule.condition_value" class="rounded bg-slate-100 px-2 py-1 dark:bg-slate-700">
                                    {{ rule.condition_field }} {{ rule.condition_operator }} "{{ rule.condition_value }}"
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="toggleRule(rule)"
                                class="rounded-lg p-2 transition"
                                :class="rule.is_active
                                    ? 'text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20'
                                    : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'"
                            >
                                <svg v-if="rule.is_active" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                            <button
                                @click="openEditModal(rule)"
                                class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                @click="deleteRule(rule)"
                                class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
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
            <div
                v-if="!rules.length"
                class="rounded-2xl bg-white p-12 text-center shadow-sm dark:bg-slate-800"
            >
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-slate-900 dark:text-white">
                    Brak reguł scoringu
                </h3>
                <p class="mt-2 text-slate-500 dark:text-slate-400">
                    Dodaj pierwszą regułę lub zresetuj do domyślnych ustawień
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <button
                        @click="resetDefaults"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
                    >
                        Załaduj domyślne
                    </button>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
                        Dodaj regułę
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    @click.self="closeModal"
                >
                    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                        <h2 class="mb-6 text-xl font-semibold text-slate-900 dark:text-white">
                            {{ editingRule ? 'Edytuj regułę' : 'Dodaj regułę scoringu' }}
                        </h2>

                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Typ zdarzenia
                                    </label>
                                    <select
                                        v-model="form.event_type"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option v-for="(label, key) in eventTypes" :key="key" :value="key">
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Punkty
                                    </label>
                                    <input
                                        v-model.number="form.points"
                                        type="number"
                                        min="-100"
                                        max="100"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Nazwa reguły
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    placeholder="np. Otwarcie emaila"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Opis (opcjonalny)
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Cooldown (min)
                                    </label>
                                    <input
                                        v-model.number="form.cooldown_minutes"
                                        type="number"
                                        min="0"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Priorytet
                                    </label>
                                    <input
                                        v-model.number="form.priority"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="mt-1 w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Warunek -->
                            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                                <h3 class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Warunek (opcjonalny)
                                </h3>
                                <div class="grid grid-cols-3 gap-3">
                                    <input
                                        v-model="form.condition_field"
                                        type="text"
                                        placeholder="Pole (np. page_url)"
                                        class="rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                    <select
                                        v-model="form.condition_operator"
                                        class="rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option value="">Operator</option>
                                        <option v-for="(label, key) in operators" :key="key" :value="key">
                                            {{ label }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="form.condition_value"
                                        type="text"
                                        placeholder="Wartość"
                                        class="rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    id="is_active"
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                                />
                                <label for="is_active" class="text-sm text-slate-700 dark:text-slate-300">
                                    Reguła aktywna
                                </label>
                            </div>

                            <div class="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300"
                                >
                                    Anuluj
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ editingRule ? 'Zapisz' : 'Dodaj' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast -->
        <Teleport to="body">
            <Transition
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    class="fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg"
                    :class="toast.success ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'"
                >
                    <svg v-if="toast.success" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ toast.message }}
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
