@props(['role', 'active' => null, 'compact' => false])
@php
    $steps = match ($role) {
        'admin' => [
            ['prepare', 'Prepare', 'Confirm rooms, seating and paper times.', '/admin/exams?tab=datesheet', 'calendar'],
            ['assign', 'Assign', 'Put a primary invigilator in every room.', '/admin/invigilation', 'users'],
            ['confirm', 'Confirm cover', 'Resolve leave requests and approve reserve staff.', '/admin/invigilation#cover-requests', 'check'],
            ['exam-day', 'Run exam day', 'Use the duty list as the live room brief.', '/admin/invigilation#duty-roster', 'shield'],
            ['close', 'Close & report', 'Record issues, then continue to marks and results.', '/admin/marks', 'award'],
        ],
        'teacher' => [
            ['review', 'Review duty', 'Check the paper, date, reporting time and room.', '/teacher/duties', 'calendar'],
            ['respond', 'Confirm availability', 'Request cover before the 48-hour deadline if needed.', '/teacher/duties#my-duties', 'check'],
            ['prepare', 'Prepare', 'Open the seating plan and arrive before students.', '/teacher/schedule?tab=seating', 'grid'],
            ['supervise', 'Invigilate', 'Verify students, protect exam time and report issues.', '/teacher/duties#exam-day-guide', 'shield'],
            ['handover', 'Handover', 'Count scripts and follow your examination cell procedure.', '/teacher/duties#exam-day-guide', 'file'],
        ],
        default => [
            ['schedule', 'Check schedule', 'Review the latest date, start time and room.', '/student/exams?tab=schedule', 'calendar'],
            ['admit', 'Get admit card', 'Download it before examination day.', '/student/exams?tab=admit', 'file'],
            ['seat', 'Find your seat', 'Check the room and seat number.', '/student/exams?tab=seat', 'grid'],
            ['arrive', 'Arrive prepared', 'Bring your admit card and required identification.', '/student/exams?tab=admit', 'check'],
        ],
    };
    $active ??= $steps[0][0];
@endphp
<nav {{ $attributes->class(['invigilation-flow', 'is-compact' => $compact]) }} aria-label="{{ ucfirst($role) }} invigilation flow">
    <div class="invigilation-flow-heading">
        <div><span class="eyebrow">EXAM DAY FLOW</span><h2>{{ $role === 'admin' ? 'Invigilation from planning to handover' : ($role === 'teacher' ? 'Your invigilation checklist' : 'Be ready for examination day') }}</h2></div>
        @unless($compact)<p>Follow these steps in order. Open any step when you need its details.</p>@endunless
    </div>
    <ol>
        @foreach ($steps as [$key, $title, $description, $url, $icon])
            <li>
                <a href="{{ $url }}" @if ($active === $key) aria-current="step" @endif>
                    <span class="invigilation-flow-number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="invigilation-flow-copy"><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
                    <x-icon name="chevron" :size="14" />
                </a>
            </li>
        @endforeach
    </ol>
</nav>
