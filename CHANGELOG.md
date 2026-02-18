# Changelog

All notable changes to the NetSendo project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

### Fixed

- **Brain Integration Fixes:**
  - Fixed `SyntaxError: Invalid linked format` in frontend by escaping `@BotFather` references (`{'@'}BotFather`) in all locale files (PL, EN, DE, ES). This prevents Vue I18n from interpreting `@` as a linked message token.
  - Fixed `SQLSTATE[42S01]: Table 'ai_brain_settings' already exists` error during migration by adding `Schema::hasTable()` check to `2026_02_18_120000_create_ai_brain_settings_table.php`.

### Changed

## [2.0.0] – Short Description

**Release date:** 2026-02-18

### Added

- **NetSendo Brain — Dashboard Status Widget:**
  - **Status Widget:** Added a new widget to the main Dashboard right sidebar displaying Brain's status, work mode, and knowledge base stats.
  - **Quick Chat:** Integrated quick chat input directly in the dashboard widget for asking questions to Brain without navigating away.
  - **Real-time Status:** Widget fetches live data via new `/brain/api/status` endpoint (work mode, knowledge count, telegram connection).
  - **Localization:** Full translations for the widget in PL, EN, DE, ES.

- **Telegram Marketplace Integration:**
  - **Marketplace Entry:** Added Telegram to the Marketplace active integrations list with a dedicated detail page.
  - **Integration Flow:** Users can now discover and connect Telegram from the Marketplace, redirecting to Brain settings for the handshake.
  - **Status Indicators:** Active/Inactive badges and connection details now visible in Marketplace.

### Fixed

- **Brain Settings - 401 Unauthorized:**
  - Fixed 401 errors when saving settings or generating Telegram codes in `Settings.vue`.
  - **Root Cause:** Frontend was using session cookies while API routes (`/api/v1/brain/*`) required Bearer token (`api.key` middleware).
  - **Fix:** Implemented duplicate session-authenticated web routes (`/brain/api/*`) for internal frontend usage, mirroring the API capabilities but using `web` middleware group.

### Added

- **NetSendo Brain — AI Marketing Assistant (Phase 1):**
  - **Multi-Agent Architecture:** Introduced a central AI orchestrator ("NetSendo Brain") with three specialist agents (Campaign, List, Message) that can plan, execute, and advise on marketing tasks autonomously.
  - **Three Work Modes:**
    - **Autonomous:** AI plans and executes actions automatically, reports after completion.
    - **Semi-Auto:** AI proposes an action plan with steps, waits for user approval before executing.
    - **Manual:** AI analyzes and advises; user performs all actions manually.
  - **Brain Core Services:**
    - `AgentOrchestrator` — Central brain that classifies user intent via AI (with keyword fallback), routes to specialist agents, manages execution flow, tracks token usage.
    - `ConversationManager` — Multi-channel conversation management (web, Telegram, API) with system prompt building and AI payload construction.
    - `ModeController` — Work mode management with critical action detection (e.g., `delete_all_subscribers` always requires approval) and cross-channel approval flow with 24h expiration.
    - `KnowledgeBaseService` — Bidirectional knowledge engine: users add entries manually, AI auto-enriches from conversations. Category-based context injection for AI prompts with full-text search.
  - **Specialist Agents:**
    - `CampaignAgent` — Plans campaign strategies, selects audiences, generates content, creates messages, integrates with existing `CampaignArchitectService`.
    - `ListAgent` — Creates lists, cleans bounced subscribers, tags contacts, shows statistics with context-aware AI planning.
    - `MessageAgent` — Generates email/SMS content, subject line variants, A/B test variants, improves existing content using knowledge base context.
  - **Telegram Bot Integration:**
    - Full Telegram bot with webhook processing and bot commands (`/start`, `/connect`, `/disconnect`, `/mode`, `/status`, `/help`, `/knowledge`).
    - Account linking via 8-character codes generated in the web panel.
    - Inline keyboard approval buttons for semi-auto mode action plans.
    - Bot token stored per-user in database (self-hosted pattern) with connection test endpoint.
  - **Brain API (15 endpoints):**
    - `POST /api/v1/brain/chat` — Send a message to the Brain.
    - `GET /api/v1/brain/conversations` — List conversation history.
    - `GET /api/v1/brain/conversations/{id}` — Get conversation with messages.
    - `GET/POST/PUT/DELETE /api/v1/brain/knowledge` — Full CRUD for knowledge base entries.
    - `GET /api/v1/brain/plans` — List action plans with status filtering.
    - `POST /api/v1/brain/plans/{id}/approve` — Approve or reject an action plan.
    - `GET/PUT /api/v1/brain/settings` — Get and update Brain settings.
    - `POST /api/v1/brain/telegram/link-code` — Generate Telegram link code.
    - `POST /api/v1/brain/telegram/test` — Test Telegram bot connection.
  - **Database:** 8 new tables (`ai_brain_settings`, `ai_conversations`, `ai_conversation_messages`, `knowledge_entries`, `ai_action_plans`, `ai_action_plan_steps`, `ai_pending_approvals`, `ai_execution_logs`) with full migration support.
  - **Models:** 8 new Eloquent models with relationships, scopes, and helper methods.
  - **Configuration:** Added `telegram` service config to `services.php`.
- **NetSendo Brain — Phase 2: Extended Agents:**
  - **CRM Agent (`CrmAgent`):** Full CRM management via AI — search/create contacts, update lead status, create deals in pipeline, move deal stages, create tasks with scheduling, lead score analysis, pipeline summary with deal values, company creation.
  - **Analytics Agent (`AnalyticsAgent`):** Read-only analytics engine — campaign stats (open rate, CTOR), subscriber growth/churn, trend analysis (week-over-week), campaign comparison, AI usage reports, AI-generated insight reports.
  - **Segmentation Agent (`SegmentationAgent`):** Tag distribution analysis, score-based segmentation (cold/warm/hot/super hot), AI-powered segmentation recommendations, tag creation and bulk assignment, automation performance stats.
  - **Agent Orchestrator Update:** 6 registered agents (campaign, list, message, crm, analytics, segmentation). Updated intent classification with CRM/analytics/segmentation keywords.

- **NetSendo Brain — Phase 3.1: Frontend Chat UI:**
  - **Brain Chat Page (`Brain/Index.vue`):** Full-featured AI chat interface with conversation list sidebar, message bubbles (user/AI), typing indicator, action plan preview panel, welcome screen with suggestions, and real-time message sending via Brain API.
  - **Brain Settings Page (`Brain/Settings.vue`):** Configuration UI with work mode selector (autonomous/semi-auto/manual), Telegram integration panel (connect/test/disconnect), and knowledge base management (add/edit/delete/toggle entries).
  - **Sidebar Integration:** Added "NetSendo Brain" group to the main navigation sidebar with "AI" badge, positioned after the CRM group. Updated `updateOpenGroup()` to recognize `brain.*` routes.
  - **Backend Routing:** New `BrainPageController` with Inertia rendering for `/brain` (chat) and `/brain/settings` pages. Routes registered as `brain.index` and `brain.settings`.
  - **Translations:** Added `brain.*` translation keys (~65 keys) to all 4 locale files (PL, EN, DE, ES) covering chat UI, settings, work modes, Telegram integration, and knowledge base management. Added `navigation.groups.brain` key.

- **Subscriber Search Enhancements:**
  - **Search by Subscriber ID:** Backend search in `SubscriberController` now also matches subscriber ID when the search term is numeric. Typing `123` will find subscriber #123 in addition to matching email/name/phone.
  - **Searchable List Filter:** Replaced the simple `<select>` dropdown in the subscriber list page with a searchable dropdown. Users can now filter lists by typing a name or ID, with `#id` badges displayed next to each list name.
  - **Localization:** Added `subscribers.search_list_placeholder` translation key in PL, EN, DE, ES.

### Fixed

- **Brain Agent Heredoc Syntax Error:**
  - Fixed `{json_encode()}` calls inside PHP heredoc strings across all 6 specialist agents (`CampaignAgent`, `ListAgent`, `MessageAgent`, `CrmAgent`, `AnalyticsAgent`, `SegmentationAgent`). PHP does not support function calls inside heredocs — extracted to local variables (`$intentDesc`, `$paramsJson`) before the heredoc block.

- **Docker Zombie Process Accumulation:**
  - Added `init: true` to all PHP containers (`app`, `scheduler`, `queue`, `reverb`) in both `docker-compose.yml` and `docker-compose.dev.yml`.
  - Fixes zombie processes accumulating in the `scheduler` container (~5760/day) caused by `runInBackground()` tasks forking child PHP processes every minute without an init process (PID 1) to reap them.
  - Docker now injects Tini as PID 1, which properly calls `wait()` on terminated child processes.

### Changed

- **Queue Diagnostic Logging:**
  - Added `Log::debug()` calls to `CronScheduleService::processQueue()` for two previously silent skip conditions: list schedule disallowed and list volume limit reached. Logs include entry ID, list ID, message ID, and relevant context to help diagnose why entries remain in `planned` status.

## [1.9.3] – Short Description

**Release date:** 2026-02-15

### Added

- **Multi-Language Campaign System:**
  - **Database:** Added `language` column to `subscribers` table. Created `message_translations` table for storing per-language message content (subject, preheader, content).
  - **Subscriber Language Preference:** Subscribers can now have a preferred language set via Create/Edit forms, public preferences page, and CSV import.
  - **Message Translations UI:** Full translation management in Message Create with language tabs, add/remove translations, copy from default, and per-language subject/preheader/content editing.
  - **Language-Aware Sending:** `SendEmailJob` resolves language-specific content before sending, with fallback to default language.
  - **Subscriber List:** Added sortable "Language" column to the subscriber table (hidden by default) with globe badge display.
  - **CSV Import:** Added `language` field to import column mapping with auto-detection for headers (`language`, `lang`, `język`, `locale`).
  - **Public Preferences:** Added language selector to the public subscriber preferences page (`preferences.blade.php`).
  - **API:** Updated `SubscriberResource` to include the `language` field.
  - **i18n:** Replaced hardcoded Polish labels with `$t()` calls in Create, Edit, and Overview forms.
  - **Localization:** Full translations for all language features in PL, EN, DE, ES.

- **CRM Auto-Convert Setting:**
  - Added user-configurable toggle "Auto-convert warm contacts" in **CRM → Scoring Rules** settings.
  - When enabled (default), subscribers from mailing lists are automatically converted to CRM contacts when automation scoring or deal creation rules are triggered.
  - When disabled, scoring and deal actions will skip auto-creation — contacts must be added to CRM explicitly.
  - Setting is stored in `User.settings` JSON column under `crm.auto_convert_contacts`.
  - Backend: Modified `addScore()` and `createCrmDeal()` in `AutomationActionExecutor.php` to respect the setting.
  - Frontend: Toggle card with icon and description in `ScoringRules.vue`.
  - Route: `POST /crm/scoring/toggle-auto-convert` handled by `LeadScoringController::toggleAutoConvert()`.
  - Localization: Full translations in PL, EN, DE, ES.

- **Timezone-Aware Email Sending:**
  - **Database:** Added `timezone` column to `subscribers` table. Added `send_in_subscriber_timezone` boolean column to `messages` table.
  - **Subscriber Timezone:** Subscribers can now have an individual timezone. Added `getEffectiveTimezone()` helper on `Subscriber` model with configurable fallback.
  - **Per-Subscriber Scheduling:** When "Send in subscriber's timezone" is enabled, autoresponder `time_of_day` and broadcast `scheduled_at` are interpreted in each subscriber's local timezone, with per-subscriber UTC conversion in `CronScheduleService`.
  - **Fallback Logic:** Subscribers without a timezone fall back to the message's effective timezone hierarchy (Message → List → Group → User Account).
  - **Frontend:** Added checkbox in Message Create/Edit (Settings tab, below timezone selector), conditionally visible when `time_of_day` is set or scheduled send is selected.
  - **Controller:** Updated `MessageController` store/update validation and edit data exposure for the new flag.
  - **Localization:** Full translations in PL, EN, DE, ES.
  - **Tests:** Added `CronScheduleServiceTimezoneTest` with 6 test cases covering per-subscriber timezone gating, UTC fallback, and helper method behavior.

### Fixed

- **Message Statistics:**
  - **Queue Stats Display:** Fixed an issue where message statistics (sent, planned, failed) for autoresponder messages were showing as zero in the main list view.
  - **Detailed Status:** Added detailed breakdown of queue status (Sent, Planned, Queued, Failed, Skipped) directly in the message list for autoresponders.
  - **Localization:** Added missing translation keys for queue statistics in PL, EN, DE, ES.

### Changed

## [1.9.2] – Short Description

**Release date:** 2026-02-07

### Added

- **Tpay Integration (Polish Payments):**
  - **Full Gateway Integration:** Implemented complete support for Tpay payment processor including bank transfers, BLIK, and credit cards.
  - **Marketplace Page:** Added dedicated Tpay integration page (`/marketplace/tpay`) with features overview and setup guide.
  - **Product Management:** New "Tpay Products" section (`/settings/tpay-products`) for creating and managing payment links.
  - **Sales Funnels:** Tpay products fully integrated into Sales Funnels system (post-purchase actions, tagging, list subscription).
  - **Secure Checkout:** Redirect-based checkout flow with automatic return handling and status verification.
  - **Webhook System:** Robust webhook handling with JWS signature verification for real-time transaction updates.
  - **Configuration:** Settings page for API credentials (Client ID, Secret, Security Code) with Sandbox mode support.
  - **Localization:** Complete translations for all Tpay interfaces in PL, EN, DE, ES.

### Fixed

- **Deliverability - SPF Configuration:**
  - Fixed incorrect hardcoded `include:netsendo` SPF mechanism by replacing it with dynamic installation domain (e.g., `include:_spf.yourdomain.com`).
  - Fixed SPF record generation to correctly aggregate providers from all mailboxes sharing the same domain (e.g., merging SendGrid and NMI includes).
  - Fixed NMI provider configuration to use correct dynamic SPF include path based on the application URL.

### Changed

## [1.9.1] – Short Description

**Release date:** 2026-02-04

### Changed

- **CardIntel Mobile Optimization:**
  - **Responsive Layouts:** Complete mobile optimization for Dashboard, Queue, Memory, Settings, and Show pages.
  - **Queue Card View:** Replaced large data table with a touch-friendly Card List view on mobile devices for better readability.
  - **Navigation:** Implemented scrollable tab navigation and stacked headers for improved mobile usability.
  - **Adaptive Grids:** Dashboard statistics and settings forms now automatically adjust from multi-column to single-column layouts on smaller screens.

- **Message List Sorting:**
  - Changed default sorting logic for Email and SMS lists.
  - Broadcasts (sent/scheduled) are now sorted by effective sending date (`scheduled_at` or `sent_at`) instead of creation date.
  - Drafts and Autoresponders continue to be sorted by creation date.
- **Message List UI:**
  - Added display of sending date under "Sent" status for Email and SMS lists.
  - Added display of scheduled date under "Scheduled" status for SMS lists.

### Fixed

- **Campaign Architect:**
  - Fixed a critical bug where revenue projections were massively inflated due to a hardcoded fallback of 1000 subscribers instead of using the actual audience size. This caused forecasts like $350,000 when sending to only 11 people.
- **Message Machine:**
  - Fixed an issue where duplicatiing a message did not copy the tracked links configuration (`MessageTrackedLink` records), causing the copy to lose all link tracking settings.
- **CardIntel Agent:**
  - Fixed 422 upload error when uploading photos from mobile devices (camera or gallery). Extended MIME type validation to accept `image/jpg` and `application/octet-stream` with extension-based fallback verification for iOS/Android compatibility.

## [1.9.0] – Short Description

**Release date:** 2026-02-03

### Added

- **NMI (NetSendo Mail Infrastructure) - Complete Feature Set:**
  - **Core Backend:**
    - Created `IpPool` and `DedicatedIpAddress` models for managing sending infrastructure (Shared/Dedicated).
    - Implemented `IpWarmingService` with intelligent 28-day warming schedule and automated daily limit enforcement.
    - Implemented `DkimKeyManager` for generating 2048-bit RSA keys, managing selectors, and validating DNS records.
    - Implemented `BlacklistMonitorService` for real-time IP reputation checking against major DNSBLs (Zen, SpamCop, etc.).
    - Updated `DomainConfiguration` to support direct assignment of dedicated IPs.
  - **Frontend - Management Dashboard:**
    - **Dashboard:** New NMI Dashboard (`/settings/nmi`) with real-time health metrics, IP pool management, and dedicated IP inventory.
    - **IP Detail View:** Comprehensive IP management page (`/settings/nmi/ips/{ip}`) displaying warming progress, daily limits, and sending stats.
    - **Warming Controls:** UI controls for initiating/pausing IP warming and visualizing progress/schedule.
    - **DKIM Management:** Built-in tools for generating, rotating, and verifying DKIM keys with "One-Click Copy" DNS records.
    - **Blacklist Monitor:** On-demand and scheduled blacklist status checking with visual status indicators.
  - **Infrastructure (Docker/Haraka):**
    - Integrated **Haraka MTA** (Mail Transfer Agent) as a dedicated container (`nmi-mta`) for high-performance email delivery.
    - Added NMI service to `docker-compose.yml` (Production) and `docker-compose.dev.yml` (Development).
    - Configured persistent volumes for NMI queues, logs, DKIM keys, and TLS certificates.
  - **Localization:**
    - Complete, native-quality translations for all NMI interfaces in **Polish, English, German, and Spanish**.

### Fixed

- **NMI Localization:**
  - Fixed missing translations for NMI Pool Detail page in all supported languages.
  - Resolved `dedicated_ips` key conflict in Polish locale.

### Documentation

- **NMI Documentation:**
  - Added `docs/NMI.md` with complete user guide, configuration reference, and migration instructions.
  - Updated `.env.example` with all NMI environment variables.
  - Added NMI feature mention in `README.md`.

## [1.8.8] – Short Description

**Release date:** 2026-02-02

### Added

- **Deliverability Shield Generators:**
  - **DMARC One-Click Generator:** Implemented a new tool to automatically generate optimal DMARC records with configurable policies (None/Quarantine/Reject) and reporting addresses.
  - **SPF Auto-Fix:** Implemented an intelligent SPF generator that detects email providers, validates lookup limits, and suggests optimized records with "One-Click Fix" capability.
  - **Smart Upgrade Paths:** Added logic to guide users safely from "Quarantine" to "Reject" policies for DMARC.
  - **Localization:** Added comprehensive translations for all generator features in PL, EN, DE, ES.

- **System Messages Improvements:**
  - **Fixed Ordering:** Implemented a fixed, logical order for system emails (Signup → Activation → Welcome → Resubscribe → Management → Unsubscribe → Admin) to ensure consistency across all lists.
  - **UI Descriptions:** Added descriptive helper text for each system email in both desktop (table view) and mobile (card view) interfaces.
  - **Localization:** Added full descriptions for all 12 system emails in PL, EN, DE, ES.

- **System Pages Improvements:**
  - **Fixed Ordering:** Implemented a fixed, logical order for system pages (Signup → Activation → Unsubscribe) to ensure consistency across all lists.
  - **UI Descriptions:** Added descriptive helper text for each system page in the table view.
  - **Localization:** Added full descriptions for all 8 system pages in PL, EN, DE, ES.

- **Deliverability Shield:**
  - Implemented **Provider-Aware DNS Verification** for improved accuracy.
  - Added specific SPF checks for **SendGrid** (`sendgrid.net`) and **Gmail** (`_spf.google.com`).
  - Added support for checking multiple DKIM selectors based on the provider (e.g., `s1`, `s2`, `sendgrid` for SendGrid).
  - Added detailed error messages identifying exactly which required includes or selectors are missing.
  - Added translations for provider-specific DNS issues in PL, EN, DE, ES.

- **CardIntel Agent:**
  - Added **Desktop Camera Support** for MacBooks and USB webcams using MediaDevices API.
  - Added automatic camera detection to dynamically show/hide the "Take Photo" button based on hardware availability.
  - Added support for **HEIC/HEIF** business card images from mobile devices.

### Fixed

- **CardIntel Agent:**
  - Fixed photo upload failure on mobile devices by switching from extension-based to proper MIME type validation.
  - Fixed missing file extensions for mobile camera photos by implementing a MIME-based fallback.
  - Added missing `image/heif` MIME type to the storage service allowed list.

- **Translation System:**
  - Fixed a critical issue where frontend translations (Vue i18n) for Deliverability features were missing from JSON locale files, causing raw keys to be displayed.
  - Synchronized translation keys between backend (PHP) and frontend (JSON) files for PL, EN, DE, and ES.

- **Subscriber Management:**
  - Fixed `Call to a member function contains() on null` error by adding safe null checks for `tags` relationship in `addTag`, `removeTag`, and `syncTagsWithEvents` methods.

- **Message Statistics:**
  - Fixed "Select All" functionality in "Recent Opens" and "Recent Clicks" sections to correctly add all visible items to selection instead of toggling them off when already selected.
  - Improved "Select All" to fetch ALL subscriber IDs matching the current filter (across all pages), not just the current page.
  - Added total counts display in section headers for "Recent Opens" and "Recent Clicks" (e.g., "Ostatnie Otwarcia (125)").
  - Added loading state feedback to "Select All" buttons during AJAX requests.

- **System Message Preview:**
  - Fixed link navigation in system message preview by allowing popups and top navigation in the iframe sandbox.

- **Automation System:**
  - Fixed `Data truncated` error by adding missing `status_changed` value to `crm_activities` type ENUM.
  - Fixed `add_tag` action failure by adding support for `tag` key in configuration payload.

- **CRM Lead Scoring:**
  - Fixed `TypeError` in `LeadScoreHistory` when logging score updates for contacts with null initial score.

## [1.8.7] – Short Description

**Release date:** 2026-02-02

### Added

- **DMARC Wizard:**
  - Added "Copy to clipboard" buttons for Host and Target fields in the DNS setup step for better UX.

- **Deliverability Shield:**
  - Added localhost environment detection for Domain Verification.
  - Added warning banners in DmarcWiz and DomainStatus when running on localhost (DNS verification disabled).

- **InboxPassport AI - Message Editor Integration:**
  - Added "Check Deliverability" button to the message editor (next to Test button).
  - Quick content analysis available directly while composing messages.
  - Displays Inbox Score, Predicted Folder (Inbox/Promotions/Spam), issues, and recommendations in a modal.
  - Works in two modes: full simulation (with verified domain) and content-only analysis (without domain).
  - Full translations in PL, EN, DE, ES.

- **Statistics Bulk Actions:**
  - **Bulk Selection:** Added checkboxes to "Recent Opens" and "Recent Clicks" tables on the message statistics page, allowing multi-selection of subscribers.
  - **Bulk Action Bar:** Implemented a contextual action bar that appears when items are selected, offering "Add to List" and "Deselect All" options.
  - **Bulk Add to List:** Added `BulkAddToListModal` component for adding multiple selected subscribers to a mailing list at once.
  - **Localization:** Full translations for bulk operations in PL, EN, DE, ES.

- **CardIntel Personalization:**
  - **Message Personalization:** Added options for Formality (Formal/Informal) and Gender (Auto/Male/Female) to customize message generation.
  - **Smart Gender Detection:** Implemented automatic gender detection from Polish first names for correct grammatical inflection.
  - **Enhanced AI Prompts:** Updated decision engine to generate grammatically correct Polish forms based on selected gender and formality.
  - **Add to List:** Added full support for adding scanned contacts directly to mailing lists via a new modal.
  - **Localization:** Added translations for all new personalization UI elements in PL, EN, DE.

### Fixed

- **CardIntel Actions:**
  - Fixed "Send Email" button not functioning (missing event handler).
  - Fixed "Add to List" button not functioning and implemented missing list selection modal.

- **DMARC Verification:**
  - Fixed CNAME verification target to dynamically use the installation's domain (from `APP_URL`) instead of a hardcoded value, ensuring correct verification for white-label installations.

- **Domain Management:**
  - Fixed `UniqueConstraintViolationException` when re-adding a previously deleted domain by changing soft deletes to force deletes for `DomainConfiguration`.
  - Fixed domain validation logic to normalize domains to lowercase before uniqueness check.

### Changed

- **DMARC Wizard:**
  - Simplified domain addition flow to a single step (removed redundant preview step).
  - Improved "Continue" button label to "Add Domain" for clarity.

