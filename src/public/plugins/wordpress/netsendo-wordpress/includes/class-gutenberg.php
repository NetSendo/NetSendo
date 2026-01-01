<?php
/**
 * Gutenberg Blocks Handler
 *
 * @package NetSendo_WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WP_Gutenberg
 * Handles Gutenberg block registration and rendering
 */
class NetSendo_WP_Gutenberg {

    /**
     * Initialize Gutenberg blocks
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_editor_assets']);
    }

    /**
     * Register Gutenberg blocks
     */
    public static function register_blocks() {
        // Register subscription form block
        register_block_type('netsendo/subscription-form', [
            'editor_script' => 'netsendo-wp-blocks-editor',
            'editor_style' => 'netsendo-wp-blocks-editor-style',
            'render_callback' => [__CLASS__, 'render_subscription_form_block'],
            'attributes' => [
                'listId' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'style' => [
                    'type' => 'string',
                    'default' => 'card',
                ],
                'buttonText' => [
                    'type' => 'string',
                    'default' => 'Subscribe',
                ],
                'showName' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'description' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Register content gate block
        register_block_type('netsendo/content-gate', [
            'editor_script' => 'netsendo-wp-blocks-editor',
            'editor_style' => 'netsendo-wp-blocks-editor-style',
            'render_callback' => [__CLASS__, 'render_content_gate_block'],
            'attributes' => [
                'type' => [
                    'type' => 'string',
                    'default' => 'percentage',
                ],
                'percentage' => [
                    'type' => 'number',
                    'default' => 30,
                ],
                'listId' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'message' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    /**
     * Enqueue editor assets
     */
    public static function enqueue_editor_assets() {
        // Register block editor script
        wp_register_script(
            'netsendo-wp-blocks-editor',
            NETSENDO_WP_PLUGIN_URL . 'blocks/editor.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor'],
            NETSENDO_WP_VERSION,
            true
        );

        // Get lists for block editor
        $lists = NetSendo_WP_Admin_Settings::get_cached_lists();
        $settings = NetSendo_WP_Admin_Settings::get_settings();

        wp_localize_script('netsendo-wp-blocks-editor', 'netsendoBlocksData', [
            'lists' => $lists ?: [],
            'defaultListId' => $settings['default_list_id'] ?? '',
            'defaultPercentage' => $settings['gate_percentage'] ?? 30,
            'defaultMessage' => $settings['gate_message'] ?? __('Subscribe to continue reading', 'netsendo-wordpress'),
            'styles' => [
                ['value' => 'inline', 'label' => __('Inline', 'netsendo-wordpress')],
                ['value' => 'minimal', 'label' => __('Minimal', 'netsendo-wordpress')],
                ['value' => 'card', 'label' => __('Card', 'netsendo-wordpress')],
            ],
            'gateTypes' => [
                ['value' => 'percentage', 'label' => __('Percentage visible', 'netsendo-wordpress')],
                ['value' => 'subscribers_only', 'label' => __('Subscribers only', 'netsendo-wordpress')],
                ['value' => 'logged_in', 'label' => __('Logged in only', 'netsendo-wordpress')],
            ],
        ]);

        // Register block editor style
        wp_register_style(
            'netsendo-wp-blocks-editor-style',
            NETSENDO_WP_PLUGIN_URL . 'blocks/editor.css',
            ['wp-edit-blocks'],
            NETSENDO_WP_VERSION
        );
    }

    /**
     * Render subscription form block
     */
    public static function render_subscription_form_block($attributes) {
        $atts = [
            'list_id' => $attributes['listId'] ?? '',
            'style' => $attributes['style'] ?? 'card',
            'button_text' => $attributes['buttonText'] ?? __('Subscribe', 'netsendo-wordpress'),
            'show_name' => !empty($attributes['showName']) ? 'yes' : 'no',
            'title' => $attributes['title'] ?? '',
            'description' => $attributes['description'] ?? '',
        ];

        return NetSendo_WP_Forms::render_shortcode($atts);
    }

    /**
     * Render content gate block
     */
    public static function render_content_gate_block($attributes, $content) {
        $atts = [
            'type' => $attributes['type'] ?? 'percentage',
            'percentage' => $attributes['percentage'] ?? 30,
            'list_id' => $attributes['listId'] ?? '',
            'message' => $attributes['message'] ?? '',
        ];

        return NetSendo_WP_Content_Gate::render_shortcode($atts, $content);
    }
}
