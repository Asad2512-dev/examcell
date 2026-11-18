# ExamCell: system guide and defense preparation

Prepared from the repository on 12 November 2026 for a university project panel. The planned delivery is 15–20 minutes including questions, with a technical explanation, workflow demonstration and speaker notes.

## 1. Project introduction

ExamCell is a web-based examination management system with administrator, teacher and student accounts. It connects academic setup to examination planning, seating, invigilation, admit-card issuance, marks submission, result publication and rechecking. It also includes reporting, audit records, backups, statistical analysis and optional provider-backed AI assistance.

A useful opening statement:

> “My project, ExamCell, manages the examination lifecycle in a single application. It gives administrators control over planning and publication, lets teachers submit marks for their assigned courses, and lets students access their own published information. Its main technical focus is enforcing academic rules and maintaining traceable decisions.”

The repository establishes implemented features and intended checks. It does not establish a measured reduction in administrative time, a production deployment, forecast accuracy, or successful security certification. Describe the manual-process problems as project motivation rather than claiming an institution-specific survey you have not performed.

Evidence: `README.md`, `routes/web.php`, `app/Services`.

## 2. Problem, objectives and scope

Typical exam administration requires staff to reconcile course records, enrollments, rooms, dates, teachers and marks. A timetable can look correct while still assigning one student to simultaneous exams. A results spreadsheet can contain missing components or a value outside the allowed range. Students also need to distinguish draft information from officially published information.

ExamCell addresses these coordination problems through central records, conflict checks and explicit workflow states. Its objectives are to organize examination data, reject invalid operations, control publication, support access by role and preserve important decisions in audit records.

The scope includes academic administration and exam operations. The repository does not implement an online question delivery engine, automatic answer evaluation, biometric attendance or institutional payment processing. Single sign-on and external university integrations are future possibilities rather than established features.

## 3. Stakeholders and access

| Role | Main responsibilities | Access boundary |
|---|---|---|
| Administrator | Academic structure, accounts, exams, rooms, schedules, seating, duties, approvals, reports, backups and settings | Admin middleware protects administrative routes. Domain rules still constrain operations. |
| Teacher | Assigned courses and rosters, marks entry/import, submission, duties, replacement requests and scoped insights | Course/section assignments constrain access. Submission ownership and workflow state also matter. |
| Student | Own enrollment information, published schedule, seats, admit card, results, rechecks and scoped assistance | Own records and publication status constrain access. |
| Public verification visitor | Check a signed admit-card verification link | Limited name, roll number, exam name and current validity projection. |

Authorization uses multiple layers: authentication, active-account checks, role middleware, model policies and scoped queries. Hiding a navigation link alone is not authorization. Useful evidence includes `app/Policies/ExamCoursePolicy.php`, `CalculatedResultPolicy.php`, `AdmitCardPolicy.php` and `app/Services/ScopedQueries.php`.

## 4. Architecture and technology

The system is a Laravel monolith using MVC organization with a domain service layer. It serves Blade HTML to the browser rather than operating as a separate single-page frontend and API backend.

A typical request follows this sequence:

1. The browser sends an HTTP request to a Laravel route.
2. Middleware checks authentication, account status and role as configured.
3. A controller validates the request and authorizes the requested resource.
4. A service enforces business rules and performs the operation.
5. Eloquent models query or update MySQL, often inside a transaction.
6. A Blade view, redirect, JSON response or PDF returns to the browser.

| Layer | Technologies and purpose |
|---|---|
| Backend | PHP `^8.2`, Laravel `^12.0`, Eloquent ORM and MySQL |
| Views | Laravel Blade templates |
| Browser behavior | Alpine.js and JavaScript |
| Styling and build | Tailwind CSS and Vite |
| Charts and presentation effects | Chart.js and GSAP |
| PDFs | `barryvdh/laravel-dompdf` |
| QR codes | `endroid/qr-code` |
| Optional AI | `openai-php/laravel` and server-side HTTP integration |
| Testing | PHPUnit feature tests, Playwright scripts and Node frontend tests |

