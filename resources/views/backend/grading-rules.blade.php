@php $grSchemes=\App\Models\GradingScheme::orderBy('id')->get(); @endphp
<x-card title="{{ ui_text('results.copy.gpa_and_cgpa_rules') }}" description="Grades, GPA and CGPA are always calculated by the system, never by AI. These rules control how.">
<form method="POST" action="/admin/settings/grading-rules" class="form-grid card-body">@csrf
<x-select name="cgpa_attempt_policy" label="When a course is attempted more than once (CGPA)"><option value="latest" @selected(\App\Services\Settings::get('cgpa_attempt_policy',config('examcell.cgpa_attempt_policy'))==='latest')>{{ ui_text('results.copy.count_only_the_latest_published_attempt') }}</option><option value="all" @selected(\App\Services\Settings::get('cgpa_attempt_policy',config('examcell.cgpa_attempt_policy'))==='all')>{{ ui_text('results.copy.count_every_attempt') }}</option></x-select>
@foreach($grSchemes as $g)<x-select :name="'rounding['.$g->id.']'" :label="'Rounding places · '.$g->name.' ('.$g->status.')'" :disabled="$g->status==='approved'">@foreach([0,1,2] as $n)<option value="{{ $n }}" @selected((int)$g->rounding_places===$n)>{{ $n }} decimal places</option>
@endforeach</x-select>
@endforeach
<p class="muted text-xs">{{ ui_text('results.copy.approved_schemes_are_locked_edit_boundaries_and_points_i') }}</p>
<x-button type="submit" icon="check">{{ ui_text('results.copy.save_rules') }}</x-button></form></x-card>
