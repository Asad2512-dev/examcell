@props(['title', 'slug', 'module' => 'Admin', 'ws' => null])
@php
    $u = auth()->user();
    if ($ws) {
        request()->attributes->set('ws', $ws);
    }
@endphp
<x-app-layout role="admin" :page="['title' => $title, 'slug' => $slug, 'module' => $module, 'kind' => 'form']" :user-name="$u->name" :initials="collect(explode(' ', $u->name))
    ->map(fn($n) => mb_substr($n, 0, 1))
    ->take(2)
    ->implode('')" :notifications="$u->notifications()->latest()->limit(10)->get()" :unread-count="$u->unreadNotifications()->count()">
    @if (session('status'))
        <div class="callout callout-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="callout callout-danger" role="alert"><strong>Please check these details.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{ $slot }}
</x-app-layout>
