# Screen audit — runtime template mapping

Every catalog screen is listed below. The current catalog contains **199 unique role/slug pairs**, including three auth aliases that redirect to shared sign-in: **155 admin content screens, 24 teacher screens, 17 student screens**. The duplicate admin `missing-marks-report` entry was removed, preserving its first occurrence.

Authenticated rendering: `screens/show.blade.php` → `layouts/app.blade.php` → `components/app-layout.blade.php` → the effective backend template below. Existing controller and ScreenBindings logic are unchanged. All backend HTML modules now use shared components and the Vite design system. Catalog kind describes the intended screen pattern; many names still share one live backend module.

## Public screens

| URL | Effective Blade | Pattern |
|---|---|---|
| / | landing.blade.php | Marketing |
| /sign-in | backend/auth.blade.php | Login form |
| /forgot-password | backend/auth.blade.php | Recovery form |
| /reset-password/{token} | backend/auth.blade.php | Reset form |

## Pattern families

| Pattern | Shared implementation |
|---|---|
| List/table | data-table, filters, pagination, card; backend academic/courses/roster/results |
| Form/detail | form-field/input/select/date-input/toggle/button; existing request fields retained |
| Wizard/progress | Three-step enhancement of original generation/import forms; stepper/wizard/job-progress |
| Calendar/grid | calendar component; authorized schedule, room-unavailability and duty records |
| Dashboard | backend/dashboard; stat-card, chart-card, timeline, empty-state |
| Report | report-toolbar, existing export endpoints, shared PDF styles |
| State | empty-state/error-state/skeleton; errors/403/404/419/429/500/503 |
| Drawer/modal | drawer/modal/confirm-dialog; record, event and confirmation surfaces |
| Printable | printable-layout, A4 print CSS, backend/admit-pdf and report-pdf |

## Admin