These are dependency declarations in `composer.json` and `package.json`. They do not prove which versions are installed on a particular machine. This checkout had no `vendor` or `node_modules` directories during review.

### Code map

| Location | Responsibility |
|---|---|
| `routes/web.php` | HTTP endpoints and route middleware |
| `routes/console.php` | Installation, administration, backup and scheduled commands |
| `bootstrap/app.php` | Routing and middleware setup |
| `app/Http/Controllers` | Request handling and service orchestration |
| `app/Http/Requests` | Form validation rules |
| `app/Policies` | Resource authorization |
| `app/Services` | Academic and examination business rules |
| `app/Services/AI` | Structured AI proposals and validators |
| `app/Models` | Relational entities and Eloquent relationships |
| `app/Jobs` | Background admit-card and AI tasks |
| `resources/views` | Blade screens and printable templates |
| `resources/js`, `resources/css` | Frontend source |
| `database/migrations` | Schema evolution and constraints |
| `tests` | Feature, frontend, browser, concurrency and queue checks |
| `deploy`, `scripts` | Operational helpers and validation scripts |

## 5. Academic setup and data dependencies

Start with sessions, departments, semesters, sections and courses. Create teacher and student accounts, student profiles, enrollments and teaching assignments. Configure grading schemes and boundaries before approving them. Then create an examination and examination courses with assessment components.

A `course` represents an academic subject. An `exam_course` represents that course for a particular examination and section. Keeping these separate allows the same academic course to appear in multiple examination events with different scoring components, schedules and students.

A section combines a department with a semester. Enrollments connect a student to a course and section. Teacher assignments connect a teacher to a course and section. An academic context service applies selection filters, while authorization still determines which records the user may access.

## 6. Examination planning and publication

Examinations move through draft, approved and published states. A published examination can have a draft revision. Publishing that revision archives the previous examination. A pending revision prevents another simultaneous pending revision for the same published examination.

Scheduling checks active rooms, total room capacity, session boundaries, paper duration, room unavailability and overlapping papers for shared students. Papers starting together can share rooms if the combined enrollment fits. They must receive distinct physical seats.

The baseline generator is greedy. It visits examination courses by ID, tries dates at 09:00, 13:00 and 17:00, considers rooms in descending capacity and accepts the first feasible allocation. If it cannot find a slot, it throws a validation error and the transaction rolls back the generation. Increasing the date range or adding suitable rooms can make another attempt feasible.

This algorithm does not guarantee the best possible timetable. Do not call it a genetic algorithm, global optimizer or learned scheduling model. The optional AI date-sheet path can interpret additional constraints and checks teacher conflicts, but its proposals still require server validation.

Before publication, the system checks an approved grading scheme, course schedules, component weights totaling 100%, active assigned teachers, exact eligible seating coverage and primary invigilation coverage for rooms. It rechecks relevant conflicts. A timetable therefore has operational prerequisites beyond a list of dates.

Evidence: `app/Services/SchedulingService.php`, `app/Services/AI/DateSheetService.php`.

## 7. Seating and visual room plans

The baseline seating allocator orders eligible students by ID and fills available numbered seats in allocated rooms. Seats already used by another paper in the same shared slot are skipped. Multiple rooms can support a large paper.

A manual or assisted override must contain every expected eligible student exactly once, use only allocated rooms, stay within each room's capacity and avoid duplicate seats. Cross-paper sharing is also checked. Published and archived examinations protect seating from direct changes and require a new examination version.

Room-layout and seat-map services support visual display and printing. Optional AI seating uses its own proposal validation and adjacency analysis. The ordinary allocator should not be presented as a guarantee against cheating or as a globally optimal separation algorithm.

Evidence: `SeatingService.php`, `SeatMapService.php`, `AI/SeatingAiService.php`, room-layout migration and controller.

## 8. Invigilation and replacement

Duty assignment requires an active teacher and checks unavailability, overlapping commitments, outstanding overlapping proposals and the teacher's daily duty limit. The configured default is two duties per day, with teacher profile settings able to override it.

