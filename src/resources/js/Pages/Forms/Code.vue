<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    form: Object,
    embedCodes: Object,
});

const activeTab = ref('html');
const copied = ref(false);

const tabs = [
    { id: 'html', label: 'HTML', icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
    { id: 'js', label: 'JavaScript', icon: 'M12 3v18m9-9H3' },
    { id: 'iframe', label: 'iFrame', icon: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z' },
];

const currentCode = computed(() => {
    return props.embedCodes[activeTab.value] || '';
});

function copyCode() {
    navigator.clipboard.writeText(currentCode.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
}

function downloadCode() {
    const blob = new Blob([currentCode.value], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `netsendo-form-${props.form.slug}.${activeTab.value === 'js' ? 'js' : 'html'}`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head :title="t('forms.code.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link 
                    :href="route('forms.edit', form.id)"
                    class="btn-icon"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ t('forms.code.title') }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ form.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Status warning -->
                <div 
                    v-if="form.status !== 'active'" 
                    class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg flex items-start gap-3"
                >
                    <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="font-medium text-yellow-800 dark:text-yellow-200">{{ t('forms.code.inactive_warning') }}</p>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">{{ t('forms.code.inactive_hint') }}</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <div class="flex border-b border-gray-200 dark:border-gray-700">
                        <button 
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'flex-1 flex items-center justify-center gap-2 py-4 text-sm font-medium transition-colors',
                                activeTab === tab.id 
                                    ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400 bg-indigo-50/50 dark:bg-indigo-900/10' 
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
                            </svg>
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Code content -->
                    <div class="p-6">
                        <!-- Description -->
                        <div class="mb-4">
                            <p v-if="activeTab === 'html'" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ t('forms.code.html_desc') }}
                            </p>
                            <p v-else-if="activeTab === 'js'" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ t('forms.code.js_desc') }}
                            </p>
                            <p v-else class="text-sm text-gray-600 dark:text-gray-400">
                                {{ t('forms.code.iframe_desc') }}
                            </p>
                        </div>

                        <!-- Code block -->
                        <div class="relative">
                            <pre class="bg-gray-900 text-gray-100 rounded-lg p-4 overflow-x-auto text-sm font-mono"><code>{{ currentCode }}</code></pre>
                            
                            <!-- Copy button -->
                            <div class="absolute top-3 right-3 flex gap-2">
                                <button 
                                    @click="downloadCode"
                                    class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
                                    :title="t('common.download')"
                                >
                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </button>
                                <button 
                                    @click="copyCode"
                                    class="p-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors flex items-center gap-1.5"
                                    :title="t('common.copy')"
                                >
                                    <svg v-if="!copied" class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-xs text-gray-300">{{ copied ? t('common.copied') : t('common.copy') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integration guides -->
                <div class="mt-8 grid md:grid-cols-2 gap-6">
                    <!-- WordPress -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/40 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M21.469 6.825c.84 1.537 1.318 3.3 1.318 5.175 0 3.979-2.156 7.456-5.363 9.325l3.295-9.527c.615-1.54.82-2.771.82-3.864 0-.397-.027-.765-.07-1.109m-7.981.105c.647-.034 1.231-.102 1.231-.102.579-.068.51-.921-.069-.888 0 0-1.74.137-2.864.137-1.058 0-2.838-.137-2.838-.137-.579-.033-.648.855-.068.888 0 0 .548.068 1.127.102l1.671 4.582-2.348 7.041-3.91-11.623c.647-.034 1.231-.102 1.231-.102.579-.068.51-.921-.069-.888 0 0-1.74.137-2.864.137-.201 0-.440-.005-.696-.013C5.058 2.876 8.324.498 12 .498c2.723 0 5.207 1.039 7.07 2.741-.045-.003-.089-.007-.134-.007-1.058 0-1.81.921-1.81 1.909 0 .888.513 1.639.106 2.528-.408 1.16-1.574 3.455-1.574 3.455l-1.95 6.526-5.43-16.166m5.962 17.68c-.032.012-.064.025-.097.037-.032.012-.064.024-.096.035l-2.135 6.173c1.023.339 2.111.527 3.246.527 1.341 0 2.621-.257 3.797-.72l-.03-.093-4.685-5.959m-8.9-.593c-3.065-1.88-5.114-5.283-5.114-9.181 0-1.757.425-3.418 1.177-4.881l3.25 8.908.687 1.881.697 1.913.303.878.303-.878"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">WordPress</h3>
                        </div>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-decimal list-inside">
                            <li>{{ t('forms.code.wp_step1') }}</li>
                            <li>{{ t('forms.code.wp_step2') }}</li>
                            <li>{{ t('forms.code.wp_step3') }}</li>
                        </ol>
                    </div>

                    <!-- Elementor -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-pink-100 dark:bg-pink-900/40 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm-1.7 18H7.5V6h2.8v12zm6.4 0h-2.8v-5.5h2.8V18zm0-7.3h-5.6V6h5.6v4.7z"/>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Elementor</h3>
                        </div>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-2 list-decimal list-inside">
                            <li>{{ t('forms.code.elementor_step1') }}</li>
                            <li>{{ t('forms.code.elementor_step2') }}</li>
                            <li>{{ t('forms.code.elementor_step3') }}</li>
                        </ol>
                    </div>
                </div>

                <!-- Webhook info -->
                <div class="mt-6 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-2">{{ t('forms.code.webhook_title') }}</h3>
                            <p class="text-white/80 text-sm mb-3">{{ t('forms.code.webhook_desc') }}</p>
                            <Link 
                                :href="route('forms.edit', form.id) + '#integrations'"
                                class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                            >
                                {{ t('forms.code.configure_webhooks') }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.btn-icon {
    @apply p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors;
}
</style>
