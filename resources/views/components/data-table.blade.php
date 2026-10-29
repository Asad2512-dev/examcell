@props(['enhanced'=>true])
<div {{ $attributes->class(['table-module']) }} @if($enhanced) x-data="dataTable" x-init="initTable()" @endif>
@if($enhanced)<div class="table-toolbar" x-cloak><label class="table-search"><x-icon name="search" :size="16"/><input type="search" x-model="query" @input="filter()" placeholder="Search this page…" aria-label="Search rows on this page"></label><div class="table-tools"><span class="selection-count" x-show="selected>0" x-text="selected+' selected'"></span><button type="button" class="btn btn-secondary btn-small" x-show="selected>0" @click="exportSelected()"><x-icon name="download" :size="14"/>Export selected</button><details class="column-picker"><summary class="btn btn-secondary btn-small"><x-icon name="columns" :size="15"/>Columns</summary><div class="column-menu"><template x-for="(column,index) in columns" :key="index"><label><input type="checkbox" :checked="column.visible" @change="toggleColumn(index)"><span x-text="column.name"></span></label></template></div></details></div></div>
@endif
<div class="table-scroll">{{ $slot }}</div>
@if($enhanced)<p class="table-no-match" x-show="noMatch" x-cloak>No rows match. Try another search.</p>
@endif</div>
