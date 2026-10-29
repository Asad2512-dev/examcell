@php
$ehTab=request('tab','overview');
$ehExam=request('exam')?$exams->firstWhere('id',(int)request('exam')):null;
$ehConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured();
@endphp
@if(!$ehExam)
<x-card title="Examinations" description="Open an examination to manage its date sheet and admit cards."><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Examination</th><th>Type</th><th>Status</th><th>Papers</th><th>Scheduled</th><th></th></tr></thead><tbody>
@forelse($exams as $e)<tr><td>{{ $e->name }}</td><td>{{ ucfirst($e->type) }}</td><td><x-badge :status="ucfirst($e->status)"/></td><td>{{ $e->courses->count() }}</td><td>{{ $e->courses->filter(fn($c)=>$c->schedule)->count() }}</td><td><a class="btn btn-secondary btn-small" href="/admin/exams?exam={{ $e->id }}&tab={{ in_array($ehTab,['datesheet','admit'])?$ehTab:'overview' }}">Open</a></td></tr>
@empty<tr><td colspan="6">No examinations in this academic scope yet.</td></tr>
@endforelse</tbody></table></x-data-table></x-card>
@else
@php
$ehScheduler=app(\App\Services\SchedulingService::class);
$ehEvents=[];$ehClashes=[];
foreach($ehExam->courses as $c){ if($c->schedule){ $errs=$ehScheduler->entryConflicts($c->schedule->loadMissing('examCourse.course')); foreach($errs as $er){$ehClashes[]=$c->course->code.': '.$er;} $ehEvents[]=['id'=>$c->schedule->id,'title'=>$c->course->code,'detail'=>$c->section->name.' · '.$c->schedule->starts_at->format('H:i').' · '.$c->schedule->room_label,'date'=>$c->schedule->starts_at->format('Y-m-d H:i'),'conflict'=>(bool)$errs]; } }
$ehUnscheduled=$ehExam->courses->filter(fn($c)=>!$c->schedule)->count();
$ehLineage=\App\Models\Examination::where('id',$ehExam->id)->orWhere('id',$ehExam->supersedes_id)->orWhere('supersedes_id',$ehExam->id)->orderBy('id')->get();
@endphp
<x-card :title="$ehExam->name" :description="ucfirst($ehExam->type).' · '.$ehExam->courses->count().' papers · '.$ehUnscheduled.' unscheduled'"><x-slot:actions><x-badge :status="ucfirst($ehExam->status)"/></x-slot:actions>
<div class="card-body"><a class="card-link" href="/admin/exams?tab={{ $ehTab }}">← All examinations</a></div></x-card>
@include('ai.run-panel')
@if($ehTab==='overview')
<x-card title="Overview"><div class="card-body"><p>Admit cards {{ $ehExam->admit_enabled?'enabled':'disabled' }} · {{ $ehExam->courses->count() }} papers · {{ count($ehClashes) }} clash(es).</p>
<div class="page-actions">
@if($ehExam->status==='draft')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="approved"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Approve this examination?" data-confirm-message="Every paper must be scheduled, seated, covered and clash-free." data-confirm-label="Approve">Approve</x-button></form>
@elseif($ehExam->status==='approved')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="published"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Publish this examination?" data-confirm-message="Students and teachers are notified." data-confirm-label="Publish">Publish</x-button></form>
@elseif($ehExam->status==='published')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/revision">@csrf<x-button type="submit" variant="secondary">Create revision</x-button></form>@endif</div></div></x-card>
@elseif($ehTab==='datesheet')
@if($ehExam->status==='draft')<x-card title="Generate with AI" description="Describe constraints in plain language, or leave blank. The AI proposes; clashes, capacity and your rules are checked by the system."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="datesheet"><input type="hidden" name="exam_id" value="{{ $ehExam->id }}">
<label class="field"><span>Constraints (optional)</span><textarea name="constraints" rows="3" maxlength="1500" placeholder="No exams on Friday afternoon, max 2 papers per day for any class, gap before major subjects"></textarea></label>
<x-button type="submit" icon="sparkles" :disabled="!$ehConfigured">Generate date sheet</x-button></form></x-card>@endif
@if($ehEvents)<x-calendar :events="$ehEvents" title="Date sheet"/>@else<x-card><x-empty-state title="No schedule yet" description="Generate a date sheet with AI or schedule papers manually below." icon="calendar"/></x-card>@endif
@if($ehClashes)<div class="callout callout-danger" role="alert"><strong>Clashes to resolve</strong><ul>@foreach($ehClashes as $cl)<li>{{ $cl }}</li>
@endforeach</ul></div>@endif
<x-card title="Approve and publish"><div class="card-body page-actions">
@if($ehExam->status==='draft')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="approved"><x-button type="submit" icon="check">Approve date sheet</x-button></form>
@elseif($ehExam->status==='approved')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="published"><x-button type="submit" icon="check">Publish</x-button></form>
@else<span class="muted">Published. Use “Create revision” on Overview to change it.</span>@endif</div></x-card>
<x-card title="Versions"><x-data-table :enhanced="false"><table class="data-table"><tbody>@foreach($ehLineage as $v)<tr><td><a class="card-link" href="/admin/exams?exam={{ $v->id }}&tab=datesheet">{{ $v->name }}</a></td><td><x-badge :status="ucfirst($v->status)"/></td></tr>
@endforeach</tbody></table></x-data-table></x-card>
<h2>Manual scheduling</h2>
@elseif($ehTab==='admit')
@php $ehRun=\App\Models\AdmitGenerationRun::where('examination_id',$ehExam->id)->latest()->first(); @endphp
<x-card title="Admit cards" description="Generated for every eligible student once the examination is published."><div class="card-body">
@if($ehRun)<x-job-progress :status="$ehRun->status" :count="$ehRun->generated" :failure="$ehRun->failure??null"/>@endif
<form method="POST" action="/workflow/admit/{{ $ehExam->id }}" class="page-actions">@csrf<input type="hidden" name="bulk" value="1"><x-button type="submit" icon="file" :disabled="$ehExam->status!=='published'||!$ehExam->admit_enabled">Generate all admit cards</x-button></form>
@if($ehExam->status!=='published')<p class="muted text-xs">Publish the examination first.</p>@endif</div></x-card>
@endif
@endif
