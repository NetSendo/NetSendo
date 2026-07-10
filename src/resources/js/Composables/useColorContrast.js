/**
 * Helpers for picking a readable text color on top of an arbitrary
 * background color. Used for tag pills whose background is user-chosen,
 * so that light tag colors (yellow, pastels, ...) get dark text instead
 * of the previously hard-coded, invisible white text.
 */

const DARK_TEXT = '#111827'; // gray-900
const LIGHT_TEXT = '#ffffff';

/**
 * Normalize a hex color to `#rrggbb`. Accepts `#abc`, `abc`, `#aabbcc`
 * or `aabbcc`. Returns null when it can't be parsed.
 */
function normalizeHex(hex) {
    if (typeof hex !== 'string') return null;
    let value = hex.trim().replace(/^#/, '');
    if (value.length === 3) {
        value = value
            .split('')
            .map((c) => c + c)
            .join('');
    }
    if (!/^[0-9a-fA-F]{6}$/.test(value)) return null;
    return '#' + value.toLowerCase();
}

/**
 * Perceived brightness (0-1) of a hex color. Returns 0 (treat as dark, so
 * white text is used) for unparseable input, matching the previous default.
 */
export function colorLuminance(hex) {
    const normalized = normalizeHex(hex);
    if (!normalized) return 0;
    const r = parseInt(normalized.slice(1, 3), 16);
    const g = parseInt(normalized.slice(3, 5), 16);
    const b = parseInt(normalized.slice(5, 7), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
}

/**
 * Whether a color is light enough that dark text reads better on top of it.
 */
export function isLightColor(hex) {
    return colorLuminance(hex) > 0.6;
}

/**
 * Best-contrast text color (`#111827` or `#ffffff`) for a given background.
 */
export function contrastTextColor(hex) {
    return isLightColor(hex) ? DARK_TEXT : LIGHT_TEXT;
}

export function useColorContrast() {
    return { colorLuminance, isLightColor, contrastTextColor };
}
