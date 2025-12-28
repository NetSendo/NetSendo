# Changelog

All notable changes to the NetSendo project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.7] - Stripe Integration & Improvements

**Release date:** 2025-12-28

### Added

- **Stripe Payments Integration:**
  - Implemented full Stripe integration for handling product sales and payments.
  - Added `StripeService` for interacting with Stripe API using the database-stored configuration.
  - Created `StripeProduct` model and controller for managing products.
  - Created `StripeTransaction` model for tracking payment history.
  - Added Vue components for Product Management (`StripeProducts/Index.vue`) and Settings (`StripeSettings/Index.vue`).
  - Added **Stripe Settings** page in the panel (Settings -> Stripe Integration) to configure API keys (`publishable_key`, `secret_key`, `webhook_secret`) securely in the database.
  - Added **Stripe Products** page in the panel (Products -> Stripe Products) to manage improved product listings.
  - Sensitive API keys are encrypted in the database for security.
  - Added new sidebar menu items for Stripe Products and Stripe Integration settings.
  - Added comprehensive translations for Stripe features in PL, EN, DE, ES.
  - Installed `stripe/stripe-php` SDK.

## [1.2.6] – Short Description

**Release date:** 2025-12-28

### Added

- **Webhook-Based Password Reset:**

  - Implemented a new password reset flow using an external n8n webhook (`password.webhook-reset`) instead of standard SMTP email.
  - Replaced the standard "Forgot Password" page with a modal in the login view.
  - Reset instructions are now handled by an external automation workflow, making it compatible with environments without configured SMTP.
  - Added rate limiting (`throttle:6,1`) to the reset endpoint for security.
  - Included `origin_url` in the webhook payload to identify the source instance.
  - Added full translations for the password reset modal in EN and PL.
  - Old standard password reset routes (`password.request`, `password.email`) have been removed to prevent access to the legacy flow.

- **Batch Subscriber API Endpoint:**

  - New `POST /api/v1/subscribers/batch` endpoint for creating up to 1000 subscribers in a single request.
  - Returns detailed results: created count, updated count, skipped count, and per-item errors.
  - Supports all subscriber fields: email, phone, first_name, last_name, tags, custom_fields.
  - Webhooks (`subscriber.created`, `subscriber.subscribed`) dispatched asynchronously for each subscriber.

- **Async Webhook Dispatching:**

  - Webhooks are now dispatched asynchronously via Laravel queue for better performance.
  - New `DispatchWebhookJob` handles webhook delivery with 3 retry attempts and 10s backoff.
  - API requests no longer block on webhook HTTP calls, significantly improving response times for batch operations.

- **Custom Fields API Endpoints:**

  - New `GET /api/v1/custom-fields` endpoint to list all user's custom fields with filtering options.
  - New `GET /api/v1/custom-fields/{id}` endpoint to get single custom field details.
  - New `GET /api/v1/custom-fields/placeholders` endpoint returning all available placeholders (system + custom).
  - Enables n8n nodes to dynamically load available fields and placeholders.

- **System Emails Integration:**

  - All 8 system emails from `/settings/system-emails` are now fully functional.
  - **Signup Confirmation (Double Opt-In):** `signup_confirmation` email sent with activation link when double opt-in is enabled.
  - **Activation Confirmation:** `activation_confirmation` email sent after user clicks activation link.
  - **Already Active Notification:** `already_active_resubscribe` email sent when active subscriber tries to sign up again.
  - **Inactive Re-subscribe:** `inactive_resubscribe` email sent when inactive subscriber re-joins list.
  - **Unsubscribe Confirmation:** `unsubscribed_confirmation` email sent after successful unsubscribe.
  - New `SystemEmailMailable` class for rendering any system email with placeholders.
  - New `SystemEmailService` for centralized email sending.
  - New `ActivationController` for handling signed activation links.
  - New `SendUnsubscribeConfirmation` listener for `SubscriberUnsubscribed` event.

- **System Pages Integration:**

  - All 10 system pages from `/settings/system-pages` are now fully functional.
  - New `UnsubscribeController` for public unsubscribe flow using SystemPage templates.
  - Unsubscribe now shows `unsubscribe_confirm`, `unsubscribe_success`, or `unsubscribe_error` pages.
  - List-specific and global unsubscribe routes support signed URLs.
  - `signup_exists`, `signup_exists_active`, `signup_exists_inactive` pages used in form submission flow.

- **New `subscriber.resubscribed` Webhook Event:**
  - Added new webhook event `subscriber.resubscribed` for tracking re-activations (when unsubscribed/inactive users sign up again).
  - Includes `previous_status` in payload for automation workflows to know the subscriber's prior state.

### Fixed

- **Webhook Triggers for All Subscription Scenarios:**

  - Fixed missing webhooks for re-subscription and already-active scenarios.
  - Form submissions now dispatch: `subscriber.subscribed` (new), `subscriber.resubscribed` (re-activation), `subscriber.updated` (already active).
  - API `POST /api/v1/subscribers` now returns 200 with subscriber data instead of 409 when adding existing subscriber to a new list.
  - API `POST /api/v1/subscribers/batch` now correctly handles re-activation scenarios with proper webhooks.
  - All subscription scenarios (form and API) now trigger appropriate webhooks for n8n and other integrations.

- **System Pages Not Used After Form Submission:**

  - Fixed issue where customizable system pages from `/settings/system-pages` were not rendered after form submission.
  - Form success and error pages now properly use content from `SystemPage` model instead of hardcoded Polish text.
  - New `system-page.blade.php` template supports dynamic HTML content with placeholder replacement.
  - System pages now correctly fall back from list-specific to global defaults.
  - Icon type (success/error/warning/info) is automatically determined based on page type.
  - Added migration to ensure all system page slugs exist in the database.

- **Global Stats Query Error:**

  - Fixed `SQLSTATE[42S22]: Column not found` error in Global Stats when filtering by date.
  - Resolved scope issue with `contact_list_subscriber.updated_at` in `whereHas` query constraints.

- **System Emails Not Sending:**
  - Fixed critical issue where system emails (signup confirmation, activation, etc.) were not being delivered.
  - `SystemEmailService` was using the default Laravel mail driver instead of the configured mailbox.
  - System emails now correctly use the list's default mailbox, user's default mailbox, or any active system mailbox as fallback.
  - Added detailed logging with mailbox information for troubleshooting.

## [1.2.5] – Placeholder Personalization & n8n Documentation

**Release date:** 2025-12-28

### Added

- **Dynamic Placeholders on Thank You Page:**

  - Thank you page after form submission now supports dynamic placeholders (`[[first_name]]`, `[[email]]`, etc.).
  - Users can personalize success page title and message using subscriber data.
  - New `success_title` field on forms for customizable heading (e.g., `[[first_name]], dziękujemy!`).
  - Uses signed URLs for secure subscriber data passing to thank you page.
  - Works with all standard fields and custom fields defined in the system.

- **Placeholder Picker for System Pages:**

  - New "Available Placeholders" section in System Pages editor showing all available placeholders.
  - Placeholders are grouped by type: Standard Fields, System Placeholders, Custom Fields.
  - Click-to-copy functionality for easy insertion into content.
  - Supports user-defined custom fields from "Zarządzanie polami" settings.
  - Works with all standard fields and custom fields defined in the system.

- **n8n Subscriber Inserts Documentation:**

  - New `docs/N8N_SUBSCRIBER_INSERTS_GUIDE.md` with comprehensive instructions for n8n node agent.
  - Documents `custom_fields` support for subscriber creation/update via API.
  - Lists all available placeholders (`[[fname]]`, `[[email]]`, `[[phone]]`, etc.) for email/SMS personalization.
  - Includes TypeScript code examples for n8n node implementation.
  - Updated `API_DOCUMENTATION.md` with new "Wstawki (Placeholders)" section.

### Fixed

- **Form Submission Error:**

  - Fixed `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'source'` error during form submission.
  - Added missing `source` column to `contact_list_subscriber` table via new migration.
  - Updated `Subscriber` and `ContactList` models to include `source` in pivot relationships.

