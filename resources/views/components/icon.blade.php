@props(['name' => 'file', 'size' => 20])
{!! \App\View\Icons::svg(array_merge(['name' => $name, 'size' => $size], $attributes->getAttributes())) !!}
