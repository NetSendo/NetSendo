/**
 * Composable for DNS Record Generators (DMARC & SPF)
 * Provides one-click copy functionality and generator state management
 */
import { ref, computed } from 'vue'
import axios from 'axios'

export function useDnsGenerator(domainId) {
    const loading = ref(false)
    const error = ref(null)
    const dmarcData = ref(null)
    const spfData = ref(null)
    const translations = ref({})
    const copySuccess = ref(null)

    /**
     * Fetch DNS generator data for a domain
     */
    async function fetchGenerators() {
        loading.value = true
        error.value = null

        try {
            const response = await axios.get(route('deliverability.domains.dns-generators', domainId))

            if (response.data.success) {
                dmarcData.value = response.data.dmarc
                spfData.value = response.data.spf
                translations.value = response.data.translations
            }
        } catch (err) {
            error.value = err.response?.data?.message || 'Failed to load DNS generators'
            console.error('DNS Generator Error:', err)
        } finally {
            loading.value = false
        }
    }

    /**
     * Copy text to clipboard with success feedback
     */
    async function copyToClipboard(text, recordType = 'dns') {
        try {
            await navigator.clipboard.writeText(text)
            copySuccess.value = recordType

            // Clear success message after 2 seconds
            setTimeout(() => {
                copySuccess.value = null
            }, 2000)

            return true
        } catch (err) {
            console.error('Failed to copy:', err)
            // Fallback for older browsers
            const textArea = document.createElement('textarea')
            textArea.value = text
            textArea.style.position = 'fixed'
            textArea.style.left = '-9999px'
            document.body.appendChild(textArea)
            textArea.select()

            try {
                document.execCommand('copy')
                copySuccess.value = recordType
                setTimeout(() => {
                    copySuccess.value = null
                }, 2000)
                return true
            } catch (fallbackErr) {
                error.value = translations.value.copy_failed || 'Copy failed'
                return false
            } finally {
                document.body.removeChild(textArea)
            }
        }
    }

    /**
     * DMARC Generator computed properties
     */
    const dmarcInitialRecord = computed(() => dmarcData.value?.initial?.value || '')
    const dmarcRecommendedRecord = computed(() => dmarcData.value?.recommended?.value || '')
    const dmarcCurrentPolicy = computed(() => dmarcData.value?.current?.policy || 'none')
    const needsDmarcUpgrade = computed(() => {
        const policy = dmarcCurrentPolicy.value
        return policy === 'none' || !policy
    })

    /**
     * SPF Generator computed properties
     */
    const spfOptimalRecord = computed(() => spfData.value?.optimal?.value || '')
    const spfCurrentRecord = computed(() => spfData.value?.current?.record || '')
    const spfLookupCount = computed(() => spfData.value?.current?.lookup_count || 0)
    const spfOptimalLookupCount = computed(() => spfData.value?.optimal?.lookup_count || 0)
    const spfProviders = computed(() => spfData.value?.optimal?.providers || [])
    const spfExceedsLimit = computed(() => spfLookupCount.value >= 10)
    const spfApproachingLimit = computed(() => spfLookupCount.value >= 7 && spfLookupCount.value < 10)

    return {
        // State
        loading,
        error,
        dmarcData,
        spfData,
        translations,
        copySuccess,

        // Methods
        fetchGenerators,
        copyToClipboard,

        // DMARC computed
        dmarcInitialRecord,
        dmarcRecommendedRecord,
        dmarcCurrentPolicy,
        needsDmarcUpgrade,

        // SPF computed
        spfOptimalRecord,
        spfCurrentRecord,
        spfLookupCount,
        spfOptimalLookupCount,
        spfProviders,
        spfExceedsLimit,
        spfApproachingLimit,
    }
}