- **Subscriber Duplicate Check:**

  - Fixed `Integrity constraint violation: 1062` error when a subscriber re-subscribes via form.
  - Updated `createOrUpdateSubscriber` to scope lookup by `user_id` and include soft-deleted records (`withTrashed`), restoring them if found to prevent unique constraint violations.

- **Console Command Error:**
  - Fixed `LogicException: An option named "verbose" already exists` in `ProcessEmailQueueCommand`.
  - Removed conflicting `--verbose` option definition from command signature as it overlaps with Symfony defaults.

## [1.2.4] – Short Description

**Release date:** 2025-12-27

### Added

- **Queue Stats Modal for Autoresponders:**

  - Implemented detailed statistics modal for autoresponder messages.
  - Shows breakdown of scheduled recipients (tomorrow, day after, 3-7 days, 7+ days).
  - Identifies "missed" subscribers who joined before the message's day offset.
  - Added "Send to missed subscribers" functionality to manual triggering sends for missed recipients.
  - Integrated into Message List with a new calendar icon action.
  - Full translations for EN, PL, DE, ES.

- **System Logs Viewer:**

  - New logs viewer page at `/settings/logs` for monitoring `storage/logs/laravel.log`.
  - Log level filtering (ERROR, WARNING, INFO, DEBUG) with color-coded display.
  - Search functionality to find specific log entries.
  - Auto-refresh mode (every 5 seconds) for real-time monitoring.
  - Manual log clearing with confirmation modal.
  - Configurable log retention settings (6h to 7 days, default 24h).
  - Automatic log cleanup via scheduled CRON command (`logs:clean`).
  - **Webhook Logs Tab:** Dedicated view for tracking webhook execution history with:
    - Stats cards showing total, successful, failed webhooks and average response time (24h).
    - Filterable table by status (success/failed) and event type.
    - Expandable rows showing payload, response body, and error details.
    - New `webhook_logs` database table for structured logging.
  - Link added to "Help & Resources" sidebar menu.
  - Full translations for PL, EN, DE, ES.

### Fixed

- **Custom Field Creation:**

  - Fixed issue where creating new "Text" / "Number" type custom fields failed silently due to empty options data validation error.
  - Implemented automatic data sanitization in form submission to remove invalid empty options.

- **Form Submission Redirects:**

  - Fixed issue where form submissions were resulting in 404 errors instead of correct redirects.
  - Implemented priority-based redirect logic: Form settings -> List settings (Success/Confirmation page) -> Global settings.
  - Added support for "External Page" and custom URL redirections from List settings.

- **List Webhooks:**

  - Fixed issue where `subscribe` webhook event was not being triggered for public form submissions.
  - Submissions from public forms now correctly trigger configured List webhooks with full subscriber data.

- **Form Submission 404 Error:**

  - Fixed critical route conflict causing 404 errors when submitting embedded forms.
  - Removed deprecated `/subscribe/{contactList}` route that conflicted with form slug-based routing.
  - Added CSRF token exclusion for `/subscribe/*` routes to enable cross-domain form submissions.

- **Webhook Triggers:**
  - Fixed `subscriber.created` webhook not being triggered when a subscriber is created via public form.
  - Fixed `subscriber.subscribed` webhook not being triggered when a subscriber joins a list via public form.
  - Refactored `FormSubmissionService` to use global `WebhookDispatcher` for consistent event dispatching.

## [1.2.3] – Short Description

**Release date:** 2025-12-27

### Added

- **PDF Attachments Indicator:**

  - Added visual indicator (PDF icon) in the email message list for messages with PDF attachments.
  - Implemented smart tooltip showing the count and filenames of attached PDF files.
  - Backend now exposes `pdf_attachments` data in the message list API.

- **PDF Attachments for Emails:**
  - Added ability to attach PDF files to emails (max 5 files, 10MB each).
  - New `message_attachments` table and `MessageAttachment` model.
  - Integration with SMTP, SendGrid, and Gmail providers.
  - Drag-and-drop file upload in Message Editor.
  - Full management (add/remove) of attachments during message creation and editing.
  - Polish translations for attachment interface.

## [1.2.2] – UI Improvements & Bug Fixes

**Release date:** 2025-12-26

### Added

- **AI Executive Summary for Campaign Auditor:**

  - New AI-generated executive summary displayed after each audit.
  - Summary uses user's preferred language and informal tone ("Ty" not "Państwa").
  - New `ai_summary` column in `campaign_audits` table.
  - Uses `max_tokens_large` from selected AI integration for complete responses.
  - Summary displayed in dedicated section with gradient styling on Campaign Auditor page.

- **AI Model Selection for Campaign Advisor:**

  - New "AI Model" dropdown in Campaign Advisor settings.
  - Users can select which AI integration to use for audit summaries.
  - Falls back to default integration if selected one is not active.

- **AI Summary Widget on Dashboard:**

  - Short AI summary excerpt displayed in Health Score Widget.
  - Shows key findings (second paragraph) instead of intro text.
  - Fallback to data-based summary if AI summary unavailable.

- **Community Links:**

  - Added Official Telegram Channel link to Help Modal.

- **Developer Experience:**

  - Added `bug_report.md` GitHub Issue template for standardized bug reporting.

- **Translations:**
  - Added `ai_executive_summary` translation key to EN, PL, DE, ES.
  - Added `ai_model` translation key to EN, PL, DE, ES.
  - Added `common.default` translation key to EN, PL.
  - Added missing `help_menu.telegram` translation key to EN, PL, DE, ES locales.
  - Added `rate_limit_title` and `error_title` translation keys to EN, PL, DE, ES locales.

### Changed

- **Dashboard Layout:**
  - Quick Actions widget moved below Activity chart with full-width 4-column layout.
  - Health Score Widget now displays alone in the right column for better visual balance.
  - Added `columns` prop to QuickActions component for flexible grid configuration.
- **Campaign Auditor Dark Mode:**
  - Fixed dark mode styling for select dropdowns in settings (added `dark:text-white`).

### Fixed

- **AI Summary Generation:**

  - Fixed `Undefined array key 0` error when generating AI summary with empty recommendations.
  - Fixed SQL error with incorrect column names (`label`/`model` → `name`/`default_model`).

- **Help Modal Updates:**

  - Updated Documentation link to `https://netsendo.com/en/docs`.
  - Updated "Report a bug" link to professional GitHub Issues flow.
  - Hidden "Courses and Training" link.

- **Campaign Auditor Rate Limit Error Modal:**
  - Replaced native `window.alert()` with styled Vue Modal component for 429 rate limit errors.
  - Error modal now displays properly instead of being blocked or disappearing.
  - Added visual distinction between rate limit errors (amber) and general audit errors (red).

## [1.2.1] – AI Campaign Auditor & Advisor

**Release date:** 2025-12-26

### Added

- **AI Campaign Auditor Module:**

  - New AI-powered campaign analysis tool that identifies issues, risks, and optimization opportunities.
  - 8 analysis types: frequency, content quality, timing, segmentation, deliverability, automations, revenue impact, and AI-powered insights.
  - Overall health score (0-100) with color-coded severity levels (Excellent/Good/Needs Attention/Critical).
  - Issues categorized by severity (Critical/Warning/Info) with expandable recommendations.
  - "Mark as Fixed" functionality for tracked issues.
  - Estimated monthly revenue loss calculation based on detected issues.
  - New database tables: `campaign_audits`, `campaign_audit_issues`.
  - New backend: `CampaignAuditorService`, `CampaignAuditorController`, `CampaignAuditPolicy`, and Eloquent models.
  - Full Polish and English translations.
  - Sidebar navigation under Automation section with "AI" badge.

