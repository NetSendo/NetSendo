import { ref, computed } from 'vue';

// Get user timezone from page props or default to browser timezone
const getUserTimezone = () => {
    // Try to get from Inertia page props
    if (typeof window !== 'undefined' && window.__page?.props?.auth?.user?.timezone) {
        return window.__page.props.auth.user.timezone;
    }
    // Fallback to browser timezone
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Warsaw';
};

// Reactive timezone
const userTimezone = ref(getUserTimezone());

// Clock interval reference
let clockInterval = null;

/**
 * Date/Time formatting composable with clock functionality
 */
export function useDateTime() {
    /**
     * Format date to locale string
     */
    const formatDate = (date, locale = 'pl-PL', options = {}) => {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: userTimezone.value,
            ...options,
        });
    };

    /**
     * Format time to locale string
     */
    const formatTime = (date, locale = 'pl-PL', options = {}) => {
        if (!date) return '';
        const d = new Date(date);
        return d.toLocaleTimeString(locale, {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: userTimezone.value,
            ...options,
        });
    };

    /**
     * Format date and time
     */
    const formatDateTime = (date, locale = 'pl-PL') => {
        if (!date) return '';
        return `${formatDate(date, locale)} ${formatTime(date, locale)}`;
    };

    /**
     * Get current time formatted (HH:mm:ss)
     */
    const getCurrentTimeFormatted = (locale = 'pl-PL') => {
        return new Date().toLocaleTimeString(locale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: userTimezone.value,
        });
    };

    /**
     * Get current date formatted
     */
    const getCurrentDateFormatted = (locale = 'pl-PL') => {
        return new Date().toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: userTimezone.value,
        });
    };

    /**
     * Start the clock update interval
     */
    const startClock = () => {
        if (clockInterval) {
            clearInterval(clockInterval);
        }
        // Update timezone on each tick in case it changed
        clockInterval = setInterval(() => {
            userTimezone.value = getUserTimezone();
        }, 60000); // Update timezone every minute
    };

    /**
     * Stop the clock interval
     */
    const stopClock = () => {
        if (clockInterval) {
            clearInterval(clockInterval);
            clockInterval = null;
        }
    };

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    const formatRelative = (date, locale = 'pl-PL') => {
        if (!date) return '';
        const d = new Date(date);
        const now = new Date();
        const diff = now.getTime() - d.getTime();
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 7) {
            return formatDate(date, locale);
        } else if (days > 0) {
            return `${days} ${days === 1 ? 'dzień' : 'dni'} temu`;
        } else if (hours > 0) {
            return `${hours} ${hours === 1 ? 'godzinę' : 'godzin'} temu`;
        } else if (minutes > 0) {
            return `${minutes} ${minutes === 1 ? 'minutę' : 'minut'} temu`;
        } else {
            return 'przed chwilą';
        }
    };

    /**
     * Get current greeting based on time of day
     */
    const getGreeting = () => {
        const hour = new Date().getHours();
        if (hour < 12) return 'Dzień dobry';
        if (hour < 18) return 'Dzień dobry';
        return 'Dobry wieczór';
    };

    return {
        userTimezone,
        formatDate,
        formatTime,
        formatDateTime,
        formatRelative,
        getGreeting,
        getCurrentTimeFormatted,
        getCurrentDateFormatted,
        startClock,
        stopClock,
    };
}

export default useDateTime;
