<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";

const { formatDate: formatDateBase } = useDateTime();
const props = defineProps({
    subscriber: Object,
    statistics: Object,
    listHistory: Array,
    allTags: Array,
});

const subscriberInitials = computed(() => {
    if (props.subscriber.first_name && props.subscriber.last_name) {
        return (
            props.subscriber.first_name[0] + props.subscriber.last_name[0]
        ).toUpperCase();
    }
    if (props.subscriber.first_name) {
        return props.subscriber.first_name.substring(0, 2).toUpperCase();
    }
    if (props.subscriber.email) {
        return props.subscriber.email.substring(0, 2).toUpperCase();
    }
    return "??";
});

const subscriberName = computed(() => {
    if (props.subscriber.first_name || props.subscriber.last_name) {
        return `${props.subscriber.first_name || ""} ${props.subscriber.last_name || ""}`.trim();
    }
    return null;
});

// Gender display
const genderLabel = computed(() => {
    const genders = {
        male: t("common.gender.male"),
        female: t("common.gender.female"),
        other: t("common.gender.other"),
    };
    return genders[props.subscriber.gender] || null;
});

// Status badge class
const statusClass = computed(() => {
    return props.subscriber.status === "active"
        ? "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300"
        : "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300";
});

// Engagement score color
const engagementColor = computed(() => {
    const score = props.statistics.engagement_score;
    if (score >= 70) return "text-green-500";
    if (score >= 40) return "text-yellow-500";
    return "text-red-500";
});

