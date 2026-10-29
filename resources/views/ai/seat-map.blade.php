@php
$smUser=auth()->user();
$smSvc=app(\App\Services\SeatMapService::class);
$smSlots=$smSvc->slots($smUser);
$smEntryId=(int)request('entry');
if(!$smEntryId||!$smSvc->visibleEntries($smUser)->contains('id',$smEntryId)){$smEntryId=$smSlots[0]['entry']??null;}
$smEntry=$smEntryId?\App\Models\ScheduleEntry::with('examCourse.course')->find($smEntryId):null;
$smData=$smEntry?$smSvc->data($smUser,$smEntry):null;
$smHasSeats=$smData&&count($smData['seats'])>0;
$smQuery=collect(request()->query())->except(['entry','run'])->all();
@endphp
<link rel="stylesheet" href="/css/seat-map.css?v={{ filemtime(public_path('css/seat-map.css')) }}">
@if($smData)
<x-card :title="$smUser->role==='student'?'Your seat':($smUser->role==='teacher'?'Rooms you invigilate or teach':'Seating plan')" :description="$smData['title'].' · '.collect($smData['papers'])->map(fn($p)=>$p['course'].' ('.$p['section'].')')->implode(', ')">
<div class="card-body">
@if(count($smSlots)>1)<form method="GET" class="sm2-picker" aria-label="Choose exam slot">@foreach($smQuery as $k=>$v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
@endforeach
<label class="field"><span>Exam slot (date · time · papers)</span><select name="entry" onchange="this.form.submit()">
@foreach(collect($smSlots)->groupBy('date') as $date=>$daySlots)<optgroup label="{{ \Carbon\Carbon::parse($date)->format('l d M Y') }}">@foreach($daySlots as $slot)<option value="{{ $slot['entry'] }}" @selected(collect($smData['papers'])->pluck('id')->contains($slot['entry']))>{{ $slot['time'] }} — {{ \Illuminate\Support\Str::after($slot['label'],'— ') }}</option>
@endforeach</optgroup>
@endforeach</select></label><noscript><button class="btn btn-secondary btn-small">Open</button></noscript></form>
@endif
@if($smUser->role==='student'&&$smData['directions'])<p class="sm2-directions" role="note"><x-icon name="home" :size="18"/><span><strong>Your seat:</strong> {{ $smData['directions'] }}</span></p>@endif
@if($smUser->role==='admin')
<div class="page-actions">
<form method="POST" action="/ai/start">@csrf<input type="hidden" name="feature" value="seating"><input type="hidden" name="entry_id" value="{{ $smEntry->id }}">
<x-button type="submit" icon="sparkles" :disabled="$smData['locked']||!app(\App\Services\AI\AiSettings::class)->keyConfigured()">{{ $smHasSeats?'AI Regenerate':'AI Generate' }}</x-button></form>
@if($smData['locked'])<span class="muted text-xs">A paper in this slot is published — seating is locked. Create a revision to change it.</span>@else<span class="muted text-xs">You will see a before/after preview and approve it.</span>@endif
</div>
@endif
@if($smUser->role!=='student'&&$smHasSeats)<div class="page-actions" aria-label="Print">@foreach($smData['rooms'] as $room)<span class="muted text-xs">{{ $room['name'] }}:</span>
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=map">Room plan (A4)</a>
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=door">Door notice</a>
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=attendance">Attendance sheet</a>
@endforeach</div>@endif
<div data-seatmap id="seatmap-main" aria-label="Graphical seating plan"></div>
@if($smData['editable']&&$smHasSeats)<p class="muted text-xs">Drag a student onto another seat to move or swap. Green = allowed, red = not allowed (reason shown) — the server checks every move again.</p>@endif
</div></x-card>
<script src="/js/seat-map.js?v={{ filemtime(public_path('js/seat-map.js')) }}"></script>
<script>SeatMap.mount(document.getElementById('seatmap-main'), @json($smData));</script>
@else
<x-card title="Seating plan"><x-empty-state title="No seating to show yet" :description="$smUser->role==='admin'?'Schedule papers first, then generate seating here.':'Seats appear here once the seating plan is published.'"/></x-card>
@endif
