<script setup>
import { useTheme } from '@/Composables/useTheme';

const { currentTheme, toggleTheme, THEMES } = useTheme();
</script>

<template>
    <button
        @click="toggleTheme"
        class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-slate-400 backdrop-blur-sm transition-all duration-300 hover:bg-white/20 hover:text-white"
        :title="currentTheme === THEMES.LIGHT ? 'Jasny motyw' : currentTheme === THEMES.DARK ? 'Ciemny motyw' : 'Motyw systemowy'"
    >
        <!-- Sun icon (light mode) -->
        <svg
            v-if="currentTheme === THEMES.LIGHT"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
            />
        </svg>

        <!-- Moon icon (dark mode) -->
        <svg
            v-else-if="currentTheme === THEMES.DARK"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
            />
        </svg>

        <!-- System icon (auto mode) -->
        <svg
            v-else
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
        </svg>

        <!-- Indicator dot -->
        <span 
            class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-slate-800"
            :class="{
                'bg-amber-400': currentTheme === THEMES.LIGHT,
                'bg-indigo-400': currentTheme === THEMES.DARK,
                'bg-emerald-400': currentTheme === THEMES.SYSTEM
            }"
        ></span>
    </button>
</template>
