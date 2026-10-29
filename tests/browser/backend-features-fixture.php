<?php
// Run only after tests/browser/redesign-fixture.php on the guarded _test database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Backend browser fixtures require an isolated _test database.');
}
$teacher = App\Models\User::where('email', 'teacher@example.test')->firstOrFail();
$draft = App\Models\ExamCourse::whereHas('examination', fn ($q) => $q->where('name', 'UI draft examination'))->firstOrFail();
$draft->update(['marks_due_at' => now()->addHours(72)->startOfMinute()]);
$draft->submissionDeadlines()->updateOrCreate(['teacher_id' => $teacher->id], ['marks_due_at' => now()->addHours(47)->startOfMinute()]);
$overdueExam = App\Models\Examination::create(['name' => 'Countdown overdue examination', 'type' => 'final', 'academic_session_id' => $draft->examination->academic_session_id, 'grading_scheme_id' => $draft->examination->grading_scheme_id]);
$overdue = App\Models\ExamCourse::create(['examination_id' => $overdueExam->id, 'course_id' => $draft->course_id, 'section_id' => $draft->section_id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50, 'marks_due_at' => now()->subHour()]);
App\Models\AssessmentComponent::create(['exam_course_id' => $overdue->id, 'name' => 'Overdue assessment', 'maximum' => 100, 'weight' => 100]);
$session = App\Models\AcademicSession::create(['name' => 'Browser archive session', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'active' => false]);
$semester = App\Models\Semester::create(['academic_session_id' => $session->id, 'name' => 'Browser archive semester', 'number' => 1]);
$section = App\Models\Section::create(['department_id' => $draft->section->department_id, 'semester_id' => $semester->id, 'name' => 'Browser archive section']);
$exam = App\Models\Examination::create(['name' => 'ARCHIVE_EXAM_BROWSER_ONLY', 'type' => 'final', 'academic_session_id' => $session->id, 'grading_scheme_id' => $draft->examination->grading_scheme_id]);
App\Models\ExamCourse::create(['examination_id' => $exam->id, 'course_id' => $draft->course_id, 'section_id' => $section->id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50]);
Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8022');
$old = App\Models\AdmitCard::with('examination')->firstOrFail();
$card = app(App\Services\ReportService::class)->admit($old->examination, $old->student_id);
file_put_contents('/tmp/examcell-backend-browser-fixture.json', json_encode(['session_id' => $session->id, 'semester_id' => $semester->id, 'draft_id' => $draft->id, 'admit_id' => $card->id]));
echo "Backend feature browser fixtures prepared in _test database.\n";
