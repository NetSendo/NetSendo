<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use Illuminate\Support\Str;

/**
 * Definition, normalization and defaults for builder-driven webinar funnel
 * pages (registration, thank-you, post-purchase, waiting room, replay).
 *
 * A page definition is stored in `webinars.settings['pages'][$page]` and looks
 * like:
 *
 *   [
 *     'enabled' => true,          // false → the legacy template is rendered
 *     'theme'   => [...],         // colors, typography, spacing
 *     'rows'    => [              // ordered rows, each with 1-3 columns
 *       ['id' => '…', 'layout' => '1-1', 'style' => [...], 'columns' => [
 *         ['blocks' => [ ['id' => '…', 'type' => 'heading', 'props' => [...]] ]],
 *       ]],
 *     ],
 *   ]
 *
 * Everything the builder writes goes through {@see self::normalize()}, so the
 * renderer can rely on every key being present and of the expected type.
 */
class WebinarPageService
{
    public const PAGE_REGISTER = 'register';
    public const PAGE_THANKYOU = 'thankyou';
    public const PAGE_PURCHASE = 'purchase';
    public const PAGE_WATCH = 'watch';
    public const PAGE_REPLAY = 'replay';

    public const PAGES = [
        self::PAGE_REGISTER,
        self::PAGE_THANKYOU,
        self::PAGE_PURCHASE,
        self::PAGE_WATCH,
        self::PAGE_REPLAY,
    ];

    /**
     * Pages that are rendered entirely from blocks. The remaining pages
     * (watch, replay) keep their interactive player template and only take
     * the theme plus blocks placed above/below the player.
     */
    public const FULL_PAGES = [self::PAGE_REGISTER, self::PAGE_THANKYOU, self::PAGE_PURCHASE];

    public const LAYOUTS = ['1', '1-1', '1-2', '2-1', '1-1-1'];

    public const ROW_BACKGROUNDS = ['none', 'surface', 'primary', 'custom'];
    public const SPACINGS = ['none', 'sm', 'md', 'lg', 'xl'];
    public const ALIGNMENTS = ['left', 'center', 'right'];
    public const RADII = ['none', 'sm', 'md', 'lg', 'xl', 'full'];
    public const CONTAINERS = ['narrow', 'normal', 'wide', 'full'];
    public const FONTS = ['system', 'inter', 'poppins', 'montserrat', 'lato', 'playfair', 'source-serif'];

