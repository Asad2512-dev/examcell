<?php

namespace App\Http\Controllers;

use App\Jobs\RunAiTask;
use App\Models as M;
use App\Services\AI as AI;
use App\Services\AuditService;
use App\Services\DutyService;
use App\Services\ScopedQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException as V;

class AiController
{
    private function guard(AI\AiClient $ai): void
    {
        if (! $ai->configured()) {
            throw V::withMessages(['ai' => 'OpenAI is not configured. Add OPENAI_API_KEY to .env (see AI Settings).']);
        }
    }

    private function go(string $message, ?int $run = null)
    {
        $base = auth()->user()?->role === 'teacher' ? '/teacher/marks-csv-import' : '/admin/ai-automation';

        return redirect($base.($run ? '?run='.$run : ''))->with('status', $message);
    }

    public function saveModel(Request $r, AI\AiSettings $s)
    {
        $d = $r->validate(['model' => 'required|in:'.implode(',', config('ai.models'))]);
        $s->setModel($d['model']);
        app(AuditService::class)->record('ai.model_changed', 'ai', ['model' => $d['model']]);

        return redirect('/admin/ai-settings')->with('status', 'AI model updated.');
    }

    public function test(AI\AiClient $ai)
    {
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]];
        try {
            $this->guard($ai);
            $ai->structured('settings.test', 'Reply with ok=true.', 'ping', 'ping', $schema, auth()->id());
            $msg = 'Connection successful.';
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
            $msg = $e instanceof AI\AiException || $e instanceof V ? ($e instanceof V ? collect($e->errors())->flatten()->first() : $e->getMessage()) : 'Connection failed.';
        }

        return redirect('/admin/ai-settings')->with($ok ? 'status' : 'ai_error', $msg);
    }

    public function start(Request $r, AI\AiClient $ai)
    {
        $this->guard($ai);
        if ($r->user()->role === 'teacher') {
            abort_unless($r->input('feature') === 'import' && $r->input('target') === 'marks' && $r->filled('course_id') && app(ScopedQueries::class)->courses($r->user())->whereKey($r->integer('course_id'))->exists(), 403);
        }
        $d = $r->validate(['feature' => 'required|in:datesheet,seating,duties,import', 'exam_id' => 'required_if:feature,datesheet,duties|nullable|exists:examinations,id', 'entry_id' => 'required_if:feature,seating|nullable|exists:schedule_entries,id', 'constraints' => 'nullable|string|max:1500', 'file' => 'required_if:feature,import|nullable|file|max:8192|mimes:csv,txt,tsv,xlsx,pdf,png,jpg,jpeg,webp', 'target' => 'nullable|in:students,teachers,courses,marks', 'course_id' => 'nullable|exists:exam_courses,id']);
        $input = collect($d)->only('exam_id', 'entry_id', 'constraints', 'target', 'course_id')->filter(fn ($v) => $v !== null)->all();
        if ($d['feature'] === 'datesheet') {
            abort_unless(M\Examination::findOrFail($d['exam_id'])->status === 'draft', 422, 'Only draft examinations can receive a generated date sheet.');
        }
        if ($d['feature'] === 'import') {
            $input['path'] = $r->file('file')->store('ai-imports', 'local');
            $input['ext'] = strtolower($r->file('file')->getClientOriginalExtension());
        }
        $run = M\AiRun::create(['user_id' => $r->user()->id, 'feature' => $d['feature'], 'input' => $input, 'subject_id' => $d['exam_id'] ?? $d['entry_id'] ?? null]);
        RunAiTask::dispatch($run->id);

        return $this->go('AI is working on it.', $run->id);
    }

    public function status(M\AiRun $run)
    {
        abort_unless($run->user_id === auth()->id(), 403);

        return response()->json($run->only('status', 'progress', 'message'));
    }

    public function revise(Request $r, M\AiRun $run, AI\AiClient $ai)
    {
        abort_unless($run->user_id === auth()->id() && $run->feature === 'datesheet' && $run->result, 403);
        $this->guard($ai);
        $d = $r->validate(['instruction' => 'required|string|max:500']);
        $new = M\AiRun::create(['user_id' => $run->user_id, 'feature' => 'datesheet', 'subject_id' => $run->subject_id, 'input' => ['exam_id' => $run->input['exam_id'], 'constraints' => $run->input['constraints'] ?? null, 'instruction' => $d['instruction'], 'previous' => ['proposal' => $run->result['proposal'], 'rules' => $run->result['rules']]]]);
        RunAiTask::dispatch($new->id);

        return $this->go('Revising the date sheet…', $new->id);
    }

    public function approve(Request $r, M\AiRun $run)
    {
        abort_unless($run->user_id === auth()->id() && $run->status === 'needs_review', 403);
        abort_if($r->user()->role === 'teacher' && $run->feature !== 'import', 403);
        $res = $run->result;
        $actor = $r->user()->id;
        if (! empty($res['errors']) && $run->feature !== 'import') {
            throw V::withMessages(['ai' => 'This proposal still has validation problems and cannot be applied. Ask for a revision or regenerate.']);
        }
        $msg = match ($run->feature) {
            'datesheet' => app(AI\DateSheetService::class)->apply(M\Examination::findOrFail($run->input['exam_id']), $res['proposal'], $actor).' papers scheduled.',
            'seating' => (function () use ($run, $res, $actor) {
                app(AI\SeatingAiService::class)->apply(M\ScheduleEntry::findOrFail($run->input['entry_id']), $res['proposal'], $actor);

                return 'Seating plan saved.';
            })(),
            'duties' => app(AI\DutyAiService::class)->apply(M\Examination::findOrFail($run->input['exam_id']), $res['proposal'], $actor).' duties assigned.',
            'import' => app(AI\ImportAiService::class)->commit($res, array_map('intval', (array) $r->input('exclude', [])), $actor).' records imported.',
        };
        $run->update(['status' => 'approved', 'message' => $msg]);

        return $this->go($msg);
    }

    public function replacement(M\DutyReplacement $replacement, AI\DutyAiService $svc, AI\AiClient $ai, Request $r)
    {
        $this->guard($ai);
        $pick = $svc->suggestReplacement($replacement, $r->user()->id);
        if ($pick['candidate_id'] && $r->boolean('nominate')) {
            app(DutyService::class)->nominate($replacement, $pick['candidate_id'], $r->user()->id);
        }

        return back()->with('status', 'AI suggestion: '.($pick['candidate_id'] ? M\User::find($pick['candidate_id'])->name.'. ' : '').$pick['explanation']);
    }

    public function draft(Request $r, AI\NotificationDraftService $svc, AI\AiClient $ai)
    {
        $this->guard($ai);
        $d = $r->validate(['kind' => 'required|in:schedule,results,duties', 'exam_id' => 'required|exists:examinations,id']);

        return $this->go('Draft ready — review before sending.')->with('ai_draft', $svc->draft($d['kind'], M\Examination::findOrFail($d['exam_id']), $r->user()->id));
    }

    public function send(Request $r, AI\NotificationDraftService $svc)
    {
        $d = $r->validate(['kind' => 'required|in:schedule,results,duties', 'exam_id' => 'required|exists:examinations,id', 'title' => 'required|string|max:120', 'message' => 'required|string|max:800']);
        $n = $svc->send($d['kind'], M\Examination::findOrFail($d['exam_id']), $d['title'], $d['message']);
        app(AuditService::class)->record('ai.announcement.sent', 'notification', ['kind' => $d['kind'], 'recipients' => $n]);

        return $this->go("Announcement sent to $n people.");
    }

    public function report(Request $r, AI\ReportAiService $svc, AI\AiClient $ai)
    {
        $this->guard($ai);
        $d = $r->validate(['question' => 'required|string|max:500']);
        $plan = $svc->plan($d['question'], $r->user());
        app(AuditService::class)->record('ai.report.run', 'report', ['metric' => $plan['metric'], 'group_by' => $plan['group_by']]);

        return $this->go('Report ready.')->with('ai_report', $svc->run($plan, $r->user()));
    }

    public function triage(Request $r, AI\TriageService $svc, AI\AiClient $ai)
    {
        $this->guard($ai);
        $d = $r->validate(['kind' => 'required|in:recheck,anomaly', 'exam_id' => 'required|exists:examinations,id']);
        $exam = M\Examination::findOrFail($d['exam_id']);

        return $this->go('Triage ready — you decide.')->with('ai_triage', ['kind' => $d['kind']] + ($d['kind'] === 'recheck' ? $svc->rechecks($exam, $r->user()->id) : $svc->anomalies($exam, $r->user()->id)));
    }

    /** Seat map data for a schedule entry; students see only their own identity, others as anonymous colours. */
    public function seatData(M\User $u, M\ScheduleEntry $entry): array
    {
        $scope = app(ScopedQueries::class);
        abort_unless($scope->schedules($u)->whereKey($entry->id)->exists(), 404);
        $plan = $entry->seatingPlan;
        $seats = $plan ? $plan->seats()->with('student.profile.section', 'room')->get() : collect();
        $admin = $u->role !== 'student';
        $locked = in_array($entry->examCourse->examination->status, ['published', 'archived']);

        return [
            'entry' => $entry->id,
            'title' => $entry->examCourse->course->code.' · '.$entry->starts_at->format('d M Y H:i'),
            'locked' => $locked || $u->role !== 'admin',
            'rooms' => $entry->allocatedRooms()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'capacity' => $r->capacity, 'cols' => $r->columns(), 'accessible' => $r->accessible_seats ?? []])->values()->all(),
            'seats' => $seats->map(fn ($s) => ['seat' => $s->seat_number, 'room' => $s->room_id, 'student' => $s->student_id, 'section' => $s->student->profile?->section?->name ?? '—', 'mine' => $s->student_id === $u->id] + ($admin || $s->student_id === $u->id ? ['name' => $s->student->name, 'roll' => $s->student->profile?->roll_number, 'course' => $entry->examCourse->course->code] : []))->values()->all(),
        ];
    }

    public function seatSwap(Request $r, M\ScheduleEntry $entry, AI\SeatingAiService $svc)
    {
        $d = $r->validate(['student_id' => 'required|integer', 'room_id' => 'required|integer', 'seat' => 'required|integer|min:1']);
        $errors = $svc->swap($entry, $d['student_id'], $d['room_id'], $d['seat'], $r->user()->id);

        return response()->json(['ok' => ! $errors, 'errors' => $errors, 'data' => $this->seatData($r->user(), $entry->fresh())], $errors ? 422 : 200);
    }

    public function seatPrint(Request $r, M\ScheduleEntry $entry, M\Room $room)
    {
        $data = $this->seatData($r->user(), $entry);
        abort_unless(collect($data['rooms'])->contains('id', $room->id), 404);

        return view('ai.seat-print', ['data' => $data, 'room' => collect($data['rooms'])->firstWhere('id', $room->id)]);
    }
}
