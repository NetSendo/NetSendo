<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted } from "vue";
import axios from "axios";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import ActionMessage from "@/Components/ActionMessage.vue";
import SmsAiAssistant from "@/Components/SmsAiAssistant.vue";
import ResponsiveTabs from "@/Components/ResponsiveTabs.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    sms: Object,
    lists: Array,
    preselectedListId: {
        type: Number,
        default: null,
    },
});

const isEditing = computed(() => !!props.sms);

const form = useForm({
    subject: props.sms?.subject || "",
    content: props.sms?.content || "",
    list_id: props.sms?.list_id || props.preselectedListId || "",
    type: props.sms?.type || "broadcast",
    day: props.sms?.day || 0,
    time: props.sms?.time || "09:00",
    schedule_date: props.sms?.scheduled_at
        ? props.sms.scheduled_at.substring(0, 16)
        : "",
    status: props.sms?.status || "draft",
});

const activeTab = ref("content");

// Tab configuration for ResponsiveTabs component
const smsTabs = computed(() => [
    {
        id: 'content',
        label: t('sms.tabs.content'),
        emoji: '✏️',
    },
    {
        id: 'settings',
        label: t('sms.tabs.settings'),
        emoji: '⚙️',
    },
]);

// List filtering
const listTypeFilter = ref("");
const listSearch = ref("");

const filteredLists = computed(() => {
    let result = props.lists;

    // Filter by type
    if (listTypeFilter.value) {
        result = result.filter((list) => list.type === listTypeFilter.value);
    }

    // Filter by search
    if (listSearch.value) {
        const search = listSearch.value.toLowerCase();
        result = result.filter((list) =>
            list.name.toLowerCase().includes(search)
        );
    }

    return result;
});
const segments = computed(() => {
    // Basic calculation: 160 chars per SMS if pure GSM, 70 if Unicode.
    // For MVP/simplicity, using 160 assumption or just pure length / 160.
    // Ideally we'd detect non-GSM chars.
    const isUnicode = /[^\x00-\x7F]/.test(form.content);
    const limit = isUnicode ? 70 : 160;
    return Math.ceil(form.content.length / limit) || 1;
});

const isUnicode = computed(() => /[^\x00-\x7F]/.test(form.content));

// AI Assistant state
const showSmsAiPanel = ref(false);

const handleAiContent = (content) => {
    form.content = content;
    showSmsAiPanel.value = false;
};

// Preview with data state
const previewSearch = ref("");
const previewSubscriber = ref(null);
const previewSubscribers = ref([]);
const previewContent = ref("");
const isPreviewLoading = ref(false);

// Fetch subscribers for preview
const fetchPreviewSubscribers = async () => {
    if (!form.list_id) {
        previewSubscribers.value = [];
        return;
    }

    try {
        const response = await axios.post(route("sms.preview-subscribers"), {
            contact_list_ids: [form.list_id],
            search: previewSearch.value,
        });
        previewSubscribers.value = response.data.subscribers || [];
    } catch (e) {
        console.error(e);
    }
};

// Fetch preview content
const fetchPreviewContent = async () => {
    if (!previewSubscriber.value) {
        previewContent.value = "";
        return;
    }

    if (!form.content) return;

    isPreviewLoading.value = true;
    try {
        const response = await axios.post(route("sms.preview"), {
            content: form.content,
            subscriber_id: previewSubscriber.value.id,
        });
        previewContent.value = response.data.content;
    } catch (e) {
        console.error(e);
        previewContent.value = "";
    } finally {
        isPreviewLoading.value = false;
    }
};

// Watch for changes
watch(previewSubscriber, () => {
    fetchPreviewContent();
});

watch(
    () => form.list_id,
    () => {
        if (form.list_id) {
            fetchPreviewSubscribers();
        } else {
            previewSubscribers.value = [];
        }
    }
);

// Insert placeholder into content
const insertPlaceholder = (placeholder) => {
    const textarea = document.getElementById("content");
    if (textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const before = form.content.substring(0, start);
        const after = form.content.substring(end);
        form.content = before + placeholder + after;
        // Reset cursor position
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(
                start + placeholder.length,
                start + placeholder.length
            );
        }, 0);
    } else {
        form.content += placeholder;
    }
};

// Placeholders state
const showPlaceholdersDropdown = ref(false);
const placeholders = ref({ standard: [], custom: [] });

onMounted(async () => {
    try {
        const response = await axios.get(route("api.placeholders"));
        placeholders.value = response.data;
    } catch (e) {
        console.error("Failed to load placeholders:", e);
    }

    if (form.list_id) {
        fetchPreviewSubscribers();
    }
});

