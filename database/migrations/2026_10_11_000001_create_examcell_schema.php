<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('role', ['admin', 'teacher', 'student'])->index();
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('academic_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('semesters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->unsignedTinyInteger('number');
            $t->unique(['academic_session_id', 'name']);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('semester_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->unique(['department_id', 'semester_id', 'name']);
            $t->timestamps();
        });
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->decimal('credits', 5, 2);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('student_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->string('roll_number')->unique();
            $t->boolean('eligible')->default(true);
            $t->timestamps();
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->boolean('eligible')->default(true);
            $t->unique(['student_id', 'course_id', 'section_id']);
            $t->timestamps();
        });
        Schema::create('teacher_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->unique(['teacher_id', 'course_id', 'section_id']);
            $t->timestamps();
        });
        Schema::create('grading_schemes', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->enum('status', ['draft', 'approved', 'archived'])->default('draft');
            $t->unsignedTinyInteger('rounding_places')->default(2);
            $t->timestamps();
        });
        Schema::create('grade_boundaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grading_scheme_id')->constrained()->restrictOnDelete();
            $t->string('grade', 8);
            $t->decimal('minimum', 5, 2);
            $t->decimal('grade_points', 4, 2);
            $t->unique(['grading_scheme_id', 'minimum']);
            $t->timestamps();
        });
        Schema::create('examinations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('type', ['midterm', 'final', 'quiz', 'assignment']);
            $t->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignId('grading_scheme_id')->constrained()->restrictOnDelete();
            $t->enum('status', ['draft', 'approved', 'published', 'archived'])->default('draft')->index();
            $t->boolean('admit_enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('exam_courses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('course_id')->constrained()->restrictOnDelete();
            $t->foreignId('section_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('duration_minutes');
            $t->decimal('maximum', 8, 2);
            $t->decimal('passing', 8, 2);
            $t->unique(['examination_id', 'course_id', 'section_id']);
            $t->timestamps();
        });
        Schema::create('assessment_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_course_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->decimal('maximum', 8, 2);
            $t->decimal('weight', 5, 2);
            $t->unique(['exam_course_id', 'name']);
            $t->timestamps();
        });
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('capacity');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('room_unavailabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('reason');
            $t->index(['room_id', 'starts_at', 'ends_at']);
            $t->timestamps();
        });
        Schema::create('schedule_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_course_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->index(['room_id', 'starts_at', 'ends_at']);
            $t->timestamps();
        });
        Schema::create('seating_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_entry_id')->unique()->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('seat_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seating_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->unsignedSmallInteger('seat_number');
            $t->unique(['seating_plan_id', 'student_id']);
            $t->unique(['seating_plan_id', 'seat_number']);
            $t->timestamps();
        });
        Schema::create('admit_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->uuid('reference')->unique();
            $t->string('path');
            $t->unique(['examination_id', 'student_id']);
            $t->timestamps();
        });
        Schema::create('teacher_availabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->boolean('available')->default(false);
            $t->string('reason');
            $t->index(['teacher_id', 'starts_at', 'ends_at']);
            $t->timestamps();
        });
        Schema::create('duty_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('schedule_entry_id')->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['assigned', 'unavailable', 'replaced'])->default('assigned');
            $t->boolean('reserve')->default(false);
            $t->unique(['schedule_entry_id', 'teacher_id']);
            $t->timestamps();
        });
        Schema::create('duty_replacements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('duty_assignment_id')->constrained()->restrictOnDelete();
            $t->foreignId('candidate_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->enum('status', ['proposed', 'approved', 'unresolved', 'rejected'])->default('proposed');
            $t->text('reason');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('mark_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_course_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['draft', 'submitted', 'returned'])->default('draft');
            $t->text('return_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('marks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_component_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->decimal('score', 8, 2)->nullable();
            $t->boolean('absent')->default(false);
            $t->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $t->unique(['assessment_component_id', 'student_id']);
            $t->timestamps();
        });
        Schema::create('result_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->unique()->constrained()->restrictOnDelete();
            $t->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'published', 'returned'])->default('draft');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('calculated_results', function (Blueprint $t) {
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
        });
        Schema::create('result_corrections', function (Blueprint $t) {
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
        });
        Schema::create('recheck_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calculated_result_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $t->text('decision_reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unique(['calculated_result_id', 'student_id']);
            $t->timestamps();
        });
        Schema::create('ai_analysis_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('completed');
            $t->json('summary');
            $t->timestamps();
        });
        Schema::create('analysis_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('examination_id')->constrained()->restrictOnDelete();
            $t->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('type');
            $t->string('record_key')->unique();
            $t->json('details');
            $t->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $t->text('review_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('title')->default('ExamCell assistant');
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $t->enum('role', ['user', 'assistant']);
            $t->text('content');
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->string('provider_status')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action')->index();
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('changes');
            $t->index(['entity_type', 'entity_id']);
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('analysis_flags');
        Schema::dropIfExists('ai_analysis_runs');
        Schema::dropIfExists('recheck_requests');
        Schema::dropIfExists('result_corrections');
        Schema::dropIfExists('calculated_results');
        Schema::dropIfExists('result_batches');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('mark_submissions');
        Schema::dropIfExists('duty_replacements');
        Schema::dropIfExists('duty_assignments');
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('admit_cards');
        Schema::dropIfExists('seat_assignments');
        Schema::dropIfExists('seating_plans');
        Schema::dropIfExists('schedule_entries');
        Schema::dropIfExists('room_unavailabilities');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('exam_courses');
        Schema::dropIfExists('examinations');
        Schema::dropIfExists('grade_boundaries');
        Schema::dropIfExists('grading_schemes');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');
    }
};
