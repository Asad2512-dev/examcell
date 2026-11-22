# ExamCell defense speaker notes

Suggested format: approximately 15–20 minutes for the main explanation and a short demonstration, with technical slides available for questions. This timing is an assumption. Personal presenter and institution details have not been supplied.

Open `ExamCell-Defense-Presentation.html` in a browser. Use Left/Right to navigate, N to show notes, F for full screen and Print to save all slides as PDF. Hide notes before projecting if you do not want the audience to see them.

## 1. ExamCell

Opening: “ExamCell is a web application that helps an examination cell manage the examination lifecycle. Administrators organize exams and approve publication, teachers submit assigned marks, and students access their own published records.” Introduce yourself, institution, supervisor and team verbally. Suggested pacing: a selected main route in about 10–11 minutes, a 3–4 minute demo and 3–5 minutes for questions. See the companion guide for the recommended slide selection. Slides 21 onward are technical backup.

Implementation evidence: `README.md; routes/web.php`

## 2. Problem and project objectives

Explain the problem as the project motivation, not a measured study of a particular university. The design aims to centralize records, prevent invalid transitions and improve access to published information. No measured time savings or error reduction have been established in this review.

Implementation evidence: `README.md; app/Services/SchedulingService.php; app/Services/ResultsService.php`

## 3. System scope

This is an examination administration system. It does not implement an online exam-taking engine or automatic answer evaluation. Mention the connected workflow rather than listing every screen.

Implementation evidence: `routes/web.php; app/Support/ScreenCatalog.php`

## 4. Users and permissions

Role middleware controls entry to role routes. Policies and scoped database queries also check ownership or teaching assignments. An active signed-in account is required for protected workflows. Public admit verification is a narrowly exposed exception.

Implementation evidence: `routes/web.php; app/Policies; app/Services/ScopedQueries.php`

## 5. Administrator workspace

This is a repository-provided product screenshot, not a live deployment capture. The shown counts and dates are demonstration data, not project evaluation results. Explain the navigation and how academic records feed examination operations.

Implementation evidence: `public/landing/admin-workspace.png`

## 6. Technology and architecture

Architecture: a server-rendered Laravel monolith, with MVC organization and a service layer for business rules. Browser requests pass through routes and middleware to controllers. Controllers authorize and validate inputs, services run domain operations, and models persist records. DomPDF creates PDFs. Endroid creates QR codes. Vite builds frontend assets. Package manifests declare versions, but this review did not validate the installed runtime.

Implementation evidence: `composer.json; package.json; bootstrap/app.php; app/Services`

## 7. Academic setup

Setup order matters. Create the academic structure and users before trying to schedule exams or submit marks. An examination course links an examination to a course and section. Components define the scoring structure for that paper. Explain why course and exam_course are separate: one course can appear in many examination instances.

Implementation evidence: `database/migrations; app/Services/AcademicService.php; app/Http/Controllers/AcademicSetupController.php`

## 8. Examination lifecycle

The implemented exam transitions are draft to approved to published. Publishing a revision archives the previous published examination. Publication checks include an approved grading scheme, component weights totaling 100%, scheduled courses, eligible seating and primary invigilation coverage. Existing published plans are protected from direct seating edits.

Implementation evidence: `app/Services/SchedulingService.php; app/Services/SeatingService.php`

## 9. Timetable generation and conflicts

The baseline generator is greedy. It visits exam courses by ID, scans days at 09:00, 13:00 and 17:00, and considers active rooms by descending capacity. It accepts the first feasible allocation. It is not a proof of globally optimal scheduling. Shared rooms are permitted for papers starting together, subject to combined capacity and separate seats. AI date-sheet validation adds checks such as teacher clashes and interpreted scheduling constraints.

Implementation evidence: `app/Services/SchedulingService.php; app/Services/AI/DateSheetService.php`

## 10. Seating and room layouts

The baseline allocator orders eligible students by ID and fills available numbered seats in allocated rooms. Overrides must preserve full student coverage and avoid duplicate seats. Optional AI seating has a separate proposal validator. Do not claim the baseline allocator guarantees anti-cheating separation or globally optimal seating.

