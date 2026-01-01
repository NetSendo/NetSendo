<?php
/**
 * Plugin Name: NetSendo for WooCommerce
 * Plugin URI: https://netsendo.com/integrations/woocommerce
 * Description: Integracja WooCommerce z NetSendo - automatyczne zapisywanie klientów na listy mailingowe po zakupie lub próbie zakupu.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: NetSendo
 * Author URI: https://netsendo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: netsendo-woocommerce
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NETSENDO_WC_VERSION', '1.0.0');
define('NETSENDO_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NETSENDO_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NETSENDO_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function netsendo_wc_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('NetSendo for WooCommerce requires WooCommerce to be installed and active.', 'netsendo-woocommerce'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

/**
 * Initialize the plugin
 */
function netsendo_wc_init() {
    if (!netsendo_wc_check_woocommerce()) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain('netsendo-woocommerce', false, dirname(NETSENDO_WC_PLUGIN_BASENAME) . '/languages');

    // Include required files
    require_once NETSENDO_WC_PLUGIN_DIR . 'includes/class-netsendo-api.php';
    require_once NETSENDO_WC_PLUGIN_DIR . 'includes/class-admin-settings.php';
    require_once NETSENDO_WC_PLUGIN_DIR . 'includes/class-product-meta.php';

    // Initialize classes
    NetSendo_WC_Admin_Settings::init();
    NetSendo_WC_Product_Meta::init();

    // Register hooks for order processing
    add_action('woocommerce_order_status_completed', 'netsendo_wc_handle_order_completed', 10, 1);
    add_action('woocommerce_order_status_processing', 'netsendo_wc_handle_order_completed', 10, 1);
    add_action('woocommerce_checkout_order_processed', 'netsendo_wc_handle_order_pending', 10, 3);

    // Thank you page redirect hook
    add_action('woocommerce_thankyou', 'netsendo_wc_handle_thank_you_redirect', 5, 1);
}
add_action('plugins_loaded', 'netsendo_wc_init');

/**
 * Declare WooCommerce HPOS and Blocks compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Handle completed order - add subscriber to "purchase" list
 *
 * @param int $order_id
 */
function netsendo_wc_handle_order_completed($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Check if already processed
    if ($order->get_meta('_netsendo_purchase_processed')) {
        return;
    }

    $api = new NetSendo_WC_API();
    $settings = NetSendo_WC_Admin_Settings::get_settings();

    $email = $order->get_billing_email();
    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $name = trim($first_name . ' ' . $last_name);

    // Process each product in the order
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        // Get product-specific settings or use defaults
        $product_settings = netsendo_wc_get_product_settings($product_id);
        $list_id = $product_settings['purchase_list_id'] ?: NetSendo_WC_Admin_Settings::get_effective_list_id('purchase');

        if ($list_id) {
            $custom_fields = [
                'source' => 'woocommerce',
                'order_id' => $order_id,
                'product_id' => $product_id,
                'product_name' => $item->get_name(),
                'order_total' => $order->get_total(),
            ];

            $result = $api->add_subscriber($list_id, $email, $name, $custom_fields);

            if ($result) {
                $order->add_order_note(
                    sprintf(
                        __('NetSendo: Customer added to list %s for product "%s"', 'netsendo-woocommerce'),
                        $list_id,
                        $item->get_name()
                    )
                );
            }
        }
    }

    // Mark as processed
    $order->update_meta_data('_netsendo_purchase_processed', time());
    $order->save();
}

/**
 * Handle pending order - add subscriber to "pending" list
 *
 * @param int $order_id
 * @param array $posted_data
 * @param WC_Order $order
 */
