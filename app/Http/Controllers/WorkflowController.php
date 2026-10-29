<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarksRequest;
use App\Jobs\GenerateAdmitCards;
use App\Models as M;
use App\Services as S;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException as V;

class WorkflowController
{
    private function done(Request $r, string $message = 'Operation completed.')
    {
        return $r->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    public function schedule(Request $r, M\ExamCourse $course)
    {
        $d = $r->validate(['room_id' => 'required|exists:rooms,id', 'starts_at' => 'required|date', 'extra_rooms' => 'sometimes|array', 'extra_rooms.*' => 'integer|distinct|exists:rooms,id']);
        app(S\SchedulingService::class)->schedule($course, $d['room_id'], $d['starts_at'], $d['extra_rooms'] ?? []);

        return $this->done($r, 'Schedule saved and conflicts checked.');
    }

    public function generateSchedule(Request $r, M\Examination $exam)
    {
        $d = $r->validate(['starts_on' => 'required|date', 'days' => 'required|integer|between:1,90']);
        $rows = app(S\SchedulingService::class)->generate($exam, $d['starts_on'], $d['days']);

        return $this->done($r, count($rows).' schedule entries generated.');
    }

    public function examinationState(Request $r, M\Examination $exam)
    {
        $d = $r->validate(['status' => 'required|in:approved,published']);
        app(S\SchedulingService::class)->transition($exam, $d['status']);

        return $this->done($r, 'Examination '.$d['status'].'.');
    }

    public function revision(Request $r, M\Examination $exam)
    {
        $new = app(S\SchedulingService::class)->revision($exam);

        return redirect('/admin/date-sheet-list')->with('status', 'Draft examination revision #'.$new->id.' created. Review, approve and publish it to notify students.');
    }

    public function seating(Request $r, M\ScheduleEntry $entry)
    {
        $r->validate(['preview' => 'sometimes|boolean']);
        if ($r->boolean('preview')) {
            return back()->with('seating_preview', app(S\SeatingService::class)->preview($entry));
        }
        $plan = app(S\SeatingService::class)->allocate($entry, $r->user()->id);

        return $this->done($r, 'Allocated '.$plan->seats()->count().' seats, revision '.$plan->revision.'.');
    }

    public function duty(Request $r, M\ScheduleEntry $entry)
    {
        $d = $r->validate(['teacher_id' => 'required|exists:users,id', 'reserve' => 'sometimes|boolean', 'room_id' => 'nullable|exists:rooms,id']);
        try {
            app(S\DutyService::class)->assign($entry, $d['teacher_id'], $r->boolean('reserve'), $d['room_id'] ?? null);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                throw V::withMessages(['teacher_id' => 'This teacher already has an assignment for this schedule.']);
            }throw $e;
        }

        return $this->done($r, 'Duty assigned.');
    }

    public function dutyRequest(Request $r, M\DutyAssignment $duty)
    {
        $data = $r->validate(['reason' => 'required|string|min:10|max:2000']);
        app(S\DutyService::class)->request($duty, $r->user()->id, $data['reason']);

        return $this->done($r, 'Request submitted before the 48-hour deadline. Your duty remains assigned pending administrator approval.');
    }

    public function rejectDuty(Request $r, M\DutyRequest $request)
    {
        $data = $r->validate(['reason' => 'required|string|min:10|max:2000']);
        app(S\DutyService::class)->reject($request, $r->user()->id, $data['reason']);

        return $this->done($r, 'Request rejected; original assignment retained.');
    }

    public function nominate(Request $r, M\DutyReplacement $replacement)
    {
        $data = $r->validate(['candidate_id' => 'required|integer|exists:users,id']);
        app(S\DutyService::class)->nominate($replacement, $data['candidate_id'], $r->user()->id);

        return $this->done($r, 'Reserve candidate nominated. Revalidation is required at approval.');
    }

    public function forecast(Request $r)
    {
        $d = $r->validate(['student_id' => 'required|integer|exists:users,id', 'course_id' => 'required|integer|exists:courses,id']);
        $run = app(S\ForecastService::class)->forecast($r->user(), $r->user()->role === 'student' ? $r->user()->id : $d['student_id'], $d['course_id']);

        return back()->with('forecast', $run->evidence)->with('status', 'Advisory forecast generated from authorized published history.');
    }

    public function backup(Request $r)
    {
        try {
            $run = app(S\BackupService::class)->create($r->user()->id);
        } catch (\RuntimeException $e) {
            throw V::withMessages(['backup' => $e->getMessage()]);
        }

        return back()->with('status', 'Backup #'.$run->id.' created and checksum verified.');
    }

