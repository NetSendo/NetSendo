<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    webinarId: { type: Number, required: true },
    sessionId: { type: Number, default: null },
    isHost: { type: Boolean, default: false },
    registrationToken: { type: String, default: null },
});

const messages = ref([]);
const pinnedMessage = ref(null);
const newMessage = ref('');
const chatContainer = ref(null);
const isLoading = ref(true);

const loadMessages = async () => {
    try {
        const response = await axios.get(route('webinars.chat.index', props.webinarId), {
            params: { session_id: props.sessionId }
        });
        messages.value = response.data.messages;
        pinnedMessage.value = response.data.pinned;
    } catch (error) {
        console.error('Failed to load messages:', error);
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim()) return;

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

let channel = null;

onMounted(() => {
    loadMessages();

    if (window.Echo) {
        channel = window.Echo.join(`webinar.${props.webinarId}`)
            .listen('.chat.message', handleNewMessage)
            .listen('.message.pinned', handleMessagePinned)
            .listen('.message.deleted', handleMessageDeleted);
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
    <div class="h-full flex flex-col bg-gray-800">
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
                    message.is_highlighted ? 'bg-yellow-900/30 -mx-4 px-4 py-2' : ''
                ]"
            >
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
                            {{ $t('webinars.chat.host') }}
                        </span>
                        <span class="text-xs text-gray-500">{{ message.formatted_time }}</span>
                    </div>
                    <p class="text-sm text-white break-words">{{ message.message }}</p>

                    <!-- Host Actions -->
                    <div v-if="isHost" class="flex items-center gap-2 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
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
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-gray-700">
            <form @submit.prevent="sendMessage" class="flex gap-2">
                <input
                    v-model="newMessage"
                    type="text"
                    :placeholder="$t('webinars.chat.type_message')"
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
        </div>
    </div>
</template>
