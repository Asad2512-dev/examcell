<?php

namespace Tests;

use App\Models as M;
use App\Services\DutyService;
use App\Services\MarksService;
use App\Services\SchedulingService;
use App\Services\SeatingService;

trait Fixture
{
    protected function fixture(): array
    {
        $admin = M\User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'TestPassword2026!', 'role' => 'admin']);
        $reviewer = M\User::create(['name' => 'Reviewer', 'email' => 'reviewer@example.test', 'password' => 'TestPassword2026!', 'role' => 'admin']);
        $teacher = M\User::create(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'TestPassword2026!', 'role' => 'teacher']);
        $replacement = M\User::create(['name' => 'Replacement', 'email' => 'replacement@example.test', 'password' => 'TestPassword2026!', 'role' => 'teacher']);
        $student = M\User::create(['name' => 'Student One', 'email' => 'one@example.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
        $other = M\User::create(['name' => 'Student Two', 'email' => 'two@example.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
        $dep = M\Department::create(['name' => 'Information Technology', 'code' => 'IT']);
        M\TeacherProfile::create(['teacher_id' => $replacement->id, 'department_id' => $dep->id, 'reserve' => true, 'max_duties_per_day' => 2]);
        $session = M\AcademicSession::create(['name' => 'Fall test', 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $semester = M\Semester::create(['academic_session_id' => $session->id, 'name' => 'Semester 5', 'number' => 5]);
        $section = M\Section::create(['department_id' => $dep->id, 'semester_id' => $semester->id, 'name' => 'BSIT 5-A']);
        $course = M\Course::create(['department_id' => $dep->id, 'code' => 'CS301', 'name' => 'Database Systems', 'credits' => '3.00']);
        foreach ([$student, $other] as $i => $u) {
            M\StudentProfile::create(['user_id' => $u->id, 'section_id' => $section->id, 'roll_number' => 'TEST-'.$i]);
            M\Enrollment::create(['student_id' => $u->id, 'course_id' => $course->id, 'section_id' => $section->id]);
        }
        M\TeacherAssignment::create(['teacher_id' => $teacher->id, 'course_id' => $course->id, 'section_id' => $section->id]);
        $scheme = M\GradingScheme::create(['name' => 'Test scheme only', 'status' => 'approved', 'rounding_places' => 2]);
        foreach ([[0, 'F', '0'], [50, 'D', '1'], [60, 'C', '2'], [70, 'B', '3'], [85, 'A', '4']] as [$min,$grade,$gp]) {
            M\GradeBoundary::create(['grading_scheme_id' => $scheme->id, 'minimum' => $min, 'grade' => $grade, 'grade_points' => $gp]);
        }
        $exam = M\Examination::create(['name' => 'Final test', 'type' => 'final', 'academic_session_id' => $session->id, 'grading_scheme_id' => $scheme->id, 'admit_enabled' => true]);
        $ec = M\ExamCourse::create(['examination_id' => $exam->id, 'course_id' => $course->id, 'section_id' => $section->id, 'duration_minutes' => 120, 'maximum' => '100', 'passing' => '50']);
        $component = M\AssessmentComponent::create(['exam_course_id' => $ec->id, 'name' => 'Final assessment', 'maximum' => '100', 'weight' => '100']);
        $room = M\Room::create(['name' => 'Room 1', 'capacity' => 2]);
        $room2 = M\Room::create(['name' => 'Room 2', 'capacity' => 5]);

        return compact('admin', 'reviewer', 'teacher', 'replacement', 'student', 'other', 'dep', 'session', 'semester', 'section', 'course', 'scheme', 'exam', 'ec', 'component', 'room', 'room2');
    }

    protected function publish(array $f): void
    {
        $this->actingAs($f['admin']);
        $scheduler = app(SchedulingService::class);
        $scheduler->schedule($f['ec'], $f['room']->id, '2026-10-14 09:00');
        $entry = $f['ec']->fresh()->schedule;
        if (! $entry->seatingPlan) {
            app(SeatingService::class)->allocate($entry, $f['admin']->id);
        }if (! $entry->duties()->where('reserve', false)->exists()) {
            app(DutyService::class)->assign($entry, $f['teacher']->id);
        }
        $scheduler->transition($f['exam'], 'approved');
        $scheduler->transition($f['exam'], 'published');
    }

    protected function submitted(array $f, array $scores = ['86', '74']): void
    {
        $this->actingAs($f['teacher']);
        $rows = [];
        foreach ([$f['student'], $f['other']] as $i => $u) {
            $rows[] = ['assessment_component_id' => $f['component']->id, 'student_id' => $u->id, 'score' => $scores[$i], 'absent' => false];
        }
        app(MarksService::class)->save($f['ec'], $rows, $f['teacher']->id, true);
    }
}