A teacher requests replacement at least 48 hours before the paper. A pending request leaves the current duty assigned. The system searches eligible reserve teachers from the department, filters blocked staff and orders candidates using current assigned workload and an ID tie-breaker. Optional AI may help select among candidates.

An administrator can nominate eligible reserve cover and approve the replacement. Approval rechecks candidate eligibility and whether the paper has started. It transfers or promotes the replacement duty, marks the old duty replaced, records the decision and notifies relevant users. Historical duties are protected from inappropriate reassignment.

Evidence: `app/Services/DutyService.php`, `config/examcell.php`.

## 9. Admit cards and verification

Admit cards are PDF documents associated with an examination and student. Bulk generation can use the database queue. A queue worker must run for queued work to progress.

Each admit record has a UUID reference. Its QR code encodes a Laravel signed verification URL. Signature middleware rejects modified URLs. The verification service checks the student's active and eligible status, publication of the exam, enabled admit issuance and the availability of schedules for eligible courses.

The public response exposes a limited projection: student name, roll number, examination name and current validity. It sends no-store and no-index related headers. It does not expose the entire user/profile model. A signature protects link integrity, while the record's current status determines validity. This is not biometric identity verification, and the URL is not described in code as an expiring token.

Evidence: `ReportService.php`, `AdmitVerificationService.php`, `AdmitVerificationController.php`, `routes/web.php`.

## 10. Marks, imports and deadlines

Teachers enter marks for authorized course/section assignments. A draft supports incomplete work. Rows validate student eligibility, component ownership, duplicate combinations, numeric format and score range. A submitted set must include every required component for every eligible student.

Three values have different meanings:

| Case | Representation | Consequence |
|---|---|---|
| Zero score | Score `0`, absence false | Valid scored performance |
| Absent | Blank score, absence true | Contributes zero and records absence |
| Missing | No record or blank score without absence | Blocks complete submission/calculation |

Submitted marks lock until an administrator returns the submission with a reason. Approved or published batches prevent ordinary direct editing. Administrators can configure course deadlines and teacher-specific deadline overrides. The presence of deadline display/countdown should not be presented as proof of a strict late-submission rejection rule.

Baseline imports support CSV and XLSX with validation and preview before commit. CSV headers must match exactly. Student rows use `roll_number,name,email,section_id`. Marks use `roll_number,component_id,score,absent`. The parser caps input at 1000 rows. Imported students receive a random initial password value and set their usable password through recovery. Existing emails or roll numbers are rejected by the student import path.

Evidence: `MarksService.php`, `ImportService.php`, `XlsxReader.php`, `SubmissionDeadlineController.php`, `app/Models/ExamCourse.php`.

## 11. Grading and GPA

For each component, weighted contribution equals score divided by component maximum, multiplied by the component weight. Sum these contributions to obtain the percentage, then apply scheme rounding. Component weights total 100%.

Illustrative example: a student scores 16 of 20 on a component weighted 20%, and 60 of 80 on a component weighted 80%. Contributions are 16 and 60 percentage points, giving 76%. This example is invented only to explain the formula and is not an academic record.

Approved grade boundaries map the rounded percentage to a grade and grade points. Passing compares the percentage to `passing / maximum × 100` for the examination course. Quality points equal course credits multiplied by grade points. GPA is total quality points divided by total credits. With no credits, the service returns null rather than dividing by zero.

BCMath provides decimal arithmetic. Academic GPA filters to final-exam results and can apply the latest published attempt per course under the configured policy. Avoid using predicted scores in official GPA. An absence flag does not by itself force failure in the grading function: the weighted percentage still determines the passing boolean.

Evidence: `app/Services/GradingService.php`, `config/examcell.php`.

## 12. Results, corrections and rechecks

Results cannot calculate successfully if components are missing, weights are invalid, submissions are incomplete or required marks are missing. The expected count of eligible results matters at state transitions.

The normal result sequence is draft, submitted, under_review, approved, published. Returned work can be submitted again. Approval recalculates results to avoid publishing stale draft calculations after marks have changed. Publication also requires the examination schedule to be published. Student queries filter to their own published results.

