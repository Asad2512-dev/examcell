@props(['items'=>[],'active'=>null])
<nav {{ $attributes->class(['tabs']) }} aria-label="Page sections">
@foreach($items as $item)<a href="{{ $item['href'] }}" @if(($item['key']??null)===$active) aria-current="page" @endif>{{ $item['label'] }}</a>
@endforeach{{ $slot }}</nav>
