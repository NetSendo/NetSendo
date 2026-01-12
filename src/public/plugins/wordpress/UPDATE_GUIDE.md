# NetSendo for WordPress - Update & Version Guide

## Current Version: 1.1.0

## Version History

| Version | Date       | Changes                                                                          |
| ------- | ---------- | -------------------------------------------------------------------------------- |
| 1.1.0   | 2026-01-12 | Added Pixel tracking with page_view events, collision detection with WooCommerce |
| 1.0.0   | 2026-01-12 | Initial release with heartbeat, update notifications, forms, content gating      |

---

## How to Update Plugin Version

### Step 1: Update Plugin Version

Edit the main plugin file and update version in two places:

**File:** `netsendo-wordpress.php`

```php
// Line 6 - Plugin header
* Version: X.Y.Z

// Line 22 - Constant
define('NETSENDO_WP_VERSION', 'X.Y.Z');
```

### Step 2: Update NetSendo Configuration

Update the server-side version to trigger update notifications for users:

**File:** `src/config/netsendo.php`

```php
'plugins' => [
    'wordpress' => [
        'version' => 'X.Y.Z',  // <-- Update this
        ...
    ],
    ...
],
```

### Step 3: Update This Changelog

Add a new entry to the Version History table above.

### Step 4: Rebuild ZIP Package

```bash
cd src/public/plugins/wordpress
rm -f netsendo-wordpress.zip
zip -r netsendo-wordpress.zip netsendo-wordpress/
```

### Step 5: Test

1. Install the new ZIP on a test WordPress site
2. Verify the heartbeat sends correct version
3. Confirm update notification appears when server version is higher

---

## Plugin Structure

```
netsendo-wordpress/
├── assets/
│   ├── admin.css
│   ├── admin.js
│   ├── frontend.css
│   └── frontend.js
├── blocks/
│   ├── form/
│   └── content-gate/
├── includes/
│   ├── class-netsendo-api.php
│   ├── class-admin-settings.php
│   ├── class-forms.php
│   ├── class-content-gate.php
│   └── class-gutenberg.php
├── languages/
├── netsendo-wordpress.php   (main plugin file)
└── readme.txt
```

---

## Key Features

-   **Subscription Forms**: Multiple styles (inline, card, minimal)
-   **Content Gating**: Show X% of content, rest for subscribers
-   **Gutenberg Blocks**: Native WP block editor support
-   **GDPR Compliance**: Built-in consent checkbox
-   **Pixel Tracking**: Page views with type detection (Primary injector when both plugins active)
-   **Heartbeat System**: Reports version to NetSendo daily
-   **Update Notifications**: Shows update banner in WP admin

---

## API Endpoints Used

| Endpoint                   | Method | Purpose                 |
| -------------------------- | ------ | ----------------------- |
| `/api/v1/plugin/heartbeat` | POST   | Send version info daily |
| `/api/v1/account`          | GET    | Get user_id for Pixel   |
| `/api/v1/subscribers`      | POST   | Add new subscriber      |
| `/t/pixel/{user_id}`       | GET    | Pixel tracking script   |

---

## Troubleshooting

### Heartbeat not sending

-   Check API key is configured
-   Verify NetSendo URL is correct
-   Check `netsendo_wp_last_heartbeat` option in wp_options

### Update notification not showing

-   Heartbeat must complete successfully first
-   Check `netsendo_wp_update_available` option
-   Verify server version in config/netsendo.php is higher
