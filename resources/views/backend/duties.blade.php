
@if($role==='admin'&&$page['slug']==='room-availability-calendar')
@php
$uiRoomNames=\App\Models\Room::pluck('name','id');
@endphp

@php
$uiRoomEvents=\App\Models\RoomUnavailability::orderBy('starts_at')->limit(300)->get()->map(fn($interval)=>['id'=>$interval->id,'date'=>$interval->starts_at->toIso8601String(),'end'=>$interval->ends_at->toIso8601String(),'title'=>$uiRoomNames[$interval->room_id]??'Room','detail'=>$interval->reason,'room'=>null])->all();
@endphp

<x-calendar :events="$uiRoomEvents" title="Room unavailability calendar"/>
@elseif(in_array($page['kind'],['calendar'])||$page['slug']==='duty-calendar')
@php
$uiDutyEvents=collect($records->items())->map(fn($duty)=>['id'=>$duty->id,'date'=>$duty->scheduleEntry->starts_at->toIso8601String(),'end'=>$duty->scheduleEntry->ends_at->toIso8601String(),'title'=>$duty->scheduleEntry->examCourse->course->code,'detail'=>$duty->teacher->name.' · '.($duty->room?->name??$duty->scheduleEntry->room->name),'room'=>'teacher-'.$duty->teacher_id])->all();
@endphp

<x-calendar :events="$uiDutyEvents" title="Invigilation duty calendar"/>
@endif

