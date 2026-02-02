<script setup>
/**
 * DnsRecordGenerator Component
 * One-click DNS record generation for DMARC and SPF
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useDnsGenerator } from '@/Composables/useDnsGenerator'

const props = defineProps({
    domainId: {
        type: [Number, String],
        required: true
    },
    showDmarc: {
        type: Boolean,
        default: true
    },
    showSpf: {
        type: Boolean,
        default: true
    },
    compact: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['recordCopied', 'error'])

const {
    loading,
    error,
    dmarcData,
    spfData,
    translations,
    copySuccess,
    fetchGenerators,
    copyToClipboard,
    dmarcInitialRecord,
    dmarcRecommendedRecord,
    dmarcCurrentPolicy,
    needsDmarcUpgrade,
    spfOptimalRecord,
    spfCurrentRecord,
    spfLookupCount,
    spfOptimalLookupCount,
    spfProviders,
    spfExceedsLimit,
    spfApproachingLimit,
} = useDnsGenerator(props.domainId)

const activeTab = ref('dmarc')
const showInstructions = ref(false)
const selectedDmarcLevel = ref('initial') // 'initial' | 'recommended' | 'minimal'

const currentDmarcRecord = computed(() => {
    if (!dmarcData.value) return ''

    switch (selectedDmarcLevel.value) {
        case 'recommended':
            return dmarcData.value.recommended?.value || ''
        case 'minimal':
            return dmarcData.value.minimal?.value || ''
        default:
            return dmarcData.value.initial?.value || ''
    }
})

const dmarcHost = computed(() => {
    if (!dmarcData.value) return ''
    return dmarcData.value.initial?.host_display || '_dmarc'
})

const spfHost = computed(() => {
    if (!spfData.value) return '@'
    return spfData.value.optimal?.host_display || '@'
})

async function handleCopy(text, type) {
    const success = await copyToClipboard(text, type)
    if (success) {
        emit('recordCopied', { type, value: text })
    }
}

function toggleInstructions() {
    showInstructions.value = !showInstructions.value
}

onMounted(() => {
    fetchGenerators()
})

// Watch for domain changes
watch(() => props.domainId, () => {
    fetchGenerators()
})
</script>

<template>
    <div class="dns-generator" :class="{ 'dns-generator--compact': compact }">
        <!-- Loading State -->
        <div v-if="loading" class="dns-generator__loading">
            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ $t('common.loading') }}</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="dns-generator__error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span>{{ error }}</span>
        </div>

        <!-- Generator Content -->
        <div v-else class="dns-generator__content">
            <!-- Tabs -->
            <div v-if="showDmarc && showSpf" class="dns-generator__tabs">
                <button
                    @click="activeTab = 'dmarc'"
                    :class="{ 'active': activeTab === 'dmarc' }"
                    class="dns-generator__tab"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    DMARC
                    <span v-if="needsDmarcUpgrade" class="dns-generator__badge dns-generator__badge--warning">!</span>
                </button>
                <button
                    @click="activeTab = 'spf'"
                    :class="{ 'active': activeTab === 'spf' }"
                    class="dns-generator__tab"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd" />
                    </svg>
                    SPF
                    <span v-if="spfExceedsLimit" class="dns-generator__badge dns-generator__badge--error">!</span>
                    <span v-else-if="spfApproachingLimit" class="dns-generator__badge dns-generator__badge--warning">!</span>
                </button>
            </div>

            <!-- DMARC Generator -->
            <div v-show="activeTab === 'dmarc' && showDmarc" class="dns-generator__panel">
                <div class="dns-generator__header">
                    <h4>{{ $t('deliverability.dmarc_generator.title') }}</h4>
                    <p class="dns-generator__subtitle">{{ $t('deliverability.dmarc_generator.subtitle') }}</p>
                </div>

                <!-- Current Policy Status -->
                <div v-if="dmarcCurrentPolicy" class="dns-generator__status">
                    <span class="dns-generator__status-label">{{ $t('deliverability.dmarc_generator.current_policy') }}:</span>
                    <span
                        class="dns-generator__status-value"
                        :class="{
                            'dns-generator__status-value--success': dmarcCurrentPolicy === 'reject',
                            'dns-generator__status-value--warning': dmarcCurrentPolicy === 'quarantine',
                            'dns-generator__status-value--error': dmarcCurrentPolicy === 'none'
                        }"
                    >
                        {{ dmarcCurrentPolicy }}
                    </span>
                </div>

                <!-- Policy Level Selector -->
                <div class="dns-generator__level-selector">
                    <label class="dns-generator__radio">
                        <input type="radio" v-model="selectedDmarcLevel" value="initial" />
                        <span class="dns-generator__radio-label">
                            <strong>Quarantine</strong> - {{ $t('deliverability.dmarc_generator.initial_explanation') }}
                        </span>
                    </label>
                    <label class="dns-generator__radio">
                        <input type="radio" v-model="selectedDmarcLevel" value="recommended" />
                        <span class="dns-generator__radio-label">
                            <strong>Reject</strong> - {{ $t('deliverability.dmarc_generator.recommended_explanation') }}
                        </span>
                    </label>
                </div>

                <!-- Generated Record -->
                <div class="dns-generator__record">
                    <div class="dns-generator__record-header">
                        <span class="dns-generator__record-type">TXT</span>
                        <span class="dns-generator__record-host">{{ dmarcHost }}</span>
                    </div>
                    <div class="dns-generator__record-value">
                        <code>{{ currentDmarcRecord }}</code>
                    </div>
                    <button
                        @click="handleCopy(currentDmarcRecord, 'dmarc')"
                        class="dns-generator__copy-btn"
                        :class="{ 'dns-generator__copy-btn--success': copySuccess === 'dmarc' }"
                    >
                        <svg v-if="copySuccess !== 'dmarc'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                            <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ copySuccess === 'dmarc' ? $t('deliverability.dns_generator.copy_success') : $t('deliverability.dmarc_generator.copy_record') }}
                    </button>
                </div>

                <!-- Upgrade Notice -->
                <div v-if="selectedDmarcLevel === 'initial'" class="dns-generator__notice">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ $t('deliverability.dmarc_generator.upgrade_notice') }}</span>
                </div>
            </div>

            <!-- SPF Generator -->
            <div v-show="activeTab === 'spf' && showSpf" class="dns-generator__panel">
                <div class="dns-generator__header">
                    <h4>{{ $t('deliverability.spf_generator.title') }}</h4>
                    <p class="dns-generator__subtitle">{{ $t('deliverability.spf_generator.subtitle') }}</p>
                </div>

                <!-- Lookup Count Status -->
                <div class="dns-generator__status">
                    <span class="dns-generator__status-label">{{ $t('deliverability.spf_generator.lookup_count') }}:</span>
                    <span
                        class="dns-generator__status-value"
                        :class="{
                            'dns-generator__status-value--success': spfLookupCount < 7,
                            'dns-generator__status-value--warning': spfApproachingLimit,
                            'dns-generator__status-value--error': spfExceedsLimit
                        }"
                    >
                        {{ spfLookupCount }} / 10
                    </span>
                </div>

                <!-- Warning if exceeds limit -->
                <div v-if="spfExceedsLimit || spfApproachingLimit" class="dns-generator__warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ $t('deliverability.spf_generator.lookup_warning') }}</span>
                </div>

                <!-- Provider Info -->
                <div v-if="spfProviders.length > 0" class="dns-generator__providers">
                    <span class="dns-generator__providers-label">{{ $t('deliverability.spf_generator.provider_detected') }}:</span>
                    <span class="dns-generator__providers-list">{{ spfProviders.join(', ') }}</span>
                </div>

                <!-- Current vs Optimal Comparison -->
                <div v-if="spfCurrentRecord" class="dns-generator__comparison">
                    <div class="dns-generator__comparison-item">
                        <span class="dns-generator__comparison-label">{{ $t('deliverability.spf_generator.current_record') }}:</span>
                        <code class="dns-generator__comparison-code">{{ spfCurrentRecord }}</code>
                    </div>
                </div>

                <!-- Generated Record -->
                <div class="dns-generator__record">
                    <div class="dns-generator__record-header">
                        <span class="dns-generator__record-type">TXT</span>
                        <span class="dns-generator__record-host">{{ spfHost }}</span>
                        <span class="dns-generator__record-badge">{{ $t('deliverability.spf_generator.optimal_record') }}</span>
                    </div>
                    <div class="dns-generator__record-value">
                        <code>{{ spfOptimalRecord }}</code>
                    </div>
                    <div class="dns-generator__record-meta">
                        <span>{{ $t('deliverability.spf_generator.lookup_count') }}: {{ spfOptimalLookupCount }}</span>
                    </div>
                    <button
                        @click="handleCopy(spfOptimalRecord, 'spf')"
                        class="dns-generator__copy-btn"
                        :class="{ 'dns-generator__copy-btn--success': copySuccess === 'spf' }"
                    >
                        <svg v-if="copySuccess !== 'spf'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                            <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        {{ copySuccess === 'spf' ? $t('deliverability.dns_generator.copy_success') : $t('deliverability.spf_generator.copy_record') }}
                    </button>
                </div>

                <!-- Explanation -->
                <p class="dns-generator__explanation">
                    {{ $t('deliverability.spf_generator.optimal_explanation') }}
                </p>
            </div>

            <!-- Instructions Toggle -->
            <div class="dns-generator__instructions-toggle">
                <button @click="toggleInstructions" class="dns-generator__instructions-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    {{ showInstructions ? $t('deliverability.dns_generator.hide_generator') : $t('deliverability.dns_generator.instructions_title') }}
                </button>
            </div>

            <!-- Instructions Panel -->
            <Transition name="slide">
                <div v-if="showInstructions" class="dns-generator__instructions">
                    <h5>{{ $t('deliverability.dns_generator.instructions_title') }}</h5>
                    <ol>
                        <li>{{ $t('deliverability.dns_generator.step1') }}</li>
                        <li>{{ $t('deliverability.dns_generator.step2') }}</li>
                        <li>{{ $t('deliverability.dns_generator.step3') }}</li>
                    </ol>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.dns-generator {
    background: var(--bg-secondary, #1a1a2e);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color, rgba(255,255,255,0.1));
}

.dns-generator--compact {
    padding: 1rem;
}

.dns-generator__loading,
.dns-generator__error {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    color: var(--text-muted, #888);
}

.dns-generator__error {
    color: var(--error, #ef4444);
}

.dns-generator__tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.1));
    padding-bottom: 0.75rem;
}

.dns-generator__tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    color: var(--text-muted, #888);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-weight: 500;
}

.dns-generator__tab:hover {
    background: var(--bg-hover, rgba(255,255,255,0.05));
    color: var(--text-primary, #fff);
}

.dns-generator__tab.active {
    background: var(--primary, #6366f1);
    color: white;
}

.dns-generator__badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
}

.dns-generator__badge--warning {
    background: var(--warning, #f59e0b);
    color: #000;
}

.dns-generator__badge--error {
    background: var(--error, #ef4444);
    color: #fff;
}

.dns-generator__header {
    margin-bottom: 1rem;
}

.dns-generator__header h4 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.25rem;
    color: var(--text-primary, #fff);
}

.dns-generator__subtitle {
    font-size: 0.8rem;
    color: var(--text-muted, #888);
    margin: 0;
}

.dns-generator__status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.dns-generator__status-label {
    color: var(--text-muted, #888);
}

.dns-generator__status-value {
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-transform: uppercase;
    font-size: 0.75rem;
}

.dns-generator__status-value--success {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.dns-generator__status-value--warning {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.dns-generator__status-value--error {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.dns-generator__level-selector {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.dns-generator__radio {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    cursor: pointer;
}

.dns-generator__radio input {
    margin-top: 0.25rem;
}

.dns-generator__radio-label {
    font-size: 0.8rem;
    color: var(--text-secondary, #b0b0b0);
    line-height: 1.4;
}

.dns-generator__radio-label strong {
    color: var(--text-primary, #fff);
}

.dns-generator__record {
    background: var(--bg-tertiary, #0f0f1a);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.dns-generator__record-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.dns-generator__record-type {
    background: var(--primary, #6366f1);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.dns-generator__record-host {
    font-family: monospace;
    font-size: 0.8rem;
    color: var(--text-secondary, #b0b0b0);
}

.dns-generator__record-badge {
    margin-left: auto;
    font-size: 0.65rem;
    color: var(--success, #22c55e);
    font-weight: 500;
}

.dns-generator__record-value {
    margin-bottom: 0.75rem;
}

.dns-generator__record-value code {
    display: block;
    font-family: 'Fira Code', monospace;
    font-size: 0.75rem;
    color: var(--text-primary, #fff);
    background: var(--bg-primary, #0a0a14);
    padding: 0.75rem;
    border-radius: 6px;
    word-break: break-all;
    line-height: 1.5;
}

.dns-generator__record-meta {
    font-size: 0.7rem;
    color: var(--text-muted, #888);
    margin-bottom: 0.75rem;
}

.dns-generator__copy-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem;
    background: var(--primary, #6366f1);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dns-generator__copy-btn:hover {
    background: var(--primary-dark, #4f46e5);
}

.dns-generator__copy-btn--success {
    background: var(--success, #22c55e);
}

.dns-generator__notice,
.dns-generator__warning {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.8rem;
    line-height: 1.4;
}

.dns-generator__notice {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary, #6366f1);
}

.dns-generator__warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning, #f59e0b);
    margin-bottom: 1rem;
}

.dns-generator__providers {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    margin-bottom: 1rem;
}

.dns-generator__providers-label {
    color: var(--text-muted, #888);
}

.dns-generator__providers-list {
    color: var(--primary, #6366f1);
    font-weight: 500;
}

.dns-generator__comparison {
    background: var(--bg-tertiary, #0f0f1a);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.dns-generator__comparison-label {
    display: block;
    font-size: 0.7rem;
    color: var(--text-muted, #888);
    margin-bottom: 0.25rem;
}

.dns-generator__comparison-code {
    font-size: 0.7rem;
    color: var(--text-secondary, #b0b0b0);
    word-break: break-all;
}

.dns-generator__explanation {
    font-size: 0.8rem;
    color: var(--text-muted, #888);
    margin: 0;
    line-height: 1.4;
}

.dns-generator__instructions-toggle {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color, rgba(255,255,255,0.1));
}

.dns-generator__instructions-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: transparent;
    border: none;
    color: var(--text-muted, #888);
    font-size: 0.8rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.dns-generator__instructions-btn:hover {
    color: var(--text-primary, #fff);
    background: var(--bg-hover, rgba(255,255,255,0.05));
}

.dns-generator__instructions {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--bg-tertiary, #0f0f1a);
    border-radius: 8px;
}

.dns-generator__instructions h5 {
    font-size: 0.875rem;
    margin: 0 0 0.75rem;
    color: var(--text-primary, #fff);
}

.dns-generator__instructions ol {
    margin: 0;
    padding-left: 1.25rem;
    font-size: 0.8rem;
    color: var(--text-secondary, #b0b0b0);
    line-height: 1.6;
}

/* Transitions */
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
    transform: translateY(-10px);
}

.slide-enter-to,
.slide-leave-from {
    opacity: 1;
    max-height: 500px;
    transform: translateY(0);
}
</style>