## [1.8.6] – Short Description

**Release date:** 2026-02-02

### Added

- **Deliverability Shield:**
  - **Dashboard:** New comprehensive dashboard (`/deliverability`) for monitoring domain health and inbox placement.
  - **DMARC Wiz:** Step-by-step wizard for adding and verifying sending domains with automatic CNAME generation.
  - **InboxPassport AI:** AI-powered simulation tool that predicts inbox placement (Primary/Promotions/Spam) before sending.
    - Analyzes content for spam triggers, link reputation, and HTML/formatting issues.
    - Provides detailed recommendations and confidence scores.
  - **Domain Monitoring:** Continuous checking of DNS records (SPF, DKIM, DMARC) with historical tracking.
  - **Alerts System:** Automated notifications for critical deliverability issues (e.g., DMARC policy changes, DNS failures).
  - **Scheduler Integration:** New cron jobs (`deliverability:check-domains`, `deliverability:upgrade-dmarc`) for background monitoring.
  - **Sidebar Integration:** Added "Deliverability Shield" link to Settings group (visible to all users).
  - **License Integration:** GOLD users get full access; SILVER users see feature preview and upgrade options.
  - **Localization:** Full translations for all features in PL, EN, DE.

### Fixed

### Changed

- **CardIntel Token Limits:**
  - Increased token limits for AI operations to prevent truncation on large inputs:
    - OCR Extraction: 1500 -> 4000 tokens
    - Message Generation: 800 -> 4000 tokens
    - Website Summary: 300 -> 1000 tokens
    - AI Analysis: 200 -> 800 tokens

## [1.8.5] – Short Description

**Release date:** 2026-02-02

### Added

- **CardIntel File Upload:**
  - **Multi-file Support:** Added ability to upload multiple business cards at once with batch processing.
  - **Upload Mode Selection:** Users can choose between "Single File" (immediate processing) and "Multiple Files" (queue batch processing) modes.
  - **Drag & Drop:** Implemented drag-and-drop file upload for better UX.
  - **File Management:** Added file list with individual file removal and "Clear All" functionality for batch mode.
  - **Progress Tracking:** Visual upload progress bar with percentage indicator.
  - **User Instructions:** Added helpful tips section explaining both upload modes and supported formats.
  - **Localization:** Full translations for file upload functionality in PL, EN, DE, ES.

- **CardIntel Enhancements:**
  - **HTML Formatting:** Added ability for AI to generate HTML formatted emails with configurable allowed tags (`allowed_html_tags`).
  - **Structure:** AI messages now include a subject and preheader for better inbox presentation.
  - **Email Sending:** Implemented full email sending flow integrated with CRM.
    - Automatically creates or links a CRM Contact for the recipient.
    - Logs the "Email Sent" activity in the contact's history.
    - Uses the configured Default Mailbox or system default.
  - **Custom AI Prompt:** Added setting to provide custom instructions to the AI generation engine.
  - **Localization:** Full translations for new settings and features in PL, EN, DE, ES.

- **System Messages & Pages UX:**
  - **Context Label:** Added "Show for:" label above the list selector to clarify context switching.
  - **Helper Text:** Added hint text "Select a list to edit only for that list" to the dropdown trigger.
  - **Info Box:** Added informational box explaining the hierarchy between global defaults and list-specific templates.
  - **Consistency:** Applied consistent UX pattern to both "System Emails" and "System Pages" sections.
  - **Localization:** Added new UX translation keys to PL, EN, DE, ES.

### Fixed

- **CardIntel Controller:**
  - Fixed access to protected property `$decisionEngine` by implementing a public `getRecommendations` accessor in `CardIntelService`.

- **CardIntel Mode Switching:**
  - Fixed non-functional mode selection buttons (Manual, Agent, Auto) in the Scan tab.
  - Mode changes now persist to backend settings immediately via AJAX.
  - Header badge updates in real-time when switching modes (no page refresh needed).
  - Added mode descriptions under each button for better UX clarity.
  - UI now consistent with the Settings tab mode selection pattern.

- **CardIntel Settings JSON Response:**
  - Fixed Inertia JSON error when saving settings (was returning plain JSON instead of redirect).
  - Backend now correctly distinguishes between pure AJAX and Inertia requests.

- **CardIntel Translations:**
  - Fixed missing Polish translations for CRM sync mode descriptions.
  - Added `navigation.api_keys` translation to all languages (PL, EN, DE, ES).

### Changed

## [1.8.4] – Short Description

**Release date:** 2026-02-01

### Added

- **Campaign Auditor - User Currency Support:**
  - Revenue loss amounts are now displayed in the user's preferred currency (from Profile settings) instead of hardcoded USD.
  - Added automatic currency conversion using NBP exchange rates via `CurrencyExchangeService`.
  - If no currency is set in user profile, defaults to USD for backward compatibility.

- **Copy List Feature:**
  - **Email Lists:** Added ability to copy mailing lists with customizable options.
  - **SMS Lists:** Added ability to copy SMS lists with customizable options.
  - **Copy Options:**
    - Copy subscribers (optional)
    - Copy system messages and pages (email lists only)
    - Set custom name for the new list
    - Set visibility (public/private) for the new list
  - **Preserved Data:** Group assignment, tags, and list settings are always copied.
  - **UI:** New `CopyListModal.vue` components for both list types, integrated into grid and table views.
  - **Backend:** New `copy` method in `MailingListController` and `SmsListController` with dedicated routes.
  - **Localization:** Full translations in PL, EN, DE, ES.

- **CardIntel AI Vision Integration:**
  - **Vision Providers:** Added `supportsVision()` and `generateWithImage()` methods to `OpenAiProvider` and `GeminiProvider` for image-based AI analysis.
  - **Settings Status:** CardIntel settings page now displays AI Vision provider status with visual indicators (green/red) showing which providers are configured and active.
  - **Provider Detection:** Backend automatically detects vision-capable providers (OpenAI GPT-4o, Google Gemini) and their integration status.
  - **UI Feedback:** Added direct link to AI settings when no vision provider is configured.
  - **Localization:** Full CardIntel translations added for Spanish (ES) and German (DE), including all dashboard, settings, queue, memory, and vision status strings.

### Fixed

### Changed

## [1.8.3] – Short Description

**Release date:** 2026-02-01

### Added

- **Message Statistics - Add to List:**
  - Added "Actions" column to "Recent Opens" and "Recent Clicks" tables on the message statistics page.
  - New `AddToListDropdown` component allowing quick subscriber addition to mailing lists directly from statistics view.
  - Dropdown features searchable list selection and success/error feedback.
  - Backend extended to support URL search in "Recent Clicks" table and include `subscriber_id` in response.
  - Full translations in PL, EN, DE, ES.

### Fixed

### Changed

## [1.8.2] – Short Description

**Release date:** 2026-01-31

### Added

- **CRM Lead Quick Action:**
  - **Quick Add to CRM:** Added "Add to CRM" quick action button in the subscribers list (`subscribers.index`).
  - **Status Indicator:** Implemented visual feedback using a green icon for subscribers who are already CRM contacts.
  - **Duplicate Prevention:** Clicking the action for an existing contact shows a warning toast instead of creating a duplicate.
  - **Backend Logic:** Added `addToCrm` endpoint in `SubscriberController` to create a new CRM lead from subscriber data.
  - **UX:** Added toast notifications for successful addition or error states.
  - **Localization:** Full translations for the new action in PL and EN.

- **CRM Contact Selection for Email:**
  - **Direct CRM Targeting:** Added ability to select individual CRM contacts as recipients for broadcast messages, independent of email lists.
  - **Exclusions:** Implemented ability to specifically exclude individual CRM contacts from a campaign.
  - **CRM Contact Selector:** New Vue component with real-time search, status badges, and multi-selection support.
  - **Backend Integration:** Updated `MessageController` to handle `crm_contact_ids` and `excluded_crm_contact_ids`, including proper unique recipient calculation.
  - **API:** Added `searchCrmContacts` endpoint with support for searching by email, name, phone, and company.
  - **Localization:** Full translations for all CRM contact selection features in PL, EN, DE, ES.

### Fixed

- **CRM Contact Integration:**
  - **Search Fix:** Fixed 500 error in `searchCrmContacts` by correctly joining `subscribers` table to search by email/name/phone.
  - **UI Styling:** Fixed dark mode visibility issues in CRM contact selector badges.
  - **Validation:** Updated message validation to allow sending broadcasts with only CRM contacts (no list required).

### Fixed

- **Default Automations Not Loading on Production:**
  - Fixed issue where default AutoTag Pro automations were not automatically seeded on existing installations.
  - Added `php artisan automations:seed-defaults` to `docker-entrypoint.sh` startup script, ensuring default automations are seeded for all users on every container restart.
  - Added `DefaultAutomationsSeeder` to `DatabaseSeeder` for new installations via `php artisan db:seed`.
  - The seeder is idempotent (safe to run multiple times) - it skips users who already have system automations.

- **Lead Scoring Not Working (Score = 0 for all contacts):**
  - Fixed issue where Lead Scoring rules were only seeded when a user visited the Lead Scoring settings page, causing all contacts to have a score of 0.
  - Added auto-seeding of default Lead Scoring rules in `User` model when new admin users are created.
  - Created `php artisan netsendo:seed-lead-scoring-rules` command to seed rules for existing users without any rules.
  - Added Lead Scoring rules seeding to `docker-entrypoint.sh` startup script, ensuring all containers automatically seed rules on startup.
  - The seeder is idempotent (safe to run multiple times) - it only creates rules for users who don't have any.

- **Automation Restore Defaults - 500 Error:**
  - Fixed 500 error when clicking "Restore Defaults" button on Automations page.
  - Added missing trigger events to database ENUM: `crm_deal_created`, `crm_contact_status_changed`, `crm_score_threshold`, `crm_activity_logged`.
  - Changed restore logic to ADD missing default automations instead of deleting and recreating all system automations.
  - Default automations are now set to ACTIVE immediately upon creation (previously inactive).
  - Existing user automations (both custom and system) are preserved when restoring defaults.

## [1.8.1] – Short Description

**Release date:** 2026-01-31

### Added

- **AI Integration:**
  - Added support for **MoonshotAI Kimi K2.5** model via OpenRouter (`moonshotai/kimi-k2.5`) in all AI assistants.
  - Updated AI settings to include the new model in the selection list.
  - Improved model fetching to prioritize popular models even if not yet returned by API.
  - Added support for **Kimi K2.5** model in Ollama (`kimi-k2.5:cloud`) integration.
  - Added Optional API Key support for Ollama provider (allowing connection to secured/proxied Ollama instances).

- **AutoTag Pro - Behavioral Segmentation Engine:**
  - **Automations System:** Complete rule-based automation engine with visual builder for creating subscriber tagging and scoring rules.
  - **Trigger Events:** Support for 30+ trigger types including email opens/clicks, form submissions, purchases, pixel tracking (cart abandonment, product views), CRM events (deal stage changes, task completion), and subscriber lifecycle events.
  - **Actions:** Automated actions include adding/removing tags, adjusting lead scores, subscribing to lists, sending notifications, and creating CRM tasks.
  - **Condition Builder:** Advanced condition logic (AND/OR) for filtering which subscribers trigger automations.
  - **Execution Logging:** Full audit trail with `AutomationRuleLog` tracking trigger events, actions executed, and success/failure status.
  - **Segmentation Dashboard:** New `/segmentation` page with real-time analytics:
    - Tag distribution chart showing top 15 tags by subscriber count.
    - Score-based segments (Cold, Warm, Hot, Super Hot) with CRM contact counts.
    - Automation statistics: total rules, active rules, executions (24h/7d), success rate.
    - Top triggers breakdown showing most active automation triggers.
    - Recent activity feed with last 10 automation executions.
    - 7-day engagement trend chart showing daily execution volume.
  - **UI Components:** `Automations/Index.vue` for rule management, `Automations/Builder.vue` for visual rule creation.
  - **Sidebar Integration:** Added Segmentation Dashboard link to sidebar under automation group.
  - **Database:** `AutomationRule` and `AutomationRuleLog` models with full relationship support.
  - Full translations in PL, EN, DE, ES.

- **Default Automations System:**
  - Added 6 pre-configured automation templates that are seeded for each user on fresh install.
  - Templates include: Cold Lead Detection, Engaged Reader, Purchase Behavior, Click Champion, Welcome Sequence Trigger, and Cart Abandonment.
  - Added `is_system` and `system_key` columns to `automation_rules` table for identifying default automations.
  - Created `DefaultAutomationsSeeder` with factory templates and restore functionality.
  - Created `php artisan automations:seed-defaults` command for manual seeding.
  - Added "Restore Defaults" button to Automations page header with confirmation modal.
  - Added "Default" badge displayed next to system automation names in the list.
  - Expanded `trigger_event` ENUM to support all modern triggers including `subscriber_inactive`, `purchase`, `pixel_cart_abandoned`, and CRM events.
  - Full translations for restore functionality in PL, EN, DE, ES.

### Improved

- **A/B Testing Distribution:**
  - Implemented balanced round-robin style distribution for variant assignment instead of pure random selection.
  - Ensures accurate 50/50 split even for small sample sizes (e.g., 2 recipients), preventing scenarios where all recipients are assigned to a single variant.

- **A/B Testing Results - Sample Period Statistics:**
  - A/B test results now show only statistics from the test period (sample), excluding winner rollout to remaining audience.
  - All variant metrics (sent, opens, clicks, rates) are filtered to only include data before `test_ended_at`.
  - Added `getTotalMetrics()` method for accessing complete statistics including post-test winner sends.

- **Calendly Integration Settings UI:**
  - **Enhanced List Selection:** Replaced standard multi-select inputs with searchable checkbox lists for "Mailing Lists" and "Tags" in the settings modal.
  - **Better UX:** Added search functionality to easily find specific lists/tags in large collections.
  - **Dark Mode Support:** Fixed text visibility issues on dark backgrounds for list items.
  - **Clear Feedback:** Added "No results found" states and selected item counters.
  - **Per Event Type Configuration:** Added ability to configure different mailing lists and tags for each Calendly event type independently.

### Fixed

- **AI Model Selection:**
  - Fixed an issue where newly added default models (like MoonshotAI Kimi K2.5) were not visible in assistant dropdowns for existing integrations.
  - Updated `ActiveAiModelsController` to always merge default models with stored integration models.

- **Email Sending - Mailbox Configuration:**
  - Fixed an issue where emails were marked as "sent" even when no mailbox was configured (default mailer fallback removed).
  - System now throws a clear exception when trying to send without a valid mailbox, ensuring failed sends are properly tracked.

- **Calendly Integration - Webhook Subscription:**
  - Fixed "Invalid Argument" error when creating webhook subscription.
  - Corrected event name from `invitee.no_show` to `invitee_no_show.created` per Calendly API specs.

- **Calendly Integration - Inertia Response Errors:**
  - Fixed "plain JSON response was received" errors for Sync Event Types, Test Webhook, and Update Settings actions.
  - Changed controller methods to return proper Inertia-compatible redirect responses.

- **Calendly Integration - OAuth Connect Route:**
  - Fixed "405 Method Not Allowed" error when connecting Calendly account.
  - Changed `/settings/calendly/connect` route from GET to POST to match frontend form submission.

- **Calendly Integration - Migration Safety:**
  - Fixed "Table already exists" error (`SQLSTATE[42S01]`) when running migrations on servers with existing `calendly_integrations` table.
  - Added `Schema::hasTable()` check to prevent duplicate table creation.

- **Calendly Integration - OAuth Token Storage:**
  - Fixed "Column 'access_token' cannot be null" error (`SQLSTATE[23000]`) during OAuth connection flow.
  - Made `access_token` and `refresh_token` columns nullable to support two-phase OAuth (credentials saved first, tokens obtained after callback).

## [1.8.0] – Short Description

**Release date:** 2026-01-30

### Added

- **Calendly Integration:**
  - **Full OAuth 2.0 Integration:** Implemented complete Calendly integration with secure OAuth flow for connecting user accounts.
  - **Per-User API Credentials:** Users can now enter their own Calendly Client ID and Client Secret directly in the UI, eliminating the need for `.env` configuration.
  - **Encrypted Credential Storage:** API credentials are encrypted at rest using Laravel's Crypt facade for security.
  - **Webhook Support:** Automatic webhook registration for real-time sync of booking events (`invitee.created`, `invitee.canceled`, `invitee.no_show`).
  - **CRM Integration:** Bookings automatically create CRM contacts and tasks based on configurable settings.
  - **Mailing List Integration:** Invitees can be automatically added to selected mailing lists with optional tags.
  - **Event Type Sync:** Pulls and displays available Calendly event types from connected accounts.
  - **Marketplace Entry:** Added Calendly to the Marketplace active integrations list.
  - **Database:** New `calendly_integrations` table with encrypted token and credential storage.
  - **UI Components:** Connect modal with API credential input, settings modal for CRM/mailing list configuration.

- **Mailing List UX:**
  - **Toast Notifications:** Implemented toast notification system in Mailing List Edit form to display success/error messages.
  - **Feedback:** Added visual feedback for save actions with `onSuccess` and `onError` handling.
- **CRM Contact Editing:**
  - **Edit Modal:** Implemented a dedicated modal for editing contact details, including name, phone, status, score, position, source, company, and owner.
  - **Quick Edit:** Added an "Edit" button (pencil icon) to the contact list view (`Index.vue`) for quick access.
  - **Detailed Edit:** Added an "Edit" button to the contact details page (`Show.vue`) header.
  - **Localization:** Full translations for all edit functionality in PL, EN, DE, ES.

- **CRM Task Deletion:**
  - **Delete Action:** Added "Delete" button to the Task Modal (visible only in edit mode) allowing users to remove tasks directly from the details view.
  - **Safety Mechanism:** Implemented a confirmation modal with "danger" styling to prevent accidental deletions.
  - **Real-time Updates:** Task list and related views (Contact Profile) now automatically refresh upon task deletion.
  - **Localization:** Added translations for all delete actions and confirmation messages in PL, EN, DE, ES.

- **Message Validation Feedback:**
  - **Frontend Validation:** Added client-side validation in message creation/editing form (`Create.vue`) that checks for required fields (subject, recipient lists) before submitting.
  - **Toast Notifications:** Implemented toast notification system to display clear validation error messages when save fails.
  - **Automatic Tab Switching:** When validation errors occur, the form automatically navigates to the tab containing the field with the error.
  - **Backend Error Handling:** Added `onError` callback to form submission to catch and display server-side validation errors.
  - **Localization:** Full translations in PL, EN, DE, ES for validation messages (`messages.validation.*`).

### Improved

- **Message List Performance:**
  - **Progressive Loading:** Implemented progressive loading for recipient counts and skipped counts in the message list view.
  - **N+1 Logic:** Replaced blocking N+1 queries with a batched asynchronous loading strategy.
  - **Optimization:** First 5 messages load stats immediately, subsequent messages load in background batches of 5 every 2 seconds, reducing initial load time from seconds to milliseconds.
  - **Backend:** Added new `/messages/recipient-counts` endpoint for efficient batch stat retrieval in `MessageController`.

### Fixed

- **Calendly Integration - Team Members Query:**
  - Fixed `Unknown column 'parent_id'` SQL error in CalendlyController by using the correct `teamMembers()` relationship with `admin_user_id` column.

- **Mailing List Saving:**
  - **Redirect Issue:** Fixed `update` action redirecting to index instead of back, causing flash messages to be lost and confusing UX. Now resolves to `back()`.
  - **Tags Validation:** Fixed strict `exists` validation for tags failing on production because it didn't account for user scope. Now validates tags against the authenticated user's available tags.
  - **Frontend Mapping:** Fixed tags mapping in `Edit.vue` to correctly handle both array of IDs and array of objects, preventing data loss during save.
  - **Scroll Position:** Added `preserveScroll: true` to form submission to maintain user context after save.
- **Lead Scoring - Queue Connection Mismatch:**
  - Fixed `LeadScoringListener` using hardcoded `database` queue connection while the queue worker processes `redis` (from `QUEUE_CONNECTION` in `.env`).
  - Scoring jobs were accumulating in the `jobs` table instead of being processed.
  - Removed explicit `$connection = 'database'` to use the default queue connection.
  - **Action Required:** After deployment, clear stuck jobs with `php artisan tinker --execute="DB::table('jobs')->truncate();"` or process them with `php artisan queue:work database --queue=default --stop-when-empty`.

- **Message Scheduling - Select Date Button:**
  - Fixed "Select date and time" prompt being unresponsive when clicked after switching tabs (e.g., A/B Testing).
  - Changed non-interactive `<span>` element to a clickable `<button>` that navigates to the Settings tab.
  - Added hover effects to clearly indicate the element is interactive.

- **Zoom Integration Scopes:**
  - Fixed "Invalid scope" error during authorization by updating OAuth scopes to the new granular format (e.g., `meeting:write:meeting:admin`).
  - Updated integration instructions in Marketplace to reflect the new strict granular permission requirements.
  - Added UI labels for new granular scopes in the "Granted Permissions" display.

- **A/B Test Confidence Threshold Validation:**
  - Fixed validation mismatch where backend blocked saving A/B tests with confidence threshold below 80%, even though the UI allowed values down to 60%.
  - Updated validation rules in `MessageController`, `AbTestController`, and `Api/V1/AbTestController` to accept minimum value of 60%.

## [1.7.22] – Short Description

**Release date:** 2026-01-27

### Added

- **Zoom Integration Enhancements:**
  - **Granted Permissions Display:** Added functionality to display actually granted permissions (scopes) in the Zoom settings after authorization.
  - **Connection Details:** Zoom connection information now includes a list of granted scopes with helpful descriptions (e.g., "Create Meetings").
  - **Database Migration:** Added `granted_scopes` column to `user_zoom_connections` table to store OAuth scopes.

- **Lead Scoring - Default Contact Created Rule:**
  - Added missing `contact_created` event to default scoring rules (+5 points).
  - New contacts now automatically receive initial score when created in CRM.

- **Message Campaign Tags:**
  - **Tag Selector:** Added ability to assign tags to messages directly in the message creator/editor (`Create.vue`).
  - **UI Integration:** New campaign tag selector in the "Settings" tab of the message wizard.
  - **Backend Sync:** Updated `MessageController` to handle validation and synchronization of `tag_ids`.
  - **Localization:** Added translations for campaign tags in PL, EN, DE, ES.

- **Campaign Statistics Translations:**
  - Complete translations for the new Campaign Statistics feature in Polish, English, German, and Spanish.

### Fixed

- **Lead Scoring - Queue Processing:**
  - Fixed `LeadScoringListener` using separate `scoring` queue that wasn't being processed by the default worker.
  - Changed to use `default` queue ensuring scoring events are processed correctly.

- **Lead Scoring - Timezone Handling:**
  - Fixed `checkDailyLimit()` in `LeadScoringService` using server timezone instead of contact owner's timezone.
  - Fixed `getAnalytics()` in `LeadScoringService` using server timezone for date calculations.
  - Fixed `getScoreTrend()` in `CrmContact` using server timezone for trend calculations.
  - All scoring date calculations now correctly use the user's configured timezone.

- **Production Error Fixes:**
  - Fixed `SyncPendingCalendarTasks` command failing with "Column not found: due_at". Corrected column name to `due_date`.
  - Fixed integrity constraint violations in email tracking (`email_clicks`, `email_opens`) by adding validation for deleted subscribers.
  - Fixed `SubscriberPreferencesController` error when rendering system pages for deleted subscribers (handled nullable subscriber).
  - Fixed Global Stats page (`/settings/stats`) crashing with "Allowed memory size exhausted" error by removing unnecessary eager loading of subscribers in `GlobalStatsController`.

### Changed

## [1.7.21] – Short Description

**Release date:** 2026-01-27

### Added

- **Google Calendar Synchronization:**
  - **Auto-Sync Safety Net:** Implemented a new background process (`calendar:sync-pending-tasks`) running every minute to automatically detect and fix tasks that failed to sync with Google Calendar during creation.
  - Ensures 100% reliability by acting as a "safety net" for tasks that have sync enabled but are missing their calendar event ID due to queue delays or errors.

