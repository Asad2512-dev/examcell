<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class RoleFlowTest extends TestCase
{
    use Fixture, RefreshDatabase;

    public function test_teacher_course_links_keep_the_correct_examination_and_profile_has_availability(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['teacher'])->get('/teacher/courses')->assertOk()->assertSee('/teacher/courses?tab=marks&amp;course_id='.$f['ec']->id, false);
        $this->get('/teacher/profile')->assertOk()->assertSee('My profile')->assertSee('Tell us when you are unavailable')->assertDontSee('My invigilation duties');
        $this->get('/teacher/duties')->assertOk()->assertSee('My invigilation duties')->assertDontSee('Assign paper duty');
    }

    public function test_returned_teacher_work_opens_the_marks_editor_and_return_reason(): void
    {
        $f = $this->fixture();
        M\MarkSubmission::create(['exam_course_id' => $f['ec']->id, 'teacher_id' => $f['teacher']->id, 'status' => 'returned', 'return_reason' => 'Please check the missing component marks.']);
        $this->actingAs($f['teacher'])->get('/teacher/courses?tab=submissions&view=returned-marks-correction&course_id='.$f['ec']->id)
            ->assertOk()->assertSee('Corrections requested')->assertSee('Please check the missing component marks.')
            ->assertSee('action="/workflow/marks/'.$f['ec']->id.'"', false)->assertSee('Save draft');
    }

    public function test_student_semester_filter_stays_scoped_and_rechecking_is_tracked(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $batch = app(S\ResultsService::class)->calculate($f['exam']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $state) {
            app(S\ResultsService::class)->transition($batch, $state, $f['admin']->id);
        }
        $this->actingAs($f['student']);
        $response = $this->get('/student/results?semester_id='.$f['semester']->id)->assertOk();
        $this->assertCount(1, $response->viewData('records'));
        $this->assertSame($f['student']->id, $response->viewData('records')->first()->student_id);
        $this->get('/student/results?semester_id=999999')->assertOk()->assertSee('No results match these filters')->assertViewHas('records', fn ($records) => $records->isEmpty());
        $result = M\CalculatedResult::where('student_id', $f['student']->id)->firstOrFail();
        app(S\ResultsService::class)->recheck($result, $f['student']->id, 'Please review this result.');
        $this->get('/student/results')->assertOk()->assertSee('Track rechecking request');
        $this->get('/student/rechecking')->assertOk()->assertSee('Please review this result.');
    }
}
