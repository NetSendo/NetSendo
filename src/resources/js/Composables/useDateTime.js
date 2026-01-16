import { ref, computed } from 'vue';

// Locale mapping from i18n codes to BCP 47 locale codes
const LOCALE_MAP = {
    'en': 'en-US',
    'de': 'de-DE',
    'es': 'es-ES',
    'pl': 'pl-PL',
};

/**
 * Get current i18n locale from global state
 */
const getCurrentI18nLocale = () => {
    // Try to get from global i18n instance
    if (typeof window !== 'undefined') {
        // Vue-i18n stores locale in the global instance
        const i18n = window.__VUE_I18N__?.global;
        if (i18n?.locale) {
            return typeof i18n.locale === 'object' ? i18n.locale.value : i18n.locale;
        }
        // Fallback: try Inertia page props
        if (window.__page?.props?.locale?.current) {
            return window.__page.props.locale.current;
        }
    }
    return 'en';
};

/**
 * Get BCP 47 locale code for date/time formatting
 */
const getDateLocale = () => {
    const i18nLocale = getCurrentI18nLocale();
    return LOCALE_MAP[i18nLocale] || LOCALE_MAP['en'];
};

// Get user timezone from page props or default to browser timezone
const getUserTimezone = () => {
    // Try to get from Inertia page props
    if (typeof window !== 'undefined' && window.__page?.props?.auth?.user?.timezone) {
        return window.__page.props.auth.user.timezone;
    }
    // Fallback to browser timezone
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Warsaw';
};

// Get user time format preference (12h or 24h)
const getUserTimeFormat = () => {
    if (typeof window !== 'undefined' && window.__page?.props?.auth?.user?.settings?.time_format) {
        return window.__page.props.auth.user.settings.time_format;
    }
    // Default to 24h format
    return '24';
};

// Reactive timezone
const userTimezone = ref(getUserTimezone());

// Reactive time format
const userTimeFormat = ref(getUserTimeFormat());

// Clock interval reference
let clockInterval = null;

/**
 * Date/Time formatting composable with clock functionality
 */
