<x-card title="{{ ui_text('teacher.copy.tell_us_when_you_are_unavailable') }}" description="Choose the dates and explain why. Submit at least 48 hours in advance.">
<form method="POST" action="/workflow/availability" class="form-grid card-body">@csrf
<x-form-field label="Unavailable from"><input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></x-form-field>
<x-form-field label="Available again"><input type="datetime-local" name="ends_at" required value="{{ old('ends_at') }}"></x-form-field>
<x-form-field label="Reason" hint="Use 10–255 characters."><textarea name="reason" required minlength="10" maxlength="255" rows="3">{{ old('reason') }}</textarea></x-form-field>
<x-button type="submit">{{ ui_text('teacher.copy.submit_availability_request') }}</x-button>
<p class="muted">{{ ui_text('teacher.copy.if_this_affects_an_assigned_duty_it_creates_a_cover_requ') }}</p>
<a class="card-link" href="/teacher/duties">{{ ui_text('teacher.copy.track_affected_duties_and_cover_requests') }}</a>
</form></x-card>
<x-disclosure title="{{ ui_text('teacher.copy.recorded_availability') }}" description="Your latest saved availability intervals.">
<div class="attention-list">@forelse(\App\Models\TeacherAvailability::where('teacher_id',auth()->id())->orderByDesc('starts_at')->limit(10)->get() as $interval)<div class="attention-item"><div><strong>{{ $interval->starts_at->format('d M Y, H:i') }} – {{ $interval->ends_at->format('d M Y, H:i') }}</strong><p>{{ $interval->available?'Available':'Unavailable' }} · {{ $interval->reason }}</p></div></div>@empty<x-empty-state title="{{ ui_text('teacher.copy.no_recorded_intervals') }}" description="Your saved availability will appear here." icon="clock"/>@endforelse</div>
</x-disclosure>
