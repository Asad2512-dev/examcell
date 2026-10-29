
@if($role==='admin')<x-card class=""><h2>Generate private admit cards</h2><form method="POST" data-route-form="/workflow/admit/" class="form-grid" data-wizard-form>
@csrf<x-form-field ><span>Published examination</span><select data-route-id required>
@foreach($exams->where('status','published') as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</select></x-form-field><x-form-field ><span>Student (for individual generation)</span><select name="student_id"><option value="">Select student or bulk generation</option>
@foreach($admitStudents as $s)<option value="{{ $s->user_id }}">{{ $s->roll_number }} · {{ $s->user->name }}</option>
@endforeach</select></x-form-field><label><input type="checkbox" name="bulk" value="1"> Generate for all eligible enrolled students</label><x-button variant="primary" class="" type="submit">Generate PDFs</x-button></form></x-card>
@endif
@if($role==='admin')<x-card class=""><div class="card-heading"><h2>Bulk generation progress</h2><x-button variant="secondary" data-refresh icon="history">Refresh status</x-button></div>
@forelse(app(\App\Services\AcademicContext::class)->apply(\App\Models\AdmitGenerationRun::query())->latest()->limit(20)->get() as $run)<div class="card-body"><p class="muted text-xs mb-3">Run #{{ $run->id }} · {{ $run->created_at->format('d M Y H:i') }}</p><x-job-progress :status="$run->status" :count="$run->generated" :failure="$run->failure"/></div>
@empty<p>No queued generation runs.</p>
@endforelse</x-card>
@endif<x-card class=""><h2>Available admit cards</h2>
@forelse($records as $card)<div class="notification-item"><div><strong>{{ $card->examination->name }} · {{ $card->student->name }}</strong><p>Reference {{ $card->reference }} · {{ $card->created_at }}</p></div><a class="btn btn-secondary" href="/download/admit/{{ $card->id }}">Download PDF</a></div>
@empty<p class="empty-state">No generated admit cards are available to this account.</p>
@endforelse @include('backend.pagination')</x-card>
