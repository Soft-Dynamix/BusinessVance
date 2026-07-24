# BusinessVance Services Manager — Complete Project Context

> **This file is the single source of truth for any AI agent (including a new Z.ai chat) to fully understand this project without guessing.**
> 
> **Last updated: 2026-07-21 (post Phase 2 + Client Portal separation)**

---

## 1. WHAT THIS PROJECT IS

This is a **BusinessVance** management platform consisting of two parts:

1. **Next.js 16 Admin Panel** (`/` route) — A functional admin panel that mirrors the WordPress plugin admin. Used for preview and development.
2. **WordPress Plugin** (`businessvance-services-manager/`) — The actual production-ready WordPress plugin (PHP).
3. **Client Portal** (`/client-portal` route) — A standalone client-facing page where clients can manage their projects, sign agreements, fill questionnaires, and upload documents.

**The WordPress plugin is the production target.** The Next.js app is a reference prototype.

---

## 2. TECH STACK

| Layer | Technology |
|---|---|
| Framework | Next.js 16 with App Router, TypeScript 5 |
| Styling | Tailwind CSS 4 with shadcn/ui (New York style), Lucide icons |
| Database | Prisma ORM + SQLite (file: `db/custom.db`) |
| State | React useState/useCallback/useMemo |
| Drag & Drop | @dnd-kit/core + @dnd-kit/sortable |
| Font | Geist Sans + Geist Mono via next/font/google |
| Runtime | Bun (not Node) |
| Validation | Zod (some API routes), custom validator (core) |
| Toast | Sonner (layout.tsx), custom Toast (page.tsx admin) |

---

## 3. COMPLETE PROJECT STRUCTURE

