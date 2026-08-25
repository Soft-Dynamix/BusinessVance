# Changelog

All notable changes to the BusinessVance Services Manager plugin.

## [2.7.66] - 2026-08-25
### Changed
- **[bv_login_page] is now brand-neutral** — title is simply "Sign In" (no company or site name), subtitle says "Access your client portal and courses." Works for both StudyVance and BusinessVance.

## [2.7.65] - 2026-08-25
### Changed
- **[bv_login_page] is now a universal login** — uses WordPress site name (`get_bloginfo('name')`) instead of BV company name setting, so it shows "Welcome to StudyVance" instead of "Welcome to BusinessVance Consulting"
- Logged-in greeting uses user's first name when available

## [2.7.64] - 2026-08-25
### Fixed
- **Login page redirect to Consultant Dashboard**: Consultant dashboard's `woocommerce_login_redirect` filter (priority 99999) was overriding the login page redirect, sending ALL users (including consultants logging in via `[bv_login_page]`) to the Consultant Dashboard instead of back to the login page's destination picker. Now checks for `bv_login_source` hidden field and passes through when login came from `[bv_login_page]`.
- Updated `render_login_page()` docblock to accurately describe the two-step flow (was still describing v2.7.61's auto-redirect)

## [2.7.63] - 2026-08-25
### Changed
- `[bv_login_page]` now uses a two-step flow: **login first**, then choose destination
- When not logged in: shows clean WooCommerce login form (no destination cards)
- After login: shows destination picker with clickable cards for **Client Portal** and **LMS Dashboard**
- Removed automatic role-based redirect — the user chooses where to go after login
- Consultant Dashboard is completely separate (consultants log in via wp-admin)
- Added "Not you? Log out" link on the destination picker
### Fixed
- Client portal "Please log in" message was rendering raw HTML `<a>` tag instead of a clickable link (v2.7.62 fix carried forward)

## [2.7.62] - 2026-08-25
### Fixed
- Client portal "Please log in" message was rendering raw HTML `<a>` tag instead of a clickable link (root cause: `esc_html__()` was escaping the `<a>` inside the translatable string)
### Changed
- `[bv_login_page]` now shows dual destination cards above the login form: **Client Portal** and **LMS Dashboard**, so users can see both access options
- Login card widened from 440px to 620px to accommodate side-by-side cards; stacks vertically on mobile

## [2.7.61] - 2026-08-25
### Added
- `[bv_login_page]` shortcode — branded login form with gradient header, logo, username/password fields, remember me, error display
- Role-based login redirect: Admins → WP Admin, Consultants → Consultant Dashboard, TutorLMS Students → LMS Dashboard, BV Clients → Client Portal, Others → My Account
- Auto-redirect if user is already logged in when visiting login page
- Auto-detection of portal URL and TutorLMS dashboard URL when settings are empty
- `bv_login_redirect` filter on `woocommerce_login_redirect` and `login_redirect` (priority 100)

## [2.7.60] - 2026-08-25
### Fixed
- Consultant notification emails now send proper HTML (was no-op ternary returning same variable)
- `notify_consultant_new_message()` rewritten from plain text to styled HTML template
- `notify_client_completion()` now sends proper email headers
- Email From≠To spam trigger prevention (falls back to admin_email or noreply@domain)
- Added RFC-compliant anti-spam headers (Auto-Submitted, Precedence, X-Auto-Response-Suppress, X-BV-Notification-Type)
- Questionnaire file attachments now visible to consultant in dedicated card
### Added
- `BV_Settings::build_email_headers()` — centralized email header builder with anti-spam headers
- "Questionnaire Uploaded Files" card in consultant dashboard questionnaire panel
- Questionnaire files included in `ajax_get_project_detail` JSON response
- `is_array` guard for file type detection in consultant dashboard

## [2.7.59] - 2026-08-25
### Added
- Reset Questionnaire button with confirmation modal (⚠ warning, Cancel / Yes Reset Everything)
- AJAX handler `bv_portal_reset_questionnaire` — verifies nonce + access, deletes all responses + draft
- CSS modal styling with overlay, responsive breakpoint

## [2.7.58] - 2026-08-25
### Fixed
- PHP 8+ fatal error when reopening saved questionnaire — `json_decode()` now guarded with `is_array()` check
- Draft loading may produce PHP arrays for checkbox/address/repeatable/multifile types — now normalized to JSON strings
- Also added `is_string` guard in consultant dashboard

## [2.7.57] - 2026-08-25
### Fixed
- Project switching bug in client portal — all AJAX handlers now re-validate project access server-side
- Form `data-project-id` read from server-rendered attribute (not cached JS state)
- Project links trigger full page reload (not JS state change)

## [2.7.56] - 2026-08-25
### Fixed
- Client portal full-screen layout — `width:100vw` with `margin-left:calc(-50vw + 50%)`
- Removed `max-width` constraints on portal and questionnaire sections

## [2.7.55] - 2026-08-24
### Fixed
- Single file upload now uses AJAX upload via `bv_portal_upload_multifile` endpoint (same as multifile)
### Added
- Save Draft & Continue Later button (`is_draft=1` flag)
- File download links in consultant dashboard questionnaire view
- `bv_cd_download_qfile` AJAX action for downloading questionnaire-uploaded files

## [2.7.54] - 2026-08-24
### Fixed
- Email logo now uses HTML `width="120" height="auto"` attributes instead of CSS `max-height/max-width`
- Gmail strips CSS sizing but respects HTML attributes

## [2.7.53] - 2026-08-24
### Changed
- Reduced email logo from `max-height:60px/max-width:200px` to `max-height:40px/max-width:140px`
- Note: Superseded by v2.7.54 HTML attribute approach

## [2.7.52] - 2026-08-24
### Fixed
- Reminder email CTA button now deep-links to specific project (`?project_id=X` appended to portal URL)

## [2.7.51] - 2026-08-24
### Fixed
- `bv_settings` option now preserved across plugin reinstall
- Settings only deleted when `bv_delete_data_on_uninstall` option is set to `yes`
- All table drops and file deletions also conditional on the cleanup flag

## [2.7.50] - 2026-08-24
### Added
- Auto-detect client portal page URL by searching for published page containing `[businessvance_client_portal]` shortcode
- Falls back to `site_url()` if no page found

## [2.7.49] - 2026-08-24
### Fixed
- Blank portal URL when setting is empty string — changed `??` (only catches null) to `!empty()` (catches null AND empty string)
- Added `site_url()` as final fallback

## [2.7.48] - 2026-08-24
### Fixed
- Gmail rendering: added `bgcolor` attributes on all colored `<td>` elements
- Added `<font>` tag inside CTA button text (Gmail strips CSS color on links)

## [2.7.47] - 2026-08-24
### Fixed
- Replaced CSS-styled email button with bulletproof table-based button (works in all email clients)
- Added Reply-To header to reminder email

## [2.7.46] - 2026-08-24
### Added
- Professional HTML reminder email with branded header, logo, step progress indicators, CTA button
- Replaced plain text reminder with full `<!DOCTYPE html>` table-based email layout

## [2.7.45] - 2026-08-24
### Fixed
- Fatal error: replaced undefined `Order::get_currency_symbol()` with `get_woocommerce_currency_symbol()`
- Added `function_exists()` guard with `'R'` fallback on all 3 usages

## [2.7.44] - 2026-08-24
### Fixed
- Per-question deduplication for multi-service projects (composite key: label||type||options)
- Agreement deduplication (shared template shown once with combined service names)
- Document requirement deduplication (grouped by requirement ID)
- Step enforcement: hard gate hides tabs beyond current reached step
- Default tab fix for project detail view
- Added error diagnostic logging
### Added
- `get_step_completion_info()` helper for step progress calculation
- Per-service response storage via junction table lookup
- Fallback service-id lookup (junction table → legacy column)

## [2.7.43] - 2026-08-24
### Added
- 15 project detail view enhancements: activity timeline, unread message count, WC order data, client avatar, stale project warning (3-tier), client history link, next action message, milestone progress stepper, tab badge counts, confirmation modal, send reminder button, quick note, document version grouping, client history filter, AJAX detail handler

## [2.7.42] - 2026-08-24
### Added
- 16 consultant dashboard enhancements: overdue badge, relative time ago, multi-status chip filters, sortable columns, search, quick status dropdown, color-coded progress bars, unread message badge, bulk select + bulk status update, quick note modal, empty state, avatar initials, pipeline value, services column, Needs Attention widget, Recent Activity widget

---

## [2.7.41] - 2026-08-23
### Fixed
- AJAX requests failing for consultant users — added `wp_doing_ajax()` check to `lock_admin_access()`

## [2.7.40] - 2026-08-23
### Fixed
- AJAX requests killed by `lock_admin_access()` — renamed delete action to remove (security plugin bypass), added `dataType:json` on all AJAX, cache-bust reload

## [2.7.39] - 2026-08-23
### Added
- Delete project option to consultant dashboard
### Fixed
- Missing metadata column in activity_log inserts (MySQL strict mode)
- Suppressed DB errors in AJAX handlers

## [2.7.38] - 2026-08-23
### Fixed
- Consultant access via WooCommerce — `user_has_cap` filter grants `edit_posts` in admin context
- `woocommerce_login_redirect` filter at priority 99999 for consultant redirect
- Removed duplicate `lock_admin_access()` method that caused fatal error on activation

## [2.7.37] - 2026-08-20
### Fixed
- Admin menu visibility for admin users
- Consultant login redirect — `woocommerce_login_redirect` is the correct WooCommerce hook

## [2.7.36] - 2026-08-20
### Fixed
- ERR_TOO_MANY_REDIRECTS for consultant dashboard access

## [2.7.35] - 2026-08-20
### Fixed
- Admin dashboard access, consultant login redirect, email package bugs

## [2.7.26] - 2026-08-18
### Added
- Questionnaire report includes required documents and signed agreement sections
### Fixed
- MIME types for PPT/PPTX/CSV downloads

## [2.7.25] - 2026-08-18
### Fixed
- Fatal error on activation (missing closing parenthesis on error_log line)

## [2.7.24] - 2026-08-18
### Fixed
- Email now includes ALL files (multifile + required docs) with robust path resolution
- Individual attachments, HTML email, comprehensive logging

## [2.7.23] - 2026-08-18
### Added
- Project Reset to consultant dashboard
- Complete questionnaire PDF report + email-on-completion with ZIP
