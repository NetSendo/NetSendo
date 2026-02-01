<script setup>
import { ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    settings: Object,
    emailLists: Array,
    smsLists: Array,
    mailboxes: Array,
    modes: Array,
    crmSyncModes: Array,
    tones: Array,
    visionProviders: Object,
});

const form = useForm({
    default_mode: props.settings.default_mode,
    low_threshold: props.settings.low_threshold,
    high_threshold: props.settings.high_threshold,
    crm_sync_mode: props.settings.crm_sync_mode,
    crm_min_score: props.settings.crm_min_score,
    default_email_lists: props.settings.default_email_lists || [],
    default_sms_lists: props.settings.default_sms_lists || [],
    list_add_mode: props.settings.list_add_mode,
    enrichment_enabled: props.settings.enrichment_enabled,
    enrichment_only_medium_high: props.settings.enrichment_only_medium_high,
    enrichment_timeout: props.settings.enrichment_timeout,
    auto_send_enabled: props.settings.auto_send_enabled,
    auto_send_min_score: props.settings.auto_send_min_score,
    auto_send_corporate_only: props.settings.auto_send_corporate_only,
    default_tone: props.settings.default_tone,
    show_all_context_levels: props.settings.show_all_context_levels,
    default_mailbox_id: props.settings.default_mailbox_id,
    custom_ai_prompt: props.settings.custom_ai_prompt || "",
    allowed_html_tags:
        props.settings.allowed_html_tags || "p,br,strong,em,ul,ol,li,a,h3,h4",
});

const { t } = useI18n();

const isSaving = ref(false);
const saveSuccess = ref(false);

const saveSettings = async () => {
    isSaving.value = true;
    try {
        await form.post(route("crm.cardintel.settings.update"), {
            preserveScroll: true,
            onSuccess: () => {
                saveSuccess.value = true;
                setTimeout(() => (saveSuccess.value = false), 3000);
            },
        });
    } finally {
        isSaving.value = false;
    }
};

const getModeDescription = (mode) => {
    switch (mode) {
        case "manual":
            return t("crm.cardintel.settings.mode.manual_desc");
        case "agent":
            return t("crm.cardintel.settings.mode.agent_desc");
        case "auto":
            return t("crm.cardintel.settings.mode.auto_desc");
        default:
            return "";
    }
};

const getCrmSyncDescription = (mode) => {
    switch (mode) {
        case "always":
            return t("crm.cardintel.settings.crm_sync.always_desc");
        case "approve":
            return t("crm.cardintel.settings.crm_sync.approve_desc");
        case "high_only":
            return t("crm.cardintel.settings.crm_sync.high_only_desc");
        case "never":
            return t("crm.cardintel.settings.crm_sync.never_desc");
        default:
            return "";
    }
};
</script>

