
@if($role==='admin')<x-card class=""><h2>Request a published mark correction</h2><form method="POST" data-route-form="/workflow/correction/" class="form-grid">
@csrf<x-form-field ><span>Published mark record</span><select data-route-id required>
@foreach(app(\App\Services\AcademicContext::class)->apply(\App\Models\Mark::query())->with('student','component.examCourse.course')->whereHas('component.examCourse.examination.batch',fn($q)=>$q->where('status','published'))->get() as $m)<option value="{{ $m->id }}">#{{ $m->id }} · {{ $m->student->name }} · {{ $m->component->examCourse->course->code }} · {{ $m->component->name }} · {{ $m->score??'absent' }}</option>
@endforeach</select></x-form-field><x-form-field ><span>New score (blank if absent)</span><input type="number" step="0.01" min="0" name="score"></x-form-field><label><input type="checkbox" name="absent" value="1"> Absent</label><x-form-field ><span>Reason</span><textarea name="reason" minlength="10" required></textarea></x-form-field><x-button variant="primary" class="" type="submit">Request approval</x-button></form></x-card><x-card class=""><h2>Correction approval history</h2>
@forelse($corrections as $p)<div class="notification-item"><div><strong>{{ $p->mark->student->name }} · mark #{{ $p->mark_id }}</strong><div class="diff-comparison"><div><small>Previous score</small>{{ $p->old_absent?'Absent':$p->old_score }}</div><div><small>Proposed score</small>{{ $p->new_absent?'Absent':$p->new_score }}</div></div><x-badge :status="ucfirst($p->status)"/><p>{{ $p->reason }}</p><small>Requested by #{{ $p->requested_by }} · Approved by {{ $p->approved_by??'—' }}</small></div>
@if($p->status==='pending'&&$p->requested_by!==auth()->id())<form method="POST" action="/workflow/correction/{{ $p->id }}/approve">
@csrf<x-button variant="primary" class="" type="submit">Approve and recalculate</x-button></form>
@endif</div>
@empty<p>No correction records.</p>
@endforelse</x-card>
@else
@include('backend.results')
@endif
