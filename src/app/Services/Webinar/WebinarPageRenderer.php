<?php

namespace App\Services\Webinar;

use App\Models\Webinar;

/**
 * Turns a page theme into the CSS that the public funnel pages are painted
 * with. The builder-rendered pages ship their own stylesheet (no Tailwind CDN),
 * so every colour, radius and spacing value comes from the theme the user
 * picked in the builder.
 */
class WebinarPageRenderer
{
    public const SPACING = [
        'none' => '0px',
        'sm' => '14px',
        'md' => '28px',
        'lg' => '48px',
        'xl' => '72px',
    ];

    public const RADIUS = [
        'none' => '0px',
        'sm' => '6px',
        'md' => '12px',
        'lg' => '18px',
        'xl' => '26px',
        'full' => '999px',
    ];

    public const CONTAINER = [
        'narrow' => '640px',
        'normal' => '820px',
        'wide' => '1120px',
        'full' => '100%',
    ];

    public static function spacing(?string $key, string $fallback = 'md'): string
    {
        return self::SPACING[$key] ?? self::SPACING[$fallback];
    }

    public static function radius(?string $key, string $fallback = 'lg'): string
    {
        return self::RADIUS[$key] ?? self::RADIUS[$fallback];
    }

    public static function container(?string $key, string $fallback = 'normal'): string
    {
        return self::CONTAINER[$key] ?? self::CONTAINER[$fallback];
    }

    /**
     * Google Fonts stylesheet URL for the theme, or null for system fonts.
     */
    public static function fontUrl(array $theme): ?string
    {
        $families = [];

        foreach ([$theme['font'] ?? 'system', $theme['heading_font'] ?? 'system'] as $font) {
            $google = WebinarPageService::FONT_STACKS[$font]['google'] ?? null;
            if ($google && !in_array($google, $families, true)) {
                $families[] = $google;
            }
        }

        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
    }

    public static function fontStack(string $font): string
    {
        return WebinarPageService::FONT_STACKS[$font]['stack']
            ?? WebinarPageService::FONT_STACKS['system']['stack'];
    }

    /**
     * Background shorthand for <body> (solid colour, gradient or image).
     */
    public static function background(array $theme): string
    {
        return match ($theme['background_type'] ?? 'solid') {
            'gradient' => sprintf(
                'linear-gradient(%ddeg, %s 0%%, %s 100%%) fixed',
                (int) ($theme['background_angle'] ?? 135),
                $theme['background'] ?: '#4f46e5',
                $theme['background_to'] ?: '#7c3aed'
            ),
            'image' => ($theme['background_image'] ?? '') !== ''
                ? sprintf("%s url('%s') center/cover no-repeat fixed", $theme['background'] ?: '#0f172a', e($theme['background_image']))
                : ($theme['background'] ?: '#0f172a'),
            default => $theme['background'] ?: '#ffffff',
        };
    }

    /**
     * The button background for the theme's button style.
     */
    public static function buttonBackground(array $theme): string
    {
        return match ($theme['button_style'] ?? 'solid') {
            'gradient' => sprintf('linear-gradient(120deg, %s 0%%, %s 100%%)', $theme['primary'], $theme['primary_to'] ?: $theme['primary']),
            'outline' => 'transparent',
            default => $theme['primary'],
        };
    }

