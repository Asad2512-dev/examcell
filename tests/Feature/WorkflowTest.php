<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixture;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use Fixture,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
    }

    public function test_scheduling_conflicts_capacity_unavailability_and_publication(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $f['room']->update(['capacity' => 1]);
        $this->post('/workflow/schedule/'.$f['ec']->id, ['room_id' => $f['room']->id, 'starts_at' => '2026-10-14 09:00'])->assertSessionHasErrors('schedule');
        $f['room']->update(['capacity' => 2]);
        M\RoomUnavailability::create(['room_id' => $f['room']->id, 'starts_at' => '2026-10-14 08:00', 'ends_at' => '2026-10-14 12:00', 'reason' => 'Maintenance']);
        $this->post('/workflow/schedule/'.$f['ec']->id, ['room_id' => $f['room']->id, 'starts_at' => '2026-10-14 09:00'])->assertSessionHasErrors('schedule');
        $this->post('/workflow/examination/'.$f['exam']->id.'/state', ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->post('/workflow/schedule/'.$f['ec']->id, ['room_id' => $f['room2']->id, 'starts_at' => '2026-10-14 09:00'])->assertRedirect()->assertSessionHasNoErrors();
        $course2 = M\Course::create(['department_id' => $f['dep']->id, 'code' => 'CS302', 'name' => 'Operating Systems', 'credits' => 3]);
        M\Enrollment::create(['student_id' => $f['student']->id, 'course_id' => $course2->id, 'section_id' => $f['section']->id]);
        $ec2 = M\ExamCourse::create(['examination_id' => $f['exam']->id, 'course_id' => $course2->id, 'section_id' => $f['section']->id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50]);
        $this->post('/workflow/schedule/'.$ec2->id, ['room_id' => $f['room']->id, 'starts_at' => '2026-10-14 10:00'])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('schedule_entries', 1);
    }

    public function test_generation_and_seat_uniqueness_and_student_scope(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/workflow/examination/'.$f['exam']->id.'/generate', ['starts_on' => '2026-10-14', 'days' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $entry = M\ScheduleEntry::firstOrFail();
        $this->post('/workflow/seating/'.$entry->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('seat_assignments', 2);
        $this->post('/workflow/seating/'.$entry->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('seat_assignments', 2);
        $this->assertDatabaseHas('seating_plans', ['revision' => 2]);
        $s = app(S\SchedulingService::class);
        app(S\DutyService::class)->assign($entry, $f['teacher']->id);
        $s->transition($f['exam'], 'approved');
        $s->transition($f['exam'], 'published');
        $this->actingAs($f['student'])->get('/student/seating-plan')->assertOk()->assertSee('Student One')->assertDontSee('Student Two');
        $this->post('/workflow/seating/'.$entry->id)->assertForbidden();
        $this->actingAs($f['admin'])->post('/workflow/seating/'.$entry->id)->assertSessionHasErrors('seats');
    }

    public function test_duty_unavailability_proposals_revalidation_and_history(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $entry = app(S\SchedulingService::class)->schedule($f['ec'], $f['room']->id, '2026-10-14 09:00');
        $this->post('/workflow/duty/'.$entry->id, ['teacher_id' => $f['teacher']->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($f['teacher'])->post('/workflow/availability', ['teacher_id' => $f['replacement']->id, 'starts_at' => '2026-10-14 08:00', 'ends_at' => '2026-10-14 12:00', 'reason' => 'Medical appointment'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('duty_requests', ['teacher_id' => $f['teacher']->id, 'status' => 'pending']);
        $this->assertDatabaseCount('teacher_availabilities', 0);
        $this->assertDatabaseHas('duty_assignments', ['teacher_id' => $f['teacher']->id, 'status' => 'assigned']);
        $p = M\DutyReplacement::firstOrFail();
        $this->assertSame($f['replacement']->id, $p->candidate_id);
        $this->post('/workflow/replacement/'.$p->id.'/approve')->assertForbidden();
        $this->actingAs($f['admin'])->post('/workflow/replacement/'.$p->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('duty_assignments', ['teacher_id' => $f['replacement']->id, 'status' => 'assigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'duty.replacement_approved']);
        $this->assertGreaterThan(0, $f['replacement']->notifications()->count());
    }

    public function test_no_eligible_replacement_stays_unresolved(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $f['replacement']->update(['active' => false]);
        $entry = app(S\SchedulingService::class)->schedule($f['ec'], $f['room']->id, '2026-10-14 09:00');
        app(S\DutyService::class)->assign($entry, $f['teacher']->id);
        app(S\DutyService::class)->unavailable($f['teacher']->id, '2026-10-14 08:00', '2026-10-14 12:00', 'No availability');
        $p = M\DutyReplacement::first();
        $this->assertSame('unresolved', $p->status);
        $this->post('/workflow/replacement/'.$p->id.'/approve')->assertSessionHasErrors('replacement');
    }

    public function test_marks_are_authorized_validated_complete_and_locked(): void
    {
        $f = $this->fixture();
        $row = ['student_id' => $f['student']->id, 'assessment_component_id' => $f['component']->id, 'score' => 101];
        $this->actingAs($f['replacement'])->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row]])->assertForbidden();
        $this->actingAs($f['teacher'])->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row]])->assertSessionHasErrors('marks');
        $row['score'] = '-1';
        $this->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row]])->assertSessionHasErrors();
        $row['score'] = '86';
        $this->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row], 'submit' => 1])->assertSessionHasErrors('marks');
        $this->assertDatabaseCount('marks', 0);
        $this->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('marks', 1);
        $this->assertCount(1, app(S\MarksService::class)->missing($f['ec']));
        $row2 = ['student_id' => $f['other']->id, 'assessment_component_id' => $f['component']->id, 'score' => null, 'absent' => true];
        $this->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row, $row2], 'submit' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(0, app(S\MarksService::class)->missing($f['ec']));
        $this->post('/workflow/marks/'.$f['ec']->id, ['rows' => [$row]])->assertSessionHasErrors('marks');
        $this->assertDatabaseHas('marks', ['student_id' => $f['other']->id, 'score' => null, 'absent' => true]);
    }

    public function test_grading_boundaries_decimal_rounding_and_credit_weighting(): void
    {
        $f = $this->fixture();
        $this->submitted($f, ['84.99', '85.00']);
        $this->actingAs($f['admin']);
        $batch = app(S\ResultsService::class)->calculate($f['exam']);
        $first = $batch->results()->where('student_id', $f['student']->id)->first();
        $second = $batch->results()->where('student_id', $f['other']->id)->first();
        $this->assertSame('B', $first->grade);
        $this->assertSame('A', $second->grade);
        $this->assertSame('12.00', $second->quality_points);
        $g = app(S\GradingService::class);
        $this->assertSame('3.50', $g->gpa(collect([$first, $second])));
        $this->assertSame('1.24', $g->round('1.235'));
        $this->assertTrue($first->passed);
    }

    public function test_result_lifecycle_published_correction_recheck_and_private_download(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $b = app(S\ResultsService::class)->calculate($f['exam']);
        $this->actingAs($f['student'])->get('/student/my-results')->assertDontSee('Database Systems');
        $this->actingAs($f['admin']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $status) {
            $this->post('/workflow/batch/'.$b->id.'/state', ['status' => $status])->assertRedirect()->assertSessionHasNoErrors();
        }
        $result = $b->results()->where('student_id', $f['student']->id)->first();
        $this->actingAs($f['student'])->get('/student/my-results')->assertSee('Database Systems')->assertDontSee('Student Two');
        $this->post('/workflow/recheck/'.$result->id, ['reason' => 'Please verify my final assessment.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/workflow/recheck/'.$b->results()->where('student_id', $f['other']->id)->first()->id, ['reason' => 'Please verify my final assessment.'])->assertForbidden();
        $request = M\RecheckRequest::first();
        $this->actingAs($f['admin'])->post('/workflow/recheck/'.$request->id.'/decide', ['status' => 'approved', 'reason' => 'Rechecking the assessed response.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('marks', ['student_id' => $f['student']->id, 'score' => '86.00']);
        $mark = M\Mark::where('student_id', $f['student']->id)->first();
        $this->post('/workflow/correction/'.$mark->id, ['score' => '90', 'reason' => 'Approved answer recount identified four points.'])->assertRedirect()->assertSessionHasNoErrors();
        $p = M\ResultCorrection::first();
        $this->post('/workflow/correction/'.$p->id.'/approve')->assertSessionHasErrors('correction');
        $this->actingAs($f['reviewer'])->post('/workflow/correction/'.$p->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('calculated_results', ['student_id' => $f['student']->id, 'percentage' => '90.00']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'correction.approved']);
        $this->actingAs($f['admin'])->post('/workflow/admit/'.$f['exam']->id, ['student_id' => $f['student']->id])->assertRedirect()->assertSessionHasNoErrors();
        $card = M\AdmitCard::first();
        $this->assertTrue(Storage::exists($card->path));
        $this->actingAs($f['student'])->get('/download/admit/'.$card->id)->assertOk();
        $this->actingAs($f['other'])->get('/download/admit/'.$card->id)->assertForbidden();
        $this->get('/reports/results')->assertOk()->assertDownload('results.csv');
    }

    public function test_incomplete_publication_and_analysis_flags_are_distinct(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/workflow/results/'.$f['exam']->id.'/calculate')->assertSessionHasErrors('results');
        $this->assertDatabaseCount('calculated_results', 0);
        $this->post('/workflow/analysis/'.$f['exam']->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('analysis_flags', ['type' => 'missing_result']);
        $this->assertDatabaseHas('analysis_flags', ['type' => 'missing_record']);
        $this->assertDatabaseHas('analysis_flags', ['type' => 'unsubmitted']);
        $this->assertDatabaseCount('ai_analysis_runs', 1);
    }
}
