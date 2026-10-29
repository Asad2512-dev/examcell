# Changelog

## 2026-10-29 — Backend deadlines, academic context and signed QR verification

- Added course-level marks deadlines and assigned-teacher overrides, administrator editing/clearing, timezone normalization and audit events. Teacher Dashboard and marks screens show live server-clock countdowns: amber under 48 hours, red at/past the deadline, submitted state when locked/submitted.
- Added a persisted Admin session/semester switcher with active-session defaults, matching-semester validation and per-user session ownership. Applied explicit read scopes to relevant academic queries, dashboards, record selectors, exports, analytics, assistant retrieval and related audit history. Shared master directories remain global; existing policies/write validation are unchanged.
- Added signed, rate-limited public admit-card verification exposing only name, roll number, examination and current validity. New PDFs contain real QR images; legacy cards are upgraded once on authorized download, preserving their original PDF/reference and historical access. Reissuance invalidates older references.
- Kept ordinary sign-in redirects for all roles and removed the styleguide link from normal navigation. The optional admin-only reference route remains separate.
- Added the additive migration and applied it locally after verified private backup #3. Added PHP GD and Endroid QR dependencies plus independent QR decoder dev tests. Patched Symfony Mime to 7.4.22 and its compatible polyfill dependency; Composer audit reports no advisories.
- Validation: **55 PHP tests, 1,182 assertions** on PHP 8.5 and MAMP PHP 8.2.26; **four frontend tests**; browser role sign-in, persisted switching, teacher override editing, live deadline colors, responsive widths and PDF QR decoding/public verification. Production build and Blade compilation pass. Details: docs/BACKEND_FEATURES.md.


### Backend feature file inventory

Modified:

