<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    isGenerating: { type: Boolean, default: false },
    durationMinutes: { type: Number, default: 60 },
});

const emit = defineEmits(['close', 'generate']);

const form = ref({
    density: 2,
    include_questions: true,
    include_testimonials: true,
    include_excitement: true,
});

const handleSubmit = () => {
    emit('generate', { ...form.value, duration_minutes: props.durationMinutes });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[60] p-4 backdrop-blur-sm"
            @click.self="!isGenerating && emit('close')"
        >
            <div class="bg-gray-800 rounded-xl max-w-md w-full p-6 shadow-xl border border-gray-700">
                <h2 class="text-xl font-bold text-white mb-4">Generuj losowy scenariusz</h2>

                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <!-- Density -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">
                            Gęstość (wiadomości na minutę)
                        </label>
                        <div class="flex items-center gap-4">
                            <input
                                :value="form.density"
                                @input="form.density = parseFloat($event.target.value)"
                                type="range"
                                :min="0.5"
                                :max="10"
                                :step="0.5"
                                class="flex-1 accent-purple-600"
                            />
                            <span class="text-white font-mono w-12 text-right">{{ form.density.toFixed(1) }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Szacowana liczba wiadomości: ~{{ Math.round(form.density * durationMinutes) }}
                        </p>
                    </div>

                    <!-- Options -->
                    <div class="space-y-2 bg-gray-700/30 p-3 rounded-lg">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.include_questions"
                                type="checkbox"
                                class="rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                            />
                            <span class="text-sm text-gray-300">Generuj pytania</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.include_testimonials"
                                type="checkbox"
                                class="rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                            />
                            <span class="text-sm text-gray-300">Generuj opinie/referencje</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.include_excitement"
                                type="checkbox"
                                class="rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                            />
                            <span class="text-sm text-gray-300">Generuj reakcje entuzjazmu</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                        <button
                            type="button"
                            @click="emit('close')"
                            :disabled="isGenerating"
                            class="px-4 py-2 text-gray-400 hover:text-white transition-colors disabled:opacity-50"
                        >
                            Anuluj
                        </button>
                        <button
                            type="submit"
                            :disabled="isGenerating"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50 flex items-center gap-2"
                        >
                            <span v-if="isGenerating" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            {{ isGenerating ? 'Generowanie...' : 'Generuj' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