| Screen | URL | Effective Blade | Catalog pattern |
|---|---|---|---|
| Admin Dashboard | /admin/admin-dashboard | backend/dashboard.blade.php | dashboard |
| Administrative activity | /admin/administrative-activity | backend/audit.blade.php | history |
| Student directory | /admin/student-directory | backend/academic.blade.php | table |
| Teacher directory | /admin/teacher-directory | backend/academic.blade.php | table |
| Student academic profile | /admin/student-academic-profile | backend/academic.blade.php | result |
| Teacher profile & availability | /admin/teacher-profile-availability | backend/duties.blade.php | calendar |
| Create student | /admin/create-student | backend/academic.blade.php | form |
| Edit user | /admin/edit-user | backend/academic.blade.php | form |
| Account activation | /admin/account-activation | backend/academic.blade.php | modal |
| Bulk user import | /admin/bulk-user-import | backend/import.blade.php | import |
| Import validation & errors | /admin/import-validation-errors | backend/import.blade.php | import |
| Departments | /admin/departments | backend/academic.blade.php | table |
| Academic sessions | /admin/academic-sessions | backend/academic.blade.php | table |
| Semesters | /admin/semesters | backend/academic.blade.php | table |
| Sections | /admin/sections | backend/academic.blade.php | table |
| Courses | /admin/courses | backend/academic.blade.php | table |
| Course details | /admin/course-details | backend/academic.blade.php | table |
| Student enrollment | /admin/student-enrollment | backend/academic.blade.php | form |
| Teacher-course assignment | /admin/teacher-course-assignment | backend/academic.blade.php | form |
| Credit-hour configuration | /admin/credit-hour-configuration | backend/academic.blade.php | grading |
| Examination list | /admin/examination-list | backend/academic.blade.php | table |
| Create examination | /admin/create-examination | backend/academic.blade.php | form |
| Edit examination | /admin/edit-examination | backend/academic.blade.php | form |
| Examination details | /admin/examination-details | backend/academic.blade.php | table |
| Exam type configuration | /admin/exam-type-configuration | backend/academic.blade.php | form |
| Assessment components | /admin/assessment-components | backend/academic.blade.php | grading |
| Examination detail drawer | /admin/examination-detail-drawer | backend/academic.blade.php | table |
| Date-sheet list | /admin/date-sheet-list | backend/schedule.blade.php | table |
| Create date sheet | /admin/create-date-sheet | backend/schedule.blade.php | form |
| Date-sheet generation wizard | /admin/date-sheet-generation-wizard | backend/schedule.blade.php | wizard |
| Proposed schedule calendar | /admin/proposed-schedule-calendar | backend/schedule.blade.php | calendar |
| Timetable grid | /admin/timetable-grid | backend/schedule.blade.php | calendar |
| Conflict detection results | /admin/conflict-detection-results | backend/analytics.blade.php | table |
| Schedule editing | /admin/schedule-editing | backend/schedule.blade.php | form |
| Conflict resolution drawer | /admin/conflict-resolution-drawer | backend/schedule.blade.php | form |
| Date-sheet approval | /admin/date-sheet-approval | backend/schedule.blade.php | modal |
| Published date-sheet preview | /admin/published-date-sheet-preview | backend/schedule.blade.php | calendar |
| Date-sheet version history | /admin/date-sheet-version-history | backend/schedule.blade.php | history |
| Eligible admit-card students | /admin/eligible-admit-card-students | backend/admit.blade.php | table |
| Admit-card generation | /admin/admit-card-generation | backend/admit.blade.php | wizard |
| Bulk generation progress | /admin/bulk-generation-progress | backend/admit.blade.php | state |
| Admit-card preview | /admin/admit-card-preview | backend/admit.blade.php | pdf |
| Printable admit card | /admin/printable-admit-card | backend/admit.blade.php | pdf |
| Admit-card generation error | /admin/admit-card-generation-error | backend/admit.blade.php | state |
| Room directory | /admin/room-directory | backend/academic.blade.php | table |
| Create or edit room | /admin/create-or-edit-room | backend/academic.blade.php | form |
| Room details | /admin/room-details | backend/academic.blade.php | seats |
| Room availability calendar | /admin/room-availability-calendar | backend/duties.blade.php | calendar |
| Room utilization | /admin/room-utilization | backend/academic.blade.php | analytics |
| Unavailable room | /admin/unavailable-room | backend/academic.blade.php | state |
| Seating plan overview | /admin/seating-plan-overview | backend/seats.blade.php | table |
| Generate seating plan wizard | /admin/generate-seating-plan-wizard | backend/seats.blade.php | wizard |
| Room capacity validation | /admin/room-capacity-validation | backend/seats.blade.php | state |
| Room-wise seating chart | /admin/room-wise-seating-chart | backend/seats.blade.php | seats |
| Subject-wise seating chart | /admin/subject-wise-seating-chart | backend/seats.blade.php | seats |
| Seat allocation table | /admin/seat-allocation-table | backend/seats.blade.php | table |
| Student seat lookup | /admin/student-seat-lookup | backend/seats.blade.php | seats |
| Printable seating layout | /admin/printable-seating-layout | backend/seats.blade.php | seats |
| Seating conflict report | /admin/seating-conflict-report | backend/seats.blade.php | state |
| Regeneration preview | /admin/regeneration-preview | backend/seats.blade.php | seats |
| Duty allocation overview | /admin/duty-allocation-overview | backend/duties.blade.php | table |
| Duty calendar | /admin/duty-calendar | backend/duties.blade.php | calendar |
| Teacher availability management | /admin/teacher-availability-management | backend/duties.blade.php | calendar |
| Teacher-wise duties | /admin/teacher-wise-duties | backend/duties.blade.php | table |
| Room-wise duties | /admin/room-wise-duties | backend/duties.blade.php | table |
| Duty assignment | /admin/duty-assignment | backend/duties.blade.php | form |
| Duty conflict detection | /admin/duty-conflict-detection | backend/duties.blade.php | state |
| Reserve-teacher pool | /admin/reserve-teacher-pool | backend/duties.blade.php | table |
| Teacher unavailable — affected duties | /admin/teacher-unavailable-affected-duties | backend/duties.blade.php | table |
| Eligible replacements | /admin/eligible-replacements | backend/duties.blade.php | table |
| Replacement comparison | /admin/replacement-comparison | backend/duties.blade.php | table |
| Revised duty preview | /admin/revised-duty-preview | backend/duties.blade.php | calendar |
| Duty replacement approval | /admin/duty-replacement-approval | backend/duties.blade.php | modal |
| Updated duty list | /admin/updated-duty-list | backend/duties.blade.php | table |
| Printable duty list | /admin/printable-duty-list | backend/duties.blade.php | table |
| Duty change history | /admin/duty-change-history | backend/duties.blade.php | history |
| Marks submission overview | /admin/marks-submission-overview | backend/marks.blade.php | table |
| Course-wise submissions | /admin/course-wise-submissions | backend/marks.blade.php | table |
| Teacher-wise submission status | /admin/teacher-wise-submission-status | backend/marks.blade.php | table |
| Missing marks report | /admin/missing-marks-report | backend/marks.blade.php | table |
| Incomplete result report | /admin/incomplete-result-report | backend/analytics.blade.php | table |
| Submission deadline view | /admin/submission-deadline-view | backend/marks.blade.php | calendar |
| Returned submissions | /admin/returned-submissions | backend/marks.blade.php | table |
| Submission detail | /admin/submission-detail | backend/marks.blade.php | table |
| Result batch overview | /admin/result-batch-overview | backend/results.blade.php | table |
| Individual result details | /admin/individual-result-details | backend/results.blade.php | result |
| Result editing form | /admin/result-editing-form | backend/corrections.blade.php | form |
| Result correction request | /admin/result-correction-request | backend/corrections.blade.php | form |
| Previous-versus-new comparison | /admin/previous-versus-new-comparison | backend/corrections.blade.php | history |
| Result approval queue | /admin/result-approval-queue | backend/results.blade.php | table |
| Published-result correction | /admin/published-result-correction | backend/corrections.blade.php | modal |
| Grading scheme list | /admin/grading-scheme-list | backend/academic.blade.php | table |
| Create grading scheme | /admin/create-grading-scheme | backend/academic.blade.php | grading |
| Grade boundary editor | /admin/grade-boundary-editor | backend/academic.blade.php | grading |
| Grade-point mapping | /admin/grade-point-mapping | backend/academic.blade.php | grading |
| GPA and CGPA rules | /admin/gpa-and-cgpa-rules | backend/results.blade.php | grading |
| Assessment weighting editor | /admin/assessment-weighting-editor | backend/academic.blade.php | grading |
| Grading rule validation | /admin/grading-rule-validation | backend/academic.blade.php | state |
| Approved grading scheme preview | /admin/approved-grading-scheme-preview | backend/academic.blade.php | grading |
| Result processing overview | /admin/result-processing-overview | backend/results.blade.php | wizard |
| Course result table | /admin/course-result-table | backend/results.blade.php | table |
| Student-wise marks | /admin/student-wise-marks | backend/marks.blade.php | table |
| Weighted score calculation | /admin/weighted-score-calculation | backend/results.blade.php | grading |
| GPA and CGPA preview | /admin/gpa-and-cgpa-preview | backend/results.blade.php | result |
| Missing-result flags | /admin/missing-result-flags | backend/analytics.blade.php | state |
| AI anomaly review | /admin/ai-anomaly-review | backend/analytics.blade.php | analytics |
| Result batch approval | /admin/result-batch-approval | backend/results.blade.php | modal |
| Publication confirmation | /admin/publication-confirmation | backend/results.blade.php | modal |
| Published results overview | /admin/published-results-overview | backend/results.blade.php | result |
| Rechecking request queue | /admin/rechecking-request-queue | backend/rechecks.blade.php | table |
| Rechecking request details | /admin/rechecking-request-details | backend/rechecks.blade.php | table |
| Rechecking decision form | /admin/rechecking-decision-form | backend/rechecks.blade.php | form |
| Approved rechecking correction | /admin/approved-rechecking-correction | backend/rechecks.blade.php | history |
| Rechecking request history | /admin/rechecking-request-history | backend/rechecks.blade.php | history |
| Analytics overview | /admin/analytics-overview | backend/analytics.blade.php | analytics |
| Class-wise analysis | /admin/class-wise-analysis | backend/analytics.blade.php | analytics |
| Individual student analysis | /admin/individual-student-analysis | backend/analytics.blade.php | analytics |
| AI findings queue | /admin/ai-findings-queue | backend/analytics.blade.php | analytics |
| Finding review details | /admin/finding-review-details | backend/analytics.blade.php | form |
| Prediction range & uncertainty | /admin/prediction-range-uncertainty | backend/analytics.blade.php | analytics |
| Prediction unavailable | /admin/prediction-unavailable | backend/analytics.blade.php | state |
| Admin AI Assistant | /admin/admin-ai-assistant | backend/chat.blade.php | chat |
| Assistant — no matching records | /admin/assistant-no-matching-records | backend/chat.blade.php | state |
| Assistant — permission denied | /admin/assistant-permission-denied | backend/chat.blade.php | state |
| Assistant — data unavailable | /admin/assistant-data-unavailable | backend/chat.blade.php | state |
| Reports overview | /admin/reports-overview | backend/results.blade.php | table |
| Class-wise result report | /admin/class-wise-result-report | backend/results.blade.php | table |
| Course-wise performance report | /admin/course-wise-performance-report | backend/results.blade.php | analytics |
| Pass/fail report | /admin/pass-fail-report | backend/results.blade.php | analytics |
| Grade distribution report | /admin/grade-distribution-report | backend/results.blade.php | analytics |
| Individual student report | /admin/individual-student-report | backend/results.blade.php | result |
| Teacher submission report | /admin/teacher-submission-report | backend/marks.blade.php | table |
| Seating plan report | /admin/seating-plan-report | backend/seats.blade.php | seats |
| Invigilation duty report | /admin/invigilation-duty-report | backend/duties.blade.php | table |
| Examination schedule report | /admin/examination-schedule-report | backend/schedule.blade.php | calendar |
| Notification center | /admin/notification-center | backend/notifications.blade.php | table |
| Notification settings | /admin/notification-settings | backend/notifications.blade.php | form |
| Audit logs | /admin/audit-logs | backend/audit.blade.php | history |
| User activity details | /admin/user-activity-details | backend/audit.blade.php | history |
| Result change history | /admin/result-change-history | backend/audit.blade.php | history |
| Grading configuration history | /admin/grading-configuration-history | backend/audit.blade.php | history |
| Academic settings | /admin/academic-settings | backend/academic.blade.php | form |
| Account and permissions | /admin/account-and-permissions | backend/academic.blade.php | form |
| Backup status | /admin/backup-status | backend/backups.blade.php | state |
| Sign in | /admin/sign-in | Redirect → backend/auth.blade.php | auth |
| Forgot password | /admin/forgot-password | Redirect → backend/auth.blade.php | auth |
| Reset password | /admin/reset-password | Redirect → backend/auth.blade.php | auth |
| Password reset confirmation | /admin/password-reset-confirmation | backend/state.blade.php | state |
| Unauthorized access | /admin/unauthorized-access | backend/state.blade.php | state |
| Session expired | /admin/session-expired | backend/state.blade.php | state |
| Loading records | /admin/loading-records | backend/state.blade.php | state |
| Empty records | /admin/empty-records | backend/state.blade.php | state |
| Server error | /admin/server-error | backend/state.blade.php | state |
| Permission denied | /admin/permission-denied | backend/state.blade.php | state |
| Success confirmation | /admin/success-confirmation | backend/state.blade.php | state |
| Marks validation error | /admin/marks-validation-error | backend/marks.blade.php | marks |
| No eligible replacement | /admin/no-eligible-replacement | backend/duties.blade.php | state |
| Missing assessment weights | /admin/missing-assessment-weights | backend/academic.blade.php | grading |

