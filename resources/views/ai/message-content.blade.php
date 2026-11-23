@php
    $messageText = trim((string) $message->content);
    $link = null;
    if (preg_match('/\[\[link:(\/admin\/[^|\]]+)\|([^\]]+)\]\]/', $messageText, $linkMatch)) {
        $link = ['url' => $linkMatch[1], 'label' => $linkMatch[2]];
        $messageText = trim(str_replace($linkMatch[0], '', $messageText));
    }

    $explanation = null;
    $recordText = $messageText;
    $explanationMarker = "\n\nAI explanation";
    $markerPosition = strpos($messageText, $explanationMarker);
    if ($markerPosition !== false) {
        $recordText = trim(substr($messageText, 0, $markerPosition));
        $explanationBlock = trim(substr($messageText, $markerPosition + 2));
        $explanation = trim(preg_replace('/^AI explanation(?: \(review against the authoritative records above\))?:\s*/', '', $explanationBlock));
    }

    $records = json_decode($recordText, true);
    $hasRecords = is_array($records) && json_last_error() === JSON_ERROR_NONE;
    $formatLabel = fn ($key) => match ($key) {
        'starts_at' => 'Date and time',
        'grade_points' => 'Grade points',
        default => ucfirst(str_replace('_', ' ', (string) $key)),
    };
    $formatValue = function ($value, $key = null) {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if ($key === 'percentage' && is_numeric($value)) return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
        if ($key === 'starts_at') {
            try { return \Carbon\Carbon::parse($value)->format('D, M j, Y \a\t g:i A'); } catch (\Throwable) {}
        }
        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    };
@endphp

@if ($hasRecords)
    @if (empty($records))
        <p class="assistant-copy">No authorized records are available for this request.</p>
    @elseif (array_is_list($records) && is_array($records[0] ?? null))
        @php($columns = array_keys($records[0]))
        <div class="assistant-records" role="region" aria-label="Assistant records" tabindex="0">
            <table class="assistant-records-table">
                <thead><tr>
                    @foreach ($columns as $column)<th scope="col">{{ $formatLabel($column) }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>@foreach ($columns as $column)<td>{{ $formatValue($record[$column] ?? null, $column) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <dl class="assistant-details">
            @foreach ($records as $key => $value)
                <dt>{{ $formatLabel($key) }}</dt><dd>{{ $formatValue($value, $key) }}</dd>
            @endforeach
        </dl>
    @endif
@else
    <p class="assistant-copy">{!! nl2br(e($recordText)) !!}</p>
@endif

@if ($explanation)
    <div class="assistant-explanation">
        <strong>Summary</strong>
        <p>{!! nl2br(e($explanation)) !!}</p>
    </div>
@endif

@if ($link)
    <a class="btn btn-secondary btn-small" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
@endif
