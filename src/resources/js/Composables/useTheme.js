import { ref, watch } from 'vue';

const THEME_KEY = 'netsendo-theme';
const THEMES = {
    LIGHT: 'light',
    DARK: 'dark',
    SYSTEM: 'system'
};

// Shared state across components
const currentTheme = ref(THEMES.SYSTEM);
const isDark = ref(false);

// Initialize immediately (not in onMounted)
let initialized = false;

const getSystemTheme = () => {
    if (typeof window === 'undefined') return THEMES.LIGHT;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? THEMES.DARK : THEMES.LIGHT;
};

const applyTheme = (theme) => {
    if (typeof document === 'undefined') return;

    const effectiveTheme = theme === THEMES.SYSTEM ? getSystemTheme() : theme;
    isDark.value = effectiveTheme === THEMES.DARK;

    if (effectiveTheme === THEMES.DARK) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

const initTheme = () => {
    if (initialized || typeof window === 'undefined') return;
    initialized = true;

    const savedTheme = localStorage.getItem(THEME_KEY) || THEMES.SYSTEM;
    currentTheme.value = savedTheme;
    applyTheme(savedTheme);

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (currentTheme.value === THEMES.SYSTEM) {
            applyTheme(THEMES.SYSTEM);
        }
    });
};

// Initialize immediately when module is imported
if (typeof window !== 'undefined') {
    initTheme();
}

export function useTheme() {
    // Ensure initialized when composable is used
    initTheme();

    const setTheme = (theme) => {
        currentTheme.value = theme;
        localStorage.setItem(THEME_KEY, theme);
        applyTheme(theme);
    };

    const toggleTheme = () => {
        const themes = [THEMES.LIGHT, THEMES.DARK, THEMES.SYSTEM];
        const currentIndex = themes.indexOf(currentTheme.value);
        const nextIndex = (currentIndex + 1) % themes.length;
        setTheme(themes[nextIndex]);
    };

    return {
        currentTheme,
        isDark,
        setTheme,
        toggleTheme,
        THEMES
    };
}
