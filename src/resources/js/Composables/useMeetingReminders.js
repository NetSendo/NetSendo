import { ref, onMounted, onUnmounted } from "vue";
import axios from "axios";

/**
 * Composable for managing upcoming meeting reminders.
 * Checks for meetings starting soon and triggers notifications.
 */
export function useMeetingReminders() {
    const upcomingMeetings = ref([]);
    const activeReminder = ref(null);
    const reminderMode = ref(null); // '5min' | '1min' | 'now'
    const isLoading = ref(false);
    const checkInterval = ref(null);
    // Audio context for notification sound
    let audioContext = null;

    // Initialize audio context (must be called after user interaction)
    const initSound = () => {
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Web Audio API not supported');
        }
    };

    // Play notification sound using Web Audio API
    const playSound = () => {
        if (!audioContext) {
            initSound();
        }

        if (!audioContext) return;

        try {
            // Create oscillator for a pleasant notification beep
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Two-tone notification sound
            oscillator.frequency.value = 880; // A5 note
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);

            // Second beep after short pause
            setTimeout(() => {
                const osc2 = audioContext.createOscillator();
                const gain2 = audioContext.createGain();

                osc2.connect(gain2);
                gain2.connect(audioContext.destination);

                osc2.frequency.value = 1046.5; // C6 note
                osc2.type = 'sine';

                gain2.gain.setValueAtTime(0.3, audioContext.currentTime);
                gain2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

                osc2.start(audioContext.currentTime);
                osc2.stop(audioContext.currentTime + 0.3);
            }, 150);
        } catch (e) {
            console.warn('Failed to play notification sound:', e);
        }
    };

    // Fetch upcoming meetings from the server
    const fetchUpcomingMeetings = async () => {
        try {
            isLoading.value = true;
            const response = await axios.get("/crm/tasks/upcoming-meetings");
            upcomingMeetings.value = response.data.meetings || [];
            checkForReminders();
        } catch (error) {
            console.error("Failed to fetch upcoming meetings:", error);
        } finally {
            isLoading.value = false;
        }
    };

    // Check if any meeting needs a reminder
    const checkForReminders = () => {
        const now = new Date();

        for (const meeting of upcomingMeetings.value) {
            const meetingTime = new Date(meeting.start);
            const diffMs = meetingTime.getTime() - now.getTime();
            const diffMinutes = diffMs / (1000 * 60);

            // Meeting started or starting in less than 1 minute - sticky notification
            if (diffMinutes <= 1 && diffMinutes > -30) {
                if (
                    activeReminder.value?.id !== meeting.id ||
                    reminderMode.value !== "now"
                ) {
                    activeReminder.value = meeting;
                    reminderMode.value = "now";
                    playSound();
                }
                return;
            }

            // Meeting starting in 1-5 minutes - warning notification
            if (diffMinutes > 1 && diffMinutes <= 5) {
                if (
                    activeReminder.value?.id !== meeting.id ||
                    reminderMode.value !== "5min"
                ) {
                    activeReminder.value = meeting;
                    reminderMode.value = "5min";
                    playSound();
                }
                return;
            }
        }

        // No active reminders needed - but don't clear if we're in "now" mode
        if (reminderMode.value !== "now") {
            activeReminder.value = null;
            reminderMode.value = null;
        }
    };

    // Dismiss the reminder
    const dismissReminder = () => {
        // Allow dismissing 5-minute reminders always,
        // and "now" reminders after 5 minutes (validated in component)
        if (reminderMode.value === "5min" || reminderMode.value === "now") {
            activeReminder.value = null;
            reminderMode.value = null;
        }
    };

    // Join the meeting
    const joinMeeting = () => {
        if (activeReminder.value?.zoom_meeting_link) {
            window.open(activeReminder.value.zoom_meeting_link, "_blank");
        } else if (activeReminder.value?.google_meet_link) {
            window.open(activeReminder.value.google_meet_link, "_blank");
        }
    };

    // Start checking for reminders
    const startChecking = () => {
        initSound();
        fetchUpcomingMeetings();

        // Check every 30 seconds
        checkInterval.value = setInterval(() => {
            fetchUpcomingMeetings();
        }, 30000);
    };

    // Stop checking for reminders
    const stopChecking = () => {
        if (checkInterval.value) {
            clearInterval(checkInterval.value);
            checkInterval.value = null;
        }
    };

    onMounted(() => {
        startChecking();
    });

    onUnmounted(() => {
        stopChecking();
    });

    return {
        upcomingMeetings,
        activeReminder,
        reminderMode,
        isLoading,
        dismissReminder,
        joinMeeting,
        fetchUpcomingMeetings,
    };
}
