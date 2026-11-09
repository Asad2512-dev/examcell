@props(['id','title'])
<dialog id="{{ $id }}" {{ $attributes->class(['app-dialog']) }} aria-labelledby="{{ $id }}-title"><div class="card-heading"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="icon-button" data-close-dialog aria-label="{{ ui_text('common.copy.close_dialog') }}"><x-icon name="close"/></button></div><div class="dialog-content">{{ $slot }}</div></dialog>
