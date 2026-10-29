@props(['deadline'=>null,'submitted'=>false])
@php
$remaining=$deadline?->getTimestamp()-now()->getTimestamp();
$tone=$submitted?'success':($deadline?($remaining<=0?'danger':($remaining<48*3600?'warning':'info')):'neutral');
$label=$submitted?'Submitted':($deadline?($remaining<=0?'Overdue':($remaining<48*3600?'Due within 48 hours':'Upcoming deadline')):'No deadline set');
@endphp
<div class="submission-countdown" @if($deadline&&!$submitted) data-submission-countdown data-deadline="{{ $deadline->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" @endif><span class="badge badge-{{ $tone }}" data-countdown-badge><x-icon name="clock" :size="12"/><span data-countdown-label>{{ $label }}</span></span>
@if($deadline)<time class="deadline-date" datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->timezone('Asia/Karachi')->format('d M Y · H:i') }} PKT</time>
@endif</div>
