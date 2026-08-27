# Changelog

All notable changes to the BusinessVance Services Manager plugin.

## [2.7.80] - 2026-08-28
### Fixed
- **Report upload WAF 403 error — REST API approach with complete UX redesign** — The WordPress Media Library approach (`wp.media` / `async-upload.php`) ALSO gets blocked by the server WAF/ModSecurity. Reverted to direct file input + REST API (`/wp-json/bv/v1/upload-report`) which successfully bypasses the WAF. The previous JS bug (`statusEl.text is not a function`) is also eliminated.
### Changed
- **Report upload UX completely redesigned as a two-step flow**:
  - **Step 1:** Enter report title + select file using native `<input type="file">` → green preview card shows file icon, name, size, and MIME type for confirmation
  - **Step 2:** Click "Complete Upload & Notify Client" → switches to upload view with **real XMLHttpRequest progress bar** showing percentage and bytes transferred
  - On success: shows confirmation message, auto-sends email notification to client, reloads page
  - On any error: returns to Step 1 with clear red error message (no more vague alerts)
  - Client-side validation: file type (PDF/DOC/DOCX only) and size checked before upload
- **Removed `wp_enqueue_media()` dependency** — no longer needed since we don't use the WordPress Media Library frame
### Added
- **Client notification on upload** — REST API endpoint now automatically sends an email to the client when a report is uploaded (same email template as "Deliver & Notify")
- **`rest_url` and `rest_nonce`** in `wp_localize_script` for direct REST API calls from JavaScript

## [2.7.79] - 2026-08-28
### Fixed
- **Report upload 403 error resolved** — Server-level WAF/ModSecurity rules were blocking POST file uploads to `wp-admin/admin-ajax.php` (returning HTML 403 page before WordPress processes the request). Fixed by routing file uploads through the WordPress REST API (`/wp-json/bv/v1/upload-report`) which uses a different URL path that server security rules typically do not block. Includes automatic fallback to `admin-ajax.php` if REST API is disabled on the server.
### Added
- **REST API endpoint for report upload** (`bv/v1/upload-report`) — registered via `register_rest_route()` with cookie-based authentication. Handles the same file validation, type checking, and database operations as the original AJAX handler.
- **`rest_url` and `rest_nonce`** passed to consultant dashboard JavaScript for REST API authentication.

## [2.7.78] - 2026-08-28
### Fixed
- **Report upload now shows clear error messages instead of generic "Request failed"** — Three layers of improvement:
  1. **Client-side file size check** — Validates file size against `wp_max_upload_size()` before uploading, showing "File is too large (X MB). Maximum upload size is Y MB." if exceeded
  2. **Server-side upload error handling** — PHP now checks `$_FILES['file']['error']` and returns specific messages for each PHP upload error code (INI_SIZE, FORM_SIZE, PARTIAL, NO_FILE, NO_TMP_DIR, CANT_WRITE, EXTENSION), including the actual `upload_max_filesize` and `post_max_size` values
  3. **JS AJAX error handler improved** — Shows HTTP status code (403, 413, 500, etc.) and specific messages. 403 now explains it's a WAF/ModSecurity issue with GoDaddy-specific fix instructions.
- **Added max file size display** below the file input in Reports tab (e.g. "Max file size: 64 MB")
- **Passed `max_upload_size` and `max_upload_mb` to JavaScript** via `wp_localize_script` for client-side validation

## [2.7.77] - 2026-08-28
### Changed
- **Client completion email upgraded from plain text to professional HTML** — now matches the same beautiful design system used by all other emails (consultant package, client reminder, consultant new message, consultant action notifications). Includes: company logo/color header, "All Information Submitted" heading, project info card with green "100% Complete" status, info card with green left-border accent, "Go to My Project Portal" CTA button, and footer with contact info (email, phone, address) and company signature. Auto-resolves portal URL if not set in settings. Supports custom HTML body override from admin settings (same pattern as consultant emails).
### Added
- **5 new per-action email notification types with individual toggles and customizable subject/body**:
  - **Client Project Completion** (`email_client_completion`) — sent to client at 100% progress. Was hardcoded, now uses settings template with placeholders: {client_name}, {project_number}, {company_name}, {portal_url}, {contact_info}, {consultant_email}, {consultant_phone}, {site_name}
  - **Consultant Project Complete** (`email_consultant_completion`) — sent to consultant at 100% progress (the package email). Was hardcoded, now uses settings. If admin provides HTML body, used as-is; otherwise standard HTML template is built. Placeholders: {project_number}, {client_name}, {client_email}, {company_name}, {dashboard_url}, {site_name}
  - **Consultant Document Upload** (`email_consultant_document`) — sent when client uploads a document at <100% progress
  - **Consultant Questionnaire Update** (`email_consultant_questionnaire`) — sent when client saves questionnaire at <100% progress
  - **Consultant Agreement Signed** (`email_consultant_agreement`) — sent when client signs agreement at <100% progress
