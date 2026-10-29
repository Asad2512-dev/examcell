@php
$aiRun=request('run')?\App\Models\AiRun::where('user_id',auth()->id())->find(request('run')):null;
@endphp
@if($aiRun)
<x-card :title="'AI run #'.$aiRun->id.' · '.ucfirst($aiRun->feature)" :description="$aiRun->message">
@if(in_array($aiRun->status,['queued','running']))
<div class="card-body" x-data="{p:{{ $aiRun->progress }},m:@js($aiRun->message)}" x-init="const t=setInterval(async()=>{const r=await fetch('/ai/runs/{{ $aiRun->id }}/status',{headers:{Accept:'application/json'}});const j=await r.json();p=j.progress;m=j.message;if(!['queued','running'].includes(j.status)){clearInterval(t);location.reload()}},2000)">
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
<form method="POST" action="/ai/runs/{{ $aiRun->id }}/approve">@csrf
<div class="card-body"><p>Detected: <strong>{{ $res['target'] }}</strong> · {{ $res['counts']['ok'] }} ready · {{ $res['counts']['uncertain'] }} uncertain · {{ $res['counts']['error'] }} blocked</p><p class="muted text-xs">{{ $res['notes'] }}</p></div>
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>Skip</th><th>Row</th><th>Values</th><th>Status</th></tr></thead><tbody>
@foreach($res['rows'] as $row)<tr><td><input type="checkbox" name="exclude[]" value="{{ $row['n'] }}" aria-label="Skip row {{ $row['n'] }}" @checked($row['status']==='error') @disabled($row['status']==='error')></td><td>{{ $row['n'] }}</td><td>{{ collect($row['data'])->except(['section_id','department_id','component_id'])->implode(' · ') }}</td><td><x-badge :status="$row['status']==='ok'?'Ready':($row['status']==='uncertain'?'Check':'Blocked')"/>@foreach($row['issues'] as $i)<div class="muted text-xs">{{ $i }}</div>
@endforeach</td></tr>
@endforeach</tbody></table></x-data-table>
<div class="card-body"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Import these records?" data-confirm-message="Checked rows are skipped. All other valid rows are written in one step." data-confirm-label="Import">Confirm import</x-button></div></form>
@endif
@if($aiRun->feature!=='import')
<form method="POST" action="/ai/runs/{{ $aiRun->id }}/approve" class="card-body">@csrf
<x-button type="submit" icon="check" :disabled="!empty($res['errors'])" data-confirm="true" data-confirm-title="Apply this proposal?" data-confirm-message="The proposal is validated again and saved through the normal rules. The audit log records it." data-confirm-label="Approve and apply">Approve and apply</x-button></form>
@endif
@endif
</x-card>
@endif
