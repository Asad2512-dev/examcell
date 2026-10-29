<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services as S;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixture;
use Tests\TestCase;

class FinalFlowTest extends TestCase
{
    use Fixture,RefreshDatabase;

    public function test_exact_48_hour_deadline_ownership_and_late_rejection(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $duty = M\DutyAssignment::where('teacher_id', $f['teacher']->id)->first();
        $this->travelTo(Carbon::parse('2026-10-12 09:00:00', 'Asia/Karachi'));
        $this->actingAs($f['replacement'])->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'An unavoidable academic commitment'])->assertForbidden();
        $this->actingAs($f['teacher'])->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'An unavoidable academic commitment'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('duty_requests', ['teacher_id' => $f['teacher']->id, 'status' => 'pending']);
        $this->assertSame('assigned', $duty->fresh()->status);
        $this->assertDatabaseCount('teacher_availabilities', 0);
        $this->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'A duplicate request should be blocked'])->assertSessionHasErrors('request');
        $req = M\DutyRequest::first();
        $this->actingAs($f['admin'])->post('/workflow/duty-request/'.$req->id.'/reject', ['reason' => 'The stated reason is not approved.'])->assertRedirect();
        $this->assertSame('assigned', $duty->fresh()->status);
        $this->travelTo(Carbon::parse('2026-10-12 09:00:01', 'Asia/Karachi'));
        $this->actingAs($f['teacher'])->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'One second after the deadline'])->assertSessionHasErrors('request');
        $this->assertDatabaseCount('duty_requests', 1);
    }

    public function test_reserve_swap_approval_revalidates_and_notifies_both_people(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $duty = M\DutyAssignment::where('teacher_id', $f['teacher']->id)->first();
        $this->actingAs($f['teacher'])->post('/workflow/duty/'.$duty->id.'/request', ['reason' => 'I have an unavoidable commitment'])->assertRedirect();
        $p = M\DutyReplacement::firstOrFail();
        $this->assertSame($f['replacement']->id, $p->candidate_id);
        $f['replacement']->update(['active' => false]);
        $this->actingAs($f['admin'])->post('/workflow/replacement/'.$p->id.'/approve')->assertSessionHasErrors('replacement');
        $this->assertSame('assigned', $duty->fresh()->status);
        $f['replacement']->update(['active' => true]);
        $this->post('/workflow/replacement/'.$p->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('replaced', $duty->fresh()->status);
        $this->assertSame('approved', $p->fresh()->request->status);
        $this->assertDatabaseHas('duty_assignments', ['teacher_id' => $f['replacement']->id, 'room_id' => $duty->room_id, 'reserve' => false, 'status' => 'assigned']);
        $this->assertGreaterThan(0, $f['replacement']->notifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'duty.replacement_approved']);
        $this->actingAs($f['teacher'])->get('/teacher/replacement-status')->assertOk()->assertSee('approved')->assertSee('Replacement');
        $this->actingAs($f['replacement'])->get('/teacher/invigilation-duties')->assertOk()->assertSee('CS301')->assertDontSee('I have an unavoidable commitment');
    }

    public function test_unresolved_request_can_be_nominated_after_reserve_staff_is_configured(): void
    {
        $f = $this->fixture();
        $f['replacement']->teacherProfile->update(['reserve' => false]);
        $this->publish($f);
        $d = M\DutyAssignment::where('teacher_id', $f['teacher']->id)->first();
        $this->actingAs($f['teacher'])->post('/workflow/duty/'.$d->id.'/request', ['reason' => 'Please arrange replacement duty cover'])->assertRedirect();
        $p = M\DutyReplacement::first();
        $this->assertSame('unresolved', $p->status);
        $this->assertSame('assigned', $d->fresh()->status);
        $this->actingAs($f['admin'])->post('/workflow/replacement/'.$p->id.'/nominate', ['candidate_id' => $f['replacement']->id])->assertSessionHasErrors('candidate_id');
        $f['replacement']->teacherProfile->update(['reserve' => true]);
        $this->post('/workflow/replacement/'.$p->id.'/nominate', ['candidate_id' => $f['replacement']->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/workflow/replacement/'.$p->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_standby_reserve_assignment_is_promoted_without_duplicate_duty(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $entry = $f['ec']->fresh()->schedule;
        $this->actingAs($f['admin']);
        $standby = app(S\DutyService::class)->assign($entry, $f['replacement']->id, true);
        $original = M\DutyAssignment::where('teacher_id', $f['teacher']->id)->first();
        $this->actingAs($f['teacher']);
        app(S\DutyService::class)->request($original, $f['teacher']->id, 'A medical appointment before this paper');
        $this->actingAs($f['admin']);
        app(S\DutyService::class)->approve(M\DutyReplacement::first(), $f['admin']->id);
        $this->assertFalse($standby->fresh()->reserve);
        $this->assertDatabaseCount('duty_assignments', 2);
    }

    public function test_multi_room_capacity_seating_room_specific_duties_and_student_lookup(): void
    {
        $f = $this->fixture();
        $f['room']->update(['capacity' => 1]);
        $f['room2']->update(['capacity' => 1]);
        $this->actingAs($f['admin']);
        $this->post('/workflow/schedule/'.$f['ec']->id, ['room_id' => $f['room']->id, 'extra_rooms' => [$f['room2']->id], 'starts_at' => '2026-10-14 09:00'])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $f['ec']->fresh()->schedule;
        $this->assertCount(2, $entry->allocatedRooms());
        app(S\SeatingService::class)->allocate($entry, $f['admin']->id);
        $seats = M\SeatAssignment::orderBy('student_id')->get();
        $this->assertSame([1, 1], $seats->pluck('seat_number')->all());
        $this->assertCount(2, $seats->pluck('room_id')->unique());
        app(S\DutyService::class)->assign($entry, $f['teacher']->id, false, $f['room']->id);
        app(S\DutyService::class)->assign($entry, $f['replacement']->id, false, $f['room2']->id);
        app(S\SchedulingService::class)->transition($f['exam'], 'approved');
        app(S\SchedulingService::class)->transition($f['exam'], 'published');
        $this->actingAs($f['student'])->get('/student/seating-plan')->assertOk()->assertSee('Room 1')->assertDontSee('Student Two');
        $this->actingAs($f['other'])->get('/student/seating-plan')->assertOk()->assertSee('Room 2')->assertDontSee('Student One');
        $this->actingAs($f['admin'])->post('/workflow/admit/'.$f['exam']->id, ['student_id' => $f['other']->id])->assertRedirect();
        $this->assertSame('Room 2', $entry->roomForStudent($f['other']->id));
    }

    public function test_generator_uses_combined_room_capacity_and_publication_blocks_uncovered_rooms(): void
    {
        $f = $this->fixture();
        $f['room']->update(['capacity' => 1]);
        $f['room2']->update(['capacity' => 1]);
        $this->actingAs($f['admin'])->post('/workflow/examination/'.$f['exam']->id.'/generate', ['starts_on' => '2026-10-14', 'days' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $f['ec']->fresh()->schedule;
        $this->assertCount(2, $entry->allocatedRooms());
        app(S\SchedulingService::class)->transition($f['exam'], 'approved');
        $this->post('/workflow/examination/'.$f['exam']->id.'/state', ['status' => 'published'])->assertSessionHasErrors('status');
        app(S\SeatingService::class)->allocate($entry, $f['admin']->id);
        app(S\DutyService::class)->assign($entry, $f['teacher']->id, false, $f['room']->id);
        $this->post('/workflow/examination/'.$f['exam']->id.'/state', ['status' => 'published'])->assertSessionHasErrors('status');
        $this->assertSame('approved', $f['exam']->fresh()->status);
    }

    public function test_forecast_is_permission_scoped_persisted_and_never_changes_official_results(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $batch = app(S\ResultsService::class)->calculate($f['exam']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $state) {
            app(S\ResultsService::class)->transition($batch, $state, $f['admin']->id);
        }
        $this->actingAs($f['student'])->post('/workflow/forecast', ['student_id' => $f['other']->id, 'course_id' => $f['course']->id])->assertRedirect()->assertSessionHas('forecast');
        $run = M\ForecastRun::first();
        $this->assertSame($f['student']->id, $run->student_id);
        $this->assertSame(1, $run->evidence['sample_size']);
        $this->assertSame(0, $run->evidence['lower']);
        $this->assertSame(100, $run->evidence['upper']);
        $this->assertDatabaseHas('calculated_results', ['student_id' => $f['student']->id, 'percentage' => '86.00']);
        $this->actingAs($f['replacement'])->post('/workflow/forecast', ['student_id' => $f['student']->id, 'course_id' => $f['course']->id])->assertSessionHasErrors('forecast');
    }

    public function test_forecast_fits_trend_and_retains_uncertainty(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        foreach ([60, 70, 80] as $i => $score) {
            $exam = $i === 0 ? $f['exam'] : $f['exam']->replicate();
            $exam->status = 'published';
            $exam->save();
            $ec = $i === 0 ? $f['ec'] : $f['ec']->replicate();
            $ec->examination_id = $exam->id;
            $ec->save();
            $batch = M\ResultBatch::create(['examination_id' => $exam->id, 'status' => 'published', 'published_at' => now()->copy()->addDays($i)]);
            M\CalculatedResult::create(['result_batch_id' => $batch->id, 'exam_course_id' => $ec->id, 'student_id' => $f['student']->id, 'percentage' => $score, 'grade' => 'B', 'grade_points' => 3, 'credits' => 3, 'quality_points' => 9, 'absent' => false, 'passed' => true]);
        }$this->actingAs($f['student']);
        $run = app(S\ForecastService::class)->forecast($f['student'], $f['student']->id, $f['course']->id);
        $this->assertSame(3, $run->evidence['sample_size']);
        $this->assertEquals(90, $run->evidence['predicted_percentage']);
        $this->assertEquals(85, $run->evidence['lower']);
        $this->assertEquals(95, $run->evidence['upper']);
        $this->assertDatabaseCount('calculated_results', 3);
    }

    public function test_signed_backup_restore_and_download_permissions(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $backup = app(S\BackupService::class)->create($f['admin']->id);
        $this->assertSame('completed', $backup->status);
        $this->assertNotNull($backup->verified_at);
        $path = Storage::path($backup->path);
        $manifest = app(S\BackupService::class)->verify($path);
        $this->assertSame('examcell-backup-v1', $manifest['format']);
        $this->actingAs($f['student'])->get('/download/backup/'.$backup->id)->assertForbidden();
        $this->actingAs($f['admin'])->get('/download/backup/'.$backup->id)->assertOk();
        $target = 'examcell_roundtrip_restore';
        config(['database.connections.restore_admin' => config('database.connections.mysql')]);
        DB::connection('restore_admin')->statement('DROP DATABASE IF EXISTS `'.$target.'`');
        try {
            $this->post('/workflow/backup/restore', ['archive' => UploadedFile::fake()->createWithContent('backup.zip', file_get_contents($path)), 'target' => $target, 'confirm' => 1])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(6, DB::connection('restore')->table('users')->count());
            $this->assertSame('CS301', DB::connection('restore')->table('courses')->value('code'));
            $this->assertDatabaseCount('users', 6);
        } finally {
            DB::disconnect('restore');
            DB::connection('restore_admin')->statement('DROP DATABASE IF EXISTS `'.$target.'`');
        }
        $copy = Storage::path('backups/tampered.zip');
        copy($path, $copy);
        $z = new \ZipArchive;
        $z->open($copy);
        $z->addFromString('database.sql', 'DROP DATABASE examcell_test;');
        $z->close();
        try {
            app(S\BackupService::class)->verify($copy);
            $this->fail('Tampered backup should be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
        } finally {
            unlink($copy);
        }
    }
}
