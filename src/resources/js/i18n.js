/**
 * Vue-i18n Configuration
 *
 * The interface speaks English, German, Spanish and Polish. Each language is a file of about
 * half a megabyte, so only the one in use is downloaded: the others were once all part of
 * the start-up script, and every visitor paid for four languages to read one.
 */

import { createI18n } from 'vue-i18n';

// One chunk per language, fetched on demand
const loaders = import.meta.glob('./locales/*.json');

const FALLBACK = 'en';

let i18n = null;
const loading = {};

/** The texts of one language, downloaded once. */
export function loadMessages(code) {
    if (!loading[code]) {
        const load = loaders[`./locales/${code}.json`];

        loading[code] = load
            ? load()
                  .then((module) => module.default)
                  .catch((error) => {
                      // A failed download may be retried (a later switch, the next page)
                      delete loading[code];
                      throw error;
                  })
            : Promise.reject(new Error(`Unknown locale: ${code}`));
    }

    return loading[code];
}

/**
 * Create and configure the vue-i18n instance
 *
 * @param {string} locale - Initial locale code (en, de, es, pl)
 * @param {object} messages - Its texts, already loaded with loadMessages()
 * @returns {I18n} Configured i18n instance
 */
export function setupI18n(locale = FALLBACK, messages = {}) {
    let fallbackRequested = locale === FALLBACK;

    // English fills in for a text another language lacks. The four files are kept complete,
    // so it is fetched only when a text really is missing: the key shows for a moment, then
    // the English text takes its place.
    const fetchFallback = () => {
        if (fallbackRequested) return;
        fallbackRequested = true;

        loadMessages(FALLBACK)
            .then((fallback) => i18n.global.setLocaleMessage(FALLBACK, fallback))
            .catch(() => {
                fallbackRequested = false;
            });
    };

    i18n = createI18n({
        legacy: false, // Use Composition API mode
        locale: locale,
        fallbackLocale: FALLBACK,
        messages: { [locale]: messages },
        missing: (missingLocale) => {
            // Asked later, outside the render that found the gap
            if (missingLocale !== FALLBACK) setTimeout(fetchFallback, 0);
        },
        // Suppress missing translation warnings in production
        missingWarn: import.meta.env.DEV,
        fallbackWarn: import.meta.env.DEV,
    });

    return i18n;
}

/**
 * Switch the interface to another language, downloading its texts first if needed.
 * Resolves once the interface speaks the new language.
 */
export async function setLocale(code) {
    if (!i18n) return;

    if (!i18n.global.availableLocales.includes(code)) {
        i18n.global.setLocaleMessage(code, await loadMessages(code));
    }

    i18n.global.locale.value = code;

    // Screen readers and the browser's translator read the language from here
    if (typeof document !== 'undefined') {
        document.documentElement.lang = code;
    }
}

export default setupI18n;
