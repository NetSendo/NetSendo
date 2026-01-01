<?php
/**
 * Content Gate Handler
 *
 * @package NetSendo_WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WP_Content_Gate
 * Handles content restriction and gating
 */
class NetSendo_WP_Content_Gate {

    /**
     * Initialize content gate
     */
    public static function init() {
        add_shortcode('netsendo_gate', [__CLASS__, 'render_shortcode']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post', [__CLASS__, 'save_meta_box']);
        add_filter('the_content', [__CLASS__, 'filter_content'], 99);
    }

    /**
     * Render content gate shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Content inside shortcode
     * @return string
     */
    public static function render_shortcode($atts, $content = null) {
        $settings = NetSendo_WP_Admin_Settings::get_settings();

        $atts = shortcode_atts([
            'type' => 'percentage',
            'percentage' => $settings['gate_percentage'] ?? 30,
            'list_id' => $settings['default_list_id'] ?? '',
            'message' => $settings['gate_message'] ?? __('Subscribe to continue reading', 'netsendo-wordpress'),
        ], $atts, 'netsendo_gate');

        // Check if user has access
        if (self::user_has_access()) {
            return do_shortcode($content);
        }

        return self::render_gated_content($content, $atts, $settings);
    }

    /**
     * Check if current user has access to gated content
     *
     * @return bool
     */
    public static function user_has_access() {
        // Logged in users always have access (if they're subscribers in WP)
        if (is_user_logged_in()) {
            return true;
        }

        // Check for subscription cookie
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'netsendo_subscribed_') === 0 && $value === '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * Render gated content with restriction
     *
     * @param string $content
     * @param array $atts
     * @param array $settings
     * @return string
     */
    public static function render_gated_content($content, $atts, $settings) {
        $type = $atts['type'];
        $percentage = intval($atts['percentage']);
        $list_id = $atts['list_id'];
        $message = $atts['message'];

        // Process content
        $content = do_shortcode($content);

        switch ($type) {
            case 'percentage':
                return self::render_percentage_gate($content, $percentage, $list_id, $message, $settings);

            case 'subscribers_only':
                return self::render_subscribers_only_gate($content, $list_id, $message, $settings);

            case 'logged_in':
                return self::render_logged_in_gate($content, $message);

            default:
                return self::render_percentage_gate($content, $percentage, $list_id, $message, $settings);
        }
    }

    /**
     * Render percentage-based gate
     */
    private static function render_percentage_gate($content, $percentage, $list_id, $message, $settings) {
        // Calculate visible content length
        $content_length = mb_strlen(strip_tags($content));
        $visible_length = intval($content_length * ($percentage / 100));

        // Find a good cut-off point (end of word/sentence)
        $visible_content = mb_substr($content, 0, $visible_length);

        // Try to cut at last complete paragraph or sentence
        $last_period = mb_strrpos($visible_content, '.');
        $last_para = mb_strrpos($visible_content, '</p>');

        $cut_point = max($last_period, $last_para);
        if ($cut_point > $visible_length * 0.6) {
            $visible_content = mb_substr($content, 0, $cut_point + 1);
        }

        // Generate gate ID
        $gate_id = 'netsendo-gate-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($gate_id); ?>" class="netsendo-content-gate netsendo-content-gate--percentage">
            <div class="netsendo-content-gate__visible">
                <?php echo $visible_content; ?>
            </div>

            <div class="netsendo-content-gate__overlay">
                <div class="netsendo-content-gate__fade"></div>
                <div class="netsendo-content-gate__box">
                    <div class="netsendo-content-gate__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <h3 class="netsendo-content-gate__title"><?php echo esc_html($message); ?></h3>
                    <p class="netsendo-content-gate__subtitle">
                        <?php _e('Enter your email to unlock the full article', 'netsendo-wordpress'); ?>
                    </p>
                    <?php self::render_gate_form($gate_id, $list_id, $settings); ?>
                </div>
            </div>

            <div class="netsendo-content-gate__hidden" style="display: none;">
                <?php echo mb_substr($content, mb_strlen($visible_content)); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render subscribers-only gate
     */
    private static function render_subscribers_only_gate($content, $list_id, $message, $settings) {
        $gate_id = 'netsendo-gate-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div id="<?php echo esc_attr($gate_id); ?>" class="netsendo-content-gate netsendo-content-gate--subscribers-only">
            <div class="netsendo-content-gate__locked">
                <div class="netsendo-content-gate__box netsendo-content-gate__box--large">
                    <div class="netsendo-content-gate__icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="M9 12l2 2 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="netsendo-content-gate__title"><?php echo esc_html($message); ?></h3>
                    <p class="netsendo-content-gate__subtitle">
                        <?php _e('This content is exclusive to our subscribers', 'netsendo-wordpress'); ?>
                    </p>
                    <?php self::render_gate_form($gate_id, $list_id, $settings); ?>
                </div>
            </div>

            <div class="netsendo-content-gate__hidden" style="display: none;">
                <?php echo $content; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render logged-in only gate
     */
    private static function render_logged_in_gate($content, $message) {
        ob_start();
        ?>
        <div class="netsendo-content-gate netsendo-content-gate--logged-in">
            <div class="netsendo-content-gate__box netsendo-content-gate__box--large">
                <div class="netsendo-content-gate__icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <h3 class="netsendo-content-gate__title"><?php echo esc_html($message); ?></h3>
                <p class="netsendo-content-gate__subtitle">
                    <?php _e('Please log in to view this content', 'netsendo-wordpress'); ?>
                </p>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="netsendo-content-gate__login-btn">
                    <?php _e('Log In', 'netsendo-wordpress'); ?>
                </a>
                <?php if (get_option('users_can_register')): ?>
                    <p class="netsendo-content-gate__register">
                        <?php _e("Don't have an account?", 'netsendo-wordpress'); ?>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>">
                            <?php _e('Register', 'netsendo-wordpress'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render subscription form inside gate
     */
    private static function render_gate_form($gate_id, $list_id, $settings) {
        $show_gdpr = isset($settings['show_gdpr']) ? (bool) $settings['show_gdpr'] : true;
        $gdpr_text = $settings['gdpr_text'] ?? '';
        ?>
        <form class="netsendo-content-gate__form" data-gate-id="<?php echo esc_attr($gate_id); ?>" data-list-id="<?php echo esc_attr($list_id); ?>">
            <div class="netsendo-content-gate__input-group">
                <input type="email"
                       name="email"
                       class="netsendo-content-gate__input"
                       placeholder="<?php esc_attr_e('Enter your email', 'netsendo-wordpress'); ?>"
                       required>
                <button type="submit" class="netsendo-content-gate__submit">
                    <span class="netsendo-content-gate__submit-text"><?php _e('Unlock', 'netsendo-wordpress'); ?></span>
                    <span class="netsendo-content-gate__submit-loading" style="display: none;">
                        <svg class="netsendo-spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="60" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" dur="1s" from="0 12 12" to="360 12 12" repeatCount="indefinite"/>
                            </circle>
                        </svg>
                    </span>
                </button>
            </div>

            <?php if ($show_gdpr && !empty($gdpr_text)): ?>
                <label class="netsendo-content-gate__gdpr">
                    <input type="checkbox" name="consent" value="1" required>
                    <span><?php echo esc_html($gdpr_text); ?></span>
                </label>
            <?php endif; ?>

            <div class="netsendo-content-gate__message" style="display: none;"></div>
        </form>
        <?php
    }

    /**
     * Add meta box to post editor
     */
    public static function add_meta_box() {
        $post_types = apply_filters('netsendo_gate_post_types', ['post', 'page']);

        add_meta_box(
            'netsendo_content_gate',
            __('NetSendo Content Gate', 'netsendo-wordpress'),
            [__CLASS__, 'render_meta_box'],
            $post_types,
            'side',
            'default'
        );
    }

    /**
     * Render meta box content
     */
    public static function render_meta_box($post) {
        wp_nonce_field('netsendo_gate_meta_box', 'netsendo_gate_nonce');

        $enabled = get_post_meta($post->ID, '_netsendo_gate_enabled', true);
        $type = get_post_meta($post->ID, '_netsendo_gate_type', true) ?: 'percentage';
        $percentage = get_post_meta($post->ID, '_netsendo_gate_percentage', true) ?: 30;
        $list_id = get_post_meta($post->ID, '_netsendo_gate_list_id', true);

        $lists = NetSendo_WP_Admin_Settings::get_cached_lists();
        $settings = NetSendo_WP_Admin_Settings::get_settings();
        ?>
        <p>
            <label>
                <input type="checkbox" name="netsendo_gate_enabled" value="1" <?php checked($enabled, '1'); ?>>
                <?php _e('Enable content gate for this post', 'netsendo-wordpress'); ?>
            </label>
        </p>

        <div class="netsendo-gate-options" style="<?php echo $enabled ? '' : 'display: none;'; ?>">
            <p>
                <label for="netsendo_gate_type"><strong><?php _e('Gate Type:', 'netsendo-wordpress'); ?></strong></label><br>
                <select name="netsendo_gate_type" id="netsendo_gate_type" style="width: 100%;">
                    <option value="percentage" <?php selected($type, 'percentage'); ?>><?php _e('Percentage visible', 'netsendo-wordpress'); ?></option>
                    <option value="subscribers_only" <?php selected($type, 'subscribers_only'); ?>><?php _e('Subscribers only', 'netsendo-wordpress'); ?></option>
                    <option value="logged_in" <?php selected($type, 'logged_in'); ?>><?php _e('Logged in only', 'netsendo-wordpress'); ?></option>
                </select>
            </p>

            <p class="netsendo-percentage-option" style="<?php echo $type === 'percentage' ? '' : 'display: none;'; ?>">
                <label for="netsendo_gate_percentage"><strong><?php _e('Visible %:', 'netsendo-wordpress'); ?></strong></label><br>
                <input type="range" name="netsendo_gate_percentage" id="netsendo_gate_percentage"
                       min="10" max="90" step="5" value="<?php echo esc_attr($percentage); ?>" style="width: 100%;">
                <span id="netsendo_percentage_display"><?php echo $percentage; ?>%</span>
            </p>

            <p class="netsendo-list-option">
                <label for="netsendo_gate_list_id"><strong><?php _e('Subscription List:', 'netsendo-wordpress'); ?></strong></label><br>
                <select name="netsendo_gate_list_id" id="netsendo_gate_list_id" style="width: 100%;">
                    <option value=""><?php _e('— Default —', 'netsendo-wordpress'); ?></option>
                    <?php if ($lists): ?>
                        <?php foreach ($lists as $list): ?>
                            <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($list_id, $list['id']); ?>>
                                <?php echo esc_html($list['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </p>
        </div>

        <script>
            jQuery(function($) {
                $('input[name="netsendo_gate_enabled"]').on('change', function() {
                    $('.netsendo-gate-options').toggle(this.checked);
                });
                $('select[name="netsendo_gate_type"]').on('change', function() {
                    $('.netsendo-percentage-option').toggle(this.value === 'percentage');
                    $('.netsendo-list-option').toggle(this.value !== 'logged_in');
                });
                $('#netsendo_gate_percentage').on('input', function() {
                    $('#netsendo_percentage_display').text(this.value + '%');
                });
            });
        </script>
        <?php
    }

    /**
     * Save meta box data
     */
    public static function save_meta_box($post_id) {
        if (!isset($_POST['netsendo_gate_nonce']) ||
            !wp_verify_nonce($_POST['netsendo_gate_nonce'], 'netsendo_gate_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['netsendo_gate_enabled']) ? '1' : '';
        $type = isset($_POST['netsendo_gate_type']) ? sanitize_text_field($_POST['netsendo_gate_type']) : 'percentage';
        $percentage = isset($_POST['netsendo_gate_percentage']) ? intval($_POST['netsendo_gate_percentage']) : 30;
        $list_id = isset($_POST['netsendo_gate_list_id']) ? sanitize_text_field($_POST['netsendo_gate_list_id']) : '';

        update_post_meta($post_id, '_netsendo_gate_enabled', $enabled);
        update_post_meta($post_id, '_netsendo_gate_type', $type);
        update_post_meta($post_id, '_netsendo_gate_percentage', $percentage);
        update_post_meta($post_id, '_netsendo_gate_list_id', $list_id);
    }

    /**
     * Filter content for posts with gate enabled
     */
    public static function filter_content($content) {
        // Only filter on single posts/pages in main query
        if (!is_singular() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $enabled = get_post_meta($post_id, '_netsendo_gate_enabled', true);

        if ($enabled !== '1') {
            return $content;
        }

        // Check if user has access
        if (self::user_has_access()) {
            return $content;
        }

        $settings = NetSendo_WP_Admin_Settings::get_settings();

        $atts = [
            'type' => get_post_meta($post_id, '_netsendo_gate_type', true) ?: 'percentage',
            'percentage' => get_post_meta($post_id, '_netsendo_gate_percentage', true) ?: ($settings['gate_percentage'] ?? 30),
            'list_id' => get_post_meta($post_id, '_netsendo_gate_list_id', true) ?: ($settings['default_list_id'] ?? ''),
            'message' => $settings['gate_message'] ?? __('Subscribe to continue reading', 'netsendo-wordpress'),
        ];

        return self::render_gated_content($content, $atts, $settings);
    }
}
