# BusinessVance Services Manager — Project Context

> **This file is the single source of truth for any AI agent (including a new Z.ai chat) to fully understand this project without guessing.**

---

## 1. WHAT THIS PROJECT IS

This is a **WordPress plugin preview/admin panel** built with **Next.js 16 (App Router)**. It serves two purposes:
1. A **functional admin panel** that mirrors what the WordPress plugin admin looks like — used for preview and development.
2. A **showcase page** for the BusinessVance Services Manager WordPress plugin.

The actual WordPress plugin lives in `businessvance-services-manager/` and is a separate, self-contained PHP plugin.

**User can only see the `/` route** defined in `src/app/page.tsx`. Do NOT create other routes.

---

## 2. TECH STACK

| Layer | Technology |
|---|---|
| Framework | Next.js 16 with App Router, TypeScript 5 |
| Styling | Tailwind CSS 4 with shadcn/ui (New York style), Lucide icons |
| Database | Prisma ORM + SQLite (file: `db/custom.db`) |
| State | React useState/useCallback/useMemo (no Zustand/TanStack needed here) |
| Drag & Drop | @dnd-kit/core + @dnd-kit/sortable |
| Font | Geist Sans + Geist Mono via next/font/google |
| Runtime | Bun (not Node) |

---

## 3. PROJECT STRUCTURE

```
/home/z/my-project/
├── .env.example                 # DATABASE_URL template
├── .gitignore
├── Caddyfile                    # Reverse proxy config (internal)
├── bun.lock
├── components.json              # shadcn/ui config
├── eslint.config.mjs
├── next.config.ts
├── package.json
├── postcss.config.mjs
├── prisma/
│   ├── schema.prisma            # Database schema (6 models)
│   └── seed.ts                  # Seed data (settings, categories, services, plans, icons)
├── public/
│   ├── bv-frontend.css          # CSS with custom properties for the WP frontend
│   ├── bv-logo.jpeg             # Default BusinessVance logo
│   ├── logo.svg                 # SVG logo
│   └── robots.txt
├── src/
│   ├── app/
│   │   ├── globals.css          # Tailwind imports + global styles
│   │   ├── layout.tsx           # Root layout (Geist fonts, Toaster)
│   │   ├── page.tsx             # THE ENTIRE APP — admin panel (~1370 lines)
│   │   └── api/
│   │       ├── route.ts         # Default /api (unused, can ignore)
│   │       ├── settings/route.ts     # GET/PUT plugin settings
│   │       ├── services/
│   │       │   ├── route.ts          # GET (list) / POST (create) services
│   │       │   ├── [id]/route.ts     # GET/PUT/DELETE single service
│   │       │   └── reorder/route.ts  # PUT reorder services
│   │       ├── plans/
│   │       │   ├── route.ts          # GET (list) / POST (create) plans
│   │       │   ├── [id]/route.ts     # GET/PUT/DELETE single plan
│   │       │   └── reorder/route.ts  # PUT reorder plans
│   │       ├── categories/
│   │       │   ├── route.ts          # GET (list) / POST (create) categories
│   │       │   └── [id]/route.ts     # PUT/DELETE single category
│   │       ├── icons/route.ts        # GET/POST/PUT/DELETE icons (full CRUD)
│   │       └── upload/
│   │           └── logo/route.ts     # POST multipart logo upload
│   ├── components/
│   │   └── ui/                   # shadcn/ui components (40+ files, do not modify)
│   ├── hooks/
│   │   ├── use-mobile.ts
│   │   └── use-toast.ts
│   └── lib/
│       ├── db.ts                # Prisma singleton
│       └── utils.ts             # cn() helper
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

## 4. DATABASE SCHEMA (Prisma)

6 models in `prisma/schema.prisma`:

### Category
- `id` (cuid), `name`, `slug` (unique), `color` (default #002B5C)
- Has many: Service[], Plan[]

### Service
- `id`, `name`, `slug` (unique), `description`, `price` (Float), `icon`
- `buttonLabel`, `buttonType` (cart|quote|booking|link), `buttonUrl`, `woocommerceProductId`
- `categoryId` (optional, onDelete: SetNull), `visible` (bool), `featured` (bool), `displayOrder`

### Plan
- `id`, `name`, `slug` (unique), `subtitle`, `price` (Float), `color`
- `buttonLabel`, `buttonType`, `buttonUrl`, `woocommerceProductId`
- `categoryId` (optional), `visible` (bool), `featured` (bool), `displayOrder`
- Has many: PlanFeature[]

### PlanFeature
- `id`, `text`, `planId` (onDelete: Cascade)

### PluginSetting
- `id`, `key` (unique), `value` (String)

### Icon
- `id`, `name` (unique), `label`, `svgPath`, `category` (default 'general'), `displayOrder`

---

## 5. API ENDPOINTS

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/settings` | Returns merged defaults + DB settings (29 settings) |
| PUT | `/api/settings` | Upserts settings (body: `{ settings: { key: value } }`) |
| GET | `/api/services?all=true` | List all services (with categories). Without `?all=true` returns only visible. |
| POST | `/api/services` | Create service |
| GET | `/api/services/[id]` | Get single service |
| PUT | `/api/services/[id]` | Update service |
| DELETE | `/api/services/[id]` | Delete service |
| PUT | `/api/services/reorder` | Reorder (body: `{ items: [{ id, displayOrder }] }`) |
| GET | `/api/plans?all=true` | List all plans (with features + categories) |
| POST | `/api/plans` | Create plan (auto-generates slug) |
| GET | `/api/plans/[id]` | Get single plan |
| PUT | `/api/plans/[id]` | Update plan |
| DELETE | `/api/plans/[id]` | Delete plan (cascades features) |
| PUT | `/api/plans/reorder` | Reorder plans |
| GET | `/api/categories` | List all categories |
| POST | `/api/categories` | Create category |
| PUT | `/api/categories/[id]` | Update category |
| DELETE | `/api/categories/[id]` | Delete category |
| GET | `/api/icons` | List all icons (ordered: category, displayOrder, name) |
| POST | `/api/icons` | Create icon (auto-slugifies name) |
| PUT | `/api/icons` | Update icon (body includes `id`) |
| DELETE | `/api/icons?id=xxx` | Delete icon by query param |
| POST | `/api/upload/logo` | Upload logo file (multipart, PNG/JPEG/GIF/SVG/WebP, max 2MB) |

