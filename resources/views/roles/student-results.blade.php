@php
$semesterNames=\App\Models\Semester::whereIn('id',$allResults->pluck('examCourse.section.semester_id')->unique())->pluck('name','id');
$semesterResults=request()->filled('semester_id')?$allResults->filter(fn($r)=>(int)$r->examCourse->section->semester_id===(int)request('semester_id')):$allResults;
$myRechecks=\App\Models\RecheckRequest::where('student_id',auth()->id())->whereIn('calculated_result_id',collect($records->items())->pluck('id'))->latest()->get()->unique('calculated_result_id')->keyBy('calculated_result_id');
@endphp
<div class="role-flow-note"><strong>{{ ui_text('student.copy.choose_a_semester_then_review_your_published_results') }}</strong><p>{{ ui_text('student.copy.official_grades_and_gpa_follow_the_institution_s_grading') }}</p></div>
<div class="dashboard-kpis role-summary">
<x-kpi label="CGPA" :value="app(\App\Services\GradingService::class)->academicGpa($allResults)??'—'" icon="award" note="Published final exams; configured attempt policy"/>
<x-kpi :label="request()->filled('semester_id')?'Selected semester GPA':'Published results'" :value="request()->filled('semester_id')?(app(\App\Services\GradingService::class)->academicGpa($semesterResults)??'—'):$allResults->count()" icon="book" note="Calculated from published results"/>
</div>
<form method="GET" action="/student/results" class="filter-bar"><input type="hidden" name="tab" value="results">
<x-select name="semester_id" label="Semester"><option value="">{{ ui_text('student.copy.all_semesters') }}</option>@foreach($semesterNames as $id=>$name)<option value="{{ $id }}" @selected((string)request('semester_id')===(string)$id)>{{ $name }}</option>@endforeach</x-select>
<x-input name="q" label="Find a course" :value="request('q')" placeholder="{{ ui_text('student.copy.course_name_or_code') }}"/>
<x-button type="submit" variant="secondary">{{ ui_text('student.copy.show_results') }}</x-button>
</form>
<x-card title="{{ ui_text('student.copy.my_published_results') }}">
<x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ui_text('student.copy.course_examination') }}</th><th>{{ ui_text('student.copy.marks') }}</th><th>{{ ui_text('student.copy.grade') }}</th><th>{{ ui_text('student.copy.grade_points') }}</th><th>{{ ui_text('student.copy.outcome_review') }}</th></tr></thead><tbody>
@forelse($records as $res)<tr><td><strong>{{ $res->examCourse->course->code }} · {{ $res->examCourse->course->name }}</strong><small class="role-subline">{{ $res->examCourse->examination->name }} · {{ $semesterNames[$res->examCourse->section->semester_id]??'Semester' }}</small>
<details class="role-inline-details"><summary>{{ ui_text('student.copy.credit_details') }}</summary><p>{{ $res->credits }} credits · {{ $res->quality_points }} quality points</p><p>{{ $res->absent?'An absent component is recorded.':'No absent component recorded.' }}</p></details></td>
<td>{{ $res->percentage }}%</td><td><strong>{{ $res->grade }}</strong></td><td>{{ $res->grade_points }}</td><td><x-badge :status="$res->passed?'Pass':'Fail'"/>
@if(($myRechecks[$res->id]??null)&&in_array($myRechecks[$res->id]->status,['pending','under_review']))
<a class="card-link role-subline" href="/student/rechecking">{{ ui_text('student.copy.track_rechecking_request') }}</a>
@else
<details class="role-inline-details" @if($errors->has('reason')&&(string)old('review_result_id')===(string)$res->id) open @endif><summary>{{ ui_text('student.copy.request_recheck') }}</summary>
<form method="POST" action="/workflow/recheck/{{ $res->id }}" class="role-inline-form">@csrf<input type="hidden" name="review_result_id" value="{{ $res->id }}">
<x-form-field label="What should be reviewed?" hint="Explain your concern in at least 10 characters."><textarea name="reason" required minlength="10" maxlength="2000" rows="3">{{ (string)old('review_result_id')===(string)$res->id?old('reason'):'' }}</textarea></x-form-field>
<x-button type="submit" variant="secondary" data-confirm="true" data-confirm-title="{{ ui_text('student.copy.submit_this_rechecking_request') }}" data-confirm-message="{{ ui_text('student.copy.the_examination_cell_will_review_your_request_your_publi') }}" data-confirm-label="{{ ui_text('student.copy.submit_request') }}">{{ ui_text('student.copy.submit_rechecking_request') }}</x-button>
</form></details>
@endif</td></tr>
@empty<tr><td colspan="5"><x-empty-state :title="request()->filled('q')||request()->filled('semester_id')?'No results match these filters':'Your results aren’t published yet'" description="Results appear here after examination cell review and publication. Try another semester or check back later." icon="award"/></td></tr>@endforelse
</tbody></table></x-data-table>@include('backend.pagination')
</x-card>
<p class="role-flow-note">{{ ui_text('student.copy.already_requested_a_review') }} <a class="card-link" href="/student/rechecking">{{ ui_text('student.copy.track_my_rechecking_requests') }}</a></p>