- **AI Campaign Advisor (Recommendations):**

  - New AI-powered recommendation engine providing actionable advice for campaign improvement.
  - Three recommendation categories: **Quick Wins** (low-effort fixes), **Strategic** (medium-term improvements), **Growth** (AI-generated scaling opportunities).
  - New `CampaignAdvisorService` generating recommendations from audit issues, historical data, and AI analysis.
  - New `CampaignRecommendation` model with types (quick_win, strategic, growth), effort levels (low, medium, high), and expected impact tracking.
  - New database table: `campaign_recommendations` with migration.
  - Extended `CampaignAuditorController` with recommendation endpoints: fetch, apply, measure impact, and settings management.
  - Configurable user settings persisted in account: **Weekly Improvement Target** (1-10%), **Max Recommendations** (3-10), **Analysis Language** (EN/PL/DE/ES/FR/IT/PT/NL).
  - Settings panel UI in Campaign Auditor page with language selector for AI-generated analysis.
  - Applied recommendation tracking with timestamp and effectiveness measurement.
  - Visual recommendation cards with color-coded categories (emerald/blue/purple themes).
  - Full translations for EN, PL, DE, and ES locales.

- **Campaign Health Score Dashboard Widget:**

  - New `HealthScoreWidget` component displayed on the main Dashboard.
  - Shows circular score gauge, issue counts (critical/warnings/info), and stale audit warning.
  - Direct link to full Campaign Auditor page for detailed analysis.

- **Automated Daily Campaign Audit:**

  - New Artisan command: `php artisan audit:run` with `--all` and `--user=ID` options.
  - Scheduled daily at 05:00 via Laravel Scheduler.
  - Logs saved to `storage/logs/campaign-audit.log`.

- **Campaign Advisor Settings in Profile Page:**
  - New `CampaignAdvisorSettingsForm` component on user Profile page.
  - Users can configure advisor settings from two locations: Campaign Auditor page and Profile page.
  - Settings include: weekly improvement target, max recommendations count, and analysis language.

### Fixed

- **Campaign Auditor UI Issues:**
  - Fixed sidebar menu collapsing when navigating to Campaign Auditor or Campaign Architect pages.
  - Fixed dark mode visibility for issue count labels (Critical/Warnings/Info) - text now properly visible on dark backgrounds.
  - AI Advisor section now displays when an audit exists (not only when recommendations are present), allowing access to settings panel.

## [1.2.0] – AI Campaign Architect

**Release date:** 2025-12-25

### Added

- **AI Campaign Architect Module:**
  - New AI-powered campaign planning wizard for strategic email/SMS campaign creation.
  - 4-step wizard flow: Business Context → Audience Selection → AI Strategy Generation → Forecast & Export.
  - Business context inputs: industry, business model, campaign goal, AOV, margin, decision cycle.
  - Multi-list audience selection with real-time subscriber statistics.
  - AI-generated campaign strategy with message sequence, timing, and conditional logic.
  - Interactive forecast dashboard with ROI projections and adjustable sliders.
  - Campaign language selection (12 languages) for AI-generated content - allows creating campaigns in different language than UI.
  - Export functionality to create messages as drafts or scheduled campaigns.
  - Industry benchmark data for forecast calculations.
  - New database tables: `campaign_plans`, `campaign_plan_steps`, `campaign_benchmarks`.
  - New backend: `CampaignArchitectService`, `CampaignArchitectController`, models, and policies.
  - Full Polish and English translations.
  - Sidebar navigation with "AI" badge.

## [1.1.3] – SMS List Enhancements & UI Fixes

**Release date:** 2025-12-25

### Added

- **SMS List Advanced Settings:**
  - Added Integration settings tab with API key generation and webhook configuration.
  - Added CRON settings tab with custom schedule configuration per SMS list.
  - Added Advanced settings tab with co-registration (parent list sync) and limits.
  - New routes: `sms-lists.generate-api-key` and `sms-lists.test-webhook`.
  - Expanded `SmsListController` with `generateApiKey()` and `testWebhook()` methods.
  - Full Polish and English translations for all new SMS list settings.

### Improved

- **SMS Campaign List Display:**

  - Added subscriber count display in the "Audience" column of SMS campaigns list (matching email campaigns behavior).
  - Added `recipients_count` field to `SmsController::index()` response.

- **SMS Campaign Creation:**
  - Improved list selection indicator to show "X selected of Y lists" format for better clarity.

### Fixed

- **Visibility Filter:**
  - Added visibility filter (public/private) support to SMS list index, matching mailing list functionality.

## [1.1.2] – Notification System & Translations

**Release date:** 2025-12-25

### Added

- **In-App Notification System:**

  - New real-time notification dropdown in application header with animated unread badge.
  - Backend: `Notification` model, `NotificationService`, and `NotificationController` with full API.
  - Database migration for `notifications` table with support for types (info/success/warning/error).
  - Polling mechanism (60s interval) to check for new notifications.
  - Helper methods for common events: new subscriber, campaign sent, automation executed, SMTP errors, license expiring.
  - Mark as read (individual and bulk) functionality.

- **SMS AI Assistant:**

  - Added new AI generation feature for SMS content (similar to email assistant).
  - Support for tone selection (Casual, Formal, Persuasive) and multiple suggestions (1 or 3).
  - Includes SMS-specific character counting and GSM/Unicode detection.
  - New `SmsAiAssistant` Vue component integrated into SMS creation page.

- **SMS Preview with Data:**

  - Added "Preview with Data" feature to SMS editor.
  - Allows previewing content with real subscriber data (replacing `[[first_name]]`, etc.).
  - Added dynamic placeholder replacement API (`POST /sms/preview`).
  - Added subscriber search for preview context.

- **Backend AI Extensions:**
  - New `TemplateAiService::generateSmsContent()` method optimized for plain-text SMS messages.
  - New API endpoint `POST /api/templates/ai/generate-sms-content`.

### Improved

- **Translations:**

  - Added `notifications` section to vue-i18n locale files (EN/PL) with all notification-related keys.
  - Created new PHP translation files: `license.php`, `sms_providers.php`, `common.php` (EN/PL).

- **SMS Editor UX:**
  - Integrated "Insert Variable" dropdown for quick placeholder insertion.
  - Enhanced phone mockup preview with dynamic data substitution.
  - Added full Polish and English translations for all new SMS features.

## [1.1.1] – Short Description

### Added

- **SMS API Extensions:**
  - New `SmsController` with comprehensive endpoints:
    - `POST /api/v1/sms/send`: Send single SMS.
    - `POST /api/v1/sms/batch`: Batch send SMS to lists or tags.
    - `GET /api/v1/sms/status/{id}`: Check SMS delivery status.
    - `GET /api/v1/sms/providers`: List available SMS providers.
  - Added SMS-specific webhook events: `sms.queued`, `sms.sent`, `sms.failed`.
  - Added new API key permissions: `sms:read` and `sms:write`.

### Documentation

- **n8n Integration:**
  - Created detailed implementation guide for n8n SMS node (`docs/N8N_SMS_IMPLEMENTATION.md`).
  - Added SMS resource definition, operations, and trigger events for n8n agent.
- **API Documentation:**
  - Updated `API_DOCUMENTATION.md` with complete SMS section and examples.
  - Updated permissions table with new SMS access rights.

## [1.1.0] – Short Description

**Release date:** 2025-12-24

### Changed

- **Mailing List System Refactor:**

  - Separated "Mailing Lists" and "SMS Lists" into distinct views for clearer management.
  - "Mailing Lists" view now strictly shows only Email-type lists.
  - "SMS Lists" view continues to show SMS-type lists.
  - SMS Campaigns can now target both SMS and Email lists (filtering for subscribers with phone numbers).

- **Conditional Validation:**
  - Implemented smart validation for subscribers based on list type:
    - **Email Lists:** Email address is required.
    - **SMS Lists:** Phone number is required.
    - **Mixed:** Both fields are required if adding to both list types simultaneously.
    - Applies to Admin Panel, API, Public Subscription Forms, and CSV Import.

### Added

- **List Filters in Campaign Creation:**
  - Added list type filter dropdown (All/Email/SMS) to both Email and SMS campaign creation forms.
  - Added search input for filtering lists by name.
  - Shows filtered list count (e.g., "3 of 10 lists").
  - Improved usability for users with large numbers of contact lists.

### Fixed

- **Email Campaign List Selection:**

  - Fixed missing list type data in Email campaign creation form.
  - Added `type` field to contact lists query in `MessageController::create()` and `MessageController::edit()`.
  - List type filtering now works correctly in both autoresponder and broadcast modes.

