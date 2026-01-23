<script setup>
/**
 * TrackedLinksSection Component
 *
 * Displays a section for managing tracked links detected in message content.
 * Allows configuring per-link tracking, data sharing, and list actions.
 */
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    content: {
        type: String,
        default: "",
    },
    lists: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:modelValue"]);

// Available fields for sharing (matching External Pages)
const shareableFields = [
    { value: "fname", label: "messages.tracked_links.field_fname" },
    { value: "lname", label: "messages.tracked_links.field_lname" },
    { value: "email", label: "messages.tracked_links.field_email" },
    { value: "phone", label: "messages.tracked_links.field_phone" },
    { value: "sex", label: "messages.tracked_links.field_gender" },
];

// Collapse state for each link
const expandedLinks = ref({});

// Extract links from HTML content
const extractLinksFromContent = (html) => {
    if (!html) return [];

    // Helper to decode HTML entities (e.g., &amp; → &)
    const decodeHtmlEntities = (str) => {
        const textarea = document.createElement("textarea");
        textarea.innerHTML = str;
        return textarea.value;
    };

    const linkRegex = /href=["']([^"']+)["']/g;
    const links = [];
    let match;

    while ((match = linkRegex.exec(html)) !== null) {
        // Decode HTML entities from URL to ensure consistent hashing
        const url = decodeHtmlEntities(match[1]);

        // Skip special links
        if (
            url.startsWith("mailto:") ||
            url.startsWith("tel:") ||
            url.startsWith("#") ||
            url.includes("unsubscribe") ||
            url.includes("[[") // Skip variable placeholders
        ) {
            continue;
        }

        // Avoid duplicates
        if (!links.some((l) => l.url === url)) {
            links.push({ url });
        }
    }

    return links;
};

// Detected links from content
const detectedLinks = computed(() => extractLinksFromContent(props.content));

// Merge detected links with existing configuration
const trackedLinks = computed({
    get() {
        // Start with existing configuration
        const existingByUrl = new Map(props.modelValue.map((l) => [l.url, l]));

        // Merge with detected links
        return detectedLinks.value.map((detected) => {
            const existing = existingByUrl.get(detected.url);
            return {
                url: detected.url,
                tracking_enabled: existing?.tracking_enabled ?? true,
                share_data_enabled: existing?.share_data_enabled ?? false,
                shared_fields: existing?.shared_fields ?? [],
                subscribe_to_list_ids: existing?.subscribe_to_list_ids ?? [],
                unsubscribe_from_list_ids:
                    existing?.unsubscribe_from_list_ids ?? [],
            };
        });
    },
    set(value) {
        emit("update:modelValue", value);
    },
});

// Update a specific link's property
const updateLink = (index, field, value) => {
    const updated = [...trackedLinks.value];
    updated[index] = { ...updated[index], [field]: value };
    emit("update:modelValue", updated);
};

// Toggle a field in shared_fields array
const toggleSharedField = (index, fieldValue) => {
    const link = trackedLinks.value[index];
    const currentFields = link.shared_fields || [];
    const newFields = currentFields.includes(fieldValue)
        ? currentFields.filter((f) => f !== fieldValue)
        : [...currentFields, fieldValue];
    updateLink(index, "shared_fields", newFields);
};

// Toggle a list in subscribe/unsubscribe array
const toggleListSelection = (index, field, listId) => {
    const link = trackedLinks.value[index];
    const currentIds = link[field] || [];
    const newIds = currentIds.includes(listId)
        ? currentIds.filter((id) => id !== listId)
        : [...currentIds, listId];
    updateLink(index, field, newIds);
};

// Toggle expand/collapse for a link
const toggleExpand = (index) => {
    expandedLinks.value[index] = !expandedLinks.value[index];
};

// Check if link is expanded
const isExpanded = (index) => {
    return expandedLinks.value[index] ?? false;
};

// Format URL for display (truncate if too long)
const formatUrl = (url, maxLength = 50) => {
    if (url.length <= maxLength) return url;
    return url.substring(0, maxLength) + "...";
};

// Get only email lists
const emailLists = computed(() =>
    props.lists.filter((l) => l.type === "email"),
);

// Watch content changes and emit updated tracked links
watch(
    () => props.content,
    () => {
        // Re-emit current tracked links to sync with new detected links
        emit("update:modelValue", trackedLinks.value);
    },
    { immediate: false },
);
</script>

