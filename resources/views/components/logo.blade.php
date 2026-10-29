@props(['size'=>40,'pdf'=>false,'wordmark'=>false,'light'=>false])
@if($wordmark)
<span {{ $attributes->class(['brand-lockup']) }}><img src="{{ asset('brand/logo-mark.svg') }}" alt="" width="{{ $size }}" height="{{ $size }}"><span class="brand-name">ExamCell<small>EXAMINATION MANAGEMENT</small></span></span>
@else
<img src="{{ $pdf ? public_path('brand/logo-mark.png') : asset('brand/logo-mark.svg') }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class(['examcell-logo']) }}>
@endif