- **SMS Lists Route Error:**
  - Fixed `Call to undefined method SmsListController::show()` error when accessing SMS lists.
  - Excluded unused `show` route from SMS lists resource routes in `web.php`.
  - Updated `SmsList/Index.vue` to use `sms-lists.edit` route instead of non-existent `sms-lists.show`.
  - Regenerated Ziggy routes to sync frontend route list with backend.

### Improved

- **Subscriber Management UX:**

  - Updated "Add/Edit Subscriber" forms with list type filtering (All/Email/SMS).
  - Added dynamic "Required" (`*`) indicators that update in real-time based on selected lists.
  - Added informational alerts explaining which fields are required for the selected combination.

- **CSV Import:**
  - Extended import functionality to support phone numbers for SMS lists.
  - Implemented validation logic during import to ensure SMS list imports have valid phone numbers.

## [1.0.21] – Short Description

**Release date:** 2025-12-24

### Added

- **SMS Queue System:**

  - New `ProcessSmsQueueCommand` artisan command (`cron:process-sms-queue`) for processing SMS queue.
  - New `processSmsQueue()` method in `CronScheduleService` handling SMS dispatch with schedule/volume limits.
  - SMS queue scheduler entry running every minute (same as email queue).
  - Dedicated log file: `storage/logs/cron-sms-queue.log`.
  - Respects global and per-list CRON schedules and volume limits.
  - Validates subscriber phone numbers before dispatch.
  - Dry-run mode (`--dry-run`) for testing without sending.

- **SMS Integration:**

  - Implemented comprehensive SMS capability with multi-provider support.
  - Supported Providers: **Twilio**, **SMS API** (PL/COM), **Vonage (Nexmo)**, **MessageBird**, **Plivo**.
  - New "SMS Providers" settings page for credential management and connection testing.
  - Configurable daily limits per provider.
  - Secure credential storage (encryption).
  - Background job system for asynchronous SMS sending (`SendSmsJob`).
  - Added "Dostawcy SMS" link to the main sidebar.

- **Google Analytics Integration:**
  - Integrated Google Analytics 4 (gtag.js) tracking for all NetSendo installations.
  - Tracking code hardcoded in `partials/google-analytics.blade.php` for universal deployment monitoring.
  - Automatically tracks all users across all domains where NetSendo is installed.

## [1.0.20] – Short Description

**Release date:** 2025-12-24

### Added

- **API Triggers (Webhooks):**
  - Implemented comprehensive webhook system for real-time event notifications.
  - New endpoints: `CRUD /api/v1/webhooks` for managing webhook subscriptions.
  - Supported events: `subscriber.created`, `subscriber.updated`, `subscriber.deleted`, `subscriber.subscribed`, etc.
  - Security: HMAC-SHA256 signature verification (`X-NetSendo-Signature`) for all payloads.
  - Built-in failure tracking and automatic deactivation after 10 consecutive failures.
  - Integrated with `n8n` via new "NetSendo Trigger" node support.

## [1.0.19] – Short Description

**Release date:** 2025-12-24

### Added

- **Marketplace Integration:**
  - Added dedicated page for **n8n** integration (`/marketplace/n8n`) including installation instructions and feature overview.
  - Updated Marketplace dashboard to mark n8n as "Available" and link to the integration page.

## [1.0.18] – Short Description

**Release date:** 2025-12-24

### Added

- **Global "Quick Start" Modal:**

  - Implemented a "Quick Start" (Szybki start) link in the Help menu sidebar.
  - Opens a global onboarding modal with a progress checklist (License, CRON, Profile, List, Subscribers, Campaign).
  - Accessible from any page in the application.

- **Dashboard Setup Tracker:**
  - Added a slim "Setup Tracker Bar" to the top of the dashboard.
  - Only appears when critical configuration is missing (License, AI Integration, Mailbox, CRON).
  - Automatically hides when all critical steps are completed.

### Changed

- **API Key Deletion UI:**

  - Replaced the native browser confirmation dialog with a custom modal for deleting API keys.
  - Improved user experience with a consistent and integrated modal design in `Settings > API Keys`.

- **Onboarding Experience:**
  - Removed the large "Centrum Startu" card from the Dashboard to reduce clutter.
  - Replaced it with the more subtle Tracker Bar and the on-demand Quick Start modal.

### Fixed

- **Subscriber API 500 Error:**
  - Fixed `Call to a member function first() on null` error in `SubscriberResource`.
  - Resolved issue with accessing the `tags` relationship on the Subscriber model during API resource transformation.
  - Implemented robust relationship handling to prevent null pointer exceptions when fetching subscribers via API (e.g., n8n).

### Backend

- **Global Stats:**
  - Updated `GlobalStatsController` to return counts for `ai_integrations_count` and `mailboxes_count` to support the tracking logic.

## [1.0.17] – Short Description

**Release date:** 2025-12-23

### Added

- **AI Voice Dictation:**
  - Added microphone support to AI Assistant input fields (`MessageAiAssistant`, `SubjectAiAssistant`, `TemplateBuilder/AiAssistant`).
  - Implemented `useSpeechRecognition` composable for Web Speech API integration.
  - Added real-time transcript preview and visual recording feedback.
  - Added voice dictation support for multiple languages (PL, EN, DE, ES).

### Improved

- **Dashboard Activity Chart:**
  - Redesigned the Activity chart using `vue-chartjs` (Chart.js) to fix blurriness issues on high-DPI screens.
  - Added interactive tooltips showing exact values when hovering over bars.
  - Improved chart animations and visual styling to match the application theme (Indigo/Emerald/Amber).
  - Standardized chart implementation for better maintainability and performance.

## [1.0.16] – Short Description

**Release date:** 2025-12-23

### Improved

- **Gmail Integration:**
  - Added "Pending Authorization" status for Gmail mailboxes that are active but not connected.
  - Implemented automatic modal re-opening after creation to prompt user for "Connect with Google".
  - Fixed "active" status badge being misleadingly shown for unconnected Gmail mailboxes.

### Fixed

- **Mailbox Form Validation:**
  - Fixed validation interference where browser autofill from hidden SMTP/SendGrid tabs caused errors when saving Gmail mailboxes.
  - Implemented strict field clearing for `from_email` and `credentials` when submitting Gmail forms.
- **Translations:**
  - Added missing `pending_auth` translation key.

### Improved

- **AI Assistant Panel Redesign:**
  - Significantly widened the AI Assistant side panel (from `max-w-md` to `max-w-2xl/3xl`) for better visibility and usage on larger screens.
  - Added visible, custom-styled scrollbars to the panel, prompts, and preview areas to improve accessibility for users without touchpads.
  - Optimized the internal layout and grid systems to adapt to the wider panel size.

## [1.0.15] – List Integration & Advanced Settings

**Release date:** 2025-12-23

### Added

- **List-Level API Integration:**

  - New "Integracja" (Integration) sub-tab in mailing list settings.
  - List-specific API key generation and management (format: `ml_{list_id}_{random_string}`).
  - Webhook configuration with customizable events: subscribe, unsubscribe, update, bounce.
  - Test webhook functionality to verify endpoint connectivity.
  - API subscription endpoint: `POST /api/v1/lists/{id}/subscribe` for external integrations.
  - API unsubscribe endpoint: `POST /api/v1/lists/{id}/unsubscribe`.
  - Displays List ID (MLID) and API usage examples in the UI.

- **Advanced List Settings (Co-registration & Limits):**

  - Expanded "Zaawansowane" (Advanced) sub-tab with new features.
  - Co-registration: select parent list for automatic subscriber synchronization.
  - Sync settings: configurable sync on subscribe/unsubscribe events.
  - Maximum subscribers limit per list (0 = unlimited).
  - Block signups toggle to temporarily disable new subscriptions.

- **New Backend Components:**
  - `ListSubscriptionController` - API controller for external list subscriptions with API key authentication.
  - `ContactList::generateApiKey()` - method to generate unique list API keys.
  - `ContactList::triggerWebhook()` - method to dispatch webhooks to configured endpoints.
  - `ContactList::canAcceptSignups()` - method to check signup eligibility (limits, blocks).
  - `ContactList::syncToParentList()` - method for co-registration synchronization.

