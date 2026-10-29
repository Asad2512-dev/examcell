@props(['label'=>null,'error'=>null,'hint'=>null])
<label {{ $attributes->class(['field']) }}>
@if($label)<span>{{ $label }}</span>
@endif{{ $slot }}
@if($hint)<small>{{ $hint }}</small>
@endif @if($error)<small class="field-error" role="alert">{{ $error }}</small>
@endif</label>
