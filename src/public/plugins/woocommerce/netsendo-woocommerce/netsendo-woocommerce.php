<?php
/**
 * Plugin Name: NetSendo for WooCommerce
 * Plugin URI: https://netsendo.com/integrations/woocommerce
 * Description: Integracja WooCommerce z NetSendo - automatyczne zapisywanie klientów na listy mailingowe po zakupie lub próbie zakupu.
 * Version: 1.1.1
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
define('NETSENDO_WC_VERSION', '1.1.1');
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

    // Heartbeat and update notifications
    add_action('admin_init', 'netsendo_wc_maybe_send_heartbeat');
    add_action('admin_notices', 'netsendo_wc_show_update_notice');
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

// =============================================================================
// NETSENDO PIXEL TRACKING
// =============================================================================

/**
 * Check if WordPress plugin is handling Pixel
 *
 * @return bool
 */
function netsendo_wc_is_wordpress_plugin_handling_pixel() {
    // Check if WordPress plugin already loaded Pixel via constant
    if (defined('NETSENDO_PIXEL_LOADED') && NETSENDO_PIXEL_LOADED) {
        return true;
    }

    // Check if WordPress plugin is active and has Pixel enabled
    if (class_exists('NetSendo_WP_Admin_Settings')) {
        $wp_settings = NetSendo_WP_Admin_Settings::get_settings();
        if (!empty($wp_settings['enable_pixel']) && !empty($wp_settings['user_id'])) {
            return true;
        }
    }

    return false;
}

/**
 * Inject NetSendo Pixel script into WooCommerce pages
 * Only injects if WordPress plugin is NOT handling the Pixel
 */
function netsendo_wc_inject_pixel_script() {
    // Skip if WordPress plugin is handling the Pixel (collision detection)
    if (netsendo_wc_is_wordpress_plugin_handling_pixel()) {
        return;
    }

    $settings = NetSendo_WC_Admin_Settings::get_settings();
    $api_url = isset($settings['api_url']) ? rtrim($settings['api_url'], '/') : '';
    $user_id = $settings['user_id'] ?? '';

    // Only inject if properly configured
    if (empty($user_id) || empty($api_url)) {
        return;
    }

    ?>
    <!-- NetSendo Pixel -->
    <script>
    (function(n,e,t,s,d,o){n.NetSendo=n.NetSendo||[];
    n.NetSendo.push(['init',{userId:<?php echo (int)$user_id; ?>,apiUrl:'<?php echo esc_js($api_url); ?>/t/pixel'}]);
    var a=e.createElement(t);a.async=1;a.src='<?php echo esc_js($api_url); ?>/t/pixel/<?php echo (int)$user_id; ?>';
    var m=e.getElementsByTagName(t)[0];m.parentNode.insertBefore(a,m);
    })(window,document,'script');
    </script>
    <!-- End NetSendo Pixel -->
    <?php
}
add_action('wp_head', 'netsendo_wc_inject_pixel_script', 1);

/**
 * Track product views
 */
function netsendo_wc_track_product_view() {
    if (!is_product()) {
        return;
    }

    global $product;
    if (!$product) {
        return;
    }

    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $category_string = is_array($categories) ? implode(', ', $categories) : '';

    ?>
    <script>
    if (typeof NetSendo !== 'undefined') {
        NetSendo.push(['track', 'product_view', {
            product_id: '<?php echo esc_js($product->get_id()); ?>',
            product_name: '<?php echo esc_js($product->get_name()); ?>',
            product_price: <?php echo (float)$product->get_price(); ?>,
            product_category: '<?php echo esc_js($category_string); ?>'
        }]);
    }
    </script>
    <?php
}
add_action('woocommerce_after_single_product', 'netsendo_wc_track_product_view');

/**
 * Track add to cart events (AJAX)
 */
