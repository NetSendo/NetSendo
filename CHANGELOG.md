# Changelog

All notable changes to the NetSendo project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
