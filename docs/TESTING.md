# Verification and safe reproduction

PHPUnit uses MySQL, not SQLite. Create a separate empty database named `examcell_test` on your MySQL/MAMP server and configure its connection through `.env.testing` or shell environment. Never reuse the academic application database. `tests/TestCase.php` checks the configured driver and `_test` suffix before `RefreshDatabase` can reset tables.

```bash
# Set host, real port/socket and private credentials for your dedicated test server.
php artisan config:clear
DB_DATABASE=examcell_test php vendor/bin/phpunit --testdox
php vendor/bin/pint --test app config routes database tests
php artisan view:cache
```

The tests use `RefreshDatabase`, so existing contents of the dedicated test database are not preserved. Fixture boundaries and users are test inputs only. No default grading scheme or account is provisioned in the real app.

Coverage includes all 200 catalog screens behind authenticated roles, login/logout/inactive accounts, reset tokens, role tampering, server validation and pagination; academic history immutability; shared-student/capacity/availability schedule conflicts; schedule revisions; seating uniqueness and student scope; duty workload and deterministic replacement proposals; missing scores versus absence; marks authorization and submission locking; weighted grading/boundaries/rounding; publication completeness; stale-result recalculation; correction approval and audit history; rechecks; notifications; private actual PDFs; queued bulk generation; CSV/XLSX preview/commit/duplicates and formula rejection; exact 48-hour boundaries, duplicate/late/unauthorized requests, reserve cover promotion/revalidation and notifications; multi-room allocation/publication coverage; signed backup creation, web restore, tamper rejection and download authorization; statistical forecasts and history scoping; SQL analytics and roster scope; AI missing-key, failure, timeout, invalid-output, rate/budget and conversation ownership paths.

## Optional browser tests

Install the optional browser dependencies. These are not needed to run the application.

```bash
npm install
npx playwright install chromium
```

Browser fixtures must only be created in an empty dedicated MySQL database ending in `_test`. Set the test connection in the shell and use `APP_ENV=local` for browser HTTP checks so CSRF is genuinely enforced. Use `SESSION_DRIVER=file`, `CACHE_STORE=file`, `OPENAI_ENABLED=false`, and a valid local application key.

```bash
# Only after confirming this is the disposable test database:
DB_DATABASE=examcell_test php artisan migrate:fresh
DB_DATABASE=examcell_test php tests/browser/fixture.php
DB_DATABASE=examcell_test APP_ENV=local SESSION_DRIVER=file CACHE_STORE=file npm run test:browser
```

The browser test starts a local PHP server on port 8000; that port must be free. `PHP_BINARY` may select your MAMP PHP executable. `CHROMIUM_EXECUTABLE_PATH` optionally selects an existing compatible browser. The fixture password is `TestPassword2026!` for test-only accounts such as `admin@example.test`, `teacher@example.test` and `one@example.test`. Never use these in a real installation. The script verifies real CSRF rejection, form persistence after reload, role access, PDF contents, student scoping, assistant fallback, recheck persistence and 1440/1024/390-pixel layouts, and writes a textual QA report without screenshots.

## Checks still required on your Mac

Confirm actual MAMP connectivity with `examcell:db-check`, PHP extensions, SMTP delivery if enabled, the queue worker, filesystem write access and your privately configured OpenAI model/key. Workspace tests cannot verify these settings or your institution's grading/eligibility policy. This run is not a concurrency load test or a production penetration test.

## Additional executed flow checks

`tests/concurrency.php` runs two PHP processes against the same disposable MySQL database to verify one pending duty request and serialized seating regeneration without duplicate assignments. Start it with an empty `_test` database after migrations; it intentionally provisions test records.

`tests/queue-smoke.php` dispatches a real database job using the browser fixture records. Run `php artisan queue:work --once --tries=1 --timeout=120`, then `php tests/queue-smoke.php verify` to verify actual PDF generation. These checks must only run with the disposable test connection.

Unit tests isolate private file storage using Laravel's fake local disk; real HTTP browser checks use a separate QA document directory. Test clock freezing makes the 48-hour rules reproducible rather than depending on when tests happen to run.
