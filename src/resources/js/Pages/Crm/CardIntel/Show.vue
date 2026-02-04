<script setup>
import { ref, computed } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ContextBadge from "@/Components/CardIntel/ContextBadge.vue";
import Modal from "@/Components/Modal.vue";

const { t } = useI18n();

const props = defineProps({
    scan: Object,
    settings: Object,
    recommendations: Array,
});

const isEditing = ref(false);
const isEditingBody = ref(false);
const selectedContextLevel = ref(props.scan.context?.context_level || "LOW");
const generatedMessage = ref(null);
const isGenerating = ref(false);
const actionLoading = ref(null);
const emailSentSuccess = ref(false);

// List modal state
const showListModal = ref(false);
const lists = ref([]);
const selectedListId = ref(null);
const listSearchQuery = ref("");
const isLoadingLists = ref(false);
const isAddingToList = ref(false);
const listAddedSuccess = ref(false);
const addedListName = ref("");

// Message personalization options
const formality = ref("formal"); // 'formal' (Pan/Pani) or 'informal' (Ty)
const gender = ref("auto"); // 'auto', 'male', 'female'

// Editable fields form
const form = useForm({
    fields: { ...props.scan.extraction?.fields },
});

const contextLevels = [
    { value: "LOW", label: "crm.cardintel.context.low", color: "gray" },
    { value: "MEDIUM", label: "crm.cardintel.context.medium", color: "yellow" },
    { value: "HIGH", label: "crm.cardintel.context.high", color: "green" },
];

// Function to translate reasoning keys [[key]] to actual translations
const translateReasoning = (text) => {
    if (!text) return text;
    return text.replace(/\[\[([^\]]+)\]\]/g, (match, key) => {
        return t(`crm.cardintel.reasoning.${key}`);
    });
};

// Computed property for translated reasoning list
const translatedReasoning = computed(() => {
    if (!props.scan.context?.reasoning) return [];
    return props.scan.context.reasoning.map((reason) =>
        translateReasoning(reason),
    );
});

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
                formality: formality.value,
                gender: gender.value,
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

// Handle send email action
const handleSendEmail = async () => {
    if (
        !generatedMessage.value ||
        !generatedMessage.value[selectedContextLevel.value]
    ) {
        return;
    }

    const message = generatedMessage.value[selectedContextLevel.value];
    actionLoading.value = "send_email";
    emailSentSuccess.value = false;

    try {
        const response = await axios.post(
            route("crm.cardintel.action", props.scan.id),
            {
                action: "send_email",
                message,
            },
        );

        if (response.data.success) {
            // Clear the generated message after successful send
            generatedMessage.value = null;
            // Show success feedback
            emailSentSuccess.value = true;
            // Hide success message after 5 seconds
            setTimeout(() => {
                emailSentSuccess.value = false;
            }, 5000);
            // Refresh the scan data to show the action in history
            router.reload({ only: ["scan"] });
        }
    } catch (error) {
        console.error("Action failed:", error);
        alert(t("crm.cardintel.actions.send_email_error") || "Błąd wysyłania emaila");
    } finally {
        actionLoading.value = null;
    }
};

// Fetch mailing lists
const fetchLists = async () => {
    isLoadingLists.value = true;
    try {
        const response = await axios.get(route("mailing-lists.index"), {
            headers: { Accept: "application/json" },
        });
        lists.value = response.data.allLists || [];
    } catch (error) {
        console.error("Error fetching lists:", error);
        lists.value = [];
    } finally {
        isLoadingLists.value = false;
    }
};

// Filtered lists based on search
const filteredLists = computed(() => {
    if (!listSearchQuery.value.trim()) return lists.value;
    const query = listSearchQuery.value.toLowerCase();
    return lists.value.filter((list) =>
        list.name.toLowerCase().includes(query),
    );
});

// Open list modal
const openListModal = () => {
    showListModal.value = true;
    fetchLists();
};

// Close list modal
const closeListModal = () => {
    showListModal.value = false;
    selectedListId.value = null;
    listSearchQuery.value = "";
};

