# Changelog

All notable changes to the NetSendo project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

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
