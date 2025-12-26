<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const settings = ref({
    weekly_improvement_target: 2.0,
    recommendation_count: 5,
    auto_prioritize: true,
    focus_areas: [],
    analysis_language: "en",
});

const isSaving = ref(false);
const isLoading = ref(true);
const message = ref(null);

const loadSettings = async () => {
    try {
        const response = await axios.get(
            route("campaign-auditor.advisor.settings")
        );
        if (response.data.settings) {
            settings.value = { ...settings.value, ...response.data.settings };
        }
    } catch (error) {
        console.error("Failed to load advisor settings:", error);
    } finally {
        isLoading.value = false;
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    message.value = null;

    try {
        const response = await axios.put(
            route("campaign-auditor.advisor.settings.update"),
            settings.value
        );
        if (response.data.success) {
            message.value = {
                type: "success",
                text: "Settings saved successfully!",
            };
            setTimeout(() => {
                message.value = null;
            }, 3000);
        }
    } catch (error) {
        message.value = {
            type: "error",
            text: error.response?.data?.message || "Failed to save settings",
        };
    } finally {
        isSaving.value = false;
    }
};

onMounted(() => {
    loadSettings();
});
</script>

<template>
    <section>
        <header>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600"
                >
                    <svg
                        class="h-5 w-5 text-white"
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
                <div>
                    <h2
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t("campaign_advisor.settings_title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $t("campaign_advisor.subtitle") }}
                    </p>
                </div>
            </div>
        </header>

        <div
            v-if="isLoading"
            class="mt-6 flex items-center justify-center py-8"
        >
            <svg
                class="h-8 w-8 animate-spin text-indigo-600"
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

        <div v-else class="mt-6 space-y-6">
            <!-- Success/Error message -->
            <div
                v-if="message"
                :class="[
                    'rounded-lg p-3 text-sm',
                    message.type === 'success'
                        ? 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                ]"
            >
                {{ message.text }}
            </div>

            <!-- Weekly Improvement Target -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ $t("campaign_advisor.weekly_target") }}
                </label>
                <div class="mt-2 flex items-center gap-4">
                    <input
                        type="range"
                        v-model="settings.weekly_improvement_target"
                        min="1"
                        max="10"
                        step="0.5"
                        class="h-2 w-full max-w-xs cursor-pointer appearance-none rounded-lg bg-gray-200 dark:bg-gray-700"
                    />
                    <span
                        class="min-w-[3rem] text-right font-semibold text-indigo-600 dark:text-indigo-400"
                    >
                        {{ settings.weekly_improvement_target }}%
                    </span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Target improvement percentage per week
                </p>
            </div>

            <!-- Max Recommendations -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ $t("campaign_advisor.recommendation_count") }}
                </label>
                <select
                    v-model="settings.recommendation_count"
                    class="mt-2 w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option :value="3">3</option>
                    <option :value="5">5</option>
                    <option :value="7">7</option>
                    <option :value="10">10</option>
                </select>
            </div>

            <!-- Analysis Language -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ $t("campaign_advisor.analysis_language") }}
                </label>
                <select
                    v-model="settings.analysis_language"
                    class="mt-2 w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option value="en">English</option>
                    <option value="pl">Polski</option>
                    <option value="de">Deutsch</option>
                    <option value="es">Español</option>
                    <option value="fr">Français</option>
                    <option value="it">Italiano</option>
                    <option value="pt">Português</option>
                    <option value="nl">Nederlands</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Language for AI-generated analysis and recommendations
                </p>
            </div>

            <!-- Save Button -->
            <div class="flex items-center gap-4">
                <button
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    <svg
                        v-if="isSaving"
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
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    {{ $t("common.save") }}
                </button>
            </div>
        </div>
    </section>
</template>
