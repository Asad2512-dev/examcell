<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services\AI as AI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\Fixture;
use Tests\TestCase;

/** OpenAI is always faked here; the suite never reaches the network. */
class AiAutomationTest extends TestCase
{
    use Fixture,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['openai.api_key' => 'test-only-key', 'queue.default' => 'sync', 'ai.retry_delay_ms' => 0]);
    }

    private function reply(array $payload, int $in = 100, int $out = 50): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($payload)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out]]);
    }

    /** A second paper for the same class (shares every student with the first). */
    private function secondPaper(array $f): M\ExamCourse
    {
        $course = M\Course::create(['department_id' => $f['dep']->id, 'code' => 'CS302', 'name' => 'Operating Systems', 'credits' => '3.00']);
        M\Enrollment::create(['student_id' => $f['student']->id, 'course_id' => $course->id, 'section_id' => $f['section']->id]);
        M\Enrollment::create(['student_id' => $f['other']->id, 'course_id' => $course->id, 'section_id' => $f['section']->id]);
        M\TeacherAssignment::create(['teacher_id' => $f['replacement']->id, 'course_id' => $course->id, 'section_id' => $f['section']->id]);
        $ec = M\ExamCourse::create(['examination_id' => $f['exam']->id, 'course_id' => $course->id, 'section_id' => $f['section']->id, 'duration_minutes' => 120, 'maximum' => '100', 'passing' => '50']);
        M\AssessmentComponent::create(['exam_course_id' => $ec->id, 'name' => 'Final', 'maximum' => '100', 'weight' => '100']);

        return $ec;
    }

    public function test_settings_never_reveal_the_key_and_show_status(): void
    {
        $f = $this->fixture();
        $html = $this->actingAs($f['admin'])->get('/admin/settings?tab=ai')->assertOk()->assertSee('Configured')->getContent();
        $this->assertStringNotContainsString('test-only-key', $html);
        config(['openai.api_key' => '']);
        $this->get('/admin/settings?tab=ai')->assertOk()->assertSee('Missing');
        $this->actingAs($f['student'])->get('/admin/settings?tab=ai')->assertForbidden();
    }

    public function test_connection_test_logs_usage_and_cost_without_the_key(): void
    {
        $f = $this->fixture();
        OpenAI::fake([$this->reply(['ok' => true], 1000, 500)]);
        $this->actingAs($f['admin'])->post('/admin/ai-settings/test')->assertRedirect('/admin/settings?tab=ai')->assertSessionHas('status', 'Connection successful.');
        $u = M\AiUsage::first();
        $this->assertSame(1000, $u->input_tokens);
        $this->assertGreaterThan(0, (float) $u->cost_usd);
        $this->assertStringNotContainsString('test-only-key', json_encode(M\AuditLog::all()->toArray()));
    }

    public function test_missing_key_never_calls_openai(): void
    {
        $f = $this->fixture();
        config(['openai.api_key' => '']);
        OpenAI::fake([]);
        $this->actingAs($f['admin'])->post('/ai/start', ['feature' => 'datesheet', 'exam_id' => $f['exam']->id])->assertSessionHasErrors('ai');
        $this->assertSame(0, M\AiRun::count());
    }

    public function test_datesheet_retries_with_errors_then_validates_and_applies_on_approval(): void
    {
        $f = $this->fixture();
        $ec2 = $this->secondPaper($f);
        $a = $f['ec']->id;
        $b = $ec2->id;
        $room = $f['room2']->id;
        OpenAI::fake([
            $this->reply(['blocked_slots' => [['weekday' => 5, 'from_hour' => 12, 'to_hour' => 18]], 'max_papers_per_day_per_section' => 1, 'min_gap_days_before_major' => null, 'major_course_codes' => [], 'notes' => 'No Friday afternoon; one paper a day']),
            $this->reply(['entries' => [['exam_course_id' => $a, 'room_ids' => [$room], 'starts_at' => '2026-10-16 14:00'], ['exam_course_id' => $b, 'room_ids' => [$room], 'starts_at' => '2026-10-16 14:00']], 'explanation' => 'first try']),
            $this->reply(['entries' => [['exam_course_id' => $a, 'room_ids' => [$room], 'starts_at' => '2026-10-14 09:00'], ['exam_course_id' => $b, 'room_ids' => [$room], 'starts_at' => '2026-10-15 09:00']], 'explanation' => 'Spread over two days.']),
        ]);
        $this->actingAs($f['admin'])->post('/ai/start', ['feature' => 'datesheet', 'exam_id' => $f['exam']->id, 'constraints' => 'No Friday afternoon, one paper a day'])->assertRedirect();
        $run = M\AiRun::first();
        $this->assertSame('needs_review', $run->status);
        $this->assertSame([], $run->result['errors']);
        $this->assertSame(2, $run->result['attempts']);
        $this->assertSame(0, M\ScheduleEntry::count(), 'nothing is saved before approval');
        $this->get('/admin/assistant?tab=actions&run='.$run->id)->assertOk()->assertSee('Spread over two days.');
        $this->post('/ai/runs/'.$run->id.'/approve')->assertRedirect();
        $this->assertSame(2, M\ScheduleEntry::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.datesheet.applied']);
    }

    public function test_datesheet_with_unresolved_errors_after_three_attempts_cannot_be_applied(): void
    {
        $f = $this->fixture();
        $this->secondPaper($f);
        $bad = $this->reply(['entries' => [['exam_course_id' => $f['ec']->id, 'room_ids' => [$f['room']->id], 'starts_at' => '2026-10-14 09:00']], 'explanation' => 'incomplete']);
        OpenAI::fake([$bad, $bad, $bad]);
        $this->actingAs($f['admin'])->post('/ai/start', ['feature' => 'datesheet', 'exam_id' => $f['exam']->id]);
        $run = M\AiRun::first();
        $this->assertSame(3, $run->result['attempts']);
        $this->assertStringContainsString('is not scheduled', implode('|', $run->result['errors']));
        $this->post('/ai/runs/'.$run->id.'/approve')->assertSessionHasErrors('ai');
        $this->assertSame(0, M\ScheduleEntry::count());
    }

    public function test_validator_catches_clashes_capacity_and_rules(): void
    {
        $f = $this->fixture();
        $ec2 = $this->secondPaper($f);
        $svc = app(AI\DateSheetService::class);
        $ctx = $svc->context($f['exam']);
        $entry = fn ($ec, $rooms, $at) => ['exam_course_id' => $ec, 'room_ids' => $rooms, 'starts_at' => $at];
        $errs = implode('|', $svc->validate(['entries' => [$entry($f['ec']->id, [$f['room']->id], '2026-10-14 09:00'), $entry($ec2->id, [$f['room']->id], '2026-10-14 10:00')]], $ctx));
        $this->assertStringContainsString('Room clash', $errs);
        $this->assertStringContainsString('Student clash', $errs);
        $rules = ['blocked_slots' => [['weekday' => 3, 'from_hour' => 8, 'to_hour' => 12]], 'max_papers_per_day_per_section' => 1];
        $errs = implode('|', $svc->validate(['entries' => [$entry($f['ec']->id, [$f['room2']->id], '2026-10-14 09:00'), $entry($ec2->id, [$f['room2']->id], '2026-10-14 13:00')]], $ctx, $rules));
        $this->assertStringContainsString('blocked time', $errs);
        $this->assertStringContainsString('max 1', $errs);
        $this->assertStringContainsString('outside the academic session', implode('|', $svc->validate(['entries' => [$entry($f['ec']->id, [$f['room2']->id], '2030-01-01 09:00'), $entry($ec2->id, [$f['room2']->id], '2026-10-15 09:00')]], $ctx)));
    }

    public function test_ai_seating_is_validated_and_saved_only_on_approval(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        app(\App\Services\SchedulingService::class)->schedule($f['ec'], $f['room2']->id, '2026-10-14 09:00');
        $entry = $f['ec']->fresh()->schedule;
        $s1 = $f['student']->id;
        $s2 = $f['other']->id;
        OpenAI::fake([
            $this->reply(['assignments' => [['student_id' => $s1, 'room_id' => $f['room2']->id, 'seat_number' => 1], ['student_id' => $s2, 'room_id' => $f['room2']->id, 'seat_number' => 2]], 'explanation' => 'adjacent']),
            $this->reply(['assignments' => [['student_id' => $s1, 'room_id' => $f['room2']->id, 'seat_number' => 1], ['student_id' => $s2, 'room_id' => $f['room2']->id, 'seat_number' => 3]], 'explanation' => 'Skipped a seat between classmates.']),
        ]);
        $this->post('/ai/start', ['feature' => 'seating', 'entry_id' => $entry->id]);
        $run = M\AiRun::first();
        $this->assertSame([], $run->result['errors']);
        $this->assertSame(0, M\SeatAssignment::count());
        $this->post('/ai/runs/'.$run->id.'/approve');
        $this->assertSame([1, 3], M\SeatAssignment::orderBy('seat_number')->pluck('seat_number')->all());
        $this->postJson('/workflow/seat-map/'.$entry->id.'/swap', ['student_id' => $s2, 'room_id' => $f['room2']->id, 'seat' => 2])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame([1, 3], M\SeatAssignment::orderBy('seat_number')->pluck('seat_number')->all());
        $this->postJson('/workflow/seat-map/'.$entry->id.'/swap', ['student_id' => $s2, 'room_id' => $f['room2']->id, 'seat' => 5])->assertOk()->assertJsonPath('ok', true);
        $this->get('/admin/seating-plan-overview?entry='.$entry->id)->assertOk()->assertSee('Room map')->assertSee('Print A4');
        $this->get('/seat-map/'.$entry->id.'/room/'.$f['room2']->id.'/print')->assertOk()->assertSee('FRONT');
    }

    public function test_students_see_own_seat_but_not_classmate_names_and_cannot_move_seats(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $entry = $f['ec']->fresh()->schedule;
        $data = app(\App\Http\Controllers\AiController::class)->seatData($f['student'], $entry);
        $this->assertTrue($data['locked']);
        $mine = collect($data['seats'])->firstWhere('mine', true);
        $this->assertSame('Student One', $mine['name']);
        $this->assertArrayNotHasKey('name', collect($data['seats'])->firstWhere('mine', false));
        $this->actingAs($f['student'])->get('/student/seating-plan')->assertOk()->assertSee('Room map')->assertSee('Your seat is outlined');
        $this->actingAs($f['student'])->postJson('/workflow/seat-map/'.$entry->id.'/swap', ['student_id' => $f['student']->id, 'room_id' => $f['room']->id, 'seat' => 2])->assertForbidden();
    }

    public function test_smart_import_maps_odd_columns_flags_rows_and_commits_after_confirmation(): void
    {
        $f = $this->fixture();
        $csv = "Pupil,Mail,Class,Reg No\nAda Lovelace,ada@example.test,BSIT 5-A,R-100\nBad Row,not-an-email,BSIT 5-A,R-101\nGrace,grace@example.test,BSIT 5,R-102\n";
        OpenAI::fake([$this->reply(['target' => 'students', 'header_rows' => 1, 'mappings' => [['column_index' => 0, 'field' => 'name'], ['column_index' => 1, 'field' => 'email'], ['column_index' => 2, 'field' => 'section'], ['column_index' => 3, 'field' => 'roll_number']], 'notes' => 'Mapped Pupil to name'])]);
        $this->actingAs($f['admin'])->post('/ai/start', ['feature' => 'import', 'file' => UploadedFile::fake()->createWithContent('list.csv', $csv)]);
        $run = M\AiRun::first();
        $this->assertEquals(['ok' => 1, 'uncertain' => 1, 'error' => 1], $run->result['counts']);
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.test']);
        $this->post('/ai/runs/'.$run->id.'/approve')->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test', 'role' => 'student']);
        $this->assertDatabaseHas('student_profiles', ['roll_number' => 'R-102']);
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);
        \Illuminate\Support\Facades\Storage::disk('local')->assertDirectoryEmpty('ai-imports');
    }

    public function test_report_runs_only_the_fixed_scoped_query_plan(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        app(\App\Services\ResultsService::class)->calculate($f['exam']);
        OpenAI::fake([$this->reply(['title' => 'Pass rate by course', 'metric' => 'pass_rate', 'group_by' => 'course', 'chart' => 'bar', 'examination_id' => null, 'department_code' => null, 'course_code' => null, 'summary' => 'Share of passing results'])]);
        $this->actingAs($f['admin']);
        $this->assertSame(2, app(\App\Services\ScopedQueries::class)->results($f['admin'])->count());
        $marksBefore = M\Mark::count();
        $this->actingAs($f['admin'])->post('/admin/ai/report', ['question' => 'pass rate by course; also DROP TABLE users'])->assertSessionHas('ai_report', fn ($r) => $r['rows'][0]['label'] === 'CS301' && $r['rows'][0]['value'] === 100.0);
        $this->assertSame($marksBefore, M\Mark::count());
        $this->assertDatabaseCount('users', 6);
    }

    public function test_duty_assignment_rejects_non_teachers_then_applies_valid_plan(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        app(\App\Services\SchedulingService::class)->schedule($f['ec'], $f['room2']->id, '2026-10-14 09:00');
        $entry = $f['ec']->fresh()->schedule;
        OpenAI::fake([
            $this->reply(['duties' => [['schedule_entry_id' => $entry->id, 'room_id' => $f['room2']->id, 'teacher_id' => $f['student']->id]], 'explanation' => 'x']),
            $this->reply(['duties' => [['schedule_entry_id' => $entry->id, 'room_id' => $f['room2']->id, 'teacher_id' => $f['teacher']->id]], 'explanation' => 'Lightest workload.']),
        ]);
        $this->post('/ai/start', ['feature' => 'duties', 'exam_id' => $f['exam']->id]);
        $run = M\AiRun::first();
        $this->assertSame([], $run->result['errors']);
        $this->post('/ai/runs/'.$run->id.'/approve');
        $this->assertDatabaseHas('duty_assignments', ['teacher_id' => $f['teacher']->id, 'schedule_entry_id' => $entry->id]);
    }

    public function test_announcement_draft_is_sent_only_after_admin_confirms(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        OpenAI::fake([$this->reply(['title' => 'Schedule published', 'message' => 'Your exams are scheduled.'])]);
        $this->actingAs($f['admin'])->post('/admin/ai/draft', ['kind' => 'schedule', 'exam_id' => $f['exam']->id])->assertSessionHas('ai_draft');
        $before = $f['student']->notifications()->count();
        $this->post('/admin/ai/send', ['kind' => 'schedule', 'exam_id' => $f['exam']->id, 'title' => 'Schedule published', 'message' => 'Your exams are scheduled.']);
        $this->assertSame($before + 1, $f['student']->notifications()->count());
    }

    public function test_transport_errors_are_retried_then_reported_safely(): void
    {
        $f = $this->fixture();
        OpenAI::fake([new \OpenAI\Exceptions\ErrorException(['message' => 'secret provider detail', 'type' => 'server_error', 'code' => null], new \GuzzleHttp\Psr7\Response(500)), $this->reply(['ok' => true])]);
        $this->actingAs($f['admin'])->post('/admin/ai-settings/test')->assertSessionHas('status');
        $this->assertSame(['error', 'ok'], M\AiUsage::orderBy('id')->pluck('status')->all());
    }

    public function test_non_admins_cannot_use_ai_routes(): void
    {
        $f = $this->fixture();
        foreach (['teacher', 'student'] as $r) {
            $this->actingAs($f[$r])->post('/ai/start', ['feature' => 'datesheet'])->assertForbidden();
            $this->get('/admin/assistant?tab=actions')->assertForbidden();
        }
    }

    public function test_teacher_import_is_limited_to_own_marks_courses(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $csv = "Reg,Marks\nTEST-0,80\nTEST-1,70\n";
        $map = ['target' => 'marks', 'header_rows' => 1, 'mappings' => [['column_index' => 0, 'field' => 'roll_number'], ['column_index' => 1, 'field' => 'score']], 'notes' => 'ok'];
        OpenAI::fake([$this->reply($map)]);
        $this->actingAs($f['teacher'])->post('/ai/start', ['feature' => 'datesheet', 'exam_id' => $f['exam']->id])->assertForbidden();
        $this->post('/ai/start', ['feature' => 'import', 'target' => 'students', 'file' => UploadedFile::fake()->createWithContent('a.csv', $csv)])->assertForbidden();
        $this->post('/ai/start', ['feature' => 'import', 'target' => 'marks', 'course_id' => $f['ec']->id, 'file' => UploadedFile::fake()->createWithContent('m.csv', $csv)])->assertRedirect();
        $run = M\AiRun::first();
        $this->assertSame(2, $run->result['counts']['ok']);
        $this->get('/teacher/courses?tab=import&run='.$run->id)->assertOk()->assertSee('Confirm import');
        $this->post('/ai/runs/'.$run->id.'/approve');
        $this->assertSame(2, M\Mark::count());
    }

    public function test_unavailable_duty_request_gets_an_ai_replacement_suggestion(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $duty = M\DutyAssignment::where('teacher_id', $f['teacher']->id)->first();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-12 09:00:00', 'Asia/Karachi'));
        OpenAI::fake([$this->reply(['candidate_id' => $f['replacement']->id, 'explanation' => 'Fewest duties that day.'])]);
        $this->actingAs($f['teacher'])->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'An unavoidable academic commitment'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.replacement.suggested']);
    }
}
