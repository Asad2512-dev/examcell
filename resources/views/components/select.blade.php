@props(['name','label'=>null])
<x-form-field :label="$label" :error="$errors->first($name)"><select name="{{ $name }}" {{ $attributes }}>{{ $slot }}</select></x-form-field>