Implementation evidence: `app/Services/SeatingService.php; app/Services/SeatMapService.php; app/Services/AI/SeatingAiService.php`

## 11. Invigilation and replacement

Candidate selection uses active reserve teachers from the relevant department and filters unavailable or committed staff. The default daily duty limit is two unless teacher settings change it. A request does not release the existing duty. Approval rechecks eligibility and records the replacement. AI may assist candidate selection when configured, while the eligibility checks remain in server code.

Implementation evidence: `app/Services/DutyService.php; config/examcell.php`

## 12. Admit cards and QR verification

A UUID identifies the admit record, while Laravel signs the URL to detect URL tampering. Verification requires an active student, eligible profile, published exam, enabled admit issuance and scheduled eligible courses. The page shows name, roll number, exam name and validity, without exposing the full profile. A QR code is not biometric identity proof. Staff must still compare the card to the person.

Implementation evidence: `app/Services/ReportService.php; app/Services/AdmitVerificationService.php; app/Http/Controllers/AdmitVerificationController.php; app/Jobs/GenerateAdmitCards.php`

## 13. Teacher marks workflow

Distinguish three cases: zero is an actual score, absent is an explicit flag with blank score, and missing is incomplete information. Missing marks block submission and calculation. Approved or published result batches lock direct marks editing. Submission deadlines are configurable and displayed, but do not describe them as a strict late-write ban without checking the relevant enforcement.

Implementation evidence: `app/Services/MarksService.php; app/Services/ImportService.php; app/Http/Controllers/SubmissionDeadlineController.php`

## 14. Grading calculation

The example is illustrative, not a student record. Component weights must sum to 100. BCMath decimal operations reduce floating-point inconsistencies. The scheme controls rounding places. Passing compares the percentage with the configured passing-to-maximum ratio. An absent component contributes zero and sets the absence flag. Do not claim absence always forces failure, because the calculation uses the final weighted percentage. Academic GPA uses final-exam results, with configurable latest-attempt handling.

Implementation evidence: `app/Services/GradingService.php; app/Services/ResultsService.php; config/examcell.php`

## 15. Result review and publication

ResultsService restricts state transitions and checks completeness and expected eligible result counts. Approval recalculates the results so returned and resubmitted marks do not leave stale calculated values. Publication requires the examination schedule to be published. Students only see their own published results. The approval stage is an administrative control, not an external accreditation decision.

Implementation evidence: `app/Services/ResultsService.php; app/Services/ScopedQueries.php`

## 16. Corrections and student rechecks

Published corrections use a separate record and require an approver different from the requester. The service rejects approval if the underlying mark changed after the proposal. On approval, it recalculates the affected student result and sends a notification. Two active administrator accounts are needed to demonstrate this workflow end to end.

Implementation evidence: `app/Services/ResultsService.php`

## 17. AI assistance and statistical analysis

Do not describe all analysis as generative AI. AnalysisService performs deterministic completeness rules and flags deviation greater than two population standard deviations when the sample meets its checks. Flags request human review and do not establish misconduct. ForecastService uses up to eight published final attempts, a last-observation baseline for fewer than three, and least-squares regression from three onward. It does not change official grades. Provider-backed features require configuration and network access.

Implementation evidence: `app/Services/AI; app/Services/AssistantService.php; app/Services/AnalysisService.php; app/Services/ForecastService.php`

## 18. Security and record integrity

Passwords use Laravel’s hashed model cast. Laravel web middleware supplies form/session protection. Login and selected endpoints are throttled. Signed URLs protect public admit links. These are implemented controls, not evidence that the system has passed a penetration test. The audit log is stored in the application database and should not be called immutable or tamper-proof. Backups have integrity checks but the ZIP is not shown to be encrypted.

Implementation evidence: `bootstrap/app.php; routes/web.php; app/Models/User.php; app/Policies; app/Services/AuditService.php`

## 19. Testing and current evidence

State this clearly: vendor and node_modules are absent from this checkout. No application test run, load benchmark, user study or production reliability result is claimed. Review tests/Feature for workflow, role, import, AI, security, deadlines and admit verification cases. PHP tests require a disposable MySQL database ending in \_test and can reset its tables.

Implementation evidence: `tests; phpunit.xml; README.md; composer.json; package.json`

