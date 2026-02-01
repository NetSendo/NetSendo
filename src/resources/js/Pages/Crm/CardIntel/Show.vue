<script setup>
import { ref, computed } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ContextBadge from "@/Components/CardIntel/ContextBadge.vue";

const props = defineProps({
    scan: Object,
    settings: Object,
    recommendations: Array,
});

const isEditing = ref(false);
const selectedContextLevel = ref(props.scan.context?.context_level || "LOW");
const generatedMessage = ref(null);
const isGenerating = ref(false);
const actionLoading = ref(null);

// Editable fields form
const form = useForm({
    fields: { ...props.scan.extraction?.fields },
});

const contextLevels = [
    { value: "LOW", label: "crm.cardintel.context.low", color: "gray" },
    { value: "MEDIUM", label: "crm.cardintel.context.medium", color: "yellow" },
    { value: "HIGH", label: "crm.cardintel.context.high", color: "green" },
];

const formatConfidence = (value) => {
    return Math.round((value || 0) * 100);
};

const getConfidenceColor = (value) => {
    const percent = (value || 0) * 100;
    if (percent >= 80) return "text-green-600 dark:text-green-400";
    if (percent >= 50) return "text-yellow-600 dark:text-yellow-400";
    return "text-red-600 dark:text-red-400";
};

