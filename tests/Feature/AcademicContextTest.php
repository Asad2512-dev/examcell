<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services\AcademicContext;
use App\Services\ScopedQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class AcademicContextTest extends TestCase
{
    use RefreshDatabase, Fixture;

    private function period(array $f, string $label, ?M\AcademicSession $session = null): array
    {
        $session ??= M\AcademicSession::create(['name' => $label.' session', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'active' => false]);
        $semester = M\Semester::create(['academic_session_id' => $session->id, 'name' => $label.' semester', 'number' => 2]);
        $section = M\Section::create(['department_id' => $f['dep']->id, 'semester_id' => $semester->id, 'name' => $label.' section']);
        $student = M\User::create(['name' => $label.' student', 'email' => strtolower($label).'@scope.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
        M\StudentProfile::create(['user_id' => $student->id, 'section_id' => $section->id, 'roll_number' => $label.'-ROLL']);
        M\Enrollment::create(['student_id' => $student->id, 'section_id' => $section->id, 'course_id' => $f['course']->id]);
        M\TeacherAssignment::create(['teacher_id' => $f['teacher']->id, 'section_id' => $section->id, 'course_id' => $f['course']->id]);
        $exam = M\Examination::create(['name' => $label.'_EXAM_ONLY', 'academic_session_id' => $session->id, 'grading_scheme_id' => $f['scheme']->id, 'type' => 'final', 'status' => 'published', 'admit_enabled' => true]);
        $ec = M\ExamCourse::create(['examination_id' => $exam->id, 'section_id' => $section->id, 'course_id' => $f['course']->id, 'duration_minutes' => 120, 'maximum' => '100', 'passing' => '50']);
        $component = M\AssessmentComponent::create(['exam_course_id' => $ec->id, 'name' => $label.' assessment', 'maximum' => 100, 'weight' => 100]);
        $schedule = M\ScheduleEntry::create(['exam_course_id' => $ec->id, 'room_id' => $f['room']->id, 'starts_at' => '2026-10-20 09:00', 'ends_at' => '2026-10-20 11:00']);
        $batch = M\ResultBatch::create(['examination_id' => $exam->id, 'status' => 'published']);
        $result = M\CalculatedResult::create(['result_batch_id' => $batch->id, 'exam_course_id' => $ec->id, 'student_id' => $student->id, 'percentage' => 80, 'passed' => true, 'grade' => 'B', 'grade_points' => 3, 'credits' => 3, 'quality_points' => 9]);
        M\RecheckRequest::create(['calculated_result_id' => $result->id, 'student_id' => $student->id, 'reason' => $label.' recheck reason']);
        M\AdmitCard::create(['examination_id' => $exam->id, 'student_id' => $student->id, 'reference' => (string) \Illuminate\Support\Str::uuid(), 'path' => $label.'.pdf']);
        M\AnalysisFlag::create(['examination_id' => $exam->id, 'student_id' => $student->id, 'type' => 'review', 'record_key' => $label, 'details' => []]);
        M\AuditLog::create(['action' => $label.'_AUDIT_ONLY', 'entity_type' => M\ExamCourse::class, 'entity_id' => $ec->id, 'changes' => []]);

        return compact('session', 'semester', 'section', 'student', 'exam', 'ec', 'component', 'schedule', 'batch', 'result');
    }

    public function test_default_active_session_and_switch_persist_across_views_and_reports(): void
    {
        $f = $this->fixture(); $this->publish($f); $this->submitted($f);
        $f['exam']->update(['name' => 'CURRENT_EXAM_ONLY']);
        $other = $this->period($f, 'HISTORICAL');
        $this->actingAs($f['admin'])->get('/admin/admin-dashboard')->assertOk()
            ->assertSessionHas('academic_context.session_id', $f['session']->id)
            ->assertViewHas('courses', fn ($courses) => $courses->modelKeys() === [$f['ec']->id])
            ->assertViewHas('exams', fn ($exams) => $exams->modelKeys() === [$f['exam']->id]);
        $this->post('/academic-context', ['session_id' => $other['session']->id, 'semester_id' => $other['semester']->id])
            ->assertRedirect('/admin/admin-dashboard')->assertSessionHas('academic_context.semester_id', $other['semester']->id);
        $this->get('/admin/date-sheet-list')->assertOk()->assertViewHas('records', fn ($rows) => $rows->getCollection()->modelKeys() === [$other['schedule']->id])
            ->assertDontSee('CURRENT_EXAM_ONLY');
        foreach (['result-batch-overview', 'analytics-overview'] as $screen) {
            $this->get('/admin/'.$screen)->assertOk()->assertViewHas('records', fn ($rows) => $rows->getCollection()->modelKeys() === [$other['result']->id]);
        }
        $this->get('/admin/rechecking-request-queue')->assertOk()->assertViewHas('records', fn ($rows) => $rows->count() === 1 && $rows->first()->student_id === $other['student']->id);
        $this->get('/admin/eligible-admit-card-students')->assertOk()->assertViewHas('records', fn ($rows) => $rows->count() === 1 && $rows->first()->student_id === $other['student']->id);
        $this->get('/admin/records?resource=sections')->assertOk()->assertViewHas('records', fn ($rows) => $rows->getCollection()->modelKeys() === [$other['section']->id]);
        $this->get('/admin/records?resource=exam-courses&id='.$f['ec']->id)->assertNotFound();
        $csv = $this->get('/reports/results?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('HISTORICAL student', $csv);
        $this->assertStringNotContainsString('Student One', $csv);
        $audit = $this->get('/reports/audit?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('HISTORICAL_AUDIT_ONLY', $audit);
        $this->assertStringNotContainsString('seating.allocated', $audit);
        $this->assertSame([$other['ec']->id], app(ScopedQueries::class)->courses($f['admin'])->pluck('id')->all());
    }

    public function test_semester_filter_is_independent_and_resets_when_session_changes(): void
    {
        $f = $this->fixture();
        $sameSession = $this->period($f, 'OTHER_SEMESTER', $f['session']);
        $otherSession = $this->period($f, 'OTHER_SESSION');
        $this->actingAs($f['admin'])->get('/admin/admin-dashboard')->assertViewHas('courses', fn ($rows) => $rows->count() === 2);
        $this->post('/academic-context', ['session_id' => $f['session']->id, 'semester_id' => $sameSession['semester']->id])->assertRedirect();
        $this->get('/admin/admin-dashboard')->assertViewHas('courses', fn ($rows) => $rows->modelKeys() === [$sameSession['ec']->id]);
        $this->postJson('/academic-context', ['session_id' => $otherSession['session']->id, 'semester_id' => $sameSession['semester']->id])->assertUnprocessable()->assertJsonValidationErrors('semester_id');
        $this->assertSame($f['session']->id, session('academic_context.session_id'));
        $this->post('/academic-context', ['session_id' => $otherSession['session']->id])->assertRedirect()->assertSessionHas('academic_context', fn ($context) => array_key_exists('semester_id', $context) && $context['semester_id'] === null);
        $this->get('/admin/admin-dashboard')->assertViewHas('courses', fn ($rows) => $rows->modelKeys() === [$otherSession['ec']->id]);
    }

    public function test_context_is_admin_only_does_not_change_roles_and_does_not_leak_between_accounts(): void
    {
        $f = $this->fixture();
        $other = $this->period($f, 'OTHER');
        $this->actingAs($f['admin'])->post('/academic-context', ['session_id' => $other['session']->id])->assertRedirect();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->postJson('/academic-context', ['session_id' => $other['session']->id])->assertForbidden();
            $this->get('/admin/admin-dashboard')->assertForbidden();
        }
        $this->actingAs($f['teacher'])->get('/teacher/teacher-dashboard')->assertViewHas('courses', fn ($rows) => $rows->count() === 2);
        $this->actingAs($f['reviewer'])->get('/admin/admin-dashboard')->assertSessionHas('academic_context.session_id', $f['session']->id);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($f['admin'])->allows('view', $other['ec']));
    }
}
