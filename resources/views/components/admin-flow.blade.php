@props(['active'])
@php
    $steps = [
        'rooms' => ['Rooms', 'Add rooms and seat counts', '/admin/rooms'],
        'exams' => ['Date sheet', 'Choose dates and allocate rooms', '/admin/exams?tab=datesheet'],
        'seating' => ['Seating', 'Place students and review', '/admin/seating'],
        'invigilation' => ['Invigilation', 'Assign teachers to exam rooms', '/admin/invigilation'],
    ];
    $guidance = match ($active) {
        'rooms' => 'Add your rooms and their seat counts. Custom seat layouts are optional.',
        'exams' => 'Choose an examination and generate its date sheet. Available rooms are assigned automatically.',
        'seating' => 'Choose an exam slot, generate its seating plan, then review the seats.',
        default => 'Choose an examination, assign invigilators, then review the assignments.',
    };
@endphp
<nav class="admin-flow" aria-label="Exam preparation steps">
    <ol>
        @foreach ($steps as $key => [$label, $description, $url])
            <li>
                <a href="{{ $url }}" @if ($active === $key) aria-current="step" @endif>
                    <span class="admin-flow-number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                    <x-icon name="chevron" :size="16" />
                </a>
            </li>
        @endforeach
    </ol>
    <p>{{ $guidance }}</p>
</nav>
