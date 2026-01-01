<?php
/**
 * Subscription Forms Handler
 *
 * @package NetSendo_WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WP_Forms
 * Handles subscription forms shortcode and widget
 */
class NetSendo_WP_Forms {

    /**
     * Initialize forms
     */
    public static function init() {
        add_shortcode('netsendo_form', [__CLASS__, 'render_shortcode']);
        add_action('widgets_init', [__CLASS__, 'register_widget']);
    }

    /**
     * Render subscription form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public static function render_shortcode($atts) {
        $settings = NetSendo_WP_Admin_Settings::get_settings();

        $atts = shortcode_atts([
            'list_id' => $settings['default_list_id'] ?? '',
            'style' => $settings['form_style'] ?? 'card',
            'button_text' => __('Subscribe', 'netsendo-wordpress'),
            'show_name' => 'no',
            'title' => '',
            'description' => '',
            'success_message' => __('Thank you for subscribing!', 'netsendo-wordpress'),
            'placeholder_email' => __('Enter your email', 'netsendo-wordpress'),
            'placeholder_name' => __('Your name', 'netsendo-wordpress'),
        ], $atts, 'netsendo_form');

        // Generate unique form ID
        $form_id = 'netsendo-form-' . wp_generate_uuid4();

        ob_start();
        self::render_form($form_id, $atts, $settings);
        return ob_get_clean();
    }

    /**
     * Render the subscription form
     *
     * @param string $form_id
     * @param array $atts
     * @param array $settings
     */
    public static function render_form($form_id, $atts, $settings) {
        $style = esc_attr($atts['style']);
        $show_name = $atts['show_name'] === 'yes';
        $show_gdpr = isset($settings['show_gdpr']) ? (bool) $settings['show_gdpr'] : true;
        $gdpr_text = $settings['gdpr_text'] ?? '';
        ?>
        <div id="<?php echo esc_attr($form_id); ?>"
             class="netsendo-form netsendo-form--<?php echo $style; ?>"
             data-list-id="<?php echo esc_attr($atts['list_id']); ?>"
             data-success-message="<?php echo esc_attr($atts['success_message']); ?>">

            <?php if (!empty($atts['title'])): ?>
                <h3 class="netsendo-form__title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>

            <?php if (!empty($atts['description'])): ?>
                <p class="netsendo-form__description"><?php echo esc_html($atts['description']); ?></p>
            <?php endif; ?>

            <form class="netsendo-form__form" method="post">
                <div class="netsendo-form__fields">
                    <?php if ($show_name): ?>
                        <div class="netsendo-form__field netsendo-form__field--name">
                            <input type="text"
                                   name="name"
                                   class="netsendo-form__input"
                                   placeholder="<?php echo esc_attr($atts['placeholder_name']); ?>"
                                   autocomplete="name">
                        </div>
                    <?php endif; ?>

                    <div class="netsendo-form__field netsendo-form__field--email">
                        <input type="email"
                               name="email"
                               class="netsendo-form__input"
                               placeholder="<?php echo esc_attr($atts['placeholder_email']); ?>"
                               required
                               autocomplete="email">
                    </div>

                    <div class="netsendo-form__field netsendo-form__field--submit">
                        <button type="submit" class="netsendo-form__button">
                            <span class="netsendo-form__button-text"><?php echo esc_html($atts['button_text']); ?></span>
                            <span class="netsendo-form__button-loading" style="display: none;">
                                <svg class="netsendo-spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="60" stroke-linecap="round">
                                        <animateTransform attributeName="transform" type="rotate" dur="1s" from="0 12 12" to="360 12 12" repeatCount="indefinite"/>
                                    </circle>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <?php if ($show_gdpr && !empty($gdpr_text)): ?>
                    <div class="netsendo-form__gdpr">
                        <label class="netsendo-form__gdpr-label">
                            <input type="checkbox" name="consent" value="1" required class="netsendo-form__gdpr-checkbox">
                            <span class="netsendo-form__gdpr-text"><?php echo esc_html($gdpr_text); ?></span>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="netsendo-form__message" style="display: none;"></div>
            </form>

            <div class="netsendo-form__success" style="display: none;">
                <div class="netsendo-form__success-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <p class="netsendo-form__success-text"><?php echo esc_html($atts['success_message']); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Register widget
     */
    public static function register_widget() {
        register_widget('NetSendo_WP_Form_Widget');
    }
}

/**
 * Widget class for subscription form
 */
class NetSendo_WP_Form_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'netsendo_form_widget',
            __('NetSendo Subscription Form', 'netsendo-wordpress'),
            [
                'description' => __('Add a newsletter subscription form to your sidebar.', 'netsendo-wordpress'),
                'classname' => 'netsendo-form-widget',
            ]
        );
    }

    /**
     * Widget frontend output
     */
    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $settings = NetSendo_WP_Admin_Settings::get_settings();
        $form_id = 'netsendo-widget-' . $this->id;

        $atts = [
            'list_id' => $instance['list_id'] ?? $settings['default_list_id'] ?? '',
            'style' => 'card',
            'button_text' => $instance['button_text'] ?? __('Subscribe', 'netsendo-wordpress'),
            'show_name' => $instance['show_name'] ?? 'no',
            'title' => '',
            'description' => $instance['description'] ?? '',
            'success_message' => __('Thank you for subscribing!', 'netsendo-wordpress'),
            'placeholder_email' => __('Enter your email', 'netsendo-wordpress'),
            'placeholder_name' => __('Your name', 'netsendo-wordpress'),
        ];

        NetSendo_WP_Forms::render_form($form_id, $atts, $settings);

        echo $args['after_widget'];
    }

    /**
     * Widget backend form
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $description = !empty($instance['description']) ? $instance['description'] : '';
        $list_id = !empty($instance['list_id']) ? $instance['list_id'] : '';
        $button_text = !empty($instance['button_text']) ? $instance['button_text'] : __('Subscribe', 'netsendo-wordpress');
        $show_name = !empty($instance['show_name']) ? $instance['show_name'] : 'no';

        $lists = NetSendo_WP_Admin_Settings::get_cached_lists();
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_attr_e('Title:', 'netsendo-wordpress'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('description')); ?>">
                <?php esc_attr_e('Description:', 'netsendo-wordpress'); ?>
            </label>
            <textarea class="widefat"
                      id="<?php echo esc_attr($this->get_field_id('description')); ?>"
                      name="<?php echo esc_attr($this->get_field_name('description')); ?>"
                      rows="3"><?php echo esc_textarea($description); ?></textarea>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('list_id')); ?>">
                <?php esc_attr_e('List:', 'netsendo-wordpress'); ?>
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('list_id')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('list_id')); ?>">
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
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('button_text')); ?>">
                <?php esc_attr_e('Button Text:', 'netsendo-wordpress'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('button_text')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('button_text')); ?>"
                   type="text"
                   value="<?php echo esc_attr($button_text); ?>">
        </p>
        <p>
            <input type="checkbox"
                   id="<?php echo esc_attr($this->get_field_id('show_name')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_name')); ?>"
                   value="yes"
                   <?php checked($show_name, 'yes'); ?>>
            <label for="<?php echo esc_attr($this->get_field_id('show_name')); ?>">
                <?php esc_attr_e('Show name field', 'netsendo-wordpress'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Update widget settings
     */
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['description'] = (!empty($new_instance['description'])) ? sanitize_textarea_field($new_instance['description']) : '';
        $instance['list_id'] = (!empty($new_instance['list_id'])) ? sanitize_text_field($new_instance['list_id']) : '';
        $instance['button_text'] = (!empty($new_instance['button_text'])) ? sanitize_text_field($new_instance['button_text']) : '';
        $instance['show_name'] = (!empty($new_instance['show_name'])) ? sanitize_text_field($new_instance['show_name']) : 'no';
        return $instance;
    }
}
