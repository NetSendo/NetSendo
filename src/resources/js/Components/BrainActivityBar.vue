<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const isRunning = ref(false);
const isActive = ref(false);
const currentTask = ref(null);
const activeAgents = ref(0);
const tokensUsed = ref(0);
const dismissed = ref(false);
let pollInterval = null;

const fetchStatus = async () => {
    try {
        const response = await fetch(route("brain.api.monitor"));
        if (response.ok) {
            const data = await response.json();
            isRunning.value = data.brain?.is_running || false;
            isActive.value = data.brain?.is_active || false;
            currentTask.value = data.current_task;
            activeAgents.value = (data.agents || []).filter(
                (a) => a.tasks_today > 0,
            ).length;
            tokensUsed.value = data.tokens_today?.total || 0;
        }
    } catch {
        // Non-critical — silently ignore
    }
};

const dismiss = () => {
    dismissed.value = true;
    sessionStorage.setItem("brainBarDismissed", "true");
};

onMounted(() => {
    dismissed.value = sessionStorage.getItem("brainBarDismissed") === "true";
    fetchStatus();
    pollInterval = setInterval(fetchStatus, 10000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div
        v-if="(isRunning || isActive) && !dismissed"
        class="relative overflow-hidden px-4 py-2.5 text-white shadow-lg"
        :class="
            isRunning
                ? 'bg-gradient-to-r from-violet-600 via-indigo-600 to-cyan-500'
                : 'bg-gradient-to-r from-slate-600 via-slate-700 to-slate-600'
        "
    >
        <!-- Animated background shimmer (only when actively running) -->
        <div
            v-if="isRunning"
            class="absolute inset-0 -translate-x-full animate-[shimmer_2s_infinite] bg-gradient-to-r from-transparent via-white/10 to-transparent"
        ></div>

        <div
            class="relative mx-auto flex max-w-7xl items-center justify-between"
        >
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <!-- Pulsing brain icon -->
                <div class="relative flex-shrink-0">
                    <span class="text-xl">🧠</span>
                    <span class="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-300 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-400"
                        ></span>
                    </span>
                </div>

                <!-- Task info -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold whitespace-nowrap">
                            {{
                                isRunning
                                    ? t(
                                          "dashboard.brain_activity.bar_title",
                                          "Brain is working...",
                                      )
                                    : t(
                                          "dashboard.brain_activity.bar_recently_active",
                                          "Brain was recently active",
                                      )
                            }}
                        </span>
                        <span
                            v-if="currentTask"
                            class="truncate text-sm text-white/80"
                        >
                            {{ currentTask.description }}
                        </span>
                    </div>
                    <!-- Progress bar -->
                    <div
                        v-if="currentTask?.progress > 0"
                        class="mt-1 h-1 w-full max-w-xs overflow-hidden rounded-full bg-white/20"
                    >
                        <div
                            class="h-full rounded-full bg-white/60 transition-all duration-500"
                            :style="{
                                width: currentTask.progress + '%',
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Stats pills -->
                <div class="hidden items-center gap-2 sm:flex flex-shrink-0">
                    <span
                        v-if="currentTask"
                        class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium backdrop-blur-sm"
                    >
                        {{
                            currentTask.steps_done +
                            "/" +
                            currentTask.steps_total
                        }}
                        {{ t("dashboard.brain_activity.steps", "steps") }}
                    </span>
                    <span
                        v-if="tokensUsed > 0"
                        class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium backdrop-blur-sm"
                    >
                        {{ tokensUsed.toLocaleString() }}
                        {{ t("dashboard.brain_activity.tokens", "tokens") }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                <Link
                    :href="route('brain.monitor')"
                    class="rounded-lg bg-white/15 px-3 py-1 text-xs font-semibold transition-colors hover:bg-white/25 backdrop-blur-sm"
                >
                    {{
                        t(
                            "dashboard.brain_activity.bar_view_monitor",
                            "Monitor",
                        )
                    }}
                </Link>
                <button
                    @click="dismiss"
                    class="text-white/60 transition-colors hover:text-white"
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
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
</style>
