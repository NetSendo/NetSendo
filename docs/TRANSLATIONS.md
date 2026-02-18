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

---

## Recent Translation Additions

### Partner Program Expansion (January 2026)

The following translation keys were added to support the partner program referral system and team visualization:

#### Backend Translations (`src/lang/{locale}/affiliate.php`)

**Partner Referral Tools:**

- `your_referral_tools` - Referral tools section title
- `your_referral_link` - Referral link label
- `your_referral_code` - Referral code label
- `copy_link` - Copy link button
- `copy_code` - Copy code button
- `link_copied` - Success message after copying
- `referred_signups` - Count of referred user signups
- `referred_by` - Banner showing who referred the user
- `referral_code_optional` - Referral code field label for registration
- `referral_code_placeholder` - Placeholder text for referral code input
- `referral_code_hint` - Help text explaining referral codes

**Partner Team Page:**

- `my_team` - Team page title
- `invite_partners` - Invite partners section title
- `invite_partners_desc` - Description for inviting partners
- `total_partners` - Total partners count label
- `direct_partners` - Direct partners count label
- `team_clicks` - Team clicks metric label
- `team_conversions` - Team conversions metric label
- `team_earnings` - Team earnings metric label
- `partner_tree` - Partner tree visualization title
- `no_partners_yet` - Empty state message
- `share_link_to_grow` - Call-to-action for growing team
- `sub_partners` - Sub-partners label (nested affiliates)
- `view_as_partner` - Admin button to access partner portal

**Usage Example:**

```php
// In controller
return Inertia::render('Partner/Dashboard', [
    'referralCode' => $affiliate->referral_code,
    'referralUrl' => route('affiliate.referral', ['code' => $affiliate->referral_code]),
]);

// In notification
__('affiliate.referred_by', ['name' => $partner->name])
```

**Vue Component Example:**

```vue
<template>
  <h2>{{ $t("affiliate.your_referral_tools") }}</h2>
  <p>{{ $t("affiliate.referred_signups") }}: {{ count }}</p>
</template>
```

### Google Calendar Task Color Settings (January 2026)

The following translation keys were added to support user-customizable task type colors in Google Calendar integration:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Task Color Settings:**

- `calendar.task_colors.title` - Section title
- `calendar.task_colors.description` - Section description
- `calendar.task_colors.custom_color` - Label for custom color picker
- `calendar.task_colors.reset_default` - Button to reset colors
- `calendar.task_colors.saved` - Success toast message

**Usage Example:**

```vue
<h3>{{ $t("calendar.task_colors.title") }}</h3>
<p>{{ $t("calendar.task_colors.description") }}</p>
```

### Segmentation Dashboard (January 2026)

The following translation keys were added to support the AutoTag Pro Segmentation Dashboard feature:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Segmentation Section (`segmentation.*`):**

- `segmentation.title` - Dashboard title
- `segmentation.subtitle` - Dashboard subtitle/description
- `segmentation.stats.total_rules` - Total rules stat card
- `segmentation.stats.active_rules` - Active rules stat card
- `segmentation.stats.executions_30d` - Executions (30 days) stat card
- `segmentation.stats.success_rate` - Success rate stat card
- `segmentation.score_segments.title` - Score segments section title
- `segmentation.score_segments.cold` - Cold (0-20) segment label
- `segmentation.score_segments.warm` - Warm (21-50) segment label
- `segmentation.score_segments.hot` - Hot (51-80) segment label
- `segmentation.score_segments.very_hot` - Very Hot (81+) segment label
- `segmentation.score_segments.no_score` - No score segment label
- `segmentation.tag_distribution.title` - Tag distribution section title
- `segmentation.tag_distribution.empty` - Empty state message
- `segmentation.tag_distribution.subscribers` - Subscribers count label
- `segmentation.engagement_trends.title` - Engagement trends section title
- `segmentation.engagement_trends.subtitle` - Chart subtitle
- `segmentation.engagement_trends.executions` - Y-axis label
- `segmentation.recent_activity.title` - Recent activity section title
- `segmentation.recent_activity.empty` - Empty state message
- `segmentation.recent_activity.view_all` - View all link
- `segmentation.top_triggers.title` - Top triggers section title
- `segmentation.top_triggers.executions` - Executions count label
- `segmentation.quick_actions.title` - Quick actions section title
- `segmentation.quick_actions.create_rule` - Create rule button
- `segmentation.quick_actions.manage_tags` - Manage tags button
- `segmentation.quick_actions.view_logs` - View logs button

