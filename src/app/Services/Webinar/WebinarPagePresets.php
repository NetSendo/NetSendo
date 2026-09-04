<?php

namespace App\Services\Webinar;

use App\Models\Webinar;

/**
 * Ready-made looks for the webinar funnel pages: a theme plus a starter block
 * layout that is pre-filled with the webinar's own content (name, description,
 * benefits, custom sections), so applying a preset produces a finished page
 * rather than an empty canvas.
 */
class WebinarPagePresets
{
    public const PRESETS = ['midnight', 'aurora', 'minimal', 'corporate', 'urgency', 'editorial'];

    /**
     * Theme + swatches for the picker in the builder UI.
     */
    public static function all(): array
    {
        $presets = [];

        foreach (self::PRESETS as $key) {
            $theme = self::theme($key);
            $presets[] = [
                'key' => $key,
                'theme' => $theme,
                'swatches' => [
                    $theme['background_type'] === 'gradient' ? $theme['background'] : $theme['background'],
                    $theme['background_type'] === 'gradient' ? $theme['background_to'] : $theme['surface'],
                    $theme['primary'],
                    $theme['heading'],
                ],
            ];
        }

        return $presets;
    }

    public static function theme(string $preset): array
    {
        $themes = [
            // The look NetSendo shipped with — indigo/violet gradient, white text.
            'midnight' => [
                'mode' => 'dark',
                'background_type' => 'gradient',
                'background' => '#4f46e5',
                'background_to' => '#7c3aed',
                'background_angle' => 135,
                'surface' => 'rgba(255,255,255,0.10)',
                'surface_border' => 'rgba(255,255,255,0.16)',
                'text' => '#ffffff',
                'muted' => 'rgba(255,255,255,0.72)',
                'heading' => '#ffffff',
                'primary' => '#4f46e5',
                'primary_to' => '#9333ea',
                'primary_text' => '#ffffff',
                'card_background' => '#ffffff',
                'card_text' => '#111827',
                'font' => 'inter',
                'heading_font' => 'inter',
                'radius' => 'xl',
                'container' => 'normal',
                'button_style' => 'gradient',
                'shadow' => true,
            ],
            // Deep navy with a teal accent — calmer, works well for B2B.
            'aurora' => [
                'mode' => 'dark',
                'background_type' => 'gradient',
                'background' => '#0f172a',
                'background_to' => '#1e3a5f',
                'background_angle' => 160,
                'surface' => 'rgba(148,197,255,0.10)',
                'surface_border' => 'rgba(148,197,255,0.22)',
                'text' => '#e2e8f0',
                'muted' => 'rgba(226,232,240,0.68)',
                'heading' => '#ffffff',
                'primary' => '#06b6d4',
                'primary_to' => '#0ea5e9',
                'primary_text' => '#04121c',
                'card_background' => '#ffffff',
                'card_text' => '#0f172a',
                'font' => 'inter',
                'heading_font' => 'inter',
                'radius' => 'lg',
                'container' => 'normal',
                'button_style' => 'gradient',
                'shadow' => true,
            ],
            // Light, airy, lots of white space.
            'minimal' => [
                'mode' => 'light',
                'background_type' => 'solid',
                'background' => '#f8fafc',
                'background_to' => '#f1f5f9',
                'background_angle' => 180,
                'surface' => '#ffffff',
                'surface_border' => '#e2e8f0',
                'text' => '#334155',
                'muted' => '#64748b',
                'heading' => '#0f172a',
                'primary' => '#111827',
                'primary_to' => '#374151',
                'primary_text' => '#ffffff',
                'card_background' => '#ffffff',
                'card_text' => '#0f172a',
                'font' => 'inter',
                'heading_font' => 'inter',
                'radius' => 'md',
                'container' => 'narrow',
                'button_style' => 'solid',
                'shadow' => false,
            ],
            // Corporate blue on white.
            'corporate' => [
                'mode' => 'light',
                'background_type' => 'solid',
                'background' => '#ffffff',
                'background_to' => '#eff6ff',
                'background_angle' => 180,
                'surface' => '#f1f5f9',
                'surface_border' => '#dbeafe',
                'text' => '#1e293b',
                'muted' => '#475569',
                'heading' => '#1e3a8a',
                'primary' => '#1d4ed8',
                'primary_to' => '#2563eb',
                'primary_text' => '#ffffff',
                'card_background' => '#ffffff',
                'card_text' => '#0f172a',
                'font' => 'lato',
                'heading_font' => 'montserrat',
                'radius' => 'sm',
                'container' => 'wide',
                'button_style' => 'solid',
                'shadow' => true,
            ],
            // High-contrast sales page: near-black with an amber CTA.
            'urgency' => [
                'mode' => 'dark',
                'background_type' => 'gradient',
                'background' => '#111827',
                'background_to' => '#000000',
                'background_angle' => 180,
                'surface' => 'rgba(251,191,36,0.08)',
                'surface_border' => 'rgba(251,191,36,0.28)',
                'text' => '#f3f4f6',
                'muted' => 'rgba(243,244,246,0.66)',
                'heading' => '#fbbf24',
                'primary' => '#f59e0b',
                'primary_to' => '#ef4444',
                'primary_text' => '#111827',
                'card_background' => '#ffffff',
                'card_text' => '#111827',
                'font' => 'poppins',
                'heading_font' => 'poppins',
                'radius' => 'lg',
                'container' => 'normal',
                'button_style' => 'gradient',
                'shadow' => true,
            ],
            // Warm, serif, magazine-like — good for evergreen/expert webinars.
            'editorial' => [
                'mode' => 'light',
                'background_type' => 'solid',
                'background' => '#fdf8f3',
                'background_to' => '#f5ede4',
                'background_angle' => 180,
                'surface' => '#ffffff',
                'surface_border' => '#e7d9c8',
                'text' => '#3f3a35',
                'muted' => '#7c6f63',
                'heading' => '#1f2937',
                'primary' => '#b45309',
                'primary_to' => '#d97706',
                'primary_text' => '#ffffff',
                'card_background' => '#ffffff',
                'card_text' => '#1f2937',
                'font' => 'source-serif',
                'heading_font' => 'playfair',
                'radius' => 'md',
                'container' => 'narrow',
                'button_style' => 'solid',
                'shadow' => false,
            ],
        ];

        return WebinarPageService::normalizeTheme($themes[$preset] ?? $themes['midnight']);
    }

