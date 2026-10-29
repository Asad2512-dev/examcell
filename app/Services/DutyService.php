<?php

namespace App\Services;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException as V;

class DutyService
{
    public function blocked(int $teacher, M\ScheduleEntry $entry, ?int $ignore = null, ?int $proposalIgnore = null): bool
    {
        if (! M\User::whereKey($teacher)->where('role', 'teacher')->where('active', true)->exists()) {
            return true;
        }
        if (M\TeacherAvailability::where('teacher_id', $teacher)->where('available', false)->where('starts_at', '<', $entry->ends_at)->where('ends_at', '>', $entry->starts_at)->exists()) {
            return true;
        }
        $duties = M\DutyAssignment::where('teacher_id', $teacher)->where('status', 'assigned')->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->whereHas('scheduleEntry.examCourse.examination', fn ($q) => $q->where('status', '!=', 'archived'));
        if ((clone $duties)->whereHas('scheduleEntry', fn ($q) => $q->where('starts_at', '<', $entry->ends_at)->where('ends_at', '>', $entry->starts_at))->exists()) {
            return true;
        }
        $limit = M\TeacherProfile::where('teacher_id', $teacher)->value('max_duties_per_day') ?? config('examcell.max_duties_per_day', 2);
        if ((clone $duties)->whereHas('scheduleEntry', fn ($q) => $q->whereDate('starts_at', $entry->starts_at->toDateString()))->count() >= $limit) {
            return true;
        }

        return M\DutyReplacement::where('candidate_id', $teacher)->where('status', 'proposed')->when($proposalIgnore, fn ($q) => $q->where('id', '!=', $proposalIgnore))->whereHas('duty.scheduleEntry', fn ($q) => $q->where('starts_at', '<', $entry->ends_at)->where('ends_at', '>', $entry->starts_at))->exists();
    }

    public function candidates(M\ScheduleEntry $entry, ?int $proposalIgnore = null): Collection
    {
        $department = $entry->examCourse->section->department_id;

        return M\User::where('role', 'teacher')->where('active', true)->whereHas('teacherProfile', fn ($q) => $q->where('reserve', true)->where('department_id', $department))->with('teacherProfile')->get()->filter(function ($u) use ($entry, $proposalIgnore) {
            $standby = M\DutyAssignment::where('teacher_id', $u->id)->where('schedule_entry_id', $entry->id)->where('reserve', true)->where('status', 'assigned')->first();

            return ! $this->blocked($u->id, $entry, $standby?->id, $proposalIgnore);
        })->sortBy(fn ($u) => sprintf('%06d:%09d', M\DutyAssignment::where('teacher_id', $u->id)->where('status', 'assigned')->count(), $u->id))->values();
    }

    public function assign(M\ScheduleEntry $entry, int $teacher, bool $reserve = false, ?int $room = null): M\DutyAssignment
    {
        return DB::transaction(function () use ($entry, $teacher, $reserve, $room) {
            M\User::where('role', 'teacher')->orderBy('id')->lockForUpdate()->get();
            if ($entry->examCourse->examination->status === 'archived' || $entry->starts_at->lte(now())) {
                throw V::withMessages(['duty' => 'Archived or past paper duties are historical records.']);
            }
            $room ??= $entry->room_id;
            if (! $entry->allocatedRooms()->contains('id', $room)) {
                throw V::withMessages(['room_id' => 'Select a room assigned to this paper.']);
            }if ($this->blocked($teacher, $entry)) {
                throw V::withMessages(['teacher_id' => 'Teacher inactive, unavailable, at daily workload limit, or already committed to an overlapping duty/proposal.']);
            }$d = M\DutyAssignment::create(['schedule_entry_id' => $entry->id, 'teacher_id' => $teacher, 'room_id' => $room, 'reserve' => $reserve]);
            app(AuditService::class)->record('duty.assigned', $d);
            $d->teacher->notify(new WorkflowNotice('Duty assigned', 'Paper '.$entry->examCourse->course->code.' at '.$entry->starts_at->format('d M H:i').'.', '/teacher/invigilation-duties'));

            return $d;
        });
    }