const saveFields = () => {
    form.put(route("crm.cardintel.extraction.update", props.scan.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

const generateMessage = async (level = null) => {
    isGenerating.value = true;
    try {
        const response = await axios.post(
            route("crm.cardintel.message", props.scan.id),
            {
                context_level: level || selectedContextLevel.value,
                all_versions: props.settings.show_all_context_levels,
            },
        );

        if (props.settings.show_all_context_levels) {
            generatedMessage.value = response.data.messages;
        } else {
            generatedMessage.value = {
                [response.data.message.context_level]: response.data.message,
            };
        }
    } catch (error) {
        console.error("Failed to generate message:", error);
    } finally {
        isGenerating.value = false;
    }
};

const executeAction = async (action, payload = {}) => {
    actionLoading.value = action;
    try {
        const response = await axios.post(
            route("crm.cardintel.action", props.scan.id),
            {
                action,
                ...payload,
            },
        );

        if (response.data.success) {
            router.reload({ only: ["scan"] });
        }
    } catch (error) {
        console.error("Action failed:", error);
    } finally {
        actionLoading.value = null;
    }
};

const hasAction = (actionType) => {
    return props.scan.actions?.some(
        (a) => a.action_type === actionType && a.status === "completed",
    );
};
</script>

<template>
    <Head
        :title="`${$t('crm.cardintel.show.title')} #${scan.id} - CardIntel`"
    />

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
                        {{ scan.extraction?.fields?.first_name }}
                        {{ scan.extraction?.fields?.last_name }}
                        <span
                            v-if="!scan.extraction?.fields?.first_name"
                            class="text-gray-400"
                            >#{{ scan.id }}</span
                        >
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ scan.extraction?.fields?.company }}
                    </p>
                </div>
                <ContextBadge
                    v-if="scan.context"
                    :level="scan.context.context_level"
                    :score="scan.context.quality_score"
                />
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column: Image & Raw Text -->
                    <div class="space-y-6">
                        <!-- Business Card Image -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                        >
                            <div
                                class="aspect-[1.6/1] bg-gray-100 dark:bg-gray-700"
                            >
                                <img
                                    v-if="scan.file_url"
                                    :src="scan.file_url"
                                    alt="Wizytówka"
                                    class="w-full h-full object-contain"
                                />
                            </div>
                        </div>

                        <!-- Raw OCR Text -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $t("crm.cardintel.show.ocr_raw_title") }}
                                </h3>
                            </div>
                            <div class="p-4">
                                <pre
                                    class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap font-mono bg-gray-50 dark:bg-gray-900 rounded-lg p-3"
                                    >{{
                                        scan.raw_text ||
                                        $t("crm.cardintel.show.no_text")
                                    }}</pre
                                >
                            </div>
                        </div>

                        <!-- Context Signals -->
                        <div
                            v-if="scan.context"
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t("crm.cardintel.queue.table.context")
                                    }}: {{ scan.context.level_label }}
                                    <span class="text-gray-400 font-normal"
                                        >({{
                                            scan.context.quality_score
                                        }}
                                        pkt)</span
                                    >
                                </h3>
                            </div>
                            <div class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="signal in scan.context.signals"
                                        :key="signal.key"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full"
                                        :class="
                                            signal.value
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'
                                        "
                                    >
                                        <svg
                                            v-if="signal.value"
                                            class="w-3 h-3"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-3 h-3"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        {{ signal.label }}
                                    </span>
                                </div>

                                <div
                                    v-if="scan.context.reasoning?.length"
                                    class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                                >
                                    <ul
                                        class="space-y-1 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <li
                                            v-for="(reason, i) in scan.context
                                                .reasoning"
                                            :key="i"
                                            class="flex items-start gap-2"
                                        >
                                            <span class="text-gray-400">•</span>
                                            {{ reason }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Fields, Enrichment, Message, Actions -->
                    <div class="space-y-6">
                        <!-- Extracted Fields -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t("crm.cardintel.show.extracted_data")
                                    }}
                                </h3>
                                <button
                                    v-if="!isEditing"
                                    @click="isEditing = true"
                                    class="text-sm text-violet-600 hover:text-violet-700"
                                >
                                    {{ $t("crm.cardintel.actions.edit") }}
                                </button>
                                <div v-else class="flex gap-2">
                                    <button
                                        @click="isEditing = false"
                                        class="text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        {{ $t("crm.cardintel.actions.cancel") }}
                                    </button>
                                    <button
                                        @click="saveFields"
                                        class="text-sm text-violet-600 hover:text-violet-700 font-medium"
                                    >
                                        {{ $t("crm.cardintel.actions.save") }}
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 space-y-3">
                                <template v-if="scan.extraction">
                                    <div
                                        v-for="(data, field) in scan.extraction
                                            .fields_with_confidence"
                                        :key="field"
                                        class="flex items-center gap-3"
                                    >
                                        <label
                                            class="w-24 text-sm text-gray-500 dark:text-gray-400 capitalize"
                                        >
                                            {{
                                                $t(
                                                    "crm.cardintel.fields." +
                                                        field,
                                                )
                                            }}
                                        </label>
                                        <div class="flex-1">
                                            <input
                                                v-if="isEditing"
                                                v-model="form.fields[field]"
                                                type="text"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                            />
                                            <span
                                                v-else
                                                class="text-gray-900 dark:text-white"
                                            >
                                                {{ data.value || "—" }}
                                            </span>
                                        </div>
                                        <span
                                            class="text-xs font-medium"
                                            :class="
                                                getConfidenceColor(
                                                    data.confidence,
                                                )
                                            "
                                        >
                                            {{
                                                formatConfidence(
                                                    data.confidence,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Enrichment Data -->
                        <div
                            v-if="scan.enrichment?.has_data"
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $t("crm.cardintel.show.enrichment") }}
                                </h3>
                            </div>
                            <div class="p-4 space-y-4">
                                <div v-if="scan.enrichment.website_summary">
                                    <h4
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.show.about_company",
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ scan.enrichment.website_summary }}
                                    </p>
                                </div>

                                <div
                                    v-if="scan.enrichment.firmographics"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-if="
                                            scan.enrichment.firmographics
                                                .industry
                                        "
                                        class="px-2 py-1 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 rounded-full"
                                    >
                                        {{
                                            scan.enrichment.firmographics
                                                .industry
                                        }}
                                    </span>
                                    <span
                                        v-if="scan.enrichment.b2b_b2c_guess"
                                        class="px-2 py-1 text-xs bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 rounded-full"
                                    >
                                        {{ scan.enrichment.b2b_b2c_guess }}
                                    </span>
                                    <span
                                        v-if="
                                            scan.enrichment.firmographics
                                                .nip_verified
                                        "
                                        class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full"
                                    >
                                        {{ $t("crm.cardintel.fields.nip") }}
                                        {{
                                            $t(
                                                "crm.cardintel.status.verified",
                                            ) || "Verified"
                                        }}
                                    </span>
                                </div>

                                <div v-if="scan.enrichment.use_case_hypothesis">
                                    <h4
                                        class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"
                                    >
                                        {{
                                            $t(
                                                "crm.cardintel.show.use_case_hypothesis",
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-700 dark:text-gray-300 italic"
                                    >
                                        {{
                                            scan.enrichment.use_case_hypothesis
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Message Generation -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t(
                                            "crm.cardintel.show.proposed_message",
                                        )
                                    }}
                                </h3>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="selectedContextLevel"
                                        class="text-sm border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                                    >
                                        <option
                                            v-for="level in contextLevels"
                                            :key="level.value"
                                            :value="level.value"
                                        >
                                            {{ $t(level.label) }}
                                        </option>
                                    </select>
                                    <button
                                        @click="generateMessage()"
                                        :disabled="isGenerating"
                                        class="px-3 py-1.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-50"
                                    >
                                        {{
                                            isGenerating
                                                ? $t(
                                                      "crm.cardintel.message_generation.generating",
                                                  )
                                                : $t(
                                                      "crm.cardintel.message_generation.generate",
                                                  )
                                        }}
                                    </button>
                                </div>
                            </div>
                            <div class="p-4">
                                <div
                                    v-if="
                                        generatedMessage &&
                                        generatedMessage[selectedContextLevel]
                                    "
                                    class="space-y-3"
                                >
                                    <div>
                                        <label
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                            >{{
                                                $t(
                                                    "crm.cardintel.campaign_stats.table.subject",
                                                ) || "Subject"
                                            }}</label
                                        >
                                        <input
                                            v-model="
                                                generatedMessage[
                                                    selectedContextLevel
                                                ].subject
                                            "
                                            type="text"
                                            class="w-full mt-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                            >Body</label
                                        >
                                        <textarea
                                            v-model="
                                                generatedMessage[
                                                    selectedContextLevel
                                                ].body
                                            "
                                            rows="6"
                                            class="w-full mt-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        ></textarea>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="text-center py-8 text-gray-500 dark:text-gray-400"
                                >
                                    <p>
                                        Kliknij "{{
                                            $t(
                                                "crm.cardintel.message_generation.generate",
                                            )
                                        }}", aby utworzyć propozycję wiadomości
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $t("crm.cardintel.show.actions") }}
                                </h3>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <button
                                        @click="executeAction('save_memory')"
                                        :disabled="
                                            hasAction('save_memory') ||
                                            actionLoading
                                        "
                                        class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border transition-colors"
                                        :class="
                                            hasAction('save_memory')
                                                ? 'border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
                                                : 'border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20'
                                        "
                                    >
                                        <svg
                                            v-if="hasAction('save_memory')"
                                            class="w-5 h-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <span>{{
                                            hasAction("save_memory")
                                                ? $t(
                                                      "crm.cardintel.actions.saved_memory",
                                                  )
                                                : $t(
                                                      "crm.cardintel.actions.save_memory",
                                                  )
                                        }}</span>
                                    </button>

                                    <button
                                        @click="executeAction('add_crm')"
                                        :disabled="
                                            hasAction('add_crm') ||
                                            actionLoading
                                        "
                                        class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border transition-colors"
                                        :class="
                                            hasAction('add_crm')
                                                ? 'border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400'
                                                : 'border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20'
                                        "
                                    >
                                        <svg
                                            v-if="hasAction('add_crm')"
                                            class="w-5 h-5"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <span>{{
                                            hasAction("add_crm")
                                                ? $t(
                                                      "crm.cardintel.record.synced",
                                                  ) || "In CRM"
                                                : $t(
                                                      "crm.cardintel.actions.save_to_crm",
                                                  )
                                        }}</span>
                                    </button>

                                    <button
                                        class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                                    >
                                        <span>{{
                                            $t(
                                                "crm.cardintel.actions.add_to_list",
                                            )
                                        }}</span>
                                    </button>

                                    <button
                                        :disabled="
                                            !generatedMessage || actionLoading
                                        "
                                        class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
                                    >
                                        <span>{{
                                            $t(
                                                "crm.cardintel.actions.send_email",
                                            )
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Action History -->
                        <div
                            v-if="scan.actions?.length"
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700"
                        >
                            <div
                                class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                            >
                                <h3
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        $t("crm.cardintel.show.action_history")
                                    }}
                                </h3>
                            </div>
                            <div
                                class="divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <div
                                    v-for="action in scan.actions"
                                    :key="action.id"
                                    class="px-4 py-3 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-2 h-2 rounded-full"
                                            :class="{
                                                'bg-green-500':
                                                    action.status ===
                                                    'completed',
                                                'bg-yellow-500':
                                                    action.status === 'pending',
                                                'bg-red-500':
                                                    action.status === 'failed',
                                            }"
                                        ></span>
                                        <span
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                            >{{ action.type_label }}</span
                                        >
                                    </div>
                                    <span class="text-xs text-gray-400">{{
                                        action.executed_at ||
                                        $t("crm.cardintel.status.pending")
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
