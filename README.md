# ExamCell — Final Laravel Blade + MySQL project

Start with **[START-HERE.md](START-HERE.md)** for the complete MAMP walkthrough.

The Laravel 12 project has authenticated administrator, teacher and student workflows, now presented through a shared Vite/Tailwind/Alpine design system with light and dark modes. The catalog contains 199 unique entries after removing one duplicate; existing controller behavior, permissions and data workflows are preserved. Academic records, schedules, seats, duties, marks, results, notifications and audit events persist in MySQL. Browser localStorage stores only theme preference, never academic records.

## Frontend development

Compiled frontend assets are included in `public/build`. To rebuild after changing Blade, CSS or JavaScript:

```bash
npm ci
npm run build
# Optional during development:
npm run dev
```

Use a Node version supported by the installed Vite release (Node 22.12+ recommended). The application server itself does not require Node. Normal sign-in opens the appropriate role dashboard without the styleguide. The admin-only `/styleguide` remains an optional direct development reference and is absent from normal navigation. Brand assets live in `public/brand`. Submission deadlines, persisted academic-context switching and signed admit-card QR verification are documented in [Backend features](docs/BACKEND_FEATURES.md). PNG QR generation requires the GD PHP extension.

See [REDESIGN_PLAN.md](REDESIGN_PLAN.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), [screen mapping](docs/SCREEN_AUDIT.md), [validation evidence](docs/REDESIGN_VALIDATION.md), and [CHANGELOG.md](CHANGELOG.md).


## Run on your Mac with MAMP

1. Keep your existing project and database backup. This archive updates the supplied frontend; it cannot include changes made independently on your Mac. Compare your local changes before copying files over them. Dependencies and secrets are deliberately excluded.
2. In MAMP, check the actual PHP version, MySQL host/port or Unix socket, database name, username and password. PHP must be 8.2+ with PDO MySQL, BCMath and Laravel's normal extensions. Select that PHP binary for Composer and Artisan. Port `8889` in `.env.example` is an example, not verified configuration.
3. Create an empty `examcell` database using MAMP's database administration tool. Do not run `migrate:fresh` on your real project database.
4. From the project directory:

```bash
composer install
[ -f .env ] || cp .env.example .env
```

For a new installation with an empty `APP_KEY`, run `php artisan key:generate`. Keep the existing key when updating an established installation.

Edit `.env` with **your actual MAMP settings**. Set `APP_URL` to the URL used to open the app. Use `DB_HOST=127.0.0.1` for TCP and the real port; leave `DB_SOCKET` blank. To use MAMP's socket, set its actual path instead. Do not commit `.env` or share passwords/API keys.

```bash
php artisan config:clear
php artisan examcell:db-check
# Continue only after the connection check succeeds.
php artisan migrate
php artisan examcell:admin you@your-institution.edu --name="Examination Administrator"
php artisan serve
```

The administrator command asks privately for a password of at least 12 characters. No default administrator login or public registration is provided. Open `http://127.0.0.1:8000/sign-in` when using Artisan's server, or configure MAMP's document root to the project's `public/` directory. Do not expose the project root as a web directory.

Start a second terminal for bulk admit-card jobs:

```bash
php artisan queue:work --tries=1 --timeout=120
```

Bulk requests show **queued**, **processing**, **completed** or **failed** and the actual generated count. The queue worker must be running. Individual PDF generation does not require a worker. Start `php artisan schedule:work` in a third terminal for daily verified backups at 02:00 Asia/Karachi.

## First academic setup

Use Academic Management's resource links to create departments, sessions, semesters, sections and courses. Provision student/teacher users, then add student profiles, enrollment and teacher assignments. Teacher profiles configure reserve staff and daily duty limits. Provision extra staff as teacher-portal accounts and enable their reserve profile for the department.

Create a **draft** grading scheme, add boundaries including a zero threshold, then approve it. Enter the institution's approved boundaries; no sample scheme is automatically seeded. Credit hours, assessment maximums, weights and passing marks are configurable. BCMath implements weighted percentages, half-up rounding and credit-weighted GPA. The supplied GPA policy uses published final examinations and the latest published attempt per course; `CGPA_ATTEMPT_POLICY=all` includes all final attempts. Obtain institutional confirmation before treating this as official policy.

Create an examination, its exam-course assignments and assessment components whose weights total 100%. Build or generate its timetable using one or more rooms, preview and allocate seats, and assign primary cover to each room. Approval/publication checks combined capacity, enrollment, seating, teacher assignments and duty coverage. Published edits use a draft examination revision and retain the previous schedule. A revision also creates fresh assessment assignments; it does not copy or overwrite published marks/results.

Teachers request duty unavailability at least **48 hours before the paper**. A pending request retains the original duty. Administrators nominate eligible reserve staff, then approve or reject; approvals revalidate department, availability, commitments and workload and notify both people. Assigned teachers enter/import marks, save drafts and submit complete batches. Administrators calculate results, submit the batch, move it under review, approve and publish it. Approval recalculates from the latest submitted marks. Publication requires a published examination and complete records. Returned marks require resubmission. Published corrections record the original score/absence, proposed values, reason and approvals; a different administrator must approve them. Recheck approval is a review decision and never silently changes a mark.

## Forecasts, backups and imports

Performance analytics generates permission-scoped statistical forecasts from published final-exam attempts. Each forecast records sample size, method, uncertainty and an advisory notice. It does not alter marks or official grades.

The Backups screen creates/downloads signed, checksum-verified MySQL/private-document archives and restores them to a separate empty database. `examcell:backup` and `examcell:restore` provide equivalent CLI operations. Preserve your original `APP_KEY` for signature verification.

Student and marks imports support CSV and modern Excel XLSX. Use the displayed headers and one worksheet. Values are validated before preview/commit; formulas/macros are rejected. No spreadsheet package or JavaScript framework is needed.

## AI and mail

The assistant retrieves only authorized results, schedules, seats or duties. Exact database answers work without OpenAI. To request optional AI explanations, privately configure `OPENAI_API_KEY`, a permitted `OPENAI_MODEL`, and `OPENAI_ENABLED=true`, then clear the configuration cache. The checkbox in the assistant opts into explanation. See [OPENAI-SETUP.md](docs/OPENAI-SETUP.md). AI has no write tools and cannot determine official grades.

Password reset uses Laravel's password broker. `MAIL_MAILER=log` writes reset links to the local Laravel log; it does **not** deliver email. Configure SMTP in your private environment to deliver messages. Application workflow notifications are stored in the database with per-user unread state.

## Tests and documentation

See [TESTING.md](docs/TESTING.md) for the dedicated MySQL test database and browser test setup. Tests refuse to reset a database whose name does not end in `_test`. Never point tests at a database containing real records.

- [BACKEND-AUDIT.md](docs/BACKEND-AUDIT.md): original findings and restore point.
- [DATABASE.md](docs/DATABASE.md): tables, relationships and precision.
- [BACKEND-SCREEN-MAP.md](docs/BACKEND-SCREEN-MAP.md): each catalog screen's actual backend binding.
- [ROUTES.md](docs/ROUTES.md): authenticated endpoints and roles.
- [FINAL-QA.md](docs/FINAL-QA.md): exact verification and remaining environment checks.

`vendor/`, `.env`, generated private PDFs, test database files, runtime binaries and browser screenshots are excluded. Run Composer after extracting. Original demonstration partials remain in source for reference, but `ScreenController` renders `backend/*` views; public routes no longer use `DemoController`. `public/assets/app.js` contains navigation behavior only. Fixture data and fixture passwords exist only under `tests/` and are never automatically loaded into a normal application database.
