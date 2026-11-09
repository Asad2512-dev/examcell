@if($role!=='admin')
@include('roles.schedule')
@else

@php
$uiCalendarEvents=collect($records->items())->map(fn($entry)=>['id'=>$entry->id,'date'=>$entry->starts_at->toIso8601String(),'end'=>$entry->ends_at->toIso8601String(),'title'=>$entry->examCourse->course->code,'detail'=>$entry->starts_at->format('H:i').' · '.$entry->room_label,'room'=>$entry->room_id])->all();
@endphp

<x-calendar :events="$uiCalendarEvents" title="{{ ui_text('examinations.copy.examination_schedule') }}" />
@include('backend.filters')
@if($role==='admin')<x-card class=""><h2>{{ ui_text('examinations.copy.schedule_an_examination_course') }}</h2><form method="POST" data-route-form="/workflow/schedule/" class="form-grid">
@csrf<x-form-field ><span>{{ ui_text('examinations.copy.course') }}</span><select data-route-id required>
@foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->course->code }} / {{ $c->examination->name }} (#{{ $c->id }})</option>
@endforeach</select></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.room') }}</span><select name="room_id" required>
@foreach(\App\Models\Room::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }} · {{ $room->capacity }} seats</option>
@endforeach</select></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.additional_rooms_optional') }}</span><select name="extra_rooms[]" multiple>
@foreach(\App\Models\Room::where('active',true)->get() as $room)<option value="{{ $room->id }}">{{ $room->name }} · {{ $room->capacity }} seats</option>
@endforeach</select></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.start') }}</span><input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></x-form-field><x-button variant="primary" class="" type="submit">{{ ui_text('examinations.copy.validate_and_save') }}</x-button></form></x-card>
@foreach($exams as $exam)<x-card class=""><div class="card-heading"><h2>{{ $exam->name }}</h2><x-badge :status="ucfirst($exam->status)"/></div>
@if($exam->status==='draft')<form class="form-grid" data-wizard-form method="POST" action="/workflow/examination/{{ $exam->id }}/generate">
@csrf<x-form-field ><span>{{ ui_text('examinations.copy.generate_from') }}</span><input type="date" name="starts_on" required></x-form-field><x-form-field ><span>{{ ui_text('examinations.copy.search_days_190') }}</span><input type="number" name="days" value="14" min="1" max="90" required></x-form-field><x-button variant="primary" class="" type="submit">{{ ui_text('examinations.copy.generate_conflict_checked_timetable') }}</x-button></form>
@endif @if(in_array($exam->status,['draft','approved']))<form method="POST" action="/workflow/examination/{{ $exam->id }}/state">
@csrf<input type="hidden" name="status" value="{{ $exam->status==='draft'?'approved':'published' }}"><x-button variant="secondary" class="" type="submit">{{ $exam->status==='draft'?'Approve':'Publish' }} schedule</x-button></form>
@endif @if($exam->status==='published')<form method="POST" action="/workflow/examination/{{ $exam->id }}/revision">
@csrf<x-button variant="secondary" class="" type="submit">{{ ui_text('examinations.copy.create_draft_schedule_revision') }}</x-button></form>
@endif</x-card>
@endforeach @endif
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('examinations.copy.course') }}</th><th>{{ ui_text('examinations.copy.room') }}</th><th>{{ ui_text('examinations.copy.start') }}</th><th>{{ ui_text('examinations.copy.end') }}</th></tr></thead><tbody>
@forelse($records as $s)<tr><td>{{ $s->examCourse->course->code }} · {{ $s->examCourse->course->name }}</td><td>{{ $s->room_label }}</td><td>{{ $s->starts_at->format('d M Y H:i') }}</td><td>{{ $s->ends_at->format('H:i') }}</td></tr>
@empty<tr><td colspan="4">{{ ui_text('examinations.copy.no_authorized_schedules') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>

@endif
