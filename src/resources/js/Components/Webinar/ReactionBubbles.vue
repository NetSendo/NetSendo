<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    webinarId: { type: Number, required: true },
});

const bubbles = ref([]);
let bubbleIdCounter = 0;

// Add a new reaction bubble
const addBubble = (reaction) => {
    const id = bubbleIdCounter++;
    const positionX = reaction.position_x ?? Math.floor(Math.random() * 80) + 10;

    bubbles.value.push({
        id,
        emoji: reaction.emoji,
        positionX,
        scale: 0.8 + Math.random() * 0.6, // Random size variation
        duration: 2000 + Math.random() * 1000, // 2-3 seconds
        delay: Math.random() * 200, // Slight stagger
    });

    // Remove bubble after animation
    setTimeout(() => {
        bubbles.value = bubbles.value.filter(b => b.id !== id);
    }, 4000);
};

// Listen for local reactions
defineExpose({
    addBubble,
});

// Listen for WebSocket reactions
let channel = null;

onMounted(() => {
    if (window.Echo) {
        channel = window.Echo.join(`webinar.${props.webinarId}`)
            .listen('.reaction.sent', (event) => {
                if (event.reaction) {
                    addBubble(event.reaction);
                }
            });
    }
});

onUnmounted(() => {
    if (channel) {
        window.Echo.leave(`webinar.${props.webinarId}`);
    }
});

const getBubbleStyle = (bubble) => ({
    left: `${bubble.positionX}%`,
    transform: `scale(${bubble.scale})`,
    animationDuration: `${bubble.duration}ms`,
    animationDelay: `${bubble.delay}ms`,
});
</script>

<template>
    <div class="absolute inset-0 pointer-events-none overflow-hidden z-20">
        <TransitionGroup name="bubble">
            <div
                v-for="bubble in bubbles"
                :key="bubble.id"
                class="reaction-bubble absolute bottom-0"
                :style="getBubbleStyle(bubble)"
            >
                <span class="text-3xl filter drop-shadow-lg">
                    {{ bubble.emoji }}
                </span>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.reaction-bubble {
    animation: float-up ease-out forwards;
}

@keyframes float-up {
    0% {
        opacity: 1;
        transform: translateY(0) scale(var(--scale, 1));
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 0.8;
    }
    100% {
        opacity: 0;
        transform: translateY(-400px) scale(calc(var(--scale, 1) * 0.6)) translateX(20px);
    }
}

/* Slight wobble effect */
.reaction-bubble span {
    animation: wobble 0.5s ease-in-out infinite alternate;
}

@keyframes wobble {
    0% { transform: rotate(-5deg); }
    100% { transform: rotate(5deg); }
}

/* Transition group animations */
.bubble-enter-active {
    transition: all 0.3s ease-out;
}

.bubble-leave-active {
    transition: opacity 0.3s ease-in;
}

.bubble-enter-from {
    opacity: 0;
    transform: scale(0.5) translateY(20px);
}

.bubble-leave-to {
    opacity: 0;
}
</style>
