<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
        // Each tab: { id: string, label: string, icon?: Component, badge?: string, disabled?: boolean }
    },
    variant: {
        type: String,
        default: 'underline', // 'underline' | 'pills'
    },
});

const emit = defineEmits(['update:modelValue']);

// Mobile dropdown state
const isDropdownOpen = ref(false);
const dropdownRef = ref(null);

// Current active tab
const activeTab = computed(() => {
    return props.tabs.find(tab => tab.id === props.modelValue) || props.tabs[0];
});

// Select a tab
const selectTab = (tabId) => {
    if (props.tabs.find(tab => tab.id === tabId)?.disabled) return;
    emit('update:modelValue', tabId);
    isDropdownOpen.value = false;
};

// Toggle mobile dropdown
const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
};

// Close dropdown on outside click
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isDropdownOpen.value = false;
    }
};

// Horizontal scroll state for tablet
const scrollContainerRef = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const updateScrollIndicators = () => {
    if (!scrollContainerRef.value) return;
    const el = scrollContainerRef.value;
    canScrollLeft.value = el.scrollLeft > 0;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1;
};

const scrollTabs = (direction) => {
    if (!scrollContainerRef.value) return;
    const scrollAmount = 150;
    scrollContainerRef.value.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth',
    });
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    if (scrollContainerRef.value) {
        scrollContainerRef.value.addEventListener('scroll', updateScrollIndicators);
        updateScrollIndicators();
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    if (scrollContainerRef.value) {
        scrollContainerRef.value.removeEventListener('scroll', updateScrollIndicators);
    }
});

// Watch for tab changes to update scroll
watch(() => props.tabs, () => {
    setTimeout(updateScrollIndicators, 100);
}, { deep: true });
</script>

<template>
    <!-- Mobile: Dropdown Selector (visible on sm and below) -->
    <div ref="dropdownRef" class="relative md:hidden">
        <button
            type="button"
            @click="toggleDropdown"
            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-3 text-left text-sm font-medium text-slate-900 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
        >
            <span class="flex items-center gap-2">
                <component
                    v-if="activeTab.icon"
                    :is="activeTab.icon"
                    class="h-4 w-4"
                />
                <span v-else-if="activeTab.emoji" class="text-base">{{ activeTab.emoji }}</span>
                {{ activeTab.label }}
                <span
                    v-if="activeTab.badge"
                    class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                >
                    {{ activeTab.badge }}
                </span>
            </span>
            <svg
                class="h-5 w-5 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180': isDropdownOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isDropdownOpen"
                class="absolute left-0 right-0 z-50 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    @click="selectTab(tab.id)"
                    :disabled="tab.disabled"
                    :class="[
                        'flex w-full items-center gap-2 px-4 py-3 text-left text-sm transition-colors',
                        tab.id === modelValue
                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                            : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700',
                        tab.disabled && 'cursor-not-allowed opacity-50',
                    ]"
                >
                    <component
                        v-if="tab.icon"
                        :is="tab.icon"
                        class="h-4 w-4"
                    />
                    <span v-else-if="tab.emoji" class="text-base">{{ tab.emoji }}</span>
                    {{ tab.label }}
                    <span
                        v-if="tab.badge"
                        class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                    >
                        {{ tab.badge }}
                    </span>
                    <span
                        v-if="tab.indicator"
                        class="flex h-2 w-2 rounded-full bg-green-500"
                    ></span>
                    <!-- Checkmark for active -->
                    <svg
                        v-if="tab.id === modelValue"
                        class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>

    <!-- Desktop/Tablet: Horizontal Tabs (visible on md and above) -->
    <div class="relative hidden md:block">
        <!-- Scroll Left Button -->
        <button
            v-if="canScrollLeft"
            type="button"
            @click="scrollTabs('left')"
            class="absolute left-0 top-0 z-10 flex h-full w-8 items-center justify-center bg-gradient-to-r from-white via-white to-transparent dark:from-slate-900 dark:via-slate-900"
        >
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Tabs Container -->
        <nav
            ref="scrollContainerRef"
            class="flex overflow-x-auto scrollbar-hide"
            :class="[
                variant === 'underline' ? 'gap-1 border-b border-slate-200 dark:border-slate-700' : 'gap-2',
            ]"
            @scroll="updateScrollIndicators"
        >
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                @click="selectTab(tab.id)"
                :disabled="tab.disabled"
                :class="[
                    'flex shrink-0 items-center gap-2 whitespace-nowrap text-sm font-medium transition-colors',
                    variant === 'underline'
                        ? [
                              'border-b-2 px-4 py-3',
                              tab.id === modelValue
                                  ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                  : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                          ]
                        : [
                              'rounded-lg px-4 py-2',
                              tab.id === modelValue
                                  ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                  : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300',
                          ],
                    tab.disabled && 'cursor-not-allowed opacity-50',
                ]"
            >
                <component
                    v-if="tab.icon"
                    :is="tab.icon"
                    class="h-4 w-4"
                />
                <span v-else-if="tab.emoji" class="text-base">{{ tab.emoji }}</span>
                {{ tab.label }}
                <span
                    v-if="tab.badge"
                    class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300"
                >
                    {{ tab.badge }}
                </span>
                <span
                    v-if="tab.indicator"
                    class="flex h-2 w-2 rounded-full bg-green-500"
                ></span>
            </button>
        </nav>

        <!-- Scroll Right Button -->
        <button
            v-if="canScrollRight"
            type="button"
            @click="scrollTabs('right')"
            class="absolute right-0 top-0 z-10 flex h-full w-8 items-center justify-center bg-gradient-to-l from-white via-white to-transparent dark:from-slate-900 dark:via-slate-900"
        >
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
/* Hide scrollbar for Chrome, Safari and Opera */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.scrollbar-hide {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>
