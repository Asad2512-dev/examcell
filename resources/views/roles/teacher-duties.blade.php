@if((request()->attributes->get('ws')['key']??null)==='profile')
@include('backend.profile')
@include('roles.availability')
@else
<div class="role-flow-note"><strong>{{ ui_text('teacher.copy.check_your_duty_date_time_and_room') }}</strong><p>{{ ui_text('teacher.copy.if_you_cannot_attend_request_cover_at_least_48_hours_bef') }}</p></div>
@php
$personalDuties=collect($records->items())->sortBy(fn($d)=>[$d->status==='assigned'?0:1,$d->scheduleEntry->starts_at->timestamp]);
$upcomingDuties=$personalDuties->filter(fn($d)=>$d->scheduleEntry->ends_at->gte(now()));
$pastDuties=$personalDuties->reject(fn($d)=>$d->scheduleEntry->ends_at->gte(now()));
@endphp
<x-card title="{{ ui_text('teacher.copy.my_invigilation_duties') }}" description="Upcoming duties on this page. Past duties are available below. Times are PKT.">
<div class="role-task-list">
@forelse($upcomingDuties as $d)
@include('roles.duty-task')
@empty<x-empty-state title="{{ ui_text('teacher.copy.no_upcoming_duties_on_this_page') }}" description="Check other pages or look below for past duties." icon="shield"/>
@endforelse
</div>@include('backend.pagination')</x-card>
@if($pastDuties->isNotEmpty())
<x-disclosure title="{{ ui_text('teacher.copy.past_duties') }}" :description="$pastDuties->count().' earlier duties on this page.'"><div class="role-task-list">@foreach($pastDuties as $d)@include('roles.duty-task')@endforeach</div></x-disclosure>
@endif
<x-disclosure title="{{ ui_text('teacher.copy.my_cover_requests') }}" description="Track administrator decisions on your requests.">
<div class="role-task-list">@forelse($dutyRequests as $req)<article class="role-task"><div class="role-task-heading"><strong>{{ $req->duty->scheduleEntry->examCourse->course->code }}</strong><x-badge :status="ucwords(str_replace('_',' ',$req->status))"/></div><p>{{ $req->reason }}</p><small class="role-subline">Submitted {{ $req->requested_at->format('d M Y · H:i') }} PKT</small>@if($req->decision_reason)<p>{{ $req->decision_reason }}</p>@endif</article>@empty<x-empty-state title="{{ ui_text('teacher.copy.no_cover_requests') }}" description="Requests you submit from a duty will appear here." icon="history"/>@endforelse</div>
@if($dutyRequests->hasPages())<div class="card-body">{{ $dutyRequests->links() }}</div>@endif
</x-disclosure>
<p class="role-flow-note">{{ ui_text('teacher.copy.planning_future_leave') }} <a class="card-link" href="/teacher/profile">{{ ui_text('teacher.copy.update_my_availability') }}</a></p>
@endif
