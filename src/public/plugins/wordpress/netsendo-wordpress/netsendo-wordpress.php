<?php
/**
 * Plugin Name: NetSendo for WordPress
 * Plugin URI: https://netsendo.com/integrations/wordpress
 * Description: Professional newsletter subscription forms, content gating, and email marketing integration for WordPress bloggers and content creators.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: NetSendo
 * Author URI: https://netsendo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: netsendo-wordpress
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NETSENDO_WP_VERSION', '1.0.0');
define('NETSENDO_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NETSENDO_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NETSENDO_WP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class NetSendo_WordPress {

    /**
     * Singleton instance
     * @var NetSendo_WordPress
     */
    private static $instance = null;

    /**
     * Get singleton instance
     * @return NetSendo_WordPress
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once NETSENDO_WP_PLUGIN_DIR . 'includes/class-netsendo-api.php';
        require_once NETSENDO_WP_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once NETSENDO_WP_PLUGIN_DIR . 'includes/class-forms.php';
        require_once NETSENDO_WP_PLUGIN_DIR . 'includes/class-content-gate.php';
        require_once NETSENDO_WP_PLUGIN_DIR . 'includes/class-gutenberg.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Initialize components
        add_action('init', [NetSendo_WP_Admin_Settings::class, 'init']);
        add_action('init', [NetSendo_WP_Forms::class, 'init']);
        add_action('init', [NetSendo_WP_Content_Gate::class, 'init']);
        add_action('init', [NetSendo_WP_Gutenberg::class, 'init']);

        // AJAX handlers
        add_action('wp_ajax_netsendo_wp_subscribe', [$this, 'ajax_subscribe']);
        add_action('wp_ajax_nopriv_netsendo_wp_subscribe', [$this, 'ajax_subscribe']);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'netsendo-wordpress',
            false,
            dirname(NETSENDO_WP_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Additional initialization if needed
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'netsendo-wp-frontend',
            NETSENDO_WP_PLUGIN_URL . 'assets/frontend.css',
            [],
            NETSENDO_WP_VERSION
        );

        wp_enqueue_script(
            'netsendo-wp-frontend',
            NETSENDO_WP_PLUGIN_URL . 'assets/frontend.js',
            ['jquery'],
            NETSENDO_WP_VERSION,
            true
        );

        wp_localize_script('netsendo-wp-frontend', 'netsendoWP', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('netsendo_wp_nonce'),
            'strings' => [
                'subscribing' => __('Subscribing...', 'netsendo-wordpress'),
                'success' => __('Thank you for subscribing!', 'netsendo-wordpress'),
                'error' => __('An error occurred. Please try again.', 'netsendo-wordpress'),
                'invalid_email' => __('Please enter a valid email address.', 'netsendo-wordpress'),
                'consent_required' => __('Please accept the privacy policy.', 'netsendo-wordpress'),
            ],
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'netsendo') === false) {
            return;
        }

        wp_enqueue_style(
            'netsendo-wp-admin',
            NETSENDO_WP_PLUGIN_URL . 'assets/admin.css',
            [],
            NETSENDO_WP_VERSION
        );

        wp_enqueue_script(
            'netsendo-wp-admin',
            NETSENDO_WP_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            NETSENDO_WP_VERSION,
            true
        );

        wp_localize_script('netsendo-wp-admin', 'netsendoWPAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('netsendo_wp_admin_nonce'),
            'strings' => [
                'testing' => __('Testing connection...', 'netsendo-wordpress'),
                'refreshing' => __('Refreshing lists...', 'netsendo-wordpress'),
                'success' => __('Connection successful!', 'netsendo-wordpress'),
                'error' => __('Connection failed.', 'netsendo-wordpress'),
            ],
        ]);
    }

    /**
     * AJAX handler for subscription
     */
    public function ajax_subscribe() {
        check_ajax_referer('netsendo_wp_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        $consent = isset($_POST['consent']) ? (bool) $_POST['consent'] : true;

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address.', 'netsendo-wordpress'),
            ]);
        }

        // Validate list
        if (!$list_id) {
            $settings = NetSendo_WP_Admin_Settings::get_settings();
            $list_id = $settings['default_list_id'] ?? 0;
        }

        if (!$list_id) {
            wp_send_json_error([
                'message' => __('No list configured. Please contact the site administrator.', 'netsendo-wordpress'),
            ]);
        }

        // Add subscriber
        $api = new NetSendo_WP_API();
        $result = $api->add_subscriber($list_id, $email, $name, [
            'source' => 'wordpress',
            'page_url' => isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '',
            'form_id' => isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '',
        ]);

        if ($result) {
            // Set cookie for content unlock
            $unlock_key = 'netsendo_subscribed_' . md5($email);
            setcookie($unlock_key, '1', time() + (365 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

            wp_send_json_success([
                'message' => __('Thank you for subscribing!', 'netsendo-wordpress'),
                'unlock_key' => $unlock_key,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Subscription failed. Please try again.', 'netsendo-wordpress'),
            ]);
        }
    }
}

/**
 * Initialize the plugin
 */
function netsendo_wp_init() {
    return NetSendo_WordPress::instance();
}
add_action('plugins_loaded', 'netsendo_wp_init', 5);

/**
 * Plugin activation hook
 */
function netsendo_wp_activate() {
    if (!get_option('netsendo_wp_settings')) {
        update_option('netsendo_wp_settings', [
            'api_key' => '',
            'api_url' => '',
            'default_list_id' => '',
            'gate_percentage' => 30,
            'gate_message' => __('Subscribe to continue reading', 'netsendo-wordpress'),
            'form_style' => 'card',
            'show_gdpr' => true,
            'gdpr_text' => __('I agree to receive email updates and accept the privacy policy.', 'netsendo-wordpress'),
        ]);
    }
}
register_activation_hook(__FILE__, 'netsendo_wp_activate');

/**
 * Plugin deactivation hook
 */
function netsendo_wp_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'netsendo_wp_deactivate');

/**
 * Add settings link to plugins page
 */
function netsendo_wp_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=netsendo-wordpress') . '">' .
                     __('Settings', 'netsendo-wordpress') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . NETSENDO_WP_PLUGIN_BASENAME, 'netsendo_wp_plugin_action_links');