@if($role==='admin')
<x-stepper :steps="['Affected duties','Eligible cover','Review & approve','Updated list']" :current="str_contains($page['slug'],'updated')?4:(str_contains($page['slug'],'approval')?3:(str_contains($page['slug'],'replacement')?2:1))"/>
@endif
@if($role==='admin')
<x-card class=""><h2>Assign paper duty</h2><p><a href="/admin/records?resource=users">Provision teacher / extra staff accounts</a> · <a href="/admin/records?resource=teacher-profiles">Configure reserve staff and workload</a></p><form method="POST" data-route-form="/workflow/duty/" class="form-grid">
@csrf
<x-form-field ><span>Paper / rooms</span><select data-route-id required>
@foreach(app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('rooms','examCourse.course')->get() as $s)<option value="{{ $s->id }}">{{ $s->examCourse->course->code }} / {{ $s->room_label }} / {{ $s->starts_at }}</option>
@endforeach</select></x-form-field>
<x-form-field ><span>Teacher or reserve staff</span><select name="teacher_id" required>
@foreach(\App\Models\User::where('role','teacher')->where('active',true)->with('teacherProfile')->get() as $t)<option value="{{ $t->id }}">{{ $t->name }} {{ $t->teacherProfile?->reserve?'· Reserve staff':'' }}</option>
@endforeach</select></x-form-field>
<x-form-field ><span>Room (blank uses primary room)</span><select name="room_id"><option value="">Primary room</option>
@foreach(\App\Models\Room::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>
@endforeach</select></x-form-field><label><input type="checkbox" name="reserve" value="1"> Standby duty (not primary invigilation cover)</label><x-button variant="primary" class="" type="submit">Check and assign</x-button></form></x-card>
@endif
<x-card class=""><h2>48-hour unavailability requests</h2><p>Submit at least 48 hours before the paper starts. Requests at the exact deadline are accepted. The teacher stays assigned until an administrator approves eligible reserve cover. Late requests are rejected by the server.</p>
@forelse($dutyRequests as $req)<article class="notification-item duty-request"><div class="duty-request-copy"><strong>{{ $req->teacher->name }} · {{ $req->duty->scheduleEntry->examCourse->course->code }}</strong><p>{{ $req->reason }}</p><p>Status: {{ $req->status }} · Submitted {{ $req->requested_at->format('d M Y H:i') }} · Deadline {{ $req->deadline_at->format('d M Y H:i') }} (Asia/Karachi)</p><p>Reserve cover: {{ $req->proposal?->candidate?->name??'Needs eligible reserve staff' }} · {{ $req->proposal?->status }}</p>
@if($req->decision_reason)<p>{{ $req->decision_reason }}</p>
@endif</div>
@if($role==='admin'&&$req->status==='pending'&&$req->proposal)
@php
    $reserveCandidates = app(\App\Services\DutyService::class)->candidates($req->duty->scheduleEntry,$req->proposal->id);
@endphp
<div class="reserve-review" aria-label="Reserve cover review"><div class="diff-comparison"><div><small>Current invigilator</small>{{ $req->teacher->name }}</div><div><small>Proposed reserve cover</small>{{ $req->proposal?->candidate?->name??'Not nominated' }}</div></div>
    <div class="reserve-review-heading"><span><x-icon name="shield" :size="18"/></span><div><h3>Review reserve cover</h3><p>The original duty stays assigned until cover is approved.</p></div></div>
    <form method="POST" action="/admin/ai/replacement/{{ $req->proposal->id }}" class="page-actions">@csrf<x-button type="submit" variant="secondary" icon="sparkles">Ask AI for best cover</x-button><x-button type="submit" variant="secondary" name="nominate" value="1">AI suggest &amp; nominate</x-button></form>
    <form class="reserve-nomination" method="POST" action="/workflow/replacement/{{ $req->proposal->id }}/nominate">
@csrf
        <x-form-field ><span>Eligible reserve staff</span><select name="candidate_id" required><option value="">Select available staff</option>
@foreach($reserveCandidates as $staff)<option value="{{ $staff->id }}" @selected($staff->id===$req->proposal->candidate_id)>{{ $staff->name }}</option>
@endforeach</select></x-form-field>
        <div class="reserve-form-footer"><small>{{ $reserveCandidates->isEmpty()?'No eligible staff available. Review staff availability and workload.':'Choose a candidate, then update the nomination.' }}</small><x-button variant="secondary" class="" type="submit" :disabled="$reserveCandidates->isEmpty()"><x-icon name="user" :size="15"/>Update nominee</x-button></div>
    </form>
    @if($req->proposal->status==='proposed')
    <form class="reserve-approval" method="POST" action="/workflow/replacement/{{ $req->proposal->id }}/approve" data-confirm="true" data-confirm-title="Approve reserve cover?" data-confirm-message="The candidate’s eligibility will be checked again. Approval replaces the original teacher’s duty with the nominated reserve cover." data-confirm-label="Approve cover">
@csrf
        <p>Availability and workload are checked again before approval.</p><x-button variant="primary" class="" type="submit"><x-icon name="check" :size="16"/>Approve reserve cover</x-button>
    </form>
    @else<p class="reserve-unavailable">No eligible reserve cover is currently proposed. Update the nomination after reviewing staff availability.</p>
@endif
    <form class="reserve-rejection" method="POST" action="/workflow/duty-request/{{ $req->id }}/reject" data-confirm="true" data-confirm-variant="warning" data-confirm-title="Reject this cover request?" data-confirm-message="The request will be rejected and the original duty will remain assigned." data-confirm-label="Reject request">
@csrf
        <x-form-field ><span>Rejection reason</span><textarea name="reason" required minlength="10" maxlength="2000" rows="3" placeholder="Explain why this request cannot be approved" aria-describedby="rejection-help-{{ $req->id }}"></textarea></x-form-field>
        <div class="reserve-form-footer"><small id="rejection-help-{{ $req->id }}">Rejecting keeps the original duty in place.</small><x-button variant="secondary" class="" type="submit"><x-icon name="close" :size="15"/>Reject request</x-button></div>
    </form>
</div>
@endif</article>
@empty<p class="empty-state">No duty requests.</p>
@endforelse
@if($dutyRequests->hasMorePages())<a class="btn btn-secondary" href="{{ $dutyRequests->nextPageUrl() }}">Older requests</a>
@endif @if($dutyRequests->previousPageUrl())<a class="btn btn-secondary" href="{{ $dutyRequests->previousPageUrl() }}">Newer requests</a>
@endif
</x-card>
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>Teacher / staff</th><th>Course</th><th>Room</th><th>Paper start</th><th>Status</th><th>Standby</th>
@if($role==='teacher')<th>Request deadline / action</th>
@endif</tr></thead><tbody>
@forelse($records as $d)<tr><td>{{ $d->teacher->name }}</td><td>{{ $d->scheduleEntry->examCourse->course->code }}</td><td>{{ $d->room?->name??$d->scheduleEntry->room->name }}</td><td>{{ $d->scheduleEntry->starts_at->format('d M Y H:i') }}</td><td><x-badge :status="ucfirst($d->status)"/></td><td>{{ $d->reserve?'Standby':'Primary cover' }}</td>
@if($role==='teacher')<td>
@php $deadline=$d->scheduleEntry->starts_at->copy()->subHours(48);$pending=\App\Models\DutyRequest::where('duty_assignment_id',$d->id)->where('status','pending')->exists(); @endphp <p>{{ $deadline->format('d M Y H:i') }} (Asia/Karachi)</p>
@if($d->status==='assigned'&&!$pending&&now()->lte($deadline))<form method="POST" action="/workflow/duty/{{ $d->id }}/request">
@csrf<textarea name="reason" required minlength="10" maxlength="2000" placeholder="Why are you unavailable?" aria-label="Unavailability reason for duty {{ $d->id }}"></textarea><x-button variant="primary" class="" type="submit">Request reserve cover</x-button></form>
@else<p>{{ $pending?'Awaiting review':($d->status==='replaced'?'Reserve cover approved':'Request deadline passed or duty inactive') }}</p>
@endif</td>
@endif</tr>
@empty<tr><td colspan="7">No duties.</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
<x-card class=""><h2>Future availability (without an assigned paper)</h2><p>This form creates duty requests for any affected assigned papers. General availability intervals also need 48 hours notice.</p><form method="POST" action="/workflow/availability" class="form-grid">
@csrf @if($role==='admin')<x-form-field ><span>Teacher / staff</span><select name="teacher_id" required>
@foreach(\App\Models\User::where('role','teacher')->get() as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>
@endforeach</select></x-form-field>
@endif<x-form-field ><span>From</span><input type="datetime-local" name="starts_at" required></x-form-field><x-form-field ><span>Until</span><input type="datetime-local" name="ends_at" required></x-form-field><x-form-field ><span>Reason</span><textarea name="reason" required minlength="10"></textarea></x-form-field><x-button variant="secondary" class="" type="submit">Submit availability request</x-button></form></x-card>