// Active lists
const activeLists = computed(() => {
    return props.listHistory.filter((l) => l.status === "active");
});

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return "-";
    return formatDateBase(dateStr, null, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

import { useI18n } from "vue-i18n";

const { t } = useI18n();
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Left Column: Profile Info -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Profile Card -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                <div class="flex flex-col items-center text-center">
                    <!-- Avatar -->
                    <div
                        class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-2xl font-bold text-white shadow-lg"
                    >
                        {{ subscriberInitials }}
                    </div>

                    <!-- Name & Email -->
                    <h3
                        v-if="subscriberName"
                        class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ subscriberName }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ subscriber.email }}
                    </p>
                    <p
                        v-if="subscriber.phone"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ subscriber.phone }}
                    </p>

                    <!-- Status Badge -->
                    <div class="mt-4">
                        <span
                            :class="[
                                'rounded-full px-3 py-1 text-xs font-semibold',
                                statusClass,
                            ]"
                        >
                            {{
                                subscriber.status === "active"
                                    ? $t("subscribers.statuses.active")
                                    : $t("subscribers.statuses.inactive")
                            }}
                        </span>
                    </div>
                </div>

                <!-- Profile Details -->
                <div
                    class="mt-6 space-y-3 border-t border-slate-100 pt-6 dark:border-slate-800"
                >
                    <div
                        v-if="genderLabel"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscribers.fields.gender") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ genderLabel }}
                        </span>
                    </div>
                    <div
                        v-if="subscriber.language"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            🌐 {{ $t("subscribers.fields.language") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ subscriber.language?.toUpperCase() }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscriber_card.profile.created") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ formatDate(subscriber.created_at) }}
                        </span>
                    </div>
                    <div
                        v-if="subscriber.last_opened_at"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscriber_card.profile.last_open") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ formatDate(subscriber.last_opened_at) }}
                        </span>
                    </div>
                    <div
                        v-if="subscriber.last_clicked_at"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscriber_card.profile.last_click") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ formatDate(subscriber.last_clicked_at) }}
                        </span>
                    </div>
                    <div
                        v-if="subscriber.source"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscriber_card.profile.source") }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ subscriber.source }}
                        </span>
                    </div>
                    <div
                        v-if="subscriber.ip_address"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ $t("subscriber_card.profile.ip") }}
                        </span>
                        <span
                            class="font-mono text-xs text-slate-900 dark:text-white"
                        >
                            {{ subscriber.ip_address }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                <h4
                    class="mb-4 text-sm font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t("subscriber_card.tags.title") }}
                </h4>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="tag in subscriber.tags"
                        :key="tag.id"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                    >
                        {{ tag.name }}
                    </span>
                    <span
                        v-if="subscriber.tags.length === 0"
                        class="text-sm text-slate-400 dark:text-slate-500"
                    >
                        {{ $t("subscriber_card.tags.none") }}
                    </span>
                </div>
            </div>

            <!-- Custom Fields -->
            <div
                v-if="subscriber.custom_fields?.length > 0"
                class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900"
            >
                <h4
                    class="mb-4 text-sm font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t("subscriber_card.custom_fields.title") }}
                </h4>
                <div class="space-y-3">
                    <div
                        v-for="field in subscriber.custom_fields"
                        :key="field.id"
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-slate-500 dark:text-slate-400">
                            {{ field.label || field.name }}
                        </span>
                        <span
                            class="font-medium text-slate-900 dark:text-white"
                        >
                            {{ field.value || "-" }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Lists & Engagement -->
        <div class="space-y-6 lg:col-span-2">
            <!-- Engagement Ring -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                <h4
                    class="mb-4 text-sm font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t("subscriber_card.engagement.title") }}
                </h4>
                <div class="flex items-center gap-8">
                    <!-- Engagement Score Circle -->
                    <div
                        class="relative flex h-32 w-32 items-center justify-center"
                    >
                        <svg
                            class="absolute h-32 w-32 -rotate-90"
                            viewBox="0 0 100 100"
                        >
                            <circle
                                cx="50"
                                cy="50"
                                r="40"
                                stroke="currentColor"
                                stroke-width="8"
                                fill="none"
                                class="text-slate-100 dark:text-slate-800"
                            />
                            <circle
                                cx="50"
                                cy="50"
                                r="40"
                                stroke="currentColor"
                                stroke-width="8"
                                fill="none"
                                :stroke-dasharray="`${statistics.engagement_score * 2.51} 251`"
                                stroke-linecap="round"
                                :class="engagementColor"
                            />
                        </svg>
                        <span :class="['text-3xl font-bold', engagementColor]">
                            {{ statistics.engagement_score }}
                        </span>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid flex-1 grid-cols-2 gap-4">
                        <div>
                            <div
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ statistics.total_opens }}
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t("subscriber_card.engagement.total_opens")
                                }}
                            </div>
                        </div>
                        <div>
                            <div
                                class="text-2xl font-bold text-slate-900 dark:text-white"
                            >
                                {{ statistics.total_clicks }}
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t(
                                        "subscriber_card.engagement.total_clicks",
                                    )
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600">
                                {{ statistics.open_rate }}%
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ $t("subscriber_card.engagement.open_rate") }}
                            </div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-600">
                                {{ statistics.click_rate }}%
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    $t("subscriber_card.engagement.click_rate")
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Lists -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-900">
                <h4
                    class="mb-4 text-sm font-semibold text-slate-900 dark:text-white"
                >
                    {{ $t("subscriber_card.lists.active_title") }}
                </h4>
                <div v-if="activeLists.length > 0" class="space-y-3">
                    <div
                        v-for="list in activeLists"
                        :key="list.list_id"
                        class="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-lg text-sm',
                                    list.list_type === 'sms'
                                        ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                                        : 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400',
                                ]"
                            >
                                {{ list.list_type === "sms" ? "📱" : "📧" }}
                            </span>
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-white"
                                >
                                    {{ list.list_name }}
                                </div>
                                <div
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        $t("subscriber_card.lists.subscribed")
                                    }}:
                                    {{ formatDate(list.subscribed_at) }}
                                </div>
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300"
                        >
                            {{ $t("subscribers.statuses.active") }}
                        </span>
                    </div>
                </div>
                <div
                    v-else
                    class="py-8 text-center text-sm text-slate-400 dark:text-slate-500"
                >
                    {{ $t("subscriber_card.lists.none") }}
                </div>
            </div>
        </div>
    </div>
</template>