```
/home/z/my-project/
├── .env.example                 # DATABASE_URL template
├── .gitignore
├── Caddyfile                    # Reverse proxy config (internal)
├── bun.lock
├── components.json              # shadcn/ui config (New York style)
├── eslint.config.mjs
├── next.config.ts               # output: "standalone", reactStrictMode: false
├── package.json
├── postcss.config.mjs
├── tsconfig.json
├── prisma/
│   ├── schema.prisma            # Database schema (20 models, 361 lines)
│   └── seed.ts                  # Seed data (590 lines: settings, categories, services, plans, icons, test project)
├── public/
│   ├── bv-frontend.css          # CSS with custom properties for the WP frontend
│   ├── bv-logo.jpeg             # Default BusinessVance logo
│   ├── logo.svg                 # SVG logo
│   └── robots.txt
├── upload/
│   ├── mockup-analysis.json     # VLM analysis of original mockup
│   ├── mockup-detail.json       # VLM detail analysis of mockup
│   ├── preview-desktop.png      # (gitignored)
│   └── preview-mobile.png       # (gitignored)
├── src/
│   ├── app/
│   │   ├── globals.css          # Tailwind imports + brand CSS variables + custom scrollbar
│   │   ├── layout.tsx           # Root layout (Geist fonts, Sonner Toaster)
│   │   ├── page.tsx             # Admin panel (~1406 lines, 9 tabs, all inline)
│   │   ├── client-portal/
│   │   │   └── page.tsx         # Standalone client portal (~614 lines)
│   │   └── api/                 # 22 API route files
│   │       ├── route.ts         # Default /api (Hello World, unused)
│   │       ├── settings/route.ts
│   │       ├── services/
│   │       │   ├── route.ts          # GET (list) / POST (create)
│   │       │   ├── [id]/route.ts     # GET/PUT/DELETE single service
│   │       │   └── reorder/route.ts  # PUT reorder
│   │       ├── plans/
│   │       │   ├── route.ts          # GET/POST/PUT/DELETE
│   │       │   ├── [id]/route.ts     # GET/PUT/DELETE single plan
│   │       │   └── reorder/route.ts  # PUT reorder
│   │       ├── categories/
│   │       │   ├── route.ts          # GET/POST/PUT/DELETE
│   │       │   └── [id]/route.ts     # PUT/DELETE single category
│   │       ├── icons/route.ts        # GET/POST/PUT/DELETE (full CRUD)
│   │       ├── projects/
│   │       │   ├── route.ts          # GET (list, filter, search) / POST (create)
│   │       │   └── [id]/
│   │       │       ├── route.ts      # GET/PUT/DELETE single project
│   │       │       ├── agreement/route.ts  # POST (sign agreement)
│   │       │       ├── documents/route.ts  # GET/POST/DELETE project docs
│   │       │       └── questionnaire/route.ts  # POST (save responses)
│   │       ├── agreement-templates/
│   │       │   ├── route.ts          # GET/POST list + create
│   │       │   └── [id]/route.ts     # GET/PUT/DELETE single template
│   │       ├── questionnaire-templates/
│   │       │   ├── route.ts          # GET (with sections+questions) / POST
│   │       │   └── [id]/route.ts     # GET/PUT/DELETE with nested rebuild
│   │       ├── report-templates/route.ts  # GET/POST (no [id] route yet)
│   │       └── activity-log/route.ts     # GET (filter by project/entity) / POST
│   ├── components/
│   │   ├── ui/                   # shadcn/ui components (48 files, DO NOT modify)
│   │   └── admin/
│   │       ├── projects-tab.tsx       # Projects management tab (~1113 lines)
│   │       ├── questionnaire-builder.tsx  # Questionnaire template builder (~942 lines)
│   │       ├── agreement-builder.tsx  # Agreement template builder (~505 lines)
│   │       └── client-portal.tsx      # OLD embedded portal (463 lines, NO LONGER IMPORTED)
│   ├── hooks/
│   │   ├── use-mobile.ts
│   │   └── use-toast.ts
│   └── lib/
│       ├── db.ts                # Prisma singleton
│       ├── utils.ts             # cn() helper
│       ├── interfaces/
│       │   ├── types.ts          # Shared interfaces & enums (189 lines)
│       │   └── index.ts          # Barrel export
│       ├── core/                # Phase 2: Core Platform Foundation
│       │   ├── bv-constants.ts   # Platform constants (241 lines)
│       │   ├── bv-logger.ts      # Singleton logger (191 lines)
│       │   ├── bv-events.ts      # Pub/sub event bus (211 lines)
│       │   ├── bv-validator.ts   # Input validation (272 lines)
│       │   ├── bv-response.ts    # Standardised API responses (181 lines)
│       │   ├── bv-capabilities.ts # 28 capabilities, 3 roles (282 lines)
│       │   ├── bv-helper.ts      # 17 utility functions (269 lines)
│       │   └── index.ts          # Barrel export
│       ├── api/                 # Phase 2: API foundation
│       │   ├── base-controller.ts # Abstract CRUD controller (189 lines)
│       │   ├── api-helpers.ts    # Params, permissions, headers (137 lines)
│       │   └── index.ts          # Barrel export
│       └── services/            # Phase 2: Services
│           ├── notification-service.ts  # Multi-channel notifications (174 lines)
│           ├── database-versioning.ts   # Migration framework (176 lines)
│           └── index.ts          # Barrel export
├── businessvance-services-manager/   # THE ACTUAL WORDPRESS PLUGIN
│   ├── businessvance-services-manager.php  # Main plugin file
│   ├── uninstall.php
│   ├── includes/
│   │   ├── class-bv-activator.php       # DB table creation + seed
│   │   ├── class-bv-admin.php           # Admin menu registration
│   │   ├── class-bv-admin-dashboard.php # Dashboard page
│   │   ├── class-bv-admin-services.php  # Services list
│   │   ├── class-bv-admin-services-new.php  # Service add/edit form
│   │   ├── class-bv-admin-plans.php     # Plans list + form
│   │   ├── class-bv-admin-categories.php # Categories list + form
│   │   ├── class-bv-settings.php        # Settings API integration
│   │   ├── class-bv-ajax.php            # AJAX handlers (reorder, toggle, WC search)
│   │   ├── class-bv-shortcode.php       # [businessvance_services] shortcode
│   │   ├── class-bv-shortcodes.php      # Additional shortcodes
│   │   └── class-bv-woocommerce.php     # WooCommerce integration
│   ├── assets/
│   │   ├── css/
│   │   │   ├── frontend.css     # Public-facing CSS
│   │   │   └── admin.css        # Admin panel CSS
│   │   ├── js/
│   │   │   ├── frontend.js      # Scroll animations, category filter
│   │   │   └── admin.js         # Drag-drop, AJAX, modals
│   │   └── img/
│   │       └── default-logo.jpeg
│   └── templates/
│       └── services-page.php   # Frontend template
└── worklog.md                   # Detailed work history (append-only)
```

---

## 4. DATABASE SCHEMA (Prisma) — 20 MODELS

Full schema in `prisma/schema.prisma` (361 lines).

### Phase 1 Models (Core Services)

