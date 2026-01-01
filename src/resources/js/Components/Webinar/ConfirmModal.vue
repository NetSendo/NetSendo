<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmText: { type: String, default: 'Potwierdź' },
    cancelText: { type: String, default: 'Anuluj' },
    isDestructive: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[60] p-4 backdrop-blur-sm"
            @click.self="emit('close')"
        >
            <div class="bg-gray-800 rounded-xl max-w-md w-full p-6 shadow-xl border border-gray-700 transform transition-all scale-100 opacity-100">
                <h2 class="text-xl font-bold text-white mb-2">{{ title }}</h2>
                <p class="text-gray-300 mb-6">{{ message }}</p>

                <div class="flex justify-end gap-3">
                    <button
                        @click="emit('close')"
                        class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                    >
                        {{ cancelText }}
                    </button>
                    <button
                        @click="emit('confirm')"
                        :class="[
                            'px-4 py-2 rounded-lg font-medium transition-colors',
                            isDestructive
                                ? 'bg-red-600 hover:bg-red-700 text-white'
                                : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                        ]"
                    >
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
