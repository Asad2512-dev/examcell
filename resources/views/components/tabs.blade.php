@props(['items'=>[],'active'=>null])
<nav {{ $attributes->class(['tabs']) }} aria-label="{{ ui_text('common.copy.page_sections') }}">
@foreach($items as $item)<a href="{{ $item['href'] }}" @if(($item['key']??null)===$active) aria-current="page" @endif>{{ $item['label'] }}</a>
@endforeach{{ $slot }}</nav>