// Add to selected list
const addToList = async () => {
    if (!selectedListId.value || isAddingToList.value) return;

    isAddingToList.value = true;
    listAddedSuccess.value = false;

    // Get the selected list name for display
    const selectedList = lists.value.find(l => l.id === selectedListId.value);

    try {
        const response = await axios.post(
            route("crm.cardintel.action", props.scan.id),
            {
                action: "add_email_list",
                list_id: selectedListId.value,
            },
        );

        if (response.data.success) {
            closeListModal();
            // Show success feedback
            addedListName.value = selectedList?.name || "";
            listAddedSuccess.value = true;
            // Hide success message after 5 seconds
            setTimeout(() => {
                listAddedSuccess.value = false;
            }, 5000);
            // Refresh the scan data to show the action in history
            router.reload({ only: ["scan"] });
        }
    } catch (error) {
        console.error("Error adding to list:", error);
    } finally {
        isAddingToList.value = false;
    }
};
</script>

<template>
    <Head
        :title="`${$t('crm.cardintel.show.title')} #${scan.id} - CardIntel`"
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0 w-full md:w-auto">
                    <Link
                        :href="route('crm.cardintel.index')"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors flex-shrink-0"
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
                    <div class="flex-1 min-w-0">
                        <h2
                            class="text-xl font-semibold text-gray-900 dark:text-white truncate"
                        >
                            {{ scan.extraction?.fields?.first_name }}
                            {{ scan.extraction?.fields?.last_name }}
                            <span
                                v-if="!scan.extraction?.fields?.first_name"
                                class="text-gray-400"
                                >#{{ scan.id }}</span
                            >
                        </h2>
                        <p class="text-sm text-gray-500 truncate">
                            {{ scan.extraction?.fields?.company }}
                        </p>
                    </div>
                </div>
                <div class="self-start md:self-auto pl-12 md:pl-0">
                    <ContextBadge
                        v-if="scan.context"
                        :level="scan.context.context_level"
                        :score="scan.context.quality_score"
                    />
                </div>
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
                                    v-if="translatedReasoning.length"
                                    class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                                >
                                    <ul
                                        class="space-y-1 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <li
                                            v-for="(
                                                reason, i
                                            ) in translatedReasoning"
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
                                        class="text-sm border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                    >
                                        <option
                                            v-for="level in contextLevels"
                                            :key="level.value"
                                            :value="level.value"
                                        >
                                            {{ $t(level.label) }}
                                        </option>
                                    </select>
                                    <select
                                        v-model="formality"
                                        class="text-sm border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        :title="
                                            $t(
                                                'crm.cardintel.personalization.formality',
                                            )
                                        "
                                    >
                                        <option value="formal">
                                            {{
                                                $t(
                                                    "crm.cardintel.personalization.formal",
                                                )
                                            }}
                                        </option>
                                        <option value="informal">
                                            {{
                                                $t(
                                                    "crm.cardintel.personalization.informal",
                                                )
                                            }}
                                        </option>
                                    </select>
                                    <select
                                        v-model="gender"
                                        class="text-sm border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        :title="
                                            $t(
                                                'crm.cardintel.personalization.gender',
                                            )
                                        "
                                    >
                                        <option value="auto">
                                            {{
                                                $t(
                                                    "crm.cardintel.personalization.gender_auto",
                                                )
                                            }}
                                        </option>
                                        <option value="male">
                                            {{
                                                $t(
                                                    "crm.cardintel.personalization.gender_male",
                                                )
                                            }}
                                        </option>
                                        <option value="female">
                                            {{
                                                $t(
                                                    "crm.cardintel.personalization.gender_female",
                                                )
                                            }}
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
                                                    "crm.cardintel.show.subject_label",
                                                )
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
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <label
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                                >Body</label
                                            >
                                            <button
                                                @click="
                                                    isEditingBody =
                                                        !isEditingBody
                                                "
                                                class="text-xs text-violet-600 hover:text-violet-700"
                                            >
                                                {{
                                                    isEditingBody
                                                        ? $t(
                                                              "crm.cardintel.actions.preview",
                                                          ) || "Preview"
                                                        : $t(
                                                              "crm.cardintel.actions.edit",
                                                          )
                                                }}
                                            </button>
                                        </div>
                                        <textarea
                                            v-if="isEditingBody"
                                            v-model="
                                                generatedMessage[
                                                    selectedContextLevel
                                                ].body
                                            "
                                            rows="6"
                                            class="w-full mt-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                        ></textarea>
                                        <div
                                            v-else
                                            class="w-full mt-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white min-h-[150px] prose prose-sm dark:prose-invert max-w-none"
                                            v-html="
                                                generatedMessage[
                                                    selectedContextLevel
                                                ].body
                                            "
                                        ></div>
                                    </div>
                                </div>
                                <!-- Success message after email sent -->
                                <div
                                    v-else-if="emailSentSuccess"
                                    class="text-center py-8"
                                >
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <p class="text-green-600 dark:text-green-400 font-medium">
                                            {{ $t("crm.cardintel.actions.email_sent_success") || "Email wysłany pomyślnie!" }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $t("crm.cardintel.actions.generate_new_hint") || "Możesz wygenerować nową wiadomość lub przejść do kolejnej wizytówki." }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="text-center py-8 text-gray-500 dark:text-gray-400"
                                >
                                    <p>
                                        {{
                                            $t(
                                                "crm.cardintel.message_generation.hint",
                                            )
                                        }}
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
                                                : 'border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-200'
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
                                                : 'border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-200'
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
                                        @click="openListModal"
                                        :disabled="actionLoading"
                                        class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-200 transition-colors disabled:opacity-50"
                                    >
                                        <span>{{
                                            $t(
                                                "crm.cardintel.actions.add_to_list",
                                            )
                                        }}</span>
                                    </button>

                                    <button
                                        @click="handleSendEmail"
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

                                <!-- List Added Success Message -->
                                <div
                                    v-if="listAddedSuccess"
                                    class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-green-700 dark:text-green-300 font-medium">
                                                {{ $t("crm.cardintel.actions.add_to_list_success") || "Kontakt dodany do listy!" }}
                                            </p>
                                            <p v-if="addedListName" class="text-sm text-green-600 dark:text-green-400">
                                                {{ addedListName }}
                                            </p>
                                        </div>
                                    </div>
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
                                        <div class="flex flex-col">
                                            <span
                                                class="text-sm text-gray-700 dark:text-gray-300"
                                                >{{ action.type_label }}</span
                                            >
                                            <span
                                                v-if="action.metadata?.list_name"
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                {{ action.metadata.list_name }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400">{{
                                        action.executed_at
                                            ? new Date(action.executed_at).toLocaleString()
                                            : $t("crm.cardintel.status.pending")
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- List Selection Modal -->
    <Modal :show="showListModal" @close="closeListModal" max-width="md">
        <div class="p-6">
            <div class="mb-4">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    {{ $t("crm.cardintel.actions.select_list") }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t("crm.cardintel.actions.select_list_desc") }}
                </p>
            </div>

            <!-- Search Input -->
            <div class="mb-4">
                <input
                    v-model="listSearchQuery"
                    type="text"
                    :placeholder="$t('common.search')"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                />
            </div>

            <!-- Loading State -->
            <div
                v-if="isLoadingLists"
                class="flex items-center justify-center py-8"
            >
                <svg
                    class="h-6 w-6 animate-spin text-violet-500"
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

            <!-- Lists -->
            <div
                v-else
                class="max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <div
                    v-if="filteredLists.length === 0"
                    class="py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    {{
                        listSearchQuery
                            ? $t("common.no_results")
                            : $t("messages.stats.no_lists")
                    }}
                </div>
                <div
                    v-for="list in filteredLists"
                    :key="list.id"
                    @click="selectedListId = list.id"
                    class="flex cursor-pointer items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                    :class="
                        selectedListId === list.id
                            ? 'bg-violet-50 dark:bg-violet-900/20'
                            : ''
                    "
                >
                    <span
                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                        >{{ list.name }}</span
                    >
                    <svg
                        v-if="selectedListId === list.id"
                        class="h-5 w-5 text-violet-600 dark:text-violet-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="closeListModal"
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    {{ $t("common.cancel") }}
                </button>
                <button
                    @click="addToList"
                    :disabled="!selectedListId || isAddingToList"
                    type="button"
                    class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="isAddingToList"
                        class="mr-2 h-4 w-4 animate-spin"
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
                    {{
                        isAddingToList ? $t("common.adding") : $t("common.add")
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>
