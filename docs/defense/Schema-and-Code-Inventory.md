# Schema and code inventory

Generated from repository source, not a live database. Migration order matters: later migrations add or change fields and constraints. This is an evidence index, not a complete executed SQL schema.

## 2026_10_11_000001_create_examcell_schema.php

### `users` (create)

```php
$t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('role', ['admin', 'teacher', 'student'])->index();
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
```

### `departments` (create)

```php
$t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
```

### `academic_sessions` (create)

```php
$t->id();
            $t->string('name')->unique();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('active')->default(true);
            $t->timestamps();
```

### `semesters` (create)

```php
$t->id();
            $t->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->unsignedTinyInteger('number');
            $t->unique(['academic_session_id', 'name']);
            $t->boolean('active')->default(true);
            $t->timestamps();
```

### `sections` (create)

```php
$t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->unique(['department_id', 'semester_id', 'name']);
            $t->timestamps();
```

### `courses` (create)

```php
$t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->decimal('credits', 5, 2);
            $t->boolean('active')->default(true);
            $t->timestamps();
```

### `student_profiles` (create)

```php
$t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->string('roll_number')->unique();
            $t->boolean('eligible')->default(true);
            $t->timestamps();
```

### `enrollments` (create)

```php
$t->id();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->boolean('eligible')->default(true);
            $t->unique(['student_id', 'course_id', 'section_id']);
            $t->timestamps();
```

### `teacher_assignments` (create)

```php
$t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->unique(['teacher_id', 'course_id', 'section_id']);
            $t->timestamps();
```

### `grading_schemes` (create)

```php
$t->id();
            $t->string('name')->unique();
            $t->enum('status', ['draft', 'approved', 'archived'])->default('draft');
            $t->unsignedTinyInteger('rounding_places')->default(2);
            $t->timestamps();
```

### `grade_boundaries` (create)

```php
$t->id();
            $t->foreignId('grading_scheme_id')->constrained()->restrictOnDelete();
            $t->string('grade', 8);
            $t->decimal('minimum', 5, 2);
            $t->decimal('grade_points', 4, 2);
            $t->unique(['grading_scheme_id', 'minimum']);
            $t->timestamps();
```

### `examinations` (create)

```php
$t->id();
            $t->string('name');
            $t->enum('type', ['midterm', 'final', 'quiz', 'assignment']);
            $t->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignId('grading_scheme_id')->constrained()->restrictOnDelete();
            $t->enum('status', ['draft', 'approved', 'published', 'archived'])->default('draft')->index();
            $t->boolean('admit_enabled')->default(false);
            $t->timestamps();
```

### `exam_courses` (create)

```php
$t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('duration_minutes');
            $t->decimal('maximum', 8, 2);
            $t->decimal('passing', 8, 2);
            $t->unique(['examination_id', 'course_id', 'section_id']);
            $t->timestamps();
```

### `assessment_components` (create)

```php
$t->id();
            $t->foreignId('exam_course_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->decimal('maximum', 8, 2);
            $t->decimal('weight', 5, 2);
            $t->unique(['exam_course_id', 'name']);
            $t->timestamps();
```

### `rooms` (create)

```php
$t->id();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('capacity');
            $t->boolean('active')->default(true);
            $t->timestamps();
```

### `room_unavailabilities` (create)

```php
$t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('reason');
            $t->index(['room_id', 'starts_at', 'ends_at']);
            $t->timestamps();
```

### `schedule_entries` (create)

```php
$t->id();
            $t->foreignId('exam_course_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->index(['room_id', 'starts_at', 'ends_at']);
            $t->timestamps();
```

### `seating_plans` (create)

```php
$t->id();
            $t->foreignId('schedule_entry_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
```

### `seat_assignments` (create)

```php
$t->id();
            $t->foreignId('seating_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->unsignedSmallInteger('seat_number');
            $t->unique(['seating_plan_id', 'student_id']);
            $t->unique(['seating_plan_id', 'seat_number']);
            $t->timestamps();
```

