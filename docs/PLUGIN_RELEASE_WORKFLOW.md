# Plugin Release Workflow

## When to use this workflow

**IMPORTANT:** Always execute this workflow after making ANY changes to the WordPress or WooCommerce plugins located in:

- `src/public/plugins/wordpress/netsendo-wordpress/`
- `src/public/plugins/woocommerce/netsendo-woocommerce/`

## Steps

### 1. Update Plugin Version Numbers

For **WordPress** plugin, update version in two places in `netsendo-wordpress.php`:

```php
* Version: X.Y.Z           // Line 6 - Plugin header
define('NETSENDO_WP_VERSION', 'X.Y.Z');  // Line 22
```

For **WooCommerce** plugin, update version in two places in `netsendo-woocommerce.php`:

```php
* Version: X.Y.Z            // Line 6 - Plugin header
define('NETSENDO_WC_VERSION', 'X.Y.Z');  // Line 24
```

### 2. Update Server Configuration

Update `src/config/netsendo.php` to set new plugin versions:

```php
'plugins' => [
    'wordpress' => ['version' => 'X.Y.Z', ...],
    'woocommerce' => ['version' => 'X.Y.Z', ...],
],
```

### 3. Update Version History

Add new entry to the Version History table in:

- `src/public/plugins/wordpress/UPDATE_GUIDE.md`
- `src/public/plugins/woocommerce/UPDATE_GUIDE.md`

Also update "Current Version" at the top of each file.

### 4. Rebuild ZIP Packages

```bash
cd src/public/plugins/wordpress && rm -f netsendo-wordpress.zip && zip -r netsendo-wordpress.zip netsendo-wordpress/
```

```bash
cd src/public/plugins/woocommerce && rm -f netsendo-woocommerce.zip && zip -r netsendo-woocommerce.zip netsendo-woocommerce/
```

### 5. Update CHANGELOG.md

Add plugin changes to the `[Unreleased]` section in `CHANGELOG.md`.

## Versioning Guidelines

- **PATCH** (1.0.X): Bug fixes, minor improvements
- **MINOR** (1.X.0): New features, non-breaking changes
- **MAJOR** (X.0.0): Breaking changes, major rewrites

---

> **Note for AI Agent:** This workflow should be executed automatically after completing any work on WordPress or WooCommerce plugins. Do not skip any steps.