    /**
     * Build a complete page definition for the given preset, pre-filled with
     * the webinar's own content.
     */
    public static function build(Webinar $webinar, string $preset, string $page): array
    {
        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'midnight';

        return [
            'enabled' => true,
            'theme' => self::theme($preset),
            'rows' => match ($page) {
                WebinarPageService::PAGE_REGISTER => self::registerRows($webinar, $preset),
                WebinarPageService::PAGE_THANKYOU => self::thankYouRows($webinar, $preset),
                WebinarPageService::PAGE_PURCHASE => self::purchaseRows($webinar),
                WebinarPageService::PAGE_WATCH => self::watchRows($webinar),
                WebinarPageService::PAGE_REPLAY => self::replayRows($webinar),
                default => [],
            },
        ];
    }

    protected static function registerRows(Webinar $webinar, string $preset): array
    {
        $B = fn (string $type, array $props = []) => WebinarPageService::block($type, $props);
        $benefits = $webinar->benefitsList();
        $rows = [];

        // Hero: thumbnail, headline, subheadline.
        $hero = [];
        if ($webinar->thumbnail_url) {
            $hero[] = $B('image', ['url' => $webinar->thumbnail_url, 'alt' => $webinar->name, 'radius' => 'xl']);
        }
        $hero[] = $B('heading', [
            'text' => $webinar->pageContent('register_headline', $webinar->name),
            'level' => 1,
            'size' => 'xl',
            'align' => 'center',
        ]);
        $subheadline = $webinar->pageContent('register_subheadline', $webinar->description);
        if ($subheadline) {
            $hero[] = $B('text', ['body' => $subheadline, 'align' => 'center', 'size' => 'lg', 'color' => 'muted']);
        }
        $rows[] = WebinarPageService::row($hero, ['padding' => 'lg', 'align' => 'center', 'margin_bottom' => 'md']);

        // Countdown on the urgency preset only — it is the one that sells on time.
        if ($preset === 'urgency') {
            $rows[] = WebinarPageService::row([
                $B('countdown', ['title' => __('webinars.builder.blocks.countdown.default_title')]),
            ], ['background' => 'surface', 'padding' => 'md', 'align' => 'center']);
        }

        // Date card + registration form side by side on wide presets.
        $formBlock = $B('form', []);
        $dateBlock = $B('date_card', []);

        if (in_array($preset, ['corporate', 'aurora'], true)) {
            $row = WebinarPageService::row([], ['padding' => 'md', 'gap' => 'lg'], '1-1');
            $row['columns'][0]['blocks'] = array_values(array_filter([
                $webinar->scheduled_at ? $dateBlock : null,
                count($benefits) > 0
                    ? $B('benefits', [
                        'title' => $webinar->pageContent('register_benefits_title', __('webinars.public.register.benefits')),
                        'items' => $benefits,
                    ])
                    : null,
            ]));
            $row['columns'][1]['blocks'] = [$formBlock];
            $rows[] = $row;
        } else {
            if ($webinar->scheduled_at) {
                $rows[] = WebinarPageService::row([$dateBlock], ['padding' => 'sm', 'align' => 'center']);
            }
            $rows[] = WebinarPageService::row([$B('sessions', []), $formBlock], ['padding' => 'md']);
            if (count($benefits) > 0) {
                $rows[] = WebinarPageService::row([
                    $B('benefits', [
                        'title' => $webinar->pageContent('register_benefits_title', __('webinars.public.register.benefits')),
                        'items' => $benefits,
                    ]),
                ], ['background' => 'surface', 'padding' => 'lg']);
            }
        }

        // Existing custom sections keep their content.
        foreach ($webinar->registrationSections() as $section) {
            $rows[] = self::rowFromLegacySection($section);
        }

        if ($webinar->pageContent('speaker_name')) {
            $rows[] = WebinarPageService::row([
                $B('speaker', [
                    'name' => (string) $webinar->pageContent('speaker_name'),
                    'role' => (string) $webinar->pageContent('speaker_role'),
                    'bio' => (string) $webinar->pageContent('speaker_bio'),
                ]),
            ], ['background' => 'surface', 'padding' => 'lg']);
        }

        return WebinarPageService::normalizeRows($rows, WebinarPageService::PAGE_REGISTER);
    }

