@php
    $allocationCourses = $allocationCourses ?? $courses;
    if ($allocationCourses->isNotEmpty()) {
        $allocationCourses->loadMissing(['course', 'section', 'examination']);
    }
    $allocationCourses = $allocationCourses->filter(fn($course) => $course->examination->status === 'draft');
    $allocationRooms = \App\Models\Room::where('active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'capacity']);
@endphp
@if ($allocationCourses->isEmpty() || $allocationRooms->isEmpty())
    <div class="card-body">
        <p>{{ $allocationRooms->isEmpty() ? 'Add an available room before allocating exam papers.' : 'Add papers to a draft examination before allocating rooms.' }}
        </p>
        <x-button :href="$allocationRooms->isEmpty() ? '/admin/rooms' : '/admin/exams'"
            variant="secondary">{{ $allocationRooms->isEmpty() ? 'Add a room' : 'Open examinations' }}</x-button>
    </div>
@else
    <form method="POST" data-route-form="/workflow/schedule/" class="form-grid" data-room-allocation>
        @csrf
        <x-form-field label="1. Choose a paper">
            <select name="allocation_course_id" data-route-id required aria-label="Choose a paper">
                <option value="">Choose an examination paper</option>
                @foreach ($allocationCourses as $course)
                    <option value="{{ $course->id }}" @selected((string) old('allocation_course_id') === (string) $course->id)>{{ $course->course->code }} —
                        {{ $course->section->name }} · {{ $course->examination->name }}</option>
                @endforeach
            </select>
        </x-form-field>
        <x-form-field label="2. Choose a room">
            <select name="room_id" required>
                <option value="">Choose an available room</option>
                @foreach ($allocationRooms as $room)
                    <option value="{{ $room->id }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->name }} ·
                        {{ $room->capacity }} seats</option>
                @endforeach
            </select>
        </x-form-field>
        <x-input name="starts_at" label="3. Exam date and start time" type="datetime-local" :value="old('starts_at')"
            required />
        <details class="admin-optional" @if (old('extra_rooms')) open @endif>
            <summary>Need more than one room? Add extra rooms</summary>
            <p class="muted">Choose extra rooms only when the main room cannot fit the class.</p>
            <div class="admin-room-choices">
                @foreach ($allocationRooms as $room)
                    <label><input type="checkbox" name="extra_rooms[]" value="{{ $room->id }}"
                            @checked(in_array((string) $room->id, array_map('strval', (array) old('extra_rooms', []))))>{{ $room->name }} · {{ $room->capacity }} seats</label>
                @endforeach
            </div>
        </details>
        <div class="form-actions"><x-button type="submit" icon="check">Check and allocate room</x-button></div>
        <p class="muted">Availability, capacity, and timetable clashes are checked before saving. To change a paper
            with saved seating or duties, create an examination revision first.</p>
    </form>
@endif