### `admit_cards` (create)

```php
$t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->uuid('reference')->unique();
            $t->string('path');
            $t->unique(['examination_id', 'student_id']);
            $t->timestamps();
```

### `teacher_availabilities` (create)

```php
$t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->boolean('available')->default(false);
            $t->string('reason');
            $t->index(['teacher_id', 'starts_at', 'ends_at']);
            $t->timestamps();
```

### `duty_assignments` (create)

```php
$t->id();
            $t->foreignId('schedule_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['assigned', 'unavailable', 'replaced'])->default('assigned');
            $t->boolean('reserve')->default(false);
            $t->unique(['schedule_entry_id', 'teacher_id']);
            $t->timestamps();
```

### `duty_replacements` (create)

```php
$t->id();
            $t->foreignId('duty_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('candidate_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->enum('status', ['proposed', 'approved', 'unresolved', 'rejected'])->default('proposed');
            $t->text('reason');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
```

### `mark_submissions` (create)

```php
$t->id();
            $t->foreignId('exam_course_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['draft', 'submitted', 'returned'])->default('draft');
            $t->text('return_reason')->nullable();
            $t->timestamps();
```

### `marks` (create)

```php
$t->id();
            $t->foreignId('assessment_component_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->decimal('score', 8, 2)->nullable();
            $t->boolean('absent')->default(false);
            $t->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $t->unique(['assessment_component_id', 'student_id']);
            $t->timestamps();
```

### `result_batches` (create)

```php
$t->id();
            $t->foreignId('examination_id')->unique()->constrained()->restrictOnDelete();
            $t->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'published', 'returned'])->default('draft');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
```

### `calculated_results` (create)

```php
$t->id();
            $t->foreignId('result_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('exam_course_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->decimal('percentage', 5, 2);
            $t->boolean('passed');
            $t->string('grade', 8);
            $t->decimal('grade_points', 4, 2);
            $t->decimal('credits', 5, 2);
            $t->decimal('quality_points', 8, 2);
            $t->boolean('absent')->default(false);
            $t->unique(['exam_course_id', 'student_id']);
            $t->timestamps();
```

### `result_corrections` (create)

```php
$t->id();
            $t->foreignId('mark_id')->constrained('marks')->restrictOnDelete();
            $t->decimal('old_score', 8, 2)->nullable();
            $t->decimal('new_score', 8, 2)->nullable();
            $t->boolean('old_absent');
            $t->boolean('new_absent');
            $t->text('reason');
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
```

### `recheck_requests` (create)

```php
$t->id();
            $t->foreignId('calculated_result_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $t->text('decision_reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unique(['calculated_result_id', 'student_id']);
            $t->timestamps();
```

### `ai_analysis_runs` (create)

```php
$t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('completed');
            $t->json('summary');
            $t->timestamps();
```

### `analysis_flags` (create)

```php
$t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('type');
            $t->string('record_key')->unique();
            $t->json('details');
            $t->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $t->text('review_reason')->nullable();
            $t->timestamps();
```

### `conversations` (create)

```php
$t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('title')->default('ExamCell assistant');
            $t->timestamps();
```

### `messages` (create)

```php
$t->id();
            $t->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $t->enum('role', ['user', 'assistant']);
            $t->text('content');
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->string('provider_status')->nullable();
            $t->timestamps();
```

### `audit_logs` (create)

```php
$t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action')->index();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('changes');
            $t->index(['entity_type', 'entity_id']);
            $t->timestamps();
```

### `password_reset_tokens` (create)

```php
$t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
```

### `notifications` (create)

```php
$t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
```

## 2026_10_11_000002_add_teacher_profiles_and_schedule_versions.php

### `teacher_profiles` (create)

```php
$t->id();
            $t->foreignId('teacher_id')->unique()->constrained('users')->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->boolean('reserve')->default(false);
            $t->unsignedTinyInteger('max_duties_per_day')->default(2);
            $t->timestamps();
```