    public function request(M\DutyAssignment $d, int $teacher, string $reason): M\DutyRequest
    {
        return DB::transaction(function () use ($d, $teacher, $reason) {
            M\User::where('role', 'teacher')->orderBy('id')->lockForUpdate()->get();
            $d = M\DutyAssignment::with('scheduleEntry.examCourse.examination')->lockForUpdate()->findOrFail($d->id);
            if ($d->teacher_id !== $teacher) {
                abort(403);
            }if ($d->status !== 'assigned' || $d->scheduleEntry->examCourse->examination->status === 'archived') {
                throw V::withMessages(['request' => 'Only a current assigned duty can be requested.']);
            }$deadline = $d->scheduleEntry->starts_at->copy()->subHours(48);
            if (now()->gt($deadline)) {
                throw V::withMessages(['request' => 'Requests must be submitted at least 48 hours before the paper. Deadline: '.$deadline->format('d M Y H:i').' Asia/Karachi.']);
            }if (M\DutyRequest::where('duty_assignment_id', $d->id)->where('status', 'pending')->exists()) {
                throw V::withMessages(['request' => 'A pending request already exists for this duty.']);
            }$req = M\DutyRequest::create(['duty_assignment_id' => $d->id, 'teacher_id' => $teacher, 'reason' => $reason, 'requested_at' => now(), 'deadline_at' => $deadline, 'status' => 'pending']);
            $candidate = $this->candidates($d->scheduleEntry)->where('id', '!=', $teacher)->first();
            M\DutyReplacement::create(['duty_assignment_id' => $d->id, 'duty_request_id' => $req->id, 'candidate_id' => $candidate?->id, 'status' => $candidate ? 'proposed' : 'unresolved', 'reason' => $reason]);
            if ($candidate) {
                $repId = M\DutyReplacement::where('duty_request_id', $req->id)->value('id');
                DB::afterCommit(fn () => $this->aiPick($repId, $teacher));
            }
            app(AuditService::class)->record('duty.requested', $req, ['deadline' => $deadline->toDateTimeString()]);
            M\User::where('role', 'admin')->where('active', true)->get()->each(fn ($u) => $u->notify(new WorkflowNotice('48-hour duty request', 'A teacher requested replacement; review the reserve-staff proposal.', '/admin/duty-replacements')));
            $d->teacher->notify(new WorkflowNotice('Duty request submitted', 'Your current duty remains assigned until reserve cover is approved.', '/teacher/replacement-status'));

            return $req;
        });
    }

