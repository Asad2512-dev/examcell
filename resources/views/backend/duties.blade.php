@if($role==='teacher')
@include('roles.teacher-duties')
@else

@if($role==='admin'&&$page['slug']==='room-availability-calendar')
@php
$uiRoomNames=\App\Models\Room::pluck('name','id');
@endphp

@php
$uiRoomEvents=\App\Models\RoomUnavailability::orderBy('starts_at')->limit(300)->get()->map(fn($interval)=>['id'=>$interval->id,'date'=>$interval->starts_at->toIso8601String(),'end'=>$interval->ends_at->toIso8601String(),'title'=>$uiRoomNames[$interval->room_id]??'Room','detail'=>$interval->reason,'room'=>null])->all();
@endphp

<x-calendar :events="$uiRoomEvents" title="{{ ui_text('examinations.copy.room_unavailability_calendar') }}"/>
@elseif(in_array($page['kind'],['calendar'])||$page['slug']==='duty-calendar')
@php
$uiDutyEvents=collect($records->items())->map(fn($duty)=>['id'=>$duty->id,'date'=>$duty->scheduleEntry->starts_at->toIso8601String(),'end'=>$duty->scheduleEntry->ends_at->toIso8601String(),'title'=>$duty->scheduleEntry->examCourse->course->code,'detail'=>$duty->teacher->name.' · '.($duty->room?->name??$duty->scheduleEntry->room->name),'room'=>'teacher-'.$duty->teacher_id])->all();
@endphp

<x-calendar :events="$uiDutyEvents" title="{{ ui_text('examinations.copy.invigilation_duty_calendar') }}"/>
@endif