**Usage Example:**

```vue
<h1>{{ $t("segmentation.title") }}</h1>
<p>{{ $t("segmentation.subtitle") }}</p>

<div class="stat-card">
  <span>{{ $t("segmentation.stats.total_rules") }}</span>
  <span>{{ stats.totalRules }}</span>
</div>
```

### Copy List Feature (February 2026)

The following translation keys were added to support the Copy List feature for both email and SMS lists:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Mailing Lists (`mailing_lists.*`):**

- `mailing_lists.copy` - Copy button label
- `mailing_lists.copy_success` - Success toast message after copying
- `mailing_lists.copy_modal.title` - Modal title with list name placeholder
- `mailing_lists.copy_modal.description` - Modal description
- `mailing_lists.copy_modal.new_name` - New list name field label
- `mailing_lists.copy_modal.visibility` - Visibility toggle label
- `mailing_lists.copy_modal.options_title` - Copy options section title
- `mailing_lists.copy_modal.copy_subscribers` - Copy subscribers checkbox label
- `mailing_lists.copy_modal.copy_subscribers_desc` - Copy subscribers description with count placeholder
- `mailing_lists.copy_modal.copy_system_messages` - Copy system messages checkbox label
- `mailing_lists.copy_modal.copy_system_messages_desc` - Copy system messages description
- `mailing_lists.copy_modal.copy_system_pages` - Copy system pages checkbox label
- `mailing_lists.copy_modal.copy_system_pages_desc` - Copy system pages description
- `mailing_lists.copy_modal.always_copied` - "Always copied" section title
- `mailing_lists.copy_modal.always_copied_list` - List of always-copied items
- `mailing_lists.copy_modal.submit` - Submit button label

**SMS Lists (`sms_lists.*`):**

- `sms_lists.copy` - Copy button label
- `sms_lists.copy_success` - Success toast message after copying
- `sms_lists.copy_modal.title` - Modal title with list name placeholder
- `sms_lists.copy_modal.description` - Modal description
- `sms_lists.copy_modal.new_name` - New list name field label
- `sms_lists.copy_modal.visibility` - Visibility toggle label
- `sms_lists.copy_modal.options_title` - Copy options section title
- `sms_lists.copy_modal.copy_subscribers` - Copy subscribers checkbox label
- `sms_lists.copy_modal.copy_subscribers_desc` - Copy subscribers description with count placeholder
- `sms_lists.copy_modal.always_copied` - "Always copied" section title
- `sms_lists.copy_modal.always_copied_list` - List of always-copied items
- `sms_lists.copy_modal.submit` - Submit button label

**Usage Example:**

```vue
<template>
  <Modal :show="show" @close="$emit('close')">
    <h2>{{ $t("mailing_lists.copy_modal.title", { name: list.name }) }}</h2>
    <p>{{ $t("mailing_lists.copy_modal.description") }}</p>

    <Checkbox v-model="form.copy_subscribers">
      {{ $t("mailing_lists.copy_modal.copy_subscribers") }}
    </Checkbox>

    <Button @click="submit">
      {{ $t("mailing_lists.copy_modal.submit") }}
    </Button>
  </Modal>
</template>
```

### Deliverability Shield (February 2026)

