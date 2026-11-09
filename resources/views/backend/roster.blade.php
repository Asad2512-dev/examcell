<div class="role-flow-note"><strong>{{ ui_text('admin.copy.students_in_your_assigned_courses') }}</strong><p>{{ ui_text('admin.copy.choose_an_examination_course_to_narrow_this_list_search') }}</p></div>
@if($courses->isNotEmpty())@include('backend.course-select')@endif
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('admin.copy.student') }}</th><th>{{ ui_text('admin.copy.roll') }}</th><th>{{ ui_text('admin.copy.course') }}</th><th>{{ ui_text('admin.copy.section') }}</th><th>{{ ui_text('admin.copy.eligibility') }}</th></tr></thead><tbody>
@forelse($records as $en)<tr><td>{{ $en->student->name }}</td><td>{{ $en->student->profile?->roll_number }}</td><td>{{ $en->course->code }}</td><td>{{ $en->section->name }}</td><td>{{ $en->eligible?'Eligible':'Ineligible' }}</td></tr>
@empty<tr><td colspan="5">{{ ui_text('admin.copy.no_assigned_students') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
