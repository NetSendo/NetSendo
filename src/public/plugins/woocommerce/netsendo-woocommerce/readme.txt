=== NetSendo for WooCommerce ===
Contributors: netsendo
Tags: email marketing, woocommerce, mailing list, newsletter, abandoned cart
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate WooCommerce with NetSendo - automatically add customers to mailing lists after purchase or abandoned cart.

== Description ==

**NetSendo for WooCommerce** connects your WooCommerce store with your NetSendo email marketing platform. Automatically segment customers based on their purchase behavior.

= Features =

* **Automatic Subscription** - Add customers to mailing lists when they complete a purchase
* **Abandoned Cart Recovery** - Capture emails from pending orders for follow-up campaigns
* **Per-Product Settings** - Override default lists for individual products
* **Custom Redirect URLs** - Send customers to a custom thank-you page after purchase
* **Easy Setup** - Connect with just your API key

= How It Works =

1. Install and activate the plugin
2. Enter your NetSendo API URL and API Key
3. Select default lists for completed and pending orders
4. Optionally customize settings per product

= Requirements =

* WooCommerce 5.0 or higher
* NetSendo account with API access
* PHP 7.4 or higher

== Installation ==

1. Upload the `netsendo-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > NetSendo to configure your settings
4. Enter your NetSendo URL and API Key
5. Test the connection and select your default lists

== Frequently Asked Questions ==

= Where do I find my API Key? =

Log in to your NetSendo dashboard, go to Settings > API Keys, and create a new API key.

= Can I use different lists for different products? =

Yes! Edit any WooCommerce product and look for the "NetSendo" tab in the Product Data panel. Enable "Override Default Settings" to use custom lists for that product.

= What happens with abandoned carts? =

When a customer creates an order but doesn't complete payment, they can be added to a "pending" list. You can then send automated follow-up emails through NetSendo.

= Does this work with variable products? =

Yes, the plugin works with simple, variable, and subscription products.

== Screenshots ==

1. Plugin settings page
2. Product-level settings
3. Connection test

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic subscription on order completion
* Pending order capture for abandoned cart recovery
* Per-product settings override
* Custom redirect URL support

== Upgrade Notice ==

= 1.0.0 =
Initial release.
