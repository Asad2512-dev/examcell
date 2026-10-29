# Deadlines, academic context and admit-card verification

## Submission deadlines
Administrators open Marks Submissions, choose an examination course, and use **Set a submission deadline**. The course default applies to every assigned teacher. An optional teacher override takes precedence. Clearing a teacher override restores the course default; clearing the default leaves the course without a deadline unless a teacher override exists.

`POST /workflow/marks/{course}/deadline` accepts `marks_due_at` (nullable date) and `teacher_id` (optional assigned teacher). Existing auth/active/admin middleware, CSRF validation and audit logging apply. Datetime inputs are PKT; ISO offsets are normalized to Asia/Karachi without changing the instant. Storage is `exam_courses.marks_due_at` plus the unique `(exam_course_id, teacher_id)` override table `marks_submission_deadlines`.

Teacher Dashboard and marks screens show effective deadlines for authorized course assignments. The live counter advances using server time and monotonic browser elapsed time, avoiding dependence on an incorrect device clock. Exactly 48 hours remains informational; less than 48 hours is amber; reaching/passing the deadline is red. Submitted records display a submitted state instead of a running overdue counter. Deadlines are informational and do not change the existing submission, return, approval or locking permissions.

## Admin academic context
The top-bar switcher posts `session_id` and optional `semester_id` to `/academic-context`. The semester must belong to the selected session. Selection is stored in the authenticated user's session under `academic_context`, including its owner user ID. It persists across navigation/reloads and is cleared by normal logout. Teachers/students cannot change it and their existing scopes ignore it.

Default order is: date-current active session; newest active session; newest existing session. An empty installation has no context until academic sessions exist. Deleted/invalid stored selections safely fall back. Changing session clears the semester selection unless a valid matching semester is submitted.

`AcademicContext` is an explicit read-query filter, not a model global scope or an authorization rule. It scopes examinations/courses, semesters/sections, academic profiles/enrollment/teaching assignments, assessments/marks/results, schedules/seating/duties, replacements, corrections/rechecking, admit cards/bulk jobs, findings, related audit history, dashboard counters, dropdowns, reports, analytics and assistant/forecast retrieval. Examination course eager loads are filtered too. Additional report filters intersect the selected context.

Institution-wide master data (accounts, departments, course definitions, rooms/availability, grading schemes), backups and account notifications remain global. The switcher's session/semester metadata intentionally lists available contexts. Existing policies and role checks remain authoritative; context filtering does not grant or revoke permissions or weaken cross-session conflict checks in write services.

## Signed admit-card QR verification
Each generated card embeds an actual PNG QR using Endroid QR Code (requires PHP GD). The payload contains only a Laravel-signed URL of the form `/verify/admit/{random-reference}?signature=...`, with no student name, roll number, email, grades or other PII in the QR itself.

The public route requires a valid signature before resolving a record, is rate limited, and has no sign-in/styleguide dependency. The view receives only four explicit fields: student name, roll number, examination name and current validity. It uses no-store/no-referrer/noindex headers and no external assets. Unsigned, altered, expired or unknown links disclose no student data.

A valid card must still refer to a published, admit-enabled examination, an active eligible student and eligible scheduled courses. Status is evaluated on every scan. Withdrawal, deactivation or archival makes the verification invalid. Explicit reissuance generates a new reference, making previous links unavailable. URLs are otherwise durable so printed cards do not arbitrarily expire; temporary signed URLs, if issued, are also checked for expiry.

Pre-QR cards have `verification_version=0`. The existing authorized download route upgrades them once to a QR PDF while preserving the original private PDF and reference. Historical cards remain downloadable under the existing policy; their public status can be invalid. Upgrade does not restore eligibility or unlock records. New cards and upgrades use version 1.

Configure `APP_URL` to the correct deployed address, reachable by scanning devices, and keep the existing `APP_KEY` stable. Queue workers must use the same configuration. Changing the signing key invalidates previously printed signatures. No verification secret is stored in a public asset.

## Sign-in
Admin, Teacher and Student sign-in still redirects directly to the role dashboard. There is no styleguide dependency and no styleguide link in normal navigation. `/styleguide` remains available as a direct, admin-only development reference.

## Migration and validation
The additive migration `2026_10_29_000001_add_submission_deadlines_and_admit_verification` adds the deadline fields/table and admit verification version. It was applied locally after private database/document backup #3 was verified. No existing deadline dates were invented, and existing PDF files were not deleted.

Validation: 55 PHP tests / 1,182 assertions passing on both PHP 8.5 and MAMP PHP 8.2.26, four frontend tests including an independent QR decoder, normal-role login checks, session-switch persistence, live countdown colors, mobile widths, and PDF QR scan-to-public-page browser checks. Existing and new tests use the guarded `_test` database. QA admit PDFs were rendered with Poppler and visually inspected.

```bash
composer install
npm ci
php artisan migrate
npm run build
php vendor/bin/phpunit
npm run test:frontend
```

Optional browser checks require an isolated test server on port 8022, the existing browser fixtures followed by `tests/browser/backend-features-fixture.php`, and `EXAMCELL_UI_TEST=1 npm run test:backend-ui`. PDF extraction in that browser check uses Poppler `pdfimages`; the runtime application does not require Poppler.

Implementation references: [Laravel signed URLs](https://laravel.com/docs/12.x/urls#signed-urls), [Endroid QR Code](https://github.com/endroid/qr-code).
