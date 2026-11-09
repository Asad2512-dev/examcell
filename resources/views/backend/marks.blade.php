
@php
$submissionOverview=$role==='teacher'&&$page['slug']==='marks-submissions';
@endphp
@if($submissionOverview)
<x-submission-deadlines :courses="$courses"/>
@else
@if($role==='teacher')<div class="role-flow-note"><strong>{{ ui_text('results.copy.choose_course_enter_marks_save_draft_submit_for_review') }}</strong><p>{{ ui_text('results.copy.save_a_draft_while_you_work_submit_only_when_complete_su') }}</p></div>@endif
@include('backend.course-select')
@if($role==='teacher'&&$selected)
<div class="course-deadline-context"><span><strong>{{ $selected->course->code }}</strong> {{ ui_text('results.copy.submission_deadline') }}</span><x-submission-countdown :deadline="$selected->submissionDeadlineFor(auth()->user())" :submitted="$selected->submission?->status==='submitted'"/></div>
@endif
@if($role==='admin'&&$selected)<x-submission-deadline-editor :course="$selected"/>
@endif
@if($selected)<x-card class=""><div class="card-heading"><h2>{{ $selected->course->name }}</h2><x-badge :status="ucfirst($selected->submission?->status??'draft')"/></div>@if($selected->submission?->return_reason)<div class="callout callout-warning"><strong>{{ ui_text('results.copy.corrections_requested') }}</strong><p>{{ $selected->submission->return_reason }}</p></div>@endif
@if($role==='teacher'&&$students->isEmpty())<div class="callout">{{ ui_text('results.copy.no_eligible_students_are_assigned_to_this_examination_co') }}</div>@endif
@if($role==='teacher'&&$selected->components->isEmpty())<div class="callout">{{ ui_text('results.copy.assessment_components_are_not_configured_yet_ask_the_exa') }}</div>@endif<div class="marks-toolbar"><span><x-icon name="edit" :size="14"/> {{ ui_text('results.copy.enter_marks_enter_moves_between_scores') }}</span><label class="toggle-field"><input type="checkbox" data-autosave @disabled($selected->submission?->status==='submitted')>{{ ui_text('results.copy.autosave_draft') }}</label><span data-save-status role="status">{{ $selected->submission?->status==='submitted'?'Submitted · editing locked':'Ready · use Save draft to persist changes' }}</span></div><form method="POST" action="/workflow/marks/{{ $selected->id }}">
@csrf<x-data-table :enhanced="false"><table class="data-table marks-grid"><thead><tr><th>{{ ui_text('results.copy.roll_student') }}</th><th>{{ ui_text('results.copy.component_maximum') }}</th><th>{{ ui_text('results.copy.score') }}</th><th>{{ ui_text('results.copy.absent') }}</th><th>{{ ui_text('results.copy.weighted_preview') }}</th></tr></thead><tbody>
@php $i=0; @endphp @foreach($students as $enrollment)
@foreach($selected->components as $component)
@php $mark=$marks->get($enrollment->student_id.':'.$component->id);$index=$i++;$locked=$selected->submission?->status==='submitted'; @endphp<tr data-student="{{ $enrollment->student_id }}" data-weight="{{ $component->weight }}"><td>{{ $enrollment->student->profile?->roll_number }} · {{ $enrollment->student->name }}</td><td>{{ $component->name }} / {{ $component->maximum }} ({{ $component->weight }}%)</td><td><input type="hidden" name="rows[{{ $index }}][student_id]" value="{{ $enrollment->student_id }}"><input type="hidden" name="rows[{{ $index }}][assessment_component_id]" value="{{ $component->id }}"><input type="number" step="0.01" min="0" max="{{ $component->maximum }}" name="rows[{{ $index }}][score]" value="{{ old('rows.'.$index.'.score',$mark?->score) }}" @disabled($locked) aria-label="Score for {{ $enrollment->student->name }} {{ $component->name }}"></td><td><input type="hidden" name="rows[{{ $index }}][absent]" value="0"><input type="checkbox" data-absent aria-label="Absent: {{ $enrollment->student->name }}, {{ $component->name }}" name="rows[{{ $index }}][absent]" value="1" @checked(old('rows.'.$index.'.absent',$mark?->absent)) @disabled($locked)></td><td><strong data-total="{{ $enrollment->student_id }}">—</strong></td></tr>
@endforeach @endforeach</tbody></table></x-data-table><div class="form-actions"><x-button variant="secondary" class="" name="submit" value="0" :disabled="$selected->submission?->status==='submitted'" type="submit">{{ ui_text('results.copy.save_draft') }}</x-button><x-button variant="primary" class="" name="submit" value="1" data-confirm="true" data-confirm-title="{{ ui_text('results.copy.submit_and_lock_these_marks') }}" data-confirm-message="{{ ui_text('results.copy.the_server_will_validate_completeness_and_lock_this_subm') }}" data-confirm-label="{{ ui_text('results.copy.submit_and_lock') }}" :disabled="$selected->submission?->status==='submitted'" type="submit">{{ ui_text('results.copy.submit_and_lock') }}</x-button></div></form>
@if($role==='admin')<form method="POST" action="/workflow/marks/{{ $selected->id }}/return" class="form-grid">
@csrf<x-form-field ><span>{{ ui_text('results.copy.return_reason_a5c6c5') }}</span><textarea name="reason" required minlength="5"></textarea></x-form-field><x-button variant="secondary" class="" type="submit">{{ ui_text('results.copy.return_for_correction') }}</x-button></form>
@endif</x-card>
@else<x-card class="empty-state">{{ ui_text('results.copy.no_assigned_examination_courses_the_examination_cell_mus') }}</x-card>
@endif

@endif