- `CHANGELOG.md`
- `DESIGN_SYSTEM.md`
- `README.md`
- `START-HERE.md`
- `app/Http/Controllers/ScreenController.php`
- `app/Http/Controllers/WorkflowController.php`
- `app/Models/ExamCourse.php`
- `app/Services/ReportService.php`
- `app/Services/ScopedQueries.php`
- `app/Support/Resources.php`
- `composer.json`
- `composer.lock`
- `docs/SCREEN_AUDIT.md`
- `package-lock.json`
- `package.json`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/views/backend/academic.blade.php`
- `resources/views/backend/admit-pdf.blade.php`
- `resources/views/backend/admit.blade.php`
- `resources/views/backend/corrections.blade.php`
- `resources/views/backend/dashboard.blade.php`
- `resources/views/backend/duties.blade.php`
- `resources/views/backend/marks.blade.php`
- `resources/views/backend/seats.blade.php`
- `resources/views/components/app-layout.blade.php`
- `resources/views/components/badge.blade.php`
- `resources/views/components/sidebar.blade.php`
- `resources/views/components/topbar.blade.php`
- `routes/web.php`

Added:

- `app/Http/Controllers/AcademicContextController.php`
- `app/Http/Controllers/AdmitVerificationController.php`
- `app/Http/Controllers/SubmissionDeadlineController.php`
- `app/Models/MarksSubmissionDeadline.php`
- `app/Services/AcademicContext.php`
- `app/Services/AdmitVerificationService.php`
- `database/migrations/2026_10_29_000001_add_submission_deadlines_and_admit_verification.php`
- `docs/BACKEND_FEATURES.md`
- `docs/backend-features/browser-report.json`
- `docs/backend-features/public-verification.png`
- `docs/backend-features/session-switcher.png`
- `docs/backend-features/teacher-countdowns.png`
- `public/build/assets/app-BleilE3Z.css`
- `public/build/assets/app-CJ6rnt1k.js`
- `resources/js/submission-countdown.js`
- `resources/views/components/academic-switcher.blade.php`
- `resources/views/components/submission-countdown.blade.php`
- `resources/views/components/submission-deadline-editor.blade.php`
- `resources/views/components/submission-deadlines.blade.php`
- `resources/views/public/admit-verification.blade.php`
- `tests/Feature/AcademicContextTest.php`
- `tests/Feature/AdmitVerificationTest.php`
- `tests/Feature/NormalSignInTest.php`
- `tests/Feature/SubmissionDeadlineTest.php`
- `tests/browser/backend-features-fixture.php`
- `tests/browser/backend-features.cjs`
- `tests/frontend/admit-qr.test.mjs`
- `tests/frontend/generate-qr.php`
- `tests/frontend/submission-countdown.test.mjs`

Replaced generated bundles:

- `public/build/assets/app-CdYZrUs2.css`
- `public/build/assets/app-DYqACMAY.js`

## 2026-10-29 — ExamCell presentation redesign

### Delivered
- Audited every catalog screen and effective Blade mapping, including all six controller fallback names. Removed only the duplicate admin Missing marks report catalog entry.
- Replaced the raster identity with deterministic SVG marks/wordmarks, light/dark variants and PNG/browser icons in `public/brand/`.
- Added Vite, Tailwind CSS, Alpine.js, server-rendered Lucide icons and lazy-loaded Chart.js. Added semantic light/dark tokens, local fonts, responsive patterns and A4 print styles.
- Rebuilt public pages, authentication, shared app layout, grouped navigation for all roles, command palette, notification/account surfaces, mobile drawer and theme persistence.
- Added the reusable Blade component library, enhanced table controls, calendar views, stepped form review, seating grids, replacement comparison, grading previews, report filters/exports, chat, timelines, semantic states and printables.
- Enhanced teacher marks with keyboard movement, inline validation, weighted previews, optional real draft autosave and submit confirmation; added CSV upload previews and server validation-error presentation.
- Added student next-exam actions, semester result filters, GPA highlights, rechecking tracking and updated-schedule messaging.
- Added admin-only `/styleguide` through existing middleware. No existing route or permission changed.
- Updated guides, complete screen mapping, design-system documentation, validation reports and screenshots.

### Preservation and validation
All 95 pre-existing backend/application/configuration/schema files match the baseline hashes. Catalog data is identical except for removal of its duplicate. No live database migration, seed, reset or academic-record mutation was performed. Existing server requests, validation, CSRF, record scope and workflow lock rules remain authoritative.

Production build and Blade compilation pass. PHP suite: **43 tests, 1,018 assertions**. Browser checks: **214 role/fallback routes**, **64 responsive scenarios**, **20 public-page scenarios**, additional wizard/weighting/CSV checks, and real optional autosave persistence. No browser JavaScript errors in the final reports. PDFs were rendered with Poppler and visually inspected.

Inherited backend-dependent limits (deadline fields, global session scoping, persistent conflict totals and QR verification) are documented in DESIGN_SYSTEM.md. No fake counters, confidence scores or verification codes were introduced.

### Complete workspace file inventory
This inventory lists final added/modified project files, including built assets and visual evidence. Installed dependency trees, runtime caches/logs/sessions, and temporary QA files are excluded; dependency changes are captured by package.json and package-lock.json. Legacy retained Blade fallback files include directive/format normalization.

#### Modified files (58)

- `README.md`
- `START-HERE.md`
- `package-lock.json`
- `package.json`
- `resources/screens.json`
- `resources/views/backend/academic.blade.php`
- `resources/views/backend/admit-pdf.blade.php`
- `resources/views/backend/admit.blade.php`
- `resources/views/backend/analytics-summary.blade.php`
- `resources/views/backend/analytics.blade.php`
- `resources/views/backend/audit.blade.php`
- `resources/views/backend/auth.blade.php`
- `resources/views/backend/backups.blade.php`
- `resources/views/backend/chat.blade.php`
- `resources/views/backend/corrections.blade.php`
- `resources/views/backend/course-select.blade.php`
- `resources/views/backend/courses.blade.php`
- `resources/views/backend/dashboard.blade.php`
- `resources/views/backend/duties.blade.php`
- `resources/views/backend/filters.blade.php`
- `resources/views/backend/import.blade.php`
- `resources/views/backend/marks.blade.php`
- `resources/views/backend/notifications.blade.php`
- `resources/views/backend/pagination.blade.php`
- `resources/views/backend/profile.blade.php`
- `resources/views/backend/rechecks.blade.php`
- `resources/views/backend/report-pdf.blade.php`
- `resources/views/backend/results.blade.php`
- `resources/views/backend/roster.blade.php`
- `resources/views/backend/schedule.blade.php`
- `resources/views/backend/seats.blade.php`
- `resources/views/backend/state.blade.php`
- `resources/views/components/badge.blade.php`
- `resources/views/components/brand-head.blade.php`
- `resources/views/components/button.blade.php`
- `resources/views/components/field.blade.php`
- `resources/views/components/icon.blade.php`
- `resources/views/components/kpi.blade.php`
- `resources/views/components/logo.blade.php`
- `resources/views/components/table.blade.php`
- `resources/views/landing.blade.php`
- `resources/views/layouts/account-menu.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/screens/analytics.blade.php`
- `resources/views/screens/auth.blade.php`
- `resources/views/screens/calendar.blade.php`
- `resources/views/screens/chat.blade.php`
- `resources/views/screens/dashboard.blade.php`
- `resources/views/screens/form.blade.php`
- `resources/views/screens/grading.blade.php`
- `resources/views/screens/marks.blade.php`
- `resources/views/screens/notifications.blade.php`
- `resources/views/screens/result.blade.php`
- `resources/views/screens/seats.blade.php`
- `resources/views/screens/show.blade.php`
- `resources/views/screens/state.blade.php`
- `resources/views/screens/table.blade.php`
- `routes/web.php`

#### Added files (91)

- `CHANGELOG.md`
- `DESIGN_SYSTEM.md`
- `REDESIGN_PLAN.md`
- `app/View/WorkspaceNavigation.php`
- `docs/REDESIGN_VALIDATION.md`
- `docs/SCREEN_AUDIT.md`
- `docs/redesign/admin-1280.png`
- `docs/redesign/admin-1920.png`
- `docs/redesign/admin-375.png`
- `docs/redesign/admin-768.png`
- `docs/redesign/admin-dark.png`
- `docs/redesign/browser-report.json`
- `docs/redesign/landing-1280.png`
- `docs/redesign/landing-375.png`
- `docs/redesign/pattern-report.json`
- `docs/redesign/public-report.json`
- `docs/redesign/signin-1280.png`
- `docs/redesign/signin-dark.png`
- `docs/redesign/student-1280.png`
- `docs/redesign/student-1920.png`
- `docs/redesign/student-375.png`
- `docs/redesign/student-768.png`
- `docs/redesign/student-dark.png`
- `docs/redesign/teacher-1280.png`
- `docs/redesign/teacher-1920.png`
- `docs/redesign/teacher-375.png`
- `docs/redesign/teacher-768.png`
- `docs/redesign/teacher-dark.png`
- `public/brand/apple-touch-icon.png`
- `public/brand/favicon-32.png`
- `public/brand/favicon.svg`
- `public/brand/logo-dark.svg`
- `public/brand/logo-light.svg`
- `public/brand/logo-mark-dark.svg`
- `public/brand/logo-mark-light.svg`
- `public/brand/logo-mark.png`
- `public/brand/logo-mark.svg`
- `public/brand/logo.svg`
- `public/build/assets/app-CdYZrUs2.css`
- `public/build/assets/app-DYqACMAY.js`
- `public/build/assets/chart-CSmr0dTq.js`
- `public/build/manifest.json`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/js/legacy.js`
- `resources/views/components/app-layout.blade.php`
- `resources/views/components/assets.blade.php`
- `resources/views/components/breadcrumb.blade.php`
- `resources/views/components/calendar.blade.php`
- `resources/views/components/card.blade.php`
- `resources/views/components/chart-card.blade.php`
- `resources/views/components/confirm-dialog.blade.php`
- `resources/views/components/data-table.blade.php`
- `resources/views/components/date-input.blade.php`
- `resources/views/components/drawer.blade.php`
- `resources/views/components/empty-state.blade.php`
- `resources/views/components/error-state.blade.php`
- `resources/views/components/file-upload.blade.php`
- `resources/views/components/form-field.blade.php`
- `resources/views/components/input.blade.php`
- `resources/views/components/job-progress.blade.php`
- `resources/views/components/modal.blade.php`
- `resources/views/components/page-header.blade.php`
- `resources/views/components/pdf-style.blade.php`
- `resources/views/components/printable-layout.blade.php`
- `resources/views/components/report-toolbar.blade.php`
- `resources/views/components/select.blade.php`
- `resources/views/components/sidebar.blade.php`
- `resources/views/components/skeleton.blade.php`
- `resources/views/components/stat-card.blade.php`
- `resources/views/components/status-pill.blade.php`
- `resources/views/components/stepper.blade.php`
- `resources/views/components/tabs.blade.php`
- `resources/views/components/theme-toggle.blade.php`
- `resources/views/components/timeline.blade.php`
- `resources/views/components/toast.blade.php`
- `resources/views/components/toggle.blade.php`
- `resources/views/components/topbar.blade.php`
- `resources/views/components/wizard.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/errors/429.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`
- `resources/views/errors/layout.blade.php`
- `resources/views/styleguide.blade.php`
- `tests/Feature/PresentationTest.php`
- `tests/browser/redesign-fixture.php`
- `tests/browser/redesign.cjs`
- `vite.config.js`

#### Removed files

None.