### Fixed

- **Sidebar Navigation Links:**
  - Fixed broken links for "Zaawansowane" and "Integracja" menu items that were pointing to non-existent routes.
  - "Zaawansowane" now links to Default Settings.
  - "Integracja" now links to API Keys.

### Database

- New migration: `2025_12_23_010000_add_integration_settings_to_contact_lists`
  - Added columns: `api_key`, `webhook_url`, `webhook_events`, `parent_list_id`, `sync_settings`, `max_subscribers`, `signups_blocked`, `required_fields`

### Translations

- Added new translation keys for Integration and Advanced settings in PL and EN.

## [1.0.14] – Short Description

**Release date:** 2025-12-23

### Added

- **Anti-Spam Headers Configuration:**
  - Implemented `List-Unsubscribe` and `List-Unsubscribe-Post` header support for improved email deliverability.
  - Added "Sending Settings" UI for configuring these headers at both the global default level and individual mailing list level.
  - List-specific header settings override global defaults.
  - Headers are now correctly passed to all mail providers (SMTP, SendGrid, Gmail).
  - Added "Insert Template" helper buttons to easily populate standard header values.
  - Implemented smart auto-fill: `List-Unsubscribe` headers are automatically populated based on the selected mailing list mailbox (sender email) to ensure valid `mailto:` links.
- **Enhanced Subscription Form Builder:**
  - Modernized design with "Glassmorphism", "Modern Dark", and "Gradient" presets.
  - Transparent background support with RGBA color picker and opacity slider.
  - Professional styling effects: customizable shadows (blur, opacity, offsets), linear gradients (8 directions), and entry animations (fadeIn, slideUp, pulse, bounce).
  - Explicit placeholder customization for each form field.
  - "Transparent container" toggle to quickly show only fields and buttons.
  - Integration with contact list settings for dynamic post-submission redirects based on Double Opt-in status.
  - Real-time preview improvements including border width, padding, and mobile/desktop toggle.
- **Enhanced Subscriber Management:**
  - Added bulk actions: delete multiple subscribers, move subscribers between mailing lists, and change status (active/inactive) in bulk.
  - Implemented customizable column visibility for the subscriber list, including support for phone numbers and dynamic custom field columns.
  - Added persistence for column visibility preferences using browser local storage.
  - Added sorting functionality by email, name, phone, and date joined.
  - Added new UI components: `BulkActionToolbar`, `MoveToListModal`, and `ColumnSettingsDropdown`.
  - Added translation support for all new subscriber management features in English and Polish.
- **Form Builder Error Handling:**
  - Added console logging and user alerts for form validation errors to prevent silent save failures.
  - Implemented automatic data transformation to convert empty URL and message strings to `null` before submission.
- **Phone Input with Country Picker:**
  - Created a new `PhoneInput.vue` component featuring a country selector with emoji flags and international dial codes (50+ countries supported).
  - Integrated the `PhoneInput` component into the "Add Subscriber" and "Edit Subscriber" forms to ensure consistent and correct phone number formatting.
- **Subscriber Fields & UI:**
  - Added `Phone`, `Gender`, and `Global Status` fields to subscriber profiles.
  - Updated "Add/Edit Subscriber" forms to support multi-select for contact lists.
  - Added "Send Welcome Email" toggle to the subscriber creation form.
  - Implemented dynamic rendering for Custom Fields in subscriber forms.
- **Translations:**
  - Added missing translations for subscriber features (gender, phone, welcome email, multi-list helper) in EN, PL, DE, ES.

### Fixed

- **Subject AI Assistant Scroll Behavior:**
  - Fixed issue where the Subject AI Assistant dropdown would close when scrolling the page.
  - The modal now updates its position on scroll and stays visible until explicitly closed by clicking outside or pressing the close button.
  - Added proper click-outside detection and cleanup on component unmount.
- **Subscriber List Routing:** Fixed a routing conflict where bulk action routes were being intercepted by the subscriber resource routes; moved bulk routes before resource routes to ensure correct matching.
- **Vue 3 Template Conflicts:** Resolved a `TypeError` in the subscriber list by fixing a `v-if`/`v-for` conflict on the same element and adding proper null checks for custom fields.
- **Ziggy Route Synchronization:** Ensured all new subscriber routes are correctly synchronized with the Ziggy route list for frontend usage.
- **Form Save Failures:**
  - Fixed issue where forms would not save by making all boolean styling and configuration fields `nullable` in `SubscriptionFormController` validation.
  - Resolved URL validation errors caused by empty strings being sent instead of `null` for redirect and policy URLs.
- **Placeholder Customization:** Fixed UI issue where field placeholders were difficult to edit; they are now clearly exposed in the field settings panel.
- **Subscriber Many-to-Many Relationship Alignment:**
  - Resolved multiple `QueryException` errors by fixing outdated queries still referencing the removed `contact_list_id` column on the `subscribers` table.
  - Updated `ContactList::subscribers()` relationship to `belongsToMany` to match the pivot table implementation.
  - Refactored `Api/V1/SubscriberController` CRUD and search endpoints to properly use pivot table relationships and many-to-many filtering.
  - Fixed subscriber transfer logic in `MailingListController` and `SmsListController` to use pivot table detach/attach operations.
  - Updated `Message::getUniqueRecipients()` and `GlobalStatsController` to query subscribers across multiple lists through the relationship.
  - Removed outdated singular `contactList` relationship references in `Subscriber` model and `MessageController`.
- **Subscriber Controller Bug:** Fixed an `ErrorException` (Undefined variable `$request`) in the `update` method of `SubscriberController` by passing the `$request` variable to the database transaction closure.

### Changed

- **Subscriber System Refactor:**
  - **Many-to-Many Relationship:** Refactored database schema to allow subscribers to belong to multiple contact lists simultaneously without duplication.
  - **Unique Email Constraint:** Subscribers are now unique by email per user account, resolving data redundancy issues.
  - **Migration Fix:** Resolved `Duplicate column name 'phone'` error by implementing idempotent migration checks in `refactor_subscribers_relationship`.

## [1.0.13] – Short Description

**Release date:** 2025-12-22

### Fixed

- **Advanced Editor Rendering:**

  - Fixed a critical rendering issue caused by incorrect TipTap extension imports (default vs named exports).
  - Fixed `SyntaxError` with `@tiptap/extension-text-style` in Vite build.
  - Fixed Vue runtime error (`Property "window" was accessed during render`) in Emoji Picker positioning by creating a safe computed property.

- **Message Duplication Queue Bug:**
  - Fixed critical issue where duplicated messages (both broadcast and autoresponder) would not receive new subscribers in their queue.
  - When duplicating a message, queue-related fields (`sent_count`, `scheduled_at`, `planned_recipients_count`, `recipients_calculated_at`) were copied from the original, causing `syncPlannedRecipients()` to skip adding new recipients.
  - Now all queue counters are properly reset to zero/null when duplicating a message.

### Added

- **Editor Features:**
  - Added new formatting options to the WYSIWYG editor:
    - **Font Family Picker:** specific font selection (Arial, Georgia, etc.).
    - **Font Size Picker:** custom text size support.
    - **Text Color & Highlight:** color pickers for text and background.
    - **Enhanced Emoji Picker:** new categorized emoji picker with tabs (Faces, Symbols, Gestures, etc.), properly positioned using Teleport to avoid clipping.

### Fixed

- **Email Preheader Bug:**

  - Fixed issue where preheader text from HTML template was used instead of the preheader field set by user in message form.
  - `SendEmailJob` now removes existing preheader from HTML content and injects the `Message->preheader` value after `<body>` tag.
  - User-defined preheader now takes priority over template preheader.

- **Test Email Placeholder and Preheader Support:**

  - Fixed issue where test emails did not substitute placeholders (e.g., `[[first_name]]`) with actual values.
  - Fixed issue where test emails did not include the preheader text.
  - `MessageController::test()` now uses `PlaceholderService` for variable substitution.
  - Test emails use real subscriber data from selected contact lists, or sample data ("Jan Kowalski") if no lists are selected.
  - Preheader is now injected into HTML content after `<body>` tag (same logic as `SendEmailJob`).
  - Updated frontend to send `preheader` and `contact_list_ids` in test email request.

