<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    webinarId: { type: Number, required: true },
    initialSettings: { type: Object, default: () => ({}) },
    pendingCount: { type: Number, default: 0 },
    viewersCount: { type: Number, default: 0 },
});

const emit = defineEmits(['settings-changed', 'announcement-sent']);

const settings = ref({
    enabled: true,
    mode: 'open',
    slow_mode_seconds: 0,
    reactions_enabled: true,
    fake_viewers_base: 50,
    fake_viewers_variance: 20,
    ...props.initialSettings,
});

const announcementText = ref('');
const announcementType = ref('info');
const isSaving = ref(false);
const isSendingAnnouncement = ref(false);

const modeOptions = computed(() => [
    { value: 'open', label: t('webinars.chat_panel.modes.open.label'), description: t('webinars.chat_panel.modes.open.desc') },
    { value: 'moderated', label: t('webinars.chat_panel.modes.moderated.label'), description: t('webinars.chat_panel.modes.moderated.desc') },
    { value: 'qa_only', label: t('webinars.chat_panel.modes.qa_only.label'), description: t('webinars.chat_panel.modes.qa_only.desc') },
    { value: 'host_only', label: t('webinars.chat_panel.modes.host_only.label'), description: t('webinars.chat_panel.modes.host_only.desc') },
]);

const slowModeOptions = computed(() => [
    { value: 0, label: t('webinars.chat_panel.disabled') },
    { value: 5, label: `5 ${t('webinars.chat_panel.seconds')}` },
    { value: 10, label: `10 ${t('webinars.chat_panel.seconds')}` },
    { value: 30, label: `30 ${t('webinars.chat_panel.seconds')}` },
    { value: 60, label: `1 ${t('webinars.chat_panel.minute')}` },
]);

const announcementTypes = computed(() => [
    { value: 'info', label: 'ℹ️ Info', color: 'blue' },
    { value: 'success', label: `✅ ${t('webinars.chat_panel.announcement_types.success')}`, color: 'green' },
    { value: 'warning', label: `⚠️ ${t('webinars.chat_panel.announcement_types.warning')}`, color: 'yellow' },
    { value: 'promo', label: `🔥 ${t('webinars.chat_panel.announcement_types.promo')}`, color: 'red' },
]);



const updateSetting = async (key, value) => {
    settings.value[key] = value;
    isSaving.value = true;

    try {
        await axios.post(`/webinars/${props.webinarId}/host/chat-settings`, {
            [key]: value,
        });
        emit('settings-changed', settings.value);
    } catch (error) {
        console.error('Failed to update setting:', error);
        // Revert on error
        settings.value[key] = props.initialSettings[key];
    } finally {
        isSaving.value = false;
    }
};

const sendAnnouncement = async () => {
    if (!announcementText.value.trim()) return;

    isSendingAnnouncement.value = true;

    try {
        await axios.post(`/webinars/${props.webinarId}/host/announcement`, {
            message: announcementText.value,
            type: announcementType.value,
        });
        emit('announcement-sent', {
            message: announcementText.value,
            type: announcementType.value,
        });
        announcementText.value = '';
    } catch (error) {
        console.error('Failed to send announcement:', error);
    } finally {
        isSendingAnnouncement.value = false;
    }
};

watch(() => props.initialSettings, (newSettings) => {
    settings.value = { ...settings.value, ...newSettings };
}, { deep: true });
</script>

<template>
    <div class="bg-gray-800 rounded-xl p-4 space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ $t('webinars.chat_panel.title') }}
            </h3>

            <!-- Viewers count -->
            <div class="flex items-center gap-2 px-3 py-1 bg-gray-700 rounded-full">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-white">{{ viewersCount }} {{ $t('webinars.chat_panel.viewers') }}</span>
            </div>
        </div>

        <!-- Quick toggles -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Chat enabled -->
            <label class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg cursor-pointer hover:bg-gray-700 transition-colors">
                <input
                    type="checkbox"
                    :checked="settings.enabled"
                    @change="updateSetting('enabled', $event.target.checked)"
                    class="w-5 h-5 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                />
                <div>
                    <p class="text-sm font-medium text-white">{{ $t('webinars.chat_panel.chat_enabled') }}</p>
                    <p class="text-xs text-gray-400">{{ $t('webinars.chat_panel.chat_visibility') }}</p>
                </div>
            </label>

            <!-- Reactions enabled -->
            <label class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg cursor-pointer hover:bg-gray-700 transition-colors">
                <input
                    type="checkbox"
                    :checked="settings.reactions_enabled"
                    @change="updateSetting('reactions_enabled', $event.target.checked)"
                    class="w-5 h-5 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                />
                <div>
                    <p class="text-sm font-medium text-white">{{ $t('webinars.chat_panel.reactions') }}</p>
                    <p class="text-xs text-gray-400">{{ $t('webinars.chat_panel.reactions_hint') }}</p>
                </div>
            </label>
        </div>

        <!-- Chat mode -->
        <div>
            <label class
