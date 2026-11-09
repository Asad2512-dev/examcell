@php
$aiRun=request('run')?\App\Models\AiRun::where('user_id',auth()->id())->find(request('run')):null;
@endphp
@if($aiRun)
<x-card :title="'AI run #'.$aiRun->id.' · '.ucfirst($aiRun->feature)" :description="$aiRun->message">
@if(in_array($aiRun->status,['queued','running']))
<div class="card-body" x-data="{p:{{ $aiRun->progress }},m:@js($aiRun->message)}" x-init="const t=setInterval(async()=>{const r=await fetch('/ai/runs/{{ $aiRun->id }}/status',{headers:{Accept:'application/json'}});const j=await r.json();p=j.progress;m=j.message;if(!['queued','running'].includes(j.status)){clearInterval(t);location.reload()}},2000)">
<progress max="100" :value="p" aria-label="{{ ui_text('ai.copy.ai_progress') }}"></progress><p class="muted text-xs mt-3" x-text="m||'Queued…'" aria-live="polite"></p><p class="muted text-xs">{{ ui_text('ai.copy.this_runs_in_the_background_you_can_leave_and_come_back') }}</p></div>
@elseif($aiRun->status==='failed')
<div class="callout callout-danger" role="alert">{{ $aiRun->message }}</div>
@elseif($aiRun->status==='approved')
<div class="callout callout-success">{{ $aiRun->message }}</div>
@else
@php $res=$aiRun->result; @endphp
@if(!empty($res['errors']))<div class="callout callout-danger" role="alert"><strong>The AI could not fully resolve these after {{ $res['attempts'] }} attempts — it cannot be applied until fixed.</strong><ul>@foreach($res['errors'] as $e)<li>{{ $e }}</li>
@endforeach</ul></div>
@else<div class="callout callout-success"><x-icon name="check" :size="16"/> {{ ui_text('ai.copy.all_checks_passed_clashes_capacity_rules_review_and_appr') }}</div>
@endif
@if(!empty($res['proposal']['explanation']))<div class="card-body"><h3>{{ ui_text('ai.copy.why_the_ai_chose_this') }}</h3><p>{{ $res['proposal']['explanation'] }}</p></div>
@endif
@if($aiRun->feature==='datesheet')
<x-calendar :events="$res['events']" title="{{ ui_text('ai.copy.proposed_date_sheet') }}"/>
@if(!empty($res['rules']['notes']))<p class="muted text-xs">Rules understood: {{ $res['rules']['notes'] }}</p>
@endif
<form method="POST" action="/admin/ai/runs/{{ $aiRun->id }}/revise" class="form-grid card-body">@csrf
<x-input name="instruction" label="Ask for a change" placeholder="{{ ui_text('ai.copy.e_g_move_physics_to_tuesday') }}" maxlength="500" required/><x-button type="submit" variant="secondary" icon="sparkles">{{ ui_text('ai.copy.revise_with_ai') }}</x-button></form>
@elseif($aiRun->feature==='seating')
@php
$cmpEntry=\App\Models\ScheduleEntry::find($aiRun->input['entry_id']??0);
$cmpSvc=app(\App\Services\SeatMapService::class);
$cmpBefore=$cmpEntry?$cmpSvc->data(auth()->user(),$cmpEntry):null;
$cmpAfter=$cmpEntry?$cmpSvc->data(auth()->user(),$cmpEntry,$res['proposal']['assignments']??[]):null;
@endphp
<div class="card-body"><p>{{ count($res['proposal']['assignments']??[]) }} students seated across {{ count($cmpAfter['papers']??[]) }} paper(s). Compare before and after, then approve.</p>
@if($cmpAfter)<link rel="stylesheet" href="/css/seat-map.css?v={{ filemtime(public_path('css/seat-map.css')) }}">
<div class="sm2-compare"><div><h3>Before{{ count($cmpBefore['seats'])?'':' (no seats yet)' }}</h3><div id="cmp-before"></div></div><div><h3>{{ ui_text('ai.copy.after_ai_proposal') }}</h3><div id="cmp-after"></div></div></div>
<script src="/js/seat-map.js?v={{ filemtime(public_path('js/seat-map.js')) }}"></script>
<script>SeatMap.mount(document.getElementById('cmp-before'), @json($cmpBefore), {static:true});SeatMap.mount(document.getElementById('cmp-after'), @json($cmpAfter), {static:true});</script>
@endif</div>
@elseif($aiRun->feature==='duties')
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('ai.copy.teacher') }}</th><th>{{ ui_text('ai.copy.entry') }}</th><th>{{ ui_text('ai.copy.room') }}</th></tr></thead><tbody>
@foreach($res['proposal']['duties']??[] as $d)<tr><td>{{ \App\Models\User::find($d['teacher_id'])?->name }}</td><td>#{{ $d['schedule_entry_id'] }}</td><td>{{ \App\Models\Room::find($d['room_id'])?->name }}</td></tr>
@endforeach</tbody></table></x-data-table>
@elseif($aiRun->feature==='import')
<form method="POST" action="/ai/runs/{{ $aiRun->id }}/approve">@csrf
<div class="card-body"><p>{{ ui_text('ai.copy.detected') }} <strong>{{ $res['target'] }}</strong> · {{ $res['counts']['ok'] }} ready · {{ $res['counts']['uncertain'] }} uncertain · {{ $res['counts']['error'] }} blocked</p><p class="muted text-xs">{{ $res['notes'] }}</p></div>
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('ai.copy.skip') }}</th><th>{{ ui_text('ai.copy.row') }}</th><th>{{ ui_text('ai.copy.values') }}</th><th>{{ ui_text('ai.copy.status') }}</th></tr></thead><tbody>
@foreach($res['rows'] as $row)<tr><td><input type="checkbox" name="exclude[]" value="{{ $row['n'] }}" aria-label="Skip row {{ $row['n'] }}" @checked($row['status']==='error') @disabled($row['status']==='error')></td><td>{{ $row['n'] }}</td><td>{{ collect($row['data'])->except(['section_id','department_id','component_id'])->implode(' · ') }}</td><td><x-badge :status="$row['status']==='ok'?'Ready':($row['status']==='uncertain'?'Check':'Blocked')"/>@foreach($row['issues'] as $i)<div class="muted text-xs">{{ $i }}</div>
@endforeach</td></tr>
@endforeach</tbody></table></x-data-table>
<div class="card-body"><x-button type="submit" icon="check" data-confirm="true" data-confirm-title="{{ ui_text('ai.copy.import_these_records') }}" data-confirm-message="{{ ui_text('ai.copy.checked_rows_are_skipped_all_other_valid_rows_are_writte') }}" data-confirm-label="{{ ui_text('ai.copy.import') }}">{{ ui_text('ai.copy.confirm_import') }}</x-button></div></form>
@endif
@if($aiRun->feature!=='import')
<form method="POST" action="/ai/runs/{{ $aiRun->id }}/approve" class="card-body">@csrf
<x-button type="submit" icon="check" :disabled="!empty($res['errors'])" data-confirm="true" data-confirm-title="{{ ui_text('ai.copy.apply_this_proposal') }}" data-confirm-message="{{ ui_text('ai.copy.the_proposal_is_validated_again_and_saved_through_the_no') }}" data-confirm-label="{{ ui_text('ai.copy.approve_and_apply') }}">{{ ui_text('ai.copy.approve_and_apply') }}</x-button></form>
@endif
@endif
</x-card>
@endif
