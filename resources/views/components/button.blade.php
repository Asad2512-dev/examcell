@props(['variant'=>'primary','href'=>null,'icon'=>null])
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['btn','btn-'.$variant]) }}>
@if($icon)<x-icon :name="$icon" :size="17" />
@endif{{ $slot }}</a>
@else
<button {{ $attributes->merge(['type'=>'button'])->class(['btn','btn-'.$variant]) }}>
@if($icon)<x-icon :name="$icon" :size="17" />
@endif{{ $slot }}</button>
@endif