### `examinations` (alter)

```php
$t->foreignId('supersedes_id')->nullable()->constrained('examinations')->restrictOnDelete();
```

## 2026_10_11_000003_create_bulk_generation_queue.php

### `jobs` (create)

```php
$t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
```

### `failed_jobs` (create)

```php
$t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
```

### `admit_generation_runs` (create)

```php
$t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('queued');
            $t->unsignedInteger('generated')->default(0);
            $t->text('failure')->nullable();
            $t->timestamps();
```

## 2026_10_12_000004_complete_operational_workflows.php

### `schedule_rooms` (create)

```php
$t->id();
            $t->foreignId('schedule_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->unique(['schedule_entry_id', 'room_id']);
            $t->timestamps();
```

### `seat_assignments` (alter)

```php
$t->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $t->dropUnique(['seating_plan_id', 'seat_number']);
            $t->unique(['seating_plan_id', 'room_id', 'seat_number'], 'seats_plan_room_number_unique');
```

### `duty_assignments` (alter)

```php
$t->id();
            $t->foreignId('duty_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $t->text('reason');
            $t->timestamp('requested_at');
            $t->timestamp('deadline_at');
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('decision_reason')->nullable();
            $t->timestamps();
            $t->index(['teacher_id', 'status']);
```

### `duty_replacements` (alter)

```php
$t->id();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->json('evidence');
            $t->timestamps();
```

### `backup_runs` (create)

```php
$t->id();
            $t->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status')->default('running');
            $t->string('path')->nullable();
            $t->string('checksum', 64)->nullable();
            $t->unsignedBigInteger('bytes')->default(0);
            $t->text('failure')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
```

## 2026_10_29_000001_add_submission_deadlines_and_admit_verification.php

### `exam_courses` (alter)

```php
$table->timestamp('marks_due_at')->nullable()->index();
```

### `marks_submission_deadlines` (create)

```php
$table->id();
            $table->foreignId('exam_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('marks_due_at');
            $table->unique(['exam_course_id', 'teacher_id']);
            $table->timestamps();
```

### `admit_cards` (alter)

```php
$table->unsignedTinyInteger('verification_version')->default(0);
```

## 2026_10_30_000001_create_ai_automation_tables.php

### `ai_settings` (create)

```php
$t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
```

### `ai_usages` (create)

```php
$t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature', 60)->index();
            $t->string('model', 60);
            $t->string('status', 20);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->decimal('cost_usd', 10, 6)->default(0);
            $t->timestamps();
            $t->index('created_at');
```

### `ai_runs` (create)

```php
$t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('feature', 60)->index();
            $t->string('status', 20)->default('queued');
            $t->unsignedTinyInteger('progress')->default(0);
            $t->string('message')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('input')->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
```

### `rooms` (alter)

```php
$t->unsignedTinyInteger('seat_columns')->nullable();
            $t->json('accessible_seats')->nullable();
```

## 2026_10_31_000001_create_app_settings.php

### `app_settings` (create)

```php
$t->string('key')->primary();
            $t->json('value')->nullable();
            $t->timestamps();
```

## 2026_11_01_000001_add_room_layouts.php

### `rooms` (alter)

```php
$t->unsignedTinyInteger('seat_rows')->nullable();
            $t->json('aisles')->nullable();
            $t->json('disabled_seats')->nullable();
            $t->string('door_side', 8)->default('right');
```

## Eloquent models

