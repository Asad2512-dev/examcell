@props(['headers','rows','title'=>null,'actions'=>true,'readonly'=>false])
<section class="card data-card" data-table-panel>
@if($title)<div class="card-heading"><h2>{{ $title }}</h2><span class="muted text-sm">{{ count($rows) }} records</span></div>
@endif
<div class="table-scroll"><table class="data-table"><thead><tr><th class="select-cell"><input type="checkbox" aria-label="Select all rows" data-select-all></th>
@foreach($headers as $header)<th><button class="sort-button" data-sort="{{ $loop->index+1 }}">{{ $header }}<span aria-hidden="true">↕</span></button></th>
@endforeach @if($actions)<th class="actions-cell"><span class="sr-only">Actions</span></th>
@endif</tr></thead><tbody>
@foreach($rows as $row)<tr><td class="select-cell"><input type="checkbox" aria-label="Select row {{ $loop->iteration }}"></td>
@foreach($row as $index=>$value)<td data-label="{{ $headers[$index] ?? 'Value' }}">
@if(in_array($value,['Active','Inactive','Approved','Published','Draft','Conflict','Returned','In Progress','Under Review','Pending','Resolved','Available','Unavailable','Submitted','Eligible','Ineligible','Needs review','Confirmed','Assigned','Completed']))<x-badge :status="$value"/>
@else{{ $value }}
@endif</td>
@endforeach @if($actions)<td class="actions-cell"><button class="icon-button" data-row-details aria-label="View record"><x-icon name="chevron" :size="16"/></button></td>
@endif</tr>
@endforeach
</tbody></table><div class="table-empty" hidden><x-icon name="search" :size="28"/><h3>No matching records</h3><p>Try another search or clear your filters.</p><button class="btn btn-secondary" data-clear-filters>Clear filters</button></div></div>
<footer class="table-footer"><span data-table-count>Showing 1–{{ count($rows) }} of {{ count($rows) }} records</span><div class="pagination"><button aria-label="Previous page" disabled>‹</button><button class="current" aria-current="page">1</button><button aria-label="Next page" disabled>›</button></div></footer>
</section>
