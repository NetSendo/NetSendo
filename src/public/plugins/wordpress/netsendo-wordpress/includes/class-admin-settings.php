<?php
/**
 * Admin Settings Page
 *
 * @package NetSendo_WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WP_Admin_Settings
 * Handles the plugin settings page
 */
class NetSendo_WP_Admin_Settings {

    /**
     * Option name in database
     */
    const OPTION_NAME = 'netsendo_wp_settings';

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
        add_action('wp_ajax_netsendo_wp_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_netsendo_wp_refresh_lists', [__CLASS__, 'ajax_refresh_lists']);
    }

    /**
     * Add menu page
     */
    public static function add_menu_page() {
        add_options_page(
            __('NetSendo Settings', 'netsendo-wordpress'),
            __('NetSendo', 'netsendo-wordpress'),
            'manage_options',
            'netsendo-wordpress',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'netsendo_wp_settings_group',
            self::OPTION_NAME,
            [__CLASS__, 'sanitize_settings']
        );

        // API Settings Section
        add_settings_section(
            'netsendo_wp_api_section',
            __('API Settings', 'netsendo-wordpress'),
            [__CLASS__, 'render_api_section'],
            'netsendo-wordpress'
        );

        add_settings_field(
            'api_url',
            __('NetSendo URL', 'netsendo-wordpress'),
            [__CLASS__, 'render_api_url_field'],
            'netsendo-wordpress',
            'netsendo_wp_api_section'
        );

        add_settings_field(
            'api_key',
            __('API Key', 'netsendo-wordpress'),
            [__CLASS__, 'render_api_key_field'],
            'netsendo-wordpress',
            'netsendo_wp_api_section'
        );

        // Form Settings Section
        add_settings_section(
            'netsendo_wp_form_section',
            __('Form Settings', 'netsendo-wordpress'),
            [__CLASS__, 'render_form_section'],
            'netsendo-wordpress'
        );

        add_settings_field(
            'default_list_id',
            __('Default List', 'netsendo-wordpress'),
            [__CLASS__, 'render_default_list_field'],
            'netsendo-wordpress',
            'netsendo_wp_form_section'
        );

        add_settings_field(
            'form_style',
            __('Default Form Style', 'netsendo-wordpress'),
            [__CLASS__, 'render_form_style_field'],
            'netsendo-wordpress',
            'netsendo_wp_form_section'
        );

        add_settings_field(
            'show_gdpr',
            __('GDPR Consent', 'netsendo-wordpress'),
            [__CLASS__, 'render_gdpr_field'],
            'netsendo-wordpress',
            'netsendo_wp_form_section'
        );

        // Content Gate Section
        add_settings_section(
            'netsendo_wp_gate_section',
            __('Content Gate Settings', 'netsendo-wordpress'),
            [__CLASS__, 'render_gate_section'],
            'netsendo-wordpress'
        );

        add_settings_field(
            'gate_percentage',
            __('Default Visible Percentage', 'netsendo-wordpress'),
            [__CLASS__, 'render_gate_percentage_field'],
            'netsendo-wordpress',
            'netsendo_wp_gate_section'
        );

        add_settings_field(
            'gate_message',
            __('Gate Message', 'netsendo-wordpress'),
            [__CLASS__, 'render_gate_message_field'],
            'netsendo-wordpress',
            'netsendo_wp_gate_section'
        );
    }

    /**
     * Render API section description
     */
    public static function render_api_section() {
        echo '<p>' . __('Enter your NetSendo API credentials. You can find your API Key in NetSendo under Settings > API Keys.', 'netsendo-wordpress') . '</p>';
    }

    /**
     * Render form section description
     */
    public static function render_form_section() {
        echo '<p>' . __('Configure default settings for subscription forms.', 'netsendo-wordpress') . '</p>';
    }

    /**
     * Render gate section description
     */
    public static function render_gate_section() {
        echo '<p>' . __('Configure default settings for content gating. Users must subscribe to view restricted content.', 'netsendo-wordpress') . '</p>';
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
        <p class="description"><?php _e('Your NetSendo installation URL (without trailing slash)', 'netsendo-wordpress'); ?></p>
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
            <?php _e('Test Connection', 'netsendo-wordpress'); ?>
        </button>
        <span id="netsendo_connection_status"></span>
        <p class="description"><?php _e('API Key from NetSendo (Settings > API Keys)', 'netsendo-wordpress'); ?></p>
        <?php
    }

    /**
     * Render Default List field
     */
    public static function render_default_list_field() {
        $settings = self::get_settings();
        $lists = self::get_cached_lists();
        $selected = $settings['default_list_id'] ?? '';
        ?>
        <div class="netsendo-list-field">
            <select name="<?php echo self::OPTION_NAME; ?>[default_list_id]"
                    id="netsendo_default_list"
                    class="regular-text netsendo-list-select">
                <option value=""><?php _e('— Select List —', 'netsendo-wordpress'); ?></option>
                <?php if ($lists): ?>
                    <?php foreach ($lists as $list): ?>
                        <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($selected, $list['id']); ?>>
                            <?php echo esc_html($list['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button type="button" id="netsendo_refresh_lists" class="button button-secondary">
                <?php _e('Refresh Lists', 'netsendo-wordpress'); ?>
            </button>
        </div>
        <p class="description"><?php _e('Default list for subscription forms. Can be overridden per form.', 'netsendo-wordpress'); ?></p>
        <?php
    }

    /**
     * Render Form Style field
     */
    public static function render_form_style_field() {
        $settings = self::get_settings();
        $selected = $settings['form_style'] ?? 'card';
        ?>
        <select name="<?php echo self::OPTION_NAME; ?>[form_style]" class="regular-text">
            <option value="inline" <?php selected($selected, 'inline'); ?>><?php _e('Inline', 'netsendo-wordpress'); ?></option>
            <option value="minimal" <?php selected($selected, 'minimal'); ?>><?php _e('Minimal', 'netsendo-wordpress'); ?></option>
            <option value="card" <?php selected($selected, 'card'); ?>><?php _e('Card (Recommended)', 'netsendo-wordpress'); ?></option>
        </select>
        <p class="description"><?php _e('Default style for subscription forms.', 'netsendo-wordpress'); ?></p>
        <?php
    }

    /**
     * Render GDPR field
     */
    public static function render_gdpr_field() {
        $settings = self::get_settings();
        $show_gdpr = isset($settings['show_gdpr']) ? (bool) $settings['show_gdpr'] : true;
        $gdpr_text = $settings['gdpr_text'] ?? __('I agree to receive email updates and accept the privacy policy.', 'netsendo-wordpress');
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo self::OPTION_NAME; ?>[show_gdpr]"
                   value="1"
                   <?php checked($show_gdpr); ?>>
            <?php _e('Show GDPR consent checkbox on forms', 'netsendo-wordpress'); ?>
        </label>
        <br><br>
        <input type="text"
               name="<?php echo self::OPTION_NAME; ?>[gdpr_text]"
               value="<?php echo esc_attr($gdpr_text); ?>"
               class="large-text"
               placeholder="<?php esc_attr_e('Consent text...', 'netsendo-wordpress'); ?>">
        <p class="description"><?php _e('Text displayed next to the consent checkbox.', 'netsendo-wordpress'); ?></p>
        <?php
    }

    /**
     * Render Gate Percentage field
     */
    public static function render_gate_percentage_field() {
        $settings = self::get_settings();
        $percentage = isset($settings['gate_percentage']) ? intval($settings['gate_percentage']) : 30;
        ?>
        <input type="range"
               name="<?php echo self::OPTION_NAME; ?>[gate_percentage]"
               id="gate_percentage_range"
               min="10"
               max="90"
               step="5"
               value="<?php echo esc_attr($percentage); ?>"
               style="width: 200px; vertical-align: middle;">
        <span id="gate_percentage_value" style="margin-left: 10px; font-weight: bold;"><?php echo $percentage; ?>%</span>
        <p class="description"><?php _e('Percentage of content visible before requiring subscription (10-90%).', 'netsendo-wordpress'); ?></p>
        <script>
            document.getElementById('gate_percentage_range').addEventListener('input', function() {
                document.getElementById('gate_percentage_value').textContent = this.value + '%';
            });
        </script>
        <?php
    }

    /**
     * Render Gate Message field
     */
    public static function render_gate_message_field() {
        $settings = self::get_settings();
        $message = $settings['gate_message'] ?? __('Subscribe to continue reading', 'netsendo-wordpress');
        ?>
        <input type="text"
               name="<?php echo self::OPTION_NAME; ?>[gate_message]"
               value="<?php echo esc_attr($message); ?>"
               class="large-text"
               placeholder="<?php esc_attr_e('Subscribe to continue reading', 'netsendo-wordpress'); ?>">
        <p class="description"><?php _e('Message displayed above the subscription form on gated content.', 'netsendo-wordpress'); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap netsendo-wordpress-settings">
            <h1>
                <img src="<?php echo NETSENDO_WP_PLUGIN_URL; ?>assets/netsendo-logo.png" alt="NetSendo" style="height: 30px; vertical-align: middle; margin-right: 10px;">
                <?php _e('NetSendo for WordPress', 'netsendo-wordpress'); ?>
            </h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('netsendo_wp_settings_group');
                do_settings_sections('netsendo-wordpress');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Shortcode Reference', 'netsendo-wordpress'); ?></h2>

            <div class="netsendo-shortcode-reference" style="background: #f6f7f7; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <h3 style="margin-top: 0;"><?php _e('Subscription Form', 'netsendo-wordpress'); ?></h3>
                <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; margin-bottom: 10px;">
                    [netsendo_form]
                </code>
                <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; margin-bottom: 15px;">
                    [netsendo_form list_id="123" style="card" button_text="Subscribe Now"]
                </code>
                <p class="description">
                    <strong><?php _e('Attributes:', 'netsendo-wordpress'); ?></strong><br>
                    • <code>list_id</code> - <?php _e('List ID (optional, uses default if not set)', 'netsendo-wordpress'); ?><br>
                    • <code>style</code> - <?php _e('Form style: inline, minimal, card', 'netsendo-wordpress'); ?><br>
                    • <code>button_text</code> - <?php _e('Custom button text', 'netsendo-wordpress'); ?><br>
                    • <code>show_name</code> - <?php _e('Show name field: yes/no', 'netsendo-wordpress'); ?>
                </p>

                <h3><?php _e('Content Gate', 'netsendo-wordpress'); ?></h3>
                <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; margin-bottom: 10px;">
                    [netsendo_gate]Your premium content here...[/netsendo_gate]
                </code>
                <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; margin-bottom: 15px;">
                    [netsendo_gate type="percentage" percentage="30" list_id="123"]Content...[/netsendo_gate]
                </code>
                <p class="description">
                    <strong><?php _e('Attributes:', 'netsendo-wordpress'); ?></strong><br>
                    • <code>type</code> - <?php _e('Gate type: percentage, subscribers_only, logged_in', 'netsendo-wordpress'); ?><br>
                    • <code>percentage</code> - <?php _e('Visible percentage (for percentage type)', 'netsendo-wordpress'); ?><br>
                    • <code>list_id</code> - <?php _e('List for subscription form', 'netsendo-wordpress'); ?><br>
                    • <code>message</code> - <?php _e('Custom gate message', 'netsendo-wordpress'); ?>
                </p>
            </div>
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
        $sanitized['default_list_id'] = isset($input['default_list_id']) ? sanitize_text_field($input['default_list_id']) : '';
        $sanitized['form_style'] = isset($input['form_style']) ? sanitize_text_field($input['form_style']) : 'card';
        $sanitized['show_gdpr'] = isset($input['show_gdpr']) ? (bool) $input['show_gdpr'] : false;
        $sanitized['gdpr_text'] = isset($input['gdpr_text']) ? sanitize_text_field($input['gdpr_text']) : '';
        $sanitized['gate_percentage'] = isset($input['gate_percentage']) ? min(90, max(10, intval($input['gate_percentage']))) : 30;
        $sanitized['gate_message'] = isset($input['gate_message']) ? sanitize_text_field($input['gate_message']) : '';

        // Clear cached data when settings change
        delete_transient('netsendo_wp_lists');

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
            'default_list_id' => '',
            'form_style' => 'card',
            'show_gdpr' => true,
            'gdpr_text' => __('I agree to receive email updates and accept the privacy policy.', 'netsendo-wordpress'),
            'gate_percentage' => 30,
            'gate_message' => __('Subscribe to continue reading', 'netsendo-wordpress'),
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
        $lists = get_transient('netsendo_wp_lists');

        if ($lists === false) {
            // Fetch from API
            $api = new NetSendo_WP_API();
            $lists = $api->get_lists();

            if ($lists) {
                set_transient('netsendo_wp_lists', $lists, HOUR_IN_SECONDS);
            }
        }

        self::$cached_lists = $lists;
        return $lists;
    }

    /**
     * AJAX: Test API connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer('netsendo_wp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'netsendo-wordpress')]);
        }

        $api = new NetSendo_WP_API();
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
        check_ajax_referer('netsendo_wp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'netsendo-wordpress')]);
        }

        // Clear cache
        delete_transient('netsendo_wp_lists');
        self::$cached_lists = null;

        // Fetch fresh lists
        $api = new NetSendo_WP_API();
        $lists = $api->get_lists();

        if ($lists) {
            set_transient('netsendo_wp_lists', $lists, HOUR_IN_SECONDS);
            wp_send_json_success([
                'message' => __('Lists refreshed successfully!', 'netsendo-wordpress'),
                'lists' => $lists,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to fetch lists. Check your API settings.', 'netsendo-wordpress')]);
        }
    }
}