function netsendo_wc_track_add_to_cart_script() {
    if (!is_woocommerce() && !is_cart() && !is_checkout()) {
        return;
    }

    ?>
    <script>
    jQuery(function($) {
        // Track AJAX add to cart
        $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
            if (typeof NetSendo === 'undefined') return;

            var productId = $button.data('product_id');
            var productName = $button.closest('.product').find('.woocommerce-loop-product__title, .product_title').first().text() || '';
            var productPrice = $button.closest('.product').find('.price .woocommerce-Price-amount').first().text() || '';

            NetSendo.push(['track', 'add_to_cart', {
                product_id: String(productId),
                product_name: productName.trim(),
                product_price: parseFloat(productPrice.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0
            }]);
        });

        // Track remove from cart
        $(document.body).on('removed_from_cart', function(event, fragments, cart_hash, $button) {
            if (typeof NetSendo === 'undefined') return;

            var productName = $button.closest('tr').find('.product-name a').first().text() || '';

            NetSendo.push(['track', 'remove_from_cart', {
                product_name: productName.trim()
            }]);
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'netsendo_wc_track_add_to_cart_script');

/**
 * Track single product add to cart (non-AJAX)
 */
function netsendo_wc_track_single_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (wp_doing_ajax()) {
        return; // Already handled by AJAX tracking
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }

    // Store in session to track on next page load
    WC()->session->set('netsendo_track_add_to_cart', [
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'product_price' => $product->get_price(),
        'quantity' => $quantity,
    ]);
}
add_action('woocommerce_add_to_cart', 'netsendo_wc_track_single_add_to_cart', 10, 6);

/**
 * Output tracking for non-AJAX add to cart from session
 */
function netsendo_wc_output_session_tracking() {
    if (!WC()->session) {
        return;
    }

    $track_data = WC()->session->get('netsendo_track_add_to_cart');
    if ($track_data) {
        WC()->session->set('netsendo_track_add_to_cart', null);
        ?>
        <script>
        if (typeof NetSendo !== 'undefined') {
            NetSendo.push(['track', 'add_to_cart', {
                product_id: '<?php echo esc_js($track_data['product_id']); ?>',
                product_name: '<?php echo esc_js($track_data['product_name']); ?>',
                product_price: <?php echo (float)$track_data['product_price']; ?>
            }]);
        }
        </script>
        <?php
    }
}
add_action('wp_footer', 'netsendo_wc_output_session_tracking');

/**
 * Track checkout page view
 */
function netsendo_wc_track_checkout() {
    if (!is_checkout()) {
        return;
    }

    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    $cart_total = $cart->get_cart_contents_total();

    ?>
    <script>
    if (typeof NetSendo !== 'undefined') {
        NetSendo.push(['track', 'checkout_started', {
            cart_value: <?php echo (float)$cart_total; ?>,
            product_currency: '<?php echo esc_js(get_woocommerce_currency()); ?>'
        }]);
    }
    </script>
    <?php
}
add_action('woocommerce_before_checkout_form', 'netsendo_wc_track_checkout');

/**
 * Identify user on checkout (when email is entered)
 */
function netsendo_wc_identify_on_checkout() {
    if (!is_checkout()) {
        return;
    }

    ?>
    <script>
    jQuery(function($) {
        var identifyTimeout;
        $('#billing_email').on('change blur', function() {
            var email = $(this).val();
            if (!email || !email.includes('@')) return;

            clearTimeout(identifyTimeout);
            identifyTimeout = setTimeout(function() {
                if (typeof NetSendo !== 'undefined') {
                    NetSendo.push(['identify', { email: email }]);
                }
            }, 500);
        });
    });
    </script>
    <?php
}
add_action('woocommerce_after_checkout_form', 'netsendo_wc_identify_on_checkout');

/**
 * Track successful purchase
 */
function netsendo_wc_track_purchase($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $products = [];
    foreach ($order->get_items() as $item) {
        $products[] = [
            'id' => $item->get_product_id(),
            'name' => $item->get_name(),
            'price' => $order->get_item_total($item, false, true),
            'quantity' => $item->get_quantity(),
        ];
    }

    ?>
    <script>
    if (typeof NetSendo !== 'undefined') {
        NetSendo.push(['track', 'purchase', {
            order_id: '<?php echo esc_js($order_id); ?>',
            cart_value: <?php echo (float)$order->get_total(); ?>,
            product_currency: '<?php echo esc_js($order->get_currency()); ?>',
            custom_data: <?php echo json_encode(['products' => $products]); ?>
        }]);

        // Also identify the customer
        NetSendo.push(['identify', {
            email: '<?php echo esc_js($order->get_billing_email()); ?>'
        }]);
    }
    </script>
    <?php
}
add_action('woocommerce_thankyou', 'netsendo_wc_track_purchase', 10, 1);

// =============================================================================
// HEARTBEAT AND UPDATE NOTIFICATIONS
// =============================================================================

/**
 * Check if we should send heartbeat and send if needed
 */
function netsendo_wc_maybe_send_heartbeat() {
    $last_heartbeat = get_option('netsendo_wc_last_heartbeat', 0);
    // Send heartbeat once per day
    if (time() - $last_heartbeat > 86400) {
        netsendo_wc_send_heartbeat();
        update_option('netsendo_wc_last_heartbeat', time());
    }
}

/**
 * Send heartbeat to NetSendo API
 */
function netsendo_wc_send_heartbeat() {
    $settings = NetSendo_WC_Admin_Settings::get_settings();
    if (empty($settings['api_key']) || empty($settings['api_url'])) {
        return;
    }

    $response = wp_remote_post(
        rtrim($settings['api_url'], '/') . '/api/v1/plugin/heartbeat',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode([
                'plugin_type' => 'woocommerce',
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'plugin_version' => NETSENDO_WC_VERSION,
                'wp_version' => get_bloginfo('version'),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : null,
                'php_version' => phpversion(),
            ]),
            'timeout' => 10,
        ]
    );

    if (!is_wp_error($response)) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['update_available']) && !empty($body['latest_version'])) {
            update_option('netsendo_wc_update_available', [
                'version' => $body['latest_version'],
                'download_url' => $body['download_url'] ?? '',
                'checked_at' => time(),
            ]);
        } else {
            delete_option('netsendo_wc_update_available');
        }
    }
}

/**
 * Show update notice in admin
 */
function netsendo_wc_show_update_notice() {
    $update = get_option('netsendo_wc_update_available');
    if (!$update) {
        return;
    }

    // Refresh if older than 24 hours
    if (time() - ($update['checked_at'] ?? 0) > 86400) {
        netsendo_wc_send_heartbeat();
        $update = get_option('netsendo_wc_update_available');
        if (!$update) {
            return;
        }
    }

    $settings = NetSendo_WC_Admin_Settings::get_settings();
    $panel_url = !empty($settings['api_url'])
        ? rtrim($settings['api_url'], '/') . '/marketplace/woocommerce'
        : '#';
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong>NetSendo for WooCommerce:</strong>
            <?php printf(
                /* translators: %1$s: new version number, %2$s: link to download */
                __('Dostępna jest nowa wersja wtyczki (v%1$s). <a href="%2$s" target="_blank">Pobierz aktualizację</a> z panelu NetSendo.', 'netsendo-woocommerce'),
                esc_html($update['version']),
                esc_url($panel_url)
            ); ?>
        </p>
    </div>
    <?php
}
