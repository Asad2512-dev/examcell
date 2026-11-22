@props(['cycle'])
@php
    $next = $cycle['next'];
    $first = $cycle['done'] === 0;
@endphp
<section class="cycle" aria-labelledby="cycle-title">
    <div class="cycle-hero">
        <div class="cycle-hero-main">
            <span
                class="eyebrow">{{ $next ? ($first ? 'WELCOME · LET’S GET STARTED' : 'YOUR NEXT STEP · ' . $next['number'] . ' OF ' . $cycle['total']) : 'ALL DONE' }}</span>
            @if ($next)
                <h2 id="cycle-title">{{ $next['title'] }}</h2>
                <p>{{ $next['text'] }}</p>
                @if ($next['issues'])
                    <ul class="cycle-issues">
                        @foreach (array_slice($next['issues'], 0, 3) as $issue)
                            <li><x-icon name="alert" :size="14" /><span>{{ $issue['text'] }}</span><a
                                    href="{{ $issue['url'] }}">{{ $issue['action'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
                <div class="cycle-hero-actions">
                    <x-button :href="$next['url']" icon="arrow">{{ $next['button'] }}</x-button>
                </div>
            @else
                <h2 id="cycle-title">Everything for this examination is done</h2>
                <p>Results are published. Start a new examination whenever you are ready.</p>
                <div class="cycle-hero-actions"><x-button href="/admin/exams/new" icon="plus">New
                        examination</x-button></div>
            @endif
        </div>
        <div class="cycle-hero-side">
            <div class="cycle-ring" style="--p: {{ $cycle['percent'] }}" role="img"
                aria-label="{{ $cycle['percent'] }} percent complete">
                <strong>{{ $cycle['percent'] }}%</strong><small>{{ $cycle['done'] }} of {{ $cycle['total'] }}
                    done</small></div>
            @if ($cycle['exams']->count() > 1)
                <form method="GET" class="cycle-exam">
                    <label class="field"><span>Examination</span>
                        <select name="exam" data-autosubmit aria-label="Choose examination">
                            @foreach ($cycle['exams'] as $e)
                                <option value="{{ $e->id }}" @selected($cycle['exam']?->id === $e->id)>{{ $e->name }}
                                </option>
                            @endforeach
                        </select></label>
                    <noscript><x-button type="submit" variant="secondary">Show</x-button></noscript>
                </form>
            @elseif ($cycle['exam'])
                <p class="cycle-exam-name">{{ $cycle['exam']->name }}</p>
            @endif
        </div>
    </div>
    <ol class="cycle-steps">
        @foreach ($cycle['steps'] as $step)
            <li class="cycle-step is-{{ $step['status'] }}">
                <span class="cycle-dot" aria-hidden="true">
                    @if ($step['status'] === 'done')
                        <x-icon name="check" :size="14" />
                    @else
                        {{ $step['number'] }}
                    @endif
                </span>
                <a class="cycle-step-body" href="{{ $step['url'] }}">
                    <strong>{{ $step['title'] }}</strong>
                    <small>{{ $step['status'] === 'done' ? $step['text'] : $step['why'] }}</small>
                </a>
                @if ($step['status'] !== 'done' && $step['issues'] && $step !== $next)
                    <span class="cycle-flag" title="{{ $step['issues'][0]['text'] }}"><x-icon name="alert"
                            :size="13" />{{ count($step['issues']) }}</span>
                @endif
                <span
                    class="cycle-state">{{ $step['status'] === 'done' ? 'Done' : ($step['status'] === 'current' ? 'Up next' : 'Later') }}</span>
            </li>
        @endforeach
    </ol>
</section>
