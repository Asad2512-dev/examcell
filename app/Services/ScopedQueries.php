<?php

namespace App\Services;

use App\Models as M;

class ScopedQueries
{
    public function courses(M\User $u)
    {
        $q = M\ExamCourse::query();
        if ($u->role === 'teacher') {
            $q->whereExists(function ($s) use ($u) {
                $s->selectRaw('1')->from('teacher_assignments')->whereColumn('teacher_assignments.course_id', 'exam_courses.course_id')->whereColumn('teacher_assignments.section_id', 'exam_courses.section_id')->where('teacher_id', $u->id);
            });
        }
        if ($u->role === 'student') {
            $q->whereHas('examination', fn ($s) => $s->where('status', 'published'))->whereExists(function ($s) use ($u) {
                $s->selectRaw('1')->from('enrollments')->whereColumn('enrollments.course_id', 'exam_courses.course_id')->whereColumn('enrollments.section_id', 'exam_courses.section_id')->where('student_id', $u->id)->where('eligible', true);
            });
        }

        return app(AcademicContext::class)->apply($q, $u);
    }

    public function roster(M\User $u)
    {
        $q = M\Enrollment::with('student.profile', 'course', 'section');
        if ($u->role === 'student') {
            return $q->where('student_id', $u->id);
        }
        if ($u->role === 'teacher') {
            $q->whereExists(function ($s) use ($u) {
                $s->selectRaw('1')->from('teacher_assignments')->whereColumn('teacher_assignments.course_id', 'enrollments.course_id')->whereColumn('teacher_assignments.section_id', 'enrollments.section_id')->where('teacher_id', $u->id);
            });
        }

        return app(AcademicContext::class)->apply($q, $u);
    }

    public function results(M\User $u)
    {
        $q = M\CalculatedResult::with('examCourse.course', 'student.profile', 'batch.examination');
        if ($u->role === 'student') {
            $q->where('student_id', $u->id)->whereHas('batch', fn ($b) => $b->where('status', 'published'));
        }
        if ($u->role === 'teacher') {
            $q->whereIn('exam_course_id', $this->courses($u)->select('exam_courses.id'));
        }

        return app(AcademicContext::class)->apply($q, $u);
    }

    public function schedules(M\User $u)
    {
        return M\ScheduleEntry::with('examCourse.course', 'room', 'rooms')->whereIn('exam_course_id', $this->courses($u)->select('exam_courses.id'));
    }

    public function seats(M\User $u)
    {
        $q = M\SeatAssignment::with('student.profile', 'room', 'seatingPlan.scheduleEntry.room', 'seatingPlan.scheduleEntry.examCourse.course');
        if ($u->role === 'student') {
            $q->where('student_id', $u->id)->whereHas('seatingPlan.scheduleEntry.examCourse.examination', fn ($e) => $e->where('status', 'published'));
        }
        if ($u->role === 'teacher') {
            $q->whereHas('seatingPlan.scheduleEntry', fn ($e) => $e->whereIn('exam_course_id', $this->courses($u)->select('exam_courses.id')));
        }

        return app(AcademicContext::class)->apply($q, $u);
    }

    public function duties(M\User $u)
    {
        return app(AcademicContext::class)->apply(M\DutyAssignment::with('teacher', 'room', 'scheduleEntry.room', 'scheduleEntry.examCourse.course')->when($u->role === 'teacher', fn ($q) => $q->where('teacher_id', $u->id)), $u);
    }
}
