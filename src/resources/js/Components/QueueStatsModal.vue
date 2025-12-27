<script setup>
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { ref, watch, computed } from "vue";
import axios from "axios";

const props = defineProps({
    show: Boolean,
    message: Object,
});

const emit = defineEmits(["close", "sent"]);

const loading = ref(false);
const sending = ref(false);
const stats = ref(null);
const error = ref(null);
const sendResult = ref(null);
const showConfirmSend = ref(false);

// Fetch stats when modal opens
watch(
    () => props.show,
    async (newValue) => {
        if (newValue && props.message) {
            await fetchStats();
        } else {
            stats.value = null;
            error.value = null;
            sendResult.value = null;
            showConfirmSend.value = false;
        }
    }
);

const fetchStats = async () => {
    loading.value = true;
    error.value = null;
    try {
        const response = await axios.get(
            route("messages.queue-schedule-stats", props.message.id)
        );
        if (response.data.success) {
            stats.value = response.data.stats;
        } else {
            error.value = response.data.message;
        }
    } catch (e) {
        error.value =
            e.response?.data?.message ||
            "Wystąpił błąd podczas pobierania statystyk.";
    } finally {
        loading.value = false;
    }
};

const sendToMissed = async () => {
    sending.value = true;
    sendResult.value = null;
    try {
        const response = await axios.post(
            route("messages.send-to-missed", props.message.id)
        );
        sendResult.value = response.data;
        if (response.data.success) {
            // Refresh stats after sending
            await fetchStats();
            emit("sent", response.data);
        }
    } catch (e) {
        sendResult.value = {
            success: false,
            message:
                e.response?.data?.message ||
                "Wystąpił błąd podczas wysyłania do pominiętych.",
        };
    } finally {
        sending.value = false;
        showConfirmSend.value = false;
    }
};

const totalScheduled = computed(() => {
    if (!stats.value) return 0;
    return (
        stats.value.tomorrow +
        stats.value.day_after_tomorrow +
        stats.value.days_3_7 +
        stats.value.over_7_days
    );
});

const totalAll = computed(() => {
    if (!stats.value) return 0;
    return stats.value.sent + totalScheduled.value + stats.value.missed;
});

const getPercentage = (value) => {
    if (!totalAll.value) return 0;
    return Math.round((value / totalAll.value) * 100);
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                    >
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
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            {{ $t("queue_stats.title") }}
                        </h3>
                        <p
                            class="text-sm text-slate-500 dark:text-slate-400 truncate max-w-sm"
                        >
                            {{ message?.subject }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300"
                >
                    <svg
                        class="h-5 w-5"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="py-12 text-center">
                <svg
                    class="mx-auto h-8 w-8 animate-spin text-indigo-600"
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
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $t("common.loading") }}...
                </p>
            </div>

            <!-- Error -->
            <div
                v-else-if="error"
                class="py-8 text-center text-red-600 dark:text-red-400"
            >
                <svg
                    class="mx-auto h-12 w-12 mb-4"
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
                <p>{{ error }}</p>
            </div>

            <!-- Stats Content -->
            <div v-else-if="stats" class="space-y-4">
                <!-- Day info -->
                <div
                    class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3 text-sm text-slate-600 dark:text-slate-300"
                >
                    {{ $t("queue_stats.day_info", { day: message?.day || 0 }) }}
                </div>

                <!-- Stats Grid -->
                <div class="grid gap-3">
                    <!-- Sent -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">✅</span>
                            <span class="text-slate-700 dark:text-slate-200">{{
                                $t("queue_stats.sent")
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="font-semibold text-slate-900 dark:text-white"
                                >{{ stats.sent }}</span
                            >
                            <span class="text-xs text-slate-500"
                                >({{ getPercentage(stats.sent) }}%)</span
                            >
                        </div>
                    </div>

                    <!-- Tomorrow -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📅</span>
                            <span class="text-slate-700 dark:text-slate-200">{{
                                $t("queue_stats.tomorrow")
                            }}</span>
                        </div>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ stats.tomorrow }}</span
                        >
                    </div>

                    <!-- Day after tomorrow -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📅</span>
                            <span class="text-slate-700 dark:text-slate-200">{{
                                $t("queue_stats.day_after_tomorrow")
                            }}</span>
                        </div>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ stats.day_after_tomorrow }}</span
                        >
                    </div>

                    <!-- 3-7 days -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📅</span>
                            <span class="text-slate-700 dark:text-slate-200">{{
                                $t("queue_stats.days_3_7")
                            }}</span>
                        </div>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ stats.days_3_7 }}</span
                        >
                    </div>

                    <!-- Over 7 days -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-slate-200 dark:border-slate-700 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📅</span>
                            <span class="text-slate-700 dark:text-slate-200">{{
                                $t("queue_stats.over_7_days")
                            }}</span>
                        </div>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ stats.over_7_days }}</span
                        >
                    </div>

                    <!-- Missed (highlighted) -->
                    <div
                        v-if="stats.missed > 0"
                        class="flex items-center justify-between rounded-lg border-2 border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20 p-3"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">⚠️</span>
                            <span
                                class="text-amber-800 dark:text-amber-200 font-medium"
                                >{{ $t("queue_stats.missed") }}</span
                            >
                        </div>
                        <span
                            class="font-bold text-amber-700 dark:text-amber-300"
                            >{{ stats.missed }}</span
                        >
                    </div>
                </div>

                <!-- Missed explanation and action -->
                <div
                    v-if="stats.missed > 0"
                    class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-4"
                >
                    <p class="text-sm text-amber-800 dark:text-amber-200 mb-4">
                        {{
                            $t("queue_stats.missed_explanation", {
                                day: message?.day || 0,
                            })
                        }}
                    </p>

                    <!-- Send Result -->
                    <div
                        v-if="sendResult"
                        class="mb-4 p-3 rounded-lg"
                        :class="
                            sendResult.success
                                ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200'
                                : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200'
                        "
                    >
                        {{ sendResult.message }}
                    </div>

                    <!-- Confirm dialog -->
                    <div v-if="showConfirmSend" class="space-y-3">
                        <p
                            class="text-sm font-medium text-amber-900 dark:text-amber-100"
                        >
                            {{
                                $t("queue_stats.send_to_missed_confirm", {
                                    count: stats.missed,
                                })
                            }}
                        </p>
                        <div class="flex gap-2">
                            <DangerButton
                                @click="sendToMissed"
                                :disabled="sending"
                            >
                                <svg
                                    v-if="sending"
                                    class="h-4 w-4 mr-2 animate-spin"
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
                                {{ $t("common.confirm") }}
                            </DangerButton>
                            <SecondaryButton @click="showConfirmSend = false">
                                {{ $t("common.cancel") }}
                            </SecondaryButton>
                        </div>
                    </div>

                    <!-- Initial button -->
                    <PrimaryButton
                        v-else
                        @click="showConfirmSend = true"
                        class="bg-amber-600 hover:bg-amber-700"
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
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                            />
                        </svg>
                        {{ $t("queue_stats.send_to_missed") }}
                    </PrimaryButton>
                </div>

                <!-- No missed -->
                <div
                    v-else
                    class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 p-4 text-center"
                >
                    <p class="text-emerald-700 dark:text-emerald-300">
                        {{ $t("queue_stats.no_missed") }}
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="emit('close')">
                    {{ $t("common.close") }}
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