The following translation keys were added to support the Deliverability Shield feature (DMARC Wiz, Domain Monitoring, InboxPassport AI):

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Deliverability Section (`deliverability.*`):**

- `deliverability.title` - Feature title
- `deliverability.subtitle` - Feature subtitle
- `deliverability.add_domain` - Button to add domain
- `deliverability.domain_name` - Domain name label
- `deliverability.record_type` - DNS record type label
- `deliverability.host` - DNS host label
- `deliverability.target` - DNS target value label

**DMARC Wizard (`deliverability.dmarc_wiz.*`):**

- `deliverability.dmarc_wiz.title` - Wizard title
- `deliverability.dmarc_wiz.subtitle` - Wizard subtitle
- `deliverability.dmarc_wiz.step_domain` - Step 1 label
- `deliverability.dmarc_wiz.step_verify` - Step 2 label
- `deliverability.dmarc_wiz.enter_domain_title` - Step 1 title
- `deliverability.dmarc_wiz.enter_domain_description` - Step 1 description
- `deliverability.dmarc_wiz.add_record_title` - Step 2 title
- `deliverability.dmarc_wiz.add_record_description` - Step 2 description
- `deliverability.dmarc_wiz.dns_propagation_info` - DNS propagation note
- `deliverability.dmarc_wiz.add_and_verify` - Action button

**Dashboard & Domains (`deliverability.domains.*`):**

- `deliverability.domains.title` - Section title for domains
- `deliverability.domains.empty.title` - Empty state title
- `deliverability.domains.empty.description` - Empty state description

**InboxPassport AI (`deliverability.simulations.*`):**

- `deliverability.simulations.recent` - Recent simulations section
- `deliverability.simulations.empty` - Empty state for simulations

**Upsell Screens (`deliverability.upsell.*`):**

- `deliverability.upsell.title` - Upsell title
- `deliverability.upsell.description` - Upsell description
- `deliverability.upsell.featureX` - Feature bullets (1-4)
- `deliverability.upsell.cta` - Upgrade button

**Deliverability Messages (`deliverability.messages.*`):**

- `deliverability.messages.gmail_managed_dns` - Gmail provider info message
- `deliverability.messages.domain_not_configured` - Domain not configured warning
- `deliverability.messages.no_domain_warning` - No domain warning (fallback)

**Content Analysis (`deliverability.content.*` - Backend):**

- `deliverability.content.spam_word` - Spam word detected issue message

### System Email Descriptions (February 2026)

The following translation keys were added to provide descriptions for system emails in the UI:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**System Emails (`system_emails.descriptions.*`):**

- `system_emails.descriptions.signup_confirmation` - Double opt-in verification email
- `system_emails.descriptions.activation_email` - Activation link email
- `system_emails.descriptions.activation_confirmation` - Post-activation confirmation
- `system_emails.descriptions.subscription_welcome` - Welcome email
- `system_emails.descriptions.welcome_email` - Alternative welcome email
- `system_emails.descriptions.already_active_resubscribe` - Resubscribe attempt (active user)
- `system_emails.descriptions.inactive_resubscribe` - Resubscribe attempt (inactive user)
- `system_emails.descriptions.preference_confirm` - Preference update confirmation
- `system_emails.descriptions.data_edit_access` - Data edit link
- `system_emails.descriptions.unsubscribe_request` - Unsubscribe confirmation request
- `system_emails.descriptions.unsubscribed_confirmation` - Unsubscribe confirmed
- `system_emails.descriptions.new_subscriber_notification` - Admin notification about new subscriber

**Usage Example:**

```vue
<p v-if="getEmailDescription(email.slug)">
  {{ $t(`system_emails.descriptions.${email.slug}`) }}
</p>
```

### System Page Descriptions (February 2026)

The following translation keys were added to provide descriptions for system pages in the UI:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**System Pages (`system_pages.descriptions.*`):**

