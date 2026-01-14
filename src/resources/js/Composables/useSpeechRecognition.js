import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Get speech recognition language code based on app locale
 */
export function getSpeechLang(locale = 'pl') {
    const langMap = {
        'pl': 'pl-PL',
        'en': 'en-US',
        'de': 'de-DE',
        'es': 'es-ES',
    };
    return langMap[locale] || 'en-US';
}

/**
 * Speech recognition composable for voice input
 */
export function useSpeechRecognition(options = {}) {
    const isSupported = ref(false);
    const isListening = ref(false);
    const transcript = ref('');
    const interimTranscript = ref('');
    const error = ref(null);

    let recognition = null;

    onMounted(() => {
        // Check if browser supports speech recognition
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (SpeechRecognition) {
            isSupported.value = true;
            recognition = new SpeechRecognition();
            recognition.continuous = options.continuous || false;
            recognition.interimResults = options.interimResults || true;
            recognition.lang = options.lang || 'pl-PL';

            recognition.onresult = (event) => {
                let finalTranscriptText = '';
                let interimTranscriptText = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const result = event.results[i];
                    if (result.isFinal) {
                        finalTranscriptText += result[0].transcript;
                    } else {
                        interimTranscriptText += result[0].transcript;
                    }
                }

                // Update interim transcript for live display
                interimTranscript.value = interimTranscriptText;

                // Only set final transcript when we have one
                if (finalTranscriptText) {
                    transcript.value = finalTranscriptText;
                    interimTranscript.value = ''; // Clear interim when final is ready
                }

                if (finalTranscriptText && options.onResult) {
                    options.onResult(finalTranscriptText);
                }
            };

            recognition.onerror = (event) => {
                error.value = event.error;
                isListening.value = false;
                if (options.onError) {
                    options.onError(event.error);
                }
            };

            recognition.onend = () => {
                isListening.value = false;
                if (options.onEnd) {
                    options.onEnd();
                }
            };
        }
    });

    onUnmounted(() => {
        if (recognition && isListening.value) {
            recognition.stop();
        }
    });

    const start = () => {
        if (!isSupported.value || isListening.value) return;

        try {
            transcript.value = '';
            error.value = null;
            recognition.start();
            isListening.value = true;
        } catch (e) {
            error.value = e.message;
        }
    };

    const stop = () => {
        if (!recognition || !isListening.value) return;

        try {
            recognition.stop();
            isListening.value = false;
        } catch (e) {
            error.value = e.message;
        }
    };

    const toggle = () => {
        if (isListening.value) {
            stop();
        } else {
            start();
        }
    };

    /**
     * Toggle listening with optional language setting
     * This is the main function used by components
     */
    const toggleListening = (lang) => {
        if (lang && recognition) {
            recognition.lang = lang;
        }
        toggle();
    };

    const setLang = (lang) => {
        if (recognition) {
            recognition.lang = lang;
        }
    };

    return {
        isSupported,
        isListening,
        transcript,
        interimTranscript,
        error,
        start,
        stop,
        toggle,
        toggleListening,
        setLang,
    };
}

export default useSpeechRecognition;
