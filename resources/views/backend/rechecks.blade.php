
@if($role==='student')<div class="role-flow-note"><strong>{{ ui_text('results.copy.request_a_review_from_your_course_result') }}</strong><p>{{ ui_text('results.copy.open_results_request_recheck_explain_the_concern_track_p') }}</p><x-button href="/student/results">{{ ui_text('results.copy.open_my_results') }}</x-button></div>
@endif
@if($role==='admin')@include('backend.filters')@endif
<x-card class=""><h2>{{ ui_text('results.copy.rechecking_requests') }}</h2>
@forelse($records as $rr)
<article class="notification-item"><div><strong>{{ $rr->student->name }} · {{ $rr->result->examCourse->course->code }}</strong><p>{{ $rr->reason }}</p><x-badge :status="ucwords(str_replace('_',' ',$rr->status))"/><p>{{ $rr->decision_reason }}</p><div class="status-tracker" aria-label="{{ ui_text('results.copy.rechecking_progress') }}"><span class="complete"><x-icon name="check" :size="12"/>{{ ui_text('results.copy.submitted') }}</span><span class="{{ in_array($rr->status,['under_review','approved','rejected'])?'complete':'' }}"><x-icon name="clock" :size="12"/>{{ ui_text('results.copy.under_review') }}</span><span class="{{ in_array($rr->status,['approved','rejected'])?'complete':'' }}"><x-icon name="shield" :size="12"/>{{ ui_text('results.copy.decision') }}</span></div></div>
@if($role==='admin'&&in_array($rr->status,['pending','under_review']))
<form class="review-action-form" method="POST" action="/workflow/recheck/{{ $rr->id }}/decide">
@csrf
    <x-form-field ><span>{{ ui_text('results.copy.decision') }}</span><select name="status"><option value="under_review">{{ ui_text('results.copy.under_review') }}</option><option value="approved">{{ ui_text('results.copy.approve_recheck') }}</option><option value="rejected">{{ ui_text('results.copy.reject') }}</option></select></x-form-field>
    <x-form-field ><span>{{ ui_text('results.copy.decision_reason') }}</span><textarea name="reason" minlength="10" required rows="3" placeholder="{{ ui_text('results.copy.explain_the_decision') }}"></textarea></x-form-field>
    <div class="form-actions"><x-button variant="primary" class="" type="submit">{{ ui_text('results.copy.record_decision') }}</x-button></div>
</form>
@endif
</article>
@empty<x-empty-state title="{{ ui_text('results.copy.no_rechecking_requests_yet') }}" description="Your submitted requests and decisions will appear here." icon="history"/>
@endforelse
@include('backend.pagination')
</x-card>