## Teacher

| Screen | URL | Effective Blade | Catalog pattern |
|---|---|---|---|
| Teacher Dashboard | /teacher/teacher-dashboard | backend/dashboard.blade.php | dashboard |
| My Courses | /teacher/my-courses | backend/courses.blade.php | table |
| My Students | /teacher/my-students | backend/roster.blade.php | table |
| Course details | /teacher/course-details | backend/courses.blade.php | table |
| Student roster | /teacher/student-roster | backend/roster.blade.php | table |
| Course assessments | /teacher/course-assessments | backend/courses.blade.php | grading |
| Course examination schedule | /teacher/course-examination-schedule | backend/schedule.blade.php | calendar |
| Course performance | /teacher/course-performance | backend/courses.blade.php | analytics |
| Marks Entry | /teacher/marks-entry | backend/marks.blade.php | marks |
| Marks CSV import | /teacher/marks-csv-import | backend/import.blade.php | import |
| Marks import errors | /teacher/marks-import-errors | backend/import.blade.php | import |
| Marks Submissions | /teacher/marks-submissions | backend/marks.blade.php | table |
| Returned marks correction | /teacher/returned-marks-correction | backend/corrections.blade.php | marks |
| Marks submission confirmation | /teacher/marks-submission-confirmation | backend/marks.blade.php | modal |
| Examination Schedule | /teacher/examination-schedule | backend/schedule.blade.php | calendar |
| Seating Plans | /teacher/seating-plans | backend/seats.blade.php | seats |
| Invigilation Duties | /teacher/invigilation-duties | backend/duties.blade.php | calendar |
| Duty instructions | /teacher/duty-instructions | backend/duties.blade.php | table |
| Profile and Availability | /teacher/profile-and-availability | backend/duties.blade.php | form |
| Unavailability request | /teacher/unavailability-request | backend/duties.blade.php | form |
| Replacement status | /teacher/replacement-status | backend/duties.blade.php | state |
| Performance Analytics | /teacher/performance-analytics | backend/analytics.blade.php | analytics |
| Teacher Notifications | /teacher/teacher-notifications | backend/notifications.blade.php | notifications |
| Teacher AI Assistant | /teacher/teacher-ai-assistant | backend/chat.blade.php | chat |