function netsendo_wc_handle_order_pending($order_id, $posted_data, $order) {
    if (!$order) {
        $order = wc_get_order($order_id);
    }

    if (!$order) {
        return;
    }

    $api = new NetSendo_WC_API();
    $settings = NetSendo_WC_Admin_Settings::get_settings();

    $email = $order->get_billing_email();
    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $name = trim($first_name . ' ' . $last_name);

    // Process each product in the order
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        // Get product-specific settings or use defaults
        $product_settings = netsendo_wc_get_product_settings($product_id);
        $list_id = $product_settings['pending_list_id'] ?: NetSendo_WC_Admin_Settings::get_effective_list_id('pending');

        if ($list_id) {
            $custom_fields = [
                'source' => 'woocommerce',
                'order_id' => $order_id,
                'product_id' => $product_id,
                'product_name' => $item->get_name(),
                'order_status' => 'pending',
            ];

            $result = $api->add_subscriber($list_id, $email, $name, $custom_fields);

            if ($result) {
                $order->add_order_note(
                    sprintf(
                        __('NetSendo: Customer added to pending list %s for product "%s"', 'netsendo-woocommerce'),
                        $list_id,
                        $item->get_name()
                    )
                );
            }
        }
    }
}

/**
 * Handle thank you page redirect
 *
 * @param int $order_id
 */
function netsendo_wc_handle_thank_you_redirect($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Only redirect for completed/processing orders
    if (!in_array($order->get_status(), ['completed', 'processing'])) {
        return;
    }

    // Check if already redirected
    if ($order->get_meta('_netsendo_redirected')) {
        return;
    }

    $settings = NetSendo_WC_Admin_Settings::get_settings();
    $redirect_url = null;
    $first_product_id = null;

    // Check each product for custom redirect URL (first one wins)
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if (!$first_product_id) {
            $first_product_id = $product_id;
        }
        $product_settings = netsendo_wc_get_product_settings($product_id);

        if (!empty($product_settings['redirect_url'])) {
            $redirect_url = $product_settings['redirect_url'];
            break;
        }

        // Check for product-specific external page
        if (!empty($product_settings['external_page_id'])) {
            $api_url = $settings['api_url'] ?? '';
            $redirect_url = rtrim($api_url, '/') . '/p/' . $product_settings['external_page_id'];
            break;
        }
    }

    // Fall back to default redirect URL from settings
    if (!$redirect_url) {
        $redirect_url = NetSendo_WC_Admin_Settings::get_redirect_url();
    }

    if ($redirect_url) {
        // Add order data to URL for NetSendo external page processing
        $redirect_url = add_query_arg([
            'order_id' => $order_id,
            'order_key' => $order->get_order_key(),
            'email' => urlencode($order->get_billing_email()),
            'product_id' => $first_product_id,
            'source' => 'woocommerce',
        ], $redirect_url);

        // Mark as redirected
        $order->update_meta_data('_netsendo_redirected', time());
        $order->save();

        // Output JavaScript redirect
        ?>
        <script type="text/javascript">
            window.location.href = <?php echo json_encode(esc_url($redirect_url)); ?>;
        </script>
        <?php
        exit;
    }
}

/**
 * Get product-specific NetSendo settings
 *
 * @param int $product_id
 * @return array
 */
function netsendo_wc_get_product_settings($product_id) {
    $override = get_post_meta($product_id, '_netsendo_override_settings', true);

    if ($override !== 'yes') {
        return [
            'purchase_list_id' => '',
            'pending_list_id' => '',
            'external_page_id' => '',
            'redirect_url' => '',
        ];
    }

    return [
        'purchase_list_id' => get_post_meta($product_id, '_netsendo_purchase_list_id', true),
        'pending_list_id' => get_post_meta($product_id, '_netsendo_pending_list_id', true),
        'external_page_id' => get_post_meta($product_id, '_netsendo_external_page_id', true),
        'redirect_url' => get_post_meta($product_id, '_netsendo_redirect_url', true),
    ];
}

/**
 * Plugin activation hook
 */
function netsendo_wc_activate() {
    // Create default options
    if (!get_option('netsendo_wc_settings')) {
        update_option('netsendo_wc_settings', [
            'api_key' => '',
            'api_url' => '',
            'default_purchase_list_id' => '',
            'default_pending_list_id' => '',
            'default_redirect_url' => '',
        ]);
    }
}
register_activation_hook(__FILE__, 'netsendo_wc_activate');

/**
 * Add settings link to plugins page
 */
function netsendo_wc_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=netsendo-woocommerce') . '">' .
                     __('Settings', 'netsendo-woocommerce') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . NETSENDO_WC_PLUGIN_BASENAME, 'netsendo_wc_plugin_action_links');
