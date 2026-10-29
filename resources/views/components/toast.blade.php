@props(['tone'=>'success'])
<div class="toast toast-{{ $tone }}" role="status"><x-icon name="check" :size="18"/><span>{{ $slot }}</span></div>