export function useDateTime() {
    /**
     * Get current date locale (BCP 47 format)
     */
    const locale = computed(() => getDateLocale());

    /**
     * Format date to locale string
     */
    const formatDate = (date, customLocale = null, options = {}) => {
        if (!date) return '';
        const d = new Date(date);
        const loc = customLocale || getDateLocale();
        return d.toLocaleDateString(loc, {
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
    const formatTime = (date, customLocale = null, options = {}) => {
        if (!date) return '';
        const d = new Date(date);
        const loc = customLocale || getDateLocale();
        const hour12 = userTimeFormat.value === '12';
        return d.toLocaleTimeString(loc, {
            hour: '2-digit',
            minute: '2-digit',
            hour12: hour12,
            timeZone: userTimezone.value,
            ...options,
        });
    };

    /**
     * Format date and time
     */
    const formatDateTime = (date, customLocale = null) => {
        if (!date) return '';
        const loc = customLocale || getDateLocale();
        return `${formatDate(date, loc)} ${formatTime(date, loc)}`;
    };

    /**
     * Get current time formatted (HH:mm:ss)
     */
    const getCurrentTimeFormatted = (customLocale = null) => {
        const loc = customLocale || getDateLocale();
        // Get fresh time format setting directly from page props
        const currentTimeFormat = getUserTimeFormat();
        const hour12 = currentTimeFormat === '12';
        return new Date().toLocaleTimeString(loc, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: hour12,
            timeZone: getUserTimezone(),
        });
    };

    /**
     * Get current date formatted
     */
    const getCurrentDateFormatted = (customLocale = null) => {
        const loc = customLocale || getDateLocale();
        return new Date().toLocaleDateString(loc, {
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
        // Update timezone and time format on each tick in case it changed
        clockInterval = setInterval(() => {
            userTimezone.value = getUserTimezone();
            userTimeFormat.value = getUserTimeFormat();
        }, 60000); // Update every minute
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
     * Get relative time translations based on current locale
     */
    const getRelativeTimeStrings = () => {
        const i18nLocale = getCurrentI18nLocale();

        const translations = {
            'en': {
                just_now: 'just now',
                minute_ago: '1 minute ago',
                minutes_ago: (n) => `${n} minutes ago`,
                hour_ago: '1 hour ago',
                hours_ago: (n) => `${n} hours ago`,
                day_ago: '1 day ago',
                days_ago: (n) => `${n} days ago`,
            },
            'de': {
                just_now: 'gerade eben',
                minute_ago: 'vor 1 Minute',
                minutes_ago: (n) => `vor ${n} Minuten`,
                hour_ago: 'vor 1 Stunde',
                hours_ago: (n) => `vor ${n} Stunden`,
                day_ago: 'vor 1 Tag',
                days_ago: (n) => `vor ${n} Tagen`,
            },
            'es': {
                just_now: 'hace un momento',
                minute_ago: 'hace 1 minuto',
                minutes_ago: (n) => `hace ${n} minutos`,
                hour_ago: 'hace 1 hora',
                hours_ago: (n) => `hace ${n} horas`,
                day_ago: 'hace 1 día',
                days_ago: (n) => `hace ${n} días`,
            },
            'pl': {
                just_now: 'przed chwilą',
                minute_ago: '1 minutę temu',
                minutes_ago: (n) => `${n} minut temu`,
                hour_ago: '1 godzinę temu',
                hours_ago: (n) => `${n} godzin temu`,
                day_ago: '1 dzień temu',
                days_ago: (n) => `${n} dni temu`,
            },
        };

        return translations[i18nLocale] || translations['en'];
    };

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    const formatRelative = (date, customLocale = null) => {
        if (!date) return '';
        const d = new Date(date);
        const now = new Date();
        const diff = now.getTime() - d.getTime();
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        const strings = getRelativeTimeStrings();
        const loc = customLocale || getDateLocale();

        if (days > 7) {
            return formatDate(date, loc);
        } else if (days > 1) {
            return strings.days_ago(days);
        } else if (days === 1) {
            return strings.day_ago;
        } else if (hours > 1) {
            return strings.hours_ago(hours);
        } else if (hours === 1) {
            return strings.hour_ago;
        } else if (minutes > 1) {
            return strings.minutes_ago(minutes);
        } else if (minutes === 1) {
            return strings.minute_ago;
        } else {
            return strings.just_now;
        }
    };

    /**
     * Format currency value
     * @param {number} value - The value to format
     * @param {string} currency - Currency code (default: PLN)
     * @param {string} customLocale - Optional custom locale
     */
    const formatCurrency = (value, currency = 'PLN', customLocale = null) => {
        const loc = customLocale || getDateLocale();
        return new Intl.NumberFormat(loc, {
            style: 'currency',
            currency: currency,
        }).format(value || 0);
    };

    /**
     * Format number value
     * @param {number} value - The value to format
     * @param {string} customLocale - Optional custom locale
     */
    const formatNumber = (value, customLocale = null) => {
        const loc = customLocale || getDateLocale();
        return new Intl.NumberFormat(loc).format(value || 0);
    };

    /**
     * Get current greeting based on time of day
     */
    const getGreeting = () => {
        const hour = new Date().getHours();
        const i18nLocale = getCurrentI18nLocale();

        const greetings = {
            'en': {
                morning: 'Good morning',
                afternoon: 'Good afternoon',
                evening: 'Good evening',
            },
            'de': {
                morning: 'Guten Morgen',
                afternoon: 'Guten Tag',
                evening: 'Guten Abend',
            },
            'es': {
                morning: 'Buenos días',
                afternoon: 'Buenas tardes',
                evening: 'Buenas noches',
            },
            'pl': {
                morning: 'Dzień dobry',
                afternoon: 'Dzień dobry',
                evening: 'Dobry wieczór',
            },
        };

        const localeGreetings = greetings[i18nLocale] || greetings['en'];

        if (hour < 12) return localeGreetings.morning;
        if (hour < 18) return localeGreetings.afternoon;
        return localeGreetings.evening;
    };

    return {
        userTimezone,
        timeFormat: userTimeFormat,
        locale,
        formatDate,
        formatTime,
        formatDateTime,
        formatRelative,
        formatCurrency,
        formatNumber,
        getGreeting,
        getCurrentTimeFormatted,
        getCurrentDateFormatted,
        startClock,
        stopClock,
        getDateLocale,
    };
}

export default useDateTime;