- **CRM Task Modal Enhancements:**
  - **Auto-Select Contact:** Automatically pre-selects the current contact when creating a task from the Contact Profile (`Contacts/Show.vue`).
  - **Required Contact:** Made assigning a contact mandatory for all tasks, with frontend and backend validation to ensure data integrity.
  - **Integration Support:** Enabled Google Meet and Zoom integration toggles when creating tasks from the Contact Profile (previously only available in Tasks dashboard).
  - **Localization:** Added missing translations for validation messages and integration labels in PL, EN, DE, ES.

- **Task Type Color Customization:**
  - **Customizable Colors:** Added a new settings section in Google Calendar integration allowing users to define custom colors for each task type (Call, Email, Meeting, Task, Follow-up).
  - **Calendar Sync:** Updated Google Calendar synchronization to use these custom colors (mapped to the closest available Google color ID) for events.
  - **Frontend:** Added color pickers with presets and custom hex input support. These settings are stored in `user_calendar_connections`.
  - **Localization:** Added missing translations for task color settings in PL, EN, DE, ES.

### Fixed

### Changed

## [1.7.20] – Short Description

**Release date:** 2026-01-27

### Added

### Fixed

- **Zoom Integration Translations:**
  - Fixed missing translations for Zoom integration in Task Modal (`crm.task.zoom.add_email`, `add_guest`, `attendees_hint`) in PL and EN.
  - Added missing Zoom attendees translations for German and Spanish locales.

- **CRM Notification Classes:**
  - Fixed production errors in `crm:check-overdue-tasks` scheduled command caused by missing notification classes.
  - Created `TaskOverdueNotification.php` - sends email and database notification when a CRM task becomes overdue.
  - Created `DealStageChangedNotification.php` - notifies deal owner when a deal stage changes (especially for won/lost deals).
  - Created `ContactRepliedNotification.php` - notifies when a CRM contact responds through any communication channel.

- **Zoom Integration & Calendar Sync:**
  - Fixed critical `ArgumentCountError` in `SyncTaskToCalendar` job by correctly passing `UserZoomConnection` when creating meetings.
  - Fixed missing Zoom join link in Google Calendar events by adding it to the `location` field, enabling the native "Join" button.
  - **New:** Zoom meetings are now automatically deleted when a CRM task is deleted.
  - **New:** Zoom meetings are now automatically updated when task title or time changes.
  - **New:** Disabling "Zoom Meeting" toggle on an existing task now deletes the associated Zoom meeting.
  - Fixed duplicate Zoom meetings being created on job retry by refreshing task state from database before checking `zoom_meeting_id`.
  - Fixed `zoom_meeting_link` column truncation error by expanding from VARCHAR(500) to TEXT to accommodate long Zoom start URLs with JWT tokens.

### Changed

## [1.7.19] – Short Description

**Release date:** 2026-01-26

### Added

- **CRM Task Action Feedback:**
  - **Toast Notifications:** Added visual feedback (toast messages) for task actions: Reschedule (Tomorrow, +3 Days, +1 Week), Create Follow-up, and Delete.
  - **Delete Confirmation:** Replaced browser-native confirm dialog with a proper "Confirm Delete" modal for tasks.
  - **Instant Updates:** Task list now automatically refreshes after rescheduling or creating follow-ups to reflect changes in the current filter view immediately.

- **Subscriber Import Column Mapping:**
  - **Flexible Mapping:** Implemented a new interface allowing users to map CSV columns to system fields (Email, Name, Phone) and Custom Fields during import.
  - **Smart Detection:** Automatically detects and suggests column mappings based on header names and content analysis.
  - **Custom Field Support:** Values mapped to custom fields are automatically stored in the `fieldValues` relationship.
  - **Header Control:** Added "First row contains headers" toggle to handle files with or without headers.
  - **Localization:** Full translations for the mapping interface in EN, PL, DE, ES.

- **Zoom & Google Meet Integration Improvements:**
  - **New Guest Management for Zoom:** Implemented "Invited Guests" section for Zoom Meetings in Task Modal, matching Google Meet functionality.
  - **Auto-Add Contact:** Enabling Zoom integration now automatically adds the assigned contact's email to the attendee list.
  - **Smart Visibility:** Zoom and Google Meet sections are now always visible for meeting-type tasks.
  - **Connection State Handling:** Added "Connect to activate" overlay and disabled state for integration sections when the respective service is not connected.
  - **Localization:** Added translations for guest management and connection status messages in PL, EN, DE, ES.

### Fixed

- **CRM Task Rescheduling:**
  - Fixed an issue where rescheduling a task would reset its time to midnight (00:00). Now preserves the original time while changing the date.
  - Fixed task visibility issue where rescheduled tasks remained visible in the "Today" filter until manual refresh.

- **Anthropic AI Integration - Content Generation Timeout:**
  - Fixed Anthropic Claude not working when generating email content, templates, or A/B tests due to insufficient API timeout.
  - Added custom `makeRequest()` override in `AnthropicProvider.php` with 120s timeout (BaseProvider uses 30s which was too short for large prompts).
  - Improved error handling with proper Anthropic API error structure parsing (`error.message` and `error.type`).
  - Added detailed logging for API errors to help with debugging.
  - Fixed text fragment generation (`mode='text'`) using `max_tokens_small` (8000) instead of `max_tokens_large` (50000), causing incomplete responses for longer content.

- **Task Modal Stability:**
  - Fixed `TypeError` crash when opening task modal without an active Google Calendar connection.
  - Added safe access to calendar connection properties in the UI.

### Changed

- **Meeting Integration UX:**
  - **Visibility:** Google Meet and Zoom options are now visible for "Meeting" tasks even when disconnected (displayed as disabled with explanatory status message), improving feature discoverability.
  - **Status Messages:** Added clear status indicators distinguishing between "Configuration required" and "Enable sync first" states.
  - **Localization:** Added missing translation keys for integration status messages in PL and EN.

## [1.7.18] – Short Description

**Release date:** 2026-01-26

### Added

- **Subscriber Column Ordering:**
  - **Drag and Drop:** Added active/available column lists with drag-and-drop ordering for subscribers.
  - **Persistence:** Implemented local storage persistence for column order to maintain user preferences.
  - **Localization:** Updated translations for the new column settings labels in EN, PL, DE, ES.

- **Message Open Triggers:**
  - **New Trigger Types:** Added "Opened message" (`opened_message`) and "Did not open message" (`not_opened_message`) triggers.
  - **Targeting:** Allows sending follow-up messages based on whether a subscriber opened a specific previous message.
  - **Filtering Logic:** Implemented backend filtering in `Message::getUniqueRecipients` using `EmailOpen` data.
  - **UI Implementation:** Added message search and selection in the campaign wizard (`Create.vue`).
  - **Localization:** Full translations in EN and PL.

- **CRM Delete Functionality:**
  - **Company Deletion:** Implemented comprehensive delete flow for companies with `delete_contacts` option.
  - **Contact Deletion:** Added delete button and confirmation modal for contacts.
  - **Modals:** Added confirmation modals with detailed consequence information (e.g. unlinking vs deleting contacts).
  - **Backend:** Updated `CrmCompanyController::destroy` to handle optional contact deletion.
  - **UI/UX:** Added delete buttons to Show and Index pages for both Contacts and Companies.

- **Zoom Integration Enhancements:**
  - **Google Calendar Sync:** Zoom meeting links are now automatically added to the Google Calendar event description during synchronization.
  - **Guest Management:** Unified guest management for both Zoom and Google Meet. Guests added in CRM are correctly passed to Zoom meeting attendees.
  - **UX Improvements:** Improved "Add Guest" input with a dedicated "Add" button and better validation.
  - **Localization:** Added missing Zoom translations for all supported languages (PL, EN, DE, ES).

## [1.7.17] – Short Description

**Release date:** 2026-01-26

### Added

- **Subscription Forms - Status Toggle:**
  - **Quick Action:** Added a toggle button in the forms list (`Index.vue`) to quickly activate/deactivate forms without entering edit mode.
  - **Visual Feedback:** Status button changes icon and color (green checkmark for activation, orange power icon for deactivation).
  - **Backend Support:** Implemented `toggleStatus` endpoint in `SubscriptionFormController`.

- **CRM Task Duration Picker:**
  - Added duration picker with presets (5, 10, 15, 30, 60, 120 min) and "Custom" option to Task Modal.
  - Implemented automatic end time calculation based on selected duration and start time.
  - Implemented reverse detection of duration preset when end time is manually changed.
  - Added localization for duration controls in EN, PL, DE, ES.

- **Resubscription Autoresponder Reset:**
  - Added new per-list setting `reset_autoresponders_on_resubscription` (default: enabled).
  - When enabled, re-subscribing to a list deletes old queue entries and sends autoresponders from the beginning.
  - When disabled, existing queue entries are preserved (original behavior).
  - UI toggle added to list settings under "Subscription" tab.

### Improved

- **Subscription Forms UX:**
  - **Toast Notifications:** Added clear toast notifications for all form actions (save, duplicate, delete, status change) in both List and Builder views.
  - **Visual Feedback:** Improved success states in the Form Builder save button.
  - **Localization:** Added full translations for form status actions in PL and EN.

- **CRM Calendar - Weekly View Overlap Handling:**
  - Implemented advanced collision detection algorithm to handle overlapping events.
  - Events occurring at the same time are now displayed side-by-side (sharing column width) instead of stacking on top of each other.
  - Improved readability for busy schedules with concurrent tasks.

- **CRM Calendar - Daily View:**
  - Added new detailed "Day" view mode accessible via view toggle and by clicking day headers.
  - Features larger time slots (72px/hour) for better readability of event details.
  - Displays full event information including priority, contact name, and description.

### Fixed

- **Subscriber Restoration:**
  - Fixed an issue where re-adding a soft-deleted subscriber would retain old list memberships, original subscription date, and message queue history.
  - Implemented complete reset of subscriber state on restoration: detaches all previous lists, deletes all message queue entries (allowing fresh autoresponder sequences), and sets a fresh `subscribed_at` timestamp.

- **Autoresponder Queue Statistics:**
  - Fixed an issue where subscribers with pending queue entries (PLANNED/QUEUED) were incorrectly counted as "Missed" for Day 0 autoresponders.
  - Added a new `pending` statistic to `getQueueScheduleStats` to accurately track subscribers waiting for CRON processing.

- **SendEmailJob Argument Validation:**
  - Fixed critical `TypeError` exceptions in `FunnelExecutionService`, `FunnelRetryService`, and `AutomationActionExecutor` where `SendEmailJob` was instantiated with incorrect argument types (string or array instead of `Mailbox` model).
  - Ensured email sending reliability in Funnels and Automations.

- **CRM Tasks Timezone Handling:**
  - Fixed an issue where CRM tasks were parsed in UTC instead of the user's timezone, causing a 1-hour offset when syncing to Google Calendar (e.g., Warsaw time).
  - Updated `GoogleCalendarService` to correctly convert UTC stored dates to the user's specific timezone before sending to Google Calendar API.
  - Fixed `CalendarGrid.vue` to compare dates using the local timezone instead of UTC, ensuring events appear on the correct days regardless of user's location.
  - Added `userTimezone` prop to Dashboard and Task views to ensure consistent date presentation across the frontend.

- **Google Calendar Synchronization:**
  - Fixed `destroy` action not removing the event from Google Calendar before deleting the task locally.
  - Fixed `update` action not synchronizing changes when the task was already connected to Google Calendar.
  - Fixed `reschedule` action not updating the event date in Google Calendar.
  - Added consistent JSON responses for `destroy`, `reschedule` and other task actions.

## [1.7.16] – Short Description

**Release date:** 2026-01-26

### Added

- **Zoom Integration for CRM Tasks:**
  - **Full Video Meeting Support:** Parallel implementation to Google Meet, allowing users to choose between Zoom and Meet for CRM tasks.
  - **OAuth 2.0 Integration:** Secure connection via Zoom App Marketplace with token management (access/refresh tokens).
  - **Meeting Management:**
    - Automatic meeting creation when "Add Zoom Meeting" toggle is enabled in Task Modal.
    - Generates unique Join URL, Start URL, and Password for each task.
    - Stores meeting details (`zoom_meeting_id`, `zoom_join_url`, `zoom_start_url`, `zoom_password`) in `crm_tasks` table.
  - **UI Integration:**
    - Dedicated Marketplace page (`/marketplace/zoom`) with setup instructions.
    - Settings page (`/settings/zoom`) for managing OAuth credentials and connection status.
    - Seamless integration in Task Modal with automatic Google Meet mutual exclusivity (toggling one disables the other).
  - **Backend Architecture:**
    - `UserZoomConnection` model for secure credential storage.
    - `ZoomOAuthService` for handling authorization flow and token refresh.
    - `ZoomMeetingService` for API interactions.
    - Integration with `SyncTaskToCalendar` job to ensuring meetings are created during calendar sync.
  - **Localization:** Full translations in EN, PL, DE, ES.

- **CRM Sales Funnel Improvements:**
  - **Deal Detail Modal:** Added a modal to view and edit deal details (name, value, stage, contact, company) directly from the Kanban board by clicking on a deal.
  - **Searchable Selects:** Implemented `SearchableSelect` component for Contacts and Companies in the deal form, enabling real-time search for large datasets.
  - **Smart Form Association:**
    - Automatically loads the associated Company when a Contact is selected.
    - Filters the Contact list to show only relevant employees when a Company is selected first.
  - **Delete Confirmation:** Added a safety confirmation modal when deleting a deal from the Kanban board.
  - **Backend Endpoints:** Added `/crm/contacts/search` and `/crm/companies/search` endpoints to support frontend search components.

- **Google Meet Integration for CRM Tasks:**
  - Automatic Google Meet link creation when syncing meeting-type tasks to Google Calendar.
  - Toggle "Add Google Meet link" in Task Modal (visible for meeting tasks with calendar sync enabled).
  - Guest invitation system: add attendee emails to calendar events with automatic email invitations.
  - Auto-add CRM contact email to attendees when Meet is enabled.
  - Guest response status tracking: syncs attendee status (accepted, declined, tentative) from Google Calendar.
  - Status icons displayed next to attendee emails in Task Modal.
  - New database columns: `google_meet_link`, `google_meet_id`, `include_google_meet`, `attendee_emails`, `attendees_data`.
  - Added dedicated Marketplace page (`/marketplace/google-meet`) with features, setup guide, and quick actions.
  - Full translations in PL, EN, DE, ES including guest status labels.

- **CRM Tasks - Calendar View Improvements:**
  - Default view changed from Monthly to Weekly for better task visibility.
  - Calendar automatically scrolls to 8:00 AM on load instead of midnight.
  - Clicking task filters (Overdue, Today, Upcoming, Completed) now automatically switches to List view.

- **Google Calendar Events in List View:**
  - Events from Google Calendar are now displayed in a dedicated section below tasks in List view.
  - Events are visually marked with blue Google Calendar icon and "📅 Google" badge.
  - Location information displayed for events that have it.
  - Fetches events for the next 7 days when switching to List view.

- **Google Meet Quick Join:**
  - Added "Meet" button to each task in List view that has a Google Meet link.
  - Added "Join Meet" button to Google Calendar events that have Google Meet links.
  - All Meet buttons open in a new browser tab for seamless joining.

- **Meeting Reminder Notifications:**
  - Real-time notification system for upcoming meetings with Google Meet links.
  - 5-minute warning: dismissible orange notification with "Prepare to join" button.
  - 1-minute / Now alert: sticky red notification that cannot be dismissed, with prominent "Join Meeting" button.
  - Two-tone audio notification using Web Audio API (no external files needed).
  - Checks for upcoming meetings every 30 seconds.
  - Works with both CRM tasks and Google Calendar events.
  - New API endpoint `/crm/tasks/upcoming-meetings`.
  - New composable `useMeetingReminders.js` and component `MeetingReminderNotification.vue`.

### Fixed

- **Database Migration Compatibility:**
  - Fixed migration `2026_01_25_200000_add_is_default_to_crm_follow_up_sequences` failing on Laravel 11 with error "Method getDoctrineSchemaManager does not exist".
  - Replaced deprecated Doctrine DBAL usage with native MySQL `SHOW INDEX` query for index existence checking.

- **Autoresponder List Selection:**
  - Unified the audience selection interface for autoresponder messages to match the broadcast message experience.
  - Replaced the simple list dropdown with the advanced group/tag-based selection component.
  - Added support for "Excluded Lists" in autoresponder messages, allowing users to prevent sending to specific list subscribers.

## [1.7.15] – Short Description

**Release date:** 2026-01-25

### Improved

- **Docker Container Startup:**
  - Enhanced `docker-entrypoint.sh` to prevent `500 Server Error` caused by missing database migrations.
  - Added pre-flight check to display pending migrations count.
  - Implemented automatic retry mechanism (3 attempts with 5s delay) for migration execution.
  - Added post-execution verification to ensure database schema is fully up to date before starting the application.

### Fixed

- **Scheduled Command Typo:**
  - Fixed `cron:notify-overdue-tasks` command not found error by correcting the scheduled command name to `crm:check-overdue-tasks` in `console.php`.

### Added

- **CRM Navigation & Lead Scoring:**
  - Added navigation links for "Lead Scoring" (`/crm/scoring`) and "Sequences" (`/crm/sequences`) to the CRM sidebar menu.
  - Implemented auto-seeding of default scoring rules when a user visits the scoring settings page for the first time.
  - Added confirmation modals for "Reset to Defaults" and "Delete Rule" actions in the Lead Scoring configuration page (`ScoringRules.vue`).

- **CRM Contact Search:**
  - Added real-time subscriber search in CRM Contact creation form with autocomplete dropdown.
  - Implemented intelligent matching by email, name, or phone number with debounce optimization.
  - Added auto-filling of contact forms when an existing subscriber is selected.
  - New internal endpoint `/crm/contacts/search-subscribers` for secure searching.
  - Full translations in PL, EN, DE, ES.

- **CRM Task Time Picker:**
  - Added start and end time selection fields to task creation and edit forms (default 09:00–10:00).
  - Updated Google Calendar synchronization to use task's actual end time instead of default +1 hour.
  - Updated Google Calendar synchronization to respect the user's configured timezone instead of the application default.
  - Fixed dark mode styling for time picker icons (black icons on black background).
  - Added `due_time` and `end_time` translations in PL, EN, DE, ES.

- **CRM Default Follow-up Sequences:**
  - **4 Professional Templates:** Implemented 4 highly effective default follow-up sequences ("New Lead Nurturing", "Contact Recovery", "After Meeting Follow-up", "Sales Closing") created automatically for new users.
  - **Restore Functionality:** Added "Restore Defaults" option in the Sequences dashboard with a safety confirmation modal to reset sequences to their original state.
  - **Dynamic Localization:** Implemented smart model-based translation for sequence names/descriptions, ensuring they display in the user's selected language (PL/EN) regardless of database content.
  - **UI Indicators:** Added "Default" and "Custom" badges to easily distinguish between system templates and user-modified sequences.
  - **Backend:** New `DefaultFollowUpSequencesService` and `restoreDefaults` endpoint structure.

- **CRM Tasks - Calendar View:**
  - Added new "Calendar" tab to Tasks dashboard with Month and Week views.
  - Implemented interactive calendar interface (`CalendarGrid.vue`) with date navigation.
  - Added visual indicators for task priorities, completion status, and Google Calendar events.
  - Integrated task management: clicking calendar events opens the task edit/create modal.
  - Backend: Added `/crm/tasks/calendar-events` endpoint for efficient date-range querying.
  - Localization: Full translations in PL and EN.

### Fixed

- **Lead Scoring UI:**
  - Fixed "Reset to Defaults" button not working by replacing the native browser `confirm()` dialog (which was blocked or ignored) with a custom modal component.
  - Fixed missing default rules for new users by adding an auto-seed check in `LeadScoringController`.

## [1.7.14] – Short Description

**Release date:** 2026-01-25

### Added

- **CRM Dashboard - Follow-up Widget:**
  - Added "Follow-ups" widget to the CRM Dashboard showing the number of active sequences.
  - Widget links directly to the sequences list (`/crm/sequences`).

- **CRM Follow-up System - Enhancements:**
  - **Email/SMS Reminders:** Implemented `TaskReminderMail` and extended `crm:send-task-reminders` command to send email notifications based on user preferences.
  - **Automated Triggers:** Created `FollowUpSequenceListener` to automatically enroll contacts in sequences based on CRM events: `on_deal_created`, `on_contact_created`, `on_task_completed`, `on_deal_stage_changed`.
  - **Effectiveness Reporting:** Added comprehensive reporting pages for sequences (`/crm/sequences/{id}/report`) with funnel charts, conversion rates, and activity stats.
  - **UI Integration:** Added "Report" button to sequence cards in the list view for quick access to statistics.

### Added

- **Google Calendar Integration:**
  - **Two-Way Sync:** Implemented professional two-way synchronization between CRM tasks and Google Calendar events.
  - **OAuth 2.0 Connection:** Full OAuth flow using existing Google Integrations for calendar access.
  - **Push Notifications:** Real-time event change detection via Google Calendar push notifications (webhooks).
  - **Task-to-Event:** CRM tasks with `sync_to_calendar` enabled automatically create/update Calendar events.
  - **Event-to-Task:** Changes made in Google Calendar are reflected back in CRM tasks.
  - **Database:** New `user_calendar_connections` table and sync fields on `crm_tasks`.
  - **Services:** `GoogleCalendarService` for Calendar API operations, `GoogleCalendarOAuthService` for token management.
  - **Jobs:** `SyncTaskToCalendar` for async sync, `ProcessCalendarWebhook` for handling push notifications.
  - **Console Command:** `calendar:refresh-channels` for maintaining webhook channels.
  - **UI:** Calendar settings page (`Settings/Calendar/Index.vue`) with connection management.
  - **Navigation:** Integrated Calendar settings into the main Sidebar menu under Settings group.
  - **Testing:** Added feature tests covering connection flow and sync logic (`GoogleCalendarIntegrationTest`).
  - **Localization:** Full translations in EN and PL.
  - **Recurring Tasks:** Implemented support for recurring tasks (Daily, Weekly, Monthly, Yearly) with RRULE compatibility for Google Calendar.
  - **Conflict Resolution:** Added conflict detection strategies with a dedicated UI for resolving data mismatches between CRM and Google Calendar.
  - **Bulk Sync:** Added "Sync All Tasks" functionality to the Calendar Settings page to process all pending syncs in batches.
  - **Task UI Enhancements:** Added direct "Sync to Calendar" toggle and calendar selection dropdown in the Task Modal.

- **CRM Follow-up System:**
  - **Sequences:** Implemented comprehensive Follow-up Sequence system allowing automated task creation and contact nurturing.
  - **Visual Builder:** Created `Sequences/Builder.vue` with drag-and-drop support for organizing sequence steps.
  - **Step Configuration:** Support for various action types (Task, Email, SMS, Wait) with configurable delays (days/hours).
  - **Contact Enrollment:** Added ability to enroll contacts into sequences directly from their profile or via automation.
  - **Database:** New tables: `crm_follow_up_sequences`, `crm_follow_up_steps`, `crm_follow_up_enrollments`.

- **CRM Task Enhancements:**
  - **Full Editing:** Tasks are now fully editable via a new modal interface (Title, Description, Type, Priority, Due Date).
  - **Quick Actions:** Added dropdown menu to task list with "Reschedule" (Tomorrow, +3 Days, +1 Week), "Create Follow-up", and "Snooze" actions.
  - **Snooze & Reminders:** Implemented "Snooze" functionality for task reminders and added visual indicators (badges) for task categories.
  - **Categorization:** Added visual badges for special task types: "Follow-up", "Reminder", "Important".
  - **Backend:** Enhanced `CrmTask` model with reminder support and parent/child relationship tracking.

- **Scheduled Commands:**
  - `crm:process-follow-ups`: Processes active sequence enrollments and creates tasks/actions every 5 minutes.
  - `crm:send-task-reminders`: Checks for due task reminders and sends notifications every 5 minutes.

### Fixed

- **A/B Test Tracking:**
  - Fixed an issue where email opens and clicks in A/B tests were not attributing to specific variants, causing 0% stats for all variants.
  - Added `ab_test_variant_id` to `$fillable` array in `EmailOpen` and `EmailClick` models to allow mass assignment.

### Changed

- **Task List UI:**
  - Improved `Tasks/Index.vue` with responsive grid layout, better filtering, and action menus.
  - Added direct link to "Sequences" management from the tasks dashboard.

## [1.7.13] – Short Description