    public function unavailable(int $teacher, string $start, string $end, string $reason): array
    {
        $start = Carbon::parse($start, config('app.timezone'))->setTimezone(config('app.timezone'));
        $end = Carbon::parse($end, config('app.timezone'))->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($teacher, $start, $end, $reason) {
            $duties = M\DutyAssignment::where('teacher_id', $teacher)->where('status', 'assigned')->whereHas('scheduleEntry', fn ($q) => $q->where('starts_at', '<', $end)->where('ends_at', '>', $start))->get();
            $proposals = [];
            foreach ($duties as $d) {
                $proposals[] = $this->request($d, $teacher, $reason)->proposal;
            }
            if ($duties->isEmpty()) {
                if ($start->lt(now()->addHours(48))) {
                    throw V::withMessages(['starts_at' => 'Availability changes require 48 hours advance notice.']);
                }$a = M\TeacherAvailability::create(['teacher_id' => $teacher, 'starts_at' => $start, 'ends_at' => $end, 'available' => false, 'reason' => $reason]);
                app(AuditService::class)->record('availability.recorded', $a);
            }

            return $proposals;
        });
    }

    public function nominate(M\DutyReplacement $p, int $candidate, int $actor): void
    {
        DB::transaction(function () use ($p, $candidate, $actor) {
            M\User::where('role', 'teacher')->orderBy('id')->lockForUpdate()->get();
            $p = M\DutyReplacement::lockForUpdate()->findOrFail($p->id);
            if (! in_array($p->status, ['proposed', 'unresolved']) || $p->request?->status !== 'pending') {
                throw V::withMessages(['candidate_id' => 'Only a pending request can be nominated.']);
            }if (! $this->candidates($p->duty->scheduleEntry, $p->id)->contains('id', $candidate) || $candidate === $p->duty->teacher_id) {
                throw V::withMessages(['candidate_id' => 'Select eligible reserve staff in this department.']);
            }$before = $p->candidate_id;
            $p->update(['candidate_id' => $candidate, 'status' => 'proposed']);
            app(AuditService::class)->record('duty.reserve_nominated', $p, ['previous_candidate' => $before, 'candidate' => $candidate, 'actor' => $actor]);
        });
    }

    public function approve(M\DutyReplacement $p, int $actor): void
    {
        DB::transaction(function () use ($p, $actor) {
            M\User::where('role', 'teacher')->orderBy('id')->lockForUpdate()->get();
            $p = M\DutyReplacement::lockForUpdate()->findOrFail($p->id);
            $d = M\DutyAssignment::with('scheduleEntry')->lockForUpdate()->findOrFail($p->duty_assignment_id);
            $req = $p->request;
            if ($p->status !== 'proposed' || ! in_array($d->status, ['assigned', 'unavailable']) || ! $p->candidate_id || ($req && $req->status !== 'pending')) {
                throw V::withMessages(['replacement' => 'Only a pending, nominated replacement can be approved.']);
            }if ($d->scheduleEntry->starts_at->lte(now())) {
                throw V::withMessages(['replacement' => 'This paper has started; duty history cannot be reassigned.']);
            }if (! $this->candidates($d->scheduleEntry, $p->id)->contains('id', $p->candidate_id)) {
                throw V::withMessages(['replacement' => 'Reserve candidate is no longer eligible. Nominate available reserve staff.']);
            }
            $standby = M\DutyAssignment::where('teacher_id', $p->candidate_id)->where('schedule_entry_id', $d->schedule_entry_id)->where('reserve', true)->where('status', 'assigned')->first();
            if ($standby) {
                $standby->update(['reserve' => $d->reserve, 'room_id' => $d->room_id]);
                $replacement = $standby;
            } else {
                $replacement = M\DutyAssignment::create(['schedule_entry_id' => $d->schedule_entry_id, 'teacher_id' => $p->candidate_id, 'room_id' => $d->room_id ?? $d->scheduleEntry->room_id, 'reserve' => $d->reserve]);
            }$d->update(['status' => 'replaced']);
            $p->update(['status' => 'approved', 'approved_by' => $actor]);
            if ($req) {
                $req->update(['status' => 'approved', 'decided_by' => $actor, 'decision_reason' => 'Reserve cover approved.']);
                M\TeacherAvailability::create(['teacher_id' => $d->teacher_id, 'starts_at' => $d->scheduleEntry->starts_at, 'ends_at' => $d->scheduleEntry->ends_at, 'available' => false, 'reason' => $req->reason]);
            }app(AuditService::class)->record('duty.replacement_approved', $p, ['previous_teacher' => $d->teacher_id, 'replacement_id' => $replacement->id, 'reason' => $p->reason]);
            $d->teacher->notify(new WorkflowNotice('Duty request approved', 'Your paper duty has been covered by reserve staff.', '/teacher/replacement-status'));
            $replacement->teacher->notify(new WorkflowNotice('Replacement duty assigned', 'Cover paper '.$d->scheduleEntry->examCourse->course->code.' at '.$d->scheduleEntry->starts_at->format('d M H:i').'.', '/teacher/invigilation-duties'));
        });
    }

    public function reject(M\DutyRequest $req, int $actor, string $reason): void
    {
        DB::transaction(function () use ($req, $actor, $reason) {
            $req = M\DutyRequest::lockForUpdate()->findOrFail($req->id);
            if ($req->status !== 'pending') {
                throw V::withMessages(['request' => 'Request already decided.']);
            }$req->update(['status' => 'rejected', 'decided_by' => $actor, 'decision_reason' => $reason]);
            $req->proposal?->update(['status' => 'rejected', 'approved_by' => $actor]);
            app(AuditService::class)->record('duty.request_rejected', $req, ['reason' => $reason]);
            $req->teacher->notify(new WorkflowNotice('Duty request rejected', $reason.' Your original duty remains assigned.', '/teacher/replacement-status'));
        });
    }

    /** Best-effort: AI picks and explains the replacement among eligible candidates; the admin still approves. */
    private function aiPick(int $replacementId, int $actor): void
    {
        try {
            if (! app(\App\Services\AI\AiClient::class)->configured()) {
                return;
            }
            $p = M\DutyReplacement::with('duty.scheduleEntry.examCourse.course')->findOrFail($replacementId);
            $pick = app(\App\Services\AI\DutyAiService::class)->suggestReplacement($p, $actor);
            if ($pick['candidate_id'] && $pick['candidate_id'] !== $p->candidate_id) {
                $this->nominate($p, $pick['candidate_id'], $actor);
            }
            app(AuditService::class)->record('ai.replacement.suggested', $p, ['candidate' => $pick['candidate_id'], 'explanation' => $pick['explanation']]);
            M\User::where('role', 'admin')->where('active', true)->get()->each(fn ($u) => $u->notify(new WorkflowNotice('AI replacement suggestion', $pick['explanation'], '/admin/duty-replacements')));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