- `system_pages.descriptions.signup_success` - Page after successful signup
- `system_pages.descriptions.signup_exists` - Page when email already exists
- `system_pages.descriptions.signup_error` - Page when signup fails
- `system_pages.descriptions.activation_success` - Page after successful activation
- `system_pages.descriptions.activation_error` - Page when activation link is invalid
- `system_pages.descriptions.unsubscribe_confirm` - Page requesting unsubscribe confirmation
- `system_pages.descriptions.unsubscribe_success` - Page after successful unsubscription
- `system_pages.descriptions.unsubscribe_error` - Page when unsubscription fails

**Usage Example:**

```vue
<p v-if="getPageDescription(page.slug)">
  {{ $t(`system_pages.descriptions.${page.slug}`) }}
</p>
```

### CardIntel & CRM Missing Translations (February 2026)

The following translation keys were added to support CardIntel, CRM activities, and high confidence data:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**CardIntel (`crm.cardintel.*`):**

- `crm.cardintel.scan.upload.webcam_title` - Title for webcam integration
- `crm.cardintel.scan.upload.capture` - Button to capture photo
- `crm.cardintel.actions.email_sent_success` - Success toast for sent email
- `crm.cardintel.actions.generate_new_hint` - Hint for generating new content
- `crm.cardintel.actions.add_to_list_success` - Success toast for adding to list
- `crm.cardintel.record.synced` - Sync status label

**CRM Activities (`crm.activities.type.*`):**

- `crm.activities.type.email` - Activity type label for emails
- `crm.activities.log.task_completed` - Activity log for completed task
- `crm.activities.log.email_sent` - Activity log for sent email
- `crm.activities.log.contact_created` - Activity log for contact creation

**Global Keys:**

- `high_confidence_data` - Label for high confidence data

### Multi-Language Campaign System (February 2026)

The following translation keys were added to support multi-language email campaigns, allowing subscribers to receive messages in their preferred language.

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Message Translations UI (`messages.translations.*`):**

- `messages.translations.title` - Translations section title
- `messages.translations.add` - Add translation button
- `messages.translations.remove` - Remove translation button
- `messages.translations.copy_from_default` - Copy from default language button
- `messages.translations.default_language` - Default language label
- `messages.translations.no_translations` - Empty state message
- `messages.translations.language_select` - Language selector label
- `messages.translations.subject` - Subject field label
- `messages.translations.preheader` - Preheader field label
- `messages.translations.content` - Content field label

**Subscriber Language (`subscribers.*`):**

- `subscribers.fields.language` - Language field label
- `subscribers.fields.language_help` - Language field help text
- `subscribers.table.language` - Language column header in subscriber list
- `subscribers.import.language` - Language field label for CSV import mapping
- `subscribers.preferences.language` - Language selector on public preferences page
- `subscribers.preferences.language_help` - Language help text on public preferences page

| Key                                | PL                   | EN                    | DE                     | ES                  |
| ---------------------------------- | -------------------- | --------------------- | ---------------------- | ------------------- |
| `subscribers.fields.language`      | Język                | Language              | Sprache                | Idioma              |
| `subscribers.fields.language_help` | Preferowany język... | Preferred language... | Bevorzugte Sprache...  | Idioma preferido... |
| `messages.translations.title`      | Tłumaczenia          | Translations          | Übersetzungen          | Traducciones        |
| `messages.translations.add`        | Dodaj tłumaczenie    | Add translation       | Übersetzung hinzufügen | Añadir traducción   |

**Usage Example:**

```vue
<template>
  <!-- Subscriber form -->
  <label>{{ $t("subscribers.fields.language") }}</label>
  <select v-model="form.language">
    <option v-for="lang in languages" :key="lang" :value="lang">
      {{ lang }}
    </option>
  </select>
  <p>{{ $t("subscribers.fields.language_help") }}</p>

  <!-- Message translations tab -->
  <h3>{{ $t("messages.translations.title") }}</h3>
  <button @click="addTranslation">{{ $t("messages.translations.add") }}</button>
</template>
```

