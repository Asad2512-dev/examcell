<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services\AI\SeatingAiService;
use App\Services\SchedulingService;
use App\Services\SeatMapService;
use App\Support\RoomLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class SeatingGraphicsTest extends TestCase
{
    use Fixture,RefreshDatabase;

    /** A second class (section B, two students) sitting a different paper at the same time. */
    private function secondClass(array $f): array
    {
        $sectionB = M\Section::create(['department_id' => $f['dep']->id, 'semester_id' => $f['semester']->id, 'name' => 'BSIT 5-B']);
        $course = M\Course::create(['department_id' => $f['dep']->id, 'code' => 'CS399', 'name' => 'Compilers', 'credits' => '3.00']);
        $students = [];
        foreach (['Bea', 'Ben'] as $i => $name) {
            $u = M\User::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
            M\StudentProfile::create(['user_id' => $u->id, 'section_id' => $sectionB->id, 'roll_number' => 'B-'.$i]);
            M\Enrollment::create(['student_id' => $u->id, 'course_id' => $course->id, 'section_id' => $sectionB->id]);
            $students[] = $u;
        }
        M\TeacherAssignment::create(['teacher_id' => $f['replacement']->id, 'course_id' => $course->id, 'section_id' => $sectionB->id]);
        $ec = M\ExamCourse::create(['examination_id' => $f['exam']->id, 'course_id' => $course->id, 'section_id' => $sectionB->id, 'duration_minutes' => 120, 'maximum' => '100', 'passing' => '50']);
        M\AssessmentComponent::create(['exam_course_id' => $ec->id, 'name' => 'Final', 'maximum' => '100', 'weight' => '100']);

        return ['section' => $sectionB, 'ec' => $ec, 'students' => $students];
    }

    /** Both papers at 09:00 sharing a 2×4 room with an aisle after column 2. */
    private function sharedSlot(array $f): array
    {
        $b = $this->secondClass($f);
        $f['room2']->update(['seat_rows' => 2, 'seat_columns' => 4, 'aisles' => [2], 'disabled_seats' => [], 'capacity' => 8]);
        $this->actingAs($f['admin']);
        $s = app(SchedulingService::class);
        $a = $s->schedule($f['ec'], $f['room2']->id, '2026-10-14 09:00');
        $bEntry = $s->schedule($b['ec'], $f['room2']->id, '2026-10-14 09:00');

        return $b + ['a' => $a, 'b' => $bEntry];
    }

    public function test_room_layout_labels_aisles_disabled_seats_and_directions(): void
    {
        $l = new RoomLayout(3, 4, [2], ['B2'], 'right');
        $this->assertSame(11, $l->capacity());
        $this->assertSame(['A1', 'A2', 'A3', 'A4', 'B1', 'B3'], array_slice(array_column($l->positions(), 'label'), 0, 6));
        $this->assertSame(2, $l->rightOf(1));
        $this->assertNull($l->rightOf(2), 'aisle after column 2 breaks adjacency');
        $this->assertNull($l->rightOf(5), 'B1 → B2 is disabled');
        $this->assertSame($l->numberOf('B1'), $l->behind(1));
        $this->assertSame('AA1', RoomLayout::label(26, 0));
        $this->assertSame('2nd row from the front, 2nd seat from the door side', $l->directions($l->numberOf('B3')));
        $bad = [];
        $this->assertSame(['A1', 'C4'], RoomLayout::parseLabels('a1, C4 Z9', 3, 4, $bad));
        $this->assertSame(['Z9'], $bad);
        $legacy = RoomLayout::for(new M\Room(['name' => 'x', 'capacity' => 8, 'seat_columns' => 6]));
        $this->assertSame(8, $legacy->capacity());
        $this->assertNull($legacy->rightOf(6), 'unconfigured rooms keep the old 6-per-row rule');
    }

    public function test_layout_editor_sets_capacity_and_rejects_bad_input(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get('/admin/rooms')->assertOk()->assertSee('Room layout')->assertSee('Broken / unusable seats');
        $this->post('/admin/rooms/'.$f['room2']->id.'/layout', ['rows' => 5, 'cols' => 10, 'aisles' => '5', 'disabled' => 'A1, E10', 'accessible' => 'A2', 'door_side' => 'left'])->assertSessionHasNoErrors();
        $room = $f['room2']->fresh();
        $this->assertSame(48, $room->capacity);
        $this->assertSame([5], $room->aisles);
        $this->assertSame([1], $room->accessible_seats, 'A2 is seat 1 because A1 is disabled');
        $this->post('/admin/rooms/'.$f['room2']->id.'/layout', ['rows' => 2, 'cols' => 2, 'disabled' => 'Q9', 'door_side' => 'right'])->assertSessionHasErrors('disabled');
        $this->post('/admin/rooms/'.$f['room2']->id.'/layout', ['rows' => 2, 'cols' => 2, 'aisles' => '2', 'door_side' => 'right'])->assertSessionHasErrors('aisles');
        $this->post('/admin/rooms/'.$f['room2']->id.'/layout', ['rows' => 2, 'cols' => 2, 'accessible' => 'A1', 'disabled' => 'A1', 'door_side' => 'right'])->assertSessionHasErrors('accessible');
        $this->publish($f);
        $this->post('/admin/rooms/'.$f['room']->id.'/layout', ['rows' => 1, 'cols' => 1, 'door_side' => 'right'])->assertSessionHasErrors('rows');
        $this->actingAs($f['teacher'])->post('/admin/rooms/'.$f['room']->id.'/layout', ['rows' => 1, 'cols' => 4, 'door_side' => 'right'])->assertForbidden();
    }

    public function test_papers_starting_together_may_share_a_room_but_not_otherwise(): void
    {
        $f = $this->fixture();
        $b = $this->secondClass($f);
        $this->actingAs($f['admin']);
        $s = app(SchedulingService::class);
        $s->schedule($f['ec'], $f['room2']->id, '2026-10-14 09:00');
        $this->assertNotNull($s->schedule($b['ec'], $f['room2']->id, '2026-10-14 09:00'), '4 students fit 5 seats');
        try {
            $s->schedule($b['ec'], $f['room2']->id, '2026-10-14 10:00');
            $this->fail('A different start time must not share the room.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Room overlaps', json_encode($e->errors()));
        }
        $f['room2']->update(['capacity' => 3]);
        $this->assertNotEmpty(array_filter($s->entryConflicts($b['ec']->fresh()->schedule), fn ($m) => str_contains($m, 'Shared rooms cannot seat')));
    }

    public function test_slot_seating_mixes_classes_validates_layout_and_swaps_across_papers(): void
    {
        $f = $this->fixture();
        $x = $this->sharedSlot($f);
        $svc = app(SeatingAiService::class);
        [$a1, $a2] = [$f['student']->id, $f['other']->id];
        [$b1, $b2] = [$x['students'][0]->id, $x['students'][1]->id];
        $r = $f['room2']->id;
        $row = fn ($sid, $n) => ['student_id' => $sid, 'room_id' => $r, 'seat_number' => $n];
        $ctx = $svc->context($x['a']);
        $this->assertCount(2, $ctx['entries']);
        $this->assertCount(4, $ctx['students']);
        // A1 A2 | A3 A4 : same class in seats 1-2 is a clash; seats 2|3 are across the aisle so fine
        $bad = $svc->validate(['assignments' => [$row($a1, 1), $row($a2, 2), $row($b1, 3), $row($b2, 4)]], $ctx);
        $this->assertCount(2, $bad);
        $good = ['assignments' => [$row($a1, 1), $row($b1, 2), $row($a2, 3), $row($b2, 4)]];
        $this->assertSame([], $svc->validate($good, $ctx));
        $svc->apply($x['a'], $good, $f['admin']->id);
        $this->assertSame(2, $x['a']->fresh()->seatingPlan->seats()->count());
        $this->assertSame(2, $x['b']->fresh()->seatingPlan->seats()->count());
        $data = app(SeatMapService::class)->data($f['admin'], $x['a']->fresh());
        $this->assertSame(['students' => 4, 'seats_used' => 4, 'seats_total' => 8, 'rooms_used' => 1, 'classes' => 2, 'mixed_rooms' => 1, 'clashes' => 0, 'front_back' => 0, 'unseated' => 0, 'not_eligible' => 0], $data['summary']);
        $this->assertSame(['A1', 'A2', 'A3', 'A4'], collect($data['seats'])->sortBy('seat')->pluck('label')->all());
        // Swap across papers: A1 ↔ B1 is still mixed → allowed
        $this->postJson('/workflow/seat-map/'.$x['a']->id.'/swap', ['student_id' => $a1, 'room_id' => $r, 'seat' => 2])->assertOk();
        $this->assertSame(2, M\SeatAssignment::where('student_id', $a1)->value('seat_number'));
        // Move B2 next to B1 (B1 now in seat 1, seat 2 holds A1) → B2 into 2 puts B1/B2 side by side → rejected
        $this->postJson('/workflow/seat-map/'.$x['a']->id.'/swap', ['student_id' => $b2, 'room_id' => $r, 'seat' => 2])->assertStatus(422)->assertJsonPath('ok', false);
        // A second plan can't take a seat already used by the other paper in the slot
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\SeatingService::class)->allocate($x['b']->fresh(), $f['admin']->id, [$row($b1, 1), $row($b2, 2)]);
    }

    public function test_role_scoping_student_sees_only_self_teacher_sees_invigilated_rooms(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $entry = $f['ec']->fresh()->schedule;
        $svc = app(SeatMapService::class);
        $student = $svc->data($f['student'], $entry);
        $this->assertCount(1, $student['rooms']);
        $this->assertNull($student['summary']);
        $this->assertSame([], $student['sections']);
        $mine = collect($student['seats'])->firstWhere('mine', true);
        $this->assertSame('Student One', $mine['name']);
        foreach (collect($student['seats'])->where('mine', false) as $s) {
            $this->assertArrayNotHasKey('name', $s);
            $this->assertArrayNotHasKey('roll', $s);
            $this->assertArrayNotHasKey('section', $s);
        }
        $this->assertStringContainsString('row from the front', $student['directions']);
        $teacher = $svc->data($f['teacher'], $entry);
        $this->assertNotEmpty(collect($teacher['seats'])->pluck('name')->filter());
        $this->assertNotNull($teacher['summary']);
        $this->actingAs($f['replacement'])->get('/teacher/schedule?tab=seating')->assertOk()->assertSee('No seating to show yet');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->data($f['replacement'], $entry);
    }

    public function test_prints_room_plan_door_notice_and_attendance_for_staff_only(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $entry = $f['ec']->fresh()->schedule;
        $base = '/seat-map/'.$entry->id.'/room/'.$f['room']->id.'/print';
        $this->actingAs($f['admin']);
        $this->get($base.'?type=map')->assertOk()->assertSee('Room seating plan')->assertSee('Seat list')->assertSee('TEST-0')->assertSee('logo-mark.svg');
        $this->get($base.'?type=door')->assertOk()->assertSee('Door notice')->assertSee('TEST-1');
        $this->get($base.'?type=attendance')->assertOk()->assertSee('Signature')->assertSee('Student Two')->assertSee('A1');
        $this->get($base.'?type=poster')->assertNotFound();
        $html = $this->get('/admin/seating')->assertOk()->getContent();
        $this->assertStringNotContainsString('raw_block', $html, 'seat list must not leak template code');
        $this->assertMatchesRegularExpression('#<td>A[12]</td>#', $html, 'seat list shows row-letter labels');
        $this->actingAs($f['teacher'])->get($base.'?type=attendance')->assertOk();
        $this->actingAs($f['student'])->get($base.'?type=door')->assertForbidden();
    }

    public function test_map_survives_students_who_lost_eligibility_after_seating(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $entry = $f['ec']->fresh()->schedule;
        M\Enrollment::where('student_id', $f['other']->id)->update(['eligible' => false]);
        $data = app(SeatMapService::class)->data($f['admin'], $entry);
        $this->assertCount(2, $data['seats'], 'still shown on the map');
        $this->assertSame(1, $data['summary']['not_eligible']);
        $this->assertTrue(collect($data['seats'])->firstWhere('student', $f['other']->id)['ineligible']);
        $this->assertSame(1, $data['summary']['clashes'], 'class still comes from the seat\'s own paper');
        $this->actingAs($f['admin'])->get('/admin/seating')->assertOk()->assertSee('Seating plan');
    }
}
