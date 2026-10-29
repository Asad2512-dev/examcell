<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCourse extends Model
{
    protected $table = 'exam_courses';

    protected $guarded = ['id'];

    protected $casts = ['maximum' => 'decimal:2', 'passing' => 'decimal:2', 'marks_due_at' => 'immutable_datetime'];

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function components()
    {
        return $this->hasMany(AssessmentComponent::class);
    }

    public function schedule()
    {
        return $this->hasOne(ScheduleEntry::class);
    }

    public function submission()
    {
        return $this->hasOne(MarkSubmission::class);
    }

    public function submissionDeadlines()
    {
        return $this->hasMany(MarksSubmissionDeadline::class);
    }

    public function submissionDeadlineFor(User $teacher): ?\Carbon\CarbonInterface
    {
        $override = $this->relationLoaded('submissionDeadlines')
            ? $this->submissionDeadlines->firstWhere('teacher_id', $teacher->id)
            : $this->submissionDeadlines()->where('teacher_id', $teacher->id)->first();

        return $override?->marks_due_at ?? $this->marks_due_at;
    }

    public function eligible()
    {
        return Enrollment::where('course_id', $this->course_id)->where('section_id', $this->section_id)->where('eligible', true)->whereHas('student', fn ($q) => $q->when($this->examination->status === 'draft', fn ($q) => $q->where('active', true))->whereHas('profile', fn ($p) => $p->where('eligible', true)));
    }
}