    /**
     * Full stylesheet for a page theme.
     *
     * @param  bool  $scoped  Scope the document-level rules to `.wb-scope` so
     *                        the theme can be embedded inside an existing page
     *                        (waiting room, replay) without restyling it.
     */
    public static function css(array $theme, bool $scoped = false): string
    {
        $theme = WebinarPageService::normalizeTheme($theme);

        $bodyFont = self::fontStack($theme['font']);
        $headingFont = self::fontStack($theme['heading_font']);
        $radius = self::radius($theme['radius'], 'xl');
        $container = self::container($theme['container']);
        $background = self::background($theme);
        $buttonBackground = self::buttonBackground($theme);
        $outline = ($theme['button_style'] ?? 'solid') === 'outline';
        $buttonColor = $outline ? $theme['primary'] : $theme['primary_text'];
        $shadow = $theme['shadow']
            ? '0 24px 60px -24px rgba(15, 23, 42, 0.45)'
            : 'none';

        $spacingVars = '';
        foreach (self::SPACING as $key => $value) {
            $spacingVars .= "    --wb-space-{$key}: {$value};\n";
        }

        $radiusVars = '';
        foreach (self::RADIUS as $key => $value) {
            $radiusVars .= "    --wb-radius-{$key}: {$value};\n";
        }

        $css = <<<CSS
:root {
    --wb-bg: {$theme['background']};
    --wb-surface: {$theme['surface']};
    --wb-surface-border: {$theme['surface_border']};
    --wb-text: {$theme['text']};
    --wb-muted: {$theme['muted']};
    --wb-heading: {$theme['heading']};
    --wb-primary: {$theme['primary']};
    --wb-primary-to: {$theme['primary_to']};
    --wb-primary-text: {$theme['primary_text']};
    --wb-card-bg: {$theme['card_background']};
    --wb-card-text: {$theme['card_text']};
    --wb-radius: {$radius};
    --wb-container: {$container};
    --wb-shadow: {$shadow};
    --wb-font: {$bodyFont};
    --wb-heading-font: {$headingFont};
{$spacingVars}{$radiusVars}}

*, *::before, *::after { box-sizing: border-box; }

html { -webkit-text-size-adjust: 100%; }

body {
    margin: 0;
    min-height: 100vh;
    background: {$background};
    color: var(--wb-text);
    font-family: var(--wb-font);
    font-size: 16px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

img { max-width: 100%; height: auto; display: block; }

a { color: inherit; }

.wb-page { padding: 48px 20px 72px; }

.wb-row { margin: 0 auto var(--wb-space-md); width: 100%; }
.wb-row__inner {
    margin: 0 auto;
    display: grid;
    gap: var(--wb-space-md);
    align-items: start;
}
.wb-row--surface .wb-row__inner,
.wb-row--primary .wb-row__inner,
.wb-row--custom .wb-row__inner {
    border-radius: var(--wb-radius);
}
.wb-row--surface .wb-row__inner {
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    backdrop-filter: blur(8px);
}
.wb-row--primary .wb-row__inner {
    background: {$buttonBackground};
    color: var(--wb-primary-text);
    border: 1px solid transparent;
}
.wb-row--primary .wb-heading { color: var(--wb-primary-text); }
.wb-row--primary .wb-text--muted { color: var(--wb-primary-text); opacity: 0.8; }

.wb-col { min-width: 0; display: flex; flex-direction: column; }
.wb-col > * + * { margin-top: var(--wb-space-sm); }

.wb-align-left { text-align: left; }
.wb-align-center { text-align: center; }
.wb-align-right { text-align: right; }

.wb-heading {
    font-family: var(--wb-heading-font);
    color: var(--wb-heading);
    margin: 0;
    line-height: 1.15;
    letter-spacing: -0.02em;
    font-weight: 800;
}
.wb-heading--sm { font-size: clamp(1.15rem, 2.4vw, 1.35rem); }
.wb-heading--md { font-size: clamp(1.4rem, 3vw, 1.75rem); }
.wb-heading--lg { font-size: clamp(1.8rem, 4vw, 2.4rem); }
.wb-heading--xl { font-size: clamp(2.1rem, 5.4vw, 3.2rem); }

.wb-text { margin: 0; white-space: pre-line; }
.wb-text--sm { font-size: 0.9rem; }
.wb-text--md { font-size: 1rem; }
.wb-text--lg { font-size: 1.15rem; }
.wb-text--muted { color: var(--wb-muted); }
.wb-text--primary { color: var(--wb-primary); }
.wb-text--heading { color: var(--wb-heading); }

.wb-button {
    display: inline-block;
    border: 1px solid {$theme['primary']};
    background: {$buttonBackground};
    color: {$buttonColor};
    font-weight: 700;
    text-decoration: none;
    border-radius: var(--wb-radius);
    padding: 14px 28px;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    box-shadow: var(--wb-shadow);
    font-family: inherit;
}
.wb-button:hover { transform: translateY(-1px); opacity: .94; }
.wb-button--sm { padding: 10px 20px; font-size: .95rem; }
.wb-button--lg { padding: 18px 34px; font-size: 1.15rem; }
.wb-button--block { display: block; width: 100%; text-align: center; }

.wb-card {
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    border-radius: var(--wb-radius);
    padding: 28px;
}

.wb-panel {
    background: var(--wb-card-bg);
    color: var(--wb-card-text);
    border-radius: var(--wb-radius);
    padding: 32px;
    box-shadow: var(--wb-shadow);
}

.wb-form__field { margin-bottom: 16px; }
.wb-form__label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; opacity: .85; }
.wb-form__control {
    width: 100%;
    padding: 12px 14px;
    border-radius: calc(var(--wb-radius) / 2);
    border: 1px solid rgba(15, 23, 42, .18);
    background: #fff;
    color: var(--wb-card-text);
    font-size: 1rem;
    font-family: inherit;
}
.wb-form__control:focus { outline: 2px solid var(--wb-primary); outline-offset: 1px; }
.wb-form__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.wb-form__note { font-size: .78rem; opacity: .65; text-align: center; margin: 14px 0 0; }
.wb-form__error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: .9rem;
}

.wb-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
.wb-list--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.wb-list__item { display: flex; gap: 12px; align-items: flex-start; }
.wb-list__icon { flex: 0 0 auto; width: 22px; height: 22px; color: var(--wb-primary); margin-top: 2px; }
.wb-row--primary .wb-list__icon { color: var(--wb-primary-text); }

.wb-steps { counter-reset: wb-step; display: grid; gap: 16px; }
.wb-steps__item { display: flex; gap: 14px; align-items: flex-start; }
.wb-steps__number {
    counter-increment: wb-step;
    flex: 0 0 auto;
    width: 34px; height: 34px;
    border-radius: 999px;
    background: {$buttonBackground};
    color: var(--wb-primary-text);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
}
.wb-steps__number::before { content: counter(wb-step); }

.wb-grid { display: grid; gap: 18px; }
.wb-grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.wb-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }

.wb-quote { margin: 0; }
.wb-quote__text { margin: 0 0 12px; font-style: italic; }
.wb-quote__author { display: flex; align-items: center; gap: 10px; font-size: .9rem; }
.wb-quote__avatar { width: 38px; height: 38px; border-radius: 999px; object-fit: cover; }
.wb-quote__role { color: var(--wb-muted); }

.wb-faq { display: grid; gap: 10px; }
.wb-faq__item {
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    border-radius: calc(var(--wb-radius) / 1.6);
    padding: 4px 18px;
}
.wb-faq__question { cursor: pointer; font-weight: 600; padding: 14px 0; list-style: none; }
.wb-faq__question::-webkit-details-marker { display: none; }
.wb-faq__question::after { content: '+'; float: right; opacity: .6; }
.wb-faq__item[open] .wb-faq__question::after { content: '–'; }
.wb-faq__answer { margin: 0 0 16px; color: var(--wb-muted); white-space: pre-line; }

.wb-speaker { display: flex; gap: 22px; align-items: center; }
.wb-speaker--stacked { flex-direction: column; text-align: center; }
.wb-speaker__avatar { width: 110px; height: 110px; border-radius: 999px; object-fit: cover; flex: 0 0 auto; }
.wb-speaker__name { font-weight: 700; font-size: 1.2rem; }
.wb-speaker__role { color: var(--wb-muted); font-size: .95rem; margin-bottom: 8px; }

.wb-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 18px; text-align: center; }
.wb-stats__value { font-family: var(--wb-heading-font); font-size: clamp(1.6rem, 3.6vw, 2.4rem); font-weight: 800; color: var(--wb-heading); }
.wb-stats__label { color: var(--wb-muted); font-size: .9rem; }

.wb-countdown { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
.wb-countdown__cell {
    min-width: 78px;
    padding: 12px 8px;
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    border-radius: calc(var(--wb-radius) / 1.6);
}
.wb-countdown__value { font-family: var(--wb-heading-font); font-size: 1.9rem; font-weight: 800; line-height: 1; color: var(--wb-heading); }
.wb-countdown__label { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--wb-muted); }