<template>
    <div
        class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
    >
        <div class="mb-4" v-if="trackedLinks.length > 0">
            <h3
                class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-white"
            >
                <span>🔗</span>
                {{ t("messages.tracked_links.title") }}
            </h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ t("messages.tracked_links.subtitle") }}
            </p>
        </div>

        <!-- No links message -->
        <div
            v-if="trackedLinks.length === 0"
            class="py-8 text-center text-slate-500 dark:text-slate-400"
        >
            <span class="mb-2 block text-2xl">📭</span>
            <p>{{ t("messages.tracked_links.no_links") }}</p>
        </div>

        <!-- Links list -->
        <div v-else class="flex flex-col gap-3">
            <div
                v-for="(link, index) in trackedLinks"
                :key="link.url"
                class="rounded-lg border border-slate-200 bg-white shadow-sm transition-all hover:border-indigo-500/50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-400/50"
                :class="{
                    'ring-2 ring-indigo-500/10 dark:ring-indigo-400/10':
                        isExpanded(index),
                    'opacity-75': !link.tracking_enabled,
                }"
            >
                <!-- Link header (always visible) -->
                <div
                    class="flex cursor-pointer items-center gap-3 p-3"
                    @click="toggleExpand(index)"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <span class="flex-shrink-0 text-xl">
                            {{ link.tracking_enabled ? "📊" : "🚫" }}
                        </span>
                        <span
                            class="truncate font-mono text-sm text-slate-900 dark:text-slate-200"
                            :title="link.url"
                        >
                            {{ formatUrl(link.url) }}
                        </span>
                    </div>
                    <div class="flex flex-shrink-0 gap-2">
                        <span
                            v-if="link.share_data_enabled"
                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                        >
                            {{ t("messages.tracked_links.badge_share") }}
                        </span>
                        <span
                            v-if="link.subscribe_to_list_ids?.length"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                        >
                            +{{ link.subscribe_to_list_ids.length }}
                        </span>
                        <span
                            v-if="link.unsubscribe_from_list_ids?.length"
                            class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                        >
                            -{{ link.unsubscribe_from_list_ids.length }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="p-1 text-slate-400 transition-transform dark:text-slate-500"
                    >
                        <span
                            class="inline-block transition-transform duration-200"
                            :class="{ 'rotate-180': isExpanded(index) }"
                            >▼</span
                        >
                    </button>
                </div>

                <!-- Link options (collapsible) -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 -translate-y-2 scale-95"
                >
                    <div
                        v-if="isExpanded(index)"
                        class="border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <!-- Tracking checkbox -->
                        <label
                            class="mb-4 flex cursor-pointer items-start gap-3"
                        >
                            <input
                                type="checkbox"
                                :checked="link.tracking_enabled"
                                @change="
                                    updateLink(
                                        index,
                                        'tracking_enabled',
                                        $event.target.checked,
                                    )
                                "
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-sm font-medium text-slate-900 dark:text-slate-200"
                                    >{{
                                        t(
                                            "messages.tracked_links.tracking_enabled",
                                        )
                                    }}</span
                                >
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                    >{{
                                        t(
                                            "messages.tracked_links.tracking_enabled_desc",
                                        )
                                    }}</span
                                >
                            </div>
                        </label>

                        <!-- Share data checkbox -->
                        <label
                            class="mb-4 flex cursor-pointer items-start gap-3"
                        >
                            <input
                                type="checkbox"
                                :checked="link.share_data_enabled"
                                @change="
                                    updateLink(
                                        index,
                                        'share_data_enabled',
                                        $event.target.checked,
                                    )
                                "
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-sm font-medium text-slate-900 dark:text-slate-200"
                                    >{{
                                        t("messages.tracked_links.share_data")
                                    }}</span
                                >
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                    >{{
                                        t(
                                            "messages.tracked_links.share_data_desc",
                                        )
                                    }}</span
                                >
                            </div>
                        </label>

                        <!-- Shared fields (visible when share_data_enabled) -->
                        <div v-if="link.share_data_enabled" class="mb-4 ml-7">
                            <label
                                class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                >{{
                                    t("messages.tracked_links.shared_fields")
                                }}</label
                            >
                            <div class="flex flex-wrap gap-x-4 gap-y-2">
                                <label
                                    v-for="field in shareableFields"
                                    :key="field.value"
                                    class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            link.shared_fields?.includes(
                                                field.value,
                                            )
                                        "
                                        @change="
                                            toggleSharedField(
                                                index,
                                                field.value,
                                            )
                                        "
                                        class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                    />
                                    <span>{{ t(field.label) }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Subscribe to lists -->
                        <div class="mb-4 ml-7">
                            <label
                                class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-900 dark:text-slate-200"
                            >
                                <span>➕</span>
                                {{
                                    t(
                                        "messages.tracked_links.subscribe_on_click",
                                    )
                                }}
                            </label>
                            <div
                                class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-600 dark:bg-slate-700/50"
                            >
                                <div class="grid gap-2">
                                    <label
                                        v-for="list in emailLists"
                                        :key="list.id"
                                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                link.subscribe_to_list_ids?.includes(
                                                    list.id,
                                                )
                                            "
                                            @change="
                                                toggleListSelection(
                                                    index,
                                                    'subscribe_to_list_ids',
                                                    list.id,
                                                )
                                            "
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                        />
                                        <span class="truncate">{{
                                            list.name
                                        }}</span>
                                    </label>
                                </div>
                                <p
                                    v-if="emailLists.length === 0"
                                    class="py-2 text-center text-xs italic text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t(
                                            "messages.tracked_links.no_email_lists",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Unsubscribe from lists -->
                        <div class="ml-7">
                            <label
                                class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-900 dark:text-slate-200"
                            >
                                <span>➖</span>
                                {{
                                    t(
                                        "messages.tracked_links.unsubscribe_on_click",
                                    )
                                }}
                            </label>
                            <div
                                class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 dark:border-slate-600 dark:bg-slate-700/50"
                            >
                                <div class="grid gap-2">
                                    <label
                                        v-for="list in emailLists"
                                        :key="list.id"
                                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                link.unsubscribe_from_list_ids?.includes(
                                                    list.id,
                                                )
                                            "
                                            @change="
                                                toggleListSelection(
                                                    index,
                                                    'unsubscribe_from_list_ids',
                                                    list.id,
                                                )
                                            "
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                        />
                                        <span class="truncate">{{
                                            list.name
                                        }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>
