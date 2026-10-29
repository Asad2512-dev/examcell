<?php
// Existing fixture verifies that the selected database ends in _test before any writes.
require __DIR__.'/fixture.php';
$original=\App\Models\ExamCourse::with('examination')->firstOrFail();
$exam=\App\Models\Examination::create(['name'=>'UI draft examination','type'=>'final','academic_session_id'=>$original->examination->academic_session_id,'grading_scheme_id'=>$original->examination->grading_scheme_id]);
$course=\App\Models\ExamCourse::create(['examination_id'=>$exam->id,'course_id'=>$original->course_id,'section_id'=>$original->section_id,'duration_minutes'=>120,'maximum'=>'100','passing'=>'50']);
\App\Models\AssessmentComponent::create(['exam_course_id'=>$course->id,'name'=>'UI draft assessment','maximum'=>'100','weight'=>'100']);
echo 'Editable UI test examination created: '.$course->id."\n";
