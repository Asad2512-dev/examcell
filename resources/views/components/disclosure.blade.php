@props(['title','description'=>null,'open'=>false])
<details {{ $attributes->class(['workspace-disclosure']) }} @if($open) open @endif>
<summary><span><strong>{{ $title }}</strong>@if($description)<small>{{ $description }}</small>@endif</span><x-icon name="chevron" :size="18" class="disclosure-chevron"/></summary>
<div class="disclosure-content">{{ $slot }}</div>
</details>