@if($role==='admin')
<x-stepper :steps="['Affected duties','Eligible cover','Review & approve','Updated list']" :current="str_contains($page['slug'],'updated')?4:(str_contains($page['slug'],'approval')?3:(str_contains($page['slug'],'replacement')?2:1))"/>
@endif
@if($role==='admin')
<x-card class=""><h2>{{ ui_text('examinations.copy.assign_paper_duty') }}</h2><p><a href="/admin/records?resource=users">{{ ui_text('examinations.copy.provision_teacher_extra_staff_accounts') }}</a> · <a href="/admin/records?resource=teacher-profiles">{{ ui_text('examinations.copy.configure_reserve_staff_and_workload') }}</a></p><form method="POST" data-route-form="/workflow/duty/" class="form-grid">
@csrf
<x-form-field ><span>{{ ui_text('examinations.copy.paper_rooms') }}</span><select data-route-id required>
@foreach(app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('rooms','examCourse.course')->get() as $s)<option value="{{ $s->id }}">{{ $s->examCourse->course->code }} / {{ $s->room_label }} / {{ $s->starts_at }}</option>
@endforeach</select></x-form-field>
<x-form-field ><span>{{ ui_text('examinations.copy.teacher_or_reserve_staff') }}</span><select name="teacher_id" required>
@foreach(\App\Models\User::where('role','teacher')->where('active',true)->with('teacherProfile')->get() as $t)<option value="{{ $t->id }}">{{ $t->name }} {{ $t->teacherProfile?->reserve?'· Reserve staff':'' }}</option>
@endforeach</select></x-form-field>
<x-form-field ><span>{{ ui_text('examinations.copy.room_blank_uses_primary_room') }}</span><select name="room_id"><option value="">{{ ui_text('examinations.copy.primary_room') }}</option>
@foreach(\App\Models\Room::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>
@endforeach</select></x-form-field><label><input type="checkbox" name="reserve" value="1"> {{ ui_text('examinations.copy.standby_duty_not_primary_invigilation_cover') }}</label><x-button variant="primary" class="" type="submit">{{ ui_text('examinations.copy.check_and_assign') }}</x-button></form></x-card>
@endif
<x-card class=""><h2>{{ ui_text('examinations.copy.48_hour_unavailability_requests') }}</h2><p>{{ ui_text('examinations.copy.submit_at_least_48_hours_before_the_paper_starts_request') }}</p>
@forelse($dutyRequests as $req)<article class="notification-item duty-request"><div class="duty-request-copy"><strong>{{ $req->teacher->name }} · {{ $req->duty->scheduleEntry->examCourse->course->code }}</strong><p>{{ $req->reason }}</p><p>Status: {{ $req->status }} · Submitted {{ $req->requested_at->format('d M Y H:i') }} · Deadline {{ $req->deadline_at->format('d M Y H:i') }} (Asia/Karachi)</p><p>Reserve cover: {{ $req->proposal?->candidate?->name??'Needs eligible reserve staff' }} · {{ $req->proposal?->status }}</p>
@if($req->decision_reason)<p>{{ $req->decision_reason }}</p>
@endif</div>
@if($role==='admin'&&$req->status==='pending'&&$req->proposal)
@php
    $reserveCandidates = app(\App\Services\DutyService::class)->candidates($req->duty->scheduleEntry,$req->proposal->id);
@endphp
<div class="reserve-review" aria-label="{{ ui_text('examinations.copy.reserve_cover_review') }}"><div class="diff-comparison"><div><small>{{ ui_text('examinations.copy.current_invigilator') }}</small>{{ $req->teacher->name }}</div><div><small>{{ ui_text('examinations.copy.proposed_reserve_cover') }}</small>{{ $req->proposal?->candidate?->name??'Not nominated' }}</div></div>
    <div class="reserve-review-heading"><span><x-icon name="shield" :size="18"/></span><div><h3>{{ ui_text('examinations.copy.review_reserve_cover') }}</h3><p>{{ ui_text('examinations.copy.the_original_duty_stays_assigned_until_cover_is_approved') }}</p></div></div>
    <form method="POST" action="/admin/ai/replacement/{{ $req->proposal->id }}" class="page-actions">@csrf<x-button type="submit" variant="secondary" icon="sparkles">{{ ui_text('examinations.copy.ask_ai_for_best_cover') }}</x-button><x-button type="submit" variant="secondary" name="nominate" value="1">{{ ui_text('examinations.copy.ai_suggest_nominate') }}</x-button></form>
    <form class="reserve-nomination" method="POST" action="/workflow/replacement/{{ $req->proposal->id }}/nominate">
@csrf
        <x-form-field ><span>{{ ui_text('examinations.copy.eligible_reserve_staff') }}</span><select name="candidate_id" required><option value="">{{ ui_text('examinations.copy.select_available_staff') }}</option>
@foreach($reserveCandidates as $staff)<option value="{{ $staff->id }}" @selected($staff->id===$req->proposal->candidate_id)>{{ $staff->name }}</option>
@endforeach</select></x-form-field>
        <div class="reserve-form-footer"><small>{{ $reserveCandidates->isEmpty()?'No eligible staff available. Review staff availability and workload.':'Choose a candidate, then update the nomination.' }}</small><x-button variant="secondary" class="" type="submit" :disabled="$reserveCandidates->isEmpty()"><x-icon name="user" :size="15"/>{{ ui_text('examinations.copy.update_nominee') }}</x-button></div>
    </form>
    @if($req->proposal->status==='proposed')
    <form class="reserve-approval" method="POST" action="/workflow/replacement/{{ $req->proposal->id }}/approve" data-confirm="true" data-confirm-title="{{ ui_text('examinations.copy.approve_reserve_cover_e595e6') }}" data-confirm-message="{{ ui_text('examinations.copy.the_candidates_eligibility_will_be_checked_again_approva') }}" data-confirm-label="{{ ui_text('examinations.copy.approve_cover') }}">
@csrf
        <p>{{ ui_text('examinations.copy.availability_and_workload_are_checked_again_before_appro') }}</p><x-button variant="primary" class="" type="submit"><x-icon name="check" :size="16"/>{{ ui_text('examinations.copy.approve_reserve_cover') }}</x-button>
    </form>
    @else<p class="reserve-unavailable">{{ ui_text('examinations.copy.no_eligible_reserve_cover_is_currently_proposed_update_t') }}</p>
@endif
    <form class="reserve-rejection" method="POST" action="/workflow/duty-request/{{ $req->id }}/reject" data-confirm="true" data-confirm-variant="warning" data-confirm-title="{{ ui_text('examinations.copy.reject_this_cover_request') }}" data-confirm-message="{{ ui_text('examinations.copy.the_request_will_be_rejected_and_the_original_duty_will') }}" data-confirm-label="{{ ui_text('examinations.copy.reject_request') }}">
@csrf
        <x-form-field ><span>{{ ui_text('examinations.copy.rejection_reason') }}</span><textarea name="reason" required minlength="10" maxlength="2000" rows="3" placeholder="{{ ui_text('examinations.copy.explain_why_this_request_cannot_be_approved') }}" aria-describedby="rejection-help-{{ $req->id }}"></textarea></x-form-field>
        <div class="reserve-form-footer"><small id="rejection-help-{{ $req->id }}">{{ ui_text('examinations.copy.rejecting_keeps_the_original_duty_in_place') }}</small><x-button variant="secondary" class="" type="submit"><x-icon name="close" :size="15"/>{{ ui_text('examinations.copy.reject_request') }}</x-button></div>
    </form>
</div>
@endif</article>
@empty<p class="empty-state">{{ ui_text('examinations.copy.no_duty_requests') }}</p>
@endforelse
@if($dutyRequests->hasMorePages())<a class="btn btn-secondary" href="{{ $dutyRequests->nextPageUrl() }}">{{ ui_text('examinations.copy.older_requests') }}</a>
@endif @if($dutyRequests->previousPageUrl())<a class="btn btn-secondary" href="{{ $dutyRequests->previousPageUrl() }}">{{ ui_text('examinations.copy.newer_requests') }}</a>
@endif
</x-card>
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('examinations.copy.teacher_staff') }}</th><th>{{ ui_text('examinations.copy.course') }}</th><th>{{ ui_text('examinations.copy.room') }}</th><th>{{ ui_text('examinations.copy.paper_start') }}</th><th>{{ ui_text('examinations.copy.status') }}</th><th>{{ ui_text('examinations.copy.standby') }}</th>
@if($role==='teacher')<th>{{ ui_text('examinations.copy.request_deadline_action') }}</th>
@endif</tr></thead><tbody>
@forelse($records as $d)<tr><td>{{ $d->teacher->name }}</td><td>{{ $d->scheduleEntry->examCourse->course->code }}</td><td>{{ $d->room?->name??$d->scheduleEntry->room->name }}</td><td>{{ $d->scheduleEntry->starts_at->format('d M Y H:i') }}</td><td><x-badge :status="ucfirst($d->status)"/></td><td>{{ $d->reserve?'Standby':'Primary cover' }}</td>
@if($role==='teacher')<td>
@php $deadline=$d->scheduleEntry->starts_at->copy()->subHours(48);$pending=\App\Models\DutyRequest::where('duty_assignment_id',$d->id)->where('status','pending')->exists(); @endphp <p>{{ $deadline->format('d M Y H:i') }} (Asia/Karachi)</p>
@if($d->status==='assigned'&&!$pending&&now()->lte($deadline))<form method="POST" action="/workflow/duty/{{ $d->id }}/request">
@csrf<textarea name="reason" required minlength="10" maxlength="2000" placeholder="{{ ui_text('examinations.copy.why_are_you_unavailable') }}" aria-label="Unavailability reason for duty {{ $d->id }}"></textarea><x-button variant="primary" class="" type="submit">{{ ui_text('examinations.copy.request_reserve_cover') }}</x-button></form>
@else<p>{{ $pending?'Awaiting review':($d->status==='replaced'?'Reserve cover approved':'Request deadline passed or duty inactive') }}</p>
@endif</td>
@endif</tr>
@empty<tr><td colspan="7">{{ ui_text('examinations.copy.no_duties') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
<x-card class=""><h2>{{ ui_text('examinations.copy.future_availability_without_an_assigned_paper') }}</h2><p>{{ ui_text('examinations.copy.this_form_creates_duty_requests_for_any_affected_assigne') }}</p><form method="POST" action="/workflow/availability" class="form-grid">
@csrf @if($role==='admin')<x-form-field ><span>{{ ui_text('examinations.copy.teacher_staff') }}</span><select name="teacher_id" required>
@foreach(\App\Models\User::where('role','teacher')->get() as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>
@endforeach</select></x-form-field>
@endif<x-form-field ><span>{{ ui_text('examinations.copy.from') }}</span><input type="datetime-local" name="starts_at" required></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.until') }}</span><input type="datetime-local" name="ends_at" required></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.reason') }}</span><textarea name="reason" required minlength="10"></textarea></x-form-field><x-button variant="secondary" class="" type="submit">{{ ui_text('examinations.copy.submit_availability_request') }}</x-button></form></x-card>

@endif
