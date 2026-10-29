@php
$tbKey=$wsState['key']??null;$tbTab=$wsState['tab']??null;
$tbConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured();
$tbExams=\App\Models\Examination::orderByDesc('id')->limit(30)->get();
$tbEntries=app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('examCourse.course')->orderBy('starts_at')->get();
@endphp
@if(in_array($tbKey,['seating','invigilation','people'])||($tbKey==='marks'&&in_array($tbTab,['processing','rechecking']))||$tbKey==='reports')@include('ai.run-panel')
@include('ai.outputs')
@endif
@if($tbKey==='seating')
<x-card title="AI seating" description="Mixes classes so classmates never sit side by side, within room capacity and accessible seats."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="seating">
<x-select name="entry_id" label="Scheduled paper">@foreach($tbEntries as $e)<option value="{{ $e->id }}">{{ $e->examCourse->course->code }} · {{ $e->starts_at->format('d M H:i') }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$tbConfigured||$tbEntries->isEmpty()">AI Generate</x-button></form></x-card>
@elseif($tbKey==='invigilation')
<x-card title="AI duty assignment" description="Fair assignment by availability and workload. Replacements below can ask AI for the best cover."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="duties">
<x-select name="exam_id" label="Examination">@foreach($tbExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$tbConfigured">AI Assign</x-button></form></x-card>
@elseif($tbKey==='marks'&&in_array($tbTab,['processing','rechecking']))
<x-card :title="$tbTab==='processing'?'AI anomaly check':'AI rechecking triage'" description="AI summarises and recommends; you decide. Marks are never changed by AI."><form method="POST" action="/admin/ai/triage" class="form-grid card-body">@csrf<input type="hidden" name="kind" value="{{ $tbTab==='processing'?'anomaly':'recheck' }}">
<x-select name="exam_id" label="Examination">@foreach($tbExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$tbConfigured">{{ $tbTab==='processing'?'Check anomalies':'Triage requests' }}</x-button></form></x-card>
@elseif($tbKey==='reports')
<x-card title="Ask for a report" description="e.g. Pass rate by department this semester"><form method="POST" action="/admin/ai/report" class="form-grid card-body">@csrf<x-input name="question" label="Your question" maxlength="500" required/><x-button type="submit" icon="chart" :disabled="!$tbConfigured">Build report</x-button></form>
<div class="card-body page-actions"><span class="muted text-xs">Quick reports:</span>@foreach(['pass-rate-department'=>'Pass rate by department','average-course'=>'Average by course','grade-distribution'=>'Grade distribution','results-section'=>'Pass rate by section'] as $k=>$l)<a class="btn btn-secondary btn-small" href="/admin/ai/quick/{{ $k }}">{{ $l }}</a>
@endforeach</div></x-card>
@elseif($tbKey==='people'&&in_array($tbTab,['students','teachers','academics']))
<x-card title="AI Import" description="Upload any file — Excel, CSV, PDF or a photo of a list. AI maps the columns; you confirm once."><form method="POST" action="/ai/start" enctype="multipart/form-data" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="import">
@if($tbTab==='students')<input type="hidden" name="target" value="students">@elseif($tbTab==='teachers')<input type="hidden" name="target" value="teachers">@endif
<label class="field"><span>File</span><input type="file" name="file" accept=".csv,.tsv,.txt,.xlsx,.pdf,.png,.jpg,.jpeg,.webp" required></label><x-button type="submit" icon="upload" :disabled="!$tbConfigured">AI Import</x-button></form></x-card>
@endif
