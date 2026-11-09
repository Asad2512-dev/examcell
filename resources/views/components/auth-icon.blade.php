@props(['name','size'=>20])
@php $paths=match($name){
'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>',
'eye'=>'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
'eye-off'=>'<path d="m3 3 18 18M10.6 5.1A11 11 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.3 4.2M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.6M10 10a3 3 0 0 0 4 4"/>',
'headset'=>'<path d="M4 14v-3a8 8 0 0 1 16 0v5a4 4 0 0 1-4 4h-2"/><rect x="2" y="10" width="5" height="8" rx="2"/><rect x="17" y="10" width="5" height="8" rx="2"/><path d="M11 20h3"/>',
'cap'=>'<path d="m2 8 10-5 10 5-10 5L2 8Z"/><path d="M6 10v6c3 3 9 3 12 0v-6m4-2v9"/>',
'chart'=>'<path d="M5 20V11m7 9V4m7 16V8" stroke-width="4"/>',
'check'=>'<path d="m5 12 4 4L19 6"/>',
default=>'<path d="m5 12 4 4L19 6"/>',
}; @endphp
<svg {{ $attributes }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