="block text-sm font-medium text-gray-300 mb-2">
                {{ $t('webinars.chat_panel.chat_mode') }}
            </label>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="option in modeOptions"
                    :key="option.value"
                    @click="updateSetting('mode', option.value)"
                    :class="[
                        'p-2 rounded-lg text-left transition-all',
                        settings.mode === option.value
                            ? 'bg-indigo-600 ring-2 ring-indigo-400'
                            : 'bg-gray-700/50 hover:bg-gray-700'
                    ]"
                >
                    <p class="text-sm font-medium text-white">{{ $t(`webinars.chat_panel.modes.${option.value}.label`) }}</p>
                    <p class="text-xs text-gray-400">{{ $t(`webinars.chat_panel.modes.${option.value}.desc`) }}</p>
                </button>
            </div>
        </div>

        <!-- Slow mode -->
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t('webinars.chat_panel.slow_mode') }}
            </label>
            <select
                :value="settings.slow_mode_seconds"
                @change="updateSetting('slow_mode_seconds', parseInt($event.target.value))"
                class="w-full bg-gray-700 border-gray-600 rounded-lg text-white text-sm focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option v-for="option in slowModeOptions" :key="option.value" :value="option.value">
                    {{ option.value === 0 ? $t('webinars.chat_panel.disabled') : option.label }}
                </option>
            </select>
        </div>

        <!-- Fake viewers -->
        <div class="p-3 bg-gray-700/30 rounded-lg">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                {{ $t('webinars.chat_panel.viewers_counter') }}
            </label>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <label class="text-xs text-gray-400">{{ $t('webinars.chat_panel.base') }}</label>
                    <input
                        type="number"
                        :value="settings.fake_viewers_base"
                        @change="updateSetting('fake_viewers_base', parseInt($event.target.value))"
                        min="0" max="10000"
                        class="w-full bg-gray-700 border-gray-600 rounded-lg text-white text-sm focus:ring-indigo-500"
                    />
                </div>
                <div class="flex-1">
                    <label class="text-xs text-gray-400">{{ $t('webinars.chat_panel.variance') }} ±</label>
                    <input
                        type="number"
                        :value="settings.fake_viewers_variance"
                        @change="updateSetting('fake_viewers_variance', parseInt($event.target.value))"
                        min="0" max="500"
                        class="w-full bg-gray-700 border-gray-600 rounded-lg text-white text-sm focus:ring-indigo-500"
                    />
                </div>
            </div>
        </div>

        <!-- Pending messages badge -->
        <div v-if="pendingCount > 0" class="flex items-center justify-between p-3 bg-yellow-500/20 border border-yellow-500/50 rounded-lg">
            <div class="flex items-center gap-2">
                <span class="text-yellow-400">⏳</span>
                <span class="text-sm text-yellow-200">{{ pendingCount }} {{ $t('webinars.chat_panel.pending_messages') }}</span>
            </div>
            <button class="text-xs text-yellow-300 underline hover:no-underline">
                {{ $t('webinars.chat_panel.view') }}
            </button>
        </div>

        <!-- Announcement -->
        <div class="border-t border-gray-700 pt-4">
            <label class="block text-sm font-medium text-gray-300 mb-2">
                📢 {{ $t('webinars.chat_panel.send_announcement') }}
            </label>

            <div class="flex gap-2 mb-2">
                <button
                    v-for="type in announcementTypes"
                    :key="type.value"
                    @click="announcementType = type.value"
                    :class="[
                        'px-3 py-1 rounded-full text-xs font-medium transition-all',
                        announcementType === type.value
                            ? `bg-${type.color}-500/30 ring-1 ring-${type.color}-400 text-${type.color}-300`
                            : 'bg-gray-700/50 text-gray-400 hover:bg-gray-700'
                    ]"
                >
                    {{ type.label }}
                </button>
            </div>

            <div class="flex gap-2">
                <input
                    v-model="announcementText"
                    type="text"
                    :placeholder="$t('webinars.chat_panel.announcement_placeholder')"
                    maxlength="500"
                    class="flex-1 bg-gray-700 border-gray-600 rounded-lg text-white text-sm placeholder-gray-500 focus:ring-indigo-500"
                    @keydown.enter="sendAnnouncement"
                />
                <button
                    @click="sendAnnouncement"
                    :disabled="!announcementText.trim() || isSendingAnnouncement"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white text-sm font-medium transition-colors"
                >
                    {{ isSendingAnnouncement ? '...' : $t('webinars.chat_panel.send') }}
                </button>
            </div>
        </div>

        <!-- Saving indicator -->
        <div v-if="isSaving" class="flex items-center justify-center gap-2 text-sm text-gray-400">
            <div class="w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></div>
            {{ $t('webinars.chat_panel.saving') }}
        </div>
    </div>
</template>
