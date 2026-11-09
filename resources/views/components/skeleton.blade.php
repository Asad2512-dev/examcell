@props(['rows'=>4])
<div class="skeleton-stack" role="status" aria-label="{{ ui_text('common.copy.loading_records_84415c') }}"><span class="sr-only">{{ ui_text('common.copy.loading_records') }}</span>@for($i=0;$i<$rows;$i++)<div class="skeleton"></div>@endfor</div>
