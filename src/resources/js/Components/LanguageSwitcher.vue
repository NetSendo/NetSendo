<script setup>
/**
 * LanguageSwitcher Component
 *
 * Dropdown component for switching application language.
 * Displays current language flag and allows selection of available languages.
 */

import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'light', // 'light' for light backgrounds (AuthenticatedLayout), 'dark' for dark backgrounds (GuestLayout)
        validator: (value) => ['light', 'dark'].includes(value),
    },
});

const page = usePage();
const { locale } = useI18n({ useScope: 'global' });

// Get locale data from Inertia shared props
const currentLocale = computed(() => page.props.locale?.current || 'en');
const supportedLocales = computed(() => page.props.locale?.supported || {});
const currentLocaleData = computed(() => supportedLocales.value[currentLocale.value] || {});

/**
 * Switch to a new locale
 * Sends POST request to update session/user preference and reloads page
 */
const switchLocale = (localeCode) => {
    if (localeCode === currentLocale.value) return;

    router.post(route('locale.update'), { locale: localeCode }, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            // Update vue-i18n locale after successful switch
            locale.value = localeCode;
        },
    });
};
</script>

<template>
    <Dropdown align="right" width="48">
        <template #trigger>
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl px-3 py-2 transition-colors"
                :class="variant === 'dark' 
                    ? 'hover:bg-white/10' 
                    : 'hover:bg-slate-100 dark:hover:bg-slate-800'"
                :title="$t('language.select')"
            >
                <span class="text-lg leading-none">{{ currentLocaleData?.flag }}</span>
                <span 
                    class="hidden text-sm font-medium sm:inline"
                    :class="variant === 'dark' 
                        ? 'text-white/80 hover:text-white' 
                        : 'text-slate-700 dark:text-slate-200'"
                >
                    {{ currentLocaleData?.native }}
                </span>
                <svg 
                    class="h-4 w-4" 
                    :class="variant === 'dark' ? 'text-white/60' : 'text-slate-400'"
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </template>

        <template #content>
            <div class="py-1">
                <div class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ $t('language.select') }}
                </div>

                <button
                    v-for="(data, code) in supportedLocales"
                    :key="code"
                    @click="switchLocale(code)"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors hover:bg-slate-100 dark:hover:bg-slate-700"
                    :class="{
                        'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300': code === currentLocale,
                        'text-slate-700 dark:text-slate-200': code !== currentLocale
                    }"
                >
                    <span class="text-lg leading-none">{{ data.flag }}</span>
                    <div class="flex flex-col">
                        <span class="font-medium">{{ data.native }}</span>
                        <span v-if="data.name !== data.native" class="text-xs text-slate-500 dark:text-slate-400">
                            {{ data.name }}
                        </span>
                    </div>
                    <svg
                        v-if="code === currentLocale"
                        class="ml-auto h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </template>
    </Dropdown>
</template>
