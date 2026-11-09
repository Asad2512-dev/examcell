@php
$ehTab=request('tab','overview');
$ehExam=request('exam')?$exams->firstWhere('id',(int)request('exam')):null;
$ehConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured();
@endphp
@if(!$ehExam)
@if($ehTab==='datesheet')
<div class="callout"><strong>{{ ui_text('examinations.copy.make_a_date_sheet_in_three_steps') }}</strong><p>{{ ui_text('examinations.copy.choose_an_examination_below_generate_its_timetable_revie') }}</p><p>{{ ui_text('examinations.copy.starting_fresh') }} <a class="card-link" href="/admin/records?resource=examinations">{{ ui_text('examinations.copy.create_an_examination') }}</a>{{ ui_text('examinations.copy.then') }} <a class="card-link" href="/admin/records?resource=exam-courses">{{ ui_text('examinations.copy.add_its_papers') }}</a>.</p></div>
@endif
<x-card title="{{ ui_text('examinations.copy.examinations') }}" description="Open an examination to manage its date sheet and admit cards."><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('examinations.copy.examination') }}</th><th>{{ ui_text('examinations.copy.type') }}</th><th>{{ ui_text('examinations.copy.status') }}</th><th>{{ ui_text('examinations.copy.papers') }}</th><th>{{ ui_text('examinations.copy.scheduled') }}</th><th></th></tr></thead><tbody>
@forelse($exams as $e)<tr><td>{{ $e->name }}</td><td>{{ ucfirst($e->type) }}</td><td><x-badge :status="ucfirst($e->status)"/></td><td>{{ $e->courses->count() }}</td><td>{{ $e->courses->filter(fn($c)=>$c->schedule)->count() }}</td><td>@if($ehTab!=='datesheet')<a class="btn btn-secondary btn-small" href="/admin/exams?exam={{ $e->id }}&tab={{ in_array($ehTab,['datesheet','admit'])?$ehTab:'overview' }}">{{ ui_text('examinations.copy.open') }}</a>@endif <a class="btn btn-primary btn-small" href="/admin/exams?exam={{ $e->id }}&amp;tab=datesheet">{{ $e->courses->contains(fn($c)=>(bool)$c->schedule)?'View date sheet':'Create date sheet' }}</a></td></tr>
@empty<tr><td colspan="6">{{ ui_text('examinations.copy.no_examinations_in_this_academic_scope_yet') }}</td></tr>
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
<div class="card-body"><a class="card-link" href="/admin/exams?tab={{ $ehTab }}">{{ ui_text('examinations.copy.all_examinations') }}</a></div></x-card>
@include('ai.run-panel')
@if($ehTab==='overview')
<x-card title="{{ ui_text('examinations.copy.overview') }}"><div class="card-body"><p>Admit cards {{ $ehExam->admit_enabled?'enabled':'disabled' }} · {{ $ehExam->courses->count() }} papers · {{ count($ehClashes) }} clash(es).</p>
<div class="page-actions">
@if($ehExam->status==='draft')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="approved"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="{{ ui_text('examinations.copy.approve_this_examination') }}" data-confirm-message="{{ ui_text('examinations.copy.every_paper_must_be_scheduled_seated_covered_and_clash_f') }}" data-confirm-label="{{ ui_text('examinations.copy.approve') }}">{{ ui_text('examinations.copy.approve') }}</x-button></form>
@elseif($ehExam->status==='approved')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input type="hidden" name="status" value="published"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="{{ ui_text('examinations.copy.publish_this_examination') }}" data-confirm-message="{{ ui_text('examinations.copy.students_and_teachers_are_notified') }}" data-confirm-label="{{ ui_text('examinations.copy.publish') }}">{{ ui_text('examinations.copy.publish') }}</x-button></form>
@elseif($ehExam->status==='published')<form method="POST" action="/workflow/examination/{{ $ehExam->id }}/revision">@csrf<x-button type="submit" variant="secondary">{{ ui_text('examinations.copy.create_revision') }}</x-button></form>@endif</div></div></x-card>
@elseif($ehTab==='datesheet')
@include('backend.date-sheet')
@elseif($ehTab==='admit')
@php $ehRun=\App\Models\AdmitGenerationRun::where('examination_id',$ehExam->id)->latest()->first(); @endphp
<x-card title="{{ ui_text('examinations.copy.admit_cards') }}" description="Generated for every eligible student once the examination is published."><div class="card-body">
@if($ehRun)<x-job-progress :status="$ehRun->status" :count="$ehRun->generated" :failure="$ehRun->failure??null"/>@endif
<form method="POST" action="/workflow/admit/{{ $ehExam->id }}" class="page-actions">@csrf<input type="hidden" name="bulk" value="1"><x-button type="submit" icon="file" :disabled="$ehExam->status!=='published'||!$ehExam->admit_enabled">{{ ui_text('examinations.copy.generate_all_admit_cards') }}</x-button></form>
@if($ehExam->status!=='published')<p class="muted text-xs">{{ ui_text('examinations.copy.publish_the_examination_first') }}</p>@endif</div></x-card>
@endif
@endif
