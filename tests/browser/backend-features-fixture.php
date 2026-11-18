<?php

use App\Models\AcademicSession;
use App\Models\AdmitCard;
use App\Models\AssessmentComponent;
use App\Models\ExamCourse;
use App\Models\Examination;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;

// Run only after tests/browser/redesign-fixture.php on the guarded _test database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Backend browser fixtures require an isolated _test database.');
}
$teacher = User::where('email', 'teacher@example.test')->firstOrFail();
$draft = ExamCourse::whereHas('examination', fn ($q) => $q->where('name', 'UI draft examination'))->firstOrFail();
$draft->update(['marks_due_at' => now()->addHours(72)->startOfMinute()]);
$draft->submissionDeadlines()->updateOrCreate(['teacher_id' => $teacher->id], ['marks_due_at' => now()->addHours(47)->startOfMinute()]);
$overdueExam = Examination::create(['name' => 'Countdown overdue examination', 'type' => 'final', 'academic_session_id' => $draft->examination->academic_session_id, 'grading_scheme_id' => $draft->examination->grading_scheme_id]);
$overdue = ExamCourse::create(['examination_id' => $overdueExam->id, 'course_id' => $draft->course_id, 'section_id' => $draft->section_id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50, 'marks_due_at' => now()->subHour()]);
AssessmentComponent::create(['exam_course_id' => $overdue->id, 'name' => 'Overdue assessment', 'maximum' => 100, 'weight' => 100]);
$session = AcademicSession::create(['name' => 'Browser archive session', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'active' => false]);
$semester = Semester::create(['academic_session_id' => $session->id, 'name' => 'Browser archive semester', 'number' => 1]);
$section = Section::create(['department_id' => $draft->section->department_id, 'semester_id' => $semester->id, 'name' => 'Browser archive section']);
$exam = Examination::create(['name' => 'ARCHIVE_EXAM_BROWSER_ONLY', 'type' => 'final', 'academic_session_id' => $session->id, 'grading_scheme_id' => $draft->examination->grading_scheme_id]);
ExamCourse::create(['examination_id' => $exam->id, 'course_id' => $draft->course_id, 'section_id' => $section->id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50]);
URL::forceRootUrl('http://127.0.0.1:8022');
$old = AdmitCard::with('examination')->firstOrFail();
$card = app(ReportService::class)->admit($old->examination, $old->student_id);
file_put_contents('/tmp/examcell-backend-browser-fixture.json', json_encode(['session_id' => $session->id, 'semester_id' => $semester->id, 'draft_id' => $draft->id, 'admit_id' => $card->id]));
echo "Backend feature browser fixtures prepared in _test database.\n";