Published mark corrections require a separate request recording the old and proposed new score/absence plus a reason. A different administrator must approve. The service detects changes to the original record since proposal, applies the approved correction in a transaction, recalculates the affected result and records before/after evidence.

Students can request a recheck of their own published result. An administrator records the decision and reason. Approving the recheck is a decision about the request and does not automatically change a mark. Actual changes use the correction workflow.

Evidence: `ResultsService.php`, `ScopedQueries.php`, model policies.

## 13. AI and statistical boundaries

Three different mechanisms appear in the system:

| Mechanism | Implementation | Role |
|---|---|---|
| Fixed academic rules | PHP validation, grading and workflow services | Authoritative constraints and calculations |
| Statistical analysis | Completeness rules, population standard deviation and least-squares trend | Review flags and advisory estimates |
| Provider-backed AI | Server-side AI client and feature-specific services | Proposals, interpretation and explanations |

AI assistance includes date-sheet rules and proposals, seating, import assistance, duty recommendations, notification drafts, reports and triage. The shared AI client requests structured JSON and records usage metadata. Feature validators check the proposed data. Approval paths determine whether a supported proposal can change records. A provider response is never a substitute for database constraints or domain validation.

The assistant retrieves authorized schedule, seat, result or duty records using scoped queries. It can optionally explain the returned records. Its assistant context has no write tools. Do not generalize this to say every AI feature is read-only, because approved automation features can apply supported changes.

AnalysisService flags completeness issues, absences and missing results. With at least five calculated scores and nonzero population standard deviation, it flags scores whose absolute deviation exceeds twice that standard deviation. Such a flag calls for human review and does not prove cheating.

ForecastService uses up to eight authorized published final-exam attempts for the same student/course. With fewer than three observations, it uses the last score as baseline and an uncertainty range of 0–100. With three or more, it fits a least-squares trend and an approximate Student-t prediction interval, with a minimum five-point uncertainty floor and clipping to 0–100. Repeated attempts may not be comparable. Forecasts do not save predicted grades or modify official results.

Provider features require credentials, a permitted model and connectivity. The system's core rule-based workflows can operate without that configuration. Do not claim external AI accuracy, data residency or privacy guarantees that the repository does not establish.

Evidence: `app/Services/AI`, `AssistantService.php`, `AnalysisService.php`, `ForecastService.php`.

## 14. Security, audit and recovery

Implemented controls include hashed password casting, protected sessions, role checks, policies, record scoping, validation, throttled endpoints and signed admit verification. Transactions and row locks support consistent multi-record changes and reduce conflicting writes. These controls do not prove complete security or the absence of race conditions.

AuditService records important actions, actor, entity and change details. Some operations record reasons and before/after values. Audit records support traceability but live in the same application database. They should not be described as immutable, independently notarized or protected from a privileged database operator.

Backups include a logical SQL dump, private files and a manifest with SHA-256 checksums and an HMAC signature tied to the original application key. Verification detects changed files and unsafe or unrecognized archive contents. Integrity verification is different from encryption: the code does not establish an encrypted ZIP archive.

Restore requires a separate empty database whose name ends in `_restore`. It stages the private files and does not automatically switch the running app. Keep the original APP_KEY securely for verification and protected settings. Rehearse recovery before claiming a recovery time objective.

Evidence: `User.php`, `routes/web.php`, `app/Policies`, `AuditService.php`, `BackupService.php`.

## 15. Deployment and operations

The README documents local Windows deployment using PHP 8.2+, Composer and MySQL. Use PHP extensions required by the manifest and preflight checks. Node/npm are needed to rebuild frontend assets. The web document root should expose only `public`.

After configuring a private environment file, installation uses dependency installation, key setup, preflight and migrations. Create administrator accounts through the command interface. Demo seeding is for a separate local/testing demonstration database.

Run a queue worker for queued tasks and a scheduler for periodic work. Daily backups are scheduled at 02:00 in the application's Asia/Karachi timezone. Reset-token cleanup is scheduled hourly. Scheduling a task does not make it run unless the scheduler operates.

