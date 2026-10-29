@props(['label','value'=>'','type'=>'text','name'=>null,'required'=>false,'readonly'=>false])
<label class="field"><span>{{ $label }} @if($required)<span class="required">*</span>
@endif</span>
@if($type==='textarea')
<textarea name="{{ $name ?? Illuminate\Support\Str::slug($label) }}" @required($required) @readonly($readonly) rows="3">{{ $value }}</textarea>
@elseif($type==='select')
<select name="{{ $name ?? Illuminate\Support\Str::slug($label) }}" @required($required) @disabled($readonly)>{{ $slot }}</select>
@else
<input type="{{ $type }}" name="{{ $name ?? Illuminate\Support\Str::slug($label) }}" value="{{ $value }}" @required($required) @readonly($readonly) {{ $attributes }}>
@endif
<span class="field-error" aria-live="polite"></span></label>
