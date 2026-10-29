@props(['label','value','icon'=>'chart','note'=>'','tone'=>'blue'])
<article {{ $attributes->class(['kpi','card']) }}><div class="kpi-top"><span class="kpi-label">{{ $label }}</span><span class="kpi-icon tone-{{ $tone }}"><x-icon :name="$icon" :size="19"/></span></div><strong>{{ $value }}</strong>
@if($note)<small>{{ $note }}</small>
@endif</article>
