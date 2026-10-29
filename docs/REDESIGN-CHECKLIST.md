# ExamCell product redesign checklist

This checklist records the discovered Blade screen catalog and the shared sources used to redesign it.

## Route coverage

| Role | Catalog screens | Shared layout | Primary modules |
|---|---:|---|---|
| Admin | 159 | `resources/views/layouts/app.blade.php` | Academic, examinations, schedules, seating, duties, marks, results, analytics, reports, audit, backups, settings |
| Teacher | 24 | `resources/views/layouts/app.blade.php` | Courses, roster, marks, schedule, seating, duties, analytics, assistant, notifications, availability |
| Student | 17 | `resources/views/layouts/app.blade.php` | Profile, courses, schedule, admit card, seating, results, analytics, rechecks, assistant, notifications |

## Shared screen kinds

Dashboard, table, history, result, calendar, form, modal, import, grading, wizard, state, PDF, seating, analytics, chat, authentication, marks, notifications, profile, admit, and lookup screens all resolve through `ScreenController` and the shared application shell.

## Shared redesign sources

- `resources/views/layouts/app.blade.php` — navigation, header, catalog, notifications, dialogs, and page frame.
- `public/assets/app.css` — design tokens, responsive layout, forms, tables, state styles, and dialog styling.
- `public/assets/app.js` — navigation, catalog search, profile menu, dialog focus management, confirmation processing, and duplicate-submit protection.
- `resources/views/components/` — buttons, fields, tables, badges, KPIs, and icons.
- `resources/views/backend/` — server-backed workflow bodies.
- `resources/views/screens/` — catalog-driven screen bodies and state variants.

## Verification status

- Blade view cache: verified.
- PHP route syntax: verified.
- Application JavaScript syntax: verified.
- Composer autoload: verified.
- PHPUnit: executed; blocked before assertions by the environment’s inaccessible MAMP MySQL socket (`Operation not permitted`).
- Browser checks: require a running local PHP/MySQL environment and browser session.

No database schema, authorization policy, authentication flow, or workflow request contract was changed by the visual redesign work.