<template>
    <Head :title="$t('crm.cardintel.settings.page_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('crm.cardintel.index')"
                    class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                    <svg
                        class="w-5 h-5 text-gray-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <div class="flex-1">
                    <h2
                        class="text-xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $t("crm.cardintel.settings.title") }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ $t("crm.cardintel.settings.subtitle") }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="saveSettings" class="space-y-6">
                    <!-- AI Vision Providers Status -->
                    <div
                        class="rounded-xl shadow-sm border"
                        :class="
                            visionProviders?.hasVisionProvider
                                ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                                : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                        "
                    >
                        <div class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <!-- Status Icon -->
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center"
                                    :class="
                                        visionProviders?.hasVisionProvider
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    "
                                >
                                    <svg
                                        v-if="
                                            visionProviders?.hasVisionProvider
                                        "
                                        class="w-6 h-6 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-6 h-6 text-white"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                </div>

                                <div class="flex-1">
                                    <h3
                                        class="font-medium"
                                        :class="
                                            visionProviders?.hasVisionProvider
                                                ? 'text-green-800 dark:text-green-200'
                                                : 'text-red-800 dark:text-red-200'
                                        "
                                    >
                                        {{
                                            visionProviders?.hasVisionProvider
                                                ? $t(
                                                      "crm.cardintel.vision.ready",
                                                  )
                                                : $t(
                                                      "crm.cardintel.vision.required",
                                                  )
                                        }}
                                    </h3>
                                    <p
                                        class="text-sm"
                                        :class="
                                            visionProviders?.hasVisionProvider
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-600 dark:text-red-400'
                                        "
                                    >
                                        {{ visionProviders?.message }}
                                    </p>
                                </div>

                                <Link
                                    v-if="!visionProviders?.hasVisionProvider"
                                    :href="
                                        route('settings.ai-integrations.index')
                                    "
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium"
                                >
                                    {{
                                        $t("crm.cardintel.vision.configure_ai")
                                    }}
                                </Link>
                            </div>
                        </div>

                        <!-- Provider List -->
                        <div
                            class="px-6 pb-4 pt-2 border-t"
                            :class="
                                visionProviders?.hasVisionProvider
                                    ? 'border-green-200 dark:border-green-800'
                                    : 'border-red-200 dark:border-red-800'
                            "
                        >
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 mb-2"
                            >
                                {{
                                    $t("crm.cardintel.vision.supported_models")
                                }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="provider in visionProviders?.providers"
                                    :key="provider.provider"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm"
                                    :class="
                                        provider.integrated && provider.active
                                            ? 'bg-green-100 dark:bg-green-800/40 text-green-700 dark:text-green-300'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                                    "
                                >
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :class="
                                            provider.integrated &&
                                            provider.active
                                                ? 'bg-green-500'
                                                : 'bg-gray-400'
                                        "
                                    ></span>
                                    <span class="font-medium">{{
                                        provider.name
                                    }}</span>
                                    <span class="text-xs opacity-75"
                                        >({{
                                            provider.models.join(", ")
                                        }})</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Default Mode Section -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.default_mode.title",
                                    )
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.default_mode.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-3 gap-4">
                                <label
                                    v-for="mode in ['manual', 'agent', 'auto']"
                                    :key="mode"
                                    class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all"
                                    :class="
                                        form.default_mode === mode
                                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                    "
                                >
                                    <input
                                        type="radio"
                                        v-model="form.default_mode"
                                        :value="mode"
                                        class="sr-only"
                                    />
                                    <span
                                        class="text-lg font-medium capitalize"
                                        :class="
                                            form.default_mode === mode
                                                ? 'text-violet-700 dark:text-violet-400'
                                                : 'text-gray-900 dark:text-white'
                                        "
                                    >
                                        {{
                                            mode === "manual"
                                                ? $t(
                                                      "crm.cardintel.settings.default_mode.manual",
                                                  )
                                                : mode === "agent"
                                                  ? $t(
                                                        "crm.cardintel.settings.default_mode.agent",
                                                    )
                                                  : $t(
                                                        "crm.cardintel.settings.default_mode.auto",
                                                    )
                                        }}
                                    </span>
                                    <span
                                        class="text-sm mt-1"
                                        :class="
                                            form.default_mode === mode
                                                ? 'text-violet-600 dark:text-violet-400'
                                                : 'text-gray-500 dark:text-gray-400'
                                        "
                                    >
                                        {{ getModeDescription(mode) }}
                                    </span>
                                    <div
                                        v-if="form.default_mode === mode"
                                        class="absolute top-2 right-2 w-5 h-5 bg-violet-500 rounded-full flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-3 h-3 text-white"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Context Thresholds -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.context_thresholds.title",
                                    )
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.context_thresholds.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.context_thresholds.medium_threshold",
                                            { points: form.low_threshold },
                                        )
                                    }}
                                </label>
                                <input
                                    type="range"
                                    v-model.number="form.low_threshold"
                                    min="0"
                                    max="100"
                                    class="w-full accent-yellow-500"
                                />
                                <div
                                    class="flex justify-between text-xs text-gray-400 mt-1"
                                >
                                    <span>0</span>
                                    <span>{{
                                        $t(
                                            "crm.cardintel.settings.context_thresholds.low_range",
                                            [
                                                form.low_threshold,
                                                form.low_threshold,
                                            ],
                                        )
                                    }}</span>
                                    <span>100</span>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.context_thresholds.high_threshold",
                                            { points: form.high_threshold },
                                        )
                                    }}
                                </label>
                                <input
                                    type="range"
                                    v-model.number="form.high_threshold"
                                    min="0"
                                    max="100"
                                    class="w-full accent-green-500"
                                />
                                <div
                                    class="flex justify-between text-xs text-gray-400 mt-1"
                                >
                                    <span>0</span>
                                    <span>{{
                                        $t(
                                            "crm.cardintel.settings.context_thresholds.mid_range",
                                            [
                                                form.high_threshold,
                                                form.high_threshold,
                                            ],
                                        )
                                    }}</span>
                                    <span>100</span>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-4 h-4 bg-gray-400 rounded"
                                    ></div>
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >{{
                                            $t(
                                                "crm.cardintel.settings.context_thresholds.legend.low",
                                                [form.low_threshold - 1],
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-4 h-4 bg-yellow-500 rounded"
                                    ></div>
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >{{
                                            $t(
                                                "crm.cardintel.settings.context_thresholds.legend.medium",
                                                [
                                                    form.low_threshold,
                                                    form.high_threshold - 1,
                                                ],
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-4 h-4 bg-green-500 rounded"
                                    ></div>
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                        >{{
                                            $t(
                                                "crm.cardintel.settings.context_thresholds.legend.high",
                                                [form.high_threshold],
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Sync Settings -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t("crm.cardintel.settings.crm_sync.title")
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.crm_sync.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <label
                                    v-for="mode in [
                                        'always',
                                        'approve',
                                        'high_only',
                                        'never',
                                    ]"
                                    :key="mode"
                                    class="relative flex flex-col p-4 rounded-lg border cursor-pointer transition-all"
                                    :class="
                                        form.crm_sync_mode === mode
                                            ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20'
                                            : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                    "
                                >
                                    <input
                                        type="radio"
                                        v-model="form.crm_sync_mode"
                                        :value="mode"
                                        class="sr-only"
                                    />
                                    <span
                                        class="font-medium"
                                        :class="
                                            form.crm_sync_mode === mode
                                                ? 'text-violet-700 dark:text-violet-400'
                                                : 'text-gray-900 dark:text-white'
                                        "
                                    >
                                        {{
                                            mode === "always"
                                                ? $t(
                                                      "crm.cardintel.settings.crm_sync.modes.always",
                                                  )
                                                : mode === "approve"
                                                  ? $t(
                                                        "crm.cardintel.settings.crm_sync.modes.approve",
                                                    )
                                                  : mode === "high_only"
                                                    ? $t(
                                                          "crm.cardintel.settings.crm_sync.modes.high_only",
                                                      )
                                                    : $t(
                                                          "crm.cardintel.settings.crm_sync.modes.never",
                                                      )
                                        }}
                                    </span>
                                    <span
                                        class="text-xs mt-1"
                                        :class="
                                            form.crm_sync_mode === mode
                                                ? 'text-violet-600 dark:text-violet-400'
                                                : 'text-gray-500 dark:text-gray-400'
                                        "
                                    >
                                        {{ getCrmSyncDescription(mode) }}
                                    </span>
                                </label>
                            </div>

                            <div
                                v-if="form.crm_sync_mode !== 'never'"
                                class="pt-4"
                            >
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.crm_sync.min_score",
                                            { score: form.crm_min_score },
                                        )
                                    }}
                                </label>
                                <input
                                    type="range"
                                    v-model.number="form.crm_min_score"
                                    min="0"
                                    max="100"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Enrichment Settings -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.enrichment.title",
                                    )
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.enrichment.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6 space-y-4">
                            <label class="flex items-center justify-between">
                                <span
                                    class="text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t(
                                            "crm.cardintel.settings.enrichment.enable",
                                        )
                                    }}</span
                                >
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        v-model="form.enrichment_enabled"
                                        class="sr-only peer"
                                    />
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"
                                    ></div>
                                </div>
                            </label>

                            <label
                                v-if="form.enrichment_enabled"
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t(
                                            "crm.cardintel.settings.enrichment.only_medium_high",
                                        )
                                    }}</span
                                >
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        v-model="
                                            form.enrichment_only_medium_high
                                        "
                                        class="sr-only peer"
                                    />
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"
                                    ></div>
                                </div>
                            </label>

                            <div v-if="form.enrichment_enabled">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.enrichment.timeout",
                                            {
                                                seconds:
                                                    form.enrichment_timeout,
                                            },
                                        )
                                    }}
                                </label>
                                <input
                                    type="range"
                                    v-model.number="form.enrichment_timeout"
                                    min="5"
                                    max="30"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Send Guardrails -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t("crm.cardintel.settings.auto_send.title")
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.auto_send.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6 space-y-4">
                            <label class="flex items-center justify-between">
                                <span
                                    class="text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t(
                                            "crm.cardintel.settings.auto_send.enable",
                                        )
                                    }}</span
                                >
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        v-model="form.auto_send_enabled"
                                        class="sr-only peer"
                                    />
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"
                                    ></div>
                                </div>
                            </label>

                            <div
                                v-if="form.auto_send_enabled"
                                class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"
                            >
                                <div class="flex items-start gap-3">
                                    <svg
                                        class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <div>
                                        <p
                                            class="text-sm text-yellow-800 dark:text-yellow-200 font-medium"
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.settings.auto_send.guardrails_active",
                                                )
                                            }}
                                        </p>
                                        <ul
                                            class="text-xs text-yellow-700 dark:text-yellow-300 mt-1 space-y-1"
                                        >
                                            <li>
                                                {{
                                                    $t(
                                                        "crm.cardintel.settings.auto_send.rules.high_score",
                                                        {
                                                            score: form.auto_send_min_score,
                                                        },
                                                    )
                                                }}
                                            </li>
                                            <li
                                                v-if="
                                                    form.auto_send_corporate_only
                                                "
                                            >
                                                {{
                                                    $t(
                                                        "crm.cardintel.settings.auto_send.rules.corporate_only",
                                                    )
                                                }}
                                            </li>
                                            <li>
                                                {{
                                                    $t(
                                                        "crm.cardintel.settings.auto_send.rules.max_daily",
                                                    )
                                                }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.auto_send_enabled">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.auto_send.min_score",
                                            { score: form.auto_send_min_score },
                                        )
                                    }}
                                </label>
                                <input
                                    type="range"
                                    v-model.number="form.auto_send_min_score"
                                    min="50"
                                    max="100"
                                    class="w-full"
                                />
                            </div>

                            <label
                                v-if="form.auto_send_enabled"
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-gray-700 dark:text-gray-300"
                                    >{{
                                        $t(
                                            "crm.cardintel.settings.auto_send.corporate_only_label",
                                        )
                                    }}</span
                                >
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        v-model="form.auto_send_corporate_only"
                                        class="sr-only peer"
                                    />
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"
                                    ></div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Sending Settings -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{ $t("crm.cardintel.settings.sending.title") }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                            >
                                {{
                                    $t(
                                        "crm.cardintel.settings.sending.description",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.sending.default_mailbox",
                                        )
                                    }}
                                </label>
                                <select
                                    v-model="form.default_mailbox_id"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option :value="null">
                                        {{
                                            $t(
                                                "crm.cardintel.settings.sending.no_mailbox",
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="mailbox in mailboxes"
                                        :key="mailbox.id"
                                        :value="mailbox.id"
                                    >
                                        {{ mailbox.name }} ({{
                                            mailbox.from_email
                                        }})
                                    </option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{
                                        $t(
                                            "crm.cardintel.settings.sending.mailbox_help",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Settings -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    $t("crm.cardintel.settings.messages.title")
                                }}
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.messages.default_tone",
                                        )
                                    }}
                                </label>
                                <select
                                    v-model="form.default_tone"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                >
                                    <option value="professional">
                                        {{
                                            $t(
                                                "crm.cardintel.settings.messages.tones.professional",
                                            )
                                        }}
                                    </option>
                                    <option value="friendly">
                                        {{
                                            $t(
                                                "crm.cardintel.settings.messages.tones.friendly",
                                            )
                                        }}
                                    </option>
                                    <option value="formal">
                                        {{
                                            $t(
                                                "crm.cardintel.settings.messages.tones.formal",
                                            )
                                        }}
                                    </option>
                                </select>
                            </div>

                            <label class="flex items-center justify-between">
                                <div>
                                    <span
                                        class="text-gray-700 dark:text-gray-300"
                                        >{{
                                            $t(
                                                "crm.cardintel.settings.messages.show_all_context_levels",
                                            )
                                        }}</span
                                    >
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{
                                            $t(
                                                "crm.cardintel.settings.messages.show_all_context_levels_desc",
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="relative">
                                    <input
                                        type="checkbox"
                                        v-model="form.show_all_context_levels"
                                        class="sr-only peer"
                                    />
                                    <div
                                        class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"
                                    ></div>
                                </div>
                            </label>

                            <!-- Custom AI Prompt -->
                            <div
                                class="pt-4 border-t border-gray-200 dark:border-gray-700"
                            >
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.messages.custom_prompt",
                                        )
                                    }}
                                </label>
                                <textarea
                                    v-model="form.custom_ai_prompt"
                                    rows="4"
                                    maxlength="2000"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white resize-none"
                                    :placeholder="
                                        $t(
                                            'crm.cardintel.settings.messages.custom_prompt_placeholder',
                                        )
                                    "
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{
                                        $t(
                                            "crm.cardintel.settings.messages.custom_prompt_help",
                                        )
                                    }}
                                </p>
                            </div>

                            <!-- Allowed HTML Tags -->
                            <div
                                class="pt-4 border-t border-gray-200 dark:border-gray-700"
                            >
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.settings.messages.allowed_html_tags",
                                        )
                                    }}
                                </label>
                                <input
                                    v-model="form.allowed_html_tags"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    placeholder="p,br,strong,em,ul,ol,li,a,h3,h4"
                                />
                                <p class="text-xs text-gray-500 mt-1">
                                    {{
                                        $t(
                                            "crm.cardintel.settings.messages.allowed_html_tags_help",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="flex items-center justify-end gap-4">
                        <span
                            v-if="saveSuccess"
                            class="text-sm text-green-600 dark:text-green-400 flex items-center gap-1"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            {{ $t("crm.cardintel.settings.saved") }}
                        </span>
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="px-6 py-3 bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-50 font-medium transition-colors"
                        >
                            {{
                                isSaving
                                    ? $t("crm.cardintel.settings.saving")
                                    : $t("crm.cardintel.settings.save_button")
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