- `app/Models/AcademicSession.php`
- `app/Models/AdmitCard.php`
- `app/Models/AdmitGenerationRun.php`
- `app/Models/AiAnalysisRun.php`
- `app/Models/AiRun.php`
- `app/Models/AiSetting.php`
- `app/Models/AiUsage.php`
- `app/Models/AnalysisFlag.php`
- `app/Models/AppSetting.php`
- `app/Models/AssessmentComponent.php`
- `app/Models/AuditLog.php`
- `app/Models/BackupRun.php`
- `app/Models/CalculatedResult.php`
- `app/Models/Conversation.php`
- `app/Models/Course.php`
- `app/Models/Department.php`
- `app/Models/DutyAssignment.php`
- `app/Models/DutyReplacement.php`
- `app/Models/DutyRequest.php`
- `app/Models/Enrollment.php`
- `app/Models/ExamCourse.php`
- `app/Models/Examination.php`
- `app/Models/ForecastRun.php`
- `app/Models/GradeBoundary.php`
- `app/Models/GradingScheme.php`
- `app/Models/Mark.php`
- `app/Models/MarkSubmission.php`
- `app/Models/MarksSubmissionDeadline.php`
- `app/Models/Message.php`
- `app/Models/RecheckRequest.php`
- `app/Models/ResultBatch.php`
- `app/Models/ResultCorrection.php`
- `app/Models/Room.php`
- `app/Models/RoomUnavailability.php`
- `app/Models/ScheduleEntry.php`
- `app/Models/SeatAssignment.php`
- `app/Models/SeatingPlan.php`
- `app/Models/Section.php`
- `app/Models/Semester.php`
- `app/Models/StudentProfile.php`
- `app/Models/TeacherAssignment.php`
- `app/Models/TeacherAvailability.php`
- `app/Models/TeacherProfile.php`
- `app/Models/User.php`

## Services

- `app/Services/AI/AiClient.php`
- `app/Services/AI/AiException.php`
- `app/Services/AI/AiSettings.php`
- `app/Services/AI/CommandService.php`
- `app/Services/AI/DateSheetService.php`
- `app/Services/AI/DutyAiService.php`
- `app/Services/AI/ImportAiService.php`
- `app/Services/AI/NotificationDraftService.php`
- `app/Services/AI/ReportAiService.php`
- `app/Services/AI/SeatingAiService.php`
- `app/Services/AI/TriageService.php`
- `app/Services/AcademicContext.php`
- `app/Services/AcademicService.php`
- `app/Services/AdmitVerificationService.php`
- `app/Services/AnalysisService.php`
- `app/Services/AnalyticsService.php`
- `app/Services/AssistantService.php`
- `app/Services/AuditService.php`
- `app/Services/BackupService.php`
- `app/Services/DutyService.php`
- `app/Services/ForecastService.php`
- `app/Services/GradingService.php`
- `app/Services/ImportService.php`
- `app/Services/MarksService.php`
- `app/Services/PasswordRecovery.php`
- `app/Services/RecoveryMail.php`
- `app/Services/ReportService.php`
- `app/Services/ResultsService.php`
- `app/Services/SchedulingService.php`
- `app/Services/ScopedQueries.php`
- `app/Services/SeatMapService.php`
- `app/Services/SeatingService.php`
- `app/Services/Settings.php`
- `app/Services/XlsxReader.php`

## Controllers

- `app/Http/Controllers/AcademicContextController.php`
- `app/Http/Controllers/AcademicSetupController.php`
- `app/Http/Controllers/AdminSettingsController.php`
- `app/Http/Controllers/AdmitVerificationController.php`
- `app/Http/Controllers/AiController.php`
- `app/Http/Controllers/AppearanceController.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/DemoController.php`
- `app/Http/Controllers/RecoveryMailController.php`
- `app/Http/Controllers/ResourceController.php`
- `app/Http/Controllers/RoleAccountController.php`
- `app/Http/Controllers/RoomLayoutController.php`
- `app/Http/Controllers/ScreenController.php`
- `app/Http/Controllers/SubmissionDeadlineController.php`
- `app/Http/Controllers/WorkflowController.php`

## Policies

- `app/Policies/AdmitCardPolicy.php`
- `app/Policies/CalculatedResultPolicy.php`
- `app/Policies/ConversationPolicy.php`
- `app/Policies/ExamCoursePolicy.php`

## Queued jobs

- `app/Jobs/GenerateAdmitCards.php`
- `app/Jobs/RunAiTask.php`

