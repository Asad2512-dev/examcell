@props(['exam'])
@php $items = app(\App\Services\ExamCycle::class)->readiness($exam); @endphp
<div class="readiness" role="status" aria-label="Before you continue">
    @forelse ($items as $item)
        <div class="readiness-item"><x-icon name="alert" :size="16" /><span>{{ $item['text'] }}</span><a
                href="{{ $item['url'] }}">{{ $item['action'] }}</a></div>
    @empty
        <div class="readiness-item readiness-ok"><x-icon name="check" :size="16" /><span>Everything looks ready
                for {{ $exam->name }}.</span></div>
    @endforelse
</div>