const submit = (targetStatus = null) => {
    if (targetStatus) {
        form.status = targetStatus;
    }

    if (isEditing.value) {
        form.put(route("sms.update", props.sms.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route("sms.store"), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="isEditing ? $t('sms.edit_title') : $t('sms.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            isEditing
                                ? $t("sms.edit_title")
                                : $t("sms.create_title")
                        }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            isEditing
                                ? $t("sms.edit_subtitle")
                                : $t("sms.create_subtitle")
                        }}
                    </p>
                </div>
                <Link
                    :href="route('sms.index')"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                    &larr; {{ $t("sms.back_to_list") }}
                </Link>
            </div>
        </template>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Main Settings Card -->
                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900"
                >
                    <form @submit.prevent="submit('draft')" class="space-y-6">
                        <!-- Internal Name -->
                        <div>
                            <InputLabel
                                for="subject"
                                :value="$t('sms.fields.subject')"
                            />
                            <TextInput
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="
                                    $t('sms.fields.subject_placeholder')
                                "
                            />
                            <InputError
                                :message="form.errors.subject"
                                class="mt-2"
                            />
                        </div>

                        <!-- Responsive Tabs -->
                        <ResponsiveTabs
                            v-model="activeTab"
                            :tabs="smsTabs"
                        />

                        <!-- Content Tab -->
                        <div
                            v-show="activeTab === 'content'"
                            class="space-y-6 pt-4"
                        >
                            <div>
                                <div class="flex justify-between">
                                    <InputLabel
                                        for="content"
                                        :value="$t('sms.fields.content')"
                                    />
                                    <span
                                        class="text-xs"
                                        :class="
                                            isUnicode
                                                ? 'text-orange-500'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{ form.content.length }} chars /
                                        {{ segments }} SMS
                                        <span v-if="isUnicode">(Unicode)</span>
                                    </span>
                                </div>
                                <textarea
                                    id="content"
                                    v-model="form.content"
                                    rows="5"
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white sm:text-sm"
                                    :placeholder="
                                        $t('sms.fields.content_placeholder')
                                    "
                                ></textarea>
                                <InputError
                                    :message="form.errors.content"
                                    class="mt-2"
                                />

                                <!-- Insert Placeholder & AI buttons -->
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <!-- Insert Placeholder dropdown -->
                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="
                                                showPlaceholdersDropdown =
                                                    !showPlaceholdersDropdown
                                            "
                                            class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
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
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "sms.inserts.insert_variable"
                                                )
                                            }}
                                        </button>
                                        <div
                                            v-if="showPlaceholdersDropdown"
                                            class="absolute left-0 z-10 mt-1 w-56 rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                                        >
                                            <div class="p-2">
                                                <p
                                                    class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        $t(
                                                            "sms.inserts.standard"
                                                        )
                                                    }}
                                                </p>
                                                <button
                                                    v-for="p in placeholders.standard"
                                                    :key="p.name"
                                                    type="button"
                                                    @click="
                                                        insertPlaceholder(
                                                            p.placeholder
                                                        );
                                                        showPlaceholdersDropdown = false;
                                                    "
                                                    class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                                                >
                                                    {{ p.placeholder }} -
                                                    {{ p.label }}
                                                </button>
                                                <template
                                                    v-if="
                                                        placeholders.custom
                                                            ?.length
                                                    "
                                                >
                                                    <hr
                                                        class="my-2 border-slate-200 dark:border-slate-700"
                                                    />
                                                    <p
                                                        class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                                                    >
                                                        {{
                                                            $t(
                                                                "sms.inserts.custom"
                                                            )
                                                        }}
                                                    </p>
                                                    <button
                                                        v-for="p in placeholders.custom"
                                                        :key="p.name"
                                                        type="button"
                                                        @click="
                                                            insertPlaceholder(
                                                                p.placeholder
                                                            );
                                                            showPlaceholdersDropdown = false;
                                                        "
                                                        class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-700"
                                                    >
                                                        {{ p.placeholder }} -
                                                        {{ p.label }}
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- AI Assistant button -->
                                    <button
                                        type="button"
                                        @click="showSmsAiPanel = true"
                                        class="flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-2 text-sm font-medium text-white hover:from-emerald-600 hover:to-teal-700"
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
                                                d="M13 10V3L4 14h7v7l9-11h-7z"
                                            />
                                        </svg>
                                        {{ $t("sms.ai_assistant.generate") }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Tab -->
                        <div
                            v-show="activeTab === 'settings'"
                            class="space-y-6 pt-4"
                        >
                            <!-- Type Selection -->
                            <div>
                                <InputLabel :value="$t('sms.fields.type')" />
                                <div
                                    class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <!-- Broadcast -->
                                    <div
                                        class="cursor-pointer rounded-lg border p-4 hover:border-indigo-500"
                                        :class="
                                            form.type === 'broadcast'
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-slate-200 dark:border-slate-700'
                                        "
                                        @click="form.type = 'broadcast'"
                                    >
                                        <div class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.type"
                                                value="broadcast"
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span
                                                class="ml-2 font-medium text-slate-900 dark:text-white"
                                                >{{
                                                    $t("sms.type_broadcast")
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 ml-6 text-xs text-slate-500"
                                        >
                                            {{
                                                $t(
                                                    "sms.fields.type_broadcast_desc"
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <!-- Autoresponder -->
                                    <div
                                        class="cursor-pointer rounded-lg border p-4 hover:border-indigo-500"
                                        :class="
                                            form.type === 'autoresponder'
                                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                                                : 'border-slate-200 dark:border-slate-700'
                                        "
                                        @click="form.type = 'autoresponder'"
                                    >
                                        <div class="flex items-center">
                                            <input
                                                type="radio"
                                                v-model="form.type"
                                                value="autoresponder"
                                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span
                                                class="ml-2 font-medium text-slate-900 dark:text-white"
                                                >{{
                                                    $t("sms.type_autoresponder")
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 ml-6 text-xs text-slate-500"
                                        >
                                            {{
                                                $t(
                                                    "sms.fields.type_autoresponder_desc"
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- List Selection -->
                            <div>
                                <InputLabel
                                    for="list_id"
                                    :value="$t('sms.fields.audience')"
                                />

                                <!-- Filter Controls -->
                                <div class="mt-2 grid grid-cols-2 gap-2 mb-2">
                                    <!-- List Type Filter -->
                                    <select
                                        v-model="listTypeFilter"
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="">
                                            {{
                                                $t("subscribers.all_list_types")
                                            }}
                                        </option>
                                        <option value="email">
                                            {{
                                                $t(
                                                    "subscribers.list_type_email"
                                                )
                                            }}
                                        </option>
                                        <option value="sms">
                                            {{
                                                $t("subscribers.list_type_sms")
                                            }}
                                        </option>
                                    </select>
                                    <!-- Search -->
                                    <input
                                        v-model="listSearch"
                                        type="text"
                                        :placeholder="
                                            $t('common.search') + '...'
                                        "
                                        class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <select
                                    id="list_id"
                                    v-model="form.list_id"
                                    class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    size="6"
                                >
                                    <option value="" disabled>
                                        {{ $t("sms.fields.select_list") }}
                                    </option>
                                    <option
                                        v-for="list in filteredLists"
                                        :key="list.id"
                                        :value="list.id"
                                    >
                                        {{ list.name }} ({{
                                            list.type === "sms"
                                                ? "SMS"
                                                : "Email"
                                        }})
                                    </option>
                                </select>
                                <p
                                    v-if="filteredLists.length === 0"
                                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    {{ $t("common.no_results") }}
                                </p>
                                <p v-else class="mt-1 text-xs text-slate-500">
                                    {{ form.list_id ? 1 : 0 }}
                                    {{ $t("common.selected_of") }}
                                    {{ filteredLists.length }}
                                    {{ $t("common.lists_lowercase") }}
                                </p>
                                <InputError
                                    :message="form.errors.list_id"
                                    class="mt-2"
                                />
                            </div>

                            <!-- Autoresponder Settings -->
                            <div
                                v-if="form.type === 'autoresponder'"
                                class="grid grid-cols-2 gap-4"
                            >
                                <div>
                                    <InputLabel
                                        for="day"
                                        :value="$t('sms.fields.day')"
                                    />
                                    <TextInput
                                        id="day"
                                        v-model="form.day"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full"
                                    />
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $t("sms.fields.day_help") }}
                                    </p>
                                </div>
                                <div>
                                    <InputLabel
                                        for="time"
                                        :value="$t('sms.fields.time')"
                                    />
                                    <TextInput
                                        id="time"
                                        v-model="form.time"
                                        type="time"
                                        class="mt-1 block w-full"
                                    />
                                </div>
                            </div>

                            <!-- Sending / Scheduling -->
                            <div v-if="form.type === 'broadcast'">
                                <InputLabel
                                    :value="$t('sms.fields.scheduling')"
                                />
                                <div class="mt-2 space-y-4">
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            :value="'send_now'"
                                            :checked="!form.schedule_date"
                                            @change="form.schedule_date = ''"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                            id="schedule_now"
                                        />
                                        <label
                                            for="schedule_now"
                                            class="ml-2 text-sm text-slate-700 dark:text-slate-300"
                                        >
                                            {{ $t("sms.fields.schedule_now") }}
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            :value="'schedule'"
                                            :checked="!!form.schedule_date"
                                            @change="
                                                !form.schedule_date
                                                    ? (form.schedule_date =
                                                          new Date()
                                                              .toISOString()
                                                              .slice(0, 16))
                                                    : null
                                            "
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500"
                                            id="schedule_later"
                                        />
                                        <label
                                            for="schedule_later"
                                            class="ml-2 text-sm text-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                $t("sms.fields.schedule_later")
                                            }}
                                        </label>
                                    </div>
                                    <div v-if="form.schedule_date" class="ml-6">
                                        <InputLabel
                                            for="schedule_date"
                                            :value="$t('sms.fields.datetime')"
                                        />
                                        <TextInput
                                            id="schedule_date"
                                            v-model="form.schedule_date"
                                            type="datetime-local"
                                            class="mt-1 block w-full"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Preview & Actions -->
            <div class="space-y-6">
                <!-- Actions -->
                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900"
                >
                    <h3 class="font-medium text-slate-900 dark:text-white">
                        {{ $t("sms.table.actions") }}
                    </h3>
                    <div class="mt-4 space-y-3">
                        <PrimaryButton
                            @click="submit('sent')"
                            class="w-full justify-center"
                            :disabled="form.processing"
                        >
                            {{
                                form.type === "broadcast" && form.schedule_date
                                    ? $t("sms.actions.schedule")
                                    : $t("sms.actions.send_now")
                            }}
                        </PrimaryButton>
                        <SecondaryButton
                            @click="submit('draft')"
                            class="w-full justify-center"
                            :disabled="form.processing"
                        >
                            {{ $t("sms.actions.save_draft") }}
                        </SecondaryButton>
                    </div>
                    <ActionMessage :on="form.recentlySuccessful" class="mt-3">
                        {{ $t("common.saved") }}
                    </ActionMessage>
                </div>

                <!-- Phone Preview -->
                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900"
                >
                    <h3 class="font-medium text-slate-900 dark:text-white mb-4">
                        Preview
                    </h3>
                    <div
                        class="mx-auto w-[280px] rounded-[3rem] bg-slate-800 p-4 shadow-2xl ring-8 ring-slate-900"
                    >
                        <div
                            class="h-[500px] w-full bg-slate-100 dark:bg-slate-800 rounded-[2rem] overflow-hidden flex flex-col relative"
                        >
                            <!-- Notch -->
                            <div
                                class="absolute top-0 left-1/2 -translate-x-1/2 h-6 w-32 bg-slate-900 rounded-b-xl z-10"
                            ></div>

                            <!-- Header -->
                            <div
                                class="bg-white dark:bg-slate-900 p-4 pt-10 border-b dark:border-slate-700 flex items-center gap-3"
                            >
                                <div
                                    class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs"
                                >
                                    NS
                                </div>
                                <div>
                                    <div
                                        class="text-xs font-bold dark:text-white"
                                    >
                                        NetSendo
                                    </div>
                                </div>
                            </div>

                            <!-- Content -->
                            <div
                                class="flex-1 p-4 flex flex-col gap-4 overflow-y-auto bg-slate-50 dark:bg-slate-800"
                            >
                                <div
                                    class="self-start max-w-[85%] rounded-2xl rounded-tl-none bg-slate-200 dark:bg-slate-700 p-3 text-sm text-slate-800 dark:text-slate-200 shadow-sm"
                                >
                                    <span
                                        v-if="isPreviewLoading"
                                        class="animate-pulse"
                                        >...</span
                                    >
                                    <span v-else>{{
                                        previewSubscriber && previewContent
                                            ? previewContent
                                            : form.content || "..."
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview with Data -->
                    <div
                        class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <h4
                            class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            {{ $t("sms.preview.title") }}
                        </h4>
                        <p
                            class="mb-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("sms.preview.info") }}
                        </p>
                        <input
                            v-model="previewSearch"
                            type="text"
                            :placeholder="$t('sms.preview.search_placeholder')"
                            class="mb-2 w-full rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            @input="fetchPreviewSubscribers"
                        />
                        <select
                            v-model="previewSubscriber"
                            class="w-full rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                            <option :value="null">
                                {{ $t("sms.preview.no_subscriber") }}
                            </option>
                            <option
                                v-for="sub in previewSubscribers"
                                :key="sub.id"
                                :value="sub"
                            >
                                {{ sub.first_name }} {{ sub.last_name }} ({{
                                    sub.phone
                                }})
                            </option>
                        </select>
                        <button
                            v-if="previewSubscriber"
                            type="button"
                            @click="fetchPreviewContent"
                            class="mt-2 w-full rounded bg-slate-600 px-2 py-1 text-xs text-white hover:bg-slate-700"
                        >
                            {{ $t("sms.preview.refresh") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS AI Assistant Panel -->
        <SmsAiAssistant
            v-if="showSmsAiPanel"
            @close="showSmsAiPanel = false"
            @use-content="handleAiContent"
        />
    </AuthenticatedLayout>
</template>
