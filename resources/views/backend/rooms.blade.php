<section id="room-form">
    <x-card :title="$record ? 'Edit room' : 'Add a room'" description="Give the room a name and enter the number of seats available for exams.">
        <form class="form-grid room-basic-form" method="POST" action="{{ route('resource.save', ['rooms', $record?->id]) }}">
            @csrf
            <x-input name="name" label="Room name" :value="old('name', $record?->name)" placeholder="e.g. Hall A or Room 101" maxlength="80" required />
            <x-input name="capacity" label="Total seats" type="number" :value="old('capacity', $record?->capacity)" min="1" max="2000" required />
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
<x-card title="Your rooms" description="Use Layout only when you need specific rows, aisles, or accessible seats." id="room-directory">
    <x-slot:actions><x-button href="/admin/exams?tab=datesheet" icon="arrow">Continue to date sheet</x-button></x-slot:actions>
    <form method="GET" action="/admin/rooms" class="room-search">
        <x-input name="q" label="Find a room" :value="request('q')" placeholder="Search by room name" />
        <x-button type="submit" variant="secondary" icon="search">Search</x-button>
        @if (request('q'))
            <x-button href="/admin/rooms" variant="secondary">Clear</x-button>
        @endif
    </form>
    <x-data-table :enhanced="false">
        <table class="data-table">
            <thead><tr><th>Room</th><th>Seats</th><th>Availability</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($records as $room)
                    <tr>
                        <td><strong>{{ $room->name }}</strong></td>
                        <td>{{ $room->capacity }}</td>
                        <td><x-badge :status="$room->active ? 'Available' : 'Unavailable'" /></td>
                        <td><div class="room-actions">
                            <x-button :href="'/admin/rooms?id=' . $room->id . '#room-form'" variant="secondary" :aria-label="'Edit ' . $room->name">Edit</x-button>
                            <x-button :href="'/admin/rooms?layout_room=' . $room->id . '#room-layout'" variant="secondary" :aria-label="'Configure layout for ' . $room->name">Layout</x-button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-state">{{ request('q') ? 'No matching rooms. Clear your search to see all rooms.' : 'No rooms yet. Add your first room above to get started.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
    @include('backend.pagination')
</x-card>
<x-disclosure id="room-layout" title="Optional: customize a room layout" description="Rows, aisles, unusable seats, and accessibility. You can skip this and use the seat count above." :open="request()->filled('layout_room') || $errors->hasAny(['rows', 'cols', 'aisles', 'disabled', 'accessible', 'door_side'])">
    @include('ai.room-layout-editor')
</x-disclosure>
