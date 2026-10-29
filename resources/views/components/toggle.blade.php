@props(['name','label','checked'=>false])
<label class="toggle-field"><input type="checkbox" name="{{ $name }}" value="1" @checked($checked) {{ $attributes }}><span>{{ $label }}</span></label>
