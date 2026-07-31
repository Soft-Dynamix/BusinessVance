# Changelog — BusinessVance Services Manager

All notable changes to this plugin will be documented in this file.

## [2.0.3] — 2026-07-31

### Changed
- Added questionnaire import system with Market Research and Business Plan templates


The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.3] — 2025-07-31

### Added
- **Questionnaire Import System** (`class-bv-questionnaire-import.php`)
  - Market Research Report Questionnaire — 20 sections, 100+ questions covering business overview, target customers, competition, pricing, marketing channels, demand validation, market trends and research focus
  - Business Plan Questionnaire — 24 sections, 130+ questions covering business concept, legal structure, ownership, products/services, unique value proposition, industry analysis, target market, competitor analysis, pricing/revenue model, marketing/sales strategy, customer service, location/operations, equipment/technology, management/staffing, startup costs, sales forecast, funding/loans, SWOT analysis, growth strategy and supporting documents
  - Duplicate-safe import — checks by slug, skips existing templates
  - "Import Pre-built Questionnaires" button on Questionnaire admin page
  - AJAX handler `bv_qt_import_questionnaires` for one-click import from admin
  - Import results summary showing sections and questions per template

### Changed
- Bumped plugin version to 2.0.3
- Registered `class-bv-questionnaire-import.php` in main plugin includes

---

## [2.0.1] — 2025-07-31

### Added
- **Settings page** (`class-bv-settings.php`) — full admin settings with 5 tabs:
  - **General** — Company name, tagline, logo upload, brand colors (color picker), contact info
  - **Portal Settings** — Enable/disable portal, welcome message, login gate toggle, section visibility
  - **Agreement** — Enable/disable agreement, rich text editor, signature requirement toggle
  - **Email Notifications** — Project created, agreement ready, report ready (with placeholders)
  - **WooCommerce Integration** — Auto-create projects toggle, order status triggers, WC product linking
- Settings submenu registered under BusinessVance admin menu
- Quick Access buttons on Dashboard linking to Settings and Client Portal
- `BV_Settings::get_settings()` and `BV_Settings::get()` static methods for other classes to use
- `assets/css/settings.css` — professional admin styling with toggle switches
- `assets/js/settings.js` — settings page JS support
- **Reset to Defaults** button on Settings page
- WP Color Picker integration for brand colors
- WP Media Uploader integration for logo/favicon uploads

### Changed
- Updated Quick Start guide to mention Settings configuration as step 1
- `$settings` property declared on main plugin class

### Fixed
- Settings page 404 error — page now properly registered and renders correctly

---

## [2.0.0] — 2025-07-28

### Added
- **Admin Dashboard** — stats overview, shortcode reference, quick start guide
- **Services Management** — CRUD with AJAX, categories, icons, features, WC product linking
- **Subscription Plans** — CRUD with AJAX, feature lists, pricing display
- **Categories** — CRUD with AJAX, color coding
- **Client Portal** — `[businessvance_client_portal]` shortcode
  - WooCommerce login gate (purchase-gated access)
  - Project overview, timeline, agreement signing, questionnaire
  - Document upload/download, report delivery, messaging
  - WC My Account endpoint integration
- **Consultant Dashboard** — `[businessvance_consultant_dashboard]` shortcode
  - Project management, status updates, internal notes
  - Document/report management, messaging
  - HPOS-compatible order URLs
- **WooCommerce Integration**
  - Auto-create projects from completed WC orders
  - HPOS compatibility declared (4 features)
  - Service-to-product linking
- **Frontend Services Page** — `[businessvance_services]` shortcode
  - Full designed services listing with once-off and subscription sections
- **9 Database Tables** — services, plans, categories, features, projects, project_services, questionnaires, agreements, activity_log
- **Shortcodes**: `[businessvance_services]`, `[businessvance_client_portal]`, `[businessvance_consultant_dashboard]`, `[businessvance_onceoff]`, `[businessvance_subscriptions]`

### Technical
- WordPress plugin with singleton pattern
- AJAX-powered admin CRUD operations
- Prisma-style DB tables via custom activator
- WC tested up to 8.5, requires 5.0+
