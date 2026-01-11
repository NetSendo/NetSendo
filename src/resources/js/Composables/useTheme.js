import { ref, watch, onMounted } from 'vue';

const THEMES = {
    SYSTEM: 'system',
    LIGHT: 'light',
    DARK: 'dark',
};

// Global reactive state
const currentTheme = ref(localStorage.getItem('theme') || THEMES.SYSTEM);
const isDark = ref(false);

// Update dark mode based on theme preference
const updateDarkMode = () => {
    if (currentTheme.value === THEMES.SYSTEM) {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    } else {
        isDark.value = currentTheme.value === THEMES.DARK;
    }

    // Update document class
    if (isDark.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

// Watch for system theme changes
if (typeof window !== 'undefined') {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (currentTheme.value === THEMES.SYSTEM) {
            updateDarkMode();
        }
    });

    // Initial update
    updateDarkMode();
}

// Watch for theme changes
watch(currentTheme, (newTheme) => {
    localStorage.setItem('theme', newTheme);
    updateDarkMode();
});

export function useTheme() {
    const setTheme = (theme) => {
        currentTheme.value = theme;
    };

    const toggleTheme = () => {
        const themes = [THEMES.LIGHT, THEMES.DARK, THEMES.SYSTEM];
        const currentIndex = themes.indexOf(currentTheme.value);
        const nextIndex = (currentIndex + 1) % themes.length;
        currentTheme.value = themes[nextIndex];
    };

    return {
        currentTheme,
        isDark,
        THEMES,
        setTheme,
        toggleTheme,
    };
}

export default useTheme;
