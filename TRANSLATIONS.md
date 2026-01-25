# Translation Instructions for Agents

This document defines the standard procedure for handling translations in the NetSendo project. **All Agents must follow these instructions.**

## 1. Source of Truth

The frontend application (Vue.js/Inertia) uses **JSON** files for translations.
**DO NOT** rely solely on PHP translation files (`src/lang/*`) for frontend text, as they are not automatically synchronized.

- **Location**: `src/resources/js/locales/`
- **Files**:
  - `pl.json` (Polish - Native/Default)
  - `en.json` (English)
  - `es.json` (Spanish)
  - `de.json` (German)

## 2. Supported Languages

| Code | Language | Role              |
| :--- | :------- | :---------------- |
| `pl` | Polish   | Source / Default  |
| `en` | English  | Primary Secondary |
| `es` | Spanish  | Required          |
| `de` | German   | Required          |

## 3. Workflow for Adding Translations

When adding new features or text, follow this strict process:

### Step 1: Add to Source (PL & EN)

Current Best Practice is to manually add the new keys to `pl.json` and `en.json`.
Ensure the structure is logical and nested (e.g., `crm.task.new` instead of just `new_task`).

```json
// Example addition to pl.json
"crm": {
    "task": {
        "new": "Nowe zadanie"
    }
}
```

### Step 2: Translate to Support Languages (ES & DE)

You **MUST** translate the new keys to Spanish (`es.json`) and German (`de.json`).
Do not leave them in English or empty.

### Step 3: Frontend Implementation

Use the Vue I18n `$t` function. Always provide a default string (usually Polish) as the second argument for fallback/readability during development.

```javascript
// Preferred usage
$t("crm.task.new", "Nowe zadanie");
```

## 4. Verification

Before completing a task:

1.  **Check Key Existence**: Verify that the new keys exist in **ALL 4** JSON files.
2.  **Check Syntax**: Ensure valid JSON (no trailing commas, properly closed braces).
3.  **Visual Verification**: If possible, verify the text appears correctly in the UI.

## 5. Backend Translations (Emails/API)

For emails or backend-generated messages, use the Laravel PHP files in `src/lang/`.
_Note: If a string is used in both Frontend and Backend, it must be added to BOTH the JSON files and the PHP files._
