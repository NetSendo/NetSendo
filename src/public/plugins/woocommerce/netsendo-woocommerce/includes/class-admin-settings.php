<?php
/**
 * Admin Settings Page
 *
 * @package NetSendo_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WC_Admin_Settings
 * Handles the plugin settings page
 */
class NetSendo_WC_Admin_Settings {

    /**
     * Option name in database
     */
    const OPTION_NAME = 'netsendo_wc_settings';

    /**
     * @var array Cached lists from API
     */
    private static $cached_lists = null;

    /**
     * Initialize the settings
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_netsendo_wc_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_netsendo_wc_refresh_lists', [__CLASS__, 'ajax_refresh_lists']);
    }

    /**
     * Add menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('NetSendo Settings', 'netsendo-woocommerce'),
            __('NetSendo', 'netsendo-woocommerce'),
            'manage_woocommerce',
            'netsendo-woocommerce',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'netsendo_wc_settings_group',
            self::OPTION_NAME,
            [__CLASS__, 'sanitize_settings']
        );

        // API Settings Section
        add_settings_section(
            'netsendo_wc_api_section',
            __('API Settings', 'netsendo-woocommerce'),
            [__CLASS__, 'render_api_section'],
            'netsendo-woocommerce'
        );

        add_settings_field(
            'api_url',
            __('NetSendo URL', 'netsendo-woocommerce'),
            [__CLASS__, 'render_api_url_field'],
            'netsendo-woocommerce',
            'netsendo_wc_api_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'netsendo-woocommerce'),
            [__CLASS__, 'render_api_key_field'],
            'netsendo-woocommerce',
            'netsendo_wc_api_section'
        );

        // Default Lists Section
        add_settings_section(
            'netsendo_wc_lists_section',
            __('Default List Settings', 'netsendo-woocommerce'),
            [__CLASS__, 'render_lists_section'],
            'netsendo-woocommerce'
        );

        add_settings_field(
            'default_purchase_list_id',
            __('List after Purchase', 'netsendo-woocommerce'),
            [__CLASS__, 'render_purchase_list_field'],
            'netsendo-woocommerce',
            'netsendo_wc_lists_section'
        );

        add_settings_field(
            'default_pending_list_id',
            __('List after Pending Order', 'netsendo-woocommerce'),
            [__CLASS__, 'render_pending_list_field'],
            'netsendo-woocommerce',
            'netsendo_wc_lists_section'
        );

        add_settings_field(
            'default_redirect_url',
            __('Redirect URL after Purchase', 'netsendo-woocommerce'),
            [__CLASS__, 'render_redirect_url_field'],
            'netsendo-woocommerce',
            'netsendo_wc_lists_section'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_netsendo-woocommerce') {
            return;
        }

        wp_enqueue_style(
            'netsendo-wc-admin',
            NETSENDO_WC_PLUGIN_URL . 'assets/admin.css',
            [],
            NETSENDO_WC_VERSION
        );

        wp_enqueue_script(
            'netsendo-wc-admin',
            NETSENDO_WC_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            NETSENDO_WC_VERSION,
            true
        );

        wp_localize_script('netsendo-wc-admin', 'netsendoWC', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('netsendo_wc_nonce'),
            'strings' => [
                'testing' => __('Testing connection...', 'netsendo-woocommerce'),
                'refreshing' => __('Refreshing lists...', 'netsendo-woocommerce'),
            ],
        ]);
    }

    /**
     * Render API section description
     */
    public static function render_api_section() {
        echo '<p>' . __('Enter your NetSendo API credentials. You can find your API Key in NetSendo under Settings > API Keys.', 'netsendo-woocommerce') . '</p>';
    }

    /**
     * Render lists section description
     */
    public static function render_lists_section() {
        echo '<p>' . __('Configure default lists for automatic subscription. You can override these settings for individual products.', 'netsendo-woocommerce') . '</p>';
    }

