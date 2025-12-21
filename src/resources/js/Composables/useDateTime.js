import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Composable for timezone-aware date/time formatting
 * Uses the authenticated user's timezone preference
 */
export function useDateTime() {
    const page = usePage();

    // Get user's timezone from auth props, fallback to browser timezone
    const userTimezone = computed(() => {
        return page.props.auth?.user?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    });

    // Reactive current time
    const currentTime = ref(new Date());
    let intervalId = null;

    /**
     * Format a date/time string to the user's timezone
     * @param {string|Date} date - The date to format
     * @param {object} options - Intl.DateTimeFormat options
     * @returns {string} Formatted date string
     */
    const formatDateTime = (date, options = {}) => {
        if (!date) return '-';

        const dateObj = typeof date === 'string' ? new Date(date) : date;

        if (isNaN(dateObj.getTime())) return '-';

        const defaultOptions = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: userTimezone.value,
            hour12: false,
        };

        return new Intl.DateTimeFormat(
            page.props.auth?.user?.locale || navigator.language || 'pl-PL',
            { ...defaultOptions, ...options }
        ).format(dateObj);
    };

    /**
     * Format a date (without time) to the user's timezone
     * @param {string|Date} date - The date to format
     * @returns {string} Formatted date string
     */
    const formatDate = (date, options = {}) => {
        if (!date) return '-';

        const dateObj = typeof date === 'string' ? new Date(date) : date;

        if (isNaN(dateObj.getTime())) return '-';

        const defaultOptions = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            timeZone: userTimezone.value,
        };

        return new Intl.DateTimeFormat(
            page.props.auth?.user?.locale || navigator.language || 'pl-PL',
            { ...defaultOptions, ...options }
        ).format(dateObj);
    };

    /**
     * Format time only (HH:MM)
     * @param {string|Date} date - The date to format
     * @returns {string} Formatted time string
     */
    const formatTime = (date, options = {}) => {
        if (!date) return '-';

        const dateObj = typeof date === 'string' ? new Date(date) : date;

        if (isNaN(dateObj.getTime())) return '-';

        const defaultOptions = {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: userTimezone.value,
            hour12: false,
        };

        return new Intl.DateTimeFormat(
            page.props.auth?.user?.locale || navigator.language || 'pl-PL',
            { ...defaultOptions, ...options }
        ).format(dateObj);
    };

    /**
     * Get current time formatted in user's timezone
     * @returns {string} Current time formatted as HH:MM:SS
     */
    const getCurrentTimeFormatted = () => {
        return new Intl.DateTimeFormat(
            page.props.auth?.user?.locale || navigator.language || 'pl-PL',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone: userTimezone.value,
                hour12: false,
            }
        ).format(currentTime.value);
    };

    /**
     * Get current date formatted in user's timezone
     * @returns {string} Current date formatted
     */
    const getCurrentDateFormatted = () => {
        return new Intl.DateTimeFormat(
            page.props.auth?.user?.locale || navigator.language || 'pl-PL',
            {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: userTimezone.value,
            }
        ).format(currentTime.value);
    };

    /**
     * Start updating the current time every second
     */
    const startClock = () => {
        if (intervalId) return;

        intervalId = setInterval(() => {
            currentTime.value = new Date();
        }, 1000);
    };

    /**
     * Stop updating the current time
     */
    const stopClock = () => {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    // Cleanup on unmount
    onUnmounted(() => {
        stopClock();
    });

    return {
        userTimezone,
        currentTime,
        formatDateTime,
        formatDate,
        formatTime,
        getCurrentTimeFormatted,
        getCurrentDateFormatted,
        startClock,
        stopClock,
    };
}
