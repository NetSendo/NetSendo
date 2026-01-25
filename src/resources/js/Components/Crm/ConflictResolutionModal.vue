<script setup>
import { ref, computed } from "vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    task: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["close", "resolve-local", "resolve-remote"]);

const localData = computed(() => props.task?.conflict_data?.local || {});
const remoteData = computed(() => props.task?.conflict_data?.remote || {});
const detectedAt = computed(() => {
    if (!props.task?.conflict_data?.detected_at) return null;
    return new Date(props.task.conflict_data.detected_at).toLocaleString();
});

const resolving = ref(false);

const resolveWithLocal = async () => {
    resolving.value = true;
    emit("resolve-local", props.task);
};

const resolveWithRemote = async () => {
    resolving.value = true;
    emit("resolve-remote", props.task);
};

const formatDate = (dateString) => {
    if (!dateString) return "—";
    return new Date(dateString).toLocaleDateString("pl-PL", {
        day: "numeric",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-xl">
                    <svg
                        class="h-6 w-6 text-amber-600 dark:text-amber-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ $t("crm.conflicts.title") }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("crm.conflicts.description") }}
                    </p>
                </div>
            </div>

            <!-- Conflict Details -->
            <div
                v-if="detectedAt"
                class="mb-4 text-sm text-slate-500 dark:text-slate-400"
            >
                {{ $t("crm.conflicts.detected_at", { date: detectedAt }) }}
            </div>

            <!-- Comparison Table -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <!-- Local Version -->
                <div
                    class="rounded-xl border-2 border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 p-4"
                >
                    <div class="flex items-center gap-2 mb-3">
                        <svg
                            class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                        <span
                            class="font-medium text-indigo-900 dark:text-indigo-300"
                            >{{ $t("crm.conflicts.local_version") }}</span
                        >
                    </div>
                    <dl class="space-y-2">
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Tytuł
                            </dt>
                            <dd
                                class="text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ localData.title || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Opis
                            </dt>
                            <dd
                                class="text-sm text-slate-700 dark:text-slate-300 line-clamp-3"
                            >
                                {{ localData.description || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Termin
                            </dt>
                            <dd
                                class="text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ formatDate(localData.due_date) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Remote Version -->
                <div
                    class="rounded-xl border-2 border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 p-4"
                >
                    <div class="flex items-center gap-2 mb-3">
                        <svg
                            class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                fill="currentColor"
                                d="M19.3 3H4.7a1.7 1.7 0 00-1.7 1.7v14.6A1.7 1.7 0 004.7 21h14.6a1.7 1.7 0 001.7-1.7V4.7A1.7 1.7 0 0019.3 3zM12 17.6a5.6 5.6 0 110-11.2 5.6 5.6 0 010 11.2z"
                            />
                        </svg>
                        <span
                            class="font-medium text-emerald-900 dark:text-emerald-300"
                            >{{ $t("crm.conflicts.remote_version") }}</span
                        >
                    </div>
                    <dl class="space-y-2">
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Tytuł
                            </dt>
                            <dd
                                class="text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ remoteData.title || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Opis
                            </dt>
                            <dd
                                class="text-sm text-slate-700 dark:text-slate-300 line-clamp-3"
                            >
                                {{ remoteData.description || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Termin
                            </dt>
                            <dd
                                class="text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ formatDate(remoteData.due_date) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div
                class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-700"
            >
                <SecondaryButton @click="emit('close')">
                    {{ $t("crm.conflicts.cancel") }}
                </SecondaryButton>

                <div class="flex gap-3">
                    <PrimaryButton
                        @click="resolveWithLocal"
                        :disabled="resolving"
                        class="bg-indigo-600 hover:bg-indigo-700"
                    >
                        <svg
                            class="h-4 w-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                        {{ $t("crm.conflicts.use_local") }}
                    </PrimaryButton>

                    <PrimaryButton
                        @click="resolveWithRemote"
                        :disabled="resolving"
                        class="bg-emerald-600 hover:bg-emerald-700"
                    >
                        <svg
                            class="h-4 w-4 mr-2"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                fill="currentColor"
                                d="M19.3 3H4.7a1.7 1.7 0 00-1.7 1.7v14.6A1.7 1.7 0 004.7 21h14.6a1.7 1.7 0 001.7-1.7V4.7A1.7 1.7 0 0019.3 3zM12 17.6a5.6 5.6 0 110-11.2 5.6 5.6 0 010 11.2z"
                            />
                        </svg>
                        {{ $t("crm.conflicts.use_remote") }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