**Release date:** 2026-01-23

### Added

- **Partner Program Expansion - Referral Tools:**
  - Added "Your Referral Tools" card to Partner Dashboard showing personalized referral link and code with copy-to-clipboard functionality.
  - Implemented referee tracking: new `referred_by_affiliate_id` column on `users` table to track which partner referred a user at registration.
  - Added referral detection for user registration via URL parameter (`?ref=CODE`) or cookie (`ns_affiliate`).
  - Created `/ref/{code}` route for seamless affiliate link tracking that redirects to registration with referral code.
  - Registration through referral now records a "lead" conversion for commission tracking.

- **Partner Registration Referrals (MLM Structure):**
  - Partner registration form now accepts optional referral code from existing partners.
  - Added referral banner showing "Referred by [Partner Name]" when registering via a referral link.
  - Enables multi-level tracking of partner-to-partner referrals.

- **Admin Partner Portal Access:**
  - Added "View as Partner" button to Affiliate Program dashboard header and Programs list.
  - Allows administrators to instantly access the Partner Portal for any affiliate program.
  - Automatically creates an affiliate account for the admin if one doesn't exist.

- **Partner Team Page ("My Team"):**
  - New `/partner/team` page displaying hierarchical view of partner's referral network.
  - Shows direct referrals (level 1) and their sub-referrals (level 2) with individual stats.
  - Team statistics: total partners, direct partners, team clicks, conversions, and earnings.
  - "Invite Partners" section with copy-to-clipboard referral link.
  - Added "My Team" to Partner Portal sidebar navigation.

- **Partner Program Translations:**
  - Full translations for all new partner features in PL, EN, DE, ES.
  - Updated both backend (PHP) and frontend (JSON) translation files.
  - New keys: referral tools, team page elements, referred by banners, view as partner.

### Fixed

- **Persistent Locale Selection:**
  - Implemented persistent locale storage using cookies to fix the issue where the browser's language setting overrides the user's manual selection after session expiration.
  - Updated `SetLocale` middleware to prioritize the `locale` cookie over the `Accept-Language` header.
  - Updated `LocaleController` to store the user's language preference in a cookie (valid for 1 year) upon manual selection.

- **A/B Testing - Sample Percentage & Sending Logic:**
  - Fixed critical issue where A/B tests were sending to all recipients immediately instead of respecting the configured `sample_percentage`.
  - Updated `processQueue` to properly limit sending to the sample size and auto-assign variants to recipients.
  - Implemented logic to automatically substitute message subject and preheader with the assigned variant's content during sending.
  - Added `ab_test_variant_id` to `MessageQueueEntry` model to track variant assignment per recipient.

- **Message Statistics UI:**
  - Fixed layout issue in "Recent Clicks" table where rows were stretched vertically due to grid alignment. Added `items-start` class to ensure independent height for "Recent Opens" and "Recent Clicks" cards.

### Changed

- **A/B Test UI Improvements:**
  - The "A/B Test" badge on the message list is now clickable and links directly to the results page.
  - Lowered the minimum confidence threshold for auto-winner selection from 80% to 60% to provide more flexibility.

## [1.7.12] – Short Description

**Release date:** 2026-01-23

### Fixed

- **Message Link Tracking:**
  - Fixed an issue where subscribe/unsubscribe list actions on tracked links were not working due to URL hash mismatches.
  - Updated `MessageTrackedLink` model to decode HTML entities in URLs during normalization, ensuring hashes match between HTML content and click events.
  - Updated `TrackedLinksSection.vue` to decode HTML entities when extracting links from message content for consistent tracking configuration.

- **Resend to Failed Subscribers:**
  - Fixed 419 Page Expired (CSRF) error when using "Resend to Failed" button by migrating from manual `fetch()` to Inertia `router.post()`.
  - Updated `MessageController` to return proper Redirect responses with flash messages instead of JSON, ensuring compatibility with Inertia.
  - Added missing translation keys for success/error messages in `src/lang/en/messages.php` and `src/lang/pl/messages.php`.

- **Anthropic AI Integration - Connection Test & Model Updates:**
  - Fixed Anthropic connection test failing with 404/401 errors due to outdated model IDs.
  - Updated `AnthropicProvider.php` to use the user-selected model for connection tests instead of forcing a hardcoded default.
  - Added detailed error logging with API key prefix and model information for easier debugging.
  - Improved error messages to distinguish between invalid API key (401) and model not found (404) errors.

### Changed

- **AI Provider Models Updated to January 2026:**
  - **Anthropic:** Updated to Claude 4.5 family (official January 2026 models):
    - `claude-sonnet-4-5-20250929` - Claude Sonnet 4.5 (recommended default)
    - `claude-haiku-4-5-20251001` - Claude Haiku 4.5 (fastest)
    - `claude-opus-4-5-20251101` - Claude Opus 4.5 (premium)
    - Added aliases (`claude-sonnet-4-5`, `claude-haiku-4-5`, `claude-opus-4-5`) for testing convenience.
    - Added older active snapshots: `claude-sonnet-4-20250514`, `claude-opus-4-20250514`, `claude-opus-4-1-20250805`.
    - Marked legacy Claude 3 models as deprecated with retirement dates.
  - **OpenAI (OpenRouter):** Updated to GPT-5.2 Preview, GPT-5 Pro, o3 reasoning models.
  - **Google Gemini:** Updated default to `gemini-2.5-pro`.
  - **xAI Grok:** Updated default to `grok-3-ultra`.
  - **Ollama:** Updated to support Llama 4.1, Mistral 4, Phi-5, Qwen 3.

- **AI Integration Frontend:**
  - Updated `Index.vue` (AI Integrations settings page) with January 2026 model lists for all providers.
  - Updated `localizeModelName()` function to handle new date labels ("Styczeń 2026", "Nowość").
  - Added new translation keys `ai.models.january_2026` and `ai.models.new` in `pl.json` and `en.json`.

## [1.7.11] – Short Description

**Release date:** 2026-01-21

### Added

- **Partner Program Registration Flow:**
  - Fixed partner registration form not submitting due to field name mismatch (`agree_terms` vs `accept_terms`).
  - Added `website` field support to partner registration (validation, saving, database migration).
  - Created `Pending.vue` page shown after registration when manual approval is required.
  - Created 5 new authorization policies for affiliate system: `AffiliatePolicy`, `AffiliateProgramPolicy`, `AffiliateOfferPolicy`, `AffiliateCommissionPolicy`, `AffiliatePayoutPolicy`.

- **Partner Program Management UI:**
  - Added delete confirmation modal with program details (affiliates count, offers count).
  - Added full registration link display with copy-to-clipboard button and open-in-new-tab button.
  - Added visual feedback (checkmark icon) when link is copied successfully.
  - Full translations in EN, PL, DE, ES.

- **Multi-Level Affiliate Program Configuration:**
  - **Level Rules Editor:** New `LevelRulesEditor.vue` component for configuring commission percentages per tier (e.g., L1: 20%, L2: 5%, L3: 2%).
  - **MLM Toggle:** Added advanced settings panel to program Create/Edit forms with option to enable multi-level commissions.
  - **Attribution Model:** Configurable attribution model selection (First Click, Last Click, Linear) for determining which affiliate receives credit.
  - **Commission Hold Period:** Added configurable hold period (days) before commission auto-approval for refund protection.
  - **Backend Updates:** Extended `AffiliateController` to handle level rules, attribution model, and commission hold days.
  - **Database:** Added `commission_hold_days` column to `affiliate_programs` table.
  - **Full Translations:** Added 30+ new translation keys in EN, PL, DE, ES for all MLM-related settings.

- **Scheduling Validation:**
  - **Past Date Prevention:** Implemented validation in Email and SMS campaign creation to prevent users from selecting dates in the past.
  - **Smart Reset:** Automatically resets invalid dates to the minimum allowed time (Current Time + 5 minutes).
  - **Local Timezone Support:** Fixed timezone issues in date pickers to correctly respect the user's local time instead of UTC.
  - **Component Update:** Updated `TextInput` component to correctly inherit attributes like `min` and `max`.

### Fixed

- **Docker Installation - `.env` Folder Bug:**
  - Added explicit warning in `docker-compose.yml` header that `.env` file must be created BEFORE running `docker compose up -d`.
  - Added troubleshooting section in `DOCKER_INSTALL.md` explaining the issue and solution.
  - **Root Cause:** Docker creates a folder instead of a file when bind-mounting a non-existent source path.

- **Affiliate Admin Panel 403 Errors:**
  - Fixed 403 Forbidden errors when accessing affiliate management pages by implementing missing authorization policies.
  - Registered all policies in `AppServiceProvider.php`.

### Changed

- **Message Scheduling UX:**
  - **Smart Schedule Button:** The "Schedule" button now automatically switches the mode to "Schedule for later" and opens the settings tab, reducing clicks.
  - **Context-Aware Visibility:** Hidden the generic "Schedule" button when "Schedule for later" mode is already active to prevent confusion.
  - **User Guidance:** Added a prominent amber prompt "Select date and time" when scheduling mode is active but no date/time has been chosen yet.

## [1.7.10] – Short Description

**Release date:** 2026-01-19

### Added

- **Resend to Failed Subscribers:**
  - **Feature:** Added ability to resend messages specifically to subscribers who encountered delivery errors (e.g., due to sending limits).
  - **Backend:** New `resendToFailed()` method in `MessageController` that resets status from `failed` to `planned` for retry.
  - **UI Implementation:** Added "Resend to Failed" button in Message Statistics view (`Stats.vue`) which appears when failed messages are detected.
  - **Localization:** Full translations in PL and EN.

### Fixed

- **MCP Campaign Scheduling - Timezone Support:**
  - **Problem:** When external AI agents scheduled campaigns via MCP, messages were sent at incorrect times because user's timezone was not considered.
  - **Backend Fix:** Updated `MessageController::schedule()` to accept optional `timezone` parameter and convert user's local time to UTC before storing.
  - **Timezone Resolution:** Implements hierarchical timezone detection: Request → Campaign → User Profile → UTC.
  - **MCP Server Update:** Added `timezone` parameter to `schedule_campaign` tool with improved documentation.
  - **Package Update:** Bumped `@netsendo/mcp-client` to v1.3.0.

## [1.7.9] – Short Description

**Release date:** 2026-01-19

### Fixed

- **MCP Campaign Creation:**
  - **Tool Validation:** Fixed "missing channel" error by enhancing `create_campaign` tool with strict pre-validation and clear error messages requiring `channel` ('email' or 'sms').
  - **Agent Prompts:** Updated AI agent prompts (PL/EN) in `MCP.vue` to explicitly document the `channel` parameter requirement and distinguish between Email and SMS workflows.
  - **Package Distribution:** Bumped `@netsendo/mcp-client` to v1.1.0 to ensure client tools receive the updated schema.

- **SMS Provider Selection:**
  - **Dynamic Resolution:** Implemented hierarchical resolution for SMS providers (Message → List → Global Default), matching email mailbox logic.
  - **Database Migration:** Added `default_sms_provider_id` to `contact_lists` and `sms_provider_id` to `messages` tables.
  - **UI Implementation:** Added SMS provider selection dropdown in Message Creator (`Sms/Create.vue`) with source indicators (Global, List, Explicit).
  - **Localization:** Added translations for new SMS settings in PL and EN.

### Added

- **API Request Logging:**
  - **Infrastructure:** Implemented `ApiRequestLog` model and migration for storing full API request/response lifecycle.
  - **Middleware:** Added `LogApiRequest` middleware to logging all API v1 traffic asynchronously.
  - **Settings UI:** Added backend endpoints (`/settings/logs/api-requests`) for the log viewer to display API traffic statistics and details.
- **Recent Subscribers Trigger:**
  - **New Trigger Type:** Added "Recent Subscribers" trigger allowing messages to be sent only to users who subscribed within a specific timeframe (1-365 days).
  - **Configuration:** Implemented a slider and manual input interface for easy day selection in the message creation wizard.
  - **Filtering Logic:** Updated backend recipient filtering to strictly respect the subscription date window.
  - **Localization:** Full translations in PL, EN, DE, and ES.

## [1.7.8] – Short Description

**Release date:** 2026-01-18

### Added

- **MCP Campaign Tools:**
  - **Tool Enhancements:** Updated `create_campaign` tool description to clarify required parameters (channel, type) and workflow options.
  - **New Parameter:** Added optional `scheduled_at` parameter to `create_campaign` for one-step scheduled campaign creation.
  - **Type Definitions:** Updated `MessageCreateInput` interface to include `scheduled_at` support.
- **Message Statistics Error Modal:**
  - **Detailed View:** Implemented a clickable error column in the recipient list that opens a modal with the full, non-truncated error message.
  - **Copy Functionality:** Added a "Copy to Clipboard" button for quick error copying.
  - **Localization:** Added translation support for error details in PL and DE.

### Fixed

- **API Key Generation:** Fixed "CSRF token mismatch" error when generating new keys by replacing native `fetch` with `axios` implementation to ensure proper token handling.
- **Error Message Truncation:** Fixed an issue in Message Statistics where long error messages (e.g., SMTP connection failures) were truncated/hidden in the table view. Now accessible via the new details modal.

### Changed

## [1.7.7] – Short Description

**Release date:** 2026-01-18

### Added

- **MCP Tool Enhancements:**
  - **New Tool:** Added `list_placeholders` tool to the MCP server, allowing AI agents to retrieve a complete list of available system, custom, and special placeholders.
  - **Client Method:** Added `listPlaceholders()` method to `@netsendo/mcp-client` SDK.
  - **Enhanced Descriptions:** Updated tool descriptions for `create_campaign`, `send_email`, and `send_sms` to include detailed placeholder usage examples and workflow guidance.
  - **Vocative Support:** Added documentation for `[[!fname]]` (Polish vocative case) to MCP tools.

- **MCP Marketplace UI:**
  - **Placeholder Documentation:** Added a dedicated section listing standard, system, and gender-based placeholders with examples.
  - **AI Agent Prompt:** Added a "Technical Prompt" section with a one-click copy feature to help users configure their AI agents effectively.
  - **Localization:** Added full Polish (PL) and English (EN) translations for all new MCP sections.

### Fixed

- **Frontend Syntax Error:** Fixed `SyntaxError: Not allowed nest placeholder` in Vue i18n causing issues with gender-based placeholders (e.g., `{{male|female}}`) in code blocks. Implemented safe rendering using computed properties.
- **API Routes:** Added route aliases for `/api/v1/campaigns` ensuring backward compatibility by redirecting to `MessageController`.
- **Placeholder Consistency:** Standardized unsubscribe placeholder to `[[unsubscribe_link]]` (underscores) in System Email editor (`SystemEmail/Edit.vue`) to match the global application convention and fix redundancy.

### Changed

- **UI UX:** Simplified unsubscribe placeholder labels in PL and EN translations (removed redundant "link" word).

- **Message Sorting:** Fixed sorting by "Type" column in the messages list. Now properly applies secondary sorting by "Day" for autoresponder messages, ensuring they appear in numerical order (Day 1, Day 2, etc.) instead of random order.

### Changed

## [1.7.6] – Short Description

**Release date:** 2026-01-17

### Added

- **MCP Test Endpoint:**
  - **Public API:** Added `/api/mcp/test` endpoint allowing external applications (AI assistants) to verify API key validity and connection status.
  - **Documentation:** Updated `docs/mcp-server.md` with connection testing instructions and curl examples.
  - **Marketplace UI:** Added a "Test Connection" section to the `/marketplace/mcp` page with a ready-to-use curl command generator.

- **MCP Email Campaign & Automation:**
  - **Campaign Management:** Added MCP tools and API endpoints for full email campaign lifecycle:
    - Create, update, delete campaigns (messages).
    - Manage recipient lists and exclusions.
    - Schedule and send campaigns.
    - View campaign statistics.
  - **A/B Testing:** Implemented comprehensive A/B testing capabilities via MCP:
    - Create and manage A/B tests.
    - Support for multiple variants (Subject, Content, Sender, etc.).
    - Tools for start, end, and retrieve test results.
  - **Automation Funnels:** Added tools for managing automation sequences:
    - Create funnels with triggers (List Signup, Tag Added, etc.).
    - Add steps (Email, Delay, Condition).
    - Activate and pause funnels.
  - **Extended Client:** Updated `@netsendo/mcp-client` with 25 new tools and corresponding API methods.

- **MCP Key Management:**
  - **Encrypted Storage:** Implemented secure storage for MCP-designated API keys using Laravel's encryption. Plain keys are encrypted and stored in the database, allowing retrieval for automated testing.
  - **Hybrid Connection Testing:** Updated `mcp:test-connection` command to support both standard HTTP testing and internal fallback verification.
    - Automatically detects if `localhost` is unreachable (e.g., within Docker) and switches to internal API key validation.
    - Ensures reliable MCP status reporting across all environments (Local Docker, Hosted, Remote).
  - **Zero-Config Local Setup:** Local Docker environments no longer require manual `MCP_API_KEY` configuration in environment variables when an API key is marked as "Use for MCP".
  - **Database Integration:** Added `is_mcp` column to `api_keys` table to designate a specific API key for MCP usage, removing the need for `MCP_API_KEY` environment variable.
  - **API Key Editing:** Added functionality to edit existing API keys (rename, modify permissions, toggle MCP status).
  - **UI Improvements:** Updated API Keys settings page with:
    - MCP checkbox in "Create Key" modal.
    - Edit button and modal for existing keys.
    - specialized "MCP" badge for the designated key.
  - **Auto-Discovery:** Updated `McpStatusService` to automatically detect and use the database-configured MCP key for status checks and connections.

### Fixed

- **MCP Connection Test:** Fixed failure in Docker environments where internal networking prevented the test command from reaching the API endpoint. Added fallback mechanism to verify key validity directly against the database.

- **MCP Key for Existing API Keys:** Fixed issue where editing an existing API key to mark it as MCP would not allow connection testing because the plain key was not stored. Added an input field in the API Key edit modal to provide the plain key for encryption when marking an existing key as MCP.

- **Email Editor Image Editing:**
  - Fixed an issue where images in full HTML documents (e.g., templates with imported footers/inserts) were not editable in preview mode as they are rendered inside an iframe.
  - Implemented double-click handling for images within the preview iframe to open the image editing modal.
  - Added synchronization between the image editing modal and the preview iframe for real-time updates of image properties (width, alignment, float, margin, border-radius).
  - Added visual hover effects to clearly indicate editable images in preview mode.

- **Template Builder - CORS Image Proxy:**
  - Fixed thumbnail generation failing silently when templates contain external images from domains without CORS headers.
  - Implemented server-side image proxy (`api.templates.proxy-image`) that fetches external images and returns them with proper CORS headers.
  - Updated `Builder.vue` to automatically route external images through the proxy during thumbnail generation.
  - Added security measures: MIME type validation, file size limits (5MB), blocked local/internal URLs, and response caching (1 hour).
  - **Enhanced Reliability:** Added retry logic (2 retries), browser-like User-Agent/Referer headers, and improved error logging to resolve 502 Bad Gateway errors with strict external servers (e.g., WordPress).

## [1.7.5] – Short Description

**Release date:** 2026-01-17

### Added

- **MCP Remote Connection:**
  - **Remote Support:** Added capability to connect to remote NetSendo instances using `--url` and `--api-key` CLI arguments.
  - **Auto-Configuration:** New Artisan command `mcp:config` generates ready-to-use configuration for both local Docker and remote setups (detects environment automatically).
  - **Marketplace UI:** Updated `/marketplace/mcp` page with a tabbed interface offering tailored installation instructions for "Remote (npx)" and "Local (Docker)" workflows.
  - **Public Package:** Published `@netsendo/mcp-client` to npm registry for simplified one-command usage via `npx`.

- **MCP Server (Model Context Protocol):**
  - **AI Integration:** Implemented a full-featured MCP server allowing AI assistants (Claude, Cursor, VS Code) to interact directly with NetSendo.
  - **Core Capabilities:**
    - **16 Tools:** Manage subscribers (create/update/delete), contact lists, tags, send emails, send SMS, check status.
    - **2 Resources:** `netsendo://info` (instance capabilities) and `netsendo://stats` (quick dashboard overview).
    - **3 Prompts:** Pre-built AI workflows for `analyze_subscribers`, `send_newsletter`, and `cleanup_list`.
  - **Docker Integration:** Added dedicated `mcp` service to `docker-compose.yml` for seamless deployment.
  - **Documentation:**
    - Comprehensive `docs/mcp-server.md` user guide.
    - Technical `mcp/README.md` for developers.
    - Example configurations for Claude Desktop and Cursor IDE.
  - **Security:** Private API key authentication with standard NetSendo permissions.

- **MCP Status Indicator:**
  - **Visual Status:** Added a status indicator to the top navigation bar showing the current state of the MCP connection (Connected, Disconnected, or Not Configured).
  - **Connection Testing:** Implemented automated daily connection tests via `mcp:test-connection` Artisan command and a manual "Test Now" button in the UI.
  - **Database Tracking:** Added `mcp_status` table to store connection test history, version information, and API accessibility status.
  - **User Interface:** Created `McpStatusIndicator` Vue component with a detailed dropdown menu showing connection details, version, and last test time.
  - **Localization:** Full translations for MCP status messages and UI elements in EN, PL, DE, ES.

## [1.7.4] – Short Description

**Release date:** 2026-01-17

### Added

- **Global Search System:**
  - **Command Palette Interface:** Implemented a professional, slide-out search panel inspired by modern "Command Palette" interfaces (Raycast/Spotlight).
  - **Universal Access:** Activated via a compact search icon in the top navigation or keyboard shortcut `Cmd+K` (Mac) / `Ctrl+K` (Windows/Linux).
  - **Multi-Resource Search:** Intelligent search across 8 key areas:
    - **Contacts:** Search by name, email, phone.
    - **Companies:** Search by name, NIP, domain.
    - **Tasks:** Search by title, description.
    - **Messages & Media:** Search email/SMS subjects and media filenames/tags.
    - **Subscribers, Lists, Webinars:** Quick access to marketing assets.
  - **Smart Features:**
    - **Category Filtering:** Filter results by specific resource type with clickable chips.
    - **Search History:** Remembers last 5 search queries for quick access.
    - **Keyboard Navigation:** Full support for arrow keys (↑/↓) and Enter to navigate results without a mouse.
  - **Backend Performance:** Optimized `GlobalSearchController` with user-scoped queries and limits.
  - **Localization:** Full translations in PL.

- **CRM Tasks - Advanced Creation Flow:**
  - Implemented `TaskModal.vue`, a comprehensive modal for creating and editing CRM tasks with full support for task types (Call, Email, Meeting, Task, Follow-up), priorities, due dates, and descriptions.
  - Added "Add Task" button to the CRM Tasks dashboard (`/crm/tasks`) header for quick task creation.
  - Enhanced Contact Profile (`/crm/contacts/{id}`) with a direct "Task" button in the header and a dedicated "Add Task" button within the tasks section (which is now always visible).
  - Integrated `TaskModal` into both the Tasks dashboard and Contact Profile for a seamless user experience.

- **Signature Editor - Unification with Advanced Editor:**
  - Added comprehensive toolbar controls to `SignatureEditor.vue` (used for signatures and inserts) effectively mirroring `AdvancedEditor.vue`.
  - Added new formatting options: Strikethrough, Highlight color, Text Transform (uppercase/lowercase/capitalize), Headings (H1-H3), Blockquote, Code Block, Horizontal Rule.
  - Added full List support: Bullet Lists, Ordered Lists, and Indent/Outdent actions.
  - Added Emoji Picker with categorized selection.

- **Signature Editor - Advanced Image Management:**
  - Replaced the simple image URL input with the full-featured Image Modal from `AdvancedEditor`.
  - **Media Browser:** Direct access to Media Library for selecting images and logos.
  - **Direct Upload:** Drag-and-drop image upload capability directly within the editor.
  - **Advanced Styling:** Added controls for Image Float (text wrapping), Margin, Border Radius, and Image Linking.
  - Preserved image resizing capabilities.

### Fixed

- **Global Search:**
  - Fixed `500 Server Error` caused by invalid column references (`messages.name`, `webinars.title`) and optimized search query scoping.

- **Media Browser Integration:**
  - Fixed an issue in `SignatureEditor.vue` where the media browser was attempting to use an incorrect API endpoint structure.
  - Updated `openMediaBrowser` and `openLogoBrowser` to use the correct `media.search` route and response format, ensuring consistent behavior with `AdvancedEditor`.

