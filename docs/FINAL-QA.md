# Final project acceptance — 2026-10-12

This release closes the previously identified implementation gaps in duty requests, multi-room allocation, forecasting, automated backups and local startup documentation. Configuration-specific checks are distinguished from implemented and executed behavior.

| Check | Executed result |
|---|---|
| Runtime | PHP 8.3.6, Laravel 12.69.3 |
| Database | MySQL PDO against isolated MariaDB 10.11.14 over TCP; no real academic database used |
| Schema | Four migrations; 45 tables including Laravel operational tables |
| Automated backend tests | **38 passed, 588 assertions**, no failures/errors |
| Catalog | All 200 screen specifications checked under their authenticated roles |
| Browser | **24 checks passed**; no JavaScript errors |
| Viewports | Dashboard/results/assistant verified at 1440, 1024 and 390 pixels |
| Concurrent writes | Two separate PHP processes: one duty request saved and duplicate rejected; seating revisions serialized with unique assignments |
| Queue | Real database job dispatched; actual `queue:work --once` generated two private PDFs successfully |
| Backups | Browser and CLI backup creation/download verified; signed database/document checksums validated |
| Restore | Administrator web restore verified into a separate database; original records retained; tampered archive rejected |
| Local readiness | Preflight confirmed PHP/PDO/BCMath/ZIP/app key/storage/queue configuration and MySQL connectivity in the QA environment |
| Packaging checks | Composer validation, PHP formatting, Blade compilation and setup-script syntax checked |

Evidence is included in `backend-junit.xml`, `backend-browser-checks.json`, `concurrency-checks.json`, `schema-columns.json`, `routes.json` and `ACCEPTANCE-OUTPUT.txt`.

## Verified business flow

Academic setup → approved grading → examination components → conflict-checked single/multi-room timetable → unique room-specific seats → primary cover for each room → publication → teacher request at least 48 hours before paper → pending request retaining original cover → eligible reserve nomination → administrator revalidation/approval or rejection → staff notification and preserved duty history → teacher marks/CSV/XLSX → deterministic grading and result review/publication → student-owned results, admit cards, seats and rechecks → reviewed corrections, advisory forecasts and verified backups.

The exact 48-hour deadline is accepted; a request one second late is rejected. The teacher cannot request another person's duty. Pending requests and competing reserve proposals are protected by transactional locks. Inactive, unavailable, overlapping or over-workload reserve staff cannot be approved. Existing standby staff can be promoted without duplicate duty records. Every published room requires primary cover; every eligible student needs a valid seat.

Forecasts use actual authorized published history and document their method/sample size/uncertainty. A short history produces a broad advisory range rather than a fabricated confident grade. Forecasts and OpenAI explanations cannot change official marks/results. XLSX imports read checked values and reject formulas, macros and unsafe XML; duplicate and assignment validation is shared with CSV imports.

Backups use an operating-system file mutex, consistent database export, signed manifests and document/database checksums. Daily scheduling is registered for 02:00 Asia/Karachi. Restore verifies the signature and restores into a separate empty database, staging documents for review instead of overwriting the active installation.

## Local configuration to supply

Use `START-HERE.md` and the included setup script. Your actual MAMP host/port/socket/password, SMTP transport, OpenAI project key/model and institution-approved grading/eligibility settings must come from your installation. The app provides connection/runtime diagnostics and all workflows, but this workspace cannot verify credentials or servers on your Mac. Live OpenAI was tested through success/failure/timeout/quota fakes, not through an undisclosed real key. The queue and scheduler processes must remain running to perform background work.

The recorded concurrency and security tests are concrete checks of this release; they are not a guarantee of unlimited capacity or proof that every possible production attack has been excluded. Keep resource protections, authorization and the requested 48-hour business rule enabled. There are no hidden success responses substituting for database writes, generated PDFs, provider output or backups.
