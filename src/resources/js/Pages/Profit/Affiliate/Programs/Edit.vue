<script setup>
import { ref, watch, onMounted } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import LevelRulesEditor from "@/Components/Affiliate/LevelRulesEditor.vue";

const props = defineProps({
    program: Object,
    levelRules: {
        type: Array,
        default: () => [],
    },
});

// Check if program has MLM settings configured
const hasMlmConfig =
    props.program.max_levels > 1 || props.levelRules?.length > 0;

const form = useForm({
    name: props.program.name,
    status: props.program.status,
    terms_text: props.program.terms_text || "",
    cookie_days: props.program.cookie_days,
    currency: props.program.currency,
    default_commission_percent: props.program.default_commission_percent,
    auto_approve_affiliates: props.program.auto_approve_affiliates,
    // Advanced MLM settings
    enable_mlm: hasMlmConfig,
    max_levels: props.program.max_levels || 2,
    attribution_model: props.program.attribution_model || "last_click",
    commission_hold_days: props.program.commission_hold_days || 30,
    level_rules: props.levelRules || [],
});

const showAdvanced = ref(hasMlmConfig);

// When MLM is enabled and no rules exist, initialize default rules
watch(
    () => form.enable_mlm,
    (enabled) => {
        if (enabled && form.level_rules.length === 0) {
            form.level_rules = [
                {
                    level: 1,
                    commission_type: "percent",
                    commission_value: form.default_commission_percent,
                    min_sales_required: 0,
                },
                {
                    level: 2,
                    commission_type: "percent",
                    commission_value: Math.floor(
                        form.default_commission_percent / 2,
                    ),
                    min_sales_required: 0,
                },
            ];
            form.max_levels = 2;
        }
    },
);

// Sync max_levels with actual level count
watch(
    () => form.level_rules,
    (rules) => {
        if (rules.length > 0) {
            form.max_levels = rules.length;
        }
    },
    { deep: true },
);

const submit = () => {
    form.put(route("affiliate.programs.update", props.program.id));
};

const currencies = ["PLN", "EUR", "USD", "GBP", "CHF", "CZK"];
const statuses = ["active", "paused", "closed"];

const attributionModels = [
    {
        value: "last_click",
        label: "affiliate.attribution_last_click",
        desc: "affiliate.attribution_last_click_desc",
    },
    {
        value: "first_click",
        label: "affiliate.attribution_first_click",
        desc: "affiliate.attribution_first_click_desc",
    },
    {
        value: "linear",
        label: "affiliate.attribution_linear",
        desc: "affiliate.attribution_linear_desc",
    },
];
</script>

