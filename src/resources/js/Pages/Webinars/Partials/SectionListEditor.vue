<script setup>
// Editable list of funnel-page content sections (text / YouTube / Vimeo).
// Mutates the passed array in place so it stays bound to the parent useForm.
const props = defineProps({
    sections: { type: Array, required: true },
    showPlacement: { type: Boolean, default: false },
});

const addSection = () => {
    props.sections.push({ type: 'text', title: '', body: '', video_url: '', placement: 'above_form' });
};

const removeSection = (index) => {
    props.sections.splice(index, 1);
};

const moveSection = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= props.sections.length) return;
    const [moved] = props.sections.splice(index, 1);
    props.sections.splice(target, 0, moved);
};
</script>

<template>
    <div class="space-y-3">
        <p v-if="sections.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('webinars.sections.empty') }}
        </p>

        <div
            v-for="(section, index) in sections"
            :key="index"
            class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4 space-y-3"
        >
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-model="section.type"
                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                    <option value="text">{{ $t('webinars.sections.type_text') }}</option>
                    <option value="video">{{ $t('webinars.sections.type_video') }}</option>
                </select>

                <select
                    v-if="showPlacement"
                    v-model="section.placement"
                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                    <option value="above_form">{{ $t('webinars.sections.placement_above') }}</option>
                    <option value="below_form">{{ $t('webinars.sections.placement_below') }}</option>
                </select>

                <div class="ml-auto flex items-center gap-1">
                    <button
                        type="button"
                        @click="moveSection(index, -1)"
                        :disabled="index === 0"
                        :title="$t('webinars.sections.move_up')"
                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="moveSection(index, 1)"
                        :disabled="index === sections.length - 1"
                        :title="$t('webinars.sections.move_down')"
                        class="p-1.5 rounded-md text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="removeSection(index)"
                        :title="$t('webinars.sections.remove')"
                        class="p-1.5 rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.sections.title') }}</label>
                <input
                    v-model="section.title"
                    type="text"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div v-if="section.type === 'text'">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.sections.body') }}</label>
                <textarea
                    v-model="section.body"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div v-else>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('webinars.sections.video_url') }}</label>
                <input
                    v-model="section.video_url"
                    type="text"
                    placeholder="https://www.youtube.com/watch?v=... / https://vimeo.com/123456789"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <p class="mt-1 text-xs text-gray-500">{{ $t('webinars.sections.video_url_help') }}</p>
            </div>
        </div>

        <button
            type="button"
            @click="addSection"
            class="inline-flex items-center gap-1 px-3 py-2 border border-dashed border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-600 dark:text-gray-300 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ $t('webinars.sections.add') }}
        </button>
    </div>
</template>
