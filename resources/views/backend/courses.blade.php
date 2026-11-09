<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('admin.copy.course') }}</th><th>{{ ui_text('admin.copy.section') }}</th><th>{{ ui_text('admin.copy.credits_assignment') }}</th></tr></thead><tbody>
@forelse($records as $en)<tr><td>
@if($role==='student'){{ $en->course->code }} · {{ $en->course->name }}
@else{{ $en->course->name }}
@endif</td><td>{{ $en->section?->name??'—' }}</td><td>
@if($role==='student'){{ $en->course->credits }} credits · {{ $en->eligible?'Eligible':'Ineligible' }}
@else@php $assignedPapers=$courses->where('course_id',$en->course_id)->where('section_id',$en->section_id); @endphp
@forelse($assignedPapers as $paper)<a class="card-link role-subline" href="/teacher/courses?tab=marks&amp;course_id={{ $paper->id }}">{{ $paper->examination->name }} · Enter marks →</a>@empty<span class="muted">{{ ui_text('admin.copy.no_examination_marks_assigned_yet') }}</span>@endforelse
@if($assignedPapers->isNotEmpty())<a class="card-link role-subline" href="/teacher/courses?tab=students&amp;course_id={{ $assignedPapers->first()->id }}">{{ ui_text('admin.copy.view_students') }}</a>@endif
@endif</td></tr>
@empty<tr><td colspan="3">{{ ui_text('admin.copy.no_course_assignments') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