---

## 6. PLUGIN SETTINGS (28 configurable keys)

All stored as key/value strings in `PluginSetting` table. Defaults defined in `/api/settings/route.ts`.

### Branding
- `brand_name` — Site brand name (default: "BUSINESSVANCE")
- `brand_tagline` — Tagline (default: "INSIGHT. STRATEGY. SUCCESS.")
- `brand_description` — Description text
- `header_icon` — Icon name for header (default: "shield")
- `logo_url` — Custom logo URL (empty = no logo)

### Colors
- `color_primary`, `color_primary_dark`, `color_primary_light` — Navy blues
- `color_accent` — Orange accent (#F4A261)
- `color_accent_alt` — Teal (#2A9D8F)
- `color_gold` — Gold (#D4AF37)
- `color_text`, `color_text_light`, `color_bg`

### Sections
- `services_section_title`, `services_section_subtitle`, `show_services_section`
- `plans_section_title`, `plans_section_subtitle`, `show_plans_section`

### Currency
- `currency_symbol` (default: "R"), `currency_position` ("before" | "after")

### Footer
- `footer_company_name`, `footer_website`, `footer_phone`, `footer_email`, `footer_copyright`

### Layout
- `container_max_width`, `enable_animations`, `show_trust_badges`, `show_featured_badge`

### Trust Badges
- `trust_badges` — JSON array: `[{"icon":"shield-check","text":"PROFESSIONAL QUALITY REPORTS"}, ...]`

---

## 7. THE ADMIN PANEL (page.tsx)

The entire admin panel is a single `page.tsx` file (~1370 lines). It contains:

### Architecture
- **All types defined inline** at the top (Settings, CategoryItem, ServiceItem, PlanItem, IconItem, TabId)
- **All helper components defined inline** (SortableRow, CopyBtn, SettingsCard, FormField, ColorField, Toast, BvIcon)
- **All state and CRUD logic** in the main `Home` component
- **No external component imports** beyond shadcn/ui and lucide-react

### Tabs (6)
1. **Dashboard** — 6 stat cards, 3 shortcode reference cards with copy buttons, Quick Start guide
2. **Services** — Sortable table with drag handles, category dots, prices, visibility/featured toggles, edit/delete. Modal form with: name, description, price, icon (dropdown from DB), button type/label/url, WC product ID, category, visible, featured.
3. **Subscription Plans** — Sortable table with color swatches. Edit modal with: name, subtitle, price, color picker, button label, WC product ID, category, visible, featured, dynamic features list.
4. **Categories** — Table with name, slug (auto-generated), color swatch, service/plan counts. Edit modal.
5. **Icon Manager** — Table with live SVG preview, name, label, category badge, edit/delete. Add/edit modal with: name (slug), label, category dropdown (7 categories), SVG path textarea with **live preview**. Full CRUD via `/api/icons`.
6. **Settings** — 8 grouped cards (Branding, Colors, Services, Plans, Currency, Footer, Trust Badges, Layout). Logo section has: file upload button (calls `/api/upload/logo`), URL input, live preview with remove button. Save button upserts all settings.

### Key Patterns
- **Icons are 100% dynamic** — loaded from DB via `/api/icons`, stored in `iconMap` (Record<name, svgPath>), used by `BvIcon` component everywhere
- **Service icon dropdowns** pull from the same DB icons
- **Settings header icon dropdown** also uses DB icons
- **Drag-and-drop** uses @dnd-kit with `SortableContext` and `useSortable`
- **Toast notifications** via custom `Toast` component (not sonner — sonner is in layout.tsx but unused by admin panel)
- **All forms** use controlled components with `Record<string, unknown>` state

### Styling
- WP admin style: dark sidebar (#1d2327), light content area (#f0f0f1)
- Gold accent: #D4AF37 for primary buttons
- Navy: #002B5C for headings
- All colors inline via `style={{}}` — no Tailwind color classes for brand colors

---

## 8. ICON SYSTEM

22 icons are seeded in `prisma/seed.ts` across 7 categories:
- **Business**: clipboard-list, file-text, presentation, handshake
- **Finance**: calculator, trending-up
- **General**: clock, search, heart-pulse, wrench
- **Marketing**: megaphone
- **People**: users
- **Security**: shield, shield-check, lock, shield-alert
- **Status**: check-circle, award, star, crown, check

The `BvIcon` component renders SVG icons by looking up `iconMap[name]`. Falls back to a document icon if name not found.

To add a new icon: use the Icon Manager tab in the admin panel, or POST to `/api/icons` with `{ name, label, svgPath, category }`.

---

## 9. HOW TO SET UP FROM SCRATCH

```bash
# 1. Install dependencies
bun install

# 2. Create .env from example
cp .env.example .env
# (DATABASE_URL is already set to file:./db/custom.db)

# 3. Push schema to SQLite
bun run db:push

# 4. Seed the database (settings, categories, services, plans, icons)
bunx prisma db seed

# 5. Start dev server
bun run dev
# Server runs on port 3000
```

---

## 10. WORDPRESS PLUGIN (`businessvance-services-manager/`)

A complete, production-ready WordPress plugin with:
- **4 custom DB tables**: bv_categories, bv_services, bv_plans, bv_plan_features
- **Admin panel**: 5 menu pages (Dashboard, Services, Plans, Categories, Settings)
- **3 shortcodes**: `[businessvance_services]`, `[businessvance_onceoff]`, `[businessvance_subscriptions]`
- **WooCommerce integration**: add-to-cart URLs, product linking, optional dependency
- **AJAX handlers**: drag-drop reorder, visibility toggle, WC product search
- **Responsive frontend**: desktop table → mobile cards at 768px
- **Trust badges, animations, CSS custom properties**

The plugin has its own CSS/JS/PHP files and does NOT depend on the Next.js app.

---

## 11. IMPORTANT RULES FOR AI AGENTS

1. **Only `/` route** — User can only see `src/app/page.tsx`. Do NOT create other routes.
2. **Port 3000 only** — Dev server runs on port 3000. Never use other ports for the Next.js app.
3. **Use API routes** — All backend logic goes in `src/app/api/`, NOT server actions.
4. **z-ai-web-dev-sdk** — MUST be used in backend only, never client-side.
5. **No indigo/blue** unless user requests it.
6. **shadcn/ui** — Use existing components in `src/components/ui/`. Don't build from scratch.
7. **Don't remove dynamic functionality** — Icons, settings, and data are all dynamic from the database.
8. **Page.tsx is monolithic** — All admin UI is in one file. Helper components (SortableRow, CopyBtn, etc.) are defined inline, not in separate files.
9. **Sticky footer** — If footer exists, it must stick to bottom on short pages.
10. **Browser verify** — After making changes, verify in the Preview Panel (not localhost URL).

---

## 12. BRAND COLORS

| Color | Hex | Usage |
|---|---|---|
| Primary Navy | #002B5C | Headings, sidebar active state |
| Primary Dark | #071a33 | Darker variant |
| Primary Light | #144272 | Lighter variant |
| Gold Accent | #D4AF37 | Primary buttons, highlights |
| Teal Alt | #2A9D8F | Alternative accent |
| Orange Accent | #F4A261 | Warm accent |
| Admin BG | #f0f0f1 | Content background |
| Sidebar BG | #1d2327 | Sidebar background |
| WP Blue | #2271b1 | WP-style links/active items |
| WP Red | #d63638 | Delete/danger actions |