## Student

| Screen | URL | Effective Blade | Catalog pattern |
|---|---|---|---|
| Student Dashboard | /student/student-dashboard | backend/dashboard.blade.php | dashboard |
| My Profile | /student/my-profile | backend/profile.blade.php | profile |
| My Courses | /student/my-courses | backend/courses.blade.php | table |
| Examination Schedule | /student/examination-schedule | backend/schedule.blade.php | calendar |
| Updated examination schedule | /student/updated-examination-schedule | backend/schedule.blade.php | calendar |
| Admit Card | /student/admit-card | backend/admit.blade.php | admit |
| Seating Plan | /student/seating-plan | backend/seats.blade.php | lookup |
| My Results | /student/my-results | backend/results.blade.php | result |
| Semester results | /student/semester-results | backend/results.blade.php | result |
| Performance Analysis | /student/performance-analysis | backend/analytics.blade.php | analytics |
| Rechecking Requests | /student/rechecking-requests | backend/rechecks.blade.php | table |
| Submit rechecking request | /student/submit-rechecking-request | backend/rechecks.blade.php | form |
| Rechecking confirmation | /student/rechecking-confirmation | backend/rechecks.blade.php | state |
| Rechecking decision | /student/rechecking-decision | backend/rechecks.blade.php | history |
| Student Notifications | /student/student-notifications | backend/notifications.blade.php | notifications |
| Student AI Assistant | /student/student-ai-assistant | backend/chat.blade.php | chat |
| Unpublished result | /student/unpublished-result | backend/results.blade.php | state |