    /**
     * Render API URL field
     */
    public static function render_api_url_field() {
        $settings = self::get_settings();
        ?>
        <input type="url"
               name="<?php echo self::OPTION_NAME; ?>[api_url]"
               id="netsendo_api_url"
               value="<?php echo esc_attr($settings['api_url'] ?? ''); ?>"
               class="regular-text"
               placeholder="https://your-netsendo-domain.com">
        <p class="description"><?php _e('Your NetSendo installation URL (without trailing slash)', 'netsendo-woocommerce'); ?></p>
        <?php
    }

    /**
     * Render API Key field
     */
    public static function render_api_key_field() {
        $settings = self::get_settings();
        ?>
        <input type="password"
               name="<?php echo self::OPTION_NAME; ?>[api_key]"
               id="netsendo_api_key"
               value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"
               class="regular-text"
               autocomplete="new-password">
        <button type="button" id="netsendo_test_connection" class="button button-secondary">
            <?php _e('Test Connection', 'netsendo-woocommerce'); ?>
        </button>
        <span id="netsendo_connection_status"></span>
        <p class="description"><?php _e('API Key from NetSendo (Settings > API Keys)', 'netsendo-woocommerce'); ?></p>
        <?php
    }

    /**
     * Render Purchase List field
     */
    public static function render_purchase_list_field() {
        $settings = self::get_settings();
        $lists = self::get_cached_lists();
        $selected = $settings['default_purchase_list_id'] ?? '';
        $manual_id = $settings['default_purchase_list_manual'] ?? '';
        $use_manual = !empty($manual_id) && (empty($selected) || $selected === 'manual');
        ?>
        <div class="netsendo-list-field">
            <select name="<?php echo self::OPTION_NAME; ?>[default_purchase_list_id]" id="netsendo_purchase_list" class="regular-text netsendo-list-select">
                <option value=""><?php _e('— Select List —', 'netsendo-woocommerce'); ?></option>
                <?php if ($lists): ?>
                    <?php foreach ($lists as $list): ?>
                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($selected, $list['id']); ?>>
                            <?php echo esc_html($list['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
                <option value="manual" <?php selected($selected, 'manual'); ?>><?php _e('— Enter ID manually —', 'netsendo-woocommerce'); ?></option>
            </select>
            <button type="button" id="netsendo_refresh_lists" class="button button-secondary">
                <?php _e('Refresh Lists', 'netsendo-woocommerce'); ?>
            </button>
        </div>
        <div class="netsendo-manual-id" style="margin-top: 8px; <?php echo $use_manual ? '' : 'display: none;'; ?>">
            <input type="text"
                   name="<?php echo self::OPTION_NAME; ?>[default_purchase_list_manual]"
                   value="<?php echo esc_attr($manual_id); ?>"
                   class="regular-text"
                   placeholder="<?php _e('Enter list ID', 'netsendo-woocommerce'); ?>">
        </div>
        <p class="description"><?php _e('Customers will be added to this list after completing a purchase. Select from dropdown or enter ID manually.', 'netsendo-woocommerce'); ?></p>
        <?php
    }

    /**
     * Render Pending List field
     */
    public static function render_pending_list_field() {
        $settings = self::get_settings();
        $lists = self::get_cached_lists();
        $selected = $settings['default_pending_list_id'] ?? '';
        $manual_id = $settings['default_pending_list_manual'] ?? '';
        $use_manual = !empty($manual_id) && (empty($selected) || $selected === 'manual');
        ?>
        <div class="netsendo-list-field">
            <select name="<?php echo self::OPTION_NAME; ?>[default_pending_list_id]" class="regular-text netsendo-list-select">
                <option value=""><?php _e('— Select List —', 'netsendo-woocommerce'); ?></option>
                <?php if ($lists): ?>
                    <?php foreach ($lists as $list): ?>
                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($selected, $list['id']); ?>>
                            <?php echo esc_html($list['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
                <option value="manual" <?php selected($selected, 'manual'); ?>><?php _e('— Enter ID manually —', 'netsendo-woocommerce'); ?></option>
            </select>
        </div>
        <div class="netsendo-manual-id" style="margin-top: 8px; <?php echo $use_manual ? '' : 'display: none;'; ?>">
            <input type="text"
                   name="<?php echo self::OPTION_NAME; ?>[default_pending_list_manual]"
                   value="<?php echo esc_attr($manual_id); ?>"
                   class="regular-text"
                   placeholder="<?php _e('Enter list ID', 'netsendo-woocommerce'); ?>">
        </div>
        <p class="description"><?php _e('Customers will be added to this list when they create an order but have not yet paid (abandoned cart recovery). Select from dropdown or enter ID manually.', 'netsendo-woocommerce'); ?></p>
        <?php
    }

    /**
     * Render Redirect URL field
     */
    public static function render_redirect_url_field() {
        $settings = self::get_settings();
        $external_pages = self::get_cached_external_pages();
        $selected_page = $settings['default_external_page_id'] ?? '';
        $redirect_url = $settings['default_redirect_url'] ?? '';
        ?>
        <div class="netsendo-redirect-field">
            <label style="display: block; margin-bottom: 8px;">
                <strong><?php _e('NetSendo External Page:', 'netsendo-woocommerce'); ?></strong>
            </label>
            <select name="<?php echo self::OPTION_NAME; ?>[default_external_page_id]" class="regular-text" style="margin-bottom: 10px;">
                <option value=""><?php _e('— No NetSendo page —', 'netsendo-woocommerce'); ?></option>
                <?php if ($external_pages): ?>
                    <?php foreach ($external_pages as $page): ?>
                        <option value="<?php echo esc_attr($page['id']); ?>"
                                data-url="<?php echo esc_attr($page['url'] ?? ''); ?>"
                                <?php selected($selected_page, $page['id']); ?>>
                            <?php echo esc_html($page['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="description" style="margin-bottom: 15px;"><?php _e('Select a NetSendo external page to use as thank you page with sales funnel functionality.', 'netsendo-woocommerce'); ?></p>

            <label style="display: block; margin-bottom: 8px;">
                <strong><?php _e('Or Custom URL:', 'netsendo-woocommerce'); ?></strong>
            </label>
            <input type="url"
                   name="<?php echo self::OPTION_NAME; ?>[default_redirect_url]"
                   value="<?php echo esc_attr($redirect_url); ?>"
                   class="regular-text"
                   placeholder="https://example.com/thank-you">
            <p class="description"><?php _e('Enter a custom URL if not using NetSendo external page. The following parameters will be added: order_id, email, product_id.', 'netsendo-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap netsendo-woocommerce-settings">
            <h1>
                <img src="<?php echo NETSENDO_WC_PLUGIN_URL; ?>assets/netsendo-logo.png" alt="NetSendo" style="height: 30px; vertical-align: middle; margin-right: 10px;">
                <?php _e('NetSendo for WooCommerce', 'netsendo-woocommerce'); ?>
            </h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('netsendo_wc_settings_group');
                do_settings_sections('netsendo-woocommerce');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Product-Level Settings', 'netsendo-woocommerce'); ?></h2>
            <p><?php _e('You can override these default settings for individual products. Edit any WooCommerce product and look for the "NetSendo" section in the Product Data panel.', 'netsendo-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];

        $sanitized['api_url'] = isset($input['api_url']) ? esc_url_raw(rtrim($input['api_url'], '/')) : '';
        $sanitized['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $sanitized['default_purchase_list_id'] = isset($input['default_purchase_list_id']) ? sanitize_text_field($input['default_purchase_list_id']) : '';
        $sanitized['default_purchase_list_manual'] = isset($input['default_purchase_list_manual']) ? sanitize_text_field($input['default_purchase_list_manual']) : '';
        $sanitized['default_pending_list_id'] = isset($input['default_pending_list_id']) ? sanitize_text_field($input['default_pending_list_id']) : '';
        $sanitized['default_pending_list_manual'] = isset($input['default_pending_list_manual']) ? sanitize_text_field($input['default_pending_list_manual']) : '';
        $sanitized['default_external_page_id'] = isset($input['default_external_page_id']) ? sanitize_text_field($input['default_external_page_id']) : '';
        $sanitized['default_redirect_url'] = isset($input['default_redirect_url']) ? esc_url_raw($input['default_redirect_url']) : '';

        // Preserve user_id if it was set by API (not editable by user)
        $existing = get_option(self::OPTION_NAME, []);
        if (!empty($existing['user_id'])) {
            $sanitized['user_id'] = (int) $existing['user_id'];
        }

        // Clear cached data when settings change
        delete_transient('netsendo_wc_lists');
        delete_transient('netsendo_wc_external_pages');

        return $sanitized;
    }

    /**
     * Get plugin settings
     *
     * @return array
     */
    public static function get_settings() {
        return get_option(self::OPTION_NAME, [
            'api_key' => '',
            'api_url' => '',
            'user_id' => '',
            'default_purchase_list_id' => '',
            'default_purchase_list_manual' => '',
            'default_pending_list_id' => '',
            'default_pending_list_manual' => '',
            'default_external_page_id' => '',
            'default_redirect_url' => '',
        ]);
    }

    /**
     * Get cached lists from API
     *
     * @return array|false
     */
    public static function get_cached_lists() {
        if (self::$cached_lists !== null) {
            return self::$cached_lists;
        }

        // Try to get from transient
        $lists = get_transient('netsendo_wc_lists');

        if ($lists === false) {
            // Fetch from API
            $api = new NetSendo_WC_API();
            $lists = $api->get_lists();

            if ($lists) {
                set_transient('netsendo_wc_lists', $lists, HOUR_IN_SECONDS);
            }
        }

        self::$cached_lists = $lists;
        return $lists;
    }

    /**
     * Get cached external pages from API
     *
     * @return array|false
     */
    public static function get_cached_external_pages() {
        // Try to get from transient
        $pages = get_transient('netsendo_wc_external_pages');

        if ($pages === false) {
            // Fetch from API
            $api = new NetSendo_WC_API();
            $pages = $api->get_external_pages();

            if ($pages) {
                set_transient('netsendo_wc_external_pages', $pages, HOUR_IN_SECONDS);
            }
        }

        return $pages;
    }

    /**
     * Get effective list ID (from dropdown or manual input)
     *
     * @param string $type 'purchase' or 'pending'
     * @return string|null
     */
    public static function get_effective_list_id($type = 'purchase') {
        $settings = self::get_settings();

        $list_id_key = "default_{$type}_list_id";
        $manual_key = "default_{$type}_list_manual";

        $selected = $settings[$list_id_key] ?? '';
        $manual = $settings[$manual_key] ?? '';

        if ($selected === 'manual' && !empty($manual)) {
            return $manual;
        }

        return !empty($selected) && $selected !== 'manual' ? $selected : null;
    }

    /**
     * Get redirect URL (from external page or custom URL)
     *
     * @return string|null
     */
    public static function get_redirect_url() {
        $settings = self::get_settings();
        $api = new NetSendo_WC_API();

        // Check for NetSendo external page first
        $external_page_id = $settings['default_external_page_id'] ?? '';
        if (!empty($external_page_id)) {
            $pages = self::get_cached_external_pages();
            if ($pages) {
                foreach ($pages as $page) {
                    if ($page['id'] == $external_page_id) {
                        // Return the URL from the external page
                        return $page['url'] ?? '';
                    }
                }
            }
        }

        // Fall back to custom redirect URL
        return $settings['default_redirect_url'] ?? null;
    }

    /**
     * AJAX: Test API connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer('netsendo_wc_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'netsendo-woocommerce')]);
        }

        $api = new NetSendo_WC_API();
        $result = $api->test_connection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Refresh lists from API
     */
    public static function ajax_refresh_lists() {
        check_ajax_referer('netsendo_wc_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'netsendo-woocommerce')]);
        }

        // Clear cache
        delete_transient('netsendo_wc_lists');
        self::$cached_lists = null;

        // Fetch fresh lists
        $api = new NetSendo_WC_API();
        $lists = $api->get_lists();

        if ($lists) {
            set_transient('netsendo_wc_lists', $lists, HOUR_IN_SECONDS);
            wp_send_json_success([
                'message' => __('Lists refreshed successfully!', 'netsendo-woocommerce'),
                'lists' => $lists,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to fetch lists. Check your API settings.', 'netsendo-woocommerce')]);
        }
    }
}
