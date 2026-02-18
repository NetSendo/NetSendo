<script setup>
import { ref, onMounted } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const loading = ref(true);
const brainData = ref(null);
const quickMessage = ref("");

const fetchBrainStatus = async () => {
    try {
        const response = await axios.get("/brain/api/status");
        brainData.value = response.data;
    } catch (e) {
        brainData.value = null;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBrainStatus();
});

const modeConfig = {
    autonomous: {
        icon: "🚀",
        color: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
    },
    semi_auto: {
        icon: "🤝",
        color: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
    },
    manual: {
        icon: "💡",
        color: "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300",
    },
};

const handleQuickChat = () => {
    if (!quickMessage.value.trim()) return;
    router.visit(route("brain.index"), {
        data: { q: quickMessage.value.trim() },
    });
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl bg-white shadow-lg dark:bg-gray-800"
    >
        <!-- Header -->
        <div
            class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600"
                >
                    <svg
                        class="h-4 w-4 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                        />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    NetSendo Brain
                </h3>
            </div>
            <Link
                :href="route('brain.settings')"
                class="text-sm font-medium text-cyan-600 hover:text-cyan-700 dark:text-cyan-400"
            >
                {{ t("dashboard.brain.settings", "Ustawienia") }}
            </Link>
        </div>

        <!-- Content -->
        <div class="p-5">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-8">
                <svg
                    class="h-8 w-8 animate-spin text-cyan-500"
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
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
            </div>

            <!-- Brain Status -->
            <div v-else-if="brainData" class="space-y-4">
                <!-- Work Mode -->
                <div class="flex items-center justify-between">
                    <span
                        class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500"
                    >
                        {{ t("dashboard.brain.mode", "Tryb pracy") }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            modeConfig[brainData.work_mode]?.color ||
                            modeConfig.semi_auto.color
                        "
                    >
                        {{
                            modeConfig[brainData.work_mode]?.icon ||
                            modeConfig.semi_auto.icon
                        }}
                        {{ t(brainData.mode_label) }}
                    </span>
                </div>

                <!-- Stats Row -->
                <div class="grid grid-cols-2 gap-2">
                    <div
                        class="rounded-lg bg-slate-50 px-3 py-2 text-center dark:bg-slate-700/50"
                    >
                        <p
                            class="text-xl font-bold text-cyan-600 dark:text-cyan-400"
                        >
                            {{ brainData.knowledge_count || 0 }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ t("dashboard.brain.knowledge", "Baza wiedzy") }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-slate-50 px-3 py-2 text-center dark:bg-slate-700/50"
                    >
                        <div class="flex items-center justify-center gap-1.5">
                            <span
                                class="inline-flex h-2.5 w-2.5 rounded-full"
                                :class="
                                    brainData.telegram_connected
                                        ? 'bg-green-500'
                                        : 'bg-gray-300 dark:bg-gray-600'
                                "
                            ></span>
                            <p
                                class="text-sm font-semibold"
                                :class="
                                    brainData.telegram_connected
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-gray-400 dark:text-gray-500'
                                "
                            >
                                {{
                                    brainData.telegram_connected
                                        ? t(
                                              "dashboard.brain.connected",
                                              "Połączony",
                                          )
                                        : t(
                                              "dashboard.brain.disconnected",
                                              "Niepołączony",
                                          )
                                }}
                            </p>
                        </div>
                        <p
                            class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"
                        >
                            Telegram
                        </p>
                    </div>
                </div>

                <!-- Quick Chat -->
                <div>
                    <div class="relative">
                        <input
                            v-model="quickMessage"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-4 pr-10 text-sm text-gray-900 placeholder-gray-400 transition-colors focus:border-cyan-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-cyan-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500 dark:focus:border-cyan-500 dark:focus:bg-gray-600"
                            :placeholder="
                                t(
                                    'dashboard.brain.quick_chat_placeholder',
                                    'Zapytaj Brain...',
                                )
                            "
                            @keyup.enter="handleQuickChat"
                        />
                        <button
                            @click="handleQuickChat"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-cyan-50 hover:text-cyan-600 dark:hover:bg-cyan-900/20 dark:hover:text-cyan-400"
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
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Open Chat Link -->
                <Link
                    :href="route('brain.index')"
                    class="flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/25 transition-all hover:shadow-xl hover:shadow-cyan-500/30"
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
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                        />
                    </svg>
                    {{ t("dashboard.brain.open_chat", "Otwórz Chat AI") }}
                </Link>
            </div>

            <!-- Error / Not Available -->
            <div v-else class="text-center py-6">
                <div
                    class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700"
                >
                    <svg
                        class="h-6 w-6 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                        />
                    </svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t("dashboard.brain.unavailable", "Brain AI niedostępny")
                    }}
                </p>
                <Link
                    :href="route('brain.settings')"
                    class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-cyan-600 hover:text-cyan-700 dark:text-cyan-400"
                >
                    {{ t("dashboard.brain.configure", "Skonfiguruj") }} →
                </Link>
            </div>
        </div>
    </div>
</template>