Log mail records messages locally instead of delivering them. Configure SMTP for real password-reset delivery and notifications requiring email. Keep environment values, keys and reset links out of presentation screenshots.

## 16. Testing and evidence limits

The repository contains feature tests for authorization, account flows, imports, workflow states, academic context, marks deadlines, admit verification, password recovery, AI automation and rendering. It also contains Playwright browser scripts, Node frontend tests, a concurrency script and a queue smoke script.

No application test suite ran during this documentation review because installed dependencies were absent. Do not present the existence of a test as evidence that it passed. Do not invent test counts, coverage percentages, throughput, response time or user acceptance results.

A valid evaluation plan is to install dependencies, configure the documented disposable `_test` database, run feature and frontend checks, then exercise a representative demo. Follow with institution-specific usability evaluation, schedule quality comparison, load tests, access-control review and recovery rehearsal. Database tests can reset tables, so never point them at the working academic database.

## 17. Presentation timing and demo script

For a 15–20 minute slot including questions, use a selected route instead of reading all 28 slides. Suggested main route: slides 1, 2, 4, 6, 8–18, 20 and 21. Explain the main slides in about 10–11 minutes, demonstrate for 3–4 minutes and reserve 3–5 minutes for questions. Skip deeper explanation when time runs short. The other slides are backup material.

Before the session:

- Rehearse with a separate demo database and browser sessions for each role.
- Prepare a future draft exam, approved grading scheme, components totaling 100%, assigned teachers, eligible enrollments, rooms and reserve staff.
- Prepare complete marks for quick result publication and a separate invalid-input case.
- Prepare a published exam/admit card as a fallback, and two administrator accounts if demonstrating published corrections.
- Confirm the database, application, queue worker and scheduler are running as needed. Confirm QR verification uses a reachable application URL.
- Use the browser presentation and PDF if the application is unavailable.

Demo narration:

1. **Administrator:** “The academic structure and assignments define who belongs to each examination paper.” Show one course, section and eligibility relationship.
2. **Scheduling:** “A date is valid only when it satisfies the constraints.” Attempt a known conflicting slot, then show a valid prepared plan. Explain capacity, student overlap and publication prerequisites.
3. **Seating and duties:** Show a room map, student seats and primary invigilation coverage.
4. **Teacher:** Enter an out-of-range score to show validation, correct it, then submit the prepared complete marks. Explain why submission locks editing.
5. **Administrator:** Calculate results and move through review and approval before publication. Explain that approval recalculates the batch.
6. **Student:** View the published result and admit card. Open the QR verification link and optionally submit a recheck request.

Keep a duty-replacement request or published correction for questions, because those require extra setup and can consume the live-demo time.

## 18. Viva questions and short answers

### Why did you choose this project?
Examination work involves tightly connected records and high consequences for inconsistent results. The project demonstrates both a useful academic workflow and technical controls for access, validation and publication.

### What is your main contribution?
Integrating the examination lifecycle with explicit state transitions, validation and traceable decisions. Avoid claiming that examination systems themselves are a new invention.

### Why Laravel?
The implementation uses its routing, middleware, policies, Blade views, Eloquent, transactions, queues and test support. The service layer keeps complex rules separate from request-handling code.

### Why MySQL?
The data is relational. Foreign keys and unique constraints suit enrollments, components, seat assignments and results. Transactions help keep related writes consistent.

### Is this MVC?
Yes. Routes and controllers handle requests, models represent persistent data and Blade supplies views. Domain services contain substantial business logic beyond a minimal MVC structure.

### Is it a microservice system?
The repository shows a Laravel monolith with background workers and external AI integration. Those workers do not make it a microservice architecture.

### How do you stop one teacher editing another teacher's marks?
Assignment-based authorization, scoped queries and submission ownership checks limit access. Score validation and workflow locks still apply after authorization.

### What if someone types another student's result ID?
The policies and queries check the logged-in user and publication state. A predictable ID is not relied on as authorization.

### What is the difference between zero and missing?
Zero is a valid score. Missing means incomplete information and blocks final submission/calculation. Absence is a separate explicit state.

