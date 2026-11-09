@props(['size'=>40,'pdf'=>false,'wordmark'=>false,'light'=>false])
@if($wordmark)
<span {{ $attributes->class(['brand-lockup']) }}><img src="{{ asset(config('branding.logo.mark')) }}" alt="" width="{{ $size }}" height="{{ $size }}"><span class="brand-name">{{ config('branding.short_name') }}<small>{{ config('branding.identity') }}</small></span></span>
@else
<img src="{{ $pdf ? public_path(config('branding.logo.mark_png')) : asset(config('branding.logo.mark')) }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class(['examcell-logo']) }}>
@endif