## Extra controller route names

These reuse the new shell and shared backend components. Their existing controller restrictions are preserved.

| Slug | Effective template | Existing access |
|---|---|---|
| records | backend/academic.blade.php | Admin; other roles receive 403 |
| duty-replacements | backend/duties.blade.php | Admin/teacher; student receives 403 |
| exam-schedule | backend/schedule.blade.php | All roles, existing data scope |
| my-duties | backend/duties.blade.php | Admin/teacher; student receives 403 |
| recheck-requests | backend/rechecks.blade.php | All roles, existing data scope |
| forecast | backend/analytics.blade.php | All roles, existing data scope |

## New presentation route

`/styleguide` → `styleguide.blade.php`, protected by existing `auth`, `active`, and `role:admin` middleware. This is the only new route. Every previous route remains unchanged.

## Scope boundaries

Nominal detail/drawer/wizard/state screens still inherit the live data and controls selected by existing ScreenBindings. No new backend state, API or approval permission was invented. The academic context switcher now persists and filters admin academic views; see docs/BACKEND_FEATURES.md. Optional marks autosave reuses the existing JSON-capable draft-save action, with server validation and lock rules intact. Submission deadlines and signed public QR verification are implemented. Persistent conflict totals remain a separate limitation. See DESIGN_SYSTEM.md for the presentation contracts and inherited limits.