## 20. Contribution and next steps

Closing: “The main contribution is integrating examination operations with controlled publication and traceable decisions. The code implements these controls. The next evaluation should measure usability and performance using representative institutional data.” Future ideas include stronger schedule optimization, institutional SSO and integration APIs. Present these as proposed work, not delivered features.

Implementation evidence: `README.md; app/Services; tests`

## 21. Live demonstration

Reserve about 4–6 minutes if time allows. Rehearse on a separate demo database. Prepare a future exam with eligible enrollments, approved grading, component weights totaling 100%, room capacity and assigned teachers. Keep additional data ready for invalid marks and schedule conflicts. Show the QR verification in a browser. If the live environment fails, use the included screenshots and explain the same workflow. Do not show .env, logs, email reset tokens or API keys.

Implementation evidence: `README.md; routes/web.php`

## 22. Database relationships

Key relationships: session has semesters, semester and department have sections, user has role profile, student has enrollments, examination has exam_courses, exam_course has components and one schedule, schedule has rooms and seating plan, seating plan has seats, component has student marks, examination has result batch, batch has calculated results. Foreign keys and compound unique constraints enforce relational integrity. Consult the generated schema appendix for the complete migration inventory.

Implementation evidence: `database/migrations; app/Models`

## 23. Request flow and code organization

Example: POST /workflow/marks/{course} passes through authentication and role middleware, calls WorkflowController::marks, authorizes the exam_course, and delegates to MarksService. MarksService checks assignment, batch locks, rows and completeness, then persists the submission and audit event. Resources/js and resources/css contain frontend sources. Public assets are browser-accessible. Private documents belong in storage.

Implementation evidence: `routes/web.php; app/Http/Controllers/WorkflowController.php; app/Services/MarksService.php`

## 24. Imports and reporting

Baseline CSV headers must exactly match the expected fields, and parsing caps input at 1000 rows. Student imports reject existing emails and roll numbers and use a transaction on commit. Imported students set passwords through recovery rather than receiving a shared default. AI imports are an optional assisted path and should not be confused with ordinary deterministic CSV/XLSX validation.

Implementation evidence: `app/Services/ImportService.php; app/Services/XlsxReader.php; app/Services/ReportService.php`

## 25. Queues, backups and deployment

README supports Windows installation with PHP 8.2+, Composer, MySQL and optional frontend builds. Queue and scheduler are separate long-running processes in local setup. Backup runs daily at 02:00 Asia/Karachi when the scheduler operates. HMAC and SHA-256 checks detect archive changes. Keep the original APP_KEY securely. Restore stages data for review without automatically switching the live database. Do not claim encrypted backups or validated recovery time.

Implementation evidence: `README.md; routes/console.php; app/Services/BackupService.php; deploy`

## 26. Limitations and evaluation gaps

Other practical constraints: the baseline import path has a 1000-row cap, workers must run for queued work, and SMTP is needed to deliver email. Local log mail writes email content to a file instead of sending it. Audit logs do not prevent a privileged database operator from changing records. Improvements should follow institutional requirements and measured evaluation.

Implementation evidence: `app/Services/SchedulingService.php; app/Services/ForecastService.php; app/Services/ImportService.php; README.md`

## 27. Likely defense questions

Answers: Laravel provides MVC, middleware, policies, ORM, queues and testing support. MySQL fits related academic data and transactional integrity. Marks use assignment checks, locks and controlled corrections. Scheduling rolls back if no feasible slot is found, and manual/rule-based workflows remain available without AI. Zero is a score, absence is explicit and missing blocks completeness. Effectiveness needs measured evaluation. Present existing tests as code coverage intentions until a passing run is recorded.

Implementation evidence: `composer.json; app/Services/MarksService.php; app/Services/SchedulingService.php; tests`

## 28. Defense evidence map

Use this slide to point reviewers to concrete implementation evidence. All statements in this deck describe the checked-out repository. Screenshot data is illustrative. Runtime behavior and operational readiness still need a dependency-complete environment. The companion guide includes slide notes, detailed Q&A, schema inventory and a demo checklist.

Implementation evidence: `README.md; composer.json; package.json; routes/web.php; database/migrations; app; tests`