### Timezone-Aware Email Sending (February 2026)

The following translation keys were added to support per-subscriber timezone scheduling for email campaigns.

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Message Fields (`messages.fields.*`):**

- `messages.fields.send_in_subscriber_timezone` - Checkbox label for enabling subscriber timezone sending
- `messages.fields.send_in_subscriber_timezone_help` - Help text explaining the feature behavior and fallback logic

| Key                                | PL                                                                                        | EN                                                                             | DE                                                                                    | ES                                                                                    |
| ---------------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| `send_in_subscriber_timezone`      | Wyślij wg strefy czasowej subskrybenta                                                    | Send in subscriber's timezone                                                  | In der Zeitzone des Abonnenten senden                                                 | Enviar en la zona horaria del suscriptor                                              |
| `send_in_subscriber_timezone_help` | Wiadomość zostanie wysłana o wybranej godzinie w strefie czasowej każdego subskrybenta... | The message will be sent at the selected time in each subscriber's timezone... | Die Nachricht wird zur gewählten Uhrzeit in der Zeitzone jedes Abonnenten gesendet... | El mensaje se enviará a la hora seleccionada en la zona horaria de cada suscriptor... |

**Usage Example:**

```vue
<template>
  <div v-if="form.time_of_day || scheduleMode === 'later'">
    <input
      type="checkbox"
      id="send_in_subscriber_timezone"
      v-model="form.send_in_subscriber_timezone"
    />
    <label for="send_in_subscriber_timezone">
      {{ $t("messages.fields.send_in_subscriber_timezone") }}
      <p>{{ $t("messages.fields.send_in_subscriber_timezone_help") }}</p>
    </label>
  </div>
</template>
```

### CRM Auto-Convert Setting (February 2026)

The following translation keys were added to support the auto-convert warm contacts toggle:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**CRM Scoring (`crm.scoring.*`):**

- `crm.scoring.auto_convert_title` - Section title for auto-convert toggle
- `crm.scoring.auto_convert_description` - Description of auto-convert behavior
- `crm.scoring.auto_convert_enabled` - Label when toggle is enabled
- `crm.scoring.auto_convert_disabled` - Label when toggle is disabled

### Subscriber Search Enhancements (February 2026)

The following translation key was added to support the searchable list filter dropdown on the subscriber list page:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Subscribers (`subscribers.*`):**

- `subscribers.search_list_placeholder` - Placeholder text for the searchable list dropdown filter

| Key                                   | PL                               | EN                           | DE                                | ES                              |
| ------------------------------------- | -------------------------------- | ---------------------------- | --------------------------------- | ------------------------------- |
| `subscribers.search_list_placeholder` | Szukaj listy po nazwie lub ID... | Search list by name or ID... | Liste nach Name oder ID suchen... | Buscar lista por nombre o ID... |

**Usage Example:**

```vue
<input
  v-model="listSearch"
  type="text"
  :placeholder="$t('subscribers.search_list_placeholder')"
/>
```

### NetSendo Brain — Agent Labels & Responses (February 2026)

The following hardcoded strings are used by the Brain specialist agents for user-facing responses. They are currently in Polish only and are returned via API/Telegram — not through the Vue i18n system.

> **Note:** These are backend strings embedded in the agent PHP classes. They are not yet localized via Laravel's `__()` helper and are listed here for future i18n extraction.

#### Agent Labels (used in `AgentOrchestrator` intent classification)

| Agent Key      | Label                 | File                    |
| -------------- | --------------------- | ----------------------- |
| `campaign`     | 📧 Campaign Agent     | `CampaignAgent.php`     |
| `list`         | 📋 List Agent         | `ListAgent.php`         |
| `message`      | ✉️ Message Agent      | `MessageAgent.php`      |
| `crm`          | 👥 CRM Agent          | `CrmAgent.php`          |
| `analytics`    | 📊 Analytics Agent    | `AnalyticsAgent.php`    |
| `segmentation` | 🎯 Segmentation Agent | `SegmentationAgent.php` |

