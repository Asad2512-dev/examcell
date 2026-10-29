<?php

namespace App\Services\AI;

use App\Models as M;
use App\Notifications\WorkflowNotice;

class NotificationDraftService
{
    public function __construct(private AiClient $ai) {}

    /** Facts come from the database; the AI only words them. */
    public function facts(string $kind, M\Examination $exam): array
    {
        return match ($kind) {
            'schedule' => ['examination' => $exam->name, 'status' => $exam->status, 'papers' => M\ScheduleEntry::with('examCourse.course', 'room')->whereHas('examCourse', fn ($q) => $q->where('examination_id', $exam->id))->orderBy('starts_at')->get()->map(fn ($e) => $e->examCourse->course->code.' '.$e->starts_at->format('D d M H:i').' '.$e->room->name)->all()],
            'results' => ['examination' => $exam->name, 'batch_status' => $exam->batch?->status],
            'duties' => ['examination' => $exam->name, 'duties_assigned' => M\DutyAssignment::whereHas('scheduleEntry.examCourse', fn ($q) => $q->where('examination_id', $exam->id))->where('status', 'assigned')->count()],
        };
    }

    public function draft(string $kind, M\Examination $exam, int $actor): array
    {
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'message'], 'properties' => ['title' => ['type' => 'string'], 'message' => ['type' => 'string']]];
        $d = $this->ai->structured('notification.draft', 'Write a clear, polite announcement (title under 80 characters, message under 500) for the audience using ONLY the supplied facts. Never invent dates, rooms or marks.', json_encode(['kind' => $kind, 'facts' => $this->facts($kind, $exam)]), 'announcement', $schema, $actor);

        return ['kind' => $kind, 'exam_id' => $exam->id, 'title' => mb_substr($d['title'], 0, 120), 'message' => mb_substr($d['message'], 0, 800)];
    }

    public function audience(string $kind, M\Examination $exam)
    {
        if ($kind === 'duties') {
            return M\User::whereIn('id', M\DutyAssignment::whereHas('scheduleEntry.examCourse', fn ($q) => $q->where('examination_id', $exam->id))->pluck('teacher_id'))->get();
        }

        return M\User::whereIn('id', M\Enrollment::whereIn('section_id', $exam->courses()->pluck('section_id'))->where('eligible', true)->pluck('student_id'))->get();
    }

    public function send(string $kind, M\Examination $exam, string $title, string $message): int
    {
        $users = $this->audience($kind, $exam);
        $url = $kind === 'duties' ? '/teacher/invigilation-duties' : ($kind === 'results' ? '/student/my-results' : '/student/examination-schedule');
        $users->each(fn ($u) => $u->notify(new WorkflowNotice($title, $message, $url)));

        return $users->count();
    }
}
