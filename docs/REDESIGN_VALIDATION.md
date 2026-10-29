# Redesign validation

## Scope verified
- 199 unique catalog entries after removing the duplicate `admin/missing-marks-report`.
- 196 role-specific content pages: 155 admin, 24 teacher, 17 student. The three catalog auth aliases still redirect to shared sign-in.
- Four public pages and the six existing controller fallback names retain their original behavior.
- All 95 pre-existing application/controller/service/model/configuration/migration files match their pre-redesign SHA-256 hashes. The added `app/View/WorkspaceNavigation.php` contains presentation-only grouping.
- The only routing change is the requested `/styleguide` view, using existing `auth`, `active`, `role:admin` middleware.
- No live database migration, seed, reset, workflow mutation, or account change was performed. Tests and browser fixtures use the guarded `examcell_test` database.

## Automated validation
- `npm run build`: passes; locally bundled Tailwind/Alpine, separate dynamically imported Chart.js chunk.
- `php artisan view:cache`: passes; compiled views also checked with PHP syntax lint.
- `php vendor/bin/phpunit`: **43 tests, 1,018 assertions, all passing**. Includes the existing workflow/security suite and new presentation coverage for populated pages, duplicate detection, role navigation reachability, all fallback aliases, admin-only styleguide and PDF generation.
- `tests/browser/redesign.cjs`: **214 role/fallback route checks and 64 responsive scenarios**, with no JavaScript errors. Expected 403 responses remain intact.
- Browser interactions cover Ctrl/Cmd+K search, Escape, theme persistence, mobile drawer behavior, table search/selection/columns, modal/drawer focus, real optional autosave and reload persistence, marks keyboard navigation, and submit confirmation.
- Public pages and 404 passed 20 checks at 375, 768, 1280 and 1920 px; password visibility and strength checked without sending email or submitting recovery forms. Three additional pattern checks verified wizard navigation, live assessment totals and local CSV preview.

## Visual verification
Screenshots and machine-readable browser reports are in `docs/redesign/`. Representative admin light/dark, teacher desktop and student mobile layouts were inspected. No document-level horizontal overflow was detected in the checked viewports; wide calendars and marks grids scroll within their own containers.

Admit-card and report PDFs were generated through the existing DOMPDF pipeline, rendered with Poppler and visually inspected. Branding, table alignment, text wrapping, signatures, reference fields and A4 layout were checked. QR verification remains a clearly labeled reserved area because no verification endpoint exists. QA PDFs are temporary test outputs, not real academic records.

Accessibility measures include local Inter fonts, semantic landmarks, named controls, native modal focus trapping, Escape behavior, restored mobile-drawer focus, status text in addition to color, visible focus rings, reduced-motion support, chart data alternatives, stronger input borders and responsive labeled table cards.

## Reproduce
Run PHP tests using the existing `phpunit.xml` and its dedicated database guard. Browser tests require an isolated server configured with `DB_DATABASE=examcell_test` and the browser fixtures; do not point them at live data.

```bash
npm ci
npm run build
php vendor/bin/phpunit
# After PHP tests, seed only the isolated test database:
APP_ENV=testing DB_DATABASE=examcell_test php tests/browser/redesign-fixture.php
# In another terminal, serve public/ on port 8022 with that same test database.
EXAMCELL_UI_TEST=1 BASE_URL=http://127.0.0.1:8022 npm run test:ui
```

`tests/browser/redesign-fixture.php` delegates to the existing fixture guard, which refuses any database whose name does not end in `_test`. The UI script also requires an explicit `EXAMCELL_UI_TEST=1`. Existing development/server/database configuration is unchanged.

## Inherited boundaries
Submission deadline fields, persisted conflict totals, global session scoping and QR verification are absent from the existing backend. The UI does not fabricate these features. Academic context opens the existing session/semester configuration. Calendar overlap hints are local to displayed records; server validation remains authoritative. Optional autosave is off until enabled and uses the existing draft-save action; it never submits or unlocks marks. Existing catalog names continue to share the live backend modules documented in SCREEN_AUDIT.md.
