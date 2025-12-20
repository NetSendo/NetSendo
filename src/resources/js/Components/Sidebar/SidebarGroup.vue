<script setup>
import { useTheme } from '@/Composables/useTheme';

const { isDark } = useTheme();

const props = defineProps({
    label: {
        type: String,
        required: true
    },
    isOpen: {
        type: Boolean,
        default: false
    },
    collapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle']);

const toggle = () => {
    if (!props.collapsed) {
        emit('toggle');
    }
};
</script>

<template>
    <div class="space-y-1">
        <!-- Group Header -->
        <button
            @click="toggle"
            class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold uppercase tracking-wider text-slate-400 transition-all duration-200 hover:bg-white/5 hover:text-white"
            :class="{ 'justify-center': collapsed }"
        >
            <!-- Icon -->
            <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center text-slate-500 group-hover:text-indigo-400">
                <slot name="icon" />
            </span>

            <!-- Label -->
            <span v-if="!collapsed" class="flex-1 truncate text-xs">
                {{ label }}
            </span>

            <!-- Chevron -->
            <svg
                v-if="!collapsed"
                class="h-4 w-4 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Group Content -->
        <div
            v-show="isOpen && !collapsed"
            class="ml-3 space-y-0.5 border-l border-slate-600/50 pl-3"
        >
            <slot />
        </div>

        <!-- Collapsed popup menu -->
        <div
            v-if="collapsed"
            class="pointer-events-none absolute left-full z-50 ml-2 min-w-48 rounded-xl bg-slate-800 p-2 opacity-0 shadow-xl transition-opacity group-hover:pointer-events-auto group-hover:opacity-100"
        >
            <div class="mb-2 border-b border-slate-700/50 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                {{ label }}
            </div>
            <slot />
        </div>
    </div>
</template>