- **Tracked Links - Duplicate URL Handling:**
  - Fixed `UniqueConstraintViolationException` when saving tracked links that contain duplicate URLs (e.g., when pasting content from Word).
  - Updated `MessageController` to use `updateOrCreate` for tracked links to handle duplicates gracefully.

- **WYSIWYG Editor - Insert/Signature Compatibility:**
  - Fixed issue where inserting signatures or inserts containing tables into email messages would switch the editor from WYSIWYG mode to HTML/preview mode, losing visual editing capability.
  - Updated `isFullHtmlDocument` detection in `AdvancedEditor.vue` to NOT treat simple tables as full HTML documents.
  - Tables created in the Signature/Insert editor are now fully editable in the Message editor.

### Added

- **WYSIWYG Editor - Table Support:**
  - Added table support to `AdvancedEditor.vue` (used for email messages) to match `SignatureEditor.vue` functionality.
  - New "Insert Table" button in the toolbar creates a 3x3 table with header row.
  - Table editing controls appear when a table is selected: add/delete rows, add/delete columns, merge cells, delete table.
  - Full table styling for both light and dark modes.

## [1.7.3] – Short Description

**Release date:** 2026-01-16

### Added

- **Global Date/Time Localization:**
  - Dates and times now display in the user's selected language format (en-US, de-DE, es-ES, pl-PL).
  - Updated `useDateTime.js` composable with automatic locale detection from i18n.
  - Added `formatCurrency` and `formatNumber` helpers for locale-aware number formatting.
  - Added locale-aware relative time strings ("just now", "5 minutes ago", etc.) for all 4 languages.
  - Added localized greeting messages (Good morning/afternoon/evening) for all 4 languages.
  - Updated 24+ components: CRM Dashboard, Tasks, Companies, Contacts, Media, Webinars, Forms, Subscriber tabs, Profit/Affiliate, Partner, Funnels, Settings/Backup.

- **WYSIWYG Editor - List Indentation:**
  - Added "Increase Indent" and "Decrease Indent" buttons to the editor toolbar.
  - Implemented keyboard shortcuts for list indentation (Tab / Shift+Tab).
  - Updated list icons for better visibility.
  - Added translations for indentation actions in PL, EN, DE, ES.

- **Iterative Image Compression:**
  - Implemented smart iterative compression algorithm that automatically adjusts image quality and dimensions to ensure uploaded images are within the 10MB server limit.
  - Added intelligent retry logic that progressively reduces quality (down to 0.30) and scales down image (down to 35%) if necessary.
  - Added user feedback mechanism to alert when files cannot be compressed enough to meet the 10MB limit.
  - Added `files_too_large` translations in PL, EN, DE, ES.

- **Email Image Processing:**
  - Implemented `EmailImageService` to automatically convert images with `img_to_b64` class to inline base64 enabled images.
  - Updated `SendEmailJob` to process inline images before sending, improving compatibility with email clients like Onet.
  - Added configuration options in `netsendo.php` for controlling inline image conversion active state, maximum size limit (default 500KB), and fetch timeout (default 10s).

- **User Time Format Preference:**
  - Implemented user setting for preferred time format (24-hour vs 12-hour with AM/PM).
  - Added new "Time Format" dropdown in Profile Information settings.
  - Updates all time displays across the application (Dashboard, Lists, Tables, Template Builder) to respect the user's choice.
  - Full translations for new settings in PL, EN, DE, ES.

### Changed

- **Locale-Aware Formatting:**
  - Standardized currency formatting across the entire application to use locale-aware `Intl.NumberFormat`.
  - Updated key components: `ProductPickerModal`, `WebinarProductPanel`, `BlockEditor`, `Funnels/Stats`, `Crm/Contacts/Show`, `Settings/Backup`, `Webinars/Analytics`, and all `Profit/Affiliate` & `Partner` views.
  - Improved display of prices and monetary values consistent with user's selected language.

### Fixed

- **WYSIWYG List Formatting:**
  - Fixed issue where bullet points and numbered lists were not displaying correctly due to missing CSS styles.
  - Added explicit list styling to `TiptapEditor`, `AdvancedEditor`, and `SignatureEditor`.
  - Fixed email export to include inline styles for lists, ensuring correct rendering in email clients.

- **Media Upload:**
  - Fixed 422 error when uploading images that undergo client-side compression.
  - Updated validation logic in `MediaController` to use `mimetypes` instead of `mimes`, ensuring correct type detection for Blob-created files (canvas compression output).

## [1.7.2] – Short Description

**Release date:** 2026-01-16

### Added

- **Image Auto-Compression:**
  - **Client-Side Compression:** Implemented automatic compression for images larger than 1MB using Canvas API before upload.
  - **Smart Resizing:** Automatically resizes large images to max 2048px dimensions while preserving aspect ratio.
  - **UI Feedback:** Added real-time compression progress bar and stats showing saved storage space.
  - **Optimization:** Reduces server load and upload bandwidth usage by processing images in the browser.
  - **Localization:** Full translations in PL, EN, DE, ES.

- **Company Data Lookup (Poland):**
  - **Automatic Data Retrieval:** Implemented automatic company data fetching for Polish companies using NIP or REGON numbers.
  - **Biała Lista VAT Integration:** Integrated with the official Ministry of Finance "Biała Lista VAT" API (mf.gov.pl) for accurate and free data retrieval.
  - **CRM Integration:** Added lookup functionality directly to Company Create and Edit forms.
  - **Smart Form Filling:** Automatically populates Company Name, Address, City, Postal Code, and VAT Status based on fetched data.
  - **Validation:** Added real-time validation for NIP (10 digits + checksum) and REGON (9/14 digits) formats.
  - **Backend:** New `PolishCompanyLookupService`, `CompanyLookupController`, and `/crm/companies/lookup` endpoint.
  - **Database:** Added `country`, `nip`, `regon` columns to `crm_companies` table.
  - **Localization:** Full translations for lookup features in PL, EN, DE, ES.

- **Configurable Bounce Management:**
  - Users can now configure the **soft bounce threshold** (number of soft bounces before marking as bounced, default: 3).
  - Users can choose the **bounce scope** - whether to apply bounce status per-list (recommended) or globally on the subscriber.
  - New settings available in Mailing List Edit, Create, and Default Settings pages.

- **Delete Unconfirmed Addresses:**
  - Implemented automatic deletion of unconfirmed subscribers after a configurable number of days.
  - New `delete_unconfirmed_after_days` setting in mailing list subscription settings (default: 7 days).
  - Added UI input field for configuring the retention period in Edit, Create, and Default Settings pages.
  - Backend logic added to `CronScheduleService::runDailyMaintenance()` for daily cleanup.
  - Full translations in PL and EN.

- **Email Funnels - Enhanced Visual Builder:**
  - Added 4 new step types: **SMS** (160 char limit), **Wait Until** (specific date/time), **Goal** (conversion tracking), **Split** (A/B testing).
  - Implemented **Undo/Redo** functionality (Ctrl+Z/Y) with 50-state history.
  - Added **Zoom controls** (zoom in/out/fit) and canvas toolbar.
  - Keyboard shortcuts: Delete node, Escape to deselect.
  - Configuration panels for all 10 step types.

- **Email Funnels - A/B Testing System:**
  - New database tables: `funnel_ab_tests`, `funnel_ab_variants`, `funnel_ab_enrollments`.
  - `ABTestService` with weighted random distribution algorithm for variant selection.
  - Auto-winner detection (10% lift threshold, 30 min samples per variant).
  - `executeSplitStep()` and `executeGoalStep()` handlers in `FunnelExecutionService`.
  - Conversion tracking for Goal steps records to active A/B tests.

- **Email Funnels - Advanced Analytics:**
  - Enhanced Stats page with tabbed interface (Overview / Steps / A/B Tests).
  - Step-by-step conversion rates and drop-off analysis.
  - Time-to-completion metrics (avg/min/max/median).
  - A/B test performance dashboard with variant comparison.

- **Email Funnels - Template System:**
  - New `funnel_templates` table with 8 categories (welcome, reengagement, launch, cart_abandonment, webinar, onboarding, sales, custom).
  - `FunnelTemplateService` for export/import functionality.
  - 3 pre-built system templates: Welcome Sequence, Re-engagement, Product Launch.
  - `TemplateGallery.vue` modal component with category filtering.
  - Routes: `/funnel-templates` gallery, `/funnels/{id}/export-template`.

- **Email Funnels - Subscriber Management:**
  - New **Subscriber Management** tab in Funnel Stats with filtering and pagination.
  - Ability to manually **Pause/Resume** subscriber progression.
  - **Advance/Rewind** functionality to manually move subscribers between steps.
  - **Remove** subscriber from funnel action.
  - `FunnelSubscribersController` with comprehensive API endpoints.

- **Email Funnels - Goal Tracking:**
  - Dedicated **Goals** tab in Stats with revenue dashboard.
  - **Revenue Tracking:** Calculate and display total revenue generated by funnel.
  - **Goal Conversions:** Track specific goal steps (Purchase, Signup, Custom) with value.
  - **Webhook Support:** New endpoint `/api/funnel/goal/convert` for external goal conversions (e.g., from Stripe/WooCommerce).
  - Breakdown of conversions by funnel step and source.

- **Email Funnels - Enhanced Webhooks:**
  - **Retry Logic:** Automatic retries (3 attempts) with exponential backoff for failed webhooks.
  - **Variable Substitution:** Support for dynamic placeholders (e.g., `{{subscriber.email}}`, `{{funnel.name}}`) in webhook payload.
  - **Custom Headers:** Support for custom HTTP headers and authentication (API Key, Basic Auth).
  - **Response Handling:** Option to store webhook responses for conditional logic.
  - Support for all standard HTTP methods (POST, GET, PUT, PATCH, DELETE).

- **Email Funnels - Testing Suite:**
  - Added comprehensive **Feature Tests** for Funnel Controller (CRUD, security, validation).
  - Added **Unit Tests** for `FunnelExecutionService` covering global logic, conditions, and actions.
  - Added **Unit Tests** for `WebhookService` verifying retry logic and payload construction.
  - Configured test environment for isolated execution.

### Fixed

- **System Emails:** Fixed missing welcome email for new subscribers when double opt-in is disabled.
  - Added new `subscription_welcome` system email template.
  - New subscribers without double opt-in now receive a welcome email immediately after signup.
  - Resubscribers (already active) continue to receive `already_active_resubscribe`.
  - Resubscribers (previously inactive/unsubscribed) continue to receive `inactive_resubscribe`.

- **Bounce Management:** Fixed the bounce analysis feature to properly function according to list settings.
  - Bounce status is now applied per-list (in `contact_list_subscriber` pivot table) instead of globally on the subscriber.
  - The `bounce_analysis` setting on each mailing list is now respected - bounces are only processed for lists with this setting enabled.
  - Soft bounces now increment a counter and mark the subscriber as bounced only after 3 soft bounces (previously ignored).
  - Hard bounces continue to immediately mark the subscriber as bounced.
  - Added `soft_bounce_count` column to track soft bounce occurrences per subscriber-list relationship.

## [1.7.1] – Short Description

**Release date:** 2026-01-15

### Added

- **International Names Support:**
  - Added full vocative case support for international names (US, UK, DE, FR, IT, ES, CZ, SK).
  - Implemented specific vocative mappings for **Czech (CZ)** names (e.g., Jan -> Jane).
  - Configured default vocative behavior (Nominative = Vocative) for other supported languages.
  - Added migration `fill_missing_vocatives` to backfill missing data for existing names.

### Added

- **CRM Sales Automation System:**
  - **Triggers:** Implemented 5 new CRM triggers: Deal Stage Changed, Deal Won, Deal Created, Task Completed, and Contact Created.
  - **Actions:** Added comprehensive CRM actions including Create Task, Update Score, Update Deal Stage, Assign Owner, Convert to Contact, and Log Activity.
  - **Conditions:** Added logic for evaluating CRM-specific conditions (Pipeline Stage, Deal Value, Score Threshold, Contact Status, Idle Days).
  - **Idle Deal Detection:** Created `crm:process-idle-deals` scheduled job to detect and trigger automations for deals inactive for X days.
  - **UI Integration:** Extended Automation Builder with dedicated configuration components for all new CRM triggers and actions.
  - **Testing:** Added `CrmAutomationTriggerTest` covering 8 key scenarios for CRM automation logic.
  - **Localization:** Full translations in PL, EN, DE, ES for all CRM automation features (+160 new keys).

- **CRM Email Sending:**
  - **Direct Email:** Implemented "Send Email" functionality directly from CRM Contact profile page.
  - **Composer Modal:** New rich modal interface for composing emails with subject and body.
  - **Mailbox Selection:** Ability to choose sender identity (Mailbox) if multiple are available.
  - **Activity Tracking:** Automatically logs sent emails to the contact's activity timeline.
  - **Backend:** New `sendEmail` endpoint in `CrmContactController` utilizing `MailProviderService`.

- **Automatic Gender Detection:**
  - **CSV Import:** Implemented automatic gender detection during subscriber import from CSV files. If gender is missing, it's inferred from the first name.
  - **API Support:** Added automatic gender detection to Subscriber API endpoints (`POST /api/v1/subscribers` and `POST /api/v1/subscribers/batch`).

- **Test Message Personalization:**
  - Implemented automatic subscriber lookup by email address when sending test messages.
  - Test emails now support dynamic placeholders like `{{male|female}}` (gender forms) and `[[!fname]]` (vocative) when the recipient email exists in the subscriber database.

### Fixed

- **Name Database:** Fixed missing vocative forms (e.g., `[[!fname]]` returning original name instead of vocative) on production environments by adding a missing migration for `PolishNamesSeeder`.

### Improved

- **System Email Editor & UI:**
  - **Interactive Placeholders:** Replaced static codes with a functional toolbar in System Email editor. Users can now click to insert variables into content/subject or copy to clipboard.
  - **Dark Mode Contrast:** Fixed visibility issues for input fields (`TextInput`) and country search (`PhoneInput`) in dark mode by adjusting background and text colors.
  - **Vocative Placeholder:** Added documented support for `[[!fname]]` (vocative form) in the system email editor.

## [1.7.0] – CRM Module & Kanban Enhancements

**Release date:** 2026-01-15

### Added

- **CRM Module (Sales):**
  - **Core System:** Complete CRM implementation with Companies, Contacts, Deals, Pipelines, Tasks, and Activities.
  - **Kanban Board:** Interactive drag-and-drop Deal management with customizable pipelines and stages.
  - **Contact & Company Profiles:** Deep integration with NetSendo Subscribers, activity timelines, notes, and task associations.
  - **Task Management:** Dedicated task views (Overdue, Today, Upcoming) with filtering and entity linking.
  - **CSV Import:** Built-in importer with column mapping, preview, and deduplication logic.
  - **UI/UX:** Premium high-performance Vue 3 interface with "CRM Sales" sidebar section.
  - **Backend:** 7 new Eloquent models, polymorphic activity tracking, and optimized database schema.

- **Kanban Board Visual Feedback:**
  - Added visual highlighting when dragging deals over columns in the Kanban board.
  - Columns now display indigo ring and background when a deal hovers over them.
  - Prevents highlighting when dragging over the deal's current column.
  - Smooth transition animations for better user experience.

- **Message ID Search:**
  - Extended search functionality in email and SMS message lists to support searching by message ID in addition to subject.
  - Updated `MessageController.php` and `SmsController.php` to include ID in search query.

