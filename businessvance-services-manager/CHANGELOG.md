# BusinessVance Services Manager — Changelog

## [2.0.7] — 2025-07-14

### Added
- **Per-service document requirements**: New fields on `bv_services` table (`requires_agreement`, `requires_questionnaire`, `required_document_types`, `agreement_template_id`, `consultant_email`) allow configuring which steps are mandatory for each service.
- **Dynamic tab visibility**: Client portal now automatically hides tabs that are not required for a service (e.g., if documents are not required, the Documents tab is hidden).
- **Draft report preview**: Consultant dashboard now shows "Preview / Download" button for draft reports, allowing the consultant to view and verify the report before delivering it to the client.
- **Draft report delete**: Consultant can delete draft reports that haven't been delivered yet.
- **Email notifications to consultant**: When a client signs an agreement, submits a questionnaire, uploads a document, or sends a message, the consultant receives an email notification with project details.
- **Database upgrade system**: Added `BV_Activator::upgrade()` method that automatically runs migration on existing installations when the plugin version changes.
- **Step checklist on Overview**: Client portal overview now shows a visual step-by-step checklist with completion status for each required step.

### Improved
- **Client portal UI redesign**: Complete CSS overhaul with modern design — gradient header, card-based layout, rounded corners, smooth transitions, hover effects, better typography, and proper mobile responsiveness.
- **Responsive design**: Added proper breakpoints for tablet (1024px) and mobile (768px). Sidebar collapses to full-width on mobile, grids stack vertically, and touch-friendly targets.
- **Per-service agreement templates**: Each service can now specify its own agreement template ID. Falls back to the global default template if not set.
- **Smart auto-progression**: After signing the agreement, the project status automatically advances to the next logical step based on what is required (questionnaire → documents → in-progress).

### Technical
- Added `bv_services` columns: `agreement_template_id`, `requires_agreement`, `requires_questionnaire`, `required_document_types`, `consultant_email`
- Added consultant dashboard AJAX handlers: `bv_cd_download_report`, `bv_cd_delete_report`
- Report download uses `Content-Disposition: inline` for PDF preview in browser
- Consultant email resolved per-service first, falls back to WordPress admin email

## [2.0.6] — 2025-07-13

### Fixed
- Settings page tabs now render correctly
- Client portal and consultant dashboard functional

## [2.0.5] — 2025-07-13

### Added
- Complete questionnaire import data extracted from official BusinessVance PDFs
- Market Research Report: 20 sections, ~105 questions
- Business Plan: 24 sections, ~130 questions

## [2.0.4] — 2025-07-12

### Fixed
- Template loading hang bug — import class now loaded on-demand only

## [2.0.3] — 2025-07-12

### Added
- Questionnaire import system with pre-built templates

## [2.0.2] — 2025-07-11

### Fixed
- Fatal error on activation — `BV_SERVICES_MANAGER_VERSION` → `BV_VERSION`

## [2.0.1] — 2025-07-10

### Added
- Settings page with 5 tabs
- Client Portal ([businessvance_client_portal])
- Consultant Dashboard