**Category** — `id` (cuid), `name`, `slug` (unique), `color` (#002B5C), `createdAt`, `updatedAt`. Has many: Service[], Plan[].

**Service** — `id`, `name`, `slug` (unique), `shortDescription`, `description`, `price` (Float), `icon`, `image`, `color`, `turnaround` (Int, days), `buttonLabel`, `buttonType` (cart|quote|booking|link), `buttonUrl`, `woocommerceProductId`, `categoryId` (optional, SetNull), `visible` (bool), `featured` (bool), `displayOrder`, `questionnaireTemplateId` (optional, ServiceQuestionnaire relation), `agreementTemplateId` (optional, ServiceAgreement relation), `requiredDocuments` (JSON string: `[{name, required, category}]`), `createdAt`, `updatedAt`.

**Plan** — `id`, `name`, `slug` (unique), `subtitle`, `price` (Float), `color`, `buttonLabel`, `buttonType`, `buttonUrl`, `woocommerceProductId`, `categoryId` (optional, SetNull), `visible`, `featured`, `displayOrder`, `createdAt`, `updatedAt`. Has many: PlanFeature[].

**PlanFeature** — `id`, `text`, `planId` (Cascade).

**PluginSetting** — `id`, `key` (unique), `value` (String), `updatedAt`.

**Icon** — `id`, `name` (unique), `label`, `svgPath`, `category` (default 'general'), `displayOrder`, `createdAt`, `updatedAt`.

### Phase 2 Models (Questionnaire Builder)

**QuestionnaireTemplate** — `id`, `name`, `slug` (unique), `description`, `version`, `status` (draft|published|archived), `createdAt`, `updatedAt`. Has many: QuestionnaireSection[], Service[] (ServiceQuestionnaire), ProjectQuestionnaire[].

**QuestionnaireSection** — `id`, `templateId` (Cascade), `title`, `description`, `displayOrder`, `isShared` (bool, for cross-service deduplication), `createdAt`. Has many: QuestionnaireQuestion[].

**QuestionnaireQuestion** — `id`, `sectionId` (Cascade), `type` (text|textarea|select|multiselect|number|email|phone|date|file|radio|checkbox|heading|paragraph), `label`, `placeholder`, `required`, `options` (JSON array), `conditionalOn` (question ID), `conditionalValue`, `helpText`, `displayOrder`, `createdAt`.

### Phase 2 Models (Agreement Templates)

**AgreementTemplate** — `id`, `name`, `slug` (unique), `content` (HTML), `version`, `status` (draft|published|archived), `createdAt`, `updatedAt`. Has many: Service[] (ServiceAgreement), ProjectAgreement[].

### Phase 2 Models (Client Projects)

**Project** — `id`, `projectNumber` (unique, BV-YYYY-NNNNNN), `clientName`, `clientEmail`, `clientPhone`, `clientCompany`, `woocommerceOrderId`, `status` (awaiting-agreement|awaiting-questionnaire|awaiting-documents|information-review|in-progress|quality-check|completed|delivered|archived), `progressPercent` (Int), `notes`, `assignedTo`, `internalNotes`, `createdAt`, `updatedAt`. Has many: ProjectService[], ProjectAgreement[], ProjectQuestionnaire[], ProjectDocument[], ProjectReport[], ProjectMessage[], ProjectNote[], ActivityLog[].

**ProjectService** — `id`, `projectId` (Cascade), `serviceId`, `status`, `createdAt`.

**ProjectAgreement** — `id`, `projectId` (Cascade), `templateId`, `fullName`, `ipAddress`, `userAgent`, `agreedAt`, `createdAt`.

**ProjectQuestionnaire** — `id`, `projectId` (Cascade), `templateId`, `status` (pending|in-progress|completed), `completedAt`, `createdAt`, `updatedAt`. Has many: ProjectResponse[].

**ProjectResponse** — `id`, `questionnaireId` (Cascade), `questionId`, `value`, `createdAt`.

**ProjectDocument** — `id`, `projectId` (Cascade), `name`, `filename`, `filepath`, `filesize`, `mimeType`, `category` (company-registration|id|financial|logo|branding|report|other), `uploadedBy`, `createdAt`.

### Phase 3 Models (Activity Log)

**ActivityLog** — `id`, `projectId` (optional, SetNull), `entityType` (project|service|questionnaire|agreement|document|report|message|note|template|setting), `entityId`, `action` (created|updated|deleted|signed|uploaded|delivered|status_changed|message_sent|note_added|assigned), `description`, `metadata` (JSON), `userId`, `createdAt`.

### Phase 3 Models (Reports)

**ReportTemplate** — `id`, `name`, `slug` (unique), `description`, `content` (HTML/markdown), `version`, `status` (draft|ready|delivered), `createdAt`, `updatedAt`. Has many: ProjectReport[].

**ProjectReport** — `id`, `projectId` (Cascade), `templateId` (optional, SetNull), `title`, `content`, `status` (draft|ready|delivered), `version`, `deliveredAt`, `createdAt`, `updatedAt`.

### Phase 3 Models (Messaging)

**ProjectMessage** — `id`, `projectId` (Cascade), `senderType` (admin|client), `senderName`, `senderEmail`, `message`, `read` (bool), `createdAt`.

### Phase 3 Models (Internal Notes)

**ProjectNote** — `id`, `projectId` (Cascade), `authorName`, `content`, `createdAt`.

---

## 5. ALL API ENDPOINTS (22 route files)

### Core CRUD

| Method | Path | Purpose |
|---|---|---|
| GET/PUT | `/api/settings` | Get merged settings / upsert settings |
| GET/POST | `/api/services` | List (?all=true) / Create service |
| GET/PUT/DELETE | `/api/services/[id]` | Single service CRUD |
| PUT | `/api/services/reorder` | Reorder services |
| GET/POST/PUT/DELETE | `/api/plans` | List / Create / Update / Delete plans |
| GET/PUT/DELETE | `/api/plans/[id]` | Single plan CRUD |
| PUT | `/api/plans/reorder` | Reorder plans |
| GET/POST/PUT/DELETE | `/api/categories` | List / Create / Update / Delete categories |
| PUT/DELETE | `/api/categories/[id]` | Update / Delete single category |
| GET/POST/PUT/DELETE | `/api/icons` | Full icon CRUD |

### Project Workflow

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/api/projects` | List (filter by status, search) / Create project |
| GET/PUT/DELETE | `/api/projects/[id]` | Single project CRUD |
| POST | `/api/projects/[id]/agreement` | Sign agreement (auto-advances status) |
| GET/POST/DELETE | `/api/projects/[id]/documents` | List / Upload / Delete project documents |
| POST | `/api/projects/[id]/questionnaire` | Save questionnaire responses |

### Templates

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/api/agreement-templates` | List / Create agreement templates |
| GET/PUT/DELETE | `/api/agreement-templates/[id]` | Single agreement template CRUD |
| GET/POST | `/api/questionnaire-templates` | List (with sections+questions) / Create |
| GET/PUT/DELETE | `/api/questionnaire-templates/[id]` | Single template CRUD (nested rebuild on update) |
| GET/POST | `/api/report-templates` | List / Create report templates (NO [id] route yet) |

### Other

| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/api/activity-log` | List (filter by projectId, entityType) / Create log entry |

---

## 6. ADMIN PANEL (page.tsx) — 9 TABS

`src/app/page.tsx` is ~1406 lines, monolithic (all types, components, state inline).

### Architecture
- **All types defined inline**: Settings, CategoryItem, ServiceItem, PlanItem, IconItem, TabId
- **Helper components inline**: SortableRow, CopyBtn, SettingsCard, FormField, ColorField, Toast, BvIcon
- **Uses QuestionnaireBuilder and AgreementBuilder** from `src/components/admin/`
- **Uses ProjectsTab** from `src/components/admin/projects-tab.tsx`
- **No external component imports** beyond shadcn/ui, lucide-react, and the 3 admin components above

### The 9 Tabs

1. **Dashboard** — Stat cards (total services, plans, projects, active projects, completed, revenue), quick links to Projects and Client Portal, Quick Start guide
2. **Services** — Sortable table with drag handles, category dots, prices, visibility/featured toggles. Modal form: name, description, shortDescription, price, icon (dropdown from DB), turnaround days, button type/label/url, WC product ID, category, questionnaire template, agreement template, required documents (JSON editor), visible, featured
3. **Subscription Plans** — Sortable table with color swatches. Modal: name, subtitle, price, color picker, button label, WC product ID, category, visible, featured, dynamic features list (add/remove)
4. **Categories** — Table with name, slug (auto-generated), color swatch, service/plan counts. Edit modal
5. **Icon Manager** — Table with live SVG preview, name, label, category badge. Add/edit modal: name (slug), label, category dropdown (7 categories), SVG path textarea with live preview. Full CRUD
6. **Questionnaires** — Full questionnaire builder component (`src/components/admin/questionnaire-builder.tsx`, 942 lines). Create/edit/delete templates with sections and questions. Supports 13 question types, drag-reorder, conditional logic, shared sections
7. **Agreements** — Agreement template builder (`src/components/admin/agreement-builder.tsx`, 505 lines). Create/edit/delete templates with HTML content editor, preview mode, service linking
8. **Projects** — Project management tab (`src/components/admin/projects-tab.tsx`, 1113 lines). Create projects, view details, manage agreements, questionnaires, documents, status transitions, internal notes
9. **Settings** — 8 grouped cards: Branding, Colors, Services, Plans, Currency, Footer, Trust Badges, Layout. Logo upload, all brand colors configurable

### Key Patterns
- **Icons are 100% dynamic** — loaded from DB via `/api/icons`, stored in `iconMap` (Record<name, svgPath>), used by `BvIcon` component everywhere
- **Drag-and-drop** uses @dnd-kit with `SortableContext` and `useSortable`
- **Toast notifications** via custom `Toast` component in page.tsx (not sonner)
- **All forms** use controlled components with `Record<string, unknown>` state

### Styling
- WP admin style: dark sidebar (#1d2327), light content area (#f0f0f1)
- Gold accent: #D4AF37 for primary buttons
- Navy: #002B5C for headings
- All brand colors inline via `style={{}}`

---

## 7. CLIENT PORTAL (standalone page)

`src/app/client-portal/page.tsx` — 614 lines, completely separate from admin.

### Features
- Professional header with BusinessVance branding, notification bell, user avatar, mobile hamburger menu
- Project selector bar (appears when client has multiple projects)
- Welcome header with project number and client name
- Project progress checklist (6 steps: Payment → Agreement → Questionnaire → Documents → Report → Delivered)
- My Services cards with progress bars and status badges
- Agreement status card (signed/unsigned with details)
- Questionnaire accordion with 6 sections, section locking (complete previous to unlock next)
- Document upload center with 4 document categories
- Client portal footer with contact details
- Responsive layout (auto-fit grids)
- Falls back to demo data when no real projects exist
- Uses sonner toast directly

### Key Difference from Admin
- Has its OWN header/footer (no admin sidebar)
- Uses `toast` from sonner (not the admin's custom toast)
- No admin controls or settings access

---

## 8. PHASE 2 CORE PLATFORM FOUNDATION

14 new files in `src/lib/` — internal architecture modules, NO user-facing changes.

### `src/lib/interfaces/types.ts` (189 lines)
Shared TypeScript interfaces & enums:
- EventCallback, FilterCallback, EventSubscription
- LogLevel (DEBUG/INFO/WARNING/ERROR), LogEntry, LoggerConfig
- ValidationResult, FieldRule
- ApiResponse (with pagination meta)
- BvRole enum (administrator/consultant/client), Capability
- NotificationChannel (email/dashboard/sms/push), NotificationPayload, NotificationResult, NotificationTransport
- MigrationRecord, MigrationDefinition
- PaginationParams, QueryResult

### `src/lib/core/bv-constants.ts` (241 lines)
- Platform identity: BV_PLATFORM_NAME='BusinessVance', BV_PLATFORM_VERSION='2.0.0', BV_API_NAMESPACE='businessvance/v1', BV_DB_VERSION='2.0.0'
- PROJECT_STATUSES: 10 states (order-received, project-created, awaiting-agreement, awaiting-questionnaire, awaiting-documents, information-review, in-progress, quality-check, completed, delivered, archived)
- PROJECT_STATUS_LABELS, PROJECT_STATUS_PROGRESS, PROJECT_STATUS_SEQUENCE
- TEMPLATE_STATUSES, QUESTION_TYPES (13), BUTTON_TYPES, DOCUMENT_CATEGORIES (7)
- ACTIVITY_ACTIONS (10), ACTIVITY_ENTITY_TYPES (10), REPORT_STATUSES
- BV_EVENTS: 22 event constants (18 actions + 4 filters)
- Pagination defaults, project number format (BV-YYYY-NNNNNN), roles

### `src/lib/core/bv-logger.ts` (191 lines)
Singleton logger: `logger.info()`, `logger.warning()`, `logger.error()`, `logger.debug()`. 1000-entry buffer. Context-scoped: `logger.withContext('ModuleName')`. Colored console output.

### `src/lib/core/bv-events.ts` (211 lines)
Singleton event bus: `events.on()`, `events.once()`, `events.off()`, `events.offAll()`, `events.dispatch()` (fire-and-forget), `events.applyFilter()` (transform-and-return). Priority-sorted listeners.

### `src/lib/core/bv-validator.ts` (272 lines)
Singleton validator: 9 single-field validators (email, phone, URL, integer, price, required, WooCommerceProductId, slug, projectNumber) + `validateFields(data, rules)` multi-field engine + `parseJsonSafe()`.

### `src/lib/core/bv-response.ts` (181 lines)
Singleton response builder: `bvResponse.success()`, `successMessage()`, `successWithData()`, `paginated()`, `error()`, `badRequest()`, `unauthorized()`, `forbidden()`, `notFound()`, `serverError()`, `validationError()`. Auto-logs errors.

### `src/lib/core/bv-capabilities.ts` (282 lines)
28 capability definitions across 3 roles. `capabilities.can(role, cap)`, `canAll()`, `canAny()`, `require()` (throws). `getCapabilitiesForRole()`. Deny-by-default for unknown capabilities.

### `src/lib/core/bv-helper.ts` (269 lines)
17 utility functions: generateSlug, generateProjectNumber, sanitizeString, truncate, parseSafeFloat, parseSafeInt, formatCurrency, formatDate, timeAgo, isValidStatusTransition, isForwardTransition, getNextStatus, parseJsonSafe, pick, omit, groupBy, cleanObject, formatFileSize.

### `src/lib/api/base-controller.ts` (189 lines)
Abstract `BaseController<T>` class: `extractPagination()`, `buildPagination()`, `buildOrderBy()`, `parseBody()`, `extractId()` (handles Next.js 16 Promise params), `dispatchCreated/Updated/Deleted()`, `handleErrors()` wrapper, `paginatedFind()`.

### `src/lib/api/api-helpers.ts` (137 lines)
`getApiNamespace()`, `getApiPath(resource, id?)`, `getStringParam()`, `getBoolParam()`, `getNumericParam()`, `checkPermission()`, `requireAdmin()`, `getApiHeaders()`.

### `src/lib/services/notification-service.ts` (174 lines)
Singleton notification service: `registerTransport()`, `unregisterTransport()`, `notify()`, `notifyMulti()`. Uses FILTER_NOTIFICATION_PAYLOAD hook. Enable/disable toggle.

### `src/lib/services/database-versioning.ts` (176 lines)
Migration framework: `register(migration)`, `runMigrations()`, `getPendingMigrations()`, `getCurrentVersion()`, `isUpToDate()`. Version tracked in PluginSetting table.

### Barrel Exports
- `src/lib/core/index.ts` — exports all core modules
- `src/lib/api/index.ts` — exports BaseController and all helpers
- `src/lib/services/index.ts` — exports notificationService and dbVersion
- `src/lib/interfaces/index.ts` — exports all types

---

## 9. PLUGIN SETTINGS (29 configurable keys)

All stored as key/value strings in `PluginSetting` table. Defaults in `/api/settings/route.ts`.

### Branding
- `brand_name` ("BUSINESSVANCE"), `brand_tagline` ("INSIGHT. STRATEGY. SUCCESS."), `brand_description`, `header_icon` ("shield"), `logo_url`

### Colors
- `color_primary` (#0A2647), `color_primary_dark` (#071a33), `color_primary_light` (#144272)
- `color_accent` (#F4A261), `color_accent_alt` (#2A9D8F), `color_gold` (#D4AF37)
- `color_text` (#333333), `color_text_light` (#666666), `color_bg` (#ffffff)

### Sections
- `services_section_title`, `services_section_subtitle`, `show_services_section`
- `plans_section_title`, `plans_section_subtitle`, `show_plans_section`

### Currency
- `currency_symbol` ("R"), `currency_position` ("before")

### Footer
- `footer_company_name`, `footer_website`, `footer_phone`, `footer_email`, `footer_copyright`

### Layout
- `container_max_width` ("1200px"), `enable_animations` ("true"), `show_trust_badges` ("true"), `show_featured_badge` ("true"), `default_service_button_label` ("ADD TO CART")

### Trust Badges
- `trust_badges` — JSON array of 4 badges (shield-check, clock, lock, star)

---

## 10. ICON SYSTEM

22 icons seeded in `prisma/seed.ts` across 7 categories:
- **Business**: clipboard-list, file-text, presentation, handshake
- **Finance**: calculator, trending-up
- **General**: clock, search, heart-pulse, wrench
- **Marketing**: megaphone
- **People**: users
- **Security**: shield, shield-check, lock, shield-alert
- **Status**: check-circle, award, star, crown, check

The `BvIcon` component in page.tsx renders SVG icons by looking up `iconMap[name]`. Falls back to a document icon if not found.

---

## 11. PROJECT LIFECYCLE (10 States)

```
order-received → project-created → awaiting-agreement → awaiting-questionnaire → 
awaiting-documents → information-review → in-progress → quality-check → 
completed → delivered → archived
```

Each status has a default progress percentage (0, 5, 10, 25, 50, 60, 75, 90, 100, 100, 100).

### 22 Internal Events
**Actions (18)**: INITIALIZED, PROJECT_CREATED/UPDATED/DELETED/STATUS_CHANGED, QUESTIONNAIRE_COMPLETED/ASSIGNED, AGREEMENT_SIGNED/ASSIGNED, DOCUMENT_UPLOADED/DELETED, REPORT_UPLOADED/DELIVERED, MESSAGE_SENT, NOTE_ADDED, NOTIFICATION_SENT/DISPATCHED, TEMPLATE_CREATED/UPDATED

**Filters (4)**: FILTER_PROJECT_NUMBER, FILTER_SLUG, FILTER_API_RESPONSE, FILTER_NOTIFICATION_PAYLOAD

### 28 Capabilities across 3 Roles
- **Administrator** (14): manage_settings, manage_categories, manage_icons, manage_services, manage_plans, manage_questionnaire_templates, manage_agreement_templates, manage_report_templates, manage_workflow_templates, manage_notification_templates, view_all_projects, create_projects, delete_projects, assign_consultant, view_analytics, deliver_reports
- **Consultant** (8): view_assigned_projects, edit_projects, change_project_status, upload_documents, view_internal_notes, add_internal_notes, upload_reports, send_messages
- **Client** (6): view_own_projects, sign_agreement, fill_questionnaire, upload_documents, view_reports, send_messages

---

## 12. WORDPRESS PLUGIN (`businessvance-services-manager/`)

A complete, production-ready WordPress plugin:
- **4 custom DB tables**: bv_categories, bv_services, bv_plans, bv_plan_features
- **Admin panel**: 5 menu pages (Dashboard, Services, Plans, Categories, Settings)
- **3 shortcodes**: `[businessvance_services]`, `[businessvance_onceoff]`, `[businessvance_subscriptions]`
- **WooCommerce integration**: add-to-cart URLs, product linking, optional dependency
- **AJAX handlers**: drag-drop reorder, visibility toggle, WC product search
- **Responsive frontend**: desktop table → mobile cards at 768px
- **Trust badges, animations, CSS custom properties**
- **PHP files**: 4,428 lines total across 13 files
- **CSS/JS assets**: 1,673 lines total

The plugin has its own code and does NOT depend on the Next.js app.

---

## 13. ARCHITECTURE DECISIONS (from Phase 1.5 Blueprint)

1. **WordPress plugin is production platform** — Next.js is reference prototype only
2. **Project is central entity** — everything links to Project
3. **10-state lifecycle** — refined from original 9 states
4. **Event-driven architecture** — 22 events for loose coupling
5. **3 user applications**: Admin Dashboard, Consultant Dashboard (not yet built), Client Portal (standalone)
6. **API namespace**: `businessvance/v1`
7. **Database versioning** via PluginSetting table
8. **Phase 2 core foundation** is built but NOT yet wired into existing API routes
9. **No authentication** in the Next.js prototype (WordPress handles auth in production)
10. **SOLID/DRY principles** with singleton pattern for core services

### Development Roadmap
- ✅ Phase 1: Core services (categories, services, plans, icons, settings)
- ✅ Phase 1.5: Architecture blueprint (analysis only)
- ✅ Phase 2: Core platform foundation (14 new lib files, 0 existing files modified)
- ✅ Client Portal separated from admin into standalone `/client-portal` page
- 🔲 Phase 2B: Wire core foundation into existing API routes (use bvResponse, validator, events, etc.)
- 🔲 Phase 2C: Consultant Dashboard
- 🔲 Phase 2D: Notification system (actual transport implementations)
- 🔲 Phase 2E: Client Portal production (real API integration, replace demo data)
- 🔲 Phase 2F: WooCommerce integration in Next.js prototype
- 🔲 Phase 3: Reports, Messages, Notes, Activity Timeline UI

### What's Missing / TODO
- `report-templates/[id]` API route (only list/create exists)
- `projects/[id]/reports` API route
- `projects/[id]/messages` API route
- `projects/[id]/notes` API route
- Activity Timeline UI in admin and client portal
- Reports tab in admin
- Messages tab in admin
- Wire Phase 2 core modules (bvResponse, validator, events, capabilities) into existing API routes
- Consultant Dashboard page
- Actual notification transport implementations (email, SMS, push)
- Database migration execution on startup

---

## 14. IMPORTANT RULES FOR AI AGENTS

1. **Two visible routes**: `/` (admin, 9 tabs) and `/client-portal` (standalone client page)
2. **Port 3000 only** — Dev server runs on port 3000. Never use other ports for the Next.js app.
3. **Use API routes** — All backend logic goes in `src/app/api/`, NOT server actions.
4. **z-ai-web-dev-sdk** — MUST be used in backend only, never client-side.
5. **No indigo/blue** unless user requests it.
6. **shadcn/ui** — Use existing components in `src/components/ui/`. Don't build from scratch.
7. **Don't remove dynamic functionality** — Icons, settings, and data are all dynamic from the database.
8. **Page.tsx is monolithic** — All admin UI is in one file with inline helper components.
9. **Sticky footer** — If footer exists, it must stick to bottom on short pages.
10. **Browser verify** — After making changes, verify in the Preview Panel (not localhost URL).
11. **ESLint** — Run `bun run lint` to check. Config is very permissive (most rules off).
12. **Phase 2 core modules exist** but are NOT yet used by existing API routes. They're ready to be wired in.
13. **Client Portal is separate** — It's at `/client-portal`, NOT a tab in admin. The old `src/components/admin/client-portal.tsx` still exists but is NOT imported anywhere.
14. **Don't modify shadcn/ui components** in `src/components/ui/`.
15. **Use `use client`** directive for all interactive components.

---

## 15. BRAND COLORS

| Color | Hex | Usage |
|---|---|---|
| Primary Navy | #002B5C | Headings, sidebar active state |
| Primary Dark | #071a33 | Darker variant |
| Primary Light | #144272 | Lighter variant |
| Gold Accent | #D4AF37 | Primary buttons, highlights |
| Gold Dark | #B8962E | Gold hover state |
| Teal Alt | #2A9D8F / #008080 | Alternative accent |
| Orange Accent | #F4A261 / #FF9900 | Warm accent |
| Admin BG | #f0f0f1 | Content background |
| Sidebar BG | #1d2327 | Sidebar background |
| WP Blue | #2271b1 | WP-style links/active items |
| WP Red | #d63638 | Delete/danger actions |

CSS variables defined in `globals.css`: `--bv-navy`, `--bv-gold`, `--bv-gold-dark`, `--bv-teal`, `--bv-white`, `--bv-light-gray`.

---

## 16. HOW TO SET UP FROM SCRATCH

```bash
# 1. Install dependencies
bun install

# 2. Create .env from example
cp .env.example .env
# (DATABASE_URL is already set to file:./db/custom.db)

# 3. Push schema to SQLite
bun run db:push

# 4. Seed the database (settings, categories, services, plans, icons, test project)
bunx prisma db seed

# 5. Start dev server
bun run dev
# Server runs on port 3000
```

---

## 17. FILE SIZE REFERENCE

| File | Lines | Purpose |
|---|---|---|
| src/app/page.tsx | 1,406 | Admin panel (9 tabs, all inline) |
| src/app/client-portal/page.tsx | 614 | Standalone client portal |
| src/components/admin/projects-tab.tsx | 1,113 | Projects management tab |
| src/components/admin/questionnaire-builder.tsx | 942 | Questionnaire template builder |
| src/components/admin/client-portal.tsx | 463 | OLD portal (unused) |
| src/components/admin/agreement-builder.tsx | 505 | Agreement template builder |
| prisma/schema.prisma | 361 | Database schema (20 models) |
| prisma/seed.ts | 590 | Seed data |
| Phase 2 core files (12) | 2,512 | Core platform foundation |
| WP plugin PHP | 4,428 | WordPress plugin |
| WP plugin CSS/JS | 1,673 | WordPress plugin assets |