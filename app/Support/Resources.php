<?php

namespace App\Support;

use App\Models as M;

class Resources
{
    public static function definitions(): array
    {
        return [
            'departments' => [M\Department::class, ['name' => 'required|string|max:120', 'code' => 'required|string|max:20', 'active' => 'boolean']],
            'sessions' => [M\AcademicSession::class, ['name' => 'required|string|max:120', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after:starts_on', 'active' => 'boolean']],
            'semesters' => [M\Semester::class, ['academic_session_id' => 'required|exists:academic_sessions,id', 'name' => 'required|string|max:80', 'number' => 'required|integer|between:1,12', 'active' => 'boolean']],
            'sections' => [M\Section::class, ['department_id' => 'required|exists:departments,id', 'semester_id' => 'required|exists:semesters,id', 'name' => 'required|string|max:80', 'active' => 'boolean']],
            'courses' => [M\Course::class, ['department_id' => 'required|exists:departments,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:120', 'credits' => 'required|numeric|between:0.01,30', 'active' => 'boolean']],
            'users' => [M\User::class, ['name' => 'required|string|max:120', 'email' => 'required|email|max:254', 'role' => 'required|in:teacher,student,admin', 'password' => 'nullable|string|min:12|max:255', 'active' => 'boolean']],
            'students' => [M\StudentProfile::class, ['user_id' => 'required|exists:users,id', 'section_id' => 'required|exists:sections,id', 'roll_number' => 'required|string|max:40', 'eligible' => 'boolean']],
            'enrollments' => [M\Enrollment::class, ['student_id' => 'required|exists:users,id', 'course_id' => 'required|exists:courses,id', 'section_id' => 'required|exists:sections,id', 'eligible' => 'boolean']],
            'teacher-assignments' => [M\TeacherAssignment::class, ['teacher_id' => 'required|exists:users,id', 'course_id' => 'required|exists:courses,id', 'section_id' => 'required|exists:sections,id']],
            'teacher-profiles' => [M\TeacherProfile::class, ['teacher_id' => 'required|exists:users,id', 'department_id' => 'required|exists:departments,id', 'reserve' => 'boolean', 'max_duties_per_day' => 'required|integer|between:1,10']],
            'grading' => [M\GradingScheme::class, ['name' => 'required|string|max:120', 'status' => 'required|in:draft,approved,archived', 'rounding_places' => 'required|integer|between:0,2']],
            'boundaries' => [M\GradeBoundary::class, ['grading_scheme_id' => 'required|exists:grading_schemes,id', 'grade' => 'required|string|max:8', 'minimum' => 'required|numeric|between:0,100', 'grade_points' => 'required|numeric|between:0,4']],
            'examinations' => [M\Examination::class, ['name' => 'required|string|max:120', 'type' => 'required|in:midterm,final,quiz,assignment', 'academic_session_id' => 'required|exists:academic_sessions,id', 'grading_scheme_id' => 'required|exists:grading_schemes,id', 'admit_enabled' => 'boolean']],
            'exam-courses' => [M\ExamCourse::class, ['examination_id' => 'required|exists:examinations,id', 'course_id' => 'required|exists:courses,id', 'section_id' => 'required|exists:sections,id', 'duration_minutes' => 'required|integer|between:15,480', 'maximum' => 'required|numeric|between:1,10000', 'passing' => 'required|numeric|min:0|lte:maximum']],
            'components' => [M\AssessmentComponent::class, ['exam_course_id' => 'required|exists:exam_courses,id', 'name' => 'required|string|max:80', 'maximum' => 'required|numeric|between:0.01,10000', 'weight' => 'required|numeric|between:0.01,100']],
            'rooms' => [M\Room::class, ['name' => 'required|string|max:80', 'capacity' => 'required|integer|between:1,2000', 'active' => 'boolean']],
            'room-unavailability' => [M\RoomUnavailability::class, ['room_id' => 'required|exists:rooms,id', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'required|string|min:5|max:1000']],
        ];
    }

    public static function get(string $key): array
    {
        abort_unless(isset(self::definitions()[$key]), 404);

        return self::definitions()[$key];
    }

    public static function fields(string $key): array
    {
        return self::get($key)[1];
    }

    public static function options(string $field): array
    {
        return match ($field) {
            'department_id' => M\Department::pluck('name', 'id')->all(),'academic_session_id' => M\AcademicSession::pluck('name', 'id')->all(),'semester_id' => app(\App\Services\AcademicContext::class)->apply(M\Semester::query())->pluck('name', 'id')->all(),'section_id' => app(\App\Services\AcademicContext::class)->apply(M\Section::query())->pluck('name', 'id')->all(),'course_id' => M\Course::selectRaw("id, CONCAT(code,' — ',name) AS label")->pluck('label', 'id')->all(),'grading_scheme_id' => M\GradingScheme::pluck('name', 'id')->all(),'examination_id' => app(\App\Services\AcademicContext::class)->apply(M\Examination::query())->pluck('name', 'id')->all(),'exam_course_id' => app(\App\Services\AcademicContext::class)->apply(M\ExamCourse::query())->with('course')->get()->mapWithKeys(fn ($c) => [$c->id => $c->course->code.' / exam '.$c->examination_id.' / section '.$c->section_id])->all(),'user_id','student_id' => M\User::where('role', 'student')->whereIn('id', app(\App\Services\AcademicContext::class)->apply(M\StudentProfile::query())->select('user_id'))->pluck('name', 'id')->all(),'teacher_id' => M\User::where('role', 'teacher')->pluck('name', 'id')->all(),'room_id' => M\Room::pluck('name', 'id')->all(),default => []
        };
    }
}
