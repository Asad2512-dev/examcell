# ExamCell frontend redesign plan

## Scope and guardrails
Preserve every existing route, controller, service, permission, database model, schema, request field, action URL, CSRF token, and server validation. The only new route is the explicitly requested `/styleguide`, protected by existing `auth`, `active`, and `role:admin` middleware. Remove only the duplicate admin `missing-marks-report` catalog entry. Do not create fake statistics, confidence scores, or successful actions; autosave must use the existing validated draft-save action and report its real outcome.

## Audit
`docs/SCREEN_AUDIT.md` records every catalog screen, effective backend template, declared pattern, public screen, and controller fallback. Actual runtime rendering goes through `screens/show.blade.php` and `layouts/app.blade.php`; `ScreenBindings::module()` chooses the backend view, overriding many catalog kinds. Some nominal drawers, calendars, wizards, and error screens currently share generic record views. Preserve data bindings and enhance presentation without inventing backend functionality.

## Sequence
1. Audit and deduplicate catalog; capture baseline and available regression tests.
2. Brand: deterministic SVG mark and wordmark, light/dark variants, PNG favicons. Document accessible semantic tokens, typography, spacing, and interaction rules.
3. Install local Vite + Tailwind + Alpine + Lucide + lazy Chart.js. Build reusable components and public pages; run HTTP/render checks.
4. Shared shell: requested role navigation groups, mobile drawer, command search, notifications, profile, persistent theme, scoped academic context navigation. Run role render checks.
5. Admin areas in the requested order: dashboard; users; academic structure; examinations; scheduling; admit cards; rooms; seating; invigilation; marks; results; grading; processing; rechecking; analytics; assistant; reports; notifications; audit. Use existing actions and data, with reusable patterns for tables, forms, calendar/grid views, steps, history, and printables. Run all admin screens and regression checks.
6. Teacher: task navigation, editable marks with honest unsaved/saved status, keyboard navigation and submit confirmation; import preview and row errors; duty/availability views. Run teacher screen checks.
7. Student: mobile dashboard, semester result filters, GPA highlights, rechecking tracker, schedule update messaging. Run student screen checks.
8. System states, print styles and admin styleguide. Check keyboard behavior, light/dark, 375/768/1280/1920 widths, all catalog and fallback routes, build, Blade compilation, existing test suite.
9. Update CHANGELOG with every touched file and final validation evidence. Document inherited backend limitations separately from presentation work.

## Validation strategy
Use existing isolated `examcell_test` database tests only; never migrate, seed, or reset the live application database. Add presentation regression coverage for all registered role pages and forbidden styleguide access. Use read-only HTTP/browser sessions or transactional tests for role screens. Assert controller/service hashes remain unchanged, verify submitted field names/action URLs and existing workflow tests, inspect representative screenshots and A4 print output. Check after each area before proceeding.

## Completion record
- Shared brand, components, public pages, shell, all role modules, system/error views, printables and admin styleguide are implemented.
- All catalog and fallback mappings are recorded in `docs/SCREEN_AUDIT.md`.
- Validation evidence and reproducible test instructions are recorded in `docs/REDESIGN_VALIDATION.md`.
- Backend hashes were checked against the initial snapshot: no existing controller/service/model/configuration/schema changes.
- Remaining backend-dependent boundaries are explicitly documented in `DESIGN_SYSTEM.md`; no unsupported behavior or statistics were invented.
