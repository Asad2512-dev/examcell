@props(['title','description'=>''])
<header class="page-heading"><div><div class="eyebrow">{{ $eyebrow??'EXAMINATION WORKSPACE' }}</div><h1>{{ $title }}</h1>
@if($description)<p>{{ $description }}</p>
@endif</div><div class="page-actions">{{ $slot }}</div></header>
