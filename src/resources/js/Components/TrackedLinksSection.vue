<script setup>
/**
 * TrackedLinksSection Component
 *
 * Displays a section for managing tracked links detected in message content.
 * Allows configuring per-link tracking, data sharing, and list actions.
 */
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },
    content: {
        type: String,
        default: ''
    },
    lists: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['update:modelValue']);

// Available fields for sharing (matching External Pages)
const shareableFields = [
    { value: 'fname', label: 'messages.tracked_links.field_fname' },
    { value: 'lname', label: 'messages.tracked_links.field_lname' },
    { value: 'email', label: 'messages.tracked_links.field_email' },
    { value: 'phone', label: 'messages.tracked_links.field_phone' },
    { value: 'sex', label: 'messages.tracked_links.field_gender' },
];

// Collapse state for each link
const expandedLinks = ref({});

// Extract links from HTML content
const extractLinksFromContent = (html) => {
    if (!html) return [];

    const linkRegex = /href=["']([^"']+)["']/g;
    const links = [];
    let match;

    while ((match = linkRegex.exec(html)) !== null) {
        const url = match[1];

        // Skip special links
        if (url.startsWith('mailto:') ||
            url.startsWith('tel:') ||
            url.startsWith('#') ||
            url.includes('unsubscribe') ||
            url.includes('[[') // Skip variable placeholders
        ) {
            continue;
        }

        // Avoid duplicates
        if (!links.some(l => l.url === url)) {
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
        const existingByUrl = new Map(props.modelValue.map(l => [l.url, l]));

        // Merge with detected links
        return detectedLinks.value.map(detected => {
            const existing = existingByUrl.get(detected.url);
            return {
                url: detected.url,
                tracking_enabled: existing?.tracking_enabled ?? true,
                share_data_enabled: existing?.share_data_enabled ?? false,
                shared_fields: existing?.shared_fields ?? [],
                subscribe_to_list_ids: existing?.subscribe_to_list_ids ?? [],
                unsubscribe_from_list_ids: existing?.unsubscribe_from_list_ids ?? [],
            };
        });
    },
    set(value) {
        emit('update:modelValue', value);
    }
});

// Update a specific link's property
const updateLink = (index, field, value) => {
    const updated = [...trackedLinks.value];
    updated[index] = { ...updated[index], [field]: value };
    emit('update:modelValue', updated);
};

// Toggle a field in shared_fields array
const toggleSharedField = (index, fieldValue) => {
    const link = trackedLinks.value[index];
    const currentFields = link.shared_fields || [];
    const newFields = currentFields.includes(fieldValue)
        ? currentFields.filter(f => f !== fieldValue)
        : [...currentFields, fieldValue];
    updateLink(index, 'shared_fields', newFields);
};

// Toggle a list in subscribe/unsubscribe array
const toggleListSelection = (index, field, listId) => {
    const link = trackedLinks.value[index];
    const currentIds = link[field] || [];
    const newIds = currentIds.includes(listId)
        ? currentIds.filter(id => id !== listId)
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
    return url.substring(0, maxLength) + '...';
};

// Get only email lists
const emailLists = computed(() =>
    props.lists.filter(l => l.type === 'email')
);

// Watch content changes and emit updated tracked links
watch(() => props.content, () => {
    // Re-emit current tracked links to sync with new detected links
    emit('update:modelValue', trackedLinks.value);
}, { immediate: false });
</script>

