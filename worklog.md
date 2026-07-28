---
Task ID: 1
Agent: Main Agent
Task: Make preview work for plugin admin dashboard

Work Log:
- Analyzed project structure: Next.js 16 App Router with Prisma ORM (SQLite), 20 models, 22 API routes, 8 admin tabs
- Fixed missing `Textarea` import in `src/components/admin/services-tab.tsx`
- Updated `package.json` dev script: removed `--max-old-space-size=256` memory limit (causing OOM crashes) and replaced `tee` pipe with direct redirect
- Pushed Prisma schema to database (already in sync)
- Ran seed script: 28 settings, 4 categories, 22 icons, 12 services, 3 plans, 3 questionnaire templates, 1 agreement template seeded
- Built Next.js project: 25 routes (17 static + 8 dynamic API routes)
- Discovered IPv4/IPv6 mismatch: Caddy resolves `localhost` to `::1` (IPv6) but Next.js standalone only binds to `0.0.0.0` (IPv4)
- Created IPv6-to-IPv4 proxy at `mini-services/ipv6-proxy/index.js` to bridge the gap
- Verified all API routes return correct data via curl:
  - `/api/stats` → 12 services, 3 plans, 4 categories, 1 project, 3 questionnaires, 1 agreement, 21 icons
  - `/api/services?all=true` → 12 services with full details
  - `/api/categories` → 4 categories
  - `/api/settings` → 28+ settings with defaults
  - `/api/icons` → 21 icons
  - `/api/questionnaire-templates` → 3 templates
  - `/api/agreement-templates` → 1 template
- Verified full HTML render: AdminLayout with header (BusinessVance Admin), sidebar (8 tabs), Dashboard content with stat cards

Stage Summary:
- All code is correct and functional - verified via curl showing HTTP 200 with complete HTML
- Admin dashboard renders with: Dashboard, Services, Plans, Categories, Icons, Templates, Projects, Settings tabs
- Database is seeded with realistic South African business consulting data
- Environment limitation: background Node.js processes cannot persist between Bash tool calls in the sandbox
- IPv6 proxy created to bridge Caddy (IPv6 ::1) → Next.js (IPv4 0.0.0.0:3000)
- Updated watchdog.sh to remove memory limit and add auto-restart logic
- Updated package.json dev script to be more resilient
