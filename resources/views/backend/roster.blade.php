<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>Student</th><th>Roll</th><th>Course</th><th>Section</th><th>Eligibility</th></tr></thead><tbody>
@forelse($records as $en)<tr><td>{{ $en->student->name }}</td><td>{{ $en->student->profile?->roll_number }}</td><td>{{ $en->course->code }}</td><td>{{ $en->section->name }}</td><td>{{ $en->eligible?'Eligible':'Ineligible' }}</td></tr>
@empty<tr><td colspan="5">No assigned students.</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