- **Searchable List Filter:**
  - Replaced static dropdown with searchable list picker in email and SMS message filters.
  - Users can now filter lists by name or ID, making it easier to find specific lists when managing many.
  - Added list ID display (#ID) next to each list name in the dropdown.
  - Full translations in PL, EN, DE, ES.

- **WYSIWYG Editor - Image Resize Drag Handles:**
  - Added drag-to-resize functionality for images in the WYSIWYG editor.
  - Users can click on an image to select it and drag the corner handles to resize proportionally.
  - Width percentage indicator displayed during resize.
  - Double-click on image opens the edit modal with current settings (synced with drag-resized width).
  - Implemented custom `ResizableImageView` NodeView component with full CSS styling for resize handles.

- **WYSIWYG Editor - Text Case Formatting:**
  - Added text-transform functionality (uppercase, lowercase, capitalize) to the WYSIWYG editor.
  - New toolbar button with dropdown menu for selecting text case options.
  - Custom `TextTransform` Tiptap extension using CSS `text-transform` property.
  - Full translations in PL, EN, DE, ES.

- **WYSIWYG Editor - Font Size Support:**
  - Enhanced font size picker to display the currently selected size directly on the toolbar button.
  - Added "Default" option to easily reset font size to the default value.
  - Improved visual feedback with highlighting for the active font size in the dropdown.
  - Added translations for the new "Default" option in PL, EN, DE, ES.

- **SMS Test Send:**
  - Added "Send Test SMS" button to SMS campaign creation page, mirroring the existing email test functionality.
  - New modal interface for entering test phone number with content preview.
  - Backend `SmsController@test` method with placeholder substitution using sample data when no subscriber is selected.
  - Detailed logging for successful sends and errors.
  - Full translations in PL, EN, DE, ES.

- **CRM Automation & Events:**
  - Implemented event-driven architecture for CRM: `CrmDealStageChanged`, `CrmTaskOverdue`, `CrmContactReplied`.
  - Added `CrmEventListener` to trigger automations and notifications based on CRM activities.
  - New Artisan command `crm:check-overdue-tasks` for detecting and notifying about overdue tasks.
  - Added `overdue_notified` flag to `crm_tasks` table to prevent duplicate notifications.
  - Full translations for CRM module in all supported languages (PL, EN, ES).

### Fixed

- **CRM Kanban Drag-and-Drop:**
  - Fixed "All Inertia requests must receive a valid Inertia response, however a plain JSON response was received" error when dragging deals between columns.
  - Changed `CrmDealController@updateStage` to return `RedirectResponse` instead of `JsonResponse` for proper Inertia compatibility.

- **CRM Module Fixes:**
  - Fixed 500 Internal Server Error in CRM controllers caused by column name mismatch (`admin_id` replaced with `admin_user_id`).
  - Fixed "Page not found" errors by creating missing Vue pages: `Deals/Index.vue` (Kanban board), `Companies/Create.vue`, `Companies/Show.vue`, and `Companies/Edit.vue`.
  - Fixed sidebar navigation to use valid route names for correct active state detection.
  - Fixed `UniqueConstraintViolationException` when creating a CRM contact for an existing subscriber email by using `firstOrCreate` logic to prevent duplicates.

- **Media Library - Bulk Upload 500 Error:**
  - Fixed critical 500 Internal Server Error when uploading images to the Media Library on servers without PHP GD extension.
  - Added `function_exists()` checks for all GD functions (`imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromgif`, `imagecreatefromwebp`, `imagesx`, `imagesy`, `imagecolorat`, `imagedestroy`) in `ColorExtractionService.php`.
  - Image uploads now work gracefully without GD extension - color extraction is simply skipped if GD is unavailable.

- **AI Assistant - Microphone Support:**
  - Fixed `TypeError: i(...) is not a function` when using voice dictation in AI Assistants (Message, Subject, SMS, Template Builder).
  - Updated `useSpeechRecognition.js` composable to correctly export `toggleListening` function and `interimTranscript` ref, which were missing but expected by consumer components.

### Changed

- **Documentation:**
  - Added PHP GD extension to README.md requirements section with note explaining it's optional for color extraction feature.

## [1.6.9] – Short Description

**Release date:** 2026-01-14

### Added

- **Enterprise Media Library:**
  - **Media Library Page (`/media`):** Centralized asset management with drag-and-drop upload, filtering by brand/type/folder, search functionality, bulk selection, and grid display.
  - **Brand Management Page (`/brands`):** Create and manage brands with logos, descriptions, and color palettes.
  - **Automatic Color Extraction:** Native GD library k-means clustering algorithm extracts 8 dominant colors from uploaded images automatically.
  - **WYSIWYG Components (Prepared):**
    - `MediaBrowser.vue`: Modal component for selecting images from the library within the WYSIWYG editor.
    - `ColorPalettePicker.vue`: Color picker with tabs for brand colors, media-extracted colors, and custom color input.
  - **Database Schema:** New tables: `media_folders`, `brands`, `media`, `media_colors`, `brand_palettes`.
  - **Models:** `Brand`, `Media`, `MediaColor`, `MediaFolder`, `BrandPalette` with full Eloquent relationships.
  - **Controllers:** `MediaController`, `BrandController`, `MediaFolderController` with CRUD and color extraction.
  - **Services:** `ColorExtractionService` with k-means algorithm for color detection.
  - **Authorization:** `MediaPolicy` and `BrandPolicy` for user-scoped access control.
  - **Navigation:** Added "Media Library" group to sidebar with links to Media and Brands pages.
  - **Translations:** Full localization in EN, PL, DE, ES for all media, brands, and colors features.

  - **Media Library Enhancements:**
    - **Detailed View:** Added dedicated media view page (`/media/{id}`) displaying full image preview, metadata (size, dimensions, type), and extracted color palette.
    - **Type Management:** implemented functionality to change media type (Image, Logo, Icon, Document) and update Alt Text directly from the detailed view.
    - **Upload Improvements:** Fixed 500 Internal Server Error during bulk upload by resolving GD library namespace conflicts. Added better error handling and automatic page reload on success.

  - **WYSIWYG Editor - Media Integration:**
    - **Browse Media Library:** Added "Browse media library" button to the image insertion modal.
    - **Logo Selection:** Added specialized "Insert logo from library" button that filters the view to show only media items marked as "Logo".
    - **Visual Browser:** efficient grid-based media selection modal directly within the editor interface.

- **List Management - View Subscribers Action:**
  - Added "View Subscribers" button to Email and SMS list actions (Grid and Table views), allowing direct navigation to the filtered subscriber list.
  - Added translations for the new action in PL, EN, DE, ES.

### Fixed

- **WYSIWYG Editor - Image Style Options:**
  - Fixed issue where image formatting options (width, alignment, float, margin, border-radius) were not being preserved after saving.
  - Created custom `CustomImage` extension for Tiptap that properly preserves inline `style` attribute during HTML parsing/serialization.
  - Applied fix to both `AdvancedEditor.vue` and `SignatureEditor.vue`.
  - Removed CSS override that was forcing default `border-radius` on all images.

- **Template Builder - Image Upload 404 on Production (Docker):**
  - Fixed 404 errors when accessing uploaded images in Template Builder (`/storage/templates/images/*` not found).
  - **Root cause:** Docker uses separate volumes for `public` and `storage/app` - symlinks between volumes don't work.
  - **Solution:**
    - Updated `docker/nginx/default.conf` with `/storage` location block using `alias` directive.
    - Updated `docker-compose.yml` to mount `netsendo-storage` volume to nginx webserver (read-only).
  - Added automatic storage symlink creation in `AppServiceProvider` for non-Docker environments.
  - Added automatic directory creation for `templates/images` and `templates/thumbnails`.
  - Added `storage:link --force` to composer setup script.
  - **Upgrade:** See `DOCKER_INSTALL.md` for manual update instructions if not using `git pull`.

## [1.6.8] – Short Description

**Release date:** 2026-01-13

### Added

- **WYSIWYG Editor - Enhanced Link Editing:**
  - Link editing modal (`AdvancedEditor.vue`) now includes a **Link Text** field alongside the URL field, allowing users to modify both the display text and the destination URL.
  - **Extended Link Options:** Added **Title** field (for tooltips/accessibility) and **Target** dropdown (Same window / New window) - matching previous NetSendo functionality.
  - **Click-to-Edit Links:** Clicking on any link in the editor now opens the edit modal with pre-filled values, allowing quick modifications.
  - Selected text is automatically pre-filled in the text field when opening the modal.
  - Updated translations for link editing in PL, EN, DE, ES.

- **WYSIWYG Editor - Image Upload & Advanced Formatting:**
  - **Direct Image Upload:** Added file upload support to the image modal with drag-and-drop zone, allowing users to upload images directly to NetSendo storage instead of only pasting external URLs.
  - **Click-to-Edit Images:** Clicking on any image in the editor opens the edit modal with current settings pre-loaded, allowing easy resizing and reformatting.
  - **Text Wrapping (Float):** New option to set image float (None, Left, Right) for text wrapping around images.
  - **Margin Control:** Added slider to control image margin (0-50px).
  - **Border Radius:** Added slider to control image border-radius (0-50px) for rounded corners.
  - Visual feedback with hover outline on clickable images.
  - Client-side validation for file size (max 5MB) and format (JPG, PNG, GIF, WEBP).
  - Updated translations for all new image features in PL, EN, DE, ES.

### Fixed

- **Template Builder - Link Click Prevention:**
  - Fixed issue where clicking on links within text blocks in the Template Builder canvas (`BuilderCanvas.vue`) would navigate to the link URL instead of allowing editing.
  - Added CSS to disable pointer events on anchor tags within text content in edit mode.

- **Template Builder - WooCommerce Product Visibility:**
  - **Dark Mode Support:** Fixed visibility issues where product titles and prices were white/invisible on white product block backgrounds when the app was in Dark Mode.
  - **Product Grid:** Fixed "Product Grid" blocks turning dark gray in the editor when in Dark Mode, ensuring they remain white to match the email canvas for proper contrast.
  - **Preview Panel:** Updated MJML preview generation to correctly render product blocks with dynamic background and text colors, respecting the selected Light/Dark preview mode.

- **WordPress Plugin - Pixel User ID Configuration (v1.1.1):**
  - Fixed critical issue where "User ID not set" warning persisted after successful API connection test.
  - Updated `ajax_test_connection` to accept `api_url` and `api_key` from form fields, enabling testing before saving.
  - Modified `NetSendo_WP_API` constructor to accept optional parameters for on-the-fly testing.
  - Enhanced `save_user_id` to also persist `api_url` and `api_key` after successful test, auto-saving settings.
  - Updated JavaScript to pass current form values during test and dynamically update Pixel status UI when `user_id` is received.

- **WooCommerce Plugin - Pixel User ID Configuration (v1.1.1):**
  - Applied identical fixes to WooCommerce plugin for consistent behavior.
  - Updated `NetSendo_WC_API` constructor, `ajax_test_connection`, and `save_user_id` methods.
  - Updated JavaScript to send form values during connection test.

- **A/B Testing - Draft Saving:**
  - Fixed critical issue where A/B test variants were not being saved when saving a message as a draft.
  - Implemented `ab_test_config` validation and processing in `MessageController`.
  - Added `syncAbTest` method to correctly synchronize A/B test configuration and variants with the database during save/update operations.
  - Updated `edit` method to correctly load existing A/B test configuration when editing a message.

## [1.6.7] – Short Description

**Release date:** 2026-01-13

### Added

- **WooCommerce Product Variants Support:**
  - **Backend:**
    - Extended `WooCommerceApiService` to fetch and cache product variations.
    - Updated `TemplateProductsController` with new endpoint `getProductVariations` for fetching variations.
    - Added support for variant data structure (price ranges, attributes, image overrides) in API responses.

  - **Frontend (Template Builder):**
    - **Variable Product Support:** Product Picker now identifies variable products with a specific badge and variant count.
    - **Variant Selection:** Added UI to expand variable products in the picker and select individual variants or the parent product.
    - **Block Editor Integration:** Product blocks now display selected variant attributes (e.g., Size: XL, Color: Red).
    - **Preview Rendering:** Email preview now correctly renders selected variant attributes with styled tags.

  - **Translations:**
    - Added full translations for all variant-related features in PL, EN, DE, ES.

### Changed

- **Improved Statistics Display:**
  - **Enhanced Charts:**
    - Improved readability of "Effectiveness" and "Conversion Funnel" charts.
    - Added value labels (opens, clicks, etc.) directly on chart segments using `chartjs-plugin-datalabels` for better visibility without hovering.
    - Increased chart container height to prevent overflow and ensure legend visibility.
  - **Recent Activity Pagination & Sorting:**
    - Implemented full server-side pagination for "Recent Opens" and "Recent Clicks" lists (replacing the previous 20-item limit).
    - Added column sorting functionality (by Email, Time, and URL) for both activity lists.
    - Added sort direction indicators (⇅).
    - Made URLs in the "Recent Clicks" list clickable (opens in new tab) for easier access.
  - **UI/UX Improvements:**
    - Fixed "Previous" pagination button color to be visible in dark mode (was black on dark background).
    - Optimized table layouts for better responsiveness.
    - Added missing translations for Recipient List columns and statuses in Statistics view (EN, DE, ES).

## [1.6.6] – Short Description

**Release date:** 2026-01-13

### Added

- **Full HTML Visual Editing:**
  - **Text Editing:** Implemented direct text editing for full HTML templates in visual mode with click-to-edit functionality.
  - **Modal Interface:** Added text editing modal (`AdvancedEditor.vue`) with textarea and variable insertion support.
  - **UX Improvements:** Added hover highlights for editable elements and auto-scroll to element after saving.
  - **Translations:** Added translations for text editing features in PL, EN, DE, ES.

- **A/B Testing System:**
  - **Enterprise-Grade Testing:** Implemented a comprehensive A/B testing solution for email marketing.
  - **Multi-Variant Support:** Support for up to 5 variants (A-E) per test, exceeding industry standards.
  - **Flexible Test Types:** Test different Subjects, Preheaders, Content, Sender Names, or Send Times.
  - **Advanced Configuration:**
    - Configurable sample size (5-50%).
    - Automatic winner selection based on Open Rate, Click Rate, or Conversion Rate.
    - Configurable test duration (1-72 hours).
    - Statistical confidence threshold settings (80-99%).
  - **Backend Architecture:**
    - New `ab_tests` and `ab_test_variants` tables.
    - `AbTestService` for lifecycle management (start, pause, resume, complete).
    - `AbTestStatisticsService` using Bayesian and Frequentist (Z-test) methods for result calculation.
    - `ProcessAbTestsJob` scheduled job (every 5 mins) for automatic winner evaluation.
  - **Frontend:**
    - `ABTestingPanel.vue` fully integrated into the Message Creator.
    - Real-time validation and variant management.
  - **Localization:** Full translations in EN, PL, DE, and ES.

- **Message List A/B Test Indicator:**
  - Added visual badge indicator (🧪) on the Messages list page showing when a message has an associated A/B test.
  - Badge displays with color-coded status: purple (running with animated pulse), amber (draft/paused), green (completed), gray (cancelled).
  - Hover tooltip shows the current A/B test status.
  - Added `abTest` hasOne relation to Message model.
  - Updated `MessageController` to eager-load A/B test data for the message index view.
  - Translations in EN, PL, DE, and ES.

### Changed

- **A/B Testing Panel:**
  - **Control Variant Locking:** The Control Variant (A) is now read-only and mirrors the main message content to ensure consistency. Added warning indicators when main content is empty.
  - **AI Integration:** Added AI Assistant support for all non-control variants. The AI prompt now accepts the control variant's content to generate context-aware alternatives.

### Fixed

- **Scheduler:** Fixed `RuntimeException` caused by using `runInBackground()` with `Schedule::job()`.
- **ABTestingPanel:** Fixed infinite recursion loop in watcher that caused performance degradation on the message creation page.
- **API Connection:** Fixed `404 Not Found` error during connection testing in WordPress and WooCommerce plugins. Implemented the missing `/api/v1/account` endpoint to return authenticated user details and valid `user_id` for Pixel tracking.
- **Template Builder - Product Grid:**
  - **Data Display:** Fixed issue where the "Product Grid" block in the editor showed placeholders instead of actual product data (image, title, price) when products were selected from WooCommerce.
  - **Column Layout:** Fixed the editor preview to correctly respect the column configuration (2, 3, or 4 columns) instead of defaulting to 2 columns.
  - **Email Preview:** Fixed broken layout for 4-column product grids in the preview panel and MJML generation.
  - **Visual Design:** Completely redesigned the "Product Grid" email template output to feature professional product cards with rounded corners, proper image sizing, truncated titles, clean pricing, and styled CTA buttons.

## [1.6.5] – Unique Subscriber Counting

**Release date:** 2026-01-12

### Added

- **NetSendo Pixel for WordPress Plugin:**
  - Implemented Pixel tracking script injection in WordPress plugin (`netsendo-wordpress.php`).
  - Added `page_view` tracking with page type detection (home, post, page, archive, search).
  - Added new "Pixel Tracking" settings section with `enable_pixel` toggle in admin panel.
  - Auto-retrieval of `user_id` from API during connection test for automatic Pixel configuration.
  - Collision detection constant `NETSENDO_PIXEL_LOADED` to prevent duplicate Pixel injection.
  - Info notice in settings when both WordPress and WooCommerce plugins are active.

- **Pixel Collision Detection (WooCommerce Plugin):**
  - Added `netsendo_wc_is_wordpress_plugin_handling_pixel()` function to detect if WordPress plugin is managing Pixel.
  - WooCommerce plugin now skips base Pixel injection when WordPress plugin handles it.
  - E-commerce tracking events (product_view, add_to_cart, checkout, purchase) continue to work regardless of which plugin injects the base Pixel.
  - Updated API `test_connection()` to use `/api/v1/account` endpoint and save `user_id` for Pixel.

### Changed

- **Plugin Architecture:**
  - WordPress plugin now acts as PRIMARY Pixel injector when both plugins are active.
  - WooCommerce plugin acts as SECONDARY, adding only e-commerce-specific events.
  - Both plugins now preserve `user_id` in settings sanitization.

### Fixed

- **CRM Subscriber Counting:**
  - Fixed issue where subscriber counts in Email/SMS lists included unsubscribed/removed users.
  - Updated counting logic to only include **unique active** subscribers (`status = 'active'`).
  - Affected areas: Mailing Lists view, SMS Lists view, API `subscribers_count`, and Dashboard Global Stats (`Total Subscribers`).

## [1.6.4] – Short Description

**Release date:** 2026-01-12

### Added

- **Automatic Gender Matching:**
  - **Feature:** New system to automatically detect and assign gender to subscribers based on their first name.
  - **Backend:**
    - `MatchSubscriberGendersJob`: Background job for bulk processing subscribers.
    - `GenderService`: Enhanced with `getMatchingPreview` and `matchGenderForAllSubscribers` methods.
    - `NameDatabaseController`: New endpoints for matching stats, running the job, and progress tracking.
    - `GenderMatchingCompleted`: Notification sent upon job completion.
  - **Frontend:**
    - New "Automatic Gender Matching" section in Name Database settings (`/settings/names`).
    - Preview modal showing matchable subscribers.
    - Progress bar for background job tracking.
    - Results modal with detailed statistics (matched, unmatched, errors).
  - **International Support:**
    - Added name database seeders for 8 additional countries (DE, CZ, SK, FR, IT, ES, UK, US).
    - Populated database with ~500 common first names for international gender detection.
  - **Translations:** Full support for EN and PL.

### Fixed

- **Message Preview:** Fixed 422 error when previewing messages with an empty subject line.
- **Placeholders:**
  - Added `[[fname]]` and `[[lname]]` aliases for `[[first_name]]` and `[[last_name]]` to ensure consistent behavior across the application.
  - Fixed issue where `[[!fname]]` (vocative) and other placeholders were not processed in the Preheader field for emails and test sends.
- **Template Products:** Fixed HTTP 500 error when refreshing product data in Template Builder by correcting the API route name in `BlockEditor.vue`.

## [1.6.3] – Plugin Version Tracking

**Release date:** 2026-01-12

### Added

- **Plugin Version & Update System:**
  - Implemented version tracking for WordPress and WooCommerce integrations.
  - New `plugin_connections` database table for storing plugin metadata (version, site URL, WP/WC versions).
  - New API endpoints:
    - `POST /api/v1/plugin/heartbeat`: Plugin heartbeat to report active status and version.
    - `GET /api/v1/plugin/check-version`: Endpoint for plugins to check for updates.
  - **Models:** Added `PluginConnection` model with `needsUpdate()` and `isStale()` logic.
  - **Backend:** Updated `WooCommerceIntegrationController` to verify plugin connectivity and versions.
  - **Frontend:**
    - Added plugin version badge to WooCommerce store cards in Settings.
    - Added "Update Available" notification (amber badge) when a new plugin version is released.
    - Added "Stale Connection" warning (red badge) if plugin hasn't communicated for >7 days.
  - **Translations:** Added full translations for plugin status messages in EN, PL.

- **Developer Experience:**
  - Added `UPDATE_GUIDE.md` for both WordPress and WooCommerce plugins with step-by-step update instructions.
  - Rebuilt plugin zip packages with heartbeat functionality.

## [1.6.2] – Tracked Links & Quick Actions

**Release date:** 2026-01-12

### Added

- **Quick Create Actions:**
  - Added "Create email" (envelope icon) and "Create SMS" (message icon) buttons to "Actions" column in Email and SMS List views.
  - Clicking the button automatically navigates to the message creator with the corresponding list pre-selected.
  - Supported in both Grid and Table views for seamless workflow.
  - Backend controllers (`MessageController`, `SmsController`) updated to handle `list_id` query parameter for pre-selection.

- **API User Data Passthrough:**
  - Added optional `ip_address`, `user_agent`, and `device` fields to Subscriber API (`POST /api/v1/subscribers`, `POST /api/v1/subscribers/batch`).
  - Added optional `client_ip` field to Pixel API (`POST /t/pixel/event`, `POST /t/pixel/batch`).
  - Enables passing real user data when API calls come from proxies (e.g., n8n, Zapier) instead of recording proxy server IP.
  - If fields are not provided, system falls back to automatically detected values from the HTTP request.

- **List ID in CRM Lists:**
  - Added "ID LISTY" column to Mailing Lists and SMS Lists table views for easier reference.
  - Enhanced search functionality in Mailing Lists and SMS Lists to support searching by exact List ID in addition to list name.

- **Tracked Links Feature:**
  - Implemented "Tracked Links" functionality for email messages, allowing per-link configuration.
  - **Features:**
    - Enable/disable tracking for individual links.
    - Share subscriber data with the destination URL (dynamic inserts).
    - Automatically subscribe users to selected mailing lists upon clicking a link.
    - Automatically unsubscribe users from selected mailing lists upon clicking a link.
  - **Frontend:**
    - New `TrackedLinksSection.vue` component integrated into the Message Creator (`Create.vue`).
    - Automatic detection of links in message content with real-time updates.
  - **Backend:**
    - New `message_tracked_links` table and `MessageTrackedLink` model.
    - Updated `MessageController`, `SendEmailJob`, and `TrackingController` to handle storage, conditional tracking, and click actions.
  - Full translations in PL, EN, DE, ES.

- **CRM List Sorting:**
  - Added sorting by "List ID" and "Subscribers" count in Email and SMS list views.

### Fixed

- Fixed pagination visibility in Email and SMS list views to allow navigating through all lists when count exceeds 12.

## [1.6.1] – Advanced Subscriber Card

**Release date:** 2026-01-11

### Added

- **Advanced Subscriber Card (Karta Subskrybenta):**
  - New comprehensive subscriber profile page accessible at `/subscribers/{id}` with a professional tabbed interface.
  - **Overview Tab:** Profile information, engagement score ring, tags, custom fields, and active subscriptions.
  - **Message History Tab:** Table of all messages sent to the subscriber with status, opens, and clicks.
  - **List History Tab:** Timeline visualization of subscription/unsubscription events across all lists.
  - **Pixel Data Tab:** Sub-tabs for page visits, custom events, and device information from NetSendo Pixel.
  - **Forms Tab:** History of all forms submitted by the subscriber.
  - **Activity Log Tab:** Comprehensive log of all subscriber-related activities.
  - **Quick Stats Row:** Key metrics displayed at the top (messages sent, open rate, click rate, engagement, active lists, devices).
  - Backend helper methods in `SubscriberController` for data aggregation (`getSubscriberStatistics`, `getListHistory`, `getMessageHistory`, `getPixelData`, `getFormSubmissions`, `getActivityLog`).
  - Full translations in PL, EN, DE, ES.

- **CRM Deletion Confirmations:**
  - Implemented secure deletion modals for Contact Groups, Mailing Lists, and Tags, preventing accidental data loss.
  - **Mailing Lists:** Deletion now supports transferring subscribers to ANY accessible list (previously limited to the current pagination page).
  - **Groups/Tags:** Added specific confirmation dialogs explaining the impact on related data (e.g., child groups, tagged items).

- **Pixel Custom Events Documentation:**
  - Expanded custom events help section on Pixel Settings page with comprehensive documentation.
  - Added visual overview of all supported event types (`page_view`, `product_view`, `add_to_cart`, `checkout_started`, `purchase`, `custom`).
  - Added copy-to-clipboard code examples for key tracking events.
  - Added documentation for `identify` command (linking anonymous visitors to known subscribers).
  - Added documentation for `debug` mode (enabling console logging for debugging).
  - Added reference table of available data fields (`product_id`, `product_name`, `product_price`, etc.).
  - Added translations for all new documentation text in PL, EN, DE, and ES.

- **WooCommerce Debugging:**
  - Added detailed error logging to `TemplateProductsController` for WooCommerce product and category fetch failures.
  - Added credentials validation in `WooCommerceApiService` to detect missing or corrupted API credentials before making requests.
  - Improved error messages when WooCommerce API calls fail (includes store_id, store_url, endpoint, and error details).

### Fixed

- **NetSendo Pixel Cross-Origin Tracking:**
  - Added `config/cors.php` with proper CORS configuration for `/t/pixel/*` endpoints, enabling pixel tracking from external websites.
  - Added `HandleCors` middleware to global middleware stack in `bootstrap/app.php`.
  - Fixed critical issue where `sendBeacon` requests from browsers were not being recorded despite curl requests working correctly.
  - Changed pixel JavaScript to use XHR as the primary request method instead of `sendBeacon` for better reliability.
  - `sendBeacon` is now only used as fallback for `beforeunload` events (page exit tracking).
  - Added debug logging to `PixelController::trackEvent()` and `batchEvents()` methods for easier troubleshooting.

- **NetSendo Pixel:** Fixed critical bug where pixel tracking was not working because POST endpoints (`/t/pixel/event`, `/t/pixel/batch`, `/t/pixel/identify`) were blocked by CSRF verification. Added `t/pixel/*` to CSRF exceptions in `bootstrap/app.php`.
- **CRM Deletion Logic:**
  - **Groups:** Fixed 500 server error when deleting a group with children or lists. Now safely moves child groups to the parent group and detaches lists to "Uncategorized" before deletion.
  - **Tags:** Fixed backend logic to safely detach tags from all associated contacts and lists before deletion.
  - **Tag UI:** Fixed invalid HTML nesting in `Tag/Index.vue` causing potential rendering issues.

## [1.6.0] – WooCommerce Multi-Store Support

**Release date:** 2026-01-11

### Added

- **Live Visitors (Real-Time Tracking):**
  - Real-time visitor tracking on Pixel Settings page using WebSockets (Laravel Reverb).
  - New `PixelVisitorActive` broadcast event for live visitor updates.
  - `LiveVisitorService` for Redis-based active visitor tracking with 5-minute TTL.
  - New `useEcho.js` composable for WebSocket connection management.
  - Live Visitors panel with animated visitor cards, device icons, and connection status.
  - Added Reverb container to Docker Compose (port 8085).
  - Full translations for live visitors feature in PL, EN, DE, ES.

- **Gender Personalization Placeholder:**
  - Added `{{męska|żeńska}}` (e.g., `{{male_form|female_form}}`) placeholder to `quickVariables` in Message Creator (`Create.vue`), allowing one-click insertion into Subject and Preheader fields.
  - Updated `TemplateAiService` to instruct AI on how to use gender-specific forms (`{{male_form|female_form}}`) for personalization.
  - Added translations for the new gender placeholder UI in PL, EN, DE, ES.

- **Documentation:**
  - Added complete WebSocket/Reverb configuration guide to `README.md` and `DOCKER_INSTALL.md`.
  - Created `.env.example` file with all required environment variables including Reverb settings.
  - Added Nginx WebSocket proxy configuration for production deployments.
  - Added troubleshooting steps for "WebSocket connection failed" errors.

- **Multi-Store WooCommerce Integration:**
  - Users can now connect and manage multiple WooCommerce stores from the Integrations tab.
  - New database migration adding `name` and `is_default` columns to `woocommerce_settings` table.
  - Updated `WooCommerceSettings` model with methods for multi-store support (`forUser()` returns collection, `getDefaultForUser()`, `getByIdForUser()`, `setAsDefault()`).
  - Updated `WooCommerceApiService` to accept optional `storeId` parameter and use store-specific cache keys.
  - Completely redesigned WooCommerce Settings page (`Index.vue`) with store list, add/edit modal, status indicators, and default store management.
  - Updated `ProductPickerModal.vue` with store selector dropdown when multiple stores are connected.
  - Updated `BlockEditor.vue` to save and display source store information for selected products.
  - Added "Refresh Product Data" functionality to WooCommerce product blocks in the Template Builder.
  - Updated `TemplateProductsController` to accept `store_id` parameter for all product-related endpoints.
  - New routes for store CRUD operations, set-default, disconnect, and reconnect.
  - Full translations for multi-store feature in PL, EN, DE, ES.

- **WooCommerce Integration Page Enhancements:**
  - Added disconnect confirmation modal with store name display, replacing native browser confirm dialog.
  - Added delete confirmation modal with warning about irreversible action.
  - Made store URL clickable with external link icon (opens in new browser tab).
  - Added "Test" button on each connected store for on-demand connection testing.
  - Added connection test result indicator (green/red badge with success/failure status) next to store URL.
  - Full translations for new features in PL, EN, DE, ES.

### Fixed

- **Documentation:**
  - Fixed incorrect port references in development documentation (Reverb 8085, MySQL 3306).

## [1.5.7] – Short Description

**Release date:** 2026-01-11

### Added

- **Personalization Placeholders:**
  - Added new variable picker (user icon 👤) to **Subject** and **Preheader** fields in Message Creator (`Create.vue`).
  - Implemented support for `[[fname]]` (First Name) and `[[!fname]]` (Vocative First Name) variables in subject/preheader.
  - Updated `TemplateAiService` prompt to encourage AI usage of personalization placeholders.
  - Added translations for new UI elements in PL, EN, DE, ES.

### Fixed

- **Variable Picker UI:** Fixed z-index stacking context for Subject field to ensure the variable dropdown appears above the Preheader input.

- **Autoresponder Queue Timing:** Fixed critical bug where day=0 autoresponder messages were incorrectly sent to all existing subscribers on the list. The `CronScheduleService` now uses full datetime comparison instead of `startOfDay()`, ensuring messages are only sent to subscribers whose expected send time (subscribed_at + day offset) has actually passed.
- **Queue Statistics:** Fixed incorrect "skipped" count in message statistics when duplicate subscriber records exist. The `getQueueScheduleStats()` method now deduplicates subscribers by email before counting, ensuring accurate statistics.
- **Message Statistics:**
  - Fixed duplicate subscriber display in recipient lists and queue statistics by grouping recipients by email address instead of subscriber ID.
  - Updated deduplication logic to prioritize `sent` messages over `failed`, `queued`, `planned`, or `skipped` when multiple records exist for the same email.
  - Excluded "skipped" entries from statistics and recipient lists when the reason is "Subscriber removed from list or unsubscribed".
- **Mailboxes UI:** Fixed "Default" (Domyślna) label overlapped by the toggle switch. The label is now correctly positioned next to the status badge.

## [1.5.6] – Short Description

**Release date:** 2026-01-10

### Added

- **Autoresponder Statistics:**
  - Added display of skipped subscribers count for autoresponder messages on the message list.
  - Added "skipped" and "skipped_hint" translations in PL and EN.
  - **Error Detail Modal:**
    - Modified `Mailboxes/Index.vue` to make truncated error messages clickable.
    - Implemented a new modal (`Error Details Modal`) to show the complete error message.
    - Added necessary reactive state and translation keys (`mailboxes.click_for_details`, `mailboxes.error_details.title`) in PL, EN, ES, DE.

### Fixed

- **Queue Statistics Visibility:** Fixed issue where the "Queue Progress" section was hidden for new autoresponder messages that had no processing data yet.
- **Skipped Subscribers Calculation:** Updated `MessageController` to use dynamic calculation for "missed" subscribers (`getQueueScheduleStats`) instead of relying solely on database records, ensuring the message list reflects the true state shown in the statistics modal.
- **Database Error:**
  - Fixed `SQLSTATE[22001]: String data, right truncated` error by changing `last_test_message` column type from `string` to `text` in `mailboxes` table (migration `2026_01_10_195500`).

## [1.5.5] – Short Description

**Release date:** 2026-01-10

### Fixed

- **Autoresponder Queue Timing:** Fixed issue where autoresponder messages created with a day offset (e.g., `day=1`) would incorrectly queue messages for subscribers whose sending time had already passed.
  - Implemented logic to skip automatic queue entry creation for "missed" subscribers when creating/updating messages.
  - Added new listener to properly handle queueing for new subscribers based on their signup time.
  - "Send to missed" functionality remains available for manual remediation.

- **Variable Insertion:** Fixed `[[!fname]]` (Vocative Name) variable insertion in Template Builder, which was previously inserting `[object Object]`.
- **System Emails:** Added `[[!fname]]` variable to the list of available placeholders in System Email editor.

### Added

- **Campaign Auditor Improvements:**
  - Implemented data-driven revenue loss estimation by integrating real transaction data from `StripeTransaction`, `PolarTransaction`, `StripeProduct`, `PolarProduct`, `Funnel`, and `SalesFunnel`.
  - Added `calculateRevenueMetrics()` method to `CampaignAuditorService` for fetching and normalizing user revenue data (AOV, monthly revenue, active funnels).
  - Added new revenue loss indicator in the auditor UI, showing whether estimations are based on "Real transaction data" or "Industry benchmarks".
  - Added full translations in PL and EN for the new revenue data source indicators.
  - **List Growth Potential Analysis:** New `CATEGORY_GROWTH` category with `ISSUE_LOW_SUBSCRIBER_COUNT` that penalizes small subscriber bases:
    - < 50 subscribers: -20 points (critical), 50-249: -12 points (warning), 250-999: -6 points (warning), 1000-4999: -3 points (info), 5000+: no penalty.
    - Provides actionable recommendations for lead magnets, landing pages, and list-building strategies.

### Changed

- **Campaign Auditor Scoring:**
  - Updated `calculateOverallScore()` logic to incorporate the estimated revenue loss into the final audit score.
  - Implemented a dynamic penalty system where each 1% of monthly revenue lost results in a -1 point deduction (up to a maximum of 15 points).
  - Added fallback penalty logic for users without transaction data based on absolute loss amounts ($100 = -1 point, max -10 points).

## [1.5.4] – Short Description

**Release date:** 2026-01-09

### Fixed

- **Docker Compose (Development):** Added missing `scheduler` and `queue` services to `docker-compose.dev.yml` to enable cron jobs and queue processing in development environment. Previously, scheduled tasks like autoresponders, abandoned cart detection, and other Laravel schedule commands would not run on dev environment.

- **Autoresponder Queue:** Fixed a bug where autoresponder messages could lose their `scheduled` status and be incorrectly marked as `sent` (a status reserved for broadcast messages), which caused the cron job to ignore them for new subscribers.

- **Subscriber Management:** Standardized manual subscriber addition to reliably dispatch the `SubscriberSignedUp` event, ensuring all automations and initial queue synchronizations are triggered immediately.

- **Autoresponder Delay:** Fixed critical bug where autoresponder messages ignored the `day` offset and sent immediately to all subscribers. Messages now correctly respect the configured delay (`day=0` for immediate, `day=1` for next day, etc.).

- **Subscriber Reactivation:** Fixed issue where manually adding or importing subscribers who were previously unsubscribed would not reactivate them on the list.

- **SMS List Permissions:** Fixed an issue where team members with `edit` permissions were unable to edit shared SMS lists due to direct ownership checks. The `SmsListController` now correctly uses the `canEditList()` method to validate permissions.

- **List Deletion Security:** Standardized SMS list deletion to allow only the list owner to perform the action.

### Added

- **Subscriber Rejoin Handling:**
  - New `resubscription_behavior` setting per mailing list to control what happens when active subscribers try to re-subscribe.
  - **Options:**
    - `reset_date` (default): Reset `subscribed_at` to now, restarting autoresponder queue from the beginning.
    - `keep_original_date`: Preserve the original `subscribed_at`, maintaining queue position.
  - **Former subscribers** (unsubscribed/removed) **always** have their date reset when rejoining.
  - Applies to all subscription methods: manual creation, CSV import, form signups, API, bulk operations, and automation actions.
  - New UI toggle in list settings (Subscription tab) with translations in PL and EN.

### Changed

- **List Index Metadata:** Enhanced the SMS list and Email list index views to include a `permission` field (indicating `edit` or `view` access levels) and updated Group/Tag filtering to correctly utilize the admin user's scope for team members.

## [1.5.3] – Short Description

**Release date:** 2026-01-08

### Added

- **Vocative Case Support (Polish Names):**
  - New `vocative` column in `names` table for storing vocative forms.
  - `[[!fname]]` placeholder now returns the vocative form of subscriber's first name (e.g., "Marzena" → "Marzeno").
  - `GenderService.getVocative()` method with automatic capitalization matching.
  - `Name::findVocative()` static method supporting user-defined and system names.
  - Enhanced Polish Names Database with **~480** common, historical, and less common first names with their vocative forms (added popular diminutives like "Kasia", "Tomek", "Antek", "Zuzia", etc., and historical names like "Mieszko", "Dobrawa").
  - Fixed typo in Polish names seeder for the name "aleksandra" (corrected vocative form to "aleksandro").
  - Vocative field in Name Database UI (add/edit form and table column).
  - Full translations in PL and EN.

### Fixed

- **Template Builder:**
  - **AI Button Visibility:** Fixed issue where the "Generuj z AI" button was hidden when AI was not configured or due to scrolling.
    - Button is now **always visible** (sticky at the bottom), allowing users to access the feature or see configuration prompts.
    - Fixed mobile layout scrolling to ensure the button remains accessible at the bottom of the drawer.
- **Team Member Access:**
  - Fixed 403 Forbidden error upon login for team members by hiding the admin-only "User Management" menu item.
  - Fixed visibility of shared SMS and Email lists for team members by updating multiple controllers (`SmsListController`, `MessageController`, `SubscriberController`) to use `accessibleLists()` instead of `contactLists()`.
  - Fixed "Unauthorized access" validation error when team members attempt to add subscribers to shared lists or create messages using shared lists.
  - **API:** Fixed ambiguous `status` column SQL error in `ContactListController` when filtering subscribers by status by using `wherePivot()`.
- Fixed ambiguous column SQL error in Subscriber statistics calculation.
  - Fixed subscriber visibility logic to ensure team members can see all subscribers belonging to any list they have access to, regardless of who created the subscriber.

## [1.5.2] – Short Description

**Release date:** 2026-01-08

### Fixed

- **Form Builder:**
  - Fixed issue where the same field could be added multiple times to a form. Fields already added to the form are now displayed as disabled (grayed out with a checkmark icon) in the "Available Fields" sidebar instead of being hidden or clickable. This prevents duplicate field entries and provides clear visual feedback about which fields are already in use.
  - **Template Builder:** Fixed issue where the Block Library sidebar was not scrollable on smaller screens, preventing access to bottom blocks and buttons. Added `min-h-0` class to the sidebar container and updated parent layout in `Builder.vue` from `md:block` to `md:flex` to ensure proper flexbox behavior and scrollable area height.

## [1.5.1] – Short Description

**Release date:** 2026-01-08

### Added

- **Name Database (Baza imion):**
  - New settings page for managing first names with gender assignments for grammatical personalization.
  - Dynamic grammar syntax `{{male_form|female_form}}` for automatic gender-based word forms in emails and SMS.
  - `GenderService` for centralized gender detection from name database with pattern-based fallback for Polish names.
  - Support for country-specific name datasets (PL, DE, CZ, SK, FR, IT).
  - Import/export functionality for name data (CSV format).
  - Polish names seeder with 90+ male and 80+ female common first names.
  - Full translations in EN and PL.

### Fixed

- Fixed Vue template syntax error in Name Database settings page.
- Fixed `vue-i18n` invalid placeholder syntax error in translation files.
- Fixed 404 routing error for Name Database by regenerating Ziggy configuration.
- **Form Embed CSS Protection:** Fixed issue where embedded form styles (button colors, field styles) were being overwritten by target page CSS. Added `!important` declarations to all CSS rules and inline styles to critical elements (buttons, inputs, labels) to ensure consistent appearance when forms are embedded on external websites.

## [1.5.0] – Short Description

**Release date:** 2026-01-07

### Added

- **Affiliate Program Module:**
  - Implemented complete affiliate marketing system (`AffiliateProgram`, `AffiliateOffer`, `Affiliate`, `AffiliateCommission`).
  - **Owner Panel:** dedicated section for managing tracking programs, offers, affiliates, and payouts.
  - **Partner Portal:** separate specialized portal for affiliates (`/partner`) with dashboard, tracking links, and reports.
  - **Automated Tracking:**
    - Lead tracking integration with NetSendo Forms (`FormSubmissionService`).
    - Sales tracking integration with Stripe (`StripeController` webhooks for purchase/refund).
    - Cookie-based attribution system (`AffiliateTrackingService`) with configurable duration.
  - **Commission Engine:**
    - Support for Percentage and Fixed commissions.
    - Multi-tier commission structures (Silver/Gold/Platinum affiliate levels).
    - Recurring commissions support.
  - **Localization:** Full translations for Owner Panel and Partner Portal in EN, PL, DE, ES.
  - **Documentation:** Added comprehensive guide at `docs/AFFILIATE.md`.
  - **Affiliate Program Enhancements:**
    - **Registration Link:** Improved UI with full URL display, copy-to-clipboard button, and open-in-new-tab action.

### Changed

### Fixed

- Missing translation keys for Affiliate Program in frontend locales (EN, DE, ES).

- **Signature Editor:**
  - **Image Upload:** Implemented direct file upload support (drag & drop) in `SignatureEditor.vue`, alongside existing URL insertion.
  - **Table Support:** Fixed "full HTML" detection logic to correctly identify tables as supported elements in visual mode.
  - **Dark Mode:** Fixed styling issues in the Image Modal where text was invisible on dark backgrounds.
  - **Translations:** Added missing translation keys for editor messages (`editor.full_html_message`) and upload UI.

- **Mailboxes:**
  - Fixed issue where editing a Gmail mailbox caused a validation error due to browser autofill prevention clearing the `from_email` field.
  - Backend `update` method now correctly handles empty `from_email` for Gmail providers by retaining existing values or setting a default, mirroring the creation logic.

- **Affiliate Program Translations:**
  - Added missing "open" and "open_in_new_tab" translation keys across all locales.

## [1.4.2] – Short Description

**Release date:** 2026-01-06

### Added

- **WooCommerce Product Integration for Templates:**
  - New WooCommerce Settings page (`/settings/woocommerce`) to connect your WooCommerce store using REST API credentials.
  - Added `WooCommerceSettings` model with encrypted credential storage.
  - Added `WooCommerceApiService` for fetching products, categories, and testing connection.
  - Added `TemplateProductsController` with endpoints for WooCommerce products and recently viewed products (from Pixel data).
  - New `ProductPickerModal.vue` component for selecting products in the Template Builder.
  - **Enhanced Product Picker:**
    - Implemented server-side pagination for WooCommerce products (API-driven).
    - Added category filtering dropdown fetching categories from WooCommerce.
    - Added total product count display ("Found: X products").
    - Added pagination controls (Previous/Next page, "Page X of Y").
    - Integrated with backend endpoints to fetch pagination metadata (total, total_pages) from WooCommerce API headers.
  - Updated `BlockEditor.vue` to support importing products from WooCommerce or recently viewed items.
  - Added sidebar navigation item for WooCommerce Settings.
  - Full translations for WooCommerce integration in PL, EN, DE, and ES.
  - Added support for multi-product selection in the "Product Grid" block (Siatka produktów) in the Template Builder, allowing users to populate the grid with selected WooCommerce products.
  - **Table Support in Editor:**
    - Enabled table support in `SignatureEditor` for Inserts and Signatures.
    - Added toolbar buttons for inserting tables and managing rows/columns/cells.

### Changed

### Fixed

## [1.4.1] – Short Description

**Release date:** 2026-01-06

### Added

- **Signature Editor:**
  - Implemented professional WYSIWYG editor (`SignatureEditor.vue`) for signatures and inserts with visual, source (HTML), and preview modes.
  - Added smart HTML merging logic to seamlessly integrate signatures into email templates (supports full HTML, tables, and simple text).
  - Added translations for the new editor features in PL and EN.

### Changed

- **Inserts & Signatures:**
  - Replaced simple `textarea` with `SignatureEditor` in `Inserts.vue` for better user experience.
  - Increased modal width to `max-w-4xl` to accommodate the new editor.
  - Updated `InsertPickerModal` to correctly handle signature insertion types.

## [1.4.0] – Short Description

**Release date:** 2026-01-06

### Added

- **NetSendo Pixel:**
  - Implemented comprehensive tracking pixel system for device fingerprinting and behavior tracking.
  - Added `PixelController` and API endpoints (`/t/pixel/*`) for serving the pixel script and receiving events.
  - Added `subscriber_devices` and `pixel_events` tables for storing device and event data.
  - Added `DeviceFingerprintService` for User-Agent parsing and fingerprint generation.
  - Added visitor-to-subscriber linking on form submissions.
- **Pixel Admin UI:**
  - Added Pixel Settings page (`/settings/pixel`) with real-time statistics (views, visitors) and activity charts.
  - Added Embed Code generator with copy functionality.
  - Added Sidebar navigation item for Pixel Settings.
- **E-commerce & Automation:**
  - Added dedicated Cart Abandonment detection system (`DetectAbandonedCartsCommand`) running as a scheduled job.
  - Added new automation triggers: `pixel_page_visited`, `pixel_product_viewed`, `pixel_add_to_cart`, `pixel_checkout_started`, `pixel_cart_abandoned`.
  - Updated WooCommerce plugin to inject pixel script and track product views, cart actions, checkouts, and purchases.
- **Translations:**
  - Added full translations for Pixel Settings in PL, EN, DE, ES.

### Changed

### Fixed

- **Automation System:**
  - Fixed issue where automation triggers were not active because `EventServiceProvider` was missing from `bootstrap/providers.php`.
  - Fixed fatal error in automation actions (`AutomationActionExecutor` and `AutomationService`) caused by incorrect relationship method call (`lists()` instead of `contactLists()`).

## [1.3.13] – Automation Trigger Fixes

**Release date:** 2026-01-05

### Fixed

- **Automation Triggers:**
  - Fixed issue where automations were not triggering for subscribers added via Bulk Move, Bulk Copy, or Bulk Add operations.
  - Fixed issue where manual subscriber creation only triggered automations if "Send Welcome Email" was checked.
  - **Result:** Automations now reliably trigger for ALL subscriber addition methods, ensuring seamless workflows.

## [1.3.12] – Short Description

**Release date:** 2026-01-05

### Added

- **Subscriber Management:**
  - **Advanced Pagination:**
    - Added per-page selector (10, 15, 25, 50, 100, 200 items) with persistent local storage settings.
    - Updated backend to support dynamic pagination limits.
  - **Enhanced Bulk Operations:**
    - **Select All in List:** Added functionality to select all subscribers in a filtered list (fetching all IDs from backend), not just visible page items.
    - **Delete from List:** Added specific bulk action to remove subscribers only from the currently filtered list (detach) without deleting them globally.
    - **Confirmation Modals:** Added comprehensive confirmation modals for all bulk actions (Delete, Delete from List, Select All) to prevent accidental data loss.
    - **Statistics Display:** Added contextual statistics showing total subscriber counts, list-specific counts, and filtered view details.
  - **Translations:**
    - Added full Polish translations for all new bulk operations, modals, and statistics.

- **External Pages:**
  - Added toast notification when copying the external page link to the clipboard.
  - Added confirmation modal when deleting an external page.

- **Translations:**
  - Added missing keys for `common` (first_name, last_name, phone) and `external_pages` in EN, PL, DE, ES.

### Changed

- **Subscriber UI:**
  - **Bulk Actions Toolbar:**
    - Removed redundant "Add to List" button (consolidated with "Copy to List").
    - Simplified "Copy to List" modal to single-mode operation.
  - **UX Improvements:**
    - "Delete from List" button only appears when a specific list filter is active.
    - "Select All" button only appears when a specific list filter is active.

### Fixed

- **Automation Builder:**
  - Fixed issue where mailing lists were not visible in "Then" actions (e.g., Unsubscribe, Move to list) for team members by selecting lists via `accessibleLists()` instead of `forUser()`.
  - Fixed configuration persistence issue where selected options (like list ID) were not saved to the database due to missing validation for `actions.*.config`.

- **External Pages:**
  - Fixed 403 Forbidden error when editing external pages ensuring correct policy authorization.

## [1.3.11] – Automation Fixes & Improvements

**Release date:** 2026-01-04

### Added

- **Automations:**
  - Added confirmation modals for duplicate and delete actions with dark mode support.

- **Translations:**
  - Added new translation key `edit_in_editor` for "Edit in editor" button.

### Changed

- **Template List UI:**
  - Reordered template cards to display the thumbnail at the top for better visual hierarchy.
  - Increased thumbnail height to `192px` (h-48) for improved visibility.
  - Renamed "Edit" button to "Edit in editor" for clarity.
  - Reduced vertical spacing on mobile view (`mb-3` instead of `mb-6`) to minimize empty space.

- **Builder UI/UX:**
  - **Alignment:** Fixed visual alignment rendering for Text and Image blocks in the canvas (Left, Center, Right).
  - **Layout:** Changed sidebar block editor to single-column layout to prevent nested input fields from overflowing.
  - **Scrolling:** Added bottom padding (`pb-40`) to the builder canvas to improve scrolling experience and drag-and-drop usability.
  - **Header:** Optimized template name input field to utilize full available width, fixing layout issues on both desktop and mobile.

### Removed

- **Template List:**
  - Removed redundant "Builder" badge from template cards as it duplicated the edit functionality.

### Fixed

- **Automations:**
  - Fixed 403 Forbidden error when accessing automation routes (Policy discovery issue).
  - Fixed JavaScript error (`TypeError: Cannot read properties of undefined`) in Automation Builder when actions lack configuration.
  - Fixed 404 error when editing automation rules caused by route model binding issues.
  - Fixed handling of "Unsubscribe from list" action to correctly show list selection dropdown.
  - Fixed missing translation for "Create" button in Automation Builder.
  - Fixed dark mode visibility issues (inputs, dropdowns, radio buttons, and "Cancel" button) in Automation Builder.

- **Template List Layout:**
  - Fixed issue where the page title and "Add Template" button were truncated in the header.
  - Moved title and actions to the main content area for better visibility and mobile responsiveness.
  - Reordered template card elements to place action buttons (Edit, Duplicate, Delete) at the top, preventing overlap with the thumbnail link.

- **Image Upload Error Handling:**
  - Fixed silent failures during image uploads in the Template Builder.
  - Added explicit error messages for failed uploads (e.g., file too large, invalid format).

- **Translations:**
  - Updated Polish translation for "Your templates" to "Twoje szablony" for better clarity.
    **Release date:** 2026-01-04

### Added

- **Message Creation:**
  - Active subscriber count now displayed next to each list name in list selection views (e.g., "My List (42)").
  - Count reflects only active subscribers (excludes unsubscribed).

- **Mailing Lists Sorting:**
  - Added sorting functionality to the "Created at" column in the mailing list view.
  - Users can now toggle between newest (default) and oldest lists.
  - Visual sort indicators (arrows) added to the column header.

### Fixed

- **Subscriber Duplication:**
  - Fixed issue where creating a subscriber via API with a previously soft-deleted email caused a "Duplicate entry" error.
  - API now correctly restores soft-deleted subscribers instead of attempting to create duplicates.

## [1.3.9] – Short Description

**Release date:** 2026-01-03

### Added

- **Template Builder:**
  - **Inserts (Placeholders):** Added functionality to insert dynamic placeholders (firstname, email, signatures, etc.) into text blocks, buttons, and other editable fields.
  - **Variable Picker:** Integrated `InsertPickerModal` into the builder for easy variable selection.

- **Translations:**
  - Added missing translations for `template_builder.insert_variable` and `templates.builder_badge` in all supported languages.

- **GDPR "Right to be Forgotten" (Article 17):**
  - **Data Deletion:** Subscribers can now request permanent deletion of all their data via the preferences page.
  - **Suppression List:** Deleted subscribers are added to a suppression list to prevent accidental re-adding.
  - **Re-subscription Flow:** Previously forgotten users can re-subscribe with renewed consent; system logs the event and removes them from the suppression list.
  - **Frontend:** "Delete all my data" option with confirmation dialog in Subscriber Preferences.
  - **System Emails:** automated confirmation email flow for data deletion requests.

- **Template UI Improvements:**
  - **Templates List:** Redesigned template cards (name above thumbnail) and improved header layout for better mobile responsiveness.
  - **Builder UX:** Added "Add Block" button to empty canvas and improved template name input with proper placeholder behavior.
  - **Localization:** Updated Polish translations, renaming "Builder" to "Kreator".

### Fixed

- **Starter Templates Missing on New Installations:**
  - Fixed issue where new NetSendo installations had no starter templates in the Templates section.
  - Docker entrypoint now automatically seeds the database with 6 premium starter templates (Welcome Email, Classic Newsletter, Promo Campaign, Cart Abandonment, Order Confirmation, Password Reset).
  - Smart seeding logic checks if templates exist before seeding, ensuring existing installations also receive templates on next container restart.

- **Subscription Persistence:**
  - Fixed issue in Admin Panel where `Contact Lists` tab displayed unsubscribed lists as active.
  - Updated `SubscriberController` to filter contact lists by pivot status `active`.

- **Single-List Unsubscribe:**
  - Fixed issue where unsubscribing from a single list failed to send confirmation emails.
  - Added comprehensive logging to `UnsubscribeController` and `SystemEmailService` for better traceability.

- **Mobile Notifications:**
  - Fixed issue where notification messages were truncated on mobile devices by adjusting dropdown width.

- **Templates UI:**
  - Fixed mobile layout overflow in Templates view by adjusting header flex properties and title sizing.

- **Mobile Notification Modal:**
  - Fixed unresponsive and overflowing notification modal on mobile devices by implementing `fixed` positioning for better visibility.

## [1.3.8] – Subscription Persistence Fix

**Release date:** 2026-01-03

### Added

- **System Emails UI Redesign:**
  - **Responsive Mobile View:** Replaced table with card-based layout on mobile devices for better usability.
  - **Modernized UI:** Updated styling with better spacing, typography, and shadows.
  - **Dark Mode Support:** Fixed styling issues in the list selection dropdown where text was unreadable in dark mode.

### Fixed

- **Subscription Persistence:**
  - Fixed critical issue where unchecking lists or unsubscribing in preferences was not persisted due to incorrect user identification in `SubscriberPreferencesController`.

- **System Email Sending:**
  - Fixed critical issue where custom system emails failed with "Connection could not be established" error when using non-SMTP providers (e.g., SendGrid/Gmail API).
  - Refactored `SystemEmailService` to properly leverage `MailProviderService` for all custom emails, fixing "empty host" errors.
  - Updated `SendNewSubscriberNotification` listener to use `SystemEmailService`, ensuring reliable delivery of admin notifications using the correct mailbox.

- **Subscription Preferences:**
  - Fixed issue where users could not uncheck lists on the preferences page due to a JavaScript event conflict.

## [1.3.7] – Short Description

**Release date:** 2026-01-03

### Added

- **Template Builder UX:**
  - **Mobile Experience:**
    - Restored "Preview" button in mobile navigation.
    - Added visual save status indicator (Saving/Saved) to mobile bottom bar.
    - Added "Done" button to mobile drawer header for better usability.
  - **Editor Improvements:**
    - Changed "+ Add Block" button behavior to open the block library sidebar instead of immediately adding a text block.

- **Subscriber Preference Management:**
  - **Context-Aware Unsubscribe Flow:**
    - Unsubscribing from a single list campaign now targets only that specific list.
    - Unsubscribing from multi-list or broadcast campaigns redirects to the new Preferences Management page.
  - **Email Confirmation Security:**
    - Clicking unsubscribe links **never** performs immediate actions.
    - System now sends a secure, time-limited confirmation email (`unsubscribe_request` or `preference_confirm`).
    - Actual changes are applied only after clicking the signed link in the confirmation email.
  - **Preferences Page:**
    - New public-facing page (`/preferences/{subscriber}`) allowing subscribers to manage their subscriptions.
    - Lists all public contact lists available for the subscriber.
    - User selection triggers a confirmation email flow to apply changes.
  - **New Placeholders:**
    - `[[manage]]` / `[[manage_url]]`: Generates a signed link to the subscriber's preferences page.
    - `[[unsubscribe_link]]` / `[[unsubscribe]]`: Context-aware link (single list unsubscribe vs. global preferences).
  - **System Emails & Pages:**
    - New `preference_confirm` system email template for preference change confirmation.
    - New system pages: `unsubscribe_confirm_sent`, `preference_confirm_sent`, `preference_update_success`.
  - **Backend Improvements:**
    - `SubscriberPreferencesController` for handling the new preferences flow.
    - `GenericHtmlMailable` for sending dynamic confirmation emails.
    - Updated `SendEmailJob` to inject correct list context into placeholders.
    - Added `scopePublic` to `ContactList` model for filtering visible lists on the preferences page.

### Fixed

- **Template Builder:**
  - Fixed critical issue with duplicate translation keys causing missing labels in mobile view.
  - Fixed "Add Block" button confusion by opening library instead of auto-inserting text.

## [1.3.6] – Short Description

**Release date:** 2026-01-03

### Added

- **Multi-level Group Hierarchy:**
  - Implemented hierarchical structure for Contact List Groups (parent-child relationships).
  - Updated `ContactListGroup` model with `parent`, `children`, and `allChildren` relationships.
  - New recursive methods `getAllDescendantIds`, `getFullPathAttribute`, and `getDepthAttribute`.
  - **Tree View UI:** Completely redesigned Groups page to display groups in a collapsible tree structure.
  - **Hierarchical Filtering:** Filter dropdowns in Email Lists, SMS Lists, and Messages now display indented hierarchy.
  - **Smart Filtering Logic:** Selecting a parent group now automatically includes all legitimate child groups in filters.
  - **Group Management:** Added parent selection in create/edit forms with circular dependency prevention.
  - New recursive Vue component `GroupTreeItem.vue` for efficient tree rendering.
  - Full translations for new hierarchy features in PL.

- **Template Builder Translations:**
  - Added missing keys for `template_builder` and `templates` namespaces (EN, PL, DE, ES).
  - Fixed JSON syntax errors in locale files which prevented build.
  - Verified mobile view translations.

## [1.3.5] – Universal Timezone Management

**Release date:** 2026-01-02

### Added

- **Universal Webinar Timezone Management:**
  - Implemented "Inherited" timezone logic for Webinars (defaults to User timezone) and Auto-Webinars (defaults to Webinar timezone).
  - Added `UserTimezoneUpdated` event listener to automatically sync specific webinar timezones when user changes account timezone.
  - Added `getEffectiveTimezoneAttribute` to models for transparent timezone resolution.
  - New "Default" option in timezone selectors reflecting the inherited value.
  - UI updates in Webinar Edit and Auto-Config pages.
  - Full translations for new timezone features in PL, EN, DE, ES.

- **Template Builder UX Improvements:**
  - Added "Close Preview" button in the preview panel.
  - Added comprehensive image upload error handling with user-friendly messages.
  - Added loading state indicators during image uploads.
  - Added translations for new UI elements in EN, PL, DE, ES.

### Fixed

- **Webinar Timezone Logic:**
  - Fixed migration to correctly handle nullable `timezone` column for inheritance.
  - Fixed session start times on watch/registration pages to correctly respect timezone.
  - Fixed issue where changing user timezone wouldn't update relevant webinars.

- **Frontend & UI Fixes:**
  - Fixed syntax error in `Edit.vue` (extra closing div).
  - Fixed missing translation keys for timezone fields in JSON locales.
  - Fixed Template Preview disappearing on mobile view switch.
  - Fixed MJML image rendering issues (thumbnails, width).
  - Fixed layout overlap on Inserts page on small screens.
  - Fixed Scenario Builder visibility issues in light mode.
  - Fixed "Generate random scenario" functionality and density slider validation.

## [1.3.4] – Short Description

**Release date:** 2026-01-02

### Added

- **UI Naming Consistency Improvements:**
  - Added "New Email List" and "New SMS List" quick actions on the Dashboard.
  - New SVG icons for quick actions with blue and teal color themes.
  - Updated navigation menu item names for better clarity:
    - "Add Message" → "Add Email Message"
    - "Message List" → "Email Message List"
    - "Add SMS" → "Add SMS Message"
    - "SMS List" → "SMS Message List"
  - Unified list naming across CRM section: "Address Lists" → "Email Lists".
  - Full translations updated in EN, PL, DE, ES.

- **AI Date Context:**
  - All AI prompts now include current date information to prevent outdated content generation.
  - New `AiService::getDateContext()` method providing multilingual (EN/PL) date context.
  - Fixes issue where AI generated content referring to wrong year (e.g., "Welcome 2024" instead of "Welcome 2026").
  - Affected services: `TemplateAiService`, `CampaignAdvisorService`, `CampaignAuditorService`.

### Fixed

- **AI Token Limits:**
  - Increased default fallback `max_tokens` from 1024 to 65536 in all AI providers.
  - Prevents content truncation when generating long HTML templates.
  - Affected providers: `GeminiProvider`, `OpenAiProvider`, `AnthropicProvider`, `GrokProvider`, `OpenrouterProvider`, `OllamaProvider`.

- **License Page CSRF Token Mismatch:**
  - Fixed "CSRF token mismatch" error when clicking SILVER license button on fresh installations.
  - Replaced native `fetch()` with `axios` in `Activate.vue` for all API calls.
  - Added XSRF token configuration to `bootstrap.js` for automatic CSRF handling.
  - Affected functions: `requestSilverLicense()`, `checkLicenseStatus()`, polling.

---

## [1.3.3] – Webinar Chat & Advanced Features

### Added

- **Webinar List Integration:**
  - **Advanced Attendance Tracking:** Automatically managing subscribers based on their webinar behavior.
  - **Click Tracking:** Subscribers entering the webinar watch page are automatically added to a specific "Clicked Link" contact list.
  - **Watch Duration Tracking:** Subscribers who watch the webinar for a specified duration (e.g., 5 mins) are added to an "Attended" contact list.
  - **New UI Controls:** Added "Advanced List Integration" section to Webinar Edit page for configuring these lists and the attendance threshold.
  - **Database Updates:** New columns `clicked_list_id`, `attended_list_id`, and `attended_min_minutes` in `webinars` table.
  - **Full Translations:** UI available in PL, EN, DE, ES.

- **Webinar Chat System:**
  - **Reactions (Emoji):**
    - Real-time reactions system with animated bubbles (TikTok/Instagram Live style).
    - 7 reaction types: heart, thumbs up, fire, clap, wow, laugh, think.
    - Host-configurable (enable/disable) via control panel.
    - "Simulated" reactions support for auto-webinars.
    - New `WebinarReactionBar` and `ReactionBubbles` Vue components.

  - **Host Control Panel:**
    - New "Controls" tab in Webinar Studio for advanced chat management.
    - **Chat Modes:** Open, Moderated, Q&A Only, Host Only.
    - **Slow Mode:** Configurable cooldowns (5s, 10s, 30s, 1min) to prevent spam.
    - **Fake Viewers:** "Social Proof" settings with base count and random variance.
    - **Announcements:** Send official host messages (Info, Success, Warning, Promo) directly to chat.

  - **Scenario Builder (Auto-Webinars):**
    - Visual timeline editor for creating automated chat scripts.
    - Drag-and-drop message management grouped by time segments.
    - **Random Generator:** Templates for Sales, Educational, and Launch webinars.
    - Import messages from previous live sessions.
    - Support for various message types: Comment, Question, Reaction, Testimonial.

  - **Promotion Features:**
    - **Promotion Countdown:** Urgent pulsing timer for product offers.
    - Shimmer effects and "Ending Soon" visual indicators.
    - Integrated into public watch page.

  - **Translations:**
    - Full translations for reactions, host controls, and scenario builder in PL, EN, DE, ES.

### Fixed

- **Webinar Studio:**
  - Fixed integration of host controls and product panel tabs.
  - Added pending message count badge for moderators.
  - Added periodic dashboard data refresh (viewer count, stats).

### Fixed

- **Webinar Playback & UI:**
  - Fixed `500 Internal Server Error` on playback progress tracking endpoint (`/webinar/{slug}/progress/{token}`).
  - Fixed black screen issue on autowebinars when video is not configured or session hasn't started yet.
  - Implemented proper "Session Ended" view for webinars with expired sessions, showing replay or re-registration options.
  - Fixed re-registration logic: users re-registering with the same email now get their session updated to the newly selected time instead of receiving old session data.
  - Disabled "Start" and "End" buttons in Studio for autowebinars (replaced with informational message).

### Added

- **Webinar Success Page Integration:**
  - Added "Add to Google Calendar" and "Add to Outlook" buttons to webinar success page.
  - Generates calendar events with correct webinar title, date, time, and link.
  - Added "Go to Webinar" button (🚀) directing users straight to the webinar room.
  - Added translations for calendar integration and new buttons in EN, PL, DE, ES.

- **Webinar Email System:**
  - Replaced ENV-based mailer with database-controlled Mailbox system for all webinar notifications.
  - Implemented smart mailbox resolution: uses Webinar's Target List mailbox first, falls back to User's default mailbox.
  - New threaded queue job `SendWebinarEmail` for reliable delivery of Registration, Reminder, Started, and Replay emails.
  - All webinar emails now correctly respect the sender identity (From Name/Email) defined in Mailbox settings.

### Fixed

## [1.3.2] – Short Description

**Release date:** 2026-01-01

### Added

- **Smart Email Funnels (Conditional Sequences):**
  - New "Wait & Retry" logic for funnel condition steps with configurable max attempts and interval.
  - Automatic reminder emails for subscribers who haven't met conditions (e.g., email opened).
  - New `task_completed` condition type for external quiz/task integration.
  - New migrations: `add_retry_settings_to_funnel_steps`, `create_funnel_step_retries`, `add_task_completed_condition`, `create_funnel_tasks`.
  - New models: `FunnelStepRetry`, `FunnelTask` with relationships and helper methods.
  - New `FunnelRetryService` for processing retry logic (shouldSendRetry, sendRetry, handleRetryExhausted).
  - New `ProcessFunnelRetriesCommand` artisan command for scheduled retry processing.
  - New `FunnelTaskController` with public endpoints for external task completion webhooks (`/funnel/task/complete`, `/funnel/task/status`).
  - Updated `FunnelExecutionService` with `wait_for_condition` support and `task_completed` condition check.
  - Updated `FunnelStep` model with retry constants, fillable fields, casts, and relationships.
  - Updated `FunnelSubscriber` model with `STATUS_WAITING_CONDITION` status and new relationships.
  - Full translations for retry/wait UI in PL, EN, DE, ES.

## [1.3.1] – Webinar Email Integration

**Release date:** 2026-01-01

### Added

- **Webinar Public Registration Link:**
  - Added public registration link display in webinar edit view with copy-to-clipboard functionality.
  - Visual link preview with prominent gradient styling and external link button.
  - Full translations for link section in PL, EN, DE, ES.

- **Webinar Status Management:**
  - Added status change dropdown allowing manual status transitions in webinar edit view.
  - Implemented status transition validation (e.g., draft → scheduled → live → ended → published).
  - Automatic timestamp updates (started_at, ended_at, duration_minutes) when changing status.
  - Visual loading spinner during status update.
  - Full translations for status change UI in PL, EN, DE, ES.
  - **Webinar Video Player:**
    - Blocked native video controls for better presenter control.
    - Added countdown timer overlay before session start.
    - Auto-play functionality when countdown reaches zero.
  - **Autowebinar Configuration:**
    - Added new "Schedule" configuration UI for automated webinars.
    - Support for multiple sessions per day.
    - Support for Recurring, Fixed Dates, On-demand, and Evergreen schedule types.
  - **Translations:**
    - Added missing translation keys for autowebinar configuration and schedule button in PL, EN, DE, ES.
  - **Webinar Timezone Support:**
    - Added timezone selector to webinar registration form with browser auto-detection.
    - Registration's timezone is stored and used for countdown display.
    - Session start time displayed in registrant's timezone on watch page.
  - **Email Placeholders:**
    - Added `[[webinar_register_link]]` and `[[webinar_watch_link]]` placeholders for email templates.

### Fixed

- Fixed autowebinar session time not being saved correctly when user selects specific session time during registration.
- Fixed registration confirmation page showing webinar's default time instead of selected session time.

### Added (continued)

- **Webinar Email Integration:**
  - Added `webinar_id` and `webinar_auto_register` fields to messages for email campaigns.
  - Auto-registration endpoint (`/webinar/{slug}/auto/{token}`) with signed URL security.
  - PlaceholderService: `[[webinar_register_link]]` and `[[webinar_watch_link]]` generation.
  - When subscriber clicks email link, they are auto-registered and redirected to watch page.
  - **Frontend UI:**
    - Added "Webinar Integration" section to email campaign creation form.
    - Dropdown to select active webinar.
    - Checkbox to enable/disable auto-registration functionality.
    - Info panel with available placeholders.
  - Full translations for new UI in PL, EN, DE, ES.

## [1.3.0] – Short Description

**Release date:** 2026-01-01

### Added

- **Webinar System:**
  - **Comprehensive Webinar Management:** Create, schedule, and manage live and automated webinars.
  - **Live Studio Environment:**
    - Integrated presenter studio with camera/screen sharing.
    - Real-time chat with message pinning, deletion, and moderation.
    - Product offers management (pin/unpin products).
    - CTA (Call to Action) management with timers.
  - **Automated Webinars (Evergreen):**
    - Schedule repeating webinars (daily, weekly, specific dates).
    - "Just-in-time" scheduling logic.
    - Simulated chat system for automated sessions.
  - **Frontend Components:**
    - Public registration pages with customizable layouts.
    - "Webinar Room" for attendees with video player and chat interface.
    - Webinar creation wizard and management dashboard.
  - **Email Notifications:**
    - Automated reminder sequence (Confirmation, 24h before, 1h before, 15min before).
    - "Replay Available" notifications.
  - **Sidebar Integration:**
    - Added "Webinary" section to the main navigation menu with "NOWE" badge.

### Fixed

- **Database Migrations:**
  - Resolved MySQL index length limit issue in `page_visits` table migration (`1071 Specified key was too long`).
  - Fixed `SystemMessageSeeder` to support renamed `system_pages` table after migration.
  - Fixed `webinar_chat_messages` index length issue.

- **Developer Experience:**
  - Removed automatic creation of test user in `DatabaseSeeder` to allow clean manual registration.
  - Replaced external `@heroicons/vue` dependency with inline SVGs in webinar components to fix build errors.

- **Webinar Functionality:**
  - Fixed critical issue where webinar Edit, Analytics, and Show pages were missing, causing blank screens.
  - Restored full webinar management: editing, status changes, and details view.
  - Restored webinar analytics dashboard with charts and funnel data.
  - Added missing translations for all webinar management interfaces (EN, PL).

## [1.2.13] – Shopify Integration & Translations

**Release date:** 2026-01-01

### Added

- **Shopify Integration:**
  - Full Shopify integration for automatic customer subscription and order tracking.
  - New internal webhook handler `ShopifyController` supporting `orders/paid`, `orders/create`, and `customers/create` events.
  - Secure authentication via Bearer token and optional HMAC signature verification.
  - Custom fields support: `shopify_order_id`, `shopify_order_number`, `shopify_customer_id`, `shopify_currency`.
  - New authenticated Marketplace page (`/marketplace/shopify`) with setup guide and webhook configuration.
  - Added Shopify to Active Integrations list in Marketplace.
  - Full translations for Shopify integration in EN, PL, DE, ES.

### Fixed

- **Translation Consistency:**
  - Fixed incorrect structure of `woocommerce` translation block in `pl.json` and `en.json`.
  - Fixed incorrect structure of `wordpress` translation block in `de.json`, `en.json`, `pl.json`, and `es.json`.
  - Ensured all integration features (features list, setup steps, shortcodes) are correctly localized across all supported languages.

### Added

- **NetSendo Logo for Plugins:**
  - Added NetSendo logo (`netsendo-logo.png`) to both WooCommerce and WordPress plugin assets.
  - WordPress plugin settings page now displays actual logo instead of dashicons icon.

- **WooCommerce Product-Level External Pages:**
  - Added dynamic "NetSendo External Page" dropdown to WooCommerce product settings.
  - Product override settings now support selecting external pages from API (matching global settings).
  - Renamed "Redirect URL after Purchase" to "Or Custom Redirect URL" for clarity.
  - Added `external_page_id` support to product meta saving and retrieval.

- **WordPress Integration Plugin:**
  - New WordPress plugin "NetSendo for WordPress" for bloggers and content creators.
  - **Subscription Forms:** Shortcode `[netsendo_form]`, sidebar widget, and Gutenberg block with 3 styles (inline, minimal, card).
  - **Content Gating:** Restrict article visibility with percentage-based, subscribers-only, or logged-in modes via `[netsendo_gate]` shortcode and Gutenberg block.
  - Admin settings page with API configuration, default list selection, form styling, GDPR consent settings.
  - Per-post content gate settings in WordPress editor sidebar.
  - AJAX subscription handling with cookie-based content unlock.
  - Frontend and admin CSS/JS assets with modern design system.
  - WordPress marketplace page (`/marketplace/wordpress`) with features overview and plugin download.
  - Download ZIP package available at `/marketplace/wordpress/download`.
  - WordPress added to Marketplace Index with "Active" status.

### Fixed

- **WooCommerce Plugin Compatibility:**
  - Added HPOS (High-Performance Order Storage) compatibility declaration for WooCommerce 8.0+.
  - Added Cart/Checkout Blocks compatibility declaration.
  - Resolves "incompatible plugins" warning in WooCommerce admin.

## [1.2.11] – Short Description

**Release date:** 2026-01-01

### Added

- **WooCommerce Integration:**
  - New WordPress plugin "NetSendo for WooCommerce" for automatic customer subscription after purchase.
  - Plugin features: auto-subscription on order completion, abandoned cart recovery (pending orders), per-product list settings, external page redirects with sales funnel.
  - Admin settings page with NetSendo API connection, dynamic list dropdown with manual ID input option.
  - Product meta box in WooCommerce for overriding default list and redirect settings per product.
  - External pages dropdown to redirect customers to NetSendo sales funnel pages after purchase.
  - Download ZIP package available at `/marketplace/woocommerce/download`.
  - New Laravel webhook controller `WooCommerceController` at `/webhooks/woocommerce` for receiving plugin events.
  - New API endpoint `GET /api/v1/external-pages` for fetching external pages list.
  - New `ExternalPageController` for API external pages access.
  - WooCommerce marketplace page with installation instructions and plugin download.
  - WooCommerce added to E-commerce category in Marketplace with "Active" status.
  - Full translations for WooCommerce integration in PL and EN.

## [1.2.10] – Short Description

**Release date:** 2025-12-31

### Added

- **Campaign Architect Enhancements:**
  - **Campaign Deletion:** Ability to delete campaign plans with option to cascade delete associated email and SMS messages.
  - **Export Success Modal:** New modal with export summary and "Next Steps" guidance after exporting campaigns.
  - **Campaign Filtering:** Emails and SMS messages created from plans are now linked to the campaign and filterable in message lists.
  - **Draft Creation:** Exported messages are now correctly saved as drafts linked to the campaign plan.

- **License Restrictions (SILVER vs GOLD):**
  - **Campaign Limit Enforcement:** Backend validation now strictly enforces the 3-campaign limit for SILVER plan users.
  - **UI Indicators:** Added campaign count badge (e.g., "2/3") in Campaign Architect header for SILVER users.
  - **Create Blockade:** "Create Campaign" button and functionality are disabled when the limit is reached.
  - **Centralized Service:** Implemented `LicenseService` to handle plan capabilities and restrictions.

### Fixed

- **Campaign Advisor Recommendations:**
  - Fixed critical issue where AI recommendations were showing "+0.0% improvement" due to a backend error.
  - Resolved `Call to undefined method User::subscribers()` error by correctly querying subscribers through contact lists.
  - Recommendations (Quick Wins, Strategic) are now correctly generated and displayed with potential impact percentage.
  - **Campaign ROI Calculation:** Fixed issue where ROI was displaying as -100% when projected profit was 0 (now correctly shows 0%).

## [1.2.9] – Sales Funnels Integration

**Release date:** 2025-12-31

### Added

- **Sales Funnels Integration:**
  - Implemented Sales Funnels feature for Stripe and Polar products.
  - New "Sales Funnels" tab in Stripe and Polar product settings.
  - Ability to create sales funnels, assign products, and generate embed codes.
  - **Auto-Subscription:** Automatic mailing list subscription and tagging upon successful purchase.
  - **Flexible Thank You Pages:** Support for default thank-you page, external page redirect, or custom URL.
  - New `SalesFunnel` model, controller, service, and policy.
  - **Embed Code Generator:** JavaScript embed code for external pages (WordPress, ClickFunnels, etc.).
  - Full translations for Sales Funnels in PL, EN, DE, ES.

- **User Model Improvement:**
  - Added missing `externalPages()` relationship to `User` model, fixing "Call to undefined method" error in automations.

- **Integration Documentation Updates:**
  - Added comprehensive webhook setup instructions for Stripe (API Keys) and Polar.
  - Added list of required webhook events for both providers.
  - Added webhook URL copying functionality to settings pages.
  - Added OAuth permission requirements (`read_write`) to Stripe Connect setup.
  - Updated translations for setup wizards in PL, EN, DE, ES.

## [1.2.8] – Polar Payment Processor Integration

**Release date:** 2025-12-29

### Added

- **Stripe OAuth (Connect) Integration:**
  - Added new OAuth-based connection method for Stripe alongside existing API key entry.
  - New `StripeOAuthController` handling OAuth authorization flow, callback token exchange, and disconnection.
  - Redesigned Stripe Settings page with connection mode toggle (OAuth vs Manual API Keys).
  - **In-panel OAuth setup wizard** with 4 steps: Create Connect app, add Redirect URI, paste Client ID, connect account.
  - Client ID can be configured directly in the UI (stored in database) - no `.env` editing required.
  - Auto-generated Redirect URI with copy-to-clipboard functionality.
  - "Connect with Stripe" button for quick one-click Stripe account linking.
  - Connected account info display with Stripe Account ID.
  - Disconnect functionality to remove OAuth connection.
  - Full translations for OAuth setup wizard in PL, EN, DE, ES.

- **Polar Payment Processor Integration:**
  - Implemented full Polar integration for handling digital product sales and subscriptions.
  - New `PolarService` for interacting with Polar API, including product management, checkout sessions, and webhook verification.
  - Created `PolarProduct` model and controller for managing Polar products.
  - Created `PolarTransaction` model for tracking payment history.
  - Added Vue components for Product Management (`PolarProducts/Index.vue`) and Settings (`PolarSettings/Index.vue`).
  - Added **Polar Settings** page in the panel (Settings → Polar) to configure API access token and webhook secret.
  - Added **Polar Products** page in the panel (Products → Polar Products) to manage digital products.
  - Environment selection (Sandbox/Production) for testing and live modes.
  - Webhook endpoint `/webhooks/polar` for receiving Polar events.
  - Webhook signature verification for security.
  - Sensitive API tokens are encrypted in the database.
  - Added new sidebar menu items for Polar Products and Polar Settings.
  - Added comprehensive translations for Polar features in PL, EN, DE, ES.
  - Updated Marketplace page to show Polar as "Available" integration.
  - New Polar marketplace detail page (`/marketplace/polar`) with features overview and setup instructions.

- **Marketplace Improvements:**
  - Added "Active Integrations" section showing implemented integrations (n8n, Stripe, SendGrid, Twilio, SMSAPI, OpenAI).
  - Green indicators for active/available integrations.
  - New Stripe integration detail page (`/marketplace/stripe`).
  - Added Polar to payments section (coming soon).
  - Fixed documentation links to use `netsendo.com/en/docs`.
  - **Request Integration Modal:**
    - Implementation of a dedicated modal form for users to request new integrations.
    - Fields: Integration Name, Description, Priority.
    - Submits request payload with user context to central webhook.
    - Full translations for the modal in PL, EN, DE, ES.

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
