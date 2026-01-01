<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import axios from 'axios';
import WebinarReactionBar from './WebinarReactionBar.vue';
import ReactionBubbles from './ReactionBubbles.vue';

const props = defineProps({
    webinarId: { type: Number, required: true },
    sessionId: { type: Number, default: null },
    isHost: { type: Boolean, default: false },
    registrationToken: { type: String, default: null },
    initialSettings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['settings-changed']);

const messages = ref([]);
const pinnedMessage = ref(null);
const newMessage = ref('');
const chatContainer = ref(null);
const reactionBubblesRef = ref(null);
const isLoading = ref(true);

// Chat settings from host
const chatSettings = ref({
    enabled: true,
    mode: 'open',
    slow_mode_seconds: 0,
    reactions_enabled: true,
    ...props.initialSettings,
});

const canSendMessage = computed(() => {
    if (!chatSettings.value.enabled) return false;
    if (chatSettings.value.mode === 'host_only' && !props.isHost) return false;
    return true;
});

const loadMessages = async () => {
    try {
        const response = await axios.get(route('webinars.chat.index', props.webinarId), {
            params: { session_id: props.sessionId }
        });
        messages.value = response.data.messages;
        pinnedMessage.value = response.data.pinned;
        if (response.data.settings) {
            chatSettings.value = { ...chatSettings.value, ...response.data.settings };
        }
    } catch (error) {
        console.error('Failed to load messages:', error);
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || !canSendMessage.value) return;

    const messageText = newMessage.value;
    newMessage.value = '';

    try {
        await axios.post(route('webinars.chat.send', props.webinarId), {
            message: messageText,
            session_id: props.sessionId,
        });
    } catch (error) {
        console.error('Failed to send message:', error);
        newMessage.value = messageText;
    }
};

const pinMessage = async (message) => {
    try {
        await axios.post(route('webinars.chat.pin', [props.webinarId, message.id]));
    } catch (error) {
        console.error('Failed to pin message:', error);
    }
};

const unpinMessage = async (message) => {
    try {
        await axios.post(route('webinars.chat.unpin', [props.webinarId, message.id]));
        if (pinnedMessage.value?.id === message.id) {
            pinnedMessage.value = null;
        }
    } catch (error) {
        console.error('Failed to unpin message:', error);
    }
};

const deleteMessage = async (message) => {
    try {
        await axios.delete(route('webinars.chat.delete', [props.webinarId, message.id]));
        messages.value = messages.value.filter(m => m.id !== message.id);
    } catch (error) {
        console.error('Failed to delete message:', error);
    }
};

const likeMessage = async (message) => {
    try {
        const { data } = await axios.post(route('webinars.chat.like', [props.webinarId, message.id]));
        // Update local message
        const msg = messages.value.find(m => m.id === message.id);
        if (msg) {
            msg.likes_count = data.likes_count;
        }
    } catch (error) {
        console.error('Failed to like message:', error);
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
};

const handleNewMessage = (event) => {
    messages.value.push(event.message);
    scrollToBottom();
};

const handleMessagePinned = (event) => {
    if (event.is_pinned) {
        pinnedMessage.value = event.message;
    } else if (pinnedMessage.value?.id === event.message_id) {
        pinnedMessage.value = null;
    }
};

const handleMessageDeleted = (event) => {
    messages.value = messages.value.filter(m => m.id !== event.message_id);
};

const handleMessageLiked = (event) => {
    const msg = messages.value.find(m => m.id === event.message_id);
    if (msg) {
        msg.likes_count = event.likes_count;
    }
};

const handleSettingsChanged = (event) => {
    chatSettings.value = { ...chatSettings.value, ...event.settings };
    emit('settings-changed', chatSettings.value);
};

const handleLocalReaction = (reaction) => {
    // Add bubble immediately for local reaction
    if (reactionBubblesRef.value) {
        reactionBubblesRef.value.addBubble(reaction);
    }
};

let channel = null;

onMounted(() => {
    loadMessages();

    if (window.Echo) {
        channel = window.Echo.join(`webinar.${props.webinarId}`)
            .listen('.chat.message', handleNewMessage)
            .listen('.message.pinned', handleMessagePinned)
            .listen('.message.deleted', handleMessageDeleted)
            .listen('.message.liked', handleMessageLiked)
            .listen('.chat.settings', handleSettingsChanged)
            .listen('.reaction.sent', (event) => {
                if (reactionBubblesRef.value && event.reaction) {
                    reactionBubblesRef.value.addBubble(event.reaction);
                }
            });
    }
});

onUnmounted(() => {
    if (channel) {
        window.Echo.leave(`webinar.${props.webinarId}`);
    }
});

const getAvatarUrl = (message) => {
    return message.avatar_url || `https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(message.sender_name)}`;
};
</script>

<template>
    <div class="h-full flex flex-col bg-gray-800 relative">
        <!-- Reaction Bubbles Layer -->
        <ReactionBubbles
            ref="reactionBubblesRef"
            :webinar-id="webinarId"
        />

        <!-- Chat Disabled Banner -->
        <div v-if="!chatSettings.enabled" class="bg-yellow-900/50 border-b border-yellow-700 p-2 text-center">
            <span class="text-sm text-yellow-300">{{ $t('webinars.chat.disabled') }}</span>
        </div>

        <!-- Pinned Message -->
        <div v-if="pinnedMessage" class="bg-indigo-900/50 border-b border-indigo-700 p-3">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-yellow-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-indigo-300">{{ pinnedMessage.sender_name }}</p>
                    <p class="text-sm text-white">{{ pinnedMessage.message }}</p>
                </div>
                <button
                    v-if="isHost"
                    @click="unpinMessage(pinnedMessage)"
                    class="text-gray-400 hover:text-white text-xs"
                >
                    {{ $t('webinars.chat.unpin') }}
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div v-if="isLoading" class="flex items-center justify-center h-full">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
            </div>

            <div
                v-else
                v-for="message in messages"
                :key="message.id"
                :class="[
                    'flex items-start gap-2 group',
                    message.is_highlighted ? 'bg-yellow-900/30 -mx-4 px-4 py-2' : '',
                    message.message_type === 'system' ? 'justify-center' : ''
                ]"
            >
                <!-- System/Announcement Message -->
                <template v-if="message.message_type === 'system' || message.message_type === 'announcement'">
                    <div :class="[
                        'px-4 py-2 rounded-lg text-center text-sm',
                        message.announcement_type === 'promo' ? 'bg-red-600/30 text-red-200' :
                        message.announcement_type === 'warning' ? 'bg-yellow-600/30 text-yellow-200' :
                        message.announcement_type === 'success' ? 'bg-green-600/30 text-green-200' :
                        'bg-indigo-600/30 text-indigo-200'
                    ]">
                        {{ message.message }}
                    </div>
                </template>

                <!-- Regular Message -->
                <template v-else>
                    <img
                        :src="getAvatarUrl(message)"
                        :alt="message.sender_name"
                        class="w-8 h-8 rounded-full shrink-0"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'text-sm font-medium',
                                    message.is_from_host ? 'text-indigo-400' : 'text-gray-300'
                                ]"
                            >
                                {{ message.sender_name }}
                            </span>
                            <span v-if="message.is_from_host" class="text-xs bg-indigo-600 text-white px-1.5 py-0.5 rounded">
                                Host
                            </span>
                            <span class="text-xs text-gray-500">{{ message.formatted_time }}</span>
                        </div>
                        <p class="text-sm text-white break-words">{{ message.message }}</p>

                        <!-- Like button for non-hosts -->
                        <div class="flex items-center gap-3 mt-1">
                            <button
                                v-if="!isHost"
                                @click="likeMessage(message)"
                                class="flex items-center gap-1 text-xs text-gray-400 hover:text-red-400 transition-colors"
                            >
                                <span>❤️</span>
                                <span v-if="message.likes_count > 0">{{ message.likes_count }}</span>
                            </button>

                            <!-- Host Actions -->
                            <div v-if="isHost" class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <span v-if="message.likes_count > 0" class="text-xs text-gray-400">
                                    ❤️ {{ message.likes_count }}
                                </span>
                                <button
                                    @click="pinMessage(message)"
                                    class="text-xs text-gray-400 hover:text-yellow-400"
                                >
                                    {{ $t('webinars.chat.pin') }}
                                </button>
                                <button
                                    @click="deleteMessage(message)"
                                    class="text-xs text-gray-400 hover:text-red-400"
                                >
                                    {{ $t('webinars.chat.delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Reaction Bar (for non-hosts) -->
        <div v-if="!isHost && chatSettings.reactions_enabled" class="px-3 py-2 border-t border-gray-700/50">
            <WebinarReactionBar
                :webinar-id="webinarId"
                :session-id="sessionId"
                :registration-token="registrationToken"
                :reactions-enabled="chatSettings.reactions_enabled"
                @reaction="handleLocalReaction"
            />
        </div>

        <!-- Input -->
        <div v-if="canSendMessage" class="p-3 border-t border-gray-700">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input
                    v-model="newMessage"
                    type="text"
                    :placeholder="chatSettings.mode === 'qa_only' ? $t('webinars.chat.ask_question') : $t('webinars.chat.type_message')"
                    class="flex-1 rounded-lg bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500"
                    maxlength="500"
                />
                <button
                    type="submit"
                    :disabled="!newMessage.trim()"
                    class="p-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </button>
            </form>

            <!-- Slow mode indicator -->
            <p v-if="chatSettings.slow_mode_seconds > 0" class="text-xs text-gray-500 mt-1">
                {{ $t('webinars.chat.slow_mode', { seconds: chatSettings.slow_mode_seconds }) }}
            </p>
        </div>

        <!-- Chat disabled for participants -->
        <div v-else-if="!isHost" class="p-3 border-t border-gray-700 text-center">
            <span class="text-sm text-gray-500">
                {{ chatSettings.mode === 'host_only' ? $t('webinars.chat.host_only_mode') : $t('webinars.chat.disabled') }}
            </span>
        </div>
    </div>
</template>
