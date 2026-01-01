<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/Webinar/ConfirmModal.vue';
import GenerateScriptsModal from '@/Pages/Webinars/Partials/GenerateScriptsModal.vue';

const props = defineProps({
    webinar: { type: Object, required: true },
    scripts: { type: Array, default: () => [] },
    messageTypes: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const scripts = ref(props.scripts);
const isLoading = ref(false);
const editingScript = ref(null);
const showAddForm = ref(false);
const isGenerating = ref(false);

// Modals
const showGenerateModal = ref(false);
const showClearModal = ref(false);
const scriptToDelete = ref(null);

// Form state
const form = ref({
    sender_name: '',
    message_text: '',
    message_type: 'comment',
    show_at_seconds: 0,
    show_at_minutes: 0,
    reaction_count: 0,
    delay_variance_seconds: 5,
    show_randomly: false,
});

const duration = computed(() => props.webinar.duration_minutes ? props.webinar.duration_minutes * 60 : 3600);

const typeOptions = computed(() => [
    { value: 'comment', label: `💬 ${t('webinars.scenario.types.comment')}`, color: 'gray' },
    { value: 'question', label: `❓ ${t('webinars.scenario.types.question')}`, color: 'blue' },
    { value: 'reaction', label: `🔥 ${t('webinars.scenario.types.reaction')}`, color: 'orange' },
    { value: 'testimonial', label: `⭐ ${t('webinars.scenario.types.testimonial')}`, color: 'yellow' },
    { value: 'excitement', label: `🎉 ${t('webinars.scenario.types.excitement')}`, color: 'green' },
]);

const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

const groupedScripts = computed(() => {
    const groups = {};
    scripts.value.forEach(script => {
        const segment = Math.floor(script.show_at_seconds / 300) * 5; // 5-minute segments
        const key = `${segment}-min`;
        if (!groups[key]) {
            groups[key] = {
                label: `${segment}-${segment + 5} min`,
                startSeconds: segment * 60,
                scripts: [],
            };
        }
        groups[key].scripts.push(script);
    });
    return Object.values(groups).sort((a, b) => a.startSeconds - b.startSeconds);
});

const saveScript = async () => {
    isLoading.value = true;
    const totalSeconds = (form.value.show_at_minutes * 60) + form.value.show_at_seconds;

    try {
        if (editingScript.value) {
            const { data } = await axios.put(
                `/webinars/${props.webinar.id}/scripts/${editingScript.value.id}`,
                { ...form.value, show_at_seconds: totalSeconds }
            );
            const index = scripts.value.findIndex(s => s.id === editingScript.value.id);
            if (index !== -1) {
                scripts.value[index] = data.script;
            }
        } else {
            const { data } = await axios.post(
                `/webinars/${props.webinar.id}/scripts`,
                { ...form.value, show_at_seconds: totalSeconds }
            );
            scripts.value.push(data.script);
        }
        closeForm();
    } catch (error) {
        console.error('Failed to save script:', error);
    } finally {
        isLoading.value = false;
    }
};

const editScript = (script) => {
    editingScript.value = script;
    form.value = {
        sender_name: script.sender_name,
        message_text: script.message_text,
        message_type: script.message_type,
        show_at_seconds: script.show_at_seconds % 60,
        show_at_minutes: Math.floor(script.show_at_seconds / 60),
        reaction_count: script.reaction_count,
        delay_variance_seconds: script.delay_variance_seconds,
        show_randomly: script.show_randomly,
    };
    showAddForm.value = true;
};

const deleteScript = (script) => {
    scriptToDelete.value = script;
};

const confirmDeleteScript = async () => {
    if (!scriptToDelete.value) return;

    try {
        await axios.delete(`/webinars/${props.webinar.id}/scripts/${scriptToDelete.value.id}`);
        scripts.value = scripts.value.filter(s => s.id !== scriptToDelete.value.id);
    } catch (error) {
        console.error('Failed to delete script:', error);
    } finally {
        scriptToDelete.value = null;
    }
};

const duplicateScript = async (script) => {
    try {
        const { data } = await axios.post(`/webinars/${props.webinar.id}/scripts/${script.id}/duplicate`);
        scripts.value.push(data.script);
        scripts.value.sort((a, b) => a.show_at_seconds - b.show_at_seconds);
    } catch (error) {
        console.error('Failed to duplicate script:', error);
    }
};

const generateScripts = async (options) => {
    isGenerating.value = true;
    try {
        await axios.post(`/webinars/${props.webinar.id}/scripts/generate`, options);
        // Reload scripts
        const { data } = await axios.get(`/webinars/${props.webinar.id}/scripts`);
        scripts.value = data.scripts;
        showGenerateModal.value = false;
    } catch (error) {
        console.error('Failed to generate scripts:', error);
    } finally {
        isGenerating.value = false;
    }
};

const confirmClearAll = async () => {
    try {
        await axios.delete(`/webinars/${props.webinar.id}/scripts/clear`);
        scripts.value = [];
        showClearModal.value = false;
    } catch (error) {
        console.error('Failed to clear scripts:', error);
    }
};

const closeForm = () => {
    showAddForm.value = false;
    editingScript.value = null;
    form.value = {
        sender_name: '',
        message_text: '',
        message_type: 'comment',
        show_at_seconds: 0,
        show_at_minutes: 0,
        reaction_count: 0,
        delay_variance_seconds: 5,
        show_randomly: false,
    };
};

const getTypeColor = (type) => {
    const colors = {
        comment: 'bg-gray-500/20 text-gray-300',
        question: 'bg-blue-500/20 text-blue-300',
        reaction: 'bg-orange-500/20 text-orange-300',
        testimonial: 'bg-yellow-500/20 text-yellow-300',
        excitement: 'bg-green-500/20 text-green-300',
    };
    return colors[type] || colors.comment;
};
</script>

<template>
    <Head :title="`${t('webinars.scenario.title')} - ${webinar.name}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto py-6 px-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ $t('webinars.scenario.title') }}</h1>
                    <p class="text-gray-400">{{ webinar.name }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="showGenerateModal = true"
                        :disabled="isGenerating"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 rounded-lg text-white text-sm font-medium transition-colors flex items-center gap-2"
                    >
                        <span v-if="isGenerating" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span v-else>🎲</span>
                        {{ $t('webinars.scenario.generate_random') }}
                    </button>

                    <button
                        @click="showAddForm = true"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-white text-sm font-medium transition-colors flex items-center gap-2"
                    >
                        <span>+</span>
                        {{ $t('webinars.scenario.add_message') }}
                    </button>

                    <button
                        v-if="scripts.length > 0"
                        @click="showClearModal = true"
                        class="px-4 py-2 bg-red-600/20 hover:bg-red-600/30 border border-red-600/50 rounded-lg text-red-400 text-sm font-medium transition-colors"
                    >
                    >
                        {{ $t('webinars.scenario.clear') }}
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-800 rounded-lg p-4">
                    <p class="text-2xl font-bold text-white">{{ scripts.length }}</p>
                    <p class="text-sm text-gray-400">{{ $t('webinars.scenario.messages') }}</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4">
                    <p class="text-2xl font-bold text-white">{{ Math.round(scripts.length / ((webinar.duration_minutes || 60))) }}/min</p>
                    <p class="text-sm text-gray-400">{{ $t('webinars.scenario.avg_density') }}</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4">
                    <p class="text-2xl font-bold text-white">{{ scripts.filter(s => s.message_type === 'question').length }}</p>
                    <p class="text-sm text-gray-400">{{ $t('webinars.scenario.questions') }}</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4">
                    <p class="text-2xl font-bold text-white">{{ scripts.filter(s => s.message_type === 'testimonial').length }}</p>
                    <p class="text-sm text-gray-400">{{ $t('webinars.scenario.testimonials') }}</p>
                </div>
            </div>

            <!-- Timeline -->
            <div class="bg-gray-800 rounded-xl overflow-hidden">
                <div v-if="scripts.length === 0" class="p-12 text-center">
                    <p class="text-gray-400 mb-4">{{ $t('webinars.scenario.no_messages') }}</p>
                    <button
                        @click="showAddForm = true"
                        class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-lg text-white font-medium transition-colors"
                    >
                        {{ $t('webinars.scenario.add_first') }}
                    </button>
                </div>

                <div v-else class="divide-y divide-gray-700">
                    <div v-for="group in groupedScripts" :key="group.label" class="p-4">
                        <h3 class="text-sm font-medium text-gray-400 mb-3">{{ group.label }}</h3>

                        <div class="space-y-2">
                            <div
                                v-for="script in group.scripts"
                                :key="script.id"
                                class="flex items-center gap-4 p-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition-colors group"
                            >
                                <!-- Time -->
                                <div class="w-16 text-center">
                                    <span class="text-sm font-mono text-indigo-400">
                                        {{ script.formatted_time }}
                                    </span>
                                </div>

                                <!-- Avatar -->
                                <img
                                    :src="script.avatar_url"
                                    :alt="script.sender_name"
                                    class="w-8 h-8 rounded-full"
                                />

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-white">{{ script.sender_name }}</span>
                                        <span :class="['px-2 py-0.5 rounded text-xs', getTypeColor(script.message_type)]">
                                            {{ typeOptions.find(t => t.value === script.message_type)?.label.charAt(0) }}
                                            {{ script.message_type }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-300 truncate">{{ script.message_text }}</p>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        @click="duplicateScript(script)"
                                        class="p-1.5 text-gray-400 hover:text-white transition-colors"
                                        title="Duplikuj"
                                    >
                                        📋
                                    </button>
                                    <button
                                        @click="editScript(script)"
                                        class="p-1.5 text-gray-400 hover:text-white transition-colors"
                                        title="Edytuj"
                                    >
                                        ✏️
                                    </button>
                                    <button
                                        @click="deleteScript(script)"
                                        class="p-1.5 text-gray-400 hover:text-red-400 transition-colors"
                                        title="Usuń"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <div
                v-if="showAddForm"
                class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
                @click.self="closeForm"
            >
                <div class="bg-gray-800 rounded-xl max-w-lg w-full p-6">
                    <h2 class="text-xl font-bold text-white mb-4">
                        {{ editingScript ? $t('webinars.scenario.modal.edit_title') : $t('webinars.scenario.modal.add_title') }}
                    </h2>

                    <form @submit.prevent="saveScript" class="space-y-4">
                        <!-- Sender name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.sender_name') }}</label>
                            <input
                                v-model="form.sender_name"
                                type="text"
                                required
                                class="w-full bg-gray-700 border-gray-600 rounded-lg text-white focus:ring-indigo-500"
                                placeholder="np. Anna Kowalska"
                            />
                        </div>

                        <!-- Message -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.message_text') }}</label>
                            <textarea
                                v-model="form.message_text"
                                required
                                rows="3"
                                maxlength="500"
                                class="w-full bg-gray-700 border-gray-600 rounded-lg text-white focus:ring-indigo-500"
                                :placeholder="$t('webinars.chat_panel.announcement_placeholder')"
                            ></textarea>
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.message_type') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in typeOptions"
                                    :key="type.value"
                                    type="button"
                                    @click="form.message_type = type.value"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-sm transition-all',
                                        form.message_type === type.value
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                                    ]"
                                >
                                    {{ type.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.minute') }}</label>
                                <input
                                    v-model.number="form.show_at_minutes"
                                    type="number"
                                    min="0"
                                    :max="Math.floor(duration / 60)"
                                    class="w-full bg-gray-700 border-gray-600 rounded-lg text-white focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.second') }}</label>
                                <input
                                    v-model.number="form.show_at_seconds"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="w-full bg-gray-700 border-gray-600 rounded-lg text-white focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Advanced options -->
                        <details class="text-gray-400">
                            <summary class="cursor-pointer text-sm hover:text-white">{{ $t('webinars.scenario.modal.advanced') }}</summary>
                            <div class="mt-3 space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.variance') }}</label>
                                    <input
                                        v-model.number="form.delay_variance_seconds"
                                        type="range"
                                        min="0"
                                        max="30"
                                        class="w-full"
                                    />
                                    <span class="text-xs">{{ form.delay_variance_seconds }} {{ $t('webinars.chat_panel.seconds') }}</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('webinars.scenario.modal.reactions') }}</label>
                                    <input
                                        v-model.number="form.reaction_count"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-full bg-gray-700 border-gray-600 rounded-lg text-white focus:ring-indigo-500"
                                    />
                                </div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.show_randomly"
                                        type="checkbox"
                                        class="rounded border-gray-600 text-indigo-500 focus:ring-indigo-500 bg-gray-700"
                                    />
                                    <span class="text-sm text-gray-300">{{ $t('webinars.scenario.modal.randomly') }}</span>
                                </label>
                            </div>
                        </details>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                            <button
                                type="button"
                                @click="closeForm"
                                class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                            >
                                {{ $t('webinars.scenario.modal.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="isLoading"
                                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg text-white font-medium transition-colors"
                            >
                                {{ isLoading ? $t('webinars.chat_panel.saving') : (editingScript ? $t('webinars.scenario.modal.save') : $t('webinars.scenario.modal.add')) }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>

    <!-- Modals -->
    <ConfirmModal
        :show="!!scriptToDelete"
        :title="$t('webinars.scenario.modal.delete_title')"
        :message="$t('webinars.scenario.modal.delete_confirm')"
        :confirm-text="$t('webinars.scenario.modal.delete')"
        :is-destructive="true"
        @close="scriptToDelete = null"
        @confirm="confirmDeleteScript"
    />

    <ConfirmModal
        :show="showClearModal"
        :title="$t('webinars.scenario.modal.clear_title')"
        :message="$t('webinars.scenario.modal.clear_confirm')"
        :confirm-text="$t('webinars.scenario.modal.clear_all')"
        :is-destructive="true"
        @close="showClearModal = false"
        @confirm="confirmClearAll"
    />

    <GenerateScriptsModal
        :show="showGenerateModal"
        :is-generating="isGenerating"
        :duration-minutes="webinar.duration_minutes || 60"
        @close="showGenerateModal = false"
        @generate="generateScripts"
    />
</template>
