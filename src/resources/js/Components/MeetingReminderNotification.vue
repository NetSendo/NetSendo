<script setup>
import { computed } from "vue";

const props = defineProps({
    meeting: {
        type: Object,
        required: true,
    },
    mode: {
        type: String,
        required: true, // '5min' | 'now'
    },
});

const emit = defineEmits(["dismiss", "join"]);

const isSticky = computed(() => props.mode === "now");

const timeUntilMeeting = computed(() => {
    if (!props.meeting?.start) return "";
    const now = new Date();
    const meetingTime = new Date(props.meeting.start);
    const diffMs = meetingTime.getTime() - now.getTime();
    const diffMinutes = Math.floor(diffMs / (1000 * 60));

    if (diffMinutes <= 0) {
        return "Teraz!";
    } else if (diffMinutes === 1) {
        return "za 1 minutę";
    } else {
        return `za ${diffMinutes} minut`;
    }
});

const meetingTime = computed(() => {
    if (!props.meeting?.start) return "";
    return new Date(props.meeting.start).toLocaleTimeString("pl-PL", {
        hour: "2-digit",
        minute: "2-digit",
    });
});

// Can dismiss "now" mode after 5 minutes past meeting start
const canDismissNow = computed(() => {
    if (props.mode !== "now" || !props.meeting?.start) return false;
    const now = new Date();
    const meetingStart = new Date(props.meeting.start);
    const diffMs = now.getTime() - meetingStart.getTime();
    const diffMinutes = diffMs / (1000 * 60);
    return diffMinutes >= 5; // Allow dismiss after 5 minutes
});
</script>

<template>
    <Transition
        enter-active-class="transform transition duration-300 ease-out"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transform transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div
            v-if="meeting"
            :class="[
                'fixed right-4 z-50 max-w-sm rounded-2xl shadow-2xl',
                isSticky
                    ? 'top-4 ring-2 ring-red-500 animate-pulse-slow'
                    : 'top-20',
            ]"
        >
            <!-- 5-minute warning - dismissible -->
            <div
                v-if="mode === '5min'"
                class="bg-gradient-to-br from-amber-500 to-orange-600 p-4 text-white rounded-2xl"
            >
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 rounded-full bg-white/20 p-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white/80">Nadchodzące spotkanie</p>
                        <h4 class="font-bold text-lg truncate mt-0.5">{{ meeting.title }}</h4>
                        <p class="text-sm text-white/90 mt-1">
                            {{ meetingTime }} · {{ timeUntilMeeting }}
                        </p>
                    </div>
                    <button
                        @click="emit('dismiss')"
                        class="flex-shrink-0 rounded-lg p-1 hover:bg-white/20 transition"
                        title="Zamknij"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="meeting.google_meet_link || meeting.zoom_meeting_link" class="mt-3">
                    <button
                        @click="emit('join')"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-white/20 hover:bg-white/30 px-4 py-2.5 text-sm font-semibold transition"
                    >
                        <svg v-if="meeting.zoom_meeting_link" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 4h10a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm14 3l4-2v10l-4-2V7z"/>
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Przygotuj się do wejścia
                    </button>
                </div>
            </div>

            <!-- 1-minute / NOW - sticky, can't dismiss -->
            <div
                v-else-if="mode === 'now'"
                class="bg-gradient-to-br from-red-500 to-red-700 p-4 text-white rounded-2xl"
            >
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 rounded-full bg-white/20 p-2 animate-bounce">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white/90 uppercase tracking-wide">
                            🔴 Spotkanie zaczyna się!
                        </p>
                        <h4 class="font-bold text-lg truncate mt-0.5">{{ meeting.title }}</h4>
                        <p class="text-sm text-white/90 mt-1">
                            {{ meetingTime }} · <span class="font-bold">{{ timeUntilMeeting }}</span>
                        </p>
                    </div>
                    <!-- Dismiss button - appears after 5 minutes -->
                    <button
                        v-if="canDismissNow"
                        @click="emit('dismiss')"
                        class="flex-shrink-0 rounded-lg p-1 hover:bg-white/20 transition"
                        title="Zamknij"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="meeting.google_meet_link || meeting.zoom_meeting_link" class="mt-3">
                    <button
                        @click="emit('join')"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-white text-red-600 hover:bg-red-50 px-4 py-3 text-base font-bold shadow-lg transition transform hover:scale-105"
                    >
                        <svg v-if="meeting.zoom_meeting_link" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 4h10a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm14 3l4-2v10l-4-2V7z"/>
                        </svg>
                        <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        {{ meeting.zoom_meeting_link ? 'Dołącz do Zoom' : 'Dołącz do spotkania' }}
                    </button>
                </div>

                <p v-if="meeting.contact?.name" class="mt-3 text-sm text-white/80 text-center">
                    z {{ meeting.contact.name }}
                </p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes pulse-slow {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

.animate-pulse-slow {
    animation: pulse-slow 2s ease-in-out infinite;
}
</style>