<template>
    <div class="tracked-links-section">
        <div class="section-header" v-if="trackedLinks.length > 0">
            <h3 class="section-title">
                <span class="emoji">🔗</span>
                {{ t('messages.tracked_links.title') }}
            </h3>
            <p class="section-subtitle">
                {{ t('messages.tracked_links.subtitle') }}
            </p>
        </div>

        <!-- No links message -->
        <div v-if="trackedLinks.length === 0" class="no-links">
            <span class="emoji">📭</span>
            <p>{{ t('messages.tracked_links.no_links') }}</p>
        </div>

        <!-- Links list -->
        <div v-else class="links-list">
            <div
                v-for="(link, index) in trackedLinks"
                :key="link.url"
                class="link-card"
                :class="{ 'expanded': isExpanded(index), 'tracking-disabled': !link.tracking_enabled }"
            >
                <!-- Link header (always visible) -->
                <div class="link-header" @click="toggleExpand(index)">
                    <div class="link-info">
                        <span class="link-icon">
                            {{ link.tracking_enabled ? '📊' : '🚫' }}
                        </span>
                        <span class="link-url" :title="link.url">
                            {{ formatUrl(link.url) }}
                        </span>
                    </div>
                    <div class="link-badges">
                        <span v-if="link.share_data_enabled" class="badge badge-share">
                            {{ t('messages.tracked_links.badge_share') }}
                        </span>
                        <span v-if="link.subscribe_to_list_ids?.length" class="badge badge-subscribe">
                            +{{ link.subscribe_to_list_ids.length }}
                        </span>
                        <span v-if="link.unsubscribe_from_list_ids?.length" class="badge badge-unsubscribe">
                            -{{ link.unsubscribe_from_list_ids.length }}
                        </span>
                    </div>
                    <button type="button" class="expand-btn">
                        <span :class="{ 'rotated': isExpanded(index) }">▼</span>
                    </button>
                </div>

                <!-- Link options (collapsible) -->
                <transition name="slide">
                    <div v-if="isExpanded(index)" class="link-options">
                        <!-- Tracking checkbox -->
                        <label class="option-row checkbox-row">
                            <input
                                type="checkbox"
                                :checked="link.tracking_enabled"
                                @change="updateLink(index, 'tracking_enabled', $event.target.checked)"
                            />
                            <div class="option-content">
                                <span class="option-label">{{ t('messages.tracked_links.tracking_enabled') }}</span>
                                <span class="option-desc">{{ t('messages.tracked_links.tracking_enabled_desc') }}</span>
                            </div>
                        </label>

                        <!-- Share data checkbox -->
                        <label class="option-row checkbox-row">
                            <input
                                type="checkbox"
                                :checked="link.share_data_enabled"
                                @change="updateLink(index, 'share_data_enabled', $event.target.checked)"
                            />
                            <div class="option-content">
                                <span class="option-label">{{ t('messages.tracked_links.share_data') }}</span>
                                <span class="option-desc">{{ t('messages.tracked_links.share_data_desc') }}</span>
                            </div>
                        </label>

                        <!-- Shared fields (visible when share_data_enabled) -->
                        <div v-if="link.share_data_enabled" class="option-row">
                            <label class="option-label">{{ t('messages.tracked_links.shared_fields') }}</label>
                            <div class="checkbox-grid">
                                <label
                                    v-for="field in shareableFields"
                                    :key="field.value"
                                    class="checkbox-item"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="link.shared_fields?.includes(field.value)"
                                        @change="toggleSharedField(index, field.value)"
                                    />
                                    <span>{{ t(field.label) }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Subscribe to lists -->
                        <div class="option-row">
                            <label class="option-label">
                                <span class="emoji">➕</span>
                                {{ t('messages.tracked_links.subscribe_on_click') }}
                            </label>
                            <div class="checkbox-grid list-grid">
                                <label
                                    v-for="list in emailLists"
                                    :key="list.id"
                                    class="checkbox-item"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="link.subscribe_to_list_ids?.includes(list.id)"
                                        @change="toggleListSelection(index, 'subscribe_to_list_ids', list.id)"
                                    />
                                    <span>{{ list.name }}</span>
                                </label>
                            </div>
                            <p v-if="emailLists.length === 0" class="empty-hint">
                                {{ t('messages.tracked_links.no_email_lists') }}
                            </p>
                        </div>

                        <!-- Unsubscribe from lists -->
                        <div class="option-row">
                            <label class="option-label">
                                <span class="emoji">➖</span>
                                {{ t('messages.tracked_links.unsubscribe_on_click') }}
                            </label>
                            <div class="checkbox-grid list-grid">
                                <label
                                    v-for="list in emailLists"
                                    :key="list.id"
                                    class="checkbox-item"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="link.unsubscribe_from_list_ids?.includes(list.id)"
                                        @change="toggleListSelection(index, 'unsubscribe_from_list_ids', list.id)"
                                    />
                                    <span>{{ list.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tracked-links-section {
    margin-top: 1.5rem;
    padding: 1rem;
    background: var(--color-bg-secondary, #f8fafc);
    border-radius: 0.5rem;
    border: 1px solid var(--color-border, #e2e8f0);
}

.section-header {
    margin-bottom: 1rem;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text-primary, #1e293b);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-subtitle {
    font-size: 0.875rem;
    color: var(--color-text-secondary, #64748b);
    margin-top: 0.25rem;
}

.no-links {
    text-align: center;
    padding: 2rem;
    color: var(--color-text-secondary, #64748b);
}

.no-links .emoji {
    font-size: 2rem;
    display: block;
    margin-bottom: 0.5rem;
}

.links-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.link-card {
    background: var(--color-bg-primary, #ffffff);
    border: 1px solid var(--color-border, #e2e8f0);
    border-radius: 0.5rem;
    overflow: hidden;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.link-card:hover {
    border-color: var(--color-primary, #3b82f6);
}

.link-card.expanded {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.link-card.tracking-disabled {
    opacity: 0.75;
}

.link-header {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    cursor: pointer;
    gap: 0.75rem;
}

.link-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.link-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.link-url {
    font-size: 0.875rem;
    color: var(--color-text-primary, #1e293b);
    font-family: monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.link-badges {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-weight: 500;
}

.badge-share {
    background: #dbeafe;
    color: #1d4ed8;
}

.badge-subscribe {
    background: #dcfce7;
    color: #15803d;
}

.badge-unsubscribe {
    background: #fee2e2;
    color: #b91c1c;
}

.expand-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem;
    color: var(--color-text-secondary, #64748b);
    transition: transform 0.2s;
}

.expand-btn span {
    display: inline-block;
    transition: transform 0.2s;
}

.expand-btn span.rotated {
    transform: rotate(180deg);
}

.link-options {
    padding: 1rem;
    background: var(--color-bg-secondary, #f8fafc);
    border-top: 1px solid var(--color-border, #e2e8f0);
}

.option-row {
    margin-bottom: 1rem;
}

.option-row:last-child {
    margin-bottom: 0;
}

.checkbox-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.checkbox-row input[type="checkbox"] {
    margin-top: 0.25rem;
    width: 1rem;
    height: 1rem;
    accent-color: var(--color-primary, #3b82f6);
}

.option-content {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.option-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-text-primary, #1e293b);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.option-desc {
    font-size: 0.75rem;
    color: var(--color-text-secondary, #64748b);
}

.checkbox-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-top: 0.5rem;
}

.list-grid {
    max-height: 150px;
    overflow-y: auto;
    padding: 0.5rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--color-text-primary, #1e293b);
}

.checkbox-item input[type="checkbox"] {
    width: 0.875rem;
    height: 0.875rem;
    accent-color: var(--color-primary, #3b82f6);
}

.empty-hint {
    font-size: 0.75rem;
    color: var(--color-text-secondary, #64748b);
    font-style: italic;
    margin-top: 0.5rem;
}

.emoji {
    font-style: normal;
}

/* Slide transition */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
}

.slide-enter-to,
.slide-leave-from {
    opacity: 1;
    max-height: 500px;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .tracked-links-section {
        background: #1e293b;
        border-color: #334155;
    }

    .link-card {
        background: #0f172a;
        border-color: #334155;
    }

    .link-options {
        background: #1e293b;
        border-color: #334155;
    }

    .list-grid {
        background: #0f172a;
        border-color: #334155;
    }

    .section-title,
    .link-url,
    .option-label,
    .checkbox-item {
        color: #f1f5f9;
    }

    .section-subtitle,
    .option-desc,
    .empty-hint {
        color: #94a3b8;
    }
}
</style>