    protected static function thankYouRows(Webinar $webinar, string $preset): array
    {
        $B = fn (string $type, array $props = []) => WebinarPageService::block($type, $props);
        $rows = [];

        $rows[] = WebinarPageService::row([
            $B('heading', [
                'text' => $webinar->pageContent('thankyou_headline', __('webinars.builder.presets.thankyou_headline')),
                'level' => 1,
                'size' => 'xl',
                'align' => 'center',
            ]),
            $B('text', [
                'body' => $webinar->pageContent('thankyou_message', __('webinars.builder.presets.thankyou_message')),
                'align' => 'center',
                'size' => 'lg',
                'color' => 'muted',
            ]),
        ], ['padding' => 'lg', 'align' => 'center']);

        $rows[] = WebinarPageService::row([
            $B('registration_details', []),
        ], ['padding' => 'md']);

        foreach ($webinar->thankYouSections() as $section) {
            $rows[] = self::rowFromLegacySection($section);
        }

        if ($webinar->calendlySettings()) {
            $rows[] = WebinarPageService::row([$B('calendly', [])], ['padding' => 'md']);
        }

        return WebinarPageService::normalizeRows($rows, WebinarPageService::PAGE_THANKYOU);
    }

    protected static function purchaseRows(Webinar $webinar): array
    {
        $B = fn (string $type, array $props = []) => WebinarPageService::block($type, $props);
        $rows = [];

        $rows[] = WebinarPageService::row([
            $B('heading', [
                'text' => $webinar->pageContent('purchase_thankyou_headline', __('webinars.public.thankyou.purchase_headline')),
                'level' => 1,
                'size' => 'xl',
                'align' => 'center',
            ]),
            $B('text', [
                'body' => $webinar->pageContent('purchase_thankyou_message', __('webinars.public.thankyou.purchase_message')),
                'align' => 'center',
                'size' => 'lg',
                'color' => 'muted',
            ]),
        ], ['padding' => 'lg', 'align' => 'center']);

        foreach ($webinar->thankYouSections() as $section) {
            $rows[] = self::rowFromLegacySection($section);
        }

        if ($webinar->calendlySettings()) {
            $rows[] = WebinarPageService::row([$B('calendly', [])], ['padding' => 'md']);
        }

        return WebinarPageService::normalizeRows($rows, WebinarPageService::PAGE_PURCHASE);
    }

    protected static function watchRows(Webinar $webinar): array
    {
        $B = fn (string $type, array $props = []) => WebinarPageService::block($type, $props);

        return WebinarPageService::normalizeRows([
            WebinarPageService::row([
                $B('heading', ['text' => $webinar->name, 'level' => 2, 'size' => 'lg', 'align' => 'center']),
            ], ['padding' => 'md', 'align' => 'center']),
        ], WebinarPageService::PAGE_WATCH);
    }

    protected static function replayRows(Webinar $webinar): array
    {
        $B = fn (string $type, array $props = []) => WebinarPageService::block($type, $props);

        return WebinarPageService::normalizeRows([
            WebinarPageService::row([
                $B('heading', ['text' => $webinar->name, 'level' => 2, 'size' => 'lg', 'align' => 'center']),
            ], ['padding' => 'md', 'align' => 'center']),
        ], WebinarPageService::PAGE_REPLAY);
    }

    /**
     * Convert one legacy `settings[*_sections]` entry into a builder row.
     */
    protected static function rowFromLegacySection(array $section): array
    {
        if (($section['type'] ?? 'text') === 'video') {
            $blocks = array_values(array_filter([
                ($section['title'] ?? '') !== ''
                    ? WebinarPageService::block('heading', ['text' => $section['title'], 'level' => 3, 'size' => 'md'])
                    : null,
                WebinarPageService::block('video', ['url' => $section['embed_url'] ?? '']),
            ]));

            return WebinarPageService::row($blocks, ['padding' => 'sm']);
        }

        $blocks = array_values(array_filter([
            ($section['title'] ?? '') !== ''
                ? WebinarPageService::block('heading', ['text' => $section['title'], 'level' => 3, 'size' => 'md'])
                : null,
            ($section['body'] ?? '') !== ''
                ? WebinarPageService::block('text', ['body' => $section['body']])
                : null,
        ]));

        return WebinarPageService::row($blocks, ['background' => 'surface', 'padding' => 'lg']);
    }
}
