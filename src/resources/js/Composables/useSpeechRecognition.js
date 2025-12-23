import { ref, onUnmounted } from 'vue';

/**
 * Composable for Web Speech API voice recognition
 * Provides voice-to-text functionality for AI assistant prompts
 */
export function useSpeechRecognition() {
    const isListening = ref(false);
    const transcript = ref('');
    const error = ref('');
    const interimTranscript = ref('');

    // Check browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const isSupported = ref(!!SpeechRecognition);

    let recognition = null;

    const initRecognition = (lang = 'pl-PL') => {
        if (!SpeechRecognition) return null;

        const rec = new SpeechRecognition();
        rec.lang = lang;
        rec.interimResults = true;
        rec.continuous = false;
        rec.maxAlternatives = 1;

        rec.onstart = () => {
            isListening.value = true;
            error.value = '';
        };

        rec.onresult = (event) => {
            let interim = '';
            let final = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                if (result.isFinal) {
                    final += result[0].transcript;
                } else {
                    interim += result[0].transcript;
                }
            }

            interimTranscript.value = interim;
            if (final) {
                transcript.value = final;
            }
        };

        rec.onerror = (event) => {
            error.value = event.error;
            isListening.value = false;
        };

        rec.onend = () => {
            isListening.value = false;
            interimTranscript.value = '';
        };

        return rec;
    };

    /**
     * Start listening for speech input
     * @param {string} lang - Language code (e.g., 'pl-PL', 'en-US')
     */
    const startListening = (lang = 'pl-PL') => {
        if (!isSupported.value) {
            error.value = 'not_supported';
            return;
        }

        // Stop any existing recognition
        if (recognition) {
            try {
                recognition.stop();
            } catch (e) {
                // Ignore errors from stopping
            }
        }

        transcript.value = '';
        interimTranscript.value = '';
        recognition = initRecognition(lang);

        if (recognition) {
            try {
                recognition.start();
            } catch (e) {
                error.value = e.message;
            }
        }
    };

    /**
     * Stop listening and finalize transcript
     */
    const stopListening = () => {
        if (recognition && isListening.value) {
            try {
                recognition.stop();
            } catch (e) {
                // Ignore errors from stopping
            }
        }
        isListening.value = false;
    };

    /**
     * Toggle listening state
     * @param {string} lang - Language code
     */
    const toggleListening = (lang = 'pl-PL') => {
        if (isListening.value) {
            stopListening();
        } else {
            startListening(lang);
        }
    };

    // Cleanup on component unmount
    onUnmounted(() => {
        if (recognition) {
            try {
                recognition.stop();
            } catch (e) {
                // Ignore
            }
        }
    });

    return {
        isListening,
        isSupported,
        transcript,
        interimTranscript,
        error,
        startListening,
        stopListening,
        toggleListening,
    };
}

/**
 * Get language code for speech recognition based on locale
 * @param {string} locale - App locale (pl, en, de, es)
 * @returns {string} Speech recognition language code
 */
export function getSpeechLang(locale) {
    const langMap = {
        'pl': 'pl-PL',
        'en': 'en-US',
        'de': 'de-DE',
        'es': 'es-ES',
    };
    return langMap[locale] || 'en-US';
}
