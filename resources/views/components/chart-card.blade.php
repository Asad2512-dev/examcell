@props(['title','labels'=>[],'values'=>[],'type'=>'bar','description'=>'Authorized records'])
<x-card :title="$title" :description="$description"><div class="chart-container" x-data="chartCard" x-init="observe(@js($labels), @js($values), @js($type))"><canvas aria-label="{{ $title }}" role="img"></canvas></div><details class="chart-data"><summary>{{ ui_text('common.copy.view_chart_data') }}</summary><dl>
@foreach($labels as $i=>$label)<div><dt>{{ $label }}</dt><dd>{{ $values[$i]??0 }}</dd></div>
@endforeach</dl></details></x-card>
