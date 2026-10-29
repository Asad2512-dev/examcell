@include('ai.seat-map')

@if($role==='admin')
@php
    $seatingEntries = app(\App\Services\AcademicContext::class)->apply(\App\Models\ScheduleEntry::query())->with('examCourse.course','examCourse.examination','room','rooms')->orderBy('starts_at')->get();
    $firstLocked = !$seatingEntries->count() || in_array($seatingEntries->first()->examCourse->examination->status,['published','archived']);
@endphp
<details class="card seating-manual"><summary class="card-link" style="padding:16px 20px;cursor:pointer">Manual seating without AI (sequential)</summary>
<x-card class="seating-setup">
    <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="grid"/></span><div><h2>Allocate or regenerate seating</h2><p>Review a proposed plan, then save when you’re ready.</p></div></div></div>
    <form method="POST" action="/workflow/seating/{{ $seatingEntries->first()?->id }}" data-route-form="/workflow/seating/" class="seating-setup-form">
@csrf
        <div class="seating-setup-fields"><x-form-field ><span>Scheduled course</span><select data-route-id data-seating-schedule required aria-describedby="seating-schedule-help">
            @forelse($seatingEntries as $entry)<option value="{{ $entry->id }}" data-locked="{{ in_array($entry->examCourse->examination->status,['published','archived'])?'true':'false' }}">{{ $entry->examCourse->course->code }} · {{ $entry->room_label }} · {{ $entry->starts_at->format('d M Y, H:i') }}</option>
            @empty<option value="">No scheduled courses available</option>
@endforelse
        </select></x-form-field><p class="seating-field-help" id="seating-schedule-help">Eligible students receive unique numbered seats. Changes to existing plans are recorded in the audit history.</p></div>
        <div class="seating-action-bar">
            <div class="seating-action-note"><span><x-icon name="shield" :size="17"/></span><p data-seating-status aria-live="polite">{{ $firstLocked?'Published plans can be previewed. Create a new examination revision to change seating.':'Previewing does not save changes. Published plans cannot be changed.' }}</p></div>
            <div class="seating-action-buttons">
                <x-button variant="secondary" class="" type="submit" name="preview" value="1" :disabled="$seatingEntries->isEmpty()"><x-icon name="eye" :size="16"/>Preview plan</x-button>
                <x-button variant="primary" class="" type="submit" name="preview" value="0" data-seating-save data-confirm="true" data-confirm-title="Save this seating plan?" data-confirm-message="Save seat assignments for the selected schedule. If a plan already exists, its seats will be regenerated and the previous assignments recorded in the audit history." data-confirm-label="Save seating plan" :disabled="$firstLocked"><x-icon name="check" :size="16"/>Save seating plan</x-button>
            </div>
        </div>
    </form>
</x-card>
@if(session('seating_preview'))
@php
$preview=session('seating_preview');
@endphp

<x-card class="seating-preview"><div class="card-heading"><div><h2>Seating preview</h2><p>Proposed assignments only. No changes have been saved.</p></div><span class="badge badge-info">{{ count($preview['proposed']??[]) }} seats</span></div>
    @if(!empty($preview['conflicts']))<div class="callout callout-danger" role="alert"><strong>Resolve these conflicts before saving.</strong><ul>
@foreach($preview['conflicts'] as $conflict)<li>{{ $conflict }}</li>
@endforeach</ul></div>
@endif
    <x-data-table><table class="data-table"><thead><tr><th>Student</th><th>Roll number</th><th>Room</th><th>Seat</th></tr></thead><tbody>
@forelse($preview['proposed']??[] as $seat)<tr><td>{{ $seat['student'] }}</td><td>{{ $seat['roll'] }}</td><td>{{ $seat['room'] }}</td><td>{{ $seat['seat_number'] }}</td></tr>
@empty<tr><td colspan="4">No eligible seat assignments for this schedule.</td></tr>
@endforelse</tbody></table></x-data-table>
</x-card>
@endif
</details>
@endif
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>Student</th><th>Course</th><th>Room</th><th>Seat</th><th>Date / Time</th></tr></thead><tbody>
@forelse($records as $s)<tr><td>{{ $s->student->profile?->roll_number }} · {{ $s->student->name }}</td><td>{{ $s->seatingPlan->scheduleEntry->examCourse->course->code }}</td><td>{{ $s->room?->name??$s->seatingPlan->scheduleEntry->room->name }}</td><td>@php $uiRoom=$s->room??$s->seatingPlan->scheduleEntry->room; $uiLayouts[$uiRoom->id]??=$uiRoom->layout(); @endphp{{ $uiLayouts[$uiRoom->id]->labelOf($s->seat_number) }}</td><td>{{ $s->seatingPlan->scheduleEntry->starts_at }}</td></tr>
@empty<tr><td colspan="5">No allocated seats are available to your account.</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