- **Message Preview & Logic:**
  - Fixed `500 Internal Server Error` on preview endpoints caused by incorrect database queries (non-existent `user_id` column on subscribers table).
  - Fixed missing `scopeActive()` method in `Subscriber` model.
  - Fixed relationship usage in `MessageController` (changed `contactLists` to `contactList`).
  - Fixed `AdvancedEditor.vue` to correctly display live preview with data substitution.
  - Added missing translations for preview section in all supported languages.
  - **Subscriber CSV Import:**
    - Fixed issue with UTF-8 BOM causing first column (email) failure.
    - Added auto-detection for files without headers (if first row contains email).
    - Fixed validation bug preventing comma separator from being selected.
    - Updated import page instructions to clarify that files without headers are supported and auto-detected.
  - **Database Migrations:**
    - Fixed `2025_12_22_000003` migration compatibility with SQLite to allow running tests in `sqlite` environment.

### Improved

- **Message Editor UI:**
  - Enhanced Subscriber Picker for preview: added search functionality and optimized performance (limit 10 items).

### Added

- **Live Preview with Subscriber Data:**
  - Added new "Preview" sidebar widget in Message Editor.
  - Allows selecting a subscriber from the target audience to see how placeholders (e.g., `[[first_name]]`) will be rendered.
  - Updates the preview in real-time when switching subscribers.
  - Supports both subject line and content body substitution.

### Added

- **AI Subject Assistant - Preheader Generation:**

  - AI assistant now generates a preheader alongside each subject line suggestion.
  - Preheaders are generated without emojis (per user requirement).
  - Each suggestion in the dropdown now displays both subject (with emojis) and preheader (italic, below subject).
  - Clicking a suggestion auto-fills both the subject field and preheader field (if empty).
  - Updated `TemplateAiService::generateSubjectLine()` to return objects with `subject` and `preheader` fields.
  - Updated `SubjectAiAssistant.vue` to display preheaders and emit new `@select` event with both values.
  - Updated `Message/Create.vue` to handle new format and auto-populate preheader field.

- **AI Token Limits:**
  - Increased default token limit for AI text generation from 2000 to 8000 tokens to prevent truncated responses.
  - Improved handling of `max_tokens_small` setting from integration configuration.

### Improved

- **AI Assistant UI:**

  - Added auto-scrolling to generated content so users immediately see the result.
  - Fixed dark mode readability issues by adjusting text and background contrast in content preview.

- **AI Integration Settings:**

  - Fixed issue where `max_tokens_small` and `max_tokens_large` settings were not persisting after save.
  - Added proper validation for token fields in `AiIntegrationController`.

- **Subject AI Assistant Dropdown:**

  - Fixed issue where the suggestions dropdown was clipped or hidden by surrounding elements.
  - Implemented smart positioning (Teleport to body) to ensure the dropdown is always fully visible on top of other content.
  - Fixed issue where scrolling the suggestions list would close the dropdown.

- **WYSIWYG Editor:**
  - Fixed issue where clicking toolbar buttons (Bold, Italic, etc.) would unexpectedly save and close the message form.
  - Added proper button type attributes to prevent form submission on toolbar interactions.

## [1.0.12] – Short Description

**Release date:** 2025-12-22

### Fixed

- **Message Scheduling Timezone:**

  - Fixed a critical issue where scheduled messages were saved as UTC directly without accounting for user's timezone, causing a time shift in display.
  - Implemented correct timezone conversion: User Input (User TZ) -> Storage (UTC) -> Display (User TZ).
  - Ensures "What You See Is What You Get" for message scheduling regardless of user's timezone settings.

- **Message Statistics:**

  - Fixed issue where email opens and clicks were always displaying as zero in message statistics dashboard.
  - Implemented missing queries to `EmailOpen` and `EmailClick` in `MessageController::stats`.
  - Added recent activity feed for opens and clicks on the stats page.

- **Broadcast Snapshot Behavior:**
  - Fixed issue where new subscribers joining a list while a broadcast was sending (or paused) were automatically added to the queue.
  - Implemented "Snapshot" behavior for Broadcasts: once sending starts (sent_count > 0), the recipient list is locked.
  - Late-joining subscribers are excluded unless explicitly targeted via "Resend".

## [1.0.11] – Critical Queue Fixes & UX Improvements

**Release date:** 2025-12-22

### Added

- **Message Statistics Enhancements:**

  - Added "Recipients List" section to message statistics
  - detailed table showing every recipient with their status (queued, sent, failed, skipped)
  - Color-coded status badges and error messages for failed deliveries
  - Pagination for the recipient list

- **Message Preheader Display:**

  - Added display of message preheader in the message list view (under subject)
  - Added missing "Optional" label translation for preheader input field

- **Real-time Status Updates:**

  - Implemented dynamic status polling for message list
  - "Scheduled" messages now automatically update their status to "Sent" without page refresh
  - Optimized polling runs only when scheduled messages are present (every 15s)

- **Message Scheduling Display:**

  - Added `scheduled_at` field display in message list (below status badge for scheduled messages)
  - Added `scheduled_at` display in message statistics header
  - For "Send Immediately" broadcast messages, recipients are now synced to queue immediately for instant statistics access
  - Future scheduled messages wait for CRON to sync recipients when scheduled time arrives

- **Configurable Pagination:**
  - Added "Per Page" dropdown to message list (10, 30, 50, 100 items)
  - User preference is preserved via URL parameters
  - Default pagination increased from 12 to 30 items for better overview

### Changed

- **Message Controller:**

  - `stats()` method now returns paginated `queue_entries` with recipient data
  - `index()` method now accepts `per_page` parameter
  - Added `statuses()` endpoint for efficient batch status checking

- **Message Form Layout:**
  - Optimized "Scheduling" and "Timezone" sections to be side-by-side on large screens for better space utilization

### Fixed

- **Docker Queue Worker (Critical Fix):**

  - Fixed regression where `queue` container was starting `php-fpm` instead of the queue worker command.
  - Patched `docker/php/docker-entrypoint.sh` to correctly handle command-line arguments.
  - Updated `docker-compose.yml` to mount the patched entrypoint, ensuring the fix works without rebuilding images.
  - This resolves the issue where messages remained in "Queued" status indefinitely.

- **Database Migrations:**

  - Fixed `2025_12_22_000002_create_page_visits_table` migration to check if table exists before creating, preventing startup crashes on restart.

- **Version Check Cache Invalidation:**

  - Implemented smart cache invalidation for update checks
  - Automatically clears version cache when application version changes
  - Ensures users see correct update status immediately after upgrading

- **Dark Mode Visibility:**
  - Fixed invisible calendar icon in date picker inputs on dark backgrounds by enforcing dark color scheme
- **Dashboard - Missing Recent Campaigns:**
  - Fixed an issue in `GlobalStatsController` where "Recent Campaigns" were not loading due to incorrect relationship name (`lists` vs `contactLists`)
  - Dashboard now correctly displays the last 4 messages and their stats

## [1.0.10] – Docker Queue Worker & Email Improvements

**Release date:** 2025-12-22

> [!IMPORTANT] > **Breaking Change for Docker Users:**
> This release introduces `scheduler` and `queue` services in `docker-compose.yml` required for background tasks (sending emails, automation).
> If you are upgrading an existing installation, you MUST update your `docker-compose.yml` file manually or pull the latest version from the repository.
> Running `docker compose pull` is NOT enough if your local `docker-compose.yml` is missing these services.

### Added

- **Message List Recipient Count:**

  - Audience column now shows real recipient count (after exclusions and deduplication) alongside list name
  - Count is calculated live for draft/scheduled messages
  - For sent messages, uses frozen `planned_recipients_count` to preserve historical data

- **Resend Message Feature:**
  - New "Resend" button in message actions for broadcast messages
  - Smart recipient filtering: only sends to new subscribers who haven't received the message
  - Resets failed/skipped entries for retry
  - Shows confirmation modal with warning about skipping previous recipients

