
@if($role==='student')<p class="callout">Open My Results to request a recheck of one of your published course results.</p>
@endif
@include('backend.filters')
<x-card class=""><h2>Rechecking requests</h2>
@forelse($records as $rr)
<article class="notification-item"><div><strong>{{ $rr->student->name }} · {{ $rr->result->examCourse->course->code }}</strong><p>{{ $rr->reason }}</p><x-badge :status="ucwords(str_replace('_',' ',$rr->status))"/><p>{{ $rr->decision_reason }}</p><div class="status-tracker" aria-label="Rechecking progress"><span class="complete"><x-icon name="check" :size="12"/>Submitted</span><span class="{{ in_array($rr->status,['under_review','approved','rejected'])?'complete':'' }}"><x-icon name="clock" :size="12"/>Under review</span><span class="{{ in_array($rr->status,['approved','rejected'])?'complete':'' }}"><x-icon name="shield" :size="12"/>Decision</span></div></div>
@if($role==='admin'&&in_array($rr->status,['pending','under_review']))
<form class="review-action-form" method="POST" action="/workflow/recheck/{{ $rr->id }}/decide">
@csrf
    <x-form-field ><span>Decision</span><select name="status"><option value="under_review">Under review</option><option value="approved">Approve recheck</option><option value="rejected">Reject</option></select></x-form-field>
    <x-form-field ><span>Decision reason</span><textarea name="reason" minlength="10" required rows="3" placeholder="Explain the decision"></textarea></x-form-field>
    <div class="form-actions"><x-button variant="primary" class="" type="submit">Record decision</x-button></div>
</form>
@endif
</article>
@empty<p class="empty-state">No requests.</p>
@endforelse
@include('backend.pagination')
</x-card>
