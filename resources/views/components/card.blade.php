@props(['title'=>null,'description'=>null])
<section {{ $attributes->class(['card']) }}>
@if($title)<div class="card-heading"><div><h2>{{ $title }}</h2>
@if($description)<p>{{ $description }}</p>
@endif</div>{{ $actions??'' }}</div>
@endif{{ $slot }}</section>
