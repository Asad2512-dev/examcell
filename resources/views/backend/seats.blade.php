@if($role==='student')<div class="role-flow-note"><strong>{{ ui_text('seating.copy.choose_your_exam_slot_to_locate_your_seat') }}</strong><p>{{ ui_text('seating.copy.your_room_and_seat_appear_above_the_map_your_own_seat_is') }}</p></div>@endif
@include('ai.seat-map')

@if($role==='admin')
@php
    $seatingEntries = app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('examCourse.course','examCourse.examination','room','rooms')->orderBy('starts_at')->get();
    $firstLocked = !$seatingEntries->count() || in_array($seatingEntries->first()->examCourse->examination->status,['published','archived']);
@endphp
<details class="card seating-manual"><summary class="card-link" style="padding:16px 20px;cursor:pointer">{{ ui_text('seating.copy.manual_seating_without_ai_sequential') }}</summary>
<x-card class="seating-setup">
    <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="grid"/></span><div><h2>{{ ui_text('seating.copy.allocate_or_regenerate_seating') }}</h2><p>{{ ui_text('seating.copy.review_a_proposed_plan_then_save_when_youre_ready') }}</p></div></div></div>
    <form method="POST" action="/workflow/seating/{{ $seatingEntries->first()?->id }}" data-route-form="/workflow/seating/" class="seating-setup-form">
@csrf
        <div class="seating-setup-fields"><x-form-field ><span>{{ ui_text('seating.copy.scheduled_course') }}</span><select data-route-id data-seating-schedule required aria-describedby="seating-schedule-help">
            @forelse($seatingEntries as $entry)<option value="{{ $entry->id }}" data-locked="{{ in_array($entry->examCourse->examination->status,['published','archived'])?'true':'false' }}">{{ $entry->examCourse->course->code }} · {{ $entry->room_label }} · {{ $entry->starts_at->format('d M Y, H:i') }}</option>
            @empty<option value="">{{ ui_text('seating.copy.no_scheduled_courses_available') }}</option>
@endforelse
        </select></x-form-field><p class="seating-field-help" id="seating-schedule-help">{{ ui_text('seating.copy.eligible_students_receive_unique_numbered_seats_changes') }}</p></div>
        <div class="seating-action-bar">
            <div class="seating-action-note"><span><x-icon name="shield" :size="17"/></span><p data-seating-status aria-live="polite">{{ $firstLocked?'Published plans can be previewed. Create a new examination revision to change seating.':'Previewing does not save changes. Published plans cannot be changed.' }}</p></div>
            <div class="seating-action-buttons">
                <x-button variant="secondary" class="" type="submit" name="preview" value="1" :disabled="$seatingEntries->isEmpty()"><x-icon name="eye" :size="16"/>{{ ui_text('seating.copy.preview_plan') }}</x-button>
                <x-button variant="primary" class="" type="submit" name="preview" value="0" data-seating-save data-confirm="true" data-confirm-title="{{ ui_text('seating.copy.save_this_seating_plan') }}" data-confirm-message="{{ ui_text('seating.copy.save_seat_assignments_for_the_selected_schedule_if_a_pla') }}" data-confirm-label="{{ ui_text('seating.copy.save_seating_plan') }}" :disabled="$firstLocked"><x-icon name="check" :size="16"/>{{ ui_text('seating.copy.save_seating_plan') }}</x-button>
            </div>
        </div>
    </form>
</x-card>
@if(session('seating_preview'))
@php
$preview=session('seating_preview');
@endphp

<x-card class="seating-preview"><div class="card-heading"><div><h2>{{ ui_text('seating.copy.seating_preview') }}</h2><p>{{ ui_text('seating.copy.proposed_assignments_only_no_changes_have_been_saved') }}</p></div><span class="badge badge-info">{{ count($preview['proposed']??[]) }} seats</span></div>
    @if(!empty($preview['conflicts']))<div class="callout callout-danger" role="alert"><strong>{{ ui_text('seating.copy.resolve_these_conflicts_before_saving') }}</strong><ul>
@foreach($preview['conflicts'] as $conflict)<li>{{ $conflict }}</li>
@endforeach</ul></div>
@endif
    <x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('seating.copy.student') }}</th><th>{{ ui_text('seating.copy.roll_number') }}</th><th>{{ ui_text('seating.copy.room') }}</th><th>{{ ui_text('seating.copy.seat') }}</th></tr></thead><tbody>
@forelse($preview['proposed']??[] as $seat)<tr><td>{{ $seat['student'] }}</td><td>{{ $seat['roll'] }}</td><td>{{ $seat['room'] }}</td><td>{{ $seat['seat_number'] }}</td></tr>
@empty<tr><td colspan="4">{{ ui_text('seating.copy.no_eligible_seat_assignments_for_this_schedule') }}</td></tr>
@endforelse</tbody></table></x-data-table>
</x-card>
@endif
</details>
@endif
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('seating.copy.student') }}</th><th>{{ ui_text('seating.copy.course') }}</th><th>{{ ui_text('seating.copy.room') }}</th><th>{{ ui_text('seating.copy.seat') }}</th><th>{{ ui_text('seating.copy.date_time') }}</th></tr></thead><tbody>
@forelse($records as $s)<tr><td>{{ $s->student->profile?->roll_number }} · {{ $s->student->name }}</td><td>{{ $s->seatingPlan->scheduleEntry->examCourse->course->code }}</td><td>{{ $s->room?->name??$s->seatingPlan->scheduleEntry->room->name }}</td><td>{{ $s->seatLabel() }}</td><td>{{ $s->seatingPlan->scheduleEntry->starts_at }}</td></tr>
@empty<tr><td colspan="5">{{ ui_text('seating.copy.no_allocated_seats_are_available_to_your_account') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
