<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    webinarId: { type: Number, required: true },
    sessionId: { type: Number, default: null },
    registrationToken: { type: String, default: null },
    reactionsEnabled: { type: Boolean, default: true },
});

const emit = defineEmits(['reaction']);

const isLoading = ref(false);
const cooldowns = ref({});

const reactions = [
    { type: 'heart', emoji: '❤️', label: 'Serduszko' },
    { type: 'thumbs_up', emoji: '👍', label: 'Kciuk w górę' },
    { type: 'fire', emoji: '🔥', label: 'Ogień' },
    { type: 'clap', emoji: '👏', label: 'Brawo' },
    { type: 'wow', emoji: '😮', label: 'Wow' },
    { type: 'laugh', emoji: '😂', label: 'Śmiech' },
    { type: 'think', emoji: '🤔', label: 'Hmm' },
];

const sendReaction = async (type) => {
    if (!props.reactionsEnabled || cooldowns.value[type]) {
        return;
    }

    // Set cooldown to prevent spam
    cooldowns.value[type] = true;
    setTimeout(() => {
        cooldowns.value[type] = false;
    }, 300);

    // Emit local reaction immediately for animation
    emit('reaction', {
        type,
        emoji: reactions.find(r => r.type === type)?.emoji,
        position_x: Math.floor(Math.random() * 80) + 10,
        local: true,
    });

    try {
        await axios.post(`/webinars/${props.webinarId}/reactions`, {
            type,
            session_id: props.sessionId,
            registration_token: props.registrationToken,
        });
    } catch (error) {
        console.error('Failed to send reaction:', error);
    }
};

const getButtonClass = (type) => {
    const base = 'relative flex items-center justify-center w-10 h-10 rounded-full transition-all duration-200 hover:scale-125 active:scale-90';
    if (cooldowns.value[type]) {
        return `${base} opacity-50 cursor-not-allowed`;
    }
    return `${base} cursor-pointer hover:bg-white/10`;
};
</script>

<template>
    <div
        v-if="reactionsEnabled"
        class="flex items-center justify-center gap-2 p-2 bg-gray-900/80 backdrop-blur-sm rounded-full"
    >
        <button
            v-for="reaction in reactions"
            :key="reaction.type"
            :class="getButtonClass(reaction.type)"
            :disabled="!reactionsEnabled || cooldowns[reaction.type]"
            :title="reaction.label"
            @click="sendReaction(reaction.type)"
        >
            <span class="text-xl transform transition-transform">
                {{ reaction.emoji }}
            </span>

            <!-- Pulse effect on click -->
            <span
                v-if="cooldowns[reaction.type]"
                class="absolute inset-0 rounded-full bg-white/20 animate-ping"
            ></span>
        </button>
    </div>
</template>

<style scoped>
button:active span:first-child {
    animation: bounce 0.3s ease-out;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.4); }
}
</style>
