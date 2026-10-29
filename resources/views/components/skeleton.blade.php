@props(['rows'=>4])
<div class="skeleton-stack" role="status" aria-label="Loading records"><span class="sr-only">Loading records…</span>@for($i=0;$i<$rows;$i++)<div class="skeleton"></div>@endfor</div>
