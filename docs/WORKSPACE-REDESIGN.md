# ExamCell workspace design

The Admin, Teacher, and Student workspaces share the reference-inspired visual system in `public/assets/workspace.css`, loaded after the existing stylesheet. Changes cover the application shell and every authenticated page rendered through `layouts.app`, including secondary forms, tables, reports, profiles, empty states, and dialogs.

## Shared patterns

- Navy navigation with a short, flat task list, route-derived active states, contextual section links, and a responsive drawer.
- Compact header, page titles, date display in Asia/Karachi, consistent spacing, borders, focus rings, and semantic colors.
- Equal dashboard KPI geometry and aligned panel edges. Dashboard and analytics charts use actual scoped records, with a percentage axis and a result ring drawn from the pass/fail ratio.
- Standardized form sections, filters, table headers and rows, horizontal table scrolling, action buttons, notification rows, and empty states.
- Responsive layouts and reduced-motion support. Mobile drawer supports Escape, focus return, and keyboard focus containment.

The previously removed “Explore screens” prompt and “All module screens” sidebar button remain removed. Module search still provides access to existing routes. The catalog navigation groups assign each existing authenticated screen to one owner; they do not create new backend features or routes.

Navigation was subsequently simplified to 7 Admin sections, 5 Teacher sections and 5 Student sections. Long nested screen lists were removed. Forms, details, confirmation states and duplicate representations are not listed as separate sidebar options. Useful related tools appear as at most five section links; profile and notifications remain in the header. Search uses the curated tool list rather than every catalog screen. Academic resource switching uses one selector instead of seventeen tabs. Existing routes and records are retained.

## Verification

`vendor/bin/phpunit` exercises the existing application test suite against the configured test database. `node tests/browser/workspace.cjs` audits existing local accounts without submitting academic forms, checks every catalog page at desktop and mobile widths, checks the three dashboards at eleven widths (320–1920px), verifies card alignment, and exercises mobile navigation, notification dialogs, and reduced motion.

The browser run generates `docs/workspace-qa.json`, `docs/WORKSPACE-ROUTE-AUDIT.md`, and desktop/mobile dashboard screenshots for each role. Route rendering and layout checks do not mean every record-specific business operation was manually exercised. PDF templates retain their existing print layout.

No database reset, migration, reseeding, password reset, or academic-record change is required for this redesign.

## Completed checks

- Existing PHPUnit suite: 38 tests, 588 assertions passed. After the analytics-template update, the frontend regression subset passed again: 4 tests, 238 assertions.
- Browser route audit: all 197 authenticated catalog pages returned HTTP 200, loaded the workspace design system, and fit the page viewport at both 1440px and 390px.
- Dashboard audit: 33 role/viewport combinations passed at 320, 360, 375, 390, 414, 768, 820, 1024, 1280, 1440, and 1920px. Desktop card top and bottom alignment were measured in the browser.
- Mobile drawer open/Escape, notification-dialog viewport bounds, and reduced-motion checks passed for each role; no JavaScript page errors were recorded.
- Additional browser checks passed for tablet navigation expansion/collapse, dashboard visibility with JavaScript disabled, and denial of the Admin dashboard to a Student account.
- Blade compilation, PHP syntax, and JavaScript syntax checks passed. Screenshots were visually inspected for desktop/mobile dashboards, an academic form/table, and the notification modal.
