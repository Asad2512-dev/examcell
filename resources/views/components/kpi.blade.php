@props(['label','value','icon'=>'chart','note'=>'','tone'=>'blue'])
<x-stat-card :label="$label" :value="$value" :icon="$icon" :note="$note" :tone="$tone" {{ $attributes }}/>
