# Translation System Documentation

This document describes the translation/internationalization (i18n) system used in NetSendo.

## Architecture Overview

NetSendo uses **two separate translation systems**:

| System                  | Location                          | Usage                          | Syntax      |
| ----------------------- | --------------------------------- | ------------------------------ | ----------- |
| **Frontend** (Vue i18n) | `src/resources/js/locales/*.json` | Vue components                 | `$t('key')` |
| **Backend** (Laravel)   | `src/lang/{locale}/*.php`         | PHP controllers, notifications | `__('key')` |

### Supported Languages

- 🇵🇱 **pl** - Polish (primary)
- 🇬🇧 **en** - English
- 🇩🇪 **de** - German
- 🇪🇸 **es** - Spanish

---

## Frontend Translations (Vue i18n)

### File Structure

```
src/resources/js/locales/
├── pl.json     # Polish translations
├── en.json     # English translations
├── de.json     # German translations
└── es.json     # Spanish translations
```

### Usage in Vue Components

```vue
<template>
  <!-- Simple key -->
  <h1>{{ $t("dashboard.title") }}</h1>

  <!-- With parameters -->
  <p>{{ $t("common.showing_count", { count: 10 }) }}</p>

  <!-- Pluralization -->
  <span>{{ $tc("items", count) }}</span>
</template>

<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n();

// In script
const title = t("dashboard.title");
</script>
```

### Key Naming Conventions

1. **Use hierarchical namespaces** - Group related keys under common prefixes
2. **Use snake_case** for all key names
3. **Keep values in the target language**

```json
{
  "section_name": {
    "subsection": {
      "key_name": "Translated value"
    }
  }
}
```

### Placeholder Syntax

| Type                 | Syntax          | Example             |
| -------------------- | --------------- | ------------------- |
| Named parameter      | `{name}`        | `"Hello, {name}!"`  |
| Positional parameter | `{0}`, `{1}`    | `"Page {0} of {1}"` |
| Linked translation   | `@:path.to.key` | `"@:common.save"`   |

**Example:**

```json
{
  "welcome_message": "Witaj, {name}! 👋",
  "pagination": "Strona {current} z {total}",
  "save_button": "@:common.save"
}
```

---

## Backend Translations (Laravel)

### File Structure

```
src/lang/
├── pl/
│   ├── auth.php
│   ├── common.php
│   ├── notifications.php
│   └── ...
├── en/
│   └── ...
├── de/
│   └── ...
└── es/
    └── ...
```

### Usage in PHP

```php
// Simple key
__('common.save')

// With parameters
__('notifications.welcome', ['name' => $user->name])

// From specific file
__('auth.login_success')
```

### Laravel Placeholder Syntax

| Type            | Syntax  | Example           |
| --------------- | ------- | ----------------- |
| Named parameter | `:name` | `"Hello, :name!"` |
| Capitalized     | `:Name` | `"Hello, :Name!"` |
| Uppercase       | `:NAME` | `"Hello, :NAME!"` |

---

## Adding New Translations

### Frontend (Vue i18n)

1. Add the key to **all 4 locale files** (`pl.json`, `en.json`, `de.json`, `es.json`)
2. Follow the existing namespace structure
3. Start with Polish as the primary language

**Example:**

```json
// In pl.json
{
    "my_feature": {
        "title": "Moja funkcja",
        "description": "Opis funkcji"
    }
}

// In en.json
{
    "my_feature": {
        "title": "My Feature",
        "description": "Feature description"
    }
}
```

### Backend (Laravel)

1. Add the key to the appropriate PHP file in each locale directory
2. Use the same key structure across all languages

---

## Maintenance

### Checking for Duplicate Keys

Run the duplicate key checker:

```bash
cd src
python3 scripts/fix_duplicates.py
```

To automatically fix duplicates:

```bash
python3 scripts/fix_duplicates.py --fix
```

### Validating JSON Files

```bash
cd src/resources/js/locales
for file in *.json; do
  echo "Validating $file..."
  node -e "JSON.parse(require('fs').readFileSync('$file', 'utf8'))"
done
```

---

## Best Practices

1. **Always translate to all 4 languages** when adding new keys
2. **Use meaningful namespaces** - e.g., `mailing_lists.create_title` not just `create_title`
3. **Avoid duplicate keys** - Run the checker before committing
4. **Use placeholders** instead of string concatenation
5. **Keep translations context-specific** - Same word may need different translations in different contexts
