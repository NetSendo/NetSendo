/**
 * Shared countries list for use across the application.
 * Each country object contains: code (ISO 2-letter), name, dial code, and emoji flag.
 */
export const countries = [
    { code: 'PL', name: 'Polska', dial: '+48', flag: '🇵🇱' },
    { code: 'DE', name: 'Deutschland', dial: '+49', flag: '🇩🇪' },
    { code: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧' },
    { code: 'US', name: 'United States', dial: '+1', flag: '🇺🇸' },
    { code: 'FR', name: 'France', dial: '+33', flag: '🇫🇷' },
    { code: 'ES', name: 'España', dial: '+34', flag: '🇪🇸' },
    { code: 'IT', name: 'Italia', dial: '+39', flag: '🇮🇹' },
    { code: 'NL', name: 'Nederland', dial: '+31', flag: '🇳🇱' },
    { code: 'BE', name: 'België', dial: '+32', flag: '🇧🇪' },
    { code: 'AT', name: 'Österreich', dial: '+43', flag: '🇦🇹' },
    { code: 'CH', name: 'Schweiz', dial: '+41', flag: '🇨🇭' },
    { code: 'CZ', name: 'Česko', dial: '+420', flag: '🇨🇿' },
    { code: 'SK', name: 'Slovensko', dial: '+421', flag: '🇸🇰' },
    { code: 'UA', name: 'Україна', dial: '+380', flag: '🇺🇦' },
    { code: 'RU', name: 'Россия', dial: '+7', flag: '🇷🇺' },
    { code: 'SE', name: 'Sverige', dial: '+46', flag: '🇸🇪' },
    { code: 'NO', name: 'Norge', dial: '+47', flag: '🇳🇴' },
    { code: 'DK', name: 'Danmark', dial: '+45', flag: '🇩🇰' },
    { code: 'FI', name: 'Suomi', dial: '+358', flag: '🇫🇮' },
    { code: 'IE', name: 'Ireland', dial: '+353', flag: '🇮🇪' },
    { code: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹' },
    { code: 'GR', name: 'Ελλάδα', dial: '+30', flag: '🇬🇷' },
    { code: 'HU', name: 'Magyarország', dial: '+36', flag: '🇭🇺' },
    { code: 'RO', name: 'România', dial: '+40', flag: '🇷🇴' },
    { code: 'BG', name: 'България', dial: '+359', flag: '🇧🇬' },
    { code: 'HR', name: 'Hrvatska', dial: '+385', flag: '🇭🇷' },
    { code: 'SI', name: 'Slovenija', dial: '+386', flag: '🇸🇮' },
    { code: 'LT', name: 'Lietuva', dial: '+370', flag: '🇱🇹' },
    { code: 'LV', name: 'Latvija', dial: '+371', flag: '🇱🇻' },
    { code: 'EE', name: 'Eesti', dial: '+372', flag: '🇪🇪' },
    { code: 'AU', name: 'Australia', dial: '+61', flag: '🇦🇺' },
    { code: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦' },
    { code: 'BR', name: 'Brasil', dial: '+55', flag: '🇧🇷' },
    { code: 'MX', name: 'México', dial: '+52', flag: '🇲🇽' },
    { code: 'JP', name: '日本', dial: '+81', flag: '🇯🇵' },
    { code: 'CN', name: '中国', dial: '+86', flag: '🇨🇳' },
    { code: 'IN', name: 'भारत', dial: '+91', flag: '🇮🇳' },
    { code: 'TR', name: 'Türkiye', dial: '+90', flag: '🇹🇷' },
    { code: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦' },
    { code: 'IL', name: 'ישראל', dial: '+972', flag: '🇮🇱' },
    { code: 'AE', name: 'الإمارات', dial: '+971', flag: '🇦🇪' },
    { code: 'SA', name: 'السعودية', dial: '+966', flag: '🇸🇦' },
    { code: 'EG', name: 'مصر', dial: '+20', flag: '🇪🇬' },
    { code: 'KR', name: '대한민국', dial: '+82', flag: '🇰🇷' },
    { code: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬' },
    { code: 'NZ', name: 'New Zealand', dial: '+64', flag: '🇳🇿' },
    { code: 'AR', name: 'Argentina', dial: '+54', flag: '🇦🇷' },
    { code: 'CL', name: 'Chile', dial: '+56', flag: '🇨🇱' },
    { code: 'CO', name: 'Colombia', dial: '+57', flag: '🇨🇴' },
];

/**
 * Find a country by its code.
 * @param {string} code - ISO 2-letter country code
 * @returns {Object|undefined} Country object or undefined
 */
export function findCountryByCode(code) {
    return countries.find(c => c.code === code);
}

/**
 * Get countries sorted alphabetically by name.
 * @returns {Array} Sorted countries array
 */
export function getCountriesSortedByName() {
    return [...countries].sort((a, b) => a.name.localeCompare(b.name));
}

export default countries;
