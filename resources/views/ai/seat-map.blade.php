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
<div class="card-body sm2-panel">
<div class="sm2-head">
@if(count($smSlots)>1)<form method="GET" class="sm2-picker" aria-label="{{ ui_text('ai.copy.choose_exam_slot') }}">@foreach($smQuery as $k=>$v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
@endforeach
<label class="field"><span>{{ ui_text('ai.copy.exam_slot_date_time_papers') }}</span><select name="entry" onchange="this.form.submit()">
@foreach(collect($smSlots)->groupBy('date') as $date=>$daySlots)<optgroup label="{{ \Carbon\Carbon::parse($date)->format('l d M Y') }}">@foreach($daySlots as $slot)<option value="{{ $slot['entry'] }}" @selected(collect($smData['papers'])->pluck('id')->contains($slot['entry']))>{{ $slot['time'] }} — {{ \Illuminate\Support\Str::after($slot['label'],'— ') }}</option>
@endforeach</optgroup>
@endforeach</select></label><noscript><button class="btn btn-secondary btn-small">{{ ui_text('ai.copy.open') }}</button></noscript></form>
@endif
@if($smUser->role==='student'&&$smData['directions'])<p class="sm2-directions" role="note"><x-icon name="home" :size="18"/><span><strong>{{ ui_text('ai.copy.your_seat') }}</strong> {{ $smData['directions'] }}</span></p>@endif
@if($smUser->role==='admin')
<form method="POST" action="/ai/start" class="sm2-ai">@csrf<input type="hidden" name="feature" value="seating"><input type="hidden" name="entry_id" value="{{ $smEntry->id }}">
<x-button type="submit" icon="sparkles" :disabled="$smData['locked']||!app(\App\Services\AI\AiSettings::class)->keyConfigured()">{{ $smHasSeats?'AI Regenerate':'AI Generate' }}</x-button></form>
</div>
@if($smData['locked'])<p class="sm2-note" role="note"><x-icon name="lock" :size="15"/> {{ ui_text('ai.copy.a_paper_in_this_slot_is_published_so_seating_is_locked_c') }}</p>@else<p class="sm2-note"><x-icon name="sparkles" :size="15"/> {{ ui_text('ai.copy.ai_shows_a_before_after_preview_nothing_changes_until_yo') }}</p>@endif
@else
</div>
@endif
@if($smUser->role!=='student'&&$smHasSeats)<details class="sm2-prints"><summary class="card-link">{{ ui_text('ai.copy.print_room_plans_door_notices_and_attendance_sheets') }}</summary><table class="data-table"><tbody>
@foreach($smData['rooms'] as $room)<tr><td>{{ $room['name'] }} · {{ $room['used'] }}/{{ $room['capacity'] }}</td><td><div class="page-actions">
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=map">{{ ui_text('ai.copy.room_plan_a4') }}</a>
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=door">{{ ui_text('ai.copy.door_notice') }}</a>
<a class="btn btn-secondary btn-small" target="_blank" href="/seat-map/{{ $smEntry->id }}/room/{{ $room['id'] }}/print?type=attendance">{{ ui_text('ai.copy.attendance_sheet') }}</a></div></td></tr>
@endforeach</tbody></table></details>@endif
<div data-seatmap id="seatmap-main" aria-label="{{ ui_text('ai.copy.graphical_seating_plan') }}"></div>
@if($smData['editable']&&$smHasSeats)<p class="muted text-xs">{{ ui_text('ai.copy.drag_a_student_onto_another_seat_to_move_or_swap_green_a') }}</p>@endif
</div></x-card>
<script src="/js/seat-map.js?v={{ filemtime(public_path('js/seat-map.js')) }}"></script>
<script>window.examcellSeatMap=SeatMap.mount(document.getElementById('seatmap-main'), @json($smData));</script>
@else
<x-card title="{{ ui_text('ai.copy.seating_plan') }}"><x-empty-state title="{{ ui_text('ai.copy.no_seating_to_show_yet') }}" :description="$smUser->role==='admin'?'Schedule papers first, then generate seating here.':'Seats appear here once the seating plan is published.'"/></x-card>
@endif
