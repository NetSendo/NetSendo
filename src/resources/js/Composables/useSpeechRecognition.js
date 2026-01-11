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
                let finalTranscript = '';
                let interimTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const result = event.results[i];
                    if (result.isFinal) {
                        finalTranscript += result[0].transcript;
                    } else {
                        interimTranscript += result[0].transcript;
                    }
                }

                transcript.value = finalTranscript || interimTranscript;

                if (finalTranscript && options.onResult) {
                    options.onResult(finalTranscript);
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

    const setLang = (lang) => {
        if (recognition) {
            recognition.lang = lang;
        }
    };

    return {
        isSupported,
        isListening,
        transcript,
        error,
        start,
        stop,
        toggle,
        setLang,
    };
}

export default useSpeechRecognition;