- **Intermediate consultant notifications now fire** — When a client uploads a document, saves questionnaire, or signs agreement at <100% progress, the consultant receives a per-action email (previously these were no-ops)
- **Email settings UI reorganized into two sections**: "Client Email Notifications" (5 types) and "Consultant Email Notifications" (5 types), each with enable toggle + subject + body fields
### Removed
- **`cd_auto_notify_consultant` master toggle** — replaced by individual per-action toggles. Removed from defaults and Portal Settings tab UI.
- **`email_consultant_action_subject` / `email_consultant_action_body`** generic settings — replaced by per-action settings (e.g. `email_consultant_document_subject`)
- **`email_project_package_subject`** setting — replaced by `email_consultant_completion_subject`

## [2.7.76] - 2026-08-28
### Fixed
- **Removed duplicate consultant email at project completion** — When a project reached 100%, TWO emails were sent to the consultant: (1) `notify_consultant()` with subject "Client Action on... — All Client Information Received" and (2) `email_project_package_to_consultant()` with subject "Project ... Complete". The first was redundant (the package email already tells the consultant everything) and was the one going to spam. Removed the `notify_consultant()` call from all 3 completion triggers (document upload, questionnaire save, agreement sign). Now only 1 email goes to the consultant at completion — the one that actually delivers to inbox.
- The `notify_consultant()` function is still used for **intermediate** actions (e.g. questionnaire saved at <100%, document uploaded before all steps complete) where the package email doesn't fire.

## [2.7.75] - 2026-08-28
### Fixed
- **Consultant emails going to spam — reverted email sending to exact v2.7.69 code path**:
  - Restored `build_email_headers()` to the EXACT v2.7.69 version with all original headers (Auto-Submitted, Precedence: bulk, X-Auto-Response-Suppress, X-BV-Notification-Type). These headers were present when emails delivered to inbox.
  - Removed `start_bv_email()`/`end_bv_email()` wrappers from all 3 consultant emails. These wrappers (added in v2.7.70) were the only remaining difference from the working v2.7.69 code. Client-facing emails (which work) keep the wrappers.
  - Consultant emails now use the IDENTICAL sending path as v2.7.69: `build_email_headers()` → `wp_mail()` — no PHPMailer filters, no Sender override, no X-Mailer override.
  - If emails still go to spam after this, the issue is external (server IP reputation, DNS/SPF/DKIM changes, or recipient server rule changes) — not plugin code.

## [2.7.74] - 2026-08-28
### Fixed
- **Consultant emails still going to spam — removed all custom anti-spam headers that were paradoxically causing spam**:
  - **Root cause**: In v2.7.70 we added 4 custom headers (Auto-Submitted, List-Unsubscribe, X-Auto-Response-Suppress, X-BV-Notification-Type) and 3 PHPMailer overrides (Sender/envelope-From, X-Mailer, Priority). These were intended to improve deliverability but actually INCREASED spam scoring because:
    - `List-Unsubscribe` is a mailing-list header — using it on one-to-one transactional emails tells spam filters "this is bulk mail"
    - `X-Auto-Response-Suppress: All` is a known spam trigger (was removed in first v2.7.70 fix, then re-added by mistake)
    - `$phpmailer->Sender` override forces the envelope sender to a specific domain; without proper SPF/DKIM records this causes SPF hard fail
    - `X-Mailer: Mail v1.0` is a non-standard, suspicious value that spam filters flag more than WordPress's default
    - The combination of 4+ non-standard headers creates a pattern that looks like a spammer trying to game the system
  - **Fix**: Stripped `build_email_headers()` to only Content-Type, From, Reply-To (the same headers a standard WordPress email uses). Emptied `configure_bv_phpmailer()` so it no longer overrides Sender/X-Mailer/Priority. Kept only the `wp_mail_from` and `wp_mail_from_name` filters (benign From address alignment).
  - **Philosophy**: LESS IS MORE for email headers. Standard WordPress emails with minimal headers deliver reliably.

