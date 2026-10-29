@props(['title'=>'Nothing here yet','description'=>'Records will appear here when available.','icon'=>'inbox'])
<div {{ $attributes->class(['empty-state']) }}><span class="state-illustration"><x-icon :name="$icon" :size="28"/></span><h3>{{ $title }}</h3><p>{{ $description }}</p>{{ $slot }}</div>