### How do you calculate a result?
Sum each score-to-maximum ratio multiplied by component weight, round using the approved scheme and map the percentage to boundaries. Credits and grade points produce quality points and GPA.

### Is the timetable algorithm optimal?
No. The baseline algorithm greedily chooses the first feasible slot from a fixed set of daily times. Global optimization and quality comparison are future work.

### What happens if generation fails halfway?
The baseline generation runs in a transaction. A failure to find a feasible slot raises a validation error and rolls back that generation operation.

### Can different papers share a room?
Yes, when they start together and combined capacity is sufficient. Seating validation prevents sharing the same physical seat.

### What prevents publication of an incomplete exam?
Publication checks include schedules, grading, eligible seats and primary duty coverage. Invalid prerequisites produce validation errors.

### Why have draft, approved and published states?
They separate preparation, review and release. A student should see official released information rather than work in progress.

### Can submitted marks change?
An administrator can return marks before approval/publication. Published marks need a correction request approved by a different administrator.

### Does approving a recheck change the mark?
No. Recheck approval and a mark correction are separate actions. The correction workflow governs an actual change.

### How does QR verification work?
The QR encodes a signed route with the admit record's UUID. The server validates the URL and current record conditions, then returns limited verification data.

### Does the QR prove the person is the student?
It verifies the linked admit record. Staff still need to compare the person's identity with the card.

### Where exactly is AI used?
Optional services propose schedules, seating and other supported assistance. A scoped assistant explains authorized records. Academic calculations remain deterministic PHP operations.

### What if AI produces an invalid answer?
Feature validators reject invalid proposals and can provide errors for another attempt. Supported changes still need the approval path. Core workflows do not depend on provider availability.

### Is forecasting a trained machine-learning model?
ForecastService implements a statistical baseline and least-squares trend. It is not a trained neural network or a validated predictive model.

### Does a statistical outlier indicate misconduct?
No. It indicates an unusual value under a simple rule and needs human review. It must not be used alone for a misconduct judgment.

### How are concurrent updates handled?
Important operations use transactions and row locks, alongside schema uniqueness. A concurrency test script exists, but passing behavior should be confirmed in the target environment.

### Are audit logs tamper-proof?
No such property is established. They record application actions in the database and support review, but privileged database access remains a separate concern.

### Are backups encrypted?
The implemented archive uses signatures and checksums for integrity. Do not claim encryption of the ZIP archive.

### What are your main limitations?
No global scheduling optimality guarantee, limited forecast history, dependency on correct academic setup, operational requirements for workers/email, and no measured performance or user-study results in this review.

### How would you evaluate success?
Measure task completion and usability with examination staff, compare timetable feasibility and quality, record validation outcomes, run role-isolation tests and load benchmarks, and rehearse a restore. Report actual numbers only after collecting them.

### What would you improve next?
Use measured findings to prioritize schedule optimization, institutional authentication/integrations, operational monitoring and stronger independent audit retention.

## 19. Statements to avoid in the defense

Do not claim guaranteed cheating detection, perfect security, immutable audit logs, globally optimal scheduling, encrypted backup archives, accurate predictions without evaluation, AI-generated official grades, or tests passing without execution evidence. Do not claim you used a particular development methodology, interviewed users or personally authored every module unless that is true and you can substantiate it.

## 20. Primary implementation references

Use the local source as the authority for this system. Important references are `README.md`, dependency manifests, `routes/web.php`, `database/migrations`, `app/Services/SchedulingService.php`, `SeatingService.php`, `DutyService.php`, `MarksService.php`, `GradingService.php`, `ResultsService.php`, `AdmitVerificationService.php`, `AnalysisService.php`, `ForecastService.php`, `BackupService.php`, `ScopedQueries.php`, `app/Services/AI`, `app/Policies` and `tests`.

The separate `Schema-and-Code-Inventory.md` enumerates tables and schema operations from every migration, all models and services, registered screen names and test files. `Speaker-Notes.md` provides slide-by-slide narration and implementation evidence.
