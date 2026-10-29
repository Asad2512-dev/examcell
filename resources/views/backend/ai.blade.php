@if($page['slug']==='ai-settings')
@php
$aiSettings=app(\App\Services\AI\AiSettings::class);
$aiUsage=$aiSettings->monthlyUsage();
$aiConfigured=$aiSettings->keyConfigured();
@endphp
@if(session('ai_error'))<div class="callout callout-danger" role="alert"><x-icon name="shield" :size="16"/> {{ session('ai_error') }}</div>
@endif
<div class="dashboard-kpis">
<x-stat-card label="API key" :value="$aiConfigured?'Configured':'Missing'" icon="shield" :note="$aiConfigured?'Read from OPENAI_API_KEY in .env (hidden)':'Add OPENAI_API_KEY to .env'"/>
<x-stat-card label="Requests this month" :value="$aiUsage['requests']" icon="sparkles" :note="$aiUsage['failed'].' failed attempts'"/>
<x-stat-card label="Tokens this month" :value="number_format($aiUsage['input_tokens']+$aiUsage['output_tokens'])" icon="chart" :note="number_format($aiUsage['input_tokens']).' in · '.number_format($aiUsage['output_tokens']).' out'"/>
<x-stat-card label="Estimated cost" :value="'$'.number_format($aiUsage['cost_usd'],2)" icon="award" note="Estimate from configured prices"/>
</div>
<x-card title="Model and connection" description="The key is never shown. Change it only in the server's .env file.">
<form method="POST" action="/admin/ai-settings/model" class="form-grid">@csrf
<x-select name="model" label="Model">@foreach(config('ai.models') as $m)<option value="{{ $m }}" @selected($aiSettings->model()===$m)>{{ $m }}</option>
@endforeach</x-select>
<x-button type="submit" variant="secondary" icon="check">Save model</x-button></form>
<form method="POST" action="/admin/ai-settings/test" class="card-body">@csrf<x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Test connection</x-button>
@unless($aiConfigured)<p class="muted text-xs mt-3">Set OPENAI_API_KEY in .env, run <code>php artisan config:clear</code>, then test.</p>@endunless</form>
</x-card>
@if($aiUsage['by_feature'])<x-card title="Usage by feature"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Feature</th><th>Requests</th><th>Cost (USD)</th></tr></thead><tbody>
@foreach($aiUsage['by_feature'] as $f=>$u)<tr><td>{{ $f }}</td><td>{{ $u['requests'] }}</td><td>${{ number_format($u['cost_usd'],4) }}</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
@endif
@else
@php
$aiExams=\App\Models\Examination::orderByDesc('id')->limit(30)->get();
$aiEntries=app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('examCourse.course','examCourse.examination')->orderBy('starts_at')->get();
$aiConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured();
$aiRecent=\App\Models\AiRun::where('user_id',auth()->id())->latest()->limit(8)->get();
@endphp
@unless($aiConfigured)<div class="callout callout-warning" role="status"><strong>OpenAI is not configured yet.</strong> <a class="card-link" href="/admin/ai-settings">Open AI Settings</a></div>
@endunless
@include('ai.run-panel')
@include('ai.outputs')
<div class="dashboard-middle">
<x-card title="1 · Generate a date sheet" description="Pick a draft examination and describe any rules in plain language."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="datesheet">
<x-select name="exam_id" label="Draft examination">@foreach($aiExams->where('status','draft') as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select>
<label class="field"><span>Constraints (optional)</span><textarea name="constraints" rows="3" maxlength="1500" placeholder="No exams on Friday afternoon, max 2 papers per day for any class, gap before major subjects"></textarea></label>
<x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Generate with AI</x-button></form></x-card>
<x-card title="2 · Seating plan" description="Mixes classes so classmates never sit side by side."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="seating">
<x-select name="entry_id" label="Scheduled paper">@foreach($aiEntries as $e)<option value="{{ $e->id }}">{{ $e->examCourse->course->code }} · {{ $e->starts_at->format('d M H:i') }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Generate seating</x-button></form></x-card>
<x-card title="3 · Invigilation duties" description="Fair assignment by availability and workload."><form method="POST" action="/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="duties">
<x-select name="exam_id" label="Examination">@foreach($aiExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Assign duties</x-button></form></x-card>
<x-card title="4 · Smart import" description="Upload Excel, CSV, PDF or a photo of a list. No template needed."><form method="POST" action="/ai/start" enctype="multipart/form-data" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="import">
<x-select name="target" label="Contents"><option value="">Detect automatically</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="courses">Courses</option><option value="marks">Marks</option></x-select>
<x-select name="course_id" label="Course (marks only)"><option value="">—</option>@foreach(app(\App\Services\AcademicContext::class)->apply(\App\Models\ExamCourse::query())->with('course')->get() as $c)<option value="{{ $c->id }}">{{ $c->course->code }} (#{{ $c->id }})</option>
@endforeach</x-select>
<label class="field"><span>File</span><input type="file" name="file" accept=".csv,.tsv,.txt,.xlsx,.pdf,.png,.jpg,.jpeg,.webp" required></label>
<x-button type="submit" icon="upload" :disabled="!$aiConfigured">Read with AI</x-button></form></x-card>
<x-card title="5 · Ask for a report" description="e.g. Pass rate by department this semester"><form method="POST" action="/admin/ai/report" class="form-grid card-body">@csrf<x-input name="question" label="Your question" maxlength="500" required/><x-button type="submit" icon="chart" :disabled="!$aiConfigured">Build report</x-button></form></x-card>
<x-card title="6 · Triage" description="AI summarises; you decide."><form method="POST" action="/admin/ai/triage" class="form-grid card-body">@csrf
<x-select name="exam_id" label="Examination">@foreach($aiExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-select name="kind" label="Type"><option value="recheck">Rechecking requests</option><option value="anomaly">Marks anomalies</option></x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Summarise</x-button></form></x-card>
<x-card title="7 · Draft an announcement" description="For schedule changes, results or duties."><form method="POST" action="/admin/ai/draft" class="form-grid card-body">@csrf
<x-select name="exam_id" label="Examination">@foreach($aiExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-select name="kind" label="Topic"><option value="schedule">Schedule</option><option value="results">Results</option><option value="duties">Duties</option></x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Draft with AI</x-button></form></x-card>
</div>
@if($aiRecent->isNotEmpty())<x-card title="Recent AI runs"><x-data-table :enhanced="false"><table class="data-table"><tbody>
@foreach($aiRecent as $r)<tr><td><a class="card-link" href="/admin/ai-automation?run={{ $r->id }}">#{{ $r->id }} {{ $r->feature }}</a></td><td>{{ $r->status }}</td><td>{{ $r->created_at->diffForHumans() }}</td></tr>
@endforeach</tbody></table></x-data-table></x-card>
@endif
@endif