#### CRM Agent Response Strings (`CrmAgent.php`)

| Context          | PL (current)                       | EN                                 | DE                                  | ES                               |
| ---------------- | ---------------------------------- | ---------------------------------- | ----------------------------------- | -------------------------------- |
| Contact created  | Kontakt CRM "{name}" utworzony     | CRM contact "{name}" created       | CRM-Kontakt "{name}" erstellt       | Contacto CRM "{name}" creado     |
| Status changed   | Status zmieniony z {old} na {new}  | Status changed from {old} to {new} | Status geändert von {old} auf {new} | Estado cambiado de {old} a {new} |
| Deal created     | Deal "{name}" utworzony w pipeline | Deal "{name}" created in pipeline  | Deal "{name}" in Pipeline erstellt  | Deal "{name}" creado en pipeline |
| Task created     | Zadanie "{title}" utworzone        | Task "{title}" created             | Aufgabe "{title}" erstellt          | Tarea "{title}" creada           |
| Pipeline summary | Pipeline: {name}                   | Pipeline: {name}                   | Pipeline: {name}                    | Pipeline: {name}                 |
| Score analysis   | Analiza Scoring CRM                | CRM Score Analysis                 | CRM-Score-Analyse                   | Análisis de Scoring CRM          |

#### Analytics Agent Response Strings (`AnalyticsAgent.php`)

| Context                 | PL (current)                | EN                      | DE                              | ES                         |
| ----------------------- | --------------------------- | ----------------------- | ------------------------------- | -------------------------- |
| Campaign stats header   | Kampanie ({days}d)          | Campaigns ({days}d)     | Kampagnen ({days}d)             | Campañas ({days}d)         |
| Subscriber stats header | Subskrybenci ({days}d)      | Subscribers ({days}d)   | Abonnenten ({days}d)            | Suscriptores ({days}d)     |
| AI usage header         | AI Brain ({days}d)          | AI Brain ({days}d)      | AI Brain ({days}d)              | AI Brain ({days}d)         |
| No campaigns            | Brak kampanii do porównania | No campaigns to compare | Keine Kampagnen zum Vergleichen | Sin campañas para comparar |
| Trends header           | Trendy ({days}d)            | Trends ({days}d)        | Trends ({days}d)                | Tendencias ({days}d)       |

#### Segmentation Agent Response Strings (`SegmentationAgent.php`)

| Context           | PL (current)                        | EN                             | DE                            | ES                              |
| ----------------- | ----------------------------------- | ------------------------------ | ----------------------------- | ------------------------------- |
| Tag distribution  | Rozkład tagów                       | Tag distribution               | Tag-Verteilung                | Distribución de etiquetas       |
| Score segments    | Segmenty scoring                    | Score segments                 | Score-Segmente                | Segmentos de scoring            |
| Cold segment      | Zimny                               | Cold                           | Kalt                          | Frío                            |
| Warm segment      | Ciepły                              | Warm                           | Warm                          | Cálido                          |
| Hot segment       | Gorący                              | Hot                            | Heiß                          | Caliente                        |
| Super Hot segment | Super Hot                           | Super Hot                      | Super Hot                     | Super Hot                       |
| Tag created       | Tag "{name}" utworzony              | Tag "{name}" created           | Tag "{name}" erstellt         | Tag "{name}" creado             |
| Tag applied       | Tag przypisany do {n} subskrybentów | Tag applied to {n} subscribers | Tag {n} Abonnenten zugewiesen | Tag asignado a {n} suscriptores |
| Automation stats  | Automatyzacje ({days}d)             | Automations ({days}d)          | Automatisierungen ({days}d)   | Automatizaciones ({days}d)      |

