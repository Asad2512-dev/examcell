@props(['type'])
<details class="export-menu">
<summary class="btn btn-secondary"><x-icon name="download" :size="16"/>{{ ui_text('common.copy.export_print') }}<x-icon name="chevron" :size="14"/></summary>
<div class="export-options">
<a href="/reports/{{ $type }}?format=csv"><x-icon name="download" :size="16"/>{{ ui_text('common.copy.export_csv') }}</a>
<a href="/reports/{{ $type }}?format=pdf"><x-icon name="file" :size="16"/>{{ ui_text('common.copy.download_pdf') }}</a>
<button type="button" data-print><x-icon name="print" :size="16"/>{{ ui_text('common.copy.print_this_page') }}</button>
</div>
</details>