## Test and browser scripts

- `tests/Feature/AcademicContextTest.php`
- `tests/Feature/AcademicSetupTest.php`
- `tests/Feature/AdmitVerificationTest.php`
- `tests/Feature/AiAutomationTest.php`
- `tests/Feature/AssistantTest.php`
- `tests/Feature/FinalFlowTest.php`
- `tests/Feature/FrontendTest.php`
- `tests/Feature/ImportTest.php`
- `tests/Feature/NormalSignInTest.php`
- `tests/Feature/PasswordRecoveryTest.php`
- `tests/Feature/PresentationTest.php`
- `tests/Feature/RoleAccountTest.php`
- `tests/Feature/RoleFlowTest.php`
- `tests/Feature/SeatingGraphicsTest.php`
- `tests/Feature/SecurityTest.php`
- `tests/Feature/SubmissionDeadlineTest.php`
- `tests/Feature/UiContentTest.php`
- `tests/Feature/WorkflowTest.php`
- `tests/Feature/WorkspaceNavigationTest.php`
- `tests/Fixture.php`
- `tests/TestCase.php`
- `tests/browser/admin-setup.cjs`
- `tests/browser/auth-reference.cjs`
- `tests/browser/backend-features-fixture.php`
- `tests/browser/backend-features.cjs`
- `tests/browser/backend.cjs`
- `tests/browser/date-sheet-easy.cjs`
- `tests/browser/fixture.php`
- `tests/browser/frontend.cjs`
- `tests/browser/landing-responsive.cjs`
- `tests/browser/landing-story.cjs`
- `tests/browser/panel-simplicity.cjs`
- `tests/browser/password-recovery.cjs`
- `tests/browser/redesign-fixture.php`
- `tests/browser/redesign.cjs`
- `tests/browser/role-accounts.cjs`
- `tests/browser/role-flows.cjs`
- `tests/browser/workspace.cjs`
- `tests/concurrency.php`
- `tests/frontend/admit-qr.test.mjs`
- `tests/frontend/generate-qr.php`
- `tests/frontend/submission-countdown.test.mjs`
- `tests/queue-smoke.php`

## Registered screen catalog

These are catalog entries, not independent proof that every screen is complete. Route aliases can expose additional paths.