### Fixed

- **Docker Queue Worker (Critical):**

  - Added missing `queue` service to `docker-compose.yml` running `php artisan queue:work`
  - This fixes the issue where messages appeared as "sent" but were never actually delivered
  - Jobs were being dispatched to queue but no worker was processing them

- **Docker Background Tasks:**

  - Added missing `scheduler` service to `docker-compose.yml`
  - Fixed issue where "Cron not configured" warning appeared despite correct app configuration
  - Scheduled messages and automations now process correctly in Docker environment

- **Email Queue Processing:**
  - Added `channel` filter to `CronScheduleService::processQueue()` to prevent SMS messages from being incorrectly processed
  - Queue entries are now marked as `sent` only after successful delivery (moved to `SendEmailJob`)
  - Improved accuracy of `sent_count` statistics by incrementing on actual delivery

### Changed

- **SendEmailJob Refactored:**
  - Now accepts optional `queueEntryId` parameter for tracking
  - Handles `markAsSent()` and `markAsFailed()` after delivery attempt
  - Automatically marks broadcast messages as `sent` when all entries are processed

## [1.0.9] – Short Description - 2025-12-22

### Added

- **OpenRouter Free Models:**
  - Added support for free models in OpenRouter integration (Gemini 2.0 Flash, Phi-3, Llama 3 8B, Mistral 7B, OpenChat 7B, Mythomax L2)

### Fixed

- **Mailbox Connection UI:**
  - Fixed issue where error notifications (toasts) were obscured by the integration modal (z-index fix)
- **Gmail Integration:**
  - Fixed "silent failure" when saving Gmail mailbox caused by missing optional credentials handling in controller
- **Email Queue Processing:**
  - Fixed critical "head-of-line blocking" issue where restricted tasks (e.g., due to schedule) prevented valid backlog tasks from being processed.
  - Refactored `CronScheduleService::processQueue` to use chunk-based processing to bypass blocked items.

## [1.0.8] – Short Description - 2025-12-22

### Fixed

- **License Activation Buttons:**
  - Fixed issue where activation buttons were cut off in license plan cards due to incorrect layout height calculation
- **2FA Enforcement:**
  - Added middleware to enforce 2FA verification on protected routes
  - Added missing 2FA challenge routes and view
  - Fixed login flow to redirect enabled users to 2FA challenge
  - Added Polish/English translations for 2FA screens

### Added

- **UX Improvements:**

  - Added visual 2FA status indicator in Profile settings
  - Added 2FA lock icon in the top header when enabled

- **Automatic Version Check:**

  - New CRON task `netsendo:check-updates` running daily at 9:00 AM
  - Automatically checks GitHub for new releases and caches results for 6 hours
  - Shared cache with frontend checks ensures consistent update status

- **Password Reset with Smart Mail Fallback:**
  - New `SystemMailService` for sending system emails (password reset, notifications)
  - Intelligent fallback: uses ENV mail configuration if available, otherwise falls back to first active SMTP Mailbox with 'system' type
  - Custom `ResetPasswordNotification` with localized messages (PL, EN)
  - User model now uses `SystemMailService` for password reset emails

---

## [1.0.7] – Advanced Tracking, Triggers & Bug Fixes - 2025-12-22

### Added

- **Message Triggers Integration:**

  - Triggers tab in message editor is now fully functional (removed "Coming Soon" badge)
  - Trigger selection automatically creates `AutomationRule` behind the scenes
  - Supported trigger types: signup, anniversary, birthday, inactivity, page visit, custom (tag)
  - Each trigger has configuration options (e.g. `inactive_days`, `url_pattern`, `tag_id`)
  - Green dot indicator shows when trigger is active on a message

- **Email Read Time Tracking:**

  - Track how long subscribers read emails using `EmailReadSession` model
  - New endpoints: `/t/read-start`, `/t/heartbeat`, `/t/read-end`
  - Integration with automations via `read_time_threshold` trigger

- **Read Time Statistics on Stats Page:**

  - KPI cards: Average read time, Median, Total sessions, Max time
  - Read time distribution histogram chart (0-10s, 10-30s, 30-60s, 1-2min, 2min+)
  - Top readers table showing subscribers with longest read times
  - Data sourced from `EmailReadSession` model

- **Page Visit Tracking:**

  - Track subscriber visits to external pages with `PageVisit` model
  - JS Tracking Script generator for external sites (`/t/page-script/{user}`)
  - Visitor identification linking anonymous visitors to subscribers when they click email links
  - New `page_visited` automation trigger supporting URL patterns (wildcards)

- **Date-Based Automations:**

  - `DateTriggerService` for processing time-based triggers
  - New automation triggers: `date_reached`, `subscriber_birthday`, `subscription_anniversary`
  - Integrated with main scheduler (runs daily at 8:00 AM)

- **New Automation Triggers:**
  - `subscriber_inactive` - trigger when subscriber is inactive for X days
  - `specific_link_clicked` - trigger when specific URL is clicked
  - `read_time_threshold` - trigger when email is read for X seconds
  - `page_visited` - trigger on page visit matching URL pattern

### Changed

- `AutomationRule` model extended with `trigger_source` and `trigger_source_id` fields to track where automation was created from (message, funnel, or manual)
- `MessageController` now syncs message triggers with the automation system via `syncMessageTrigger()` method
- `MessageController@stats` now returns read time statistics, histogram, and top readers data
- **Automation Builder:**
  - Updated frontend interface to support configuration for new triggers
  - Added specific fields for URL patterns, dates, and time thresholds
- **Scheduler:**
  - Added `automations:process-date-triggers` command to `routes/console.php`

### Fixed

- **Message Scheduling Bug:**

  - Added `scheduled_at` to `$fillable` array in `Message` model - this was preventing "Send immediately" and scheduled messages from being processed by CRON
  - Added `scheduled_at` to `$casts` as datetime for proper type handling

- **Vue-i18n Parsing Errors (SyntaxError: Invalid linked format):**
  - Fixed unescaped `@` characters in email placeholder translations
  - In vue-i18n, `@` must be escaped as `{'@'}` to prevent parser confusion with linked messages syntax
  - Fixed files: `en.json` (4 occurrences), `es.json` (3), `de.json` (2), `pl.json` (1)

### Database

- New migration: `2025_12_22_000001_create_email_read_sessions_table`
- New migration: `2025_12_22_000002_create_page_visits_table`
- New migration: `2025_12_22_000003_add_new_triggers_to_automation_rules`
- New migration: `2025_12_22_000004_add_trigger_source_to_automation_rules`

---

## [1.0.6] – System Messages & Pages Separation - 2025-12-21

### Added

- **System Pages & System Emails Separation:**

  - Split `system_messages` table into `system_pages` (HTML pages) and `system_emails` (email templates)
  - New `SystemPage` model for HTML pages shown after subscriber actions (signup, activation, unsubscribe)
  - New `SystemEmail` model for email templates (8 templates total)
  - New `SystemPageController` and `SystemEmailController` with CRUD operations
  - Two separate navigation links: "Wiadomości Systemowe" and "Strony Systemowe"
  - Copy-on-write logic for list-specific customizations

- **8 System Email Templates:**

  - `activation_confirmation` - Sent after user confirms email
  - `data_edit_access` - Sent when user requests to edit profile
  - `new_subscriber_notification` - Sent to admin when new subscriber joins
  - `already_active_resubscribe` - Sent when active user tries to subscribe again
  - `inactive_resubscribe` - Sent when inactive user re-subscribes
  - `unsubscribe_request` - Confirmation request before unsubscribe
  - `unsubscribed_confirmation` - Sent after successful unsubscribe
  - `signup_confirmation` - Double opt-in email to confirm subscription

- **10 System Pages (HTML):**

  - Signup success/error pages
  - Email already exists (active/inactive variants)
  - Activation success/error pages
  - Unsubscribe success/error/confirm pages

- **Quick Email Toggle:**

  - Toggle switch in email list view to enable/disable emails per list
  - Global emails cannot be toggled (always active)
  - Toggling global email for specific list creates list-specific copy

