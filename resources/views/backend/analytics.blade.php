
@include('backend.analytics-summary')
@if($role==='admin')<x-card class=""><h2>Run completeness and anomaly review</h2><p>Flags use expected eligible enrollment × assessment components and a transparent 2σ rule when at least five results exist. They require human review.</p><form method="POST" data-route-form="/workflow/analysis/">
@csrf<x-form-field ><span>Examination</span><select data-route-id required>
@foreach($exams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</select></x-form-field><x-button variant="primary" class="" type="submit">Recalculate flags</x-button></form></x-card><x-card class=""><h2>Review flags</h2>
@forelse($flags as $flag)<article class="notification-item"><div><strong>{{ str_replace('_',' ',$flag->type) }}</strong><x-badge :status="ucfirst($flag->status)"/><p>Examination #{{ $flag->examination_id }} · Student #{{ $flag->student_id??'—' }} · {{ $flag->status }}</p><pre>{{ json_encode($flag->details,JSON_PRETTY_PRINT) }}</pre></div>
@if($flag->status==='open')<form method="POST" action="/workflow/flag/{{ $flag->id }}/review">
@csrf<select name="status"><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select><textarea name="reason" required minlength="10" placeholder="Review explanation"></textarea><x-button variant="secondary" class="" type="submit">Record review</x-button></form>
@endif</article>
@empty<p>No analysis flags. Run analysis after creating examinations.</p>
@endforelse</x-card>
@endif

<x-card class=""><h2>Advisory performance forecast</h2><p>Uses authorized published final-exam history with a documented statistical trend and uncertainty range. It cannot change official results.</p><form method="POST" action="/workflow/forecast" class="form-grid">
@csrf @if($role==='student')<input type="hidden" name="student_id" value="{{ auth()->id() }}">
@else<x-form-field ><span>Student</span><select name="student_id" required>
@foreach(app(\App\Services\ScopedQueries::class)->results(auth()->user())->get()->unique('student_id') as $res)<option value="{{ $res->student_id }}">{{ $res->student->name }}</option>
@endforeach</select></x-form-field>
@endif<x-form-field ><span>Course</span><select name="course_id" required>
@foreach(app(\App\Services\ScopedQueries::class)->results(auth()->user())->get()->unique(fn($res)=>$res->examCourse->course_id) as $res)<option value="{{ $res->examCourse->course_id }}">{{ $res->examCourse->course->code }} · {{ $res->examCourse->course->name }}</option>
@endforeach</select></x-form-field><x-button variant="primary" class="" type="submit">Generate forecast and uncertainty</x-button></form>
@if(session('forecast'))
@php $f=session('forecast'); @endphp<div class="callout"><strong>Advisory estimate: {{ $f['predicted_percentage'] }}%</strong><div class="uncertainty" role="img" aria-label="Estimated {{ $f['predicted_percentage'] }} percent, range {{ $f['lower'] }} to {{ $f['upper'] }} percent"><div class="uncertainty-track"><span class="uncertainty-range" style="left:{{ max(0,min(100,$f['lower'])) }}%;width:{{ max(0,min(100,$f['upper'])-max(0,$f['lower'])) }}%"></span><span class="uncertainty-point" style="left:{{ max(0,min(100,$f['predicted_percentage'])) }}%"></span></div></div><p>Range {{ $f['lower'] }}–{{ $f['upper'] }}% · Confidence {{ $f['confidence'] }} · {{ $f['sample_size'] }} observed attempts</p><p>{{ $f['method'] }}</p><p>{{ $f['range_method'] }}</p><p>{{ $f['notice'] }}</p></div>
@endif</x-card>
