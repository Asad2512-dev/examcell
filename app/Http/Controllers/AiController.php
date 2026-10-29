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

    /** Back to the page the admin/teacher came from (so panels appear in context), optionally showing a run. */
    private function go(string $message, ?int $run = null)
    {
        $user = auth()->user();
        $fallback = $user?->role === 'teacher' ? '/teacher/courses?tab=import' : '/admin/assistant?tab=actions';
        $prev = parse_url(url()->previous(), PHP_URL_PATH) ?: '';
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $q);
        $ok = preg_match('#^/(admin|teacher)/#', $prev) && ! str_contains($prev, '/ai/') && ! str_contains($prev, '/workflow/');
        unset($q['run']);
        if ($run) {
            $q['run'] = $run;
        }

        return redirect(($ok ? $prev : explode('?', $fallback)[0]).'?'.http_build_query($ok ? $q : ($run ? ['tab' => 'actions', 'run' => $run] : ['tab' => 'actions'])))->with('status', $message);
    }

    public function saveModel(Request $r, AI\AiSettings $s)
    {
        $d = $r->validate(['model' => 'required|in:'.implode(',', config('ai.models'))]);
        $s->setModel($d['model']);
        app(AuditService::class)->record('ai.model_changed', 'ai', ['model' => $d['model']]);

        return redirect('/admin/settings?tab=ai')->with('status', 'AI model updated.');
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

        return redirect('/admin/settings?tab=ai')->with($ok ? 'status' : 'ai_error', $msg);
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

    /** One sentence in, one routed proposal out. Used by the dashboard box and the AI Assistant chat. */
    public function command(Request $r, AI\CommandService $svc, AI\AiClient $ai)
    {
        $d = $r->validate(['text' => 'required|string|max:500', 'from' => 'nullable|in:dashboard,assistant']);
        $this->guard($ai);
        $u = $r->user();
        $out = $svc->interpret($d['text'], $u);
        $reply = $out['reply'];
        $url = null;
        $flash = [];
        $need = function (string $what) use (&$reply) {
            $reply = "I need to know which $what — please name it (for example the examination name).";
        };
        switch ($out['action']) {
            case 'datesheet':
                $exam = $out['exam_id'] ? M\Examination::find($out['exam_id']) : null;
                if (! $exam) {
                    $need('draft examination');
                } elseif ($exam->status !== 'draft') {
                    $reply = $exam->name.' is not a draft, so a new date sheet cannot be generated. Create a revision first.';
                } else {
                    $run = M\AiRun::create(['user_id' => $u->id, 'feature' => 'datesheet', 'subject_id' => $exam->id, 'input' => ['exam_id' => $exam->id, 'constraints' => $d['text']]]);
                    RunAiTask::dispatch($run->id);
                    $url = '/admin/exams?exam='.$exam->id.'&tab=datesheet&run='.$run->id;
                    $reply = 'Generating a date sheet for '.$exam->name.'. Review the proposal and approve it with one click.';
                }
                break;
            case 'seating':
                if (! $out['entry_id']) {
                    $need('scheduled paper');
                } else {
                    $run = M\AiRun::create(['user_id' => $u->id, 'feature' => 'seating', 'subject_id' => $out['entry_id'], 'input' => ['entry_id' => $out['entry_id']]]);
                    RunAiTask::dispatch($run->id);
                    $url = '/admin/seating?run='.$run->id;
                    $reply = 'Generating a seating plan. Review and approve it on the seating page.';
                }
                break;
            case 'duties':
                if (! $out['exam_id']) {
                    $need('examination');
                } else {
                    $run = M\AiRun::create(['user_id' => $u->id, 'feature' => 'duties', 'subject_id' => $out['exam_id'], 'input' => ['exam_id' => $out['exam_id']]]);
                    RunAiTask::dispatch($run->id);
                    $url = '/admin/invigilation?run='.$run->id;
                    $reply = 'Assigning invigilators. Review and approve on the invigilation page.';
                }
                break;
            case 'report':
                $plan = app(AI\ReportAiService::class)->plan($out['report_question'] ?: $d['text'], $u);
                $flash['ai_report'] = app(AI\ReportAiService::class)->run($plan, $u);
                $url = '/admin/reports?tab=reports';
                $reply = 'Report ready: '.$plan['title'];
                break;
            case 'announcement':
                $exam = $out['exam_id'] ? M\Examination::find($out['exam_id']) : null;
                if (! $exam || ! $out['announcement_kind']) {
                    $need('examination and topic (schedule, results or duties)');
                } else {
                    $flash['ai_draft'] = app(AI\NotificationDraftService::class)->draft($out['announcement_kind'], $exam, $u->id);
                    $url = '/admin/assistant?tab=actions';
                    $reply = 'Draft ready — edit and send it from the AI tools tab.';
                }
                break;
            case 'triage_recheck':
            case 'triage_anomaly':
                $exam = $out['exam_id'] ? M\Examination::find($out['exam_id']) : null;
                if (! $exam) {
                    $need('examination');
                } else {
                    $kind = $out['action'] === 'triage_recheck' ? 'recheck' : 'anomaly';
                    $flash['ai_triage'] = ['kind' => $kind] + ($kind === 'recheck' ? app(AI\TriageService::class)->rechecks($exam, $u->id) : app(AI\TriageService::class)->anomalies($exam, $u->id));
                    $url = $kind === 'recheck' ? '/admin/marks?tab=rechecking' : '/admin/marks?tab=processing';
                    $reply = 'Triage ready — recommendations only, you decide.';
                }
                break;
            case 'missing_marks':
                $rows = $svc->missingMarks($u);
                $reply = $rows ? "Marks not yet submitted:\n• ".implode("\n• ", array_slice($rows, 0, 30)) : 'Every course in your current scope has submitted marks.';
                break;
            case 'navigate':
                $url = $out['navigate_to'] && in_array($out['navigate_to'], AI\CommandService::NAV) ? '/admin/'.$out['navigate_to'] : null;
                break;
        }
        $c = $r->filled('conversation_id') ? M\Conversation::where('user_id', $u->id)->find($r->integer('conversation_id')) : null;
        $c ??= M\Conversation::create(['user_id' => $u->id]);
        $c->messages()->create(['role' => 'user', 'content' => $d['text']]);
        $c->messages()->create(['role' => 'assistant', 'content' => $reply.($url ? "\n[[link:$url|Open]]" : ''), 'provider_status' => 'ai_command']);
        app(AuditService::class)->record('ai.command', 'assistant', ['action' => $out['action']]);
        if ($url && ! str_starts_with($url, '/admin/') ) {
            $url = null;
        }
        if ($url) {
            return redirect($url)->with($flash)->with('status', $reply);
        }

        return redirect('/admin/assistant?tab=chat&conversation_id='.$c->id)->with('ai_reply', $reply);
    }

    /** Saved quick reports: fixed plans, no AI call, same permission-scoped queries. */
    public function quick(Request $r, string $key, AI\ReportAiService $svc)
    {
        $plans = ['pass-rate-department' => ['Pass rate by department', 'pass_rate', 'department', 'bar'], 'average-course' => ['Average percentage by course', 'average_percentage', 'course', 'bar'], 'grade-distribution' => ['Grade distribution', 'result_count', 'grade', 'pie'], 'results-section' => ['Pass rate by section', 'pass_rate', 'section', 'bar']];
        abort_unless(isset($plans[$key]), 404);
        [$title, $metric, $group, $chart] = $plans[$key];

        return redirect('/admin/reports?tab=reports')->with('ai_report', $svc->run(['title' => $title, 'metric' => $metric, 'group_by' => $group, 'chart' => $chart, 'examination_id' => null, 'department_code' => null, 'course_code' => null, 'summary' => 'Saved quick report from live records.'], $r->user()));
    }

    /** Seat map data for a schedule entry's slot, scoped to what this user may see. */
    public function seatData(M\User $u, M\ScheduleEntry $entry, ?array $rows = null): array
    {
        return app(\App\Services\SeatMapService::class)->data($u, $entry, $rows);
    }

    public function seatSwap(Request $r, M\ScheduleEntry $entry, AI\SeatingAiService $svc)
    {
        $d = $r->validate(['student_id' => 'required|integer', 'room_id' => 'required|integer', 'seat' => 'required|integer|min:1']);
        $errors = $svc->swap($entry, $d['student_id'], $d['room_id'], $d['seat'], $r->user()->id);

        return response()->json(['ok' => ! $errors, 'errors' => $errors, 'data' => $this->seatData($r->user(), $entry->fresh())], $errors ? 422 : 200);
    }

    /** A4 room plan (map + seat list), door notice, or attendance sheet. Staff only. */
    public function seatPrint(Request $r, M\ScheduleEntry $entry, M\Room $room)
    {
        abort_if($r->user()->role === 'student', 403);
        $type = $r->query('type', 'map');
        abort_unless(in_array($type, ['map', 'door', 'attendance'], true), 404);
        $data = $this->seatData($r->user(), $entry);
        $roomData = collect($data['rooms'])->firstWhere('id', $room->id);
        abort_unless($roomData, 404);
        $seats = collect($data['seats'])->where('room', $room->id)->sortBy('seat')->values();

        return view('ai.seat-print', ['data' => $data, 'room' => $roomData, 'seats' => $seats, 'type' => $type]);
    }
}
