@php
    $roomGridMode = old('layout_mode', $record?->seat_rows ? 'grid' : 'capacity');
    $roomLayout = $record?->layout();
    $roomAccessible = $record
        ? collect($record->accessible_seats ?? [])
            ->map(fn($seat) => $roomLayout->labelOf((int) $seat))
            ->implode(', ')
        : '';
@endphp
<section id="room-form">
    <x-card :title="$record ? 'Edit room' : 'Add a room'"
        description="Add the real seating arrangement now so scheduling and AI seating use the correct seats from day one.">
        <form class="form-grid room-basic-form" method="POST"
            action="{{ route('resource.save', ['rooms', $record?->id]) }}" data-room-form>
            @csrf
            <x-input name="name" label="Room name" :value="old('name', $record?->name)" placeholder="e.g. Hall A or Room 101" maxlength="80"
                required />
            <fieldset class="room-arrangement-choice">
                <legend>How is seating arranged?</legend>
                <label><input type="radio" name="layout_mode" value="grid"
                        @checked($roomGridMode === 'grid')><span><strong>Rows and seats</strong><small>Recommended — AI
                            understands aisles, broken seats and accessibility.</small></span></label>
                <label><input type="radio" name="layout_mode" value="capacity"
                        @checked($roomGridMode === 'capacity')><span><strong>Seat count only</strong><small>Fast setup for rooms
                            where exact positions are not needed yet.</small></span></label>
            </fieldset>
            <div class="room-grid-fields" data-room-grid @if ($roomGridMode !== 'grid') hidden @endif>
                <x-input name="rows" label="Rows (front to back)" type="number" :value="old('rows', $roomLayout?->rows ?? 5)" min="1"
                    max="40" />
                <x-input name="cols" label="Seats in each row" type="number" :value="old('cols', $roomLayout?->cols ?? 6)" min="1"
                    max="30" />
                <x-input name="aisles" label="Aisle after column(s)" :value="old('aisles', $roomLayout ? implode(', ', $roomLayout->aisles) : '')" placeholder="e.g. 3 or 3, 6"
                    hint="Seats across an aisle are not treated as neighbours by AI." />
                <x-input name="disabled" label="Broken / unusable seats" :value="old('disabled', implode(', ', $record?->disabled_seats ?? []))" placeholder="e.g. A1, C4" />
                <x-input name="accessible" label="Accessible seats" :value="old('accessible', $roomAccessible)" placeholder="e.g. A1, A2"
                    hint="AI prioritizes these for students marked as having special needs." />
                <x-select name="door_side" label="Entrance door">
                    <option value="right" @selected(old('door_side', $record?->door_side ?? 'right') === 'right')>Right wall</option>
                    <option value="left" @selected(old('door_side', $record?->door_side) === 'left')>Left wall</option>
                </x-select>
                <div class="room-capacity-summary" aria-live="polite"><span>Usable exam seats</span><strong
                        data-room-capacity>{{ $record?->capacity ?? 30 }}</strong><small>Rows × seats, minus unusable
                        positions</small></div>
            </div>
            <div data-room-capacity-only @if ($roomGridMode === 'grid') hidden @endif>
                <x-input name="capacity" label="Total usable seats" type="number" :value="old('capacity', $record?->capacity)" min="1"
                    max="2000" />
            </div>
            <label class="toggle-field room-available">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', $record?->active ?? true))>
                Available for exam allocation
            </label>
            <div class="form-actions">
                @if ($record)
                    <x-button href="/admin/rooms" variant="secondary">Cancel</x-button>
                @endif
                <x-button type="submit" icon="check">{{ $record ? 'Save room' : 'Add room' }}</x-button>
            </div>
        </form>
    </x-card>
</section>
<x-card title="Your rooms"
    description="AI-ready rooms have a physical grid. Capacity-only rooms still work and can be upgraded at any time."
    id="room-directory">
    <x-slot:actions><x-button href="/admin/exams?tab=datesheet" icon="arrow">Continue to date
            sheet</x-button></x-slot:actions>
    <form method="GET" action="/admin/rooms" class="room-search">
        <x-input name="q" label="Find a room" :value="request('q')" placeholder="Search by room name" />
        <x-button type="submit" variant="secondary" icon="search">Search</x-button>
        @if (request('q'))
            <x-button href="/admin/rooms" variant="secondary">Clear</x-button>
        @endif
    </form>
    <x-data-table :enhanced="false">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Arrangement</th>
                    <th>Usable seats</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $room)
                    <tr>
                        <td><strong>{{ $room->name }}</strong></td>
                        <td>
                            @if ($room->seat_rows)
                                <x-badge status="AI ready" /> <span class="muted">{{ $room->seat_rows }} ×
                                {{ $room->seat_columns }}</span>@else<x-badge status="Basic" />
                            @endif
                        </td>
                        <td><strong>{{ $room->capacity }}</strong>
                            @if ($room->disabled_seats)
                                <small class="room-seat-note">{{ count($room->disabled_seats) }} unavailable</small>
                            @endif
                        </td>
                        <td><x-badge :status="$room->active ? 'Available' : 'Unavailable'" /></td>
                        <td>
                            <div class="room-actions">
                                <x-button :href="'/admin/rooms?id=' . $room->id . '#room-form'" variant="secondary" :aria-label="'Edit ' . $room->name">Edit</x-button>
                                <x-button :href="'/admin/rooms?layout_room=' . $room->id . '#room-layout'" variant="secondary" :aria-label="'Configure layout for ' . $room->name">Layout</x-button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">
                            {{ request('q') ? 'No matching rooms. Clear your search to see all rooms.' : 'No rooms yet. Add your first room above to get started.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
    @include('backend.pagination')
</x-card>
<x-disclosure id="room-layout" title="Optional: customize a room layout"
    description="Rows, aisles, unusable seats, and accessibility. You can skip this and use the seat count above."
    :open="request()->filled('layout_room') ||
        $errors->hasAny(['rows', 'cols', 'aisles', 'disabled', 'accessible', 'door_side'])">
    @include('ai.room-layout-editor')
</x-disclosure>
<script>
    (function() {
        var form = document.querySelector('[data-room-form]');
        if (!form) return;
        var grid = form.querySelector('[data-room-grid]'),
            countOnly = form.querySelector('[data-room-capacity-only]'),
            output = form.querySelector('[data-room-capacity]');

        function labels(value) {
            return new Set(String(value || '').toUpperCase().split(/[\s,;]+/).filter(Boolean));
        }

        function refresh() {
            var detailed = form.layout_mode.value === 'grid';
            grid.hidden = !detailed;
            countOnly.hidden = detailed;
            grid.querySelectorAll('input, select').forEach(function(field) {
                field.disabled = !detailed;
            });
            countOnly.querySelectorAll('input').forEach(function(field) {
                field.disabled = detailed;
            });
            if (!detailed) return;
            var rows = Math.max(1, Math.min(40, Number(form.rows.value) || 1));
            var cols = Math.max(1, Math.min(30, Number(form.cols.value) || 1));
            var blocked = labels(form.disabled.value),
                validBlocked = 0;
            blocked.forEach(function(label) {
                var match = label.match(/^([A-Z]+)(\d+)$/);
                if (!match) return;
                var row = 0;
                for (var i = 0; i < match[1].length; i++) row = row * 26 + match[1].charCodeAt(i) - 64;
                if (row <= rows && Number(match[2]) <= cols) validBlocked++;
            });
            output.textContent = Math.max(0, rows * cols - validBlocked);
        }
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    }());
</script>
