# NetSendo for WooCommerce - Update & Version Guide

## Current Version: 1.0.0

## Version History

| Version | Date       | Changes                                                                              |
| ------- | ---------- | ------------------------------------------------------------------------------------ |
| 1.0.0   | 2026-01-12 | Initial release with order tracking, heartbeat, update notifications, pixel tracking |

---

## How to Update Plugin Version

### Step 1: Update Plugin Version

Edit the main plugin file and update version in two places:

**File:** `netsendo-woocommerce.php`

```php
// Line 6 - Plugin header
* Version: X.Y.Z

// Line 24 - Constant
define('NETSENDO_WC_VERSION', 'X.Y.Z');
```

### Step 2: Update NetSendo Configuration

Update the server-side version to trigger update notifications for users:

**File:** `src/config/netsendo.php`

```php
'plugins' => [
    ...
    'woocommerce' => [
        'version' => 'X.Y.Z',  // <-- Update this
        ...
    ],
],
```

### Step 3: Update This Changelog

Add a new entry to the Version History table above.

### Step 4: Rebuild ZIP Package

```bash
cd src/public/plugins/woocommerce
rm -f netsendo-woocommerce.zip
zip -r netsendo-woocommerce.zip netsendo-woocommerce/
```

### Step 5: Test

1. Install the new ZIP on a test WooCommerce site
2. Verify the heartbeat sends correct version (with wc_version)
3. Confirm update notification appears when server version is higher
4. Test order processing and subscriber creation

---

## Plugin Structure

```
netsendo-woocommerce/
├── assets/
│   ├── admin.css
│   └── admin.js
├── includes/
│   ├── class-netsendo-api.php
│   ├── class-admin-settings.php
│   └── class-product-meta.php
├── languages/
├── netsendo-woocommerce.php   (main plugin file)
└── readme.txt
```

---

## Key Features

-   **Order Auto-Subscribe**: Add customers to lists after purchase
-   **Cart Recovery**: Track unpaid orders (pending list)
-   **Product-Specific Settings**: Override lists per product
-   **External Page Redirects**: Send to sales funnels after purchase
-   **Pixel Tracking**: Track product views, add to cart, checkout, purchase
-   **Heartbeat System**: Reports version (including WC version) to NetSendo daily
-   **Update Notifications**: Shows update banner in WP admin

---

## API Endpoints Used

| Endpoint                   | Method | Purpose                 |
| -------------------------- | ------ | ----------------------- |
| `/api/v1/plugin/heartbeat` | POST   | Send version info daily |
| `/webhooks/woocommerce`    | POST   | Receive order events    |
| `/t/pixel/{user_id}`       | GET    | Pixel tracking script   |

---

## Heartbeat Payload

The WooCommerce plugin sends additional data compared to WordPress:

```json
{
    "plugin_type": "woocommerce",
    "site_url": "https://store.example.com",
    "site_name": "My Store",
    "plugin_version": "1.0.0",
    "wp_version": "6.4",
    "wc_version": "8.5", // <-- WooCommerce-specific
    "php_version": "8.2"
}
```

---

## Order Processing Hooks

| Hook                                   | Action                    |
| -------------------------------------- | ------------------------- |
| `woocommerce_order_status_completed`   | Add to purchase list      |
| `woocommerce_order_status_processing`  | Add to purchase list      |
| `woocommerce_checkout_order_processed` | Add to pending list       |
| `woocommerce_thankyou`                 | Redirect to external page |

---

## Troubleshooting

### Heartbeat not sending

-   Check API key is configured in WooCommerce → NetSendo
-   Verify NetSendo URL is correct
-   Check `netsendo_wc_last_heartbeat` option in wp_options

### Update notification not showing

-   Heartbeat must complete successfully first
-   Check `netsendo_wc_update_available` option
-   Verify server version in config/netsendo.php is higher

### Orders not creating subscribers

-   Verify list IDs are configured
-   Check order notes for NetSendo messages
-   Ensure API key has write permissions
