# Run ExamCell locally on macOS / MAMP

The ZIP contains the complete Laravel Blade source, migrations, frontend assets, tests and guides. Composer installs the PHP dependencies. Compiled frontend assets are included; Node is needed to rebuild the Tailwind/Alpine frontend (`npm ci && npm run build`) or run browser tests. See DESIGN_SYSTEM.md for the component library and frontend workflow.

## 1. Select MAMP PHP

Start MAMP's MySQL server. In Terminal, list the installed PHP versions:

```bash
ls /Applications/MAMP/bin/php
```

Choose your actual PHP 8.2+ directory and add its `bin` directory to your terminal's PATH. Replace `YOUR_PHP_VERSION` before running:

```bash
export PATH="/Applications/MAMP/bin/php/YOUR_PHP_VERSION/bin:$PATH"
php -v
php -m
composer --version
```

Required extensions include PDO MySQL, BCMath, GD, ZIP, DOM/XML, Mbstring, Fileinfo and Laravel's standard extensions. The included preflight checks the key runtime requirements.

## 2. Prepare the project and database

Extract the ZIP and open Terminal in its `examcell-frontend` folder. Create an empty database called `examcell` in MAMP's database administration tool. Check the real MySQL host, port or socket, username and password in your own MAMP configuration. The example port `8889` is not a verified setting for your Mac.

```bash
composer install
[ -f .env ] || cp .env.example .env
```

Privately edit `.env`:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=YOUR_ACTUAL_MAMP_PORT
DB_DATABASE=examcell
DB_USERNAME=YOUR_ACTUAL_MAMP_USERNAME
DB_PASSWORD=YOUR_ACTUAL_MAMP_PASSWORD
DB_SOCKET=
QUEUE_CONNECTION=database
MAIL_MAILER=log
OPENAI_ENABLED=false
```

Use your real socket path in `DB_SOCKET` if connecting through a Unix socket. Keep an existing `APP_KEY` when updating or recovering an installation. Do not share or commit `.env`.

## 3. Check, migrate and create an administrator

```bash
php artisan config:clear
php artisan examcell:setup-key
php artisan examcell:preflight
# Continue only after MySQL and required runtime checks pass.
php artisan migrate
php artisan examcell:admin your-email@your-institution.edu --name="Examination Administrator"
```

The command privately requests a password of at least 12 characters. For local UI review, `php artisan db:seed` creates these demo accounts when `APP_ENV=local`:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@gmail.com` | `12345678` |
| Teacher | `teacher@gmail.com` | `12345678` |
| Student | `student@gmail.com` | `12345678` |

These are local demo credentials only. Set `EXAMCELL_SEED_DEMO=true` explicitly if you need to seed them in another non-production environment; never use them in production. For an existing installation, back up your database and compare local changes before applying files. Use `migrate`, never `migrate:fresh`, on your real records.

An alternative is `bash scripts/local-setup.sh`: its first run creates `.env` if missing, then asks you to edit it and rerun. It preserves an existing application key and refuses to continue when the connection check fails.

## 4. Start the application and workers

Keep three terminals running from the project folder:

```bash
# Terminal 1
php artisan serve
```

```bash
# Terminal 2: bulk PDFs
php artisan queue:work --tries=1 --timeout=120
```

```bash
# Terminal 3: automatic daily backups
php artisan schedule:work
```

Open **http://127.0.0.1:8000/sign-in** and sign in with the administrator you created. If using MAMP's web server instead, set its document root to this project's `public/` folder and update `APP_URL`; never serve the project root.

## 5. Populate academic settings and run the full flow

1. Academic Management: departments → sessions → semesters → sections → courses.
2. Provision teacher and student users. Add student profiles, enrollment and course-teacher assignments.
3. Provision extra staff as teacher-portal accounts. Add teacher profiles with **Reserve staff enabled**, the relevant department and a daily duty limit. Reserve staff do not need teaching assignments to receive invigilation cover.
4. Create a draft grading scheme, enter the institution's approved boundaries and grade points, including the zero threshold, then approve it. Configure credits, passing criteria and assessment weights; no test policy is automatically installed as university policy.
5. Create an examination, exam-course assignments and components whose weights total 100%. Generate or edit its timetable. You can select multiple rooms; combined capacity must cover eligible enrollment.
6. Preview and allocate seats. Assign primary invigilators for **each room** and optional standby staff. Approve and publish the timetable. Publication validates seats, active course teachers and room cover.
7. Teacher portal → Invigilation Duties: the teacher submits a reason **at least 48 hours before the paper**. The exact deadline is accepted; requests one second late are rejected. The original duty stays assigned while the request is pending.
8. Administrator → Duties: review the request, nominate eligible reserve staff if necessary, then approve or reject. Approval rechecks availability, department, overlapping commitments and workload before changing the duty. Both people receive database notifications. The reserve staff's portal shows the new room/paper duty; the original teacher sees the decision and history.
9. Assigned teachers save and submit marks, or import CSV/XLSX. Absence is separate from missing scores. Administrators calculate, review, approve and publish complete results.
10. Students see only their own published results, admit PDFs, seats and rechecks. Published mark corrections require a separate administrator's approval. Recheck approval never automatically changes marks.
11. Analytics can generate advisory forecasts from authorized published history, with methodology, sample size and uncertainty. Official grades remain deterministic and unchanged by forecasts or AI.

The same record forms and services serve the catalog's screen variants. Normal empty screens mean your database has no configured records yet; the app does not invent academic data.

## 6. Optional OpenAI and email

Configure a private OpenAI key and permitted model, set `OPENAI_ENABLED=true`, then clear the config cache. The assistant's explanation checkbox opts into provider calls. Exact authorized database answers work with OpenAI disabled. See `docs/OPENAI-SETUP.md`.

`MAIL_MAILER=log` stores local password-reset links in `storage/logs/laravel.log`; it does not send email. Configure the SMTP settings in `.env` to deliver reset links. Workflow notifications are always available in the signed-in notification center.

## 7. Back up and restore

Administrator → Backups creates, verifies and downloads a database/private-document archive. The scheduler runs the same command daily at **02:00 Asia/Karachi** while its process is running. You can also run:

```bash
php artisan examcell:backup
```

Keep the original `.env`/`APP_KEY` separately and securely; secrets are not included in the backup. Backups contain sensitive academic records, so keep downloaded archives private.

Restore through the Backups form or the CLI, using a **separate empty database** ending in `_restore`:

```bash
php artisan examcell:restore /absolute/path/to/backup.zip examcell_recovery_restore
```

The signature and document/database checksums are verified. The current database is retained. Review the restored database and files staged under `storage/app/private/restore/examcell_recovery_restore`. Once satisfied, copy the staged documents to their corresponding private-storage paths, set `DB_DATABASE` to the recovery database, preserve the original application key, and clear configuration. Keep workers stopped while switching databases. The MySQL account needs permission to create the separate restore database. For large web uploads, set MAMP PHP’s `upload_max_filesize` and `post_max_size` appropriately and restart MAMP; `RESTORE_UPLOAD_MAX_KB` configures application validation (default 512 MiB). CLI restore avoids HTTP upload constraints.

## 8. Verify your installation

Use `php artisan examcell:preflight`. Check a login, one paper allocation, a teacher request, reserve approval, marks publication, an actual PDF, and a manual backup. Inspect the queue progress and backup history for real completion.

Automated tests require a separate disposable `examcell_test` database. Never point them at real academic records. See `docs/TESTING.md` and `docs/FINAL-QA.md` for executed checks and reproduction instructions.
