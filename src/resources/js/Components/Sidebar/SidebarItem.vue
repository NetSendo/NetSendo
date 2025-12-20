<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

const { isDark } = useTheme();

const props = defineProps({
    href: {
        type: String,
        required: true
    },
    icon: {
        type: String,
        default: null
    },
    active: {
        type: Boolean,
        default: false
    },
    collapsed: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <component
        :is="active ? 'span' : Link"
        :href="active ? undefined : href"
        class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
        :class="{
            'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg shadow-indigo-500/25 cursor-default': active,
            'text-slate-300 hover:bg-white/5 hover:text-white cursor-pointer': !active,
            'justify-center': collapsed
        }"
    >
        <!-- Icon slot -->
        <span 
            class="flex h-5 w-5 flex-shrink-0 items-center justify-center"
            :class="{ 
                'text-indigo-200': active, 
                'text-slate-400 group-hover:text-indigo-400': !active
            }"
        >
            <slot name="icon" />
        </span>

        <!-- Label -->
        <span 
            v-if="!collapsed"
            class="truncate"
        >
            <slot />
        </span>

        <!-- Active indicator -->
        <span 
            v-if="active && !collapsed"
            class="ml-auto h-1.5 w-1.5 rounded-full bg-white"
        ></span>

        <!-- Tooltip for collapsed state -->
        <div 
            v-if="collapsed"
            class="pointer-events-none absolute left-full z-50 ml-2 whitespace-nowrap rounded-lg bg-slate-800 text-white px-3 py-2 text-sm font-medium shadow-xl opacity-0 transition-opacity group-hover:opacity-100"
        >
            <slot />
            <div class="absolute -left-1 top-1/2 h-2 w-2 -translate-y-1/2 rotate-45 bg-slate-800"></div>
        </div>
    </component>
</template>
