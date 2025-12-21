<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    systemVariables: {
        type: Array,
        default: () => [],
    },
    inserts: {
        type: Array,
        default: () => [],
    },
    signatures: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'insert'])

// Active tab: variables, inserts, signatures
const activeTab = ref('variables')

// Search query
const searchQuery = ref('')

// Filtered system variables
const filteredVariables = computed(() => {
    if (!searchQuery.value) return props.systemVariables
    
    const query = searchQuery.value.toLowerCase()
    return props.systemVariables.map(category => ({
        ...category,
        variables: category.variables.filter(v => 
            v.label.toLowerCase().includes(query) ||
            v.code.toLowerCase().includes(query) ||
            (v.description && v.description.toLowerCase().includes(query))
        )
    })).filter(category => category.variables.length > 0)
})

// Filtered inserts
const filteredInserts = computed(() => {
    if (!searchQuery.value) return props.inserts
    
    const query = searchQuery.value.toLowerCase()
    return props.inserts.filter(insert =>
        insert.name.toLowerCase().includes(query) ||
        (insert.description && insert.description.toLowerCase().includes(query))
    )
})

// Filtered signatures
const filteredSignatures = computed(() => {
    if (!searchQuery.value) return props.signatures
    
    const query = searchQuery.value.toLowerCase()
    return props.signatures.filter(sig =>
        sig.name.toLowerCase().includes(query) ||
        (sig.description && sig.description.toLowerCase().includes(query))
    )
})

// Handle insert
const handleInsert = (content) => {
    emit('insert', content)
    emit('close')
}

// Handle variable click
const insertVariable = (code) => {
    handleInsert(code)
}

// Handle insert/signature click
const insertTemplate = (template) => {
    // Use content or content_plain if available
    const content = template.content || template.content_plain || ''
    handleInsert(content)
}

// Reset search when tab changes
const switchTab = (tab) => {
    activeTab.value = tab
    searchQuery.value = ''
}

// Clear search
const clearSearch = () => {
    searchQuery.value = ''
}
</script>

<template>
    <!-- Modal Backdrop -->
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4"
            @click.self="$emit('close')"
        >
            <!-- Modal Content -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="show"
                    class="relative w-full max-w-2xl max-h-[80vh] flex flex-col bg-white dark:bg-slate-800 rounded-xl shadow-2xl"
                    @click.stop
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-6 py-4">
                        <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            {{ $t('inserts.picker_title') }}
                        </h3>
                        <button
                            @click="$emit('close')"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-slate-200 dark:border-slate-700 px-6">
                        <nav class="flex gap-2">
                            <button
                                @click="switchTab('variables')"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                                    activeTab === 'variables'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                                ]"
                            >
                                {{ $t('inserts.tabs.variables') }}
                            </button>
                            <button
                                @click="switchTab('inserts')"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                                    activeTab === 'inserts'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                                ]"
                            >
                                {{ $t('inserts.tabs.inserts') }}
                                <span v-if="inserts.length" class="ml-1.5 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs dark:bg-slate-700">
                                    {{ inserts.length }}
                                </span>
                            </button>
                            <button
                                @click="switchTab('signatures')"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                                    activeTab === 'signatures'
                                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                                ]"
                            >
                                {{ $t('inserts.tabs.signatures') }}
                                <span v-if="signatures.length" class="ml-1.5 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs dark:bg-slate-700">
                                    {{ signatures.length }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- Search Bar -->
                    <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="$t('inserts.search')"
                                class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white dark:placeholder-slate-500"
                            />
                            <button
                                v-if="searchQuery"
                                @click="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <!-- System Variables Tab -->
                        <div v-if="activeTab === 'variables'">
                            <div v-if="filteredVariables.length === 0" class="py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.no_results') }}</p>
                            </div>
                            
                            <div v-else class="space-y-6">
                                <div v-for="category in filteredVariables" :key="category.category">
                                    <h4 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ category.label }}
                                    </h4>
                                    <div class="space-y-2">
                                        <button
                                            v-for="variable in category.variables"
                                            :key="variable.code"
                                            @click="insertVariable(variable.code)"
                                            class="w-full rounded-lg border border-slate-200 bg-white p-3 text-left transition-all hover:border-indigo-500 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <code class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono text-indigo-600 dark:bg-slate-700 dark:text-indigo-400">
                                                            {{ variable.code }}
                                                        </code>
                                                        <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                            {{ variable.label }}
                                                        </span>
                                                    </div>
                                                    <p v-if="variable.description" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ variable.description }}
                                                    </p>
                                                </div>
                                                <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inserts Tab -->
                        <div v-if="activeTab === 'inserts'">
                            <div v-if="filteredInserts.length === 0" class="py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ searchQuery ? $t('inserts.no_results') : $t('inserts.no_inserts') }}
                                </p>
                            </div>
                            
                            <div v-else class="space-y-2">
                                <button
                                    v-for="insert in filteredInserts"
                                    :key="insert.id"
                                    @click="insertTemplate(insert)"
                                    class="w-full rounded-lg border border-slate-200 bg-white p-4 text-left transition-all hover:border-indigo-500 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ insert.name }}
                                                </span>
                                            </div>
                                            <p v-if="insert.description" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ insert.description }}
                                            </p>
                                        </div>
                                        <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Signatures Tab -->
                        <div v-if="activeTab === 'signatures'">
                            <div v-if="filteredSignatures.length === 0" class="py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ searchQuery ? $t('inserts.no_results') : $t('inserts.no_signatures') }}
                                </p>
                            </div>
                            
                            <div v-else class="space-y-2">
                                <button
                                    v-for="signature in filteredSignatures"
                                    :key="signature.id"
                                    @click="insertTemplate(signature)"
                                    class="w-full rounded-lg border border-slate-200 bg-white p-4 text-left transition-all hover:border-indigo-500 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ signature.name }}
                                                </span>
                                            </div>
                                            <p v-if="signature.description" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ signature.description }}
                                            </p>
                                        </div>
                                        <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
