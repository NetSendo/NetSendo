=== NetSendo for WordPress ===
Contributors: netsendo
Tags: newsletter, subscription, email marketing, content gating, paywall
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional newsletter subscription forms and content gating for WordPress bloggers and content creators. Powered by NetSendo.

== Description ==

NetSendo for WordPress is a professional integration plugin that brings powerful email marketing features to your WordPress site:

**Subscription Forms**
* Beautiful, responsive subscription forms
* Three form styles: Inline, Minimal, and Card
* Gutenberg block and shortcode support
* Sidebar widget included
* AJAX submissions (no page reload)
* GDPR consent checkbox
* Custom button text and fields

**Content Gating**
* Restrict content to subscribers only
* Percentage-based gating (show X% of content)
* Subscribers-only content
* Logged-in users only mode
* Beautiful blur overlay effect
* Embedded subscription form in gate
* Per-post gate settings

**Gutenberg Blocks**
* NetSendo Form block
* NetSendo Content Gate block with inner blocks
* Full inspector controls

**Easy Setup**
* Connect with your NetSendo account
* Select default mailing lists
* Customize form appearance
* Test connection with one click

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/netsendo-wordpress/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings → NetSendo to configure the plugin
4. Enter your NetSendo API URL and API Key
5. Select your default mailing list
6. Start adding subscription forms and content gates!

== Frequently Asked Questions ==

= Where do I get my API Key? =

Log in to your NetSendo account, go to Settings → API Keys, and create a new API key.

= How do I add a subscription form? =

Use the Gutenberg block "NetSendo Form" or the shortcode `[netsendo_form]`. You can also add the NetSendo widget to your sidebar.

= How do I restrict content? =

Wrap your content with `[netsendo_gate]Your content[/netsendo_gate]` or use the "NetSendo Content Gate" Gutenberg block. You can also enable content gating in the post editor sidebar.

= Does this work with any WordPress theme? =

Yes! NetSendo for WordPress is designed to work with any properly coded WordPress theme.

== Screenshots ==

1. Subscription form with card style
2. Content gate with percentage restriction
3. Plugin settings page
4. Gutenberg blocks in editor

== Changelog ==

= 1.0.0 =
* Initial release
* Subscription form shortcode and widget
* Content gate shortcode
* Gutenberg blocks
* Admin settings page
* AJAX subscription handling
* GDPR consent support

== Upgrade Notice ==

= 1.0.0 =
Initial release of NetSendo for WordPress.
