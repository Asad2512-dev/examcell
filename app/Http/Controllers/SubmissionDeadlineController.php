<?php

namespace App\Http\Controllers;

use App\Models\ExamCourse;
use App\Models\TeacherAssignment;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionDeadlineController
{
    public function update(Request $request, ExamCourse $course)
    {
        $data = $request->validate([
            'marks_due_at' => 'nullable|date',
            'teacher_id' => 'nullable|integer|exists:users,id',
        ]);
        $teacherId = $data['teacher_id'] ?? null;
        if ($teacherId && ! TeacherAssignment::where('teacher_id', $teacherId)->where('course_id', $course->course_id)->where('section_id', $course->section_id)->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Choose a teacher assigned to this course and section.']);
        }
        $deadline = empty($data['marks_due_at']) ? null : CarbonImmutable::parse($data['marks_due_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
        DB::transaction(function () use ($course, $teacherId, $deadline) {
            $locked = ExamCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($teacherId) {
                $previous = $locked->submissionDeadlines()->where('teacher_id', $teacherId)->first();
                $before = $previous?->marks_due_at?->toIso8601String();
                $deadline ? $locked->submissionDeadlines()->updateOrCreate(['teacher_id' => $teacherId], ['marks_due_at' => $deadline])
                    : $locked->submissionDeadlines()->where('teacher_id', $teacherId)->delete();
            } else {
                $before = $locked->marks_due_at?->toIso8601String();
                $locked->update(['marks_due_at' => $deadline]);
            }
            app(AuditService::class)->record('marks.deadline_updated', $locked, ['teacher_id' => $teacherId, 'before' => $before, 'marks_due_at' => $deadline?->toIso8601String()]);
        });

        return $request->expectsJson()
            ? response()->json(['message' => 'Submission deadline updated.', 'marks_due_at' => $deadline?->toIso8601String()])
            : back()->with('status', 'Submission deadline updated.');
    }
}
