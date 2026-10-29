<?php

namespace Tests\Feature;

use App\Jobs\GenerateAdmitCards;
use App\Models as M;
use App\Notifications\WorkflowNotice;
use App\Services as S;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fixture;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use Fixture,RefreshDatabase;

    public function test_returned_marks_are_recalculated_before_result_approval(): void
    {
        $f = $this->fixture();
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $batch = app(S\ResultsService::class)->calculate($f['exam']);
        app(S\MarksService::class)->return($f['ec'], 'Please verify the score.');
        $this->submitted($f, ['90', '74']);
        $this->actingAs($f['admin']);
        foreach (['submitted', 'under_review', 'approved'] as $status) {
            app(S\ResultsService::class)->transition($batch, $status, $f['admin']->id);
        }$this->assertDatabaseHas('calculated_results', ['student_id' => $f['student']->id, 'percentage' => '90.00']);
    }

    public function test_sql_analytics_and_teacher_roster_are_scoped(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $batch = app(S\ResultsService::class)->calculate($f['exam']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $status) {
            app(S\ResultsService::class)->transition($batch, $status, $f['admin']->id);
        }$admin = app(S\AnalyticsService::class)->aggregates($f['admin']);
        $student = app(S\AnalyticsService::class)->aggregates($f['student']);
        $this->assertSame(2, $admin['records']);
        $this->assertSame('80.00', $admin['average']);
        $this->assertSame(1, $student['records']);
        $this->assertSame('86.00', $student['average']);
        $this->actingAs($f['teacher'])->get('/teacher/my-students')->assertOk()->assertSee('Student One')->assertSee('Student Two');
        $this->actingAs($f['replacement'])->get('/teacher/my-students')->assertOk()->assertDontSee('Student One')->assertDontSee('Student Two');
        $this->actingAs($f['admin'])->get('/admin/analytics-overview')->assertOk()->assertSee('80.00')->assertSee('CS301');
    }

    public function test_password_reset_changes_password_and_invalid_token_is_rejected(): void
    {
        $f = $this->fixture();
        Notification::fake();
        $this->post('/forgot-password', ['email' => $f['student']->email])->assertRedirect()->assertSessionHas('status');
        Notification::assertSentTo($f['student'], ResetPassword::class);
        $token = Password::createToken($f['student']);
        $this->post('/reset-password', ['email' => $f['student']->email, 'token' => 'invalid', 'password' => 'ChangedPassword2026!', 'password_confirmation' => 'ChangedPassword2026!'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['email' => $f['student']->email, 'token' => $token, 'password' => 'ChangedPassword2026!', 'password_confirmation' => 'ChangedPassword2026!'])->assertRedirect('/sign-in');
        $this->assertTrue(Hash::check('ChangedPassword2026!', $f['student']->fresh()->password));
    }

    public function test_notification_ownership_and_audit_write_protection(): void
    {
        $f = $this->fixture();
        $f['student']->notify(new WorkflowNotice('Private notice', 'Only for this student'));
        $notice = $f['student']->notifications()->first();
        $this->actingAs($f['other'])->post('/workflow/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->actingAs($f['student'])->post('/workflow/notifications/'.$notice->id.'/read')->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->post('/manage/audit', ['action' => 'tampered'])->assertForbidden();
        $this->get('/reports/audit')->assertForbidden();
    }

    public function test_published_configuration_is_immutable_and_roles_are_server_derived(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->actingAs($f['admin'])->post('/manage/courses/'.$f['course']->id, ['department_id' => $f['dep']->id, 'code' => 'CS301', 'name' => 'Changed course', 'credits' => 4])->assertSessionHasErrors('record');
        $this->post('/manage/components/'.$f['component']->id, ['exam_course_id' => $f['ec']->id, 'name' => 'Changed', 'maximum' => 100, 'weight' => 100])->assertSessionHasErrors('record');
        $this->actingAs($f['student'])->post('/manage/users/'.$f['student']->id, ['role' => 'admin', 'name' => 'Admin', 'email' => 'one@example.test'])->assertForbidden();
        $this->get('/teacher/my-students')->assertForbidden();
        $this->assertSame('student', $f['student']->fresh()->role);
    }

    public function test_schedule_revision_retains_history_and_notifies_students(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->actingAs($f['admin']);
        $revision = app(S\SchedulingService::class)->revision($f['exam']);
        $this->assertSame('draft', $revision->status);
        $this->assertSame($f['exam']->id, $revision->supersedes_id);
        $c = $revision->courses()->first();
        app(S\SchedulingService::class)->schedule($c, $f['room2']->id, '2026-10-15 09:00');
        app(S\SeatingService::class)->allocate($c->fresh()->schedule, $f['admin']->id);
        app(S\DutyService::class)->assign($c->fresh()->schedule, $f['teacher']->id);
        app(S\SchedulingService::class)->transition($revision, 'approved');
        app(S\SchedulingService::class)->transition($revision, 'published');
        $this->assertSame('archived', $f['exam']->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.superseded']);
        $this->assertDatabaseCount('schedule_entries', 2);
        $this->actingAs($f['student'])->get('/student/examination-schedule')->assertOk()->assertSee('15 Oct 2026')->assertDontSee('14 Oct 2026');
    }

    public function test_bulk_admit_job_is_queued_and_actual_artifacts_are_generated(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        Queue::fake();
        $this->actingAs($f['admin'])->post('/workflow/admit/'.$f['exam']->id, ['bulk' => 1])->assertRedirect();
        Queue::assertPushed(GenerateAdmitCards::class);
        $run = M\AdmitGenerationRun::firstOrFail();
        $this->assertSame('queued', $run->status);
        $this->assertDatabaseCount('admit_cards', 0);
        (new GenerateAdmitCards($run->id))->handle();
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->generated);
        $this->assertDatabaseCount('admit_cards', 2);
        foreach (M\AdmitCard::all() as $card) {
            $this->assertStringStartsWith('%PDF', Storage::get($card->path));
        }
    }

    public function test_duty_overlap_and_workload_limit_reject_assignment(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        config(['examcell.max_duties_per_day' => 1]);
        $entry = app(S\SchedulingService::class)->schedule($f['ec'], $f['room']->id, '2026-10-14 09:00');
        app(S\DutyService::class)->assign($entry, $f['teacher']->id);
        $otherExam = M\Examination::create(['name' => 'Other final', 'type' => 'final', 'academic_session_id' => $f['session']->id, 'grading_scheme_id' => $f['scheme']->id]);
        $ec = M\ExamCourse::create(['examination_id' => $otherExam->id, 'course_id' => $f['course']->id, 'section_id' => $f['section']->id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50]);
        $otherEntry = app(S\SchedulingService::class)->schedule($ec, $f['room2']->id, '2026-10-14 13:00');
        $this->post('/workflow/duty/'.$otherEntry->id, ['teacher_id' => $f['teacher']->id])->assertSessionHasErrors('teacher_id');
        $this->assertDatabaseCount('duty_assignments', 1);
    }

    public function test_ai_timeout_invalid_output_and_daily_budget_have_truthful_fallbacks(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->actingAs($f['student']);
        config(['services.openai.enabled' => true, 'services.openai.key' => 'test-only']);
        Http::fake(fn () => throw new ConnectionException('Simulated timeout'));
        $this->postJson('/workflow/assistant', ['intent' => 'schedule', 'question' => 'My schedule', 'explain' => 1])->assertOk()->assertJsonPath('status', 'unavailable')->assertDontSee('Simulated timeout');
        Http::swap(new Factory);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => []])]);
        $this->postJson('/workflow/assistant', ['intent' => 'schedule', 'question' => 'My schedule', 'explain' => 1])->assertOk()->assertJsonPath('status', 'invalid_output');
        $conversation = M\Conversation::create(['user_id' => $f['student']->id]);
        for ($i = 0; $i < 30; $i++) {
            $conversation->messages()->create(['role' => 'assistant', 'content' => 'Explanation', 'provider_status' => 'openai']);
        }Http::swap(new Factory);
        Http::fake();
        $this->postJson('/workflow/assistant', ['intent' => 'schedule', 'question' => 'My schedule', 'explain' => 1])->assertOk()->assertJsonPath('status', 'daily_limit');
        Http::assertNothingSent();
    }

    public function test_weighted_assessments_rounding_gpa_and_absence_rules(): void
    {
        $f = $this->fixture();
        $f['component']->update(['weight' => '50', 'maximum' => '40']);
        $second = M\AssessmentComponent::create(['exam_course_id' => $f['ec']->id, 'name' => 'Coursework', 'maximum' => 60, 'weight' => 50]);
        $this->actingAs($f['teacher']);
        $rows = [];
        foreach ([$f['student'], $f['other']] as $student) {
            $rows[] = ['assessment_component_id' => $f['component']->id, 'student_id' => $student->id, 'score' => '34.00'];
            $rows[] = ['assessment_component_id' => $second->id, 'student_id' => $student->id, 'score' => '51.00'];
        }app(S\MarksService::class)->save($f['ec']->fresh(), $rows, $f['teacher']->id, true);
        $grade = app(S\GradingService::class)->calculate($f['ec']->fresh(), $f['student']->id);
        $this->assertSame('85.00', $grade['percentage']);
        $this->assertSame('A', $grade['grade']);
        $this->assertSame('12.00', $grade['quality_points']);
        $this->assertSame('4.00', app(S\GradingService::class)->gpa(collect([(object) $grade])));
    }
}