<template>
    <Head :title="$t('affiliate.edit_program')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('affiliate.programs.index')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg
                        class="w-5 h-5"
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
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    {{ $t("affiliate.edit_program") }}: {{ program.name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Settings Card -->
                    <div
                        class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6"
                    >
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2"
                        >
                            <svg
                                class="w-5 h-5 text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            {{ $t("affiliate.basic_settings") }}
                        </h3>

                        <!-- Program Name -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ $t("affiliate.program_name") }} *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Status -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ $t("common.status") }}
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s"
                                    :value="s"
                                >
                                    {{ $t(`affiliate.status_${s}`) }}
                                </option>
                            </select>
                        </div>

                        <!-- Settings Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ $t("affiliate.cookie_days") }}
                                </label>
                                <input
                                    v-model="form.cookie_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ $t("affiliate.currency") }}
                                </label>
                                <select
                                    v-model="form.currency"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option
                                        v-for="c in currencies"
                                        :key="c"
                                        :value="c"
                                    >
                                        {{ c }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >
                                    {{ $t("affiliate.default_commission") }} (%)
                                </label>
                                <input
                                    v-model="form.default_commission_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Terms -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ $t("affiliate.terms_text") }}
                            </label>
                            <textarea
                                v-model="form.terms_text"
                                rows="4"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <!-- Auto Approve -->
                        <div class="flex items-center gap-3">
                            <input
                                id="auto_approve"
                                v-model="form.auto_approve_affiliates"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label
                                for="auto_approve"
                                class="text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("affiliate.auto_approve_affiliates") }}
                            </label>
                        </div>

                        <!-- Registration Link Info -->
                        <div
                            class="p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                        >
                            <p
                                class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >
                                {{ $t("affiliate.registration_link") }}
                            </p>
                            <code
                                class="text-sm text-indigo-600 dark:text-indigo-400"
                                >/partners/{{ program.slug }}/join</code
                            >
                        </div>
                    </div>

                    <!-- Advanced Settings Toggle -->
                    <button
                        type="button"
                        @click="showAdvanced = !showAdvanced"
                        class="w-full flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-xl border border-indigo-200 dark:border-indigo-800 hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg"
                            >
                                <svg
                                    class="w-5 h-5 text-indigo-600 dark:text-indigo-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>
                            </div>
                            <div class="text-left">
                                <span
                                    class="font-medium text-gray-900 dark:text-white"
                                    >{{
                                        $t("affiliate.advanced_settings")
                                    }}</span
                                >
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("affiliate.mlm_description") }}
                                </p>
                            </div>
                        </div>
                        <svg
                            class="w-5 h-5 text-gray-500 transition-transform duration-200"
                            :class="{ 'rotate-180': showAdvanced }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <!-- Advanced Settings Panel -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="showAdvanced"
                            class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6"
                        >
                            <!-- Enable MLM Toggle -->
                            <div
                                class="flex items-center gap-3 p-4 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-lg border border-amber-200 dark:border-amber-800"
                            >
                                <input
                                    id="enable_mlm"
                                    v-model="form.enable_mlm"
                                    type="checkbox"
                                    class="rounded border-amber-300 dark:border-amber-600 text-amber-600 focus:ring-amber-500"
                                />
                                <div>
                                    <label
                                        for="enable_mlm"
                                        class="font-medium text-gray-900 dark:text-white cursor-pointer"
                                    >
                                        {{ $t("affiliate.enable_mlm") }}
                                    </label>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t("affiliate.enable_mlm_desc") }}
                                    </p>
                                </div>
                            </div>

                            <!-- MLM Level Rules -->
                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="opacity-0 max-h-0"
                                enter-to-class="opacity-100 max-h-[2000px]"
                                leave-active-class="transition duration-150 ease-in"
                                leave-from-class="opacity-100 max-h-[2000px]"
                                leave-to-class="opacity-0 max-h-0"
                            >
                                <div
                                    v-if="form.enable_mlm"
                                    class="space-y-6 overflow-hidden"
                                >
                                    <!-- Level Rules Editor -->
                                    <div>
                                        <h4
                                            class="font-medium text-gray-900 dark:text-white mb-3"
                                        >
                                            {{ $t("affiliate.level_rules") }}
                                        </h4>
                                        <LevelRulesEditor
                                            v-model="form.level_rules"
                                            :currency="form.currency"
                                            :max-levels="10"
                                        />
                                    </div>

                                    <!-- Attribution Model -->
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                        >
                                            {{
                                                $t(
                                                    "affiliate.attribution_model",
                                                )
                                            }}
                                        </label>
                                        <div
                                            class="grid grid-cols-1 md:grid-cols-3 gap-3"
                                        >
                                            <label
                                                v-for="model in attributionModels"
                                                :key="model.value"
                                                class="relative flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
                                                :class="
                                                    form.attribution_model ===
                                                    model.value
                                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                                        : 'border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
                                                "
                                            >
                                                <input
                                                    type="radio"
                                                    v-model="
                                                        form.attribution_model
                                                    "
                                                    :value="model.value"
                                                    class="sr-only"
                                                />
                                                <div>
                                                    <span
                                                        class="font-medium text-gray-900 dark:text-white text-sm"
                                                    >
                                                        {{ $t(model.label) }}
                                                    </span>
                                                    <p
                                                        class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                                                    >
                                                        {{ $t(model.desc) }}
                                                    </p>
                                                </div>
                                                <div
                                                    v-if="
                                                        form.attribution_model ===
                                                        model.value
                                                    "
                                                    class="absolute top-2 right-2"
                                                >
                                                    <svg
                                                        class="w-5 h-5 text-indigo-500"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Commission Hold Period -->
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                        >
                                            {{
                                                $t(
                                                    "affiliate.commission_hold_days",
                                                )
                                            }}
                                        </label>
                                        <div class="flex items-center gap-3">
                                            <input
                                                v-model.number="
                                                    form.commission_hold_days
                                                "
                                                type="number"
                                                min="0"
                                                max="365"
                                                class="w-32 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                            <span
                                                class="text-gray-500 dark:text-gray-400"
                                                >{{
                                                    $t("affiliate.days")
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "affiliate.commission_hold_days_desc",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </Transition>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4">
                        <Link
                            :href="route('affiliate.programs.index')"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                        >
                            {{ $t("common.cancel") }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ $t("common.save") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