### NetSendo Brain — Dashboard Widget (February 2026)

The following translation keys were added to support the Brain AI widget on the main dashboard:

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Dashboard Brain Widget (`dashboard.brain.*`):**

- `dashboard.brain.mode` - Label for the work mode badge
- `dashboard.brain.knowledge` - Label for knowledge base count
- `dashboard.brain.connected` - Telegram connected status
- `dashboard.brain.disconnected` - Telegram disconnected status
- `dashboard.brain.quick_chat_placeholder` - Placeholder for the quick chat input
- `dashboard.brain.open_chat` - Button to open full Brain chat
- `dashboard.brain.settings` - Link to Brain settings
- `dashboard.brain.unavailable` - Message when Brain API is unreachable
- `dashboard.brain.configure` - Link to configure Brain when unavailable

| Key                           | PL                | EN               | DE                | ES                   |
| ----------------------------- | ----------------- | ---------------- | ----------------- | -------------------- |
| `dashboard.brain.mode`        | Tryb pracy        | Work Mode        | Arbeitsmodus      | Modo de trabajo      |
| `dashboard.brain.knowledge`   | Baza wiedzy       | Knowledge Base   | Wissensdatenbank  | Base de conocimiento |
| `dashboard.brain.open_chat`   | Otwórz Chat AI    | Open AI Chat     | KI-Chat öffnen    | Abrir Chat IA        |
| `dashboard.brain.unavailable` | Brain AI niedost. | Brain AI unavail | Brain KI nicht... | Brain IA no dispo... |

**Usage Example:**

```vue
<div class="flex items-center justify-between">
    <span>{{ $t("dashboard.brain.mode") }}</span>
    <span class="badge">{{ currentMode }}</span>
</div>

<input :placeholder="$t('dashboard.brain.quick_chat_placeholder')" />
```

### Brain Settings & Modes (February 2026)

The following translation keys were added to support the Brain Settings page, including Work Modes, AI Model selection, and Task Routing.

#### Frontend Translations (`src/resources/js/locales/*.json`)

**Work Modes (`brain.mode.*`):**

- `brain.mode.title` - Section title
- `brain.mode.autonomous_label` - Label for Autonomous mode
- `brain.mode.autonomous_desc` - Description for Autonomous mode
- `brain.mode.semi_auto_label` - Label for Semi-Auto mode
- `brain.mode.semi_auto_desc` - Description for Semi-Auto mode
- `brain.mode.manual_label` - Label for Manual mode
- `brain.mode.manual_desc` - Description for Manual mode

**AI Model Settings (`brain.model.*`):**

- `brain.model.title` - Section title
- `brain.model.description` - Section description
- `brain.model.provider` - Provider select label
- `brain.model.model` - Model select label
- `brain.model.save_model` - Save button label

**Task Routing (`brain.routing.*` & `brain.task.*`):**

- `brain.routing.title` - Section title
- `brain.routing.description` - Section description
- `brain.routing.hide` / `show` - Toggle button labels
- `brain.task.orchestration` - Label for Orchestration task
- `brain.task.content_generation` - Label for Content Generation task
- `brain.task.analytics` - Label for Analytics task
- `brain.task.campaign` - Label for Campaign task
- `brain.task.crm` - Label for CRM task
- `brain.task.segmentation` - Label for Segmentation task
- `brain.task.conversation` - Label for General Conversation task

| Key                           | PL                      | EN                  | DE                | ES                     |
| ----------------------------- | ----------------------- | ------------------- | ----------------- | ---------------------- |
| `brain.mode.autonomous_label` | 🤖 Pełna autonomiczność | 🤖 Fully Autonomous | 🤖 Vollautonom    | 🤖 Totalmente autónomo |
| `brain.task.orchestration`    | 🧠 Orkiestracja         | 🧠 Orchestration    | 🧠 Orchestrierung | 🧠 Orquestación        |

```

```
