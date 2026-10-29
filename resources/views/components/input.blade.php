@props(['name','label'=>null,'type'=>'text','hint'=>null])
<x-form-field :label="$label" :hint="$hint" :error="$errors->first($name)"><input name="{{ $name }}" type="{{ $type }}" {{ $attributes->merge(['value'=>old($name),'aria-invalid'=>$errors->has($name)?'true':'false']) }}></x-form-field>
