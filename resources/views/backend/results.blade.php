
@include('backend.filters')
@if($role==='admin')
@foreach($exams as $e)<x-card class=""><div class="card-heading"><h2>{{ $e->name }}</h2><x-badge :status="ucfirst(str_replace('_',' ',$e->batch?->status??'draft'))"/></div><form method="POST" action="/workflow/results/{{ $e->id }}/calculate">
@csrf<x-button variant="secondary" class="" type="submit">Validate completeness and calculate</x-button></form>
@if($e->batch && in_array($e->batch->status,['draft','returned','submitted','under_review','approved']))
@php $next=['draft'=>'submitted','returned'=>'submitted','submitted'=>'under_review','under_review'=>'approved','approved'=>'published'][$e->batch->status]; @endphp<form method="POST" action="/workflow/batch/{{ $e->batch->id }}/state">
@csrf<input type="hidden" name="status" value="{{ $next }}"><x-button variant="primary" class="" type="submit">Move to {{ str_replace('_',' ',$next) }}</x-button></form>
@endif</x-card>
@endforeach @endif
@if($role==='student')<div class="dashboard-kpis"><x-kpi label="CGPA" :value="app(\App\Services\GradingService::class)->academicGpa($allResults)??'—'" icon="award" note="Published final exams; configured attempt policy"/>
@foreach($allResults->groupBy(fn($r)=>$r->examCourse->section->semester_id) as $semester=>$results)<x-kpi :label="'Semester '.$semester.' GPA'" :value="app(\App\Services\GradingService::class)->academicGpa($results)??'—'" icon="award" note="Published credit-weighted results"/>
@endforeach</div>
@endif
@if($role==='student')<div class="semester-tabs" aria-label="Filter result rows by semester"><button type="button" class="btn btn-secondary btn-small" data-semester-filter="all" aria-pressed="true">All semesters</button>
@foreach($allResults->groupBy(fn($r)=>$r->examCourse->section->semester_id) as $uiSemester=>$uiResults)<button type="button" class="btn btn-secondary btn-small" data-semester-filter="{{ $uiSemester }}" aria-pressed="false">Semester {{ $uiSemester }}</button>
@endforeach</div>
@endif
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>Student</th><th>Course</th><th>Percentage</th><th>Grade</th><th>Points</th><th>Credits</th><th>Quality</th><th>Status</th><th>Pass / Fail</th><th>Absence</th>
@if($role==='student')<th>Recheck</th>
@endif</tr></thead><tbody>
@forelse($records as $res)<tr data-result-semester="{{ $res->examCourse->section->semester_id }}"><td>{{ $res->student->name }}</td><td>{{ $res->examCourse->course->code }} · {{ $res->examCourse->course->name }}</td><td>{{ $res->percentage }}</td><td>{{ $res->grade }}</td><td>{{ $res->grade_points }}</td><td>{{ $res->credits }}</td><td>{{ $res->quality_points }}</td><td><x-badge :status="ucwords(str_replace('_',' ',$res->batch->status))"/></td><td>{{ $res->passed?'Pass':'Fail' }}</td><td>{{ $res->absent?'Absent component':'Present' }}</td>
@if($role==='student')<td><form method="POST" action="/workflow/recheck/{{ $res->id }}">
@csrf<textarea name="reason" required minlength="10" placeholder="Reason for rechecking" aria-label="Reason for rechecking {{ $res->examCourse->course->code }}"></textarea><x-button variant="secondary" class="" type="submit">Request recheck</x-button></form></td>
@endif</tr>
@empty<tr><td colspan="11"><x-empty-state :title="$role==='student'?'Your results aren’t published yet':'No result records available'" :description="$role==='student'?'Your examination cell will release results here after the review and approval process.':'Calculated result records will appear here when available to your account.'" icon="award"/></td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
