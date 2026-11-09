
@include('backend.analytics-summary')
@if($role==='admin')<x-card class=""><h2>{{ ui_text('results.copy.run_completeness_and_anomaly_review') }}</h2><p>{{ ui_text('results.copy.flags_use_expected_eligible_enrollment_assessment_compon') }}</p><form method="POST" data-route-form="/workflow/analysis/">
@csrf<x-form-field ><span>{{ ui_text('results.copy.examination') }}</span><select data-route-id required>
@foreach($exams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>
@endforeach</select></x-form-field><x-button variant="primary" class="" type="submit">{{ ui_text('results.copy.recalculate_flags') }}</x-button></form></x-card><x-card class=""><h2>{{ ui_text('results.copy.review_flags') }}</h2>
@forelse($flags as $flag)<article class="notification-item"><div><strong>{{ str_replace('_',' ',$flag->type) }}</strong><x-badge :status="ucfirst($flag->status)"/><p>Examination #{{ $flag->examination_id }} · Student #{{ $flag->student_id??'—' }} · {{ $flag->status }}</p><pre>{{ json_encode($flag->details,JSON_PRETTY_PRINT) }}</pre></div>
@if($flag->status==='open')<form method="POST" action="/workflow/flag/{{ $flag->id }}/review">
@csrf<select name="status"><option value="resolved">{{ ui_text('results.copy.resolved') }}</option><option value="dismissed">{{ ui_text('results.copy.dismissed') }}</option></select><textarea name="reason" required minlength="10" placeholder="{{ ui_text('results.copy.review_explanation') }}"></textarea><x-button variant="secondary" class="" type="submit">{{ ui_text('results.copy.record_review') }}</x-button></form>
@endif</article>
@empty<p>{{ ui_text('results.copy.no_analysis_flags_run_analysis_after_creating_examinatio') }}</p>
@endforelse</x-card>
@endif

<x-card class=""><h2>{{ ui_text('results.copy.advisory_performance_forecast') }}</h2><p>{{ ui_text('results.copy.uses_authorized_published_final_exam_history_with_a_docu') }}</p><form method="POST" action="/workflow/forecast" class="form-grid">
@csrf @if($role==='student')<input type="hidden" name="student_id" value="{{ auth()->id() }}">
@else<x-form-field ><span>{{ ui_text('results.copy.student') }}</span><select name="student_id" required>
@foreach(app(\App\Services\ScopedQueries::class)->results(auth()->user())->get()->unique('student_id') as $res)<option value="{{ $res->student_id }}">{{ $res->student->name }}</option>
@endforeach</select></x-form-field>
@endif<x-form-field ><span>{{ ui_text('results.copy.course') }}</span><select name="course_id" required>
@foreach(app(\App\Services\ScopedQueries::class)->results(auth()->user())->get()->unique(fn($res)=>$res->examCourse->course_id) as $res)<option value="{{ $res->examCourse->course_id }}">{{ $res->examCourse->course->code }} · {{ $res->examCourse->course->name }}</option>
@endforeach</select></x-form-field><x-button variant="primary" class="" type="submit">{{ ui_text('results.copy.generate_forecast_and_uncertainty') }}</x-button></form>
@if(session('forecast'))
@php $f=session('forecast'); @endphp<div class="callout"><strong>Advisory estimate: {{ $f['predicted_percentage'] }}%</strong><div class="uncertainty" role="img" aria-label="Estimated {{ $f['predicted_percentage'] }} percent, range {{ $f['lower'] }} to {{ $f['upper'] }} percent"><div class="uncertainty-track"><span class="uncertainty-range" style="left:{{ max(0,min(100,$f['lower'])) }}%;width:{{ max(0,min(100,$f['upper'])-max(0,$f['lower'])) }}%"></span><span class="uncertainty-point" style="left:{{ max(0,min(100,$f['predicted_percentage'])) }}%"></span></div></div><p>Range {{ $f['lower'] }}–{{ $f['upper'] }}% · Confidence {{ $f['confidence'] }} · {{ $f['sample_size'] }} observed attempts</p><p>{{ $f['method'] }}</p><p>{{ $f['range_method'] }}</p><p>{{ $f['notice'] }}</p></div>
@endif</x-card>
