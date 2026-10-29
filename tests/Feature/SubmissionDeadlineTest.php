<?php

namespace Tests\Feature;

use App\Models as M;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class SubmissionDeadlineTest extends TestCase
{
    use RefreshDatabase, Fixture;

    public function test_admin_sets_default_and_teacher_override_and_teachers_see_effective_deadline(): void
    {
        $f = $this->fixture();
        $default = now()->addDays(4)->format('Y-m-d\TH:i');
        $override = now()->addHours(47)->format('Y-m-d\TH:i');
        $this->actingAs($f['admin'])->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['marks_due_at' => $default])->assertOk();
        $this->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['teacher_id' => $f['teacher']->id, 'marks_due_at' => $override])->assertOk();
        $this->assertDatabaseHas('marks_submission_deadlines', ['exam_course_id' => $f['ec']->id, 'teacher_id' => $f['teacher']->id]);
        $course = $f['ec']->fresh();
        $this->assertTrue($course->submissionDeadlineFor($f['teacher'])->equalTo(now()->addHours(47)));
        $this->assertTrue($course->marks_due_at->equalTo(now()->addDays(4)));
        foreach (['teacher-dashboard', 'marks-submissions'] as $screen) {
            $this->actingAs($f['teacher'])->get('/teacher/'.$screen)->assertOk()
                ->assertSee('data-submission-countdown', false)->assertSee('badge-warning', false)
                ->assertSee('data-deadline="'.now()->addHours(47)->toIso8601String().'"', false);
        }
        $this->actingAs($f['admin'])->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['teacher_id' => $f['teacher']->id, 'marks_due_at' => null])->assertOk();
        $this->assertDatabaseMissing('marks_submission_deadlines', ['exam_course_id' => $f['ec']->id, 'teacher_id' => $f['teacher']->id]);
        $this->assertTrue($f['ec']->fresh()->submissionDeadlineFor($f['teacher'])->equalTo(now()->addDays(4)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'marks.deadline_updated', 'entity_id' => $f['ec']->id]);
    }

    public function test_deadline_boundaries_overdue_submitted_and_unconfigured_states(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['teacher']);
        $this->get('/teacher/marks-submissions')->assertSee('No deadline set');
        $f['ec']->update(['marks_due_at' => now()->addHours(48)]);
        $this->get('/teacher/marks-submissions')->assertSee('badge-info', false)->assertSee('Upcoming deadline');
        $f['ec']->update(['marks_due_at' => now()->subMinute()]);
        $this->get('/teacher/marks-submissions')->assertSee('badge-danger', false)->assertSee('Overdue');
        M\MarkSubmission::create(['exam_course_id' => $f['ec']->id, 'teacher_id' => $f['teacher']->id, 'status' => 'submitted']);
        $this->get('/teacher/marks-submissions')->assertSee('Submitted')->assertDontSee('data-submission-countdown', false);
    }

    public function test_timezone_offsets_are_normalized_without_changing_the_deadline_instant(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['marks_due_at' => '2026-10-15T08:00:00Z'])
            ->assertOk()->assertJsonPath('marks_due_at', '2026-10-15T13:00:00+05:00');
        $this->assertSame('2026-10-15T13:00:00+05:00', $f['ec']->fresh()->marks_due_at->toIso8601String());
    }

    public function test_only_admin_can_set_deadlines_and_only_assigned_teachers_get_overrides(): void
    {
        $f = $this->fixture();
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs($f[$role])->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['marks_due_at' => now()->toIso8601String()])->assertForbidden();
        }
        $this->actingAs($f['admin'])->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['teacher_id' => $f['replacement']->id, 'marks_due_at' => now()->toIso8601String()])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->postJson('/workflow/marks/'.$f['ec']->id.'/deadline', ['marks_due_at' => 'not-a-date'])->assertUnprocessable();
        $f['ec']->update(['marks_due_at' => now()->addHours(12)]);
        $this->actingAs($f['replacement'])->get('/teacher/marks-submissions')->assertOk()->assertDontSee('data-submission-countdown', false)->assertDontSee('Final test');
    }
}