## [2.7.73] - 2026-08-27
### Changed
- **Restored consultant email visual format to match the working screenshot**: All 4 HTML emails (consultant project complete, consultant client action, consultant new message, client reminder) now use the exact same visual style as the email that was confirmed to deliver to inbox:
  - **CTA button**: Changed from filled navy/primary-color button to **outlined black button** (white bg, 2px solid #111 border, black bold text, full-width, 6px border-radius) — matching the working email exactly
  - **Header**: Removed rounded corners (was `border-radius:12px 12px 0 0`) — now sharp rectangular header
  - **Content area**: Removed side borders (`border-left/right:1px solid`) — now clean white
  - **Footer**: Changed from gray `bgcolor` background with border to simple white with top border separator
  - **Typography**: Heading size increased to 24px, body color adjusted to #555555, data labels use 11px uppercase #888888, data values use #111111 — matching the working email
  - **Fallback URL**: Changed link color to standard blue (#2563EB) with underline

## [2.7.72] - 2026-08-27
### Fixed
- **Questionnaire multifile attachments not downloadable in consultant dashboard** — Files selected in multifile questions were never actually uploaded to the server. The JS `change` handler cleared the file input after showing a visual preview, so on form submit the upload code found 0 files and skipped. Root cause: multifile upload was deferred to submit time but the input was already empty.
  - **Fix**: Files now upload **immediately** when selected or drag-and-dropped, not on form submit. Each file shows upload status ("uploading..." → "✓" or error). File metadata is written to the hidden data input right away.
  - Removed deferred multifile upload from `bvPrepareAndSubmit()` (files are already on server by then)
  - Added remove button (×) to previously saved multifile files in the client portal
  - Drop handler now calls upload function directly instead of setting input files (more reliable)

## [2.7.71] - 2026-08-26
### Fixed
- **Consultant emails still going to spam — template rewrite to match working client reminder format**:
  - All 3 consultant notification emails (`notify_consultant`, `notify_consultant_new_message`, `email_project_package`) now use the exact same HTML format as the client reminder email (which delivers to inbox, not spam)
  - **Bulletproof CTA button**: Replaced simple `<a>` with CSS `background` (stripped by Gmail) with table-based `<td bgcolor>` + `<font color="#ffffff">` fallback — identical to client reminder
  - **Removed `background:linear-gradient(...)` header**: Replaced with `bgcolor` attribute on `<td>` (many email clients can't render CSS gradients)
  - **Added logo support**: Consultant emails now show the company logo from settings (same as client reminder)
  - **Dynamic colors from settings**: Uses `$primary_color` setting instead of hardcoded `#002B5C`
  - **Added fallback URL text** below CTA button: "If the button above doesn't work, copy and paste this link..." (same as client reminder)
  - **Removed "Please do not reply" footer** (spam signal) — replaced with "If you have any questions, feel free to reply to this email." + "Best regards, CompanyName" (same as client reminder)
  - **Removed "This is an automated notification"** language (spam signal)
  - **Removed ALL attachments from project package email** — ZIP file + individual file attachments were a major spam trigger. Email now simply notifies the consultant to review in the dashboard where all files are accessible.

## [2.7.70] - 2026-08-26
### Fixed
- **Emails going to spam — comprehensive anti-spam overhaul**:
  - Added `BV_Settings::start_bv_email()` / `end_bv_email()` wrapper that configures PHPMailer directly for every BV email:
    - **Envelope sender (Return-Path) alignment** — PHPMailer's `$Sender` is now set to match the From address. Without this, PHPMailer defaults to `wordpress@hostname`, causing SPF failure. This is the #1 code-level cause of spam classification.
    - **X-Mailer override** — Replaced `"WordPress"` with generic `"Mail v1.0"`. The `"WordPress"` X-Mailer string is a known spam-filter signal.
    - **X-Priority set to 3 (normal)** — Prevents accidental high-priority spam scoring.
  - Enhanced `build_email_headers()` with:
    - **List-Unsubscribe** (RFC 8058) — Gmail, Outlook, and Yahoo use this to classify transactional mail. Helps prevent spam classification.
    - **X-Auto-Response-Suppress: All** — Suppresses NDRs and out-of-office replies.
  - Fixed **2 emails that bypassed `build_email_headers()`** entirely (client reminder email + client message notification). Both now use the centralized header builder with full anti-spam protection.
  - Wrapped **all 6 user-facing `wp_mail()` calls** with `start_bv_email()`/`end_bv_email()`.
  - **Removed emoji HTML entities** (`&#128065;`, `&#128172;`, `&#128640;`) from email CTA buttons. Some spam filters flag emoji in email content.
- **Fatal error when saving questionnaire at 100% progress** — `BV_Settings::$last_resolved_from` was declared `private static` but accessed from outside the class. PHP fatal error caused HTTP 500 → jQuery showed "Network error". Changed to `public static`. Also added admin_email fallback in `start_bv_email()`.
### Note
- **Server-side DNS is also required**: Make sure SPF, DKIM, and DMARC records are properly configured for `studyvance.co.za` on your hosting. This is the most impactful factor for spam classification and must be done in your domain's DNS settings (not in the plugin). Without SPF/DKIM, even perfectly formatted emails may still go to spam.

## [2.7.69] - 2026-08-25
### Fixed
- **Client portal "log in" link now points to /bv-login/** instead of WordPress default login page. Resolves the BV login page permalink dynamically.

## [2.7.68] - 2026-08-25
### Added
- **Logout URL for nav menus**: `https://yoursite.com/?bv_logout=1` — logs the user out and redirects to /bv-login/. Add as a custom link in Appearance > Menus.

## [2.7.67] - 2026-08-25
### Changed
- **Logout button on destination picker** — replaced small "Not you? Log out" text link with a proper styled logout button (with door icon). Turns red on hover. Redirects back to the BV login page after logout.

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