    /**
     * Web-font stacks. The renderer only loads a Google font when the theme
     * actually asks for one.
     */
    public const FONT_STACKS = [
        'system' => ['stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif", 'google' => null],
        'inter' => ['stack' => "'Inter', system-ui, sans-serif", 'google' => 'Inter:wght@400;500;600;700;800'],
        'poppins' => ['stack' => "'Poppins', system-ui, sans-serif", 'google' => 'Poppins:wght@400;500;600;700;800'],
        'montserrat' => ['stack' => "'Montserrat', system-ui, sans-serif", 'google' => 'Montserrat:wght@400;500;600;700;800'],
        'lato' => ['stack' => "'Lato', system-ui, sans-serif", 'google' => 'Lato:wght@400;700;900'],
        'playfair' => ['stack' => "'Playfair Display', Georgia, serif", 'google' => 'Playfair+Display:wght@500;600;700;800'],
        'source-serif' => ['stack' => "'Source Serif 4', Georgia, serif", 'google' => 'Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700'],
    ];

    /**
     * Catalog of block types: default props, which pages accept them and how
     * the builder UI groups them. Shared with the Vue builder via Inertia so
     * both sides agree on the shape of a block.
     */
    public static function blockCatalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        return $catalog = [
            'heading' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['text' => '', 'level' => 2, 'size' => 'lg', 'align' => 'center', 'color' => 'heading'],
            ],
            'text' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['body' => '', 'align' => 'left', 'size' => 'md', 'color' => 'text'],
            ],
            'image' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['url' => '', 'alt' => '', 'align' => 'center', 'width' => 100, 'radius' => 'lg', 'link' => ''],
            ],
            'video' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['url' => '', 'caption' => ''],
            ],
            'button' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['label' => '', 'url' => '', 'align' => 'center', 'size' => 'md', 'style' => 'solid', 'full_width' => false],
            ],
            'divider' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['style' => 'line', 'size' => 'md'],
            ],
            'spacer' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['size' => 'md'],
            ],
            'html' => [
                'group' => 'basic',
                'pages' => self::PAGES,
                'props' => ['code' => ''],
            ],

            'form' => [
                'group' => 'conversion',
                'pages' => [self::PAGE_REGISTER],
                'unique' => true,
                'props' => [
                    'title' => '',
                    'button_label' => '',
                    'consent' => '',
                    'show_first_name' => true,
                    'show_last_name' => true,
                    'show_phone' => false,
                    'show_timezone' => true,
                    'require_name' => false,
                ],
            ],
            'sessions' => [
                'group' => 'conversion',
                'pages' => [self::PAGE_REGISTER],
                'unique' => true,
                'props' => ['title' => '', 'subtitle' => ''],
            ],
            'date_card' => [
                'group' => 'conversion',
                'pages' => self::PAGES,
                'props' => ['title' => ''],
            ],
            'countdown' => [
                'group' => 'conversion',
                'pages' => self::PAGES,
                'props' => ['title' => '', 'target' => 'auto', 'custom_at' => '', 'expired_text' => ''],
            ],
            'registration_details' => [
                'group' => 'conversion',
                'pages' => [self::PAGE_THANKYOU],
                'unique' => true,
                'props' => ['show_time' => true, 'show_link' => true, 'show_calendar' => true],
            ],
            'calendly' => [
                'group' => 'conversion',
                'pages' => [self::PAGE_THANKYOU, self::PAGE_PURCHASE, self::PAGE_REPLAY, self::PAGE_WATCH],
                'unique' => true,
                'props' => ['title' => '', 'description' => '', 'url' => ''],
            ],

            'benefits' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['title' => '', 'items' => [], 'icon' => 'check', 'columns' => 1],
            ],
            'steps' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['title' => '', 'items' => []],
            ],
            'testimonials' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['title' => '', 'items' => [], 'columns' => 2],
            ],
            'faq' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['title' => '', 'items' => []],
            ],
            'speaker' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['name' => '', 'role' => '', 'bio' => '', 'avatar' => '', 'layout' => 'side'],
            ],
            'stats' => [
                'group' => 'content',
                'pages' => self::PAGES,
                'props' => ['items' => []],
            ],
        ];
    }

    /**
     * Block types available on a given page, in catalog order.
     */
    public static function blocksForPage(string $page): array
    {
        $available = [];

        foreach (self::blockCatalog() as $type => $definition) {
            if (in_array($page, $definition['pages'], true)) {
                $available[$type] = $definition;
            }
        }

        return $available;
    }

    public static function themeDefaults(): array
    {
        return [
            'mode' => 'dark',
            'background_type' => 'gradient',
            'background' => '#4f46e5',
            'background_to' => '#7c3aed',
            'background_angle' => 135,
            'background_image' => '',
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
        ];
    }

    /**
     * Normalize a whole page definition coming from the builder (or storage).
     */
    public static function normalize(?array $definition, string $page): array
    {
        $definition = $definition ?? [];

        return [
            'enabled' => (bool) ($definition['enabled'] ?? false),
            'theme' => self::normalizeTheme($definition['theme'] ?? []),
            'rows' => self::normalizeRows($definition['rows'] ?? [], $page),
        ];
    }

    public static function normalizeTheme(mixed $theme): array
    {
        $theme = is_array($theme) ? $theme : [];
        $defaults = self::themeDefaults();
        $normalized = [];

        foreach ($defaults as $key => $default) {
            $value = $theme[$key] ?? $default;

            $normalized[$key] = match ($key) {
                'mode' => in_array($value, ['dark', 'light'], true) ? $value : $default,
                'background_type' => in_array($value, ['solid', 'gradient', 'image'], true) ? $value : $default,
                'background_angle' => max(0, min(360, (int) $value)),
                'font', 'heading_font' => in_array($value, self::FONTS, true) ? $value : $default,
                'radius' => in_array($value, self::RADII, true) ? $value : $default,
                'container' => in_array($value, self::CONTAINERS, true) ? $value : $default,
                'button_style' => in_array($value, ['solid', 'gradient', 'outline'], true) ? $value : $default,
                'shadow' => (bool) $value,
                default => is_string($value) ? trim($value) : $default,
            };
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRows(mixed $rows, string $page): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $allowed = array_keys(self::blocksForPage($page));
        $seenUnique = [];
        $normalized = [];

        foreach (array_slice($rows, 0, 60) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $layout = in_array($row['layout'] ?? null, self::LAYOUTS, true) ? $row['layout'] : '1';
            $columnCount = substr_count($layout, '-') + 1;

            $columns = [];
            $sourceColumns = is_array($row['columns'] ?? null) ? array_values($row['columns']) : [];

            for ($i = 0; $i < $columnCount; $i++) {
                $blocks = [];
                $sourceBlocks = is_array($sourceColumns[$i]['blocks'] ?? null) ? $sourceColumns[$i]['blocks'] : [];

                foreach (array_slice($sourceBlocks, 0, 40) as $block) {
                    $normalizedBlock = self::normalizeBlock($block, $allowed);

                    if (!$normalizedBlock) {
                        continue;
                    }

                    // Blocks that may only appear once per page (form, chat…)
                    $catalog = self::blockCatalog()[$normalizedBlock['type']];
                    if (($catalog['unique'] ?? false)) {
                        if (isset($seenUnique[$normalizedBlock['type']])) {
                            continue;
                        }
                        $seenUnique[$normalizedBlock['type']] = true;
                    }

                    $blocks[] = $normalizedBlock;
                }

                $columns[] = ['blocks' => $blocks];
            }

            $normalized[] = [
                'id' => self::normalizeId($row['id'] ?? null, 'row'),
                'layout' => $layout,
                'style' => self::normalizeRowStyle($row['style'] ?? []),
                'columns' => $columns,
            ];
        }

        return $normalized;
    }

    protected static function normalizeRowStyle(mixed $style): array
    {
        $style = is_array($style) ? $style : [];

        return [
            'background' => in_array($style['background'] ?? null, self::ROW_BACKGROUNDS, true) ? $style['background'] : 'none',
            'background_color' => is_string($style['background_color'] ?? null) ? trim($style['background_color']) : '',
            'padding' => in_array($style['padding'] ?? null, self::SPACINGS, true) ? $style['padding'] : 'md',
            'gap' => in_array($style['gap'] ?? null, self::SPACINGS, true) ? $style['gap'] : 'md',
            'align' => in_array($style['align'] ?? null, self::ALIGNMENTS, true) ? $style['align'] : 'left',
            'vertical_align' => in_array($style['vertical_align'] ?? null, ['top', 'middle', 'bottom'], true) ? $style['vertical_align'] : 'top',
            'width' => in_array($style['width'] ?? null, self::CONTAINERS, true) ? $style['width'] : 'normal',
            'radius' => in_array($style['radius'] ?? null, self::RADII, true) ? $style['radius'] : 'xl',
            'margin_bottom' => in_array($style['margin_bottom'] ?? null, self::SPACINGS, true) ? $style['margin_bottom'] : 'md',
        ];
    }

    /**
     * @param  array<int, string>  $allowedTypes
     */
    public static function normalizeBlock(mixed $block, array $allowedTypes): ?array
    {
        if (!is_array($block)) {
            return null;
        }

        $type = $block['type'] ?? null;

        if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
            return null;
        }

        $catalog = self::blockCatalog()[$type];
        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $normalizedProps = [];

        foreach ($catalog['props'] as $key => $default) {
            $value = $props[$key] ?? $default;

            $normalizedProps[$key] = match (true) {
                is_bool($default) => (bool) $value,
                is_int($default) => (int) $value,
                is_array($default) => self::normalizeItems($value),
                default => is_scalar($value) ? trim((string) $value) : $default,
            };
        }

        return [
            'id' => self::normalizeId($block['id'] ?? null, 'b'),
            'type' => $type,
            'props' => $normalizedProps,
        ];
    }

    /**
     * Repeatable prop entries (benefit bullets, FAQ pairs, testimonials…).
     * Kept generic: string entries stay strings, object entries keep their
     * string fields.
     */
    protected static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach (array_slice($items, 0, 30) as $item) {
            if (is_string($item)) {
                $item = trim($item);
                if ($item !== '') {
                    $normalized[] = $item;
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $entry = [];
            foreach ($item as $key => $value) {
                if (!is_string($key) || !is_scalar($value)) {
                    continue;
                }
                $entry[$key] = trim((string) $value);
            }

            if ($entry !== [] && implode('', $entry) !== '') {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    protected static function normalizeId(mixed $id, string $prefix): string
    {
        if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
            return $id;
        }

        return $prefix . '_' . Str::lower(Str::random(8));
    }

    /**
     * Build an id for a freshly created row/block (used by presets and the
     * legacy importer).
     */
    public static function newId(string $prefix = 'b'): string
    {
        return $prefix . '_' . Str::lower(Str::random(8));
    }

    /**
     * Wrap blocks in a single-column row.
     */
    public static function row(array $blocks, array $style = [], string $layout = '1'): array
    {
        $columnCount = substr_count($layout, '-') + 1;
        $columns = [];

        for ($i = 0; $i < $columnCount; $i++) {
            $columns[] = ['blocks' => $i === 0 ? $blocks : []];
        }

        return [
            'id' => self::newId('row'),
            'layout' => $layout,
            'style' => self::normalizeRowStyle($style),
            'columns' => $columns,
        ];
    }

    public static function block(string $type, array $props = []): array
    {
        $catalog = self::blockCatalog()[$type] ?? null;

        if (!$catalog) {
            throw new \InvalidArgumentException("Unknown webinar page block type [{$type}].");
        }

        return [
            'id' => self::newId('b'),
            'type' => $type,
            'props' => array_merge($catalog['props'], $props),
        ];
    }

    /**
     * The stored definition for a page, already normalized.
     */
    public static function definitionFor(Webinar $webinar, string $page): array
    {
        $pages = $webinar->settings['pages'] ?? [];
        $definition = is_array($pages) ? ($pages[$page] ?? null) : null;

        return self::normalize(is_array($definition) ? $definition : null, $page);
    }

    /**
     * True when the page should be rendered from blocks instead of the legacy
     * template.
     */
    public static function isBuilt(Webinar $webinar, string $page): bool
    {
        $definition = self::definitionFor($webinar, $page);

        return $definition['enabled'] && $definition['rows'] !== [];
    }

    /**
     * Persist a page definition without touching the rest of `settings`.
     */
    public static function store(Webinar $webinar, string $page, array $definition): array
    {
        $normalized = self::normalize($definition, $page);

        $settings = $webinar->settings ?? [];
        $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];
        $pages[$page] = $normalized;
        $settings['pages'] = $pages;

        $webinar->update(['settings' => $settings]);

        return $normalized;
    }

    /**
     * Drop a page definition (back to the legacy template).
     */
    public static function forget(Webinar $webinar, string $page): void
    {
        $settings = $webinar->settings ?? [];
        $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];
        unset($pages[$page]);
        $settings['pages'] = $pages;

        $webinar->update(['settings' => $settings]);
    }
}
