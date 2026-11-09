@props(['enhanced'=>true])
<div {{ $attributes->class(['table-module']) }} @if($enhanced) x-data="dataTable" x-init="initTable()" @endif>
@if($enhanced)<div class="table-toolbar" x-cloak><label class="table-search"><x-icon name="search" :size="16"/><input type="search" x-model="query" @input="filter()" placeholder="{{ ui_text('common.copy.search_this_page') }}" aria-label="{{ ui_text('common.copy.search_rows_on_this_page') }}"></label><div class="table-tools"><span class="selection-count" x-show="selected>0" x-text="selected+' selected'"></span><button type="button" class="btn btn-secondary btn-small" x-show="selected>0" @click="exportSelected()"><x-icon name="download" :size="14"/>{{ ui_text('common.copy.export_selected') }}</button><details class="column-picker"><summary class="btn btn-secondary btn-small"><x-icon name="columns" :size="15"/>{{ ui_text('common.copy.columns') }}</summary><div class="column-menu"><template x-for="(column,index) in columns" :key="index"><label><input type="checkbox" :checked="column.visible" @change="toggleColumn(index)"><span x-text="column.name"></span></label></template></div></details></div></div>
@endif
<div class="table-scroll">{{ $slot }}</div>
@if($enhanced)<p class="table-no-match" x-show="noMatch" x-cloak>{{ ui_text('common.copy.no_rows_match_try_another_search') }}</p>
@endif</div>