| Role    | Screen                                | Route slug                            |
| ------- | ------------------------------------- | ------------------------------------- |
| admin   | Admin Dashboard                       | `admin-dashboard`                     |
| admin   | Administrative activity               | `administrative-activity`             |
| admin   | Student directory                     | `student-directory`                   |
| admin   | Teacher directory                     | `teacher-directory`                   |
| admin   | Student academic profile              | `student-academic-profile`            |
| admin   | Teacher profile & availability        | `teacher-profile-availability`        |
| admin   | Create student                        | `create-student`                      |
| admin   | Edit user                             | `edit-user`                           |
| admin   | Account activation                    | `account-activation`                  |
| admin   | Bulk user import                      | `bulk-user-import`                    |
| admin   | Import validation & errors            | `import-validation-errors`            |
| admin   | Departments                           | `departments`                         |
| admin   | Academic sessions                     | `academic-sessions`                   |
| admin   | Semesters                             | `semesters`                           |
| admin   | Sections                              | `sections`                            |
| admin   | Courses                               | `courses`                             |
| admin   | Course details                        | `course-details`                      |
| admin   | Student enrollment                    | `student-enrollment`                  |
| admin   | Teacher-course assignment             | `teacher-course-assignment`           |
| admin   | Credit-hour configuration             | `credit-hour-configuration`           |
| admin   | Examination list                      | `examination-list`                    |
| admin   | Create examination                    | `create-examination`                  |
| admin   | Edit examination                      | `edit-examination`                    |
| admin   | Examination details                   | `examination-details`                 |
| admin   | Exam type configuration               | `exam-type-configuration`             |
| admin   | Assessment components                 | `assessment-components`               |
| admin   | Examination detail drawer             | `examination-detail-drawer`           |
| admin   | Date-sheet list                       | `date-sheet-list`                     |
| admin   | Create date sheet                     | `create-date-sheet`                   |
| admin   | Date-sheet generation wizard          | `date-sheet-generation-wizard`        |
| admin   | Proposed schedule calendar            | `proposed-schedule-calendar`          |
| admin   | Timetable grid                        | `timetable-grid`                      |
| admin   | Conflict detection results            | `conflict-detection-results`          |
| admin   | Schedule editing                      | `schedule-editing`                    |
| admin   | Conflict resolution drawer            | `conflict-resolution-drawer`          |
| admin   | Date-sheet approval                   | `date-sheet-approval`                 |
| admin   | Published date-sheet preview          | `published-date-sheet-preview`        |
| admin   | Date-sheet version history            | `date-sheet-version-history`          |
| admin   | Eligible admit-card students          | `eligible-admit-card-students`        |
| admin   | Admit-card generation                 | `admit-card-generation`               |
| admin   | Bulk generation progress              | `bulk-generation-progress`            |
| admin   | Admit-card preview                    | `admit-card-preview`                  |
| admin   | Printable admit card                  | `printable-admit-card`                |
| admin   | Admit-card generation error           | `admit-card-generation-error`         |
| admin   | Room directory                        | `room-directory`                      |
| admin   | Create or edit room                   | `create-or-edit-room`                 |
| admin   | Room details                          | `room-details`                        |
| admin   | Room availability calendar            | `room-availability-calendar`          |
| admin   | Room utilization                      | `room-utilization`                    |
| admin   | Unavailable room                      | `unavailable-room`                    |
| admin   | Seating plan overview                 | `seating-plan-overview`               |
| admin   | Generate seating plan wizard          | `generate-seating-plan-wizard`        |
| admin   | Room capacity validation              | `room-capacity-validation`            |
| admin   | Room-wise seating chart               | `room-wise-seating-chart`             |
| admin   | Subject-wise seating chart            | `subject-wise-seating-chart`          |
| admin   | Seat allocation table                 | `seat-allocation-table`               |
| admin   | Student seat lookup                   | `student-seat-lookup`                 |
| admin   | Printable seating layout              | `printable-seating-layout`            |
| admin   | Seating conflict report               | `seating-conflict-report`             |
| admin   | Regeneration preview                  | `regeneration-preview`                |
| admin   | Duty allocation overview              | `duty-allocation-overview`            |
| admin   | Duty calendar                         | `duty-calendar`                       |
| admin   | Teacher availability management       | `teacher-availability-management`     |
| admin   | Teacher-wise duties                   | `teacher-wise-duties`                 |
| admin   | Room-wise duties                      | `room-wise-duties`                    |
| admin   | Duty assignment                       | `duty-assignment`                     |
| admin   | Duty conflict detection               | `duty-conflict-detection`             |
| admin   | Reserve-teacher pool                  | `reserve-teacher-pool`                |
| admin   | Teacher unavailable — affected duties | `teacher-unavailable-affected-duties` |
| admin   | Eligible replacements                 | `eligible-replacements`               |
| admin   | Replacement comparison                | `replacement-comparison`              |
| admin   | Revised duty preview                  | `revised-duty-preview`                |
| admin   | Duty replacement approval             | `duty-replacement-approval`           |
| admin   | Updated duty list                     | `updated-duty-list`                   |
| admin   | Printable duty list                   | `printable-duty-list`                 |
| admin   | Duty change history                   | `duty-change-history`                 |
| admin   | Marks submission overview             | `marks-submission-overview`           |
| admin   | Course-wise submissions               | `course-wise-submissions`             |
| admin   | Teacher-wise submission status        | `teacher-wise-submission-status`      |
| admin   | Missing marks report                  | `missing-marks-report`                |
| admin   | Incomplete result report              | `incomplete-result-report`            |
| admin   | Submission deadline view              | `submission-deadline-view`            |
| admin   | Returned submissions                  | `returned-submissions`                |
| admin   | Submission detail                     | `submission-detail`                   |
| admin   | Result batch overview                 | `result-batch-overview`               |
| admin   | Individual result details             | `individual-result-details`           |
| admin   | Result editing form                   | `result-editing-form`                 |
| admin   | Result correction request             | `result-correction-request`           |
| admin   | Previous-versus-new comparison        | `previous-versus-new-comparison`      |
| admin   | Result approval queue                 | `result-approval-queue`               |
| admin   | Published-result correction           | `published-result-correction`         |
| admin   | Grading scheme list                   | `grading-scheme-list`                 |
| admin   | Create grading scheme                 | `create-grading-scheme`               |
| admin   | Grade boundary editor                 | `grade-boundary-editor`               |
| admin   | Grade-point mapping                   | `grade-point-mapping`                 |
| admin   | GPA and CGPA rules                    | `gpa-and-cgpa-rules`                  |
| admin   | Assessment weighting editor           | `assessment-weighting-editor`         |
| admin   | Grading rule validation               | `grading-rule-validation`             |
| admin   | Approved grading scheme preview       | `approved-grading-scheme-preview`     |
| admin   | Result processing overview            | `result-processing-overview`          |
| admin   | Course result table                   | `course-result-table`                 |
| admin   | Student-wise marks                    | `student-wise-marks`                  |
| admin   | Weighted score calculation            | `weighted-score-calculation`          |
| admin   | GPA and CGPA preview                  | `gpa-and-cgpa-preview`                |
| admin   | Missing-result flags                  | `missing-result-flags`                |
| admin   | AI anomaly review                     | `ai-anomaly-review`                   |
| admin   | Result batch approval                 | `result-batch-approval`               |
| admin   | Publication confirmation              | `publication-confirmation`            |
| admin   | Published results overview            | `published-results-overview`          |
| admin   | Rechecking request queue              | `rechecking-request-queue`            |
| admin   | Rechecking request details            | `rechecking-request-details`          |
| admin   | Rechecking decision form              | `rechecking-decision-form`            |
| admin   | Approved rechecking correction        | `approved-rechecking-correction`      |
| admin   | Rechecking request history            | `rechecking-request-history`          |
| admin   | Analytics overview                    | `analytics-overview`                  |
| admin   | Class-wise analysis                   | `class-wise-analysis`                 |
| admin   | Individual student analysis           | `individual-student-analysis`         |
| admin   | AI findings queue                     | `ai-findings-queue`                   |
| admin   | Finding review details                | `finding-review-details`              |
| admin   | Prediction range & uncertainty        | `prediction-range-uncertainty`        |
| admin   | Prediction unavailable                | `prediction-unavailable`              |
| admin   | Admin AI Assistant                    | `admin-ai-assistant`                  |
| admin   | Assistant — no matching records       | `assistant-no-matching-records`       |
| admin   | Assistant — permission denied         | `assistant-permission-denied`         |
| admin   | Assistant — data unavailable          | `assistant-data-unavailable`          |
| admin   | Reports overview                      | `reports-overview`                    |
| admin   | Class-wise result report              | `class-wise-result-report`            |
| admin   | Course-wise performance report        | `course-wise-performance-report`      |
| admin   | Pass/fail report                      | `pass-fail-report`                    |
| admin   | Grade distribution report             | `grade-distribution-report`           |
| admin   | Individual student report             | `individual-student-report`           |
| admin   | Teacher submission report             | `teacher-submission-report`           |
| admin   | Seating plan report                   | `seating-plan-report`                 |
| admin   | Invigilation duty report              | `invigilation-duty-report`            |
| admin   | Examination schedule report           | `examination-schedule-report`         |
| admin   | Notification center                   | `notification-center`                 |
| admin   | Notification settings                 | `notification-settings`               |
| admin   | Audit logs                            | `audit-logs`                          |
| admin   | User activity details                 | `user-activity-details`               |
| admin   | Result change history                 | `result-change-history`               |
| admin   | Grading configuration history         | `grading-configuration-history`       |
| admin   | Academic settings                     | `academic-settings`                   |
| admin   | Account and permissions               | `account-and-permissions`             |
| admin   | Backup status                         | `backup-status`                       |
| admin   | Sign in                               | `sign-in`                             |
| admin   | Forgot password                       | `forgot-password`                     |
| admin   | Reset password                        | `reset-password`                      |
| admin   | Password reset confirmation           | `password-reset-confirmation`         |
| admin   | Unauthorized access                   | `unauthorized-access`                 |
| admin   | Session expired                       | `session-expired`                     |
| teacher | Teacher Dashboard                     | `teacher-dashboard`                   |
| teacher | My Courses                            | `my-courses`                          |
| teacher | My Students                           | `my-students`                         |
| teacher | Course details                        | `course-details`                      |
| teacher | Student roster                        | `student-roster`                      |
| teacher | Course assessments                    | `course-assessments`                  |
| teacher | Course examination schedule           | `course-examination-schedule`         |
| teacher | Course performance                    | `course-performance`                  |
| teacher | Marks Entry                           | `marks-entry`                         |
| teacher | Marks CSV import                      | `marks-csv-import`                    |
| teacher | Marks import errors                   | `marks-import-errors`                 |
| teacher | Marks Submissions                     | `marks-submissions`                   |
| teacher | Returned marks correction             | `returned-marks-correction`           |
| teacher | Marks submission confirmation         | `marks-submission-confirmation`       |
| teacher | Examination Schedule                  | `examination-schedule`                |
| teacher | Seating Plans                         | `seating-plans`                       |
| teacher | Invigilation Duties                   | `invigilation-duties`                 |
| teacher | Duty instructions                     | `duty-instructions`                   |
| teacher | Profile and Availability              | `profile-and-availability`            |
| teacher | Unavailability request                | `unavailability-request`              |
| teacher | Replacement status                    | `replacement-status`                  |
| teacher | Performance Analytics                 | `performance-analytics`               |
| teacher | Teacher Notifications                 | `teacher-notifications`               |
| teacher | Teacher AI Assistant                  | `teacher-ai-assistant`                |
| student | Student Dashboard                     | `student-dashboard`                   |
| student | My Profile                            | `my-profile`                          |
| student | My Courses                            | `my-courses`                          |
| student | Examination Schedule                  | `examination-schedule`                |
| student | Updated examination schedule          | `updated-examination-schedule`        |
| student | Admit Card                            | `admit-card`                          |
| student | Seating Plan                          | `seating-plan`                        |
| student | My Results                            | `my-results`                          |
| student | Semester results                      | `semester-results`                    |
| student | Performance Analysis                  | `performance-analysis`                |
| student | Rechecking Requests                   | `rechecking-requests`                 |
| student | Submit rechecking request             | `submit-rechecking-request`           |
| student | Rechecking confirmation               | `rechecking-confirmation`             |
| student | Rechecking decision                   | `rechecking-decision`                 |
| student | Student Notifications                 | `student-notifications`               |
| student | Student AI Assistant                  | `student-ai-assistant`                |
| student | Unpublished result                    | `unpublished-result`                  |
| admin   | Loading records                       | `loading-records`                     |
| admin   | Empty records                         | `empty-records`                       |
| admin   | Server error                          | `server-error`                        |
| admin   | Permission denied                     | `permission-denied`                   |
| admin   | Success confirmation                  | `success-confirmation`                |
| admin   | Marks validation error                | `marks-validation-error`              |
| admin   | No eligible replacement               | `no-eligible-replacement`             |
| admin   | Missing assessment weights            | `missing-assessment-weights`          |
| admin   | AI Automation                         | `ai-automation`                       |
| admin   | AI Settings                           | `ai-settings`                         |