    public function downloadBackup(Request $r, M\BackupRun $backup)
    {
        abort_unless($backup->status === 'completed' && $backup->path && Storage::exists($backup->path), 404);
        app(S\BackupService::class)->verify(Storage::path($backup->path));

        return Storage::download($backup->path, 'examcell-backup-'.$backup->id.'.zip');
    }

    public function restoreBackup(Request $r)
    {
        $d = $r->validate(['archive' => 'required|file|mimes:zip|max:'.config('examcell.restore_upload_max_kb', 524288), 'target' => 'required|string|regex:/^[a-zA-Z0-9_]+_restore$/', 'confirm' => 'accepted']);
        try {
            app(S\BackupService::class)->restore($r->file('archive')->path(), $d['target']);
        } catch (\Throwable $e) {
            throw V::withMessages(['restore' => 'Restore failed. Use a signed backup from this installation, an empty target ending in _restore, and database creation permission. The current database has not been replaced.']);
        }

        return back()->with('status', 'Restored and verified '.$d['target'].'. Private files are staged under storage/app/private/restore/'.$d['target'].'. Review the restored database before switching local configuration.');
    }

    public function availability(Request $r)
    {
        $d = $r->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'required|string|min:5|max:1000', 'teacher_id' => 'sometimes|exists:users,id']);
        $teacher = $r->user()->role === 'teacher' ? $r->user()->id : ($d['teacher_id'] ?? null);
        if (! $teacher || ! M\User::whereKey($teacher)->where('role', 'teacher')->exists()) {
            throw V::withMessages(['teacher_id' => 'Select a teacher.']);
        }
        $p = app(S\DutyService::class)->unavailable($teacher, $d['starts_at'], $d['ends_at'], $d['reason']);

        return $this->done($r, count($p).' affected duties identified; proposals require administrator approval.');
    }

    public function replacement(Request $r, M\DutyReplacement $replacement)
    {
        app(S\DutyService::class)->approve($replacement, $r->user()->id);

        return $this->done($r, 'Replacement approved and teacher notified.');
    }

    public function marks(MarksRequest $r, M\ExamCourse $course)
    {
        app(S\MarksService::class)->save($course, $r->validated('rows'), $r->user()->id, $r->boolean('submit'));

        return $this->done($r, $r->boolean('submit') ? 'Marks submitted and locked.' : 'Marks draft saved.');
    }

    public function returnMarks(Request $r, M\ExamCourse $course)
    {
        $d = $r->validate(['reason' => 'required|string|min:5|max:1000']);
        app(S\MarksService::class)->return($course, $d['reason']);

        return $this->done($r, 'Marks returned for correction.');
    }

    public function calculate(Request $r, M\Examination $exam)
    {
        app(S\ResultsService::class)->calculate($exam);

        return $this->done($r, 'Results calculated from submitted marks.');
    }

    public function resultState(Request $r, M\ResultBatch $batch)
    {
        $d = $r->validate(['status' => 'required|in:submitted,under_review,approved,published']);
        app(S\ResultsService::class)->transition($batch, $d['status'], $r->user()->id);

        return $this->done($r, 'Result batch '.$d['status'].'.');
    }

    public function correction(Request $r, M\Mark $mark)
    {
        $d = $r->validate(['score' => 'nullable|numeric|decimal:0,2', 'absent' => 'sometimes|boolean', 'reason' => 'required|string|min:10|max:2000']);
        app(S\ResultsService::class)->requestCorrection($mark, isset($d['score']) ? (string) $d['score'] : null, $r->boolean('absent'), $d['reason'], $r->user()->id);

        return $this->done($r, 'Correction requested; a different administrator must approve it.');
    }

    public function approveCorrection(Request $r, M\ResultCorrection $correction)
    {
        app(S\ResultsService::class)->approveCorrection($correction, $r->user()->id);

        return $this->done($r, 'Correction approved; result recalculated and audit history recorded.');
    }

    public function recheck(Request $r, M\CalculatedResult $result)
    {
        Gate::authorize('view', $result);
        $d = $r->validate(['reason' => 'required|string|min:10|max:2000']);
        try {
            app(S\ResultsService::class)->recheck($result, $r->user()->id, $d['reason']);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                throw V::withMessages(['reason' => 'You already have a rechecking request for this result.']);
            }throw $e;
        }

        return $this->done($r, 'Rechecking request submitted.');
    }

    public function decideRecheck(Request $r, M\RecheckRequest $recheck)
    {
        $d = $r->validate(['status' => 'required|in:under_review,approved,rejected', 'reason' => 'required|string|min:10|max:2000']);
        app(S\ResultsService::class)->decide($recheck, $d['status'], $d['reason'], $r->user()->id);

        return $this->done($r, 'Rechecking decision recorded. Marks remain subject to a separate correction approval.');
    }

    public function analysis(Request $r, M\Examination $exam)
    {
        $run = app(S\AnalysisService::class)->run($exam, $r->user()->id);

        return $this->done($r, 'Analysis completed: '.$run->summary['conditions'].' conditions.');
    }

    public function reviewFlag(Request $r, M\AnalysisFlag $flag)
    {
        $d = $r->validate(['status' => 'required|in:resolved,dismissed', 'reason' => 'required|string|min:10|max:2000']);
        $flag->update(['status' => $d['status'], 'review_reason' => $d['reason']]);
        app(S\AuditService::class)->record('flag.reviewed', $flag, $d);

        return $this->done($r, 'Review recorded.');
    }

    public function assistant(Request $r)
    {
        $d = $r->validate(['intent' => 'required|in:results,schedule,seats,duties', 'question' => 'required|string|max:1000', 'conversation_id' => 'nullable|integer', 'explain' => 'sometimes|boolean']);
        $m = app(S\AssistantService::class)->answer($r->user(), $d['intent'], $d['question'], $d['conversation_id'] ?? null, $r->boolean('explain'));

        return $r->expectsJson() ? response()->json(['conversation_id' => $m->conversation_id, 'message' => $m->content, 'status' => $m->provider_status]) : redirect('/'.$r->user()->role.'/'.$r->user()->role.'-ai-assistant?conversation_id='.$m->conversation_id);
    }

    public function import(Request $r)
    {
        $d = $r->validate(['file' => 'required|file|max:2048|mimes:csv,txt,xlsx,zip', 'kind' => 'required|in:students,marks', 'course_id' => 'required_if:kind,marks|nullable|exists:exam_courses,id', 'commit' => 'sometimes|boolean']);
        $format = strtolower($r->file('file')->getClientOriginalExtension());
        if (! in_array($format, ['csv', 'xlsx'])) {
            throw V::withMessages(['file' => 'Use a CSV or modern Excel XLSX file.']);
        }
        if ($d['kind'] === 'students') {
            abort_unless($r->user()->role === 'admin', 403);
            $summary = app(S\ImportService::class)->students($r->file('file')->path(), $r->boolean('commit'), $format);
        } else {
            $summary = app(S\ImportService::class)->marks(M\ExamCourse::findOrFail($d['course_id']), $r->file('file')->path(), $r->user()->id, $r->boolean('commit'), $format);
        }

        return $r->expectsJson() ? response()->json($summary) : back()->with('import_summary', $summary)->with('status', $summary['committed'] ? 'Import committed.' : 'Preview validated; upload again with Commit checked to persist.');
    }

    public function admit(Request $r, M\Examination $exam)
    {
        $d = $r->validate(['student_id' => 'nullable|exists:users,id', 'bulk' => 'sometimes|boolean']);
        if ($r->boolean('bulk')) {
            if ($exam->status !== 'published' || ! $exam->admit_enabled) {
                throw V::withMessages(['admit' => 'Publish the examination with admit cards enabled first.']);
            }
            $run = M\AdmitGenerationRun::create(['examination_id' => $exam->id, 'requested_by' => $r->user()->id]);
            GenerateAdmitCards::dispatch($run->id);

            return $this->done($r, 'Bulk generation queued as run #'.$run->id.'. PDFs appear after the queue worker finishes.');
        } else {
            if (empty($d['student_id'])) {
                throw V::withMessages(['student_id' => 'Select a student or bulk generation.']);
            }
            app(S\ReportService::class)->admit($exam, $d['student_id']);
            $count = 1;
        }

        return $this->done($r, $count.' admit card(s) generated.');
    }

    public function downloadAdmit(Request $r, M\AdmitCard $card)
    {
        Gate::authorize('view', $card);
        if ((int) $card->verification_version < S\AdmitVerificationService::VERSION) {
            $card = app(S\ReportService::class)->upgradeVerification($card);
        }
        abort_unless(Storage::exists($card->path), 404);

        return Storage::download($card->path, 'admit-'.$card->reference.'.pdf');
    }

    public function readNotification(Request $r, string $id)
    {
        $n = $r->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        return $this->done($r, 'Notification marked read.');
    }

    public function report(Request $r, string $type)
    {
        $r->validate(['format' => 'nullable|in:csv,pdf', 'course_id' => 'nullable|exists:exam_courses,id', 'session_id' => 'nullable|exists:academic_sessions,id']);
        $scope = app(S\ScopedQueries::class);
        $u = $r->user();
        $query = match ($type) {
            'results' => $scope->results($u),'schedule' => $scope->schedules($u),'seats' => $scope->seats($u),'duties' => $u->role === 'student' ? abort(403) : $scope->duties($u),'audit' => $u->role === 'admin' ? app(S\AcademicContext::class)->apply(M\AuditLog::query(), $u) : abort(403),default => abort(404)
        };
        if ($r->filled('course_id')) {
            $query = match ($type) {
                'results','schedule' => $query->where('exam_course_id', $r->integer('course_id')),'seats' => $query->whereHas('seatingPlan.scheduleEntry', fn ($q) => $q->where('exam_course_id', $r->integer('course_id'))),'duties' => $query->whereHas('scheduleEntry', fn ($q) => $q->where('exam_course_id', $r->integer('course_id'))),default => $query
            };
        }
        if ($r->filled('session_id')) {
            $query = match ($type) {
                'results' => $query->whereHas('batch.examination', fn ($q) => $q->where('academic_session_id', $r->integer('session_id'))),'schedule' => $query->whereHas('examCourse.examination', fn ($q) => $q->where('academic_session_id', $r->integer('session_id'))),'seats' => $query->whereHas('seatingPlan.scheduleEntry.examCourse.examination', fn ($q) => $q->where('academic_session_id', $r->integer('session_id'))),'duties' => $query->whereHas('scheduleEntry.examCourse.examination', fn ($q) => $q->where('academic_session_id', $r->integer('session_id'))),default => $query
            };
        }
        $map = fn ($m) => match ($type) {
            'results' => ['Student' => $m->student->name, 'Roll' => $m->student->profile?->roll_number, 'Course' => $m->examCourse->course->code, 'Percentage' => $m->percentage, 'Grade' => $m->grade, 'Pass' => $m->passed ? 'Pass' : 'Fail', 'Points' => $m->grade_points, 'Credits' => $m->credits, 'Quality' => $m->quality_points],'schedule' => ['Course' => $m->examCourse->course->code, 'Room' => $m->room_label, 'Starts' => $m->starts_at->format('Y-m-d H:i'), 'Ends' => $m->ends_at->format('Y-m-d H:i')],'seats' => ['Student' => $m->student->name, 'Course' => $m->seatingPlan->scheduleEntry->examCourse->course->code, 'Room' => $m->room?->name ?? $m->seatingPlan->scheduleEntry->room->name, 'Seat' => $m->seat_number],'duties' => ['Teacher' => $m->teacher->name, 'Room' => $m->room?->name ?? $m->scheduleEntry->room->name, 'Starts' => $m->scheduleEntry->starts_at->format('Y-m-d H:i'), 'Status' => $m->status],default => ['Actor' => $m->actor_id, 'Action' => $m->action, 'Entity' => $m->entity_type, 'Record' => $m->entity_id, 'Changes' => json_encode($m->changes), 'At' => $m->created_at->toDateTimeString()]
        };
        app(S\AuditService::class)->record('report.exported', $type, ['format' => $r->input('format', 'csv')]);
        if ($r->input('format') === 'pdf') {
            if ($query->count() > 500) {
                throw V::withMessages(['report' => 'PDF limit is 500 records. Narrow the filters or use CSV.']);
            }
            $rows = $query->orderBy('id')->get()->map($map);

            return Pdf::loadView('backend.report-pdf', ['rows' => $rows, 'title' => ucfirst($type).' report'])->setPaper('a4', 'landscape')->download($type.'.pdf');
        }

        return response()->streamDownload(function () use ($query, $map) {
            $f = fopen('php://output', 'w');
            $header = false;
            foreach ($query->orderBy('id')->lazyById(250) as $m) {
                $row = $map($m);
                if (! $header) {
                    fputcsv($f, array_keys($row));
                    $header = true;
                }fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-]/', $v) ? "'".$v : $v, $row));
            }fclose($f);
        }, $type.'.csv', ['Content-Type' => 'text/csv']);
    }
}