- **Per-Subscriber Queue Tracking System:**

  - New `message_queue_entries` table for tracking send status per subscriber (planned/queued/sent/failed/skipped)
  - New `MessageQueueEntry` model with status management methods
  - `Message::syncPlannedRecipients()` dynamically adds new subscribers and marks unsubscribed ones
  - `Message::getQueueStats()` returns aggregated queue statistics
  - Queue progress section in Stats.vue with visual progress bar and status cards

- **Queue Message Active/Inactive Status:**

  - New `is_active` column for autoresponder/queue messages
  - Toggle button in message list actions to activate/deactivate queue messages
  - Queue messages display "Active"/"Inactive" status instead of "Sent"/"Scheduled"
  - Inactive queue messages are skipped by CRON processor

- **Message Statistics Improvements:**

  - New `sent_count` column to track actual sent messages
  - New `planned_recipients_count` column for planned recipient tracking
  - Stats page now shows actual sent count vs planned recipients for queue messages
  - New `queue_stats` object with planned/queued/sent/failed/skipped breakdown

- **Dashboard Clock Widget:**

  - Modern live clock showing current time in user's timezone
  - Gradient design (indigo → purple → pink) with glassmorphism
  - Displays timezone name and formatted date

- **Timezone-Aware Date Formatting:**
  - New `DateHelper` PHP class for centralized timezone-aware date formatting
  - New `useDateTime` Vue composable for frontend date formatting

### Changed

- **CRON Queue Processing Refactored:**

  - `CronScheduleService::processQueue()` now syncs recipients before processing
  - Processing now iterates over `MessageQueueEntry` records instead of messages
  - Each subscriber is tracked individually through planned → queued → sent stages
  - New subscribers added to lists are automatically included in next CRON run

- **Dashboard Layout:**

  - "License Active" and "Current Time" sections now share a single row (2 columns) to save vertical space
  - Improved responsive behavior for dashboard widgets

- **Default Content Language:**
  - All system emails and pages now have English default content
  - Users can customize content in any language per list

### Fixed

- **Timezone Display Issues:**

  - Dates now display in the user's configured timezone instead of server UTC
  - Affected controllers: MessageController, SubscriberController, ApiKeyController, SmsController, UserManagementController, MailingListController, SmsListController

- **CRON Queue Processing:**
  - Fixed "Send Now" not working - now properly sets `scheduled_at` for immediate dispatch
  - CRON now increments `sent_count` after successful message dispatch
  - CRON now marks broadcast messages as "sent" after dispatching
  - CRON skips inactive queue messages (`is_active = false`)

### Database

- New migration: `2025_12_21_210000_separate_system_pages_and_emails`

  - Renamed `system_messages` to `system_pages`
  - Added `access` column (public/private) to `system_pages`
  - Created `system_emails` table with slug, subject, content, is_active

- New migration: `2025_12_21_220000_update_system_emails_to_8_with_english`

  - Seeded 8 system email templates with English content
  - Updated 10 system pages with English content

- New migration: `2025_12_21_220000_create_message_queue_entries_table`

  - Table `message_queue_entries` with columns: `message_id`, `subscriber_id`, `status`, `planned_at`, `queued_at`, `sent_at`, `error_message`

- New migration: `2025_12_21_200000_add_queue_status_columns_to_messages`
  - Added `is_active`, `sent_count`, `planned_recipients_count`, `recipients_calculated_at`

### Translations

- Added system_pages translations (PL, EN, DE, ES):

  - Full CRUD labels, access levels, slug editing

- Added system_emails translations (PL, EN, DE, ES):

  - Full CRUD labels, toggle messages, placeholders info

- Added queue progress translations (PL, EN):

  - `messages.stats.queue.*` keys

- Added clock widget translations (PL, EN):
  - `dashboard.clock.*` keys

---

## [1.0.5] – User Management System - 2025-12-21

### Added

- **User Management System:**
  - **Team Invitations:** Admins can invite new team members via email
  - **Role Management:** Admin (owner) vs Team Member
  - **Granular Permissions:**
    - Per-list access control (View Only / View & Edit)
    - Team members only see lists explicitly shared with them
  - **New Interface:** `Settings > Users` for managing invitations and permissions
  - **Acceptance Flow:** Public page for invited users to set password

### Changed

- **Contact Lists:**
  - Lists now support `view` and `edit` permissions for team members
  - Sidebar navigation updated to correctly handle Settings sub-pages

### Database

- New `team_invitations` table
- New `contact_list_user` pivot table with permission field
- Updated `users` table with `admin_user_id` to link team members to owners

---

## [1.0.4] – Subscriber Exclusion & PHP 8.5 - 2025-12-21

### Added

- **Subscriber Exclusion Lists:**

  - New "Don't send to subscribers from lists" option in message Settings tab
  - Allows excluding specific contact lists from message recipients
  - Subscribers on excluded lists won't receive the message, even if on sending lists
  - New `excluded_contact_list_message` pivot table for storing exclusions

- **Email Deduplication:**
  - New `getUniqueRecipients()` method in Message model
  - Ensures each email address receives the message only once across multiple lists
  - Applies both exclusion filtering and deduplication by email

### Changed

- MessageController now handles `excluded_list_ids` in create, store, edit, update, and duplicate methods
- Message stats now use `getUniqueRecipients()` for accurate recipient counting

### Improved

- **Runtime Upgrade:**
  - PHP upgraded from 8.3 to **8.5** (new pipe operator, `array_first()`, `array_last()`)
  - Node.js upgraded from 20 to **25** (Current release)
  - Minimum PHP requirement raised to `^8.4` in composer.json

### Translations

- Added excluded lists translations (PL, EN, DE, ES):
  - `messages.fields.excluded_lists`
  - `messages.fields.excluded_lists_help`
  - `messages.fields.excluded_count`

---

## [1.0.3] – Dashboard Data & UX Improvements - 2025-12-21

### Improved

- **Dashboard - Real Data Integration:**

  - "Recent Campaigns" section now fetches the latest 4 campaigns from the database
  - Activity Chart shows real statistics from the last 7 days (emails sent, new subscribers, opens)
  - Removed demo/sample data from Dashboard components

- **Dashboard - UX Enhancements:**
  - Added empty states with clear CTAs when no data is available
  - Added skeleton loading states while dashboard data is being fetched

### Fixed

- **Dashboard Links:**
  - Changed "View all →" from hardcoded `/messages` to `route('messages.index')`
  - Quick Actions: all hardcoded paths replaced with dynamic `route()`:
    - `/messages/add` → `route('messages.create')`
    - `/subscribers/add` → `route('subscribers.create')`
    - `/subscribers/import` → `route('subscribers.import')`
    - `/forms/add` → `route('forms.create')`

### Backend

- Extended `getDashboardStats()` API in `GlobalStatsController` with:
  - `recent_campaigns` - 4 most recent campaigns from database
  - `activity_chart` - activity data from last 7 days

### Translations

- PL/EN: added keys for empty states:
  - `dashboard.recent_campaigns.empty_title`
  - `dashboard.recent_campaigns.empty_description`
  - `dashboard.recent_campaigns.create_first`
  - `dashboard.activity.empty_title`
  - `dashboard.activity.empty_description`

---

## [1.0.2] – Global Stats & Activity Logger - 2025-12-19

### Added

- Global Stats - monthly statistics with CSV export
- Activity Logger - activity log with automatic CRUD logging
- Tracked Links Dashboard - dashboard for tracked email links

---

## [1.0.1] – Licensing & Template Inserts - 2025-12-19

### Added

- Licensing system (SILVER/GOLD)
- Template Inserts (snippets and signatures)

---

## [1.0.0] – Initial Release - 2025-12-18

### Initial Release

- Full NetSendo migration to Laravel 11 + Vue.js 3 + Inertia.js
- Email Template Builder (MJML, Drag & Drop)
- AI Integrations (6 providers)
- Multi-provider email (SMTP, Gmail OAuth, SendGrid, Postmark, Mailgun)
- Subscription forms, email funnels
- Triggers and automations
- Public API
- Backup & Export
