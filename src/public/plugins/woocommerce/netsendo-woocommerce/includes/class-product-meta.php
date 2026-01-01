<?php
/**
 * Product Meta Box for NetSendo Settings
 *
 * @package NetSendo_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NetSendo_WC_Product_Meta
 * Handles product-level NetSendo settings
 */
class NetSendo_WC_Product_Meta {

    /**
     * Initialize
     */
    public static function init() {
        add_action('woocommerce_product_data_tabs', [__CLASS__, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'render_product_panel']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_meta']);
    }

    /**
     * Add NetSendo tab to product data
     */
    public static function add_product_tab($tabs) {
        $tabs['netsendo'] = [
            'label' => __('NetSendo', 'netsendo-woocommerce'),
            'target' => 'netsendo_product_data',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_subscription'],
            'priority' => 80,
        ];
        return $tabs;
    }

    /**
     * Render the product panel
     */
    public static function render_product_panel() {
        global $post;

        $product_id = $post->ID;
        $override = get_post_meta($product_id, '_netsendo_override_settings', true);
        $purchase_list_id = get_post_meta($product_id, '_netsendo_purchase_list_id', true);
        $pending_list_id = get_post_meta($product_id, '_netsendo_pending_list_id', true);
        $external_page_id = get_post_meta($product_id, '_netsendo_external_page_id', true);
        $redirect_url = get_post_meta($product_id, '_netsendo_redirect_url', true);

        $lists = NetSendo_WC_Admin_Settings::get_cached_lists();
        $external_pages = NetSendo_WC_Admin_Settings::get_cached_external_pages();
        ?>
        <div id="netsendo_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="netsendo_override_settings">
                        <?php _e('Override Default Settings', 'netsendo-woocommerce'); ?>
                    </label>
                    <input type="checkbox"
                           id="netsendo_override_settings"
                           name="_netsendo_override_settings"
                           value="yes"
                           <?php checked($override, 'yes'); ?>>
                    <span class="description">
                        <?php _e('Enable to use custom NetSendo settings for this product instead of the defaults.', 'netsendo-woocommerce'); ?>
                    </span>
                </p>
            </div>

            <div class="options_group netsendo-override-fields" style="<?php echo $override !== 'yes' ? 'opacity: 0.5;' : ''; ?>">
                <p class="form-field">
                    <label for="netsendo_purchase_list_id">
                        <?php _e('List after Purchase', 'netsendo-woocommerce'); ?>
                    </label>
                    <select id="netsendo_purchase_list_id"
                            name="_netsendo_purchase_list_id"
                            class="select short"
                            <?php disabled($override !== 'yes'); ?>>
                        <option value=""><?php _e('— Use Default —', 'netsendo-woocommerce'); ?></option>
                        <?php if ($lists): ?>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($purchase_list_id, $list['id']); ?>>
                                    <?php echo esc_html($list['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php echo wc_help_tip(__('Select the list to add customers after they complete a purchase of this product.', 'netsendo-woocommerce')); ?>
                </p>

                <p class="form-field">
                    <label for="netsendo_pending_list_id">
                        <?php _e('List after Pending Order', 'netsendo-woocommerce'); ?>
                    </label>
                    <select id="netsendo_pending_list_id"
                            name="_netsendo_pending_list_id"
                            class="select short"
                            <?php disabled($override !== 'yes'); ?>>
                        <option value=""><?php _e('— Use Default —', 'netsendo-woocommerce'); ?></option>
                        <?php if ($lists): ?>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?php echo esc_attr($list['id']); ?>" <?php selected($pending_list_id, $list['id']); ?>>
                                    <?php echo esc_html($list['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php echo wc_help_tip(__('Select the list to add customers when they create an order but have not yet paid.', 'netsendo-woocommerce')); ?>
                </p>

                <p class="form-field">
                    <label for="netsendo_external_page_id">
                        <?php _e('NetSendo External Page', 'netsendo-woocommerce'); ?>
                    </label>
                    <select id="netsendo_external_page_id"
                            name="_netsendo_external_page_id"
                            class="select short"
                            <?php disabled($override !== 'yes'); ?>>
                        <option value=""><?php _e('— Use Default —', 'netsendo-woocommerce'); ?></option>
                        <?php if ($external_pages): ?>
                            <?php foreach ($external_pages as $page): ?>
                                <option value="<?php echo esc_attr($page['id']); ?>" <?php selected($external_page_id, $page['id']); ?>>
                                    <?php echo esc_html($page['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php echo wc_help_tip(__('Select a NetSendo external page to use as thank you page with sales funnel functionality.', 'netsendo-woocommerce')); ?>
                </p>

                <p class="form-field">
                    <label for="netsendo_redirect_url">
                        <?php _e('Or Custom Redirect URL', 'netsendo-woocommerce'); ?>
                    </label>
                    <input type="url"
                           id="netsendo_redirect_url"
                           name="_netsendo_redirect_url"
                           value="<?php echo esc_attr($redirect_url); ?>"
                           class="short"
                           placeholder="https://example.com/thank-you"
                           <?php disabled($override !== 'yes'); ?>>
                    <?php echo wc_help_tip(__('Enter a custom URL if not using NetSendo external page. This overrides the external page selection above.', 'netsendo-woocommerce')); ?>
                </p>
            </div>

            <?php if (!$lists): ?>
            <div class="options_group">
                <p class="form-field">
                    <span class="description" style="color: #d63638;">
                        <?php _e('Unable to load lists from NetSendo. Please check your API settings.', 'netsendo-woocommerce'); ?>
                        <a href="<?php echo admin_url('admin.php?page=netsendo-woocommerce'); ?>">
                            <?php _e('Go to Settings', 'netsendo-woocommerce'); ?>
                        </a>
                    </span>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
            jQuery(function($) {
                $('#netsendo_override_settings').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    var $fields = $('.netsendo-override-fields');
                    var $inputs = $fields.find('select, input');

                    if (isChecked) {
                        $fields.css('opacity', '1');
                        $inputs.prop('disabled', false);
                    } else {
                        $fields.css('opacity', '0.5');
                        $inputs.prop('disabled', true);
                    }
                });
            });
        </script>

        <style>
            #woocommerce-product-data ul.wc-tabs li.netsendo_options a::before {
                content: '\f466';
                font-family: dashicons;
            }
        </style>
        <?php
    }

    /**
     * Save product meta
     */
    public static function save_product_meta($post_id) {
        // Override settings checkbox
        $override = isset($_POST['_netsendo_override_settings']) ? 'yes' : 'no';
        update_post_meta($post_id, '_netsendo_override_settings', $override);

        // Only save other fields if override is enabled
        if ($override === 'yes') {
            if (isset($_POST['_netsendo_purchase_list_id'])) {
                update_post_meta(
                    $post_id,
                    '_netsendo_purchase_list_id',
                    sanitize_text_field($_POST['_netsendo_purchase_list_id'])
                );
            }

            if (isset($_POST['_netsendo_pending_list_id'])) {
                update_post_meta(
                    $post_id,
                    '_netsendo_pending_list_id',
                    sanitize_text_field($_POST['_netsendo_pending_list_id'])
                );
            }

            if (isset($_POST['_netsendo_external_page_id'])) {
                update_post_meta(
                    $post_id,
                    '_netsendo_external_page_id',
                    sanitize_text_field($_POST['_netsendo_external_page_id'])
                );
            }

            if (isset($_POST['_netsendo_redirect_url'])) {
                update_post_meta(
                    $post_id,
                    '_netsendo_redirect_url',
                    esc_url_raw($_POST['_netsendo_redirect_url'])
                );
            }
        } else {
            // Clear product-specific settings when override is disabled
            delete_post_meta($post_id, '_netsendo_purchase_list_id');
            delete_post_meta($post_id, '_netsendo_pending_list_id');
            delete_post_meta($post_id, '_netsendo_external_page_id');
            delete_post_meta($post_id, '_netsendo_redirect_url');
        }
    }
}