.wb-sessions { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.wb-sessions__option { cursor: pointer; }
.wb-sessions__option input { position: absolute; opacity: 0; pointer-events: none; }
.wb-sessions__box {
    border: 2px solid var(--wb-surface-border);
    background: var(--wb-surface);
    border-radius: calc(var(--wb-radius) / 1.6);
    padding: 12px;
    text-align: center;
    transition: border-color .15s ease, background .15s ease;
}
.wb-sessions__option input:checked + .wb-sessions__box { border-color: var(--wb-primary); background: var(--wb-primary); color: var(--wb-primary-text); }
.wb-sessions__day { font-weight: 700; }
.wb-sessions__hour { font-size: .9rem; opacity: .8; }

.wb-video { position: relative; width: 100%; padding-top: 56.25%; border-radius: var(--wb-radius); overflow: hidden; background: #000; box-shadow: var(--wb-shadow); }
.wb-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.wb-caption { color: var(--wb-muted); font-size: .85rem; margin: 8px 0 0; text-align: center; }

.wb-divider { border: 0; border-top: 1px solid var(--wb-surface-border); margin: 0; }

.wb-link-box {
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    border-radius: calc(var(--wb-radius) / 1.6);
    padding: 12px 14px;
    word-break: break-all;
    font-size: .9rem;
}

.wb-chip {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: var(--wb-surface);
    border: 1px solid var(--wb-surface-border);
    color: inherit;
    text-decoration: none;
    font-size: .88rem;
}

@media (max-width: 768px) {
    .wb-row__inner { grid-template-columns: 1fr !important; }
    .wb-grid--2, .wb-grid--3, .wb-list--2 { grid-template-columns: 1fr; }
    .wb-form__grid { grid-template-columns: 1fr; }
    .wb-speaker { flex-direction: column; text-align: center; }
    .wb-page { padding: 28px 16px 48px; }
}
CSS;

        if ($scoped) {
            // Keep the host page's own styling: only the embedded block area
            // adopts the theme.
            $css = str_replace([":root {", "\nbody {", "\n*, *::before, *::after {", "\nhtml {"],
                [".wb-scope {", "\n.wb-scope {", "\n.wb-scope *, .wb-scope *::before, .wb-scope *::after {", "\n.wb-scope-noop {"],
                $css);
            $css = str_replace('min-height: 100vh;', '', $css);
        }

        return $css;
    }

    /**
     * Grid template for a row layout.
     */
    public static function gridTemplate(string $layout): string
    {
        return match ($layout) {
            '1-1' => 'repeat(2, minmax(0, 1fr))',
            '1-2' => 'minmax(0, 1fr) minmax(0, 2fr)',
            '2-1' => 'minmax(0, 2fr) minmax(0, 1fr)',
            '1-1-1' => 'repeat(3, minmax(0, 1fr))',
            default => 'minmax(0, 1fr)',
        };
    }

    /**
     * Inline style attribute for one row.
     */
    public static function rowStyle(array $style, array $theme): string
    {
        $padding = self::spacing($style['padding'] ?? 'md');
        $gap = self::spacing($style['gap'] ?? 'md');
        $marginBottom = self::spacing($style['margin_bottom'] ?? 'md');
        $width = self::container($style['width'] ?? 'normal');
        $radius = self::radius($style['radius'] ?? 'xl');

        $declarations = [
            "max-width: {$width}",
            "padding: {$padding}",
            "gap: {$gap}",
            "border-radius: {$radius}",
        ];

        if (($style['background'] ?? 'none') === 'custom' && ($style['background_color'] ?? '') !== '') {
            $declarations[] = 'background: ' . $style['background_color'];
        }

        if (($style['vertical_align'] ?? 'top') !== 'top') {
            $declarations[] = 'align-items: ' . (($style['vertical_align'] === 'middle') ? 'center' : 'end');
        }

        return implode('; ', $declarations) . ";--wb-row-mb: {$marginBottom}";
    }

    /**
     * Resolve `{{ tokens }}` a page author can use in copy and links.
     *
     * @param  array<string, string|null>  $extra
     */
    public static function tokens(Webinar $webinar, array $extra = []): array
    {
        $tokens = [
            'webinar_name' => $webinar->name,
            'webinar_date' => $webinar->scheduled_at?->format('d.m.Y') ?? '',
            'webinar_time' => $webinar->scheduled_at?->format('H:i') ?? '',
            'register_url' => route('webinar.register', $webinar->slug),
        ];

        foreach ($extra as $key => $value) {
            $tokens[$key] = (string) ($value ?? '');
        }

        return $tokens;
    }

    /**
     * Replace `{{ token }}` placeholders in author-supplied copy.
     */
    public static function replaceTokens(?string $text, array $tokens): string
    {
        $text = (string) $text;

        if ($text === '' || !str_contains($text, '{{')) {
            return $text;
        }

        foreach ($tokens as $key => $value) {
            $text = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/', (string) $value, $text);
        }

        return $text;
    }
}
