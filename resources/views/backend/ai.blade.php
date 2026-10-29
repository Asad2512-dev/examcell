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
$aiRun=request('run')?\App\Models\AiRun::where('user_id',auth()->id())->find(request('run')):null;
$aiConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured();
$aiRecent=\App\Models\AiRun::where('user_id',auth()->id())->latest()->limit(8)->get();
@endphp
@unless($aiConfigured)<div class="callout callout-warning" role="status"><strong>OpenAI is not configured yet.</strong> <a class="card-link" href="/admin/ai-settings">Open AI Settings</a></div>
@endunless
@if($aiRun)
<x-card :title="'AI run #'.$aiRun->id.' · '.ucfirst($aiRun->feature)" :description="$aiRun->message">
@if(in_array($aiRun->status,['queued','running']))
<div class="card-body" x-data="{p:{{ $aiRun->progress }},m:@js($aiRun->message)}" x-init="const t=setInterval(async()=>{const r=await fetch('/admin/ai/runs/{{ $aiRun->id }}/status',{headers:{Accept:'application/json'}});const j=await r.json();p=j.progress;m=j.message;if(!['queued','running'].includes(j.status)){clearInterval(t);location.reload()}},2000)">
<progress max="100" :value="p" aria-label="AI progress"></progress><p class="muted text-xs mt-3" x-text="m||'Queued…'" aria-live="polite"></p><p class="muted text-xs">This runs in the background; you can leave and come back.</p></div>
@elseif($aiRun->status==='failed')
<div class="callout callout-danger" role="alert">{{ $aiRun->message }}</div>
@elseif($aiRun->status==='approved')
<div class="callout callout-success">{{ $aiRun->message }}</div>
@else
@php $res=$aiRun->result; @endphp
@if(!empty($res['errors']))<div class="callout callout-danger" role="alert"><strong>The AI could not fully resolve these after {{ $res['attempts'] }} attempts — it cannot be applied until fixed.</strong><ul>@foreach($res['errors'] as $e)<li>{{ $e }}</li>
@endforeach</ul></div>
@else<div class="callout callout-success"><x-icon name="check" :size="16"/> All checks passed (clashes, capacity, rules). Review and approve.</div>
@endif
@if(!empty($res['proposal']['explanation']))<div class="card-body"><h3>Why the AI chose this</h3><p>{{ $res['proposal']['explanation'] }}</p></div>
@endif
@if($aiRun->feature==='datesheet')
<x-calendar :events="$res['events']" title="Proposed date sheet"/>
@if(!empty($res['rules']['notes']))<p class="muted text-xs">Rules understood: {{ $res['rules']['notes'] }}</p>
@endif
<form method="POST" action="/admin/ai/runs/{{ $aiRun->id }}/revise" class="form-grid card-body">@csrf
<x-input name="instruction" label="Ask for a change" placeholder="e.g. Move Physics to Tuesday" maxlength="500" required/><x-button type="submit" variant="secondary" icon="sparkles">Revise with AI</x-button></form>
@elseif($aiRun->feature==='seating')
<div class="card-body"><p>{{ count($res['proposal']['assignments']??[]) }} students seated. Fine-tune by dragging seats on the room map after approval.</p></div>
@elseif($aiRun->feature==='duties')
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Teacher</th><th>Entry</th><th>Room</th></tr></thead><tbody>
@foreach($res['proposal']['duties']??[] as $d)<tr><td>{{ \App\Models\User::find($d['teacher_id'])?->name }}</td><td>#{{ $d['schedule_entry_id'] }}</td><td>{{ \App\Models\Room::find($d['room_id'])?->name }}</td></tr>
@endforeach</tbody></table></x-data-table>
@elseif($aiRun->feature==='import')
<form method="POST" action="/admin/ai/runs/{{ $aiRun->id }}/approve">@csrf
<div class="card-body"><p>Detected: <strong>{{ $res['target'] }}</strong> · {{ $res['counts']['ok'] }} ready · {{ $res['counts']['uncertain'] }} uncertain · {{ $res['counts']['error'] }} blocked</p><p class="muted text-xs">{{ $res['notes'] }}</p></div>
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Skip</th><th>Row</th><th>Values</th><th>Status</th></tr></thead><tbody>
@foreach($res['rows'] as $row)<tr><td><input type="checkbox" name="exclude[]" value="{{ $row['n'] }}" aria-label="Skip row {{ $row['n'] }}" @checked($row['status']==='error') @disabled($row['status']==='error')></td><td>{{ $row['n'] }}</td><td>{{ collect($row['data'])->except(['section_id','department_id','component_id'])->implode(' · ') }}</td><td><x-badge :status="$row['status']==='ok'?'Ready':($row['status']==='uncertain'?'Check':'Blocked')"/>@foreach($row['issues'] as $i)<div class="muted text-xs">{{ $i }}</div>
@endforeach</td></tr>
@endforeach</tbody></table></x-data-table>
<div class="card-body"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Import these records?" data-confirm-message="Checked rows are skipped. All other valid rows are written in one step." data-confirm-label="Import">Confirm import</x-button></div></form>
@endif
@if($aiRun->feature!=='import')
<form method="POST" action="/admin/ai/runs/{{ $aiRun->id }}/approve" class="card-body">@csrf
<x-button type="submit" icon="check" :disabled="!empty($res['errors'])" data-confirm="true" data-confirm-title="Apply this proposal?" data-confirm-message="The proposal is validated again and saved through the normal rules. The audit log records it." data-confirm-label="Approve and apply">Approve and apply</x-button></form>
@endif
@endif
</x-card>
@endif
@if(session('ai_draft'))@php $dr=session('ai_draft'); @endphp
<x-card title="Draft announcement" description="Edit if needed, then send."><form method="POST" action="/admin/ai/send" class="form-grid card-body">@csrf
<input type="hidden" name="kind" value="{{ $dr['kind'] }}"><input type="hidden" name="exam_id" value="{{ $dr['exam_id'] }}">
<x-input name="title" label="Title" :value="$dr['title']" maxlength="120" required/><label class="field"><span>Message</span><textarea name="message" rows="4" maxlength="800" required>{{ $dr['message'] }}</textarea></label>
<x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Send announcement?" data-confirm-message="This notifies every affected student or teacher." data-confirm-label="Send">Send announcement</x-button></form></x-card>
@endif
@if(session('ai_report'))@php $rep=session('ai_report'); $max=max(1,collect($rep['rows'])->max('value')); @endphp
<x-card :title="$rep['plan']['title']" :description="$rep['plan']['summary']"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ucfirst($rep['plan']['group_by']) }}</th><th>{{ str_replace('_',' ',$rep['plan']['metric']) }}</th><th>Results</th><th></th></tr></thead><tbody>
@forelse($rep['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}{{ $rep['unit'] }}</td><td>{{ $row['n'] }}</td><td style="width:40%"><progress max="{{ $max }}" value="{{ $row['value'] }}" aria-label="{{ $row['label'] }}"></progress></td></tr>
@empty<tr><td colspan="4">No results match this report in your current academic scope.</td></tr>
@endforelse</tbody></table></x-data-table><p class="muted text-xs card-body">Generated from live database records. The AI only chose the query plan.</p></x-card>
@endif
@if(session('ai_triage'))@php $tr=session('ai_triage'); @endphp
<x-card :title="$tr['kind']==='recheck'?'Rechecking triage':'Anomaly triage'" :description="$tr['summary']"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ $tr['kind']==='recheck'?'Request':'Flag' }}</th><th>{{ $tr['kind']==='recheck'?'Recommendation':'Priority' }}</th><th>Notes</th></tr></thead><tbody>
@foreach($tr['items'] as $i)<tr><td>#{{ $i['request_id']??$i['flag_id'] }}</td><td>{{ $i['recommendation']??$i['priority'] }}</td><td>{{ $i['rationale']??$i['comment'] }}</td></tr>
@endforeach</tbody></table></x-data-table><p class="muted text-xs card-body">Recommendations only — decide in the Rechecking queue or Analytics screens. Marks are never changed by AI.</p></x-card>
@endif
<div class="dashboard-middle">
<x-card title="1 · Generate a date sheet" description="Pick a draft examination and describe any rules in plain language."><form method="POST" action="/admin/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="datesheet">
<x-select name="exam_id" label="Draft examination">@foreach($aiExams->where('status','draft') as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select>
<label class="field"><span>Constraints (optional)</span><textarea name="constraints" rows="3" maxlength="1500" placeholder="No exams on Friday afternoon, max 2 papers per day for any class, gap before major subjects"></textarea></label>
<x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Generate with AI</x-button></form></x-card>
<x-card title="2 · Seating plan" description="Mixes classes so classmates never sit side by side."><form method="POST" action="/admin/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="seating">
<x-select name="entry_id" label="Scheduled paper">@foreach($aiEntries as $e)<option value="{{ $e->id }}">{{ $e->examCourse->course->code }} · {{ $e->starts_at->format('d M H:i') }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Generate seating</x-button></form></x-card>
<x-card title="3 · Invigilation duties" description="Fair assignment by availability and workload."><form method="POST" action="/admin/ai/start" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="duties">
<x-select name="exam_id" label="Examination">@foreach($aiExams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</x-select><x-button type="submit" icon="sparkles" :disabled="!$aiConfigured">Assign duties</x-button></form></x-card>
<x-card title="4 · Smart import" description="Upload Excel, CSV, PDF or a photo of a list. No template needed."><form method="POST" action="/admin/ai/start" enctype="multipart/form-data" class="form-grid card-body">@csrf<input type="hidden" name="feature" value="import">
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
