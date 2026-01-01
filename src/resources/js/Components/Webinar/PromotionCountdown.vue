<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    endTime: { type: [String, Date], required: true },
    productName: { type: String, default: '' },
    remainingQuantity: { type: Number, default: null },
    ctaText: { type: String, default: 'Kup teraz' },
    ctaColor: { type: String, default: '#ef4444' },
    checkoutUrl: { type: String, default: null },
});

const emit = defineEmits(['expired', 'click']);

const timeLeft = ref({
    days: 0,
    hours: 0,
    minutes: 0,
    seconds: 0,
    total: 0,
});

const isExpired = ref(false);
const isUrgent = ref(false);

let interval = null;

const calculateTimeLeft = () => {
    const now = new Date().getTime();
    const end = new Date(props.endTime).getTime();
    const difference = end - now;

    if (difference <= 0) {
        isExpired.value = true;
        timeLeft.value = { days: 0, hours: 0, minutes: 0, seconds: 0, total: 0 };
        emit('expired');
        clearInterval(interval);
        return;
    }

    timeLeft.value = {
        days: Math.floor(difference / (1000 * 60 * 60 * 24)),
        hours: Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
        minutes: Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60)),
        seconds: Math.floor((difference % (1000 * 60)) / 1000),
        total: difference,
    };

    // Urgent mode when less than 60 seconds left
    isUrgent.value = difference < 60000;
};

onMounted(() => {
    calculateTimeLeft();
    interval = setInterval(calculateTimeLeft, 1000);
});

onUnmounted(() => {
    if (interval) {
        clearInterval(interval);
    }
});

const formatNumber = (num) => String(num).padStart(2, '0');

const handleClick = () => {
    if (props.checkoutUrl) {
        window.open(props.checkoutUrl, '_blank');
    }
    emit('click');
};

const containerClass = computed(() => {
    const base = 'relative overflow-hidden rounded-xl transition-all duration-300';
    if (isExpired.value) {
        return `${base} bg-gray-800 opacity-60`;
    }
    if (isUrgent.value) {
        return `${base} bg-gradient-to-r from-red-600 to-orange-500 animate-pulse-fast`;
    }
    return `${base} bg-gradient-to-r from-indigo-600 to-purple-600`;
});
</script>

<template>
    <div :class="containerClass">
        <!-- Shimmer effect -->
        <div
            v-if="!isExpired"
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full animate-shimmer"
        ></div>

        <div class="relative p-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🔥</span>
                    <span class="text-sm font-semibold text-white uppercase tracking-wide">
                        {{ isExpired ? 'Promocja zakończona' : 'Oferta limitowana' }}
                    </span>
                </div>

                <!-- Remaining quantity badge -->
                <div
                    v-if="remainingQuantity !== null && !isExpired"
                    class="px-2 py-1 bg-black/30 rounded-full text-xs font-bold text-white"
                >
                    Zostało: {{ remainingQuantity }}
                </div>
            </div>

            <!-- Countdown -->
            <div v-if="!isExpired" class="flex items-center justify-center gap-2 mb-4">
                <!-- Days (only if > 0) -->
                <div
                    v-if="timeLeft.days > 0"
                    class="flex flex-col items-center"
                >
                    <div class="bg-black/40 rounded-lg px-3 py-2 min-w-[50px]">
                        <span class="text-2xl font-bold text-white tabular-nums">
                            {{ formatNumber(timeLeft.days) }}
                        </span>
                    </div>
                    <span class="text-xs text-white/70 mt-1">{{ $t('webinars.public.watch.countdown.days') }}</span>
                </div>

                <span v-if="timeLeft.days > 0" class="text-xl text-white/50">:</span>

                <!-- Hours -->
                <div class="flex flex-col items-center">
                    <div class="bg-black/40 rounded-lg px-3 py-2 min-w-[50px]">
                        <span class="text-2xl font-bold text-white tabular-nums">
                            {{ formatNumber(timeLeft.hours) }}
                        </span>
                    </div>
                    <span class="text-xs text-white/70 mt-1">{{ $t('webinars.public.watch.countdown.hours') }}</span>
                </div>

                <span class="text-xl text-white/50">:</span>

                <!-- Minutes -->
                <div class="flex flex-col items-center">
                    <div class="bg-black/40 rounded-lg px-3 py-2 min-w-[50px]">
                        <span class="text-2xl font-bold text-white tabular-nums">
                            {{ formatNumber(timeLeft.minutes) }}
                        </span>
                    </div>
                    <span class="text-xs text-white/70 mt-1">{{ $t('webinars.public.watch.countdown.minutes') }}</span>
                </div>

                <span class="text-xl text-white/50">:</span>

                <!-- Seconds -->
                <div class="flex flex-col items-center">
                    <div
                        :class="[
                            'bg-black/40 rounded-lg px-3 py-2 min-w-[50px] transition-all',
                            isUrgent ? 'ring-2 ring-white animate-bounce-subtle' : ''
                        ]"
                    >
                        <span class="text-2xl font-bold text-white tabular-nums">
                            {{ formatNumber(timeLeft.seconds) }}
                        </span>
                    </div>
                    <span class="text-xs text-white/70 mt-1">{{ $t('webinars.public.watch.countdown.seconds') }}</span>
                </div>
            </div>

            <!-- Product info -->
            <div v-if="productName" class="text-center mb-3">
                <p class="text-sm text-white/80">{{ productName }}</p>
            </div>

            <!-- CTA Button -->
            <button
                v-if="!isExpired && checkoutUrl"
                @click="handleClick"
                class="w-full py-3 px-6 rounded-lg font-bold text-lg transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg"
                :style="{
                    backgroundColor: ctaColor,
                    color: '#ffffff',
                }"
            >
                {{ ctaText }}
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes shimmer {
    100% {
        transform: translateX(200%);
    }
}

.animate-shimmer {
    animation: shimmer 3s infinite;
}

@keyframes pulse-fast {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}

.animate-pulse-fast {
    animation: pulse-fast 0.5s ease-in-out infinite;
}

@keyframes bounce-subtle {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2px); }
}

.animate-bounce-subtle {
    animation: bounce-subtle 0.5s ease-in-out infinite;
}
</style>
