@props(['id','title'])
<x-modal :id="$id" :title="$title" class="drawer-dialog">{{ $slot }}</x-modal>
