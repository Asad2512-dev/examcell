<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>Course</th><th>Section</th><th>Credits / Assignment</th></tr></thead><tbody>
@forelse($records as $en)<tr><td>
@if($role==='student'){{ $en->course->code }} · {{ $en->course->name }}
@else{{ $en->course->name }}
@endif</td><td>#{{ $en->section_id }}</td><td>
@if($role==='student'){{ $en->course->credits }} credits · {{ $en->eligible?'Eligible':'Ineligible' }}
@else<a href="/teacher/marks-entry">Open assigned examination marks</a>
@endif</td></tr>
@empty<tr><td colspan="3">No course assignments.</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
