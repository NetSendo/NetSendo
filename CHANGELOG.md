# Changelog

All notable changes to the NetSendo project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
