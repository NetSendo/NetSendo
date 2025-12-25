<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";

const props = defineProps({
    list: {
        type: Object,
        required: true,
    },
    groups: Array,
    tags: Array,
    otherLists: Array,
    globalCronSettings: Object,
});

const currentTab = ref("general");
const currentSettingsTab = ref("integration");

// CRON helpers
const days = [
    { key: "mon", label: "monday" },
    { key: "tue", label: "tuesday" },
    { key: "wed", label: "wednesday" },
    { key: "thu", label: "thursday" },
    { key: "fri", label: "friday" },
    { key: "sat", label: "saturday" },
    { key: "sun", label: "sunday" },
];

const getDefaultSchedule = () => {
    const schedule = {};
    days.forEach((day) => {
        schedule[day.key] = { enabled: true, start: 0, end: 1440 };
    });
    return schedule;
};

const minutesToTime = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, "0")}:${mins
        .toString()
        .padStart(2, "0")}`;
};

const timeToMinutes = (time) => {
    const [hours, mins] = time.split(":").map(Number);
    return hours * 60 + mins;
};

const form = useForm({
    name: props.list.name,
    description: props.list.description,
    contact_list_group_id: props.list.contact_list_group_id,
    tags: props.list.tags || [],
    is_public: props.list.is_public || false,
    settings: {
        cron: {
            use_custom: props.list.cron_settings?.use_custom_settings ?? false,
            volume_per_minute:
                props.list.cron_settings?.volume_per_minute ?? null,
            schedule:
                props.list.cron_settings?.weekly_schedule ??
                getDefaultSchedule(),
        },
    },
    // Integration fields
    webhook_url: props.list.webhook_url || "",
    webhook_events: props.list.webhook_events || [],
    // Advanced fields
    parent_list_id: props.list.parent_list_id || null,
    sync_settings: {
        sync_on_subscribe: props.list.sync_settings?.sync_on_subscribe ?? true,
        sync_on_unsubscribe:
            props.list.sync_settings?.sync_on_unsubscribe ?? false,
    },
    max_subscribers: props.list.max_subscribers || 0,
    signups_blocked: props.list.signups_blocked || false,
});

const toggleTag = (tagId) => {
    const index = form.tags.indexOf(tagId);
    if (index === -1) {
        form.tags.push(tagId);
    } else {
        form.tags.splice(index, 1);
    }
};

const submit = () => {
    form.put(route("sms-lists.update", props.list.id));
};

// Integration helpers
const apiKeyCopied = ref(false);
const webhookTesting = ref(false);
const apiKeyGenerating = ref(false);

const copyApiKey = () => {
    if (props.list.api_key) {
        navigator.clipboard.writeText(props.list.api_key);
        apiKeyCopied.value = true;
        setTimeout(() => (apiKeyCopied.value = false), 2000);
    }
};

const generateApiKey = () => {
    if (
        confirm(
            "Czy na pewno chcesz wygenerować nowy klucz API? Poprzedni klucz przestanie działać."
        )
    ) {
        apiKeyGenerating.value = true;
        router.post(
            route("sms-lists.generate-api-key", props.list.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => (apiKeyGenerating.value = false),
            }
        );
    }
};

const testWebhook = () => {
    webhookTesting.value = true;
    router.post(
        route("sms-lists.test-webhook", props.list.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (webhookTesting.value = false),
        }
    );
};

const toggleWebhookEvent = (event) => {
    const idx = form.webhook_events.indexOf(event);
    if (idx === -1) {
        form.webhook_events.push(event);
    } else {
        form.webhook_events.splice(idx, 1);
    }
};

const subscribeEndpoint = computed(() => {
    return `${window.location.origin}/api/v1/sms-lists/${props.list.id}/subscribe`;
});
</script>

<template>
    <Head :title="`${$t('sms_lists.edit_title')}: ${list.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('sms-lists.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                </Link>
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("sms_lists.edit_title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("sms_lists.edit_subtitle", { name: list.name }) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-4xl">
                <!-- Tabs Navigation -->
                <div
                    class="mb-6 flex space-x-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/50"
                >
                    <button
                        v-for="tab in ['general', 'settings']"
                        :key="tab"
                        @click="currentTab = tab"
                        class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all"
                        :class="
                            currentTab === tab
                                ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                    >
                        {{
                            tab === "general"
                                ? $t("sms_lists.tabs.general")
                                : $t("sms_lists.tabs.settings")
                        }}
                    </button>
                </div>

                <div
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8"
                >
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- General Tab -->
                        <div
                            v-show="currentTab === 'general'"
                            class="space-y-6"
                        >
                            <div>
                                <label
                                    for="name"
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ $t("sms_lists.fields.name") }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                    :placeholder="
                                        $t('sms_lists.fields.name_placeholder')
                                    "
                                    required
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="mt-2 text-sm text-red-600 dark:text-red-400"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="description"
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ $t("sms_lists.fields.description") }}
                                </label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                    :placeholder="
                                        $t(
                                            'sms_lists.fields.description_placeholder'
                                        )
                                    "
                                ></textarea>
                            </div>

                            <div>
                                <label
                                    for="contact_list_group_id"
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ $t("sms_lists.fields.group") }}
                                </label>
                                <select
                                    id="contact_list_group_id"
                                    v-model="form.contact_list_group_id"
                                    class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                                >
                                    <option value="">
                                        {{ $t("sms_lists.fields.no_group") }}
                                    </option>
                                    <option
                                        v-for="group in groups"
                                        :key="group.id"
                                        :value="group.id"
                                    >
                                        {{ group.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                >
                                    {{ $t("sms_lists.fields.tags") }}
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="tag in tags"
                                        :key="tag.id"
                                        @click="toggleTag(tag.id)"
                                        class="cursor-pointer rounded-full px-3 py-1 text-sm font-medium text-white transition-all hover:scale-105 select-none"
                                        :class="{
                                            'ring-2 ring-indigo-500 ring-offset-2 ring-offset-slate-900':
                                                form.tags.includes(tag.id),
                                        }"
                                        :style="{
                                            backgroundColor: tag.color,
                                            opacity: form.tags.includes(tag.id)
                                                ? 1
                                                : 0.6,
                                        }"
                                    >
                                        {{ tag.name }}
                                    </div>
                                </div>
                                <p
                                    v-if="tags.length === 0"
                                    class="text-xs text-slate-500 mt-1"
                                >
                                    {{ $t("sms_lists.fields.no_tags") }}
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                            >
                                <div
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                                    :class="
                                        form.is_public
                                            ? 'bg-indigo-600'
                                            : 'bg-slate-200 dark:bg-slate-700'
                                    "
                                    @click="form.is_public = !form.is_public"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="{
                                            'translate-x-5': form.is_public,
                                        }"
                                    ></span>
                                </div>
                                <div>
                                    <span
                                        class="block text-sm font-medium text-slate-900 dark:text-white"
                                        >{{
                                            $t("sms_lists.fields.is_public")
                                        }}</span
                                    >
                                    <span
                                        class="block text-xs text-slate-500 dark:text-slate-400"
                                        >{{
                                            $t(
                                                "sms_lists.fields.is_public_description"
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Settings Tab -->
                        <div
                            v-show="currentTab === 'settings'"
                            class="flex flex-col gap-6 lg:flex-row"
                        >
                            <!-- Sidebar -->
                            <div class="w-full lg:w-1/4">
                                <nav class="flex flex-col space-y-1">
                                    <button
                                        v-for="tab in [
                                            'integration',
                                            'cron',
                                            'advanced',
                                        ]"
                                        :key="tab"
                                        @click="currentSettingsTab = tab"
                                        type="button"
                                        class="flex items-center rounded-lg px-4 py-2 text-left text-sm font-medium transition-colors"
                                        :class="
                                            currentSettingsTab === tab
                                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'
                                        "
                                    >
                                        {{
                                            $t(`sms_lists.settings.tabs.${tab}`)
                                        }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 lg:pl-6">
                                <!-- Integration Settings -->
                                <div
                                    v-show="
                                        currentSettingsTab === 'integration'
                                    "
                                    class="space-y-6"
                                >
                                    <h3
                                        class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2"
                                    >
                                        🔗
                                        {{
                                            $t(
                                                "sms_lists.settings.integration.title"
                                            )
                                        }}
                                    </h3>

                                    <!-- API Key Section -->
                                    <div class="space-y-4">
                                        <h4
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "sms_lists.settings.integration.api_key_section"
                                                )
                                            }}
                                        </h4>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                $t(
                                                    "sms_lists.settings.integration.api_key_desc"
                                                )
                                            }}
                                        </p>

                                        <div
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800 space-y-4"
                                        >
                                            <div
                                                class="flex items-center gap-4"
                                            >
                                                <div class="flex-1">
                                                    <label
                                                        class="text-xs text-slate-500 block mb-1"
                                                        >{{
                                                            $t(
                                                                "sms_lists.settings.integration.list_id_label"
                                                            )
                                                        }}</label
                                                    >
                                                    <div
                                                        class="font-mono text-lg font-bold text-indigo-600 dark:text-indigo-400"
                                                    >
                                                        {{ list.id }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <label
                                                    class="text-xs text-slate-500 block mb-1"
                                                    >{{
                                                        $t(
                                                            "sms_lists.settings.integration.api_key_label"
                                                        )
                                                    }}</label
                                                >
                                                <div
                                                    v-if="list.api_key"
                                                    class="flex items-center gap-2"
                                                >
                                                    <code
                                                        class="flex-1 bg-slate-100 dark:bg-slate-900 px-3 py-2 rounded-lg font-mono text-sm text-slate-700 dark:text-slate-300 truncate"
                                                    >
                                                        {{ list.api_key }}
                                                    </code>
                                                    <button
                                                        type="button"
                                                        @click="copyApiKey"
                                                        class="px-3 py-2 text-sm font-medium rounded-lg transition-colors"
                                                        :class="
                                                            apiKeyCopied
                                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                                : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 hover:bg-indigo-200'
                                                        "
                                                    >
                                                        {{
                                                            apiKeyCopied
                                                                ? $t(
                                                                      "sms_lists.settings.integration.copied"
                                                                  )
                                                                : $t(
                                                                      "sms_lists.settings.integration.copy_key"
                                                                  )
                                                        }}
                                                    </button>
                                                </div>
                                                <div
                                                    v-else
                                                    class="text-sm text-slate-500 italic"
                                                >
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.integration.no_api_key"
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                @click="generateApiKey"
                                                :disabled="apiKeyGenerating"
                                                class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                                            >
                                                {{
                                                    apiKeyGenerating
                                                        ? $t(
                                                              "common.processing"
                                                          )
                                                        : $t(
                                                              "sms_lists.settings.integration.generate_key"
                                                          )
                                                }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Webhooks Section -->
                                    <div class="space-y-4">
                                        <h4
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2"
                                        >
                                            <svg
                                                class="w-4 h-4"
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
                                            {{
                                                $t(
                                                    "sms_lists.settings.integration.webhook_section"
                                                )
                                            }}
                                        </h4>

                                        <div
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800 space-y-4"
                                        >
                                            <div>
                                                <label
                                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.integration.webhook_url"
                                                        )
                                                    }}
                                                </label>
                                                <input
                                                    v-model="form.webhook_url"
                                                    type="url"
                                                    class="block w-full rounded-xl border-slate-200 bg-white px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                                    :placeholder="
                                                        $t(
                                                            'sms_lists.settings.integration.webhook_url_placeholder'
                                                        )
                                                    "
                                                />
                                            </div>

                                            <div>
                                                <label
                                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                                >
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.integration.webhook_events"
                                                        )
                                                    }}
                                                </label>
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <div
                                                        v-for="event in [
                                                            'subscribe',
                                                            'unsubscribe',
                                                            'update',
                                                        ]"
                                                        :key="event"
                                                        @click="
                                                            toggleWebhookEvent(
                                                                event
                                                            )
                                                        "
                                                        class="cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-medium transition-all"
                                                        :class="
                                                            form.webhook_events.includes(
                                                                event
                                                            )
                                                                ? 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                                        "
                                                    >
                                                        {{
                                                            $t(
                                                                `sms_lists.settings.integration.event_${event}`
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </div>

                                            <button
                                                v-if="list.webhook_url"
                                                type="button"
                                                @click="testWebhook"
                                                :disabled="webhookTesting"
                                                class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                                            >
                                                {{
                                                    webhookTesting
                                                        ? $t(
                                                              "common.processing"
                                                          )
                                                        : $t(
                                                              "sms_lists.settings.integration.test_webhook"
                                                          )
                                                }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Subscription Endpoint Section -->
                                    <div class="space-y-4">
                                        <h4
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "sms_lists.settings.integration.endpoint_section"
                                                )
                                            }}
                                        </h4>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                $t(
                                                    "sms_lists.settings.integration.endpoint_desc"
                                                )
                                            }}
                                        </p>

                                        <div
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                        >
                                            <code
                                                class="block bg-slate-900 text-green-400 px-4 py-3 rounded-lg font-mono text-sm overflow-x-auto"
                                            >
                                                POST {{ subscribeEndpoint
                                                }}<br />
                                                Content-Type:
                                                application/json<br />
                                                Authorization: Bearer
                                                {{
                                                    list.api_key ||
                                                    "YOUR_API_KEY"
                                                }}<br />
                                                <br />
                                                {"phone": "+48123456789",
                                                "first_name": "Jan"}
                                            </code>
                                        </div>
                                    </div>
                                </div>

                                <!-- CRON Settings -->
                                <div
                                    v-show="currentSettingsTab === 'cron'"
                                    class="space-y-6"
                                >
                                    <h3
                                        class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2"
                                    >
                                        {{
                                            $t(
                                                "sms_lists.settings.cron_section"
                                            )
                                        }}
                                    </h3>

                                    <!-- Toggle: use custom or global -->
                                    <div
                                        class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div>
                                                <span
                                                    class="block text-sm font-medium text-slate-900 dark:text-white"
                                                    >{{
                                                        $t(
                                                            "sms_lists.settings.use_custom_cron"
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.use_custom_cron_desc"
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                                :class="
                                                    form.settings.cron
                                                        .use_custom
                                                        ? 'bg-indigo-600'
                                                        : 'bg-slate-200 dark:bg-slate-700'
                                                "
                                                @click="
                                                    form.settings.cron.use_custom =
                                                        !form.settings.cron
                                                            .use_custom
                                                "
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="{
                                                        'translate-x-5':
                                                            form.settings.cron
                                                                .use_custom,
                                                    }"
                                                ></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Global settings info -->
                                    <div
                                        v-if="!form.settings.cron.use_custom"
                                        class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20"
                                    >
                                        <div class="flex items-start gap-3">
                                            <svg
                                                class="h-5 w-5 text-blue-500 mt-0.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-blue-800 dark:text-blue-200"
                                                >
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.using_global_cron"
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Custom settings -->
                                    <template
                                        v-if="form.settings.cron.use_custom"
                                    >
                                        <!-- Volume per minute -->
                                        <div>
                                            <label
                                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.cron_limit_label"
                                                    )
                                                }}
                                            </label>
                                            <input
                                                v-model.number="
                                                    form.settings.cron
                                                        .volume_per_minute
                                                "
                                                type="number"
                                                min="1"
                                                max="10000"
                                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                :placeholder="
                                                    $t(
                                                        'sms_lists.settings.cron_limit_placeholder'
                                                    )
                                                "
                                            />
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.cron_limit_help"
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <!-- Weekly schedule -->
                                        <div>
                                            <label
                                                class="mb-3 block text-sm font-medium text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.weekly_schedule"
                                                    )
                                                }}
                                            </label>
                                            <div class="space-y-3">
                                                <div
                                                    v-for="day in days"
                                                    :key="day.key"
                                                    class="flex flex-wrap items-center gap-4 p-3 bg-slate-100 dark:bg-slate-700/50 rounded-lg"
                                                >
                                                    <div class="w-28">
                                                        <label
                                                            class="flex items-center gap-2 cursor-pointer"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                v-model="
                                                                    form
                                                                        .settings
                                                                        .cron
                                                                        .schedule[
                                                                        day.key
                                                                    ].enabled
                                                                "
                                                                class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                                            />
                                                            <span
                                                                class="text-sm font-medium text-slate-700 dark:text-slate-300"
                                                            >
                                                                {{
                                                                    $t(
                                                                        "common.days." +
                                                                            day.label
                                                                    )
                                                                }}
                                                            </span>
                                                        </label>
                                                    </div>

                                                    <div
                                                        v-if="
                                                            form.settings.cron
                                                                .schedule[
                                                                day.key
                                                            ].enabled
                                                        "
                                                        class="flex items-center gap-3 flex-1"
                                                    >
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <label
                                                                class="text-xs text-slate-500"
                                                                >{{
                                                                    $t(
                                                                        "sms_lists.settings.cron_from"
                                                                    )
                                                                }}</label
                                                            >
                                                            <input
                                                                type="time"
                                                                :value="
                                                                    minutesToTime(
                                                                        form
                                                                            .settings
                                                                            .cron
                                                                            .schedule[
                                                                            day
                                                                                .key
                                                                        ].start
                                                                    )
                                                                "
                                                                @input="
                                                                    form.settings.cron.schedule[
                                                                        day.key
                                                                    ].start =
                                                                        timeToMinutes(
                                                                            $event
                                                                                .target
                                                                                .value
                                                                        )
                                                                "
                                                                class="px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <label
                                                                class="text-xs text-slate-500"
                                                                >{{
                                                                    $t(
                                                                        "sms_lists.settings.cron_to"
                                                                    )
                                                                }}</label
                                                            >
                                                            <input
                                                                type="time"
                                                                :value="
                                                                    minutesToTime(
                                                                        form
                                                                            .settings
                                                                            .cron
                                                                            .schedule[
                                                                            day
                                                                                .key
                                                                        ].end
                                                                    )
                                                                "
                                                                @input="
                                                                    form.settings.cron.schedule[
                                                                        day.key
                                                                    ].end =
                                                                        timeToMinutes(
                                                                            $event
                                                                                .target
                                                                                .value
                                                                        )
                                                                "
                                                                class="px-2 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                                                            />
                                                        </div>

                                                        <!-- Quick presets -->
                                                        <div class="flex gap-1">
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    form.settings.cron.schedule[
                                                                        day.key
                                                                    ] = {
                                                                        enabled: true,
                                                                        start: 0,
                                                                        end: 1440,
                                                                    }
                                                                "
                                                                class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-600 rounded hover:bg-slate-300 dark:hover:bg-slate-500"
                                                            >
                                                                24h
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    form.settings.cron.schedule[
                                                                        day.key
                                                                    ] = {
                                                                        enabled: true,
                                                                        start: 480,
                                                                        end: 1020,
                                                                    }
                                                                "
                                                                class="px-2 py-1 text-xs bg-slate-200 dark:bg-slate-600 rounded hover:bg-slate-300 dark:hover:bg-slate-500"
                                                            >
                                                                8-17
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div
                                                        v-else
                                                        class="flex-1 text-sm text-slate-400 dark:text-slate-500 italic"
                                                    >
                                                        {{
                                                            $t(
                                                                "sms_lists.settings.cron_disabled"
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Advanced Settings -->
                                <div
                                    v-show="currentSettingsTab === 'advanced'"
                                    class="space-y-6"
                                >
                                    <h3
                                        class="text-lg font-medium text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2"
                                    >
                                        {{
                                            $t(
                                                "sms_lists.settings.advanced_section"
                                            )
                                        }}
                                    </h3>

                                    <!-- Co-registration Section -->
                                    <div class="space-y-4">
                                        <h4
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "sms_lists.settings.advanced.coregistration_section"
                                                )
                                            }}
                                        </h4>

                                        <div
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                        >
                                            <label
                                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.advanced.parent_list"
                                                    )
                                                }}
                                            </label>
                                            <select
                                                v-model="form.parent_list_id"
                                                class="block w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:border-indigo-400"
                                            >
                                                <option :value="null">
                                                    {{
                                                        $t(
                                                            "sms_lists.settings.advanced.no_parent"
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="otherList in otherLists"
                                                    :key="otherList.id"
                                                    :value="otherList.id"
                                                >
                                                    {{ otherList.name }}
                                                </option>
                                            </select>
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.advanced.parent_list_desc"
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="form.parent_list_id"
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800 space-y-3"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div>
                                                    <span
                                                        class="block text-sm font-medium text-slate-900 dark:text-white"
                                                        >{{
                                                            $t(
                                                                "sms_lists.settings.advanced.sync_on_subscribe"
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <div
                                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                                    :class="
                                                        form.sync_settings
                                                            .sync_on_subscribe
                                                            ? 'bg-indigo-600'
                                                            : 'bg-slate-200 dark:bg-slate-700'
                                                    "
                                                    @click="
                                                        form.sync_settings.sync_on_subscribe =
                                                            !form.sync_settings
                                                                .sync_on_subscribe
                                                    "
                                                >
                                                    <span
                                                        class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                        :class="{
                                                            'translate-x-5':
                                                                form
                                                                    .sync_settings
                                                                    .sync_on_subscribe,
                                                        }"
                                                    ></span>
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div>
                                                    <span
                                                        class="block text-sm font-medium text-slate-900 dark:text-white"
                                                        >{{
                                                            $t(
                                                                "sms_lists.settings.advanced.sync_on_unsubscribe"
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <div
                                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                                    :class="
                                                        form.sync_settings
                                                            .sync_on_unsubscribe
                                                            ? 'bg-indigo-600'
                                                            : 'bg-slate-200 dark:bg-slate-700'
                                                    "
                                                    @click="
                                                        form.sync_settings.sync_on_unsubscribe =
                                                            !form.sync_settings
                                                                .sync_on_unsubscribe
                                                    "
                                                >
                                                    <span
                                                        class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                        :class="{
                                                            'translate-x-5':
                                                                form
                                                                    .sync_settings
                                                                    .sync_on_unsubscribe,
                                                        }"
                                                    ></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Limits Section -->
                                    <div class="space-y-4">
                                        <h4
                                            class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "sms_lists.settings.advanced.limits_section"
                                                )
                                            }}
                                        </h4>

                                        <div
                                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                        >
                                            <label
                                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.advanced.max_subscribers"
                                                    )
                                                }}
                                            </label>
                                            <input
                                                v-model.number="
                                                    form.max_subscribers
                                                "
                                                type="number"
                                                min="0"
                                                class="block w-full rounded-xl border-slate-200 bg-white px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                                placeholder="0"
                                            />
                                            <p
                                                class="mt-1 text-xs text-slate-500"
                                            >
                                                {{
                                                    $t(
                                                        "sms_lists.settings.advanced.max_subscribers_help"
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                                        >
                                            <div>
                                                <span
                                                    class="block text-sm font-medium text-slate-900 dark:text-white"
                                                    >{{
                                                        $t(
                                                            "sms_lists.settings.advanced.block_signups"
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400"
                                                    >{{
                                                        $t(
                                                            "sms_lists.settings.advanced.block_signups_desc"
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                                :class="
                                                    form.signups_blocked
                                                        ? 'bg-red-600'
                                                        : 'bg-slate-200 dark:bg-slate-700'
                                                "
                                                @click="
                                                    form.signups_blocked =
                                                        !form.signups_blocked
                                                "
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-5 w-5 translate-x-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                    :class="{
                                                        'translate-x-5':
                                                            form.signups_blocked,
                                                    }"
                                                ></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                        >
                            <Link
                                :href="route('sms-lists.index')"
                                class="rounded-xl px-6 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            >
                                {{ $t("common.cancel") }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-4 w-4 animate-spin text-white"
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
                                <span>{{
                                    form.processing
                                        ? $t("common.saving")
                                        : $t("common.save")
                                }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
