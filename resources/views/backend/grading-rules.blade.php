@php $grSchemes=\App\Models\GradingScheme::orderBy('id')->get(); @endphp
<x-card title="GPA and CGPA rules" description="Grades, GPA and CGPA are always calculated by the system, never by AI. These rules control how.">
<form method="POST" action="/admin/settings/grading-rules" class="form-grid card-body">@csrf
<x-select name="cgpa_attempt_policy" label="When a course is attempted more than once (CGPA)"><option value="latest" @selected(\App\Services\Settings::get('cgpa_attempt_policy',config('examcell.cgpa_attempt_policy'))==='latest')>Count only the latest published attempt</option><option value="all" @selected(\App\Services\Settings::get('cgpa_attempt_policy',config('examcell.cgpa_attempt_policy'))==='all')>Count every attempt</option></x-select>
@foreach($grSchemes as $g)<x-select :name="'rounding['.$g->id.']'" :label="'Rounding places · '.$g->name.' ('.$g->status.')'" :disabled="$g->status==='approved'">@foreach([0,1,2] as $n)<option value="{{ $n }}" @selected((int)$g->rounding_places===$n)>{{ $n }} decimal places</option>
@endforeach</x-select>
@endforeach
<p class="muted text-xs">Approved schemes are locked; edit boundaries and points in the table above for draft schemes.</p>
<x-button type="submit" icon="check">Save rules</x-button></form></x-card>
