@props(['role','page','groups','resource'=>null,'unreadCount'=>0,'pendingCount'=>0])
<aside class="sidebar" id="sidebar" aria-label="{{ ucfirst($role) }} navigation"><a class="brand" href="/{{ $role }}/{{ $role }}-dashboard" aria-label="ExamCell dashboard"><x-logo wordmark :size="38"/></a><div class="workspace-label"><span>{{ ucfirst($role) }} workspace</span><span class="workspace-dot"></span></div>
<nav class="nav-items"><div class="nav-section-label">WORKSPACE</div>
@foreach($groups as $group)
@php
$active=\App\View\WorkspaceNavigation::active($group,$page['slug'],$resource);
@endphp

@if(count($group['children'])>1)<details class="nav-group" @if($active) open @endif><summary class="nav-item {{ $active?'active':'' }}" title="{{ $group['label'] }}"><x-icon :name="$group['icon']" :size="18"/><span>{{ $group['label'] }}</span>
@if($group['slug']==='result-batch-overview'&&$pendingCount)<span class="nav-count">{{ $pendingCount }}</span>@endif
@if($group['slug']==='notification-center'&&$unreadCount)<span class="nav-count">{{ $unreadCount }}</span>@endif
<x-icon class="nav-chevron" name="chevron" :size="12"/></summary><div class="nav-children">
@foreach($group['children'] as $child)<a href="/{{ $role }}/{{ $child['slug'] }}" @if($child['slug']===$page['slug']) aria-current="page" @endif>{{ $child['title'] }}</a>
@endforeach</div></details>
@else<a class="nav-item {{ $active?'active':'' }}" href="/{{ $role }}/{{ $group['slug'] }}" title="{{ $group['label'] }}" @if($active) aria-current="page" @endif><x-icon :name="$group['icon']" :size="18"/><span>{{ $group['label'] }}</span>
@if($group['icon']==='bell'&&$unreadCount)<span class="nav-count">{{ $unreadCount }}</span>
@endif</a>
@endif
@endforeach</nav><div class="sidebar-footer">
<button type="button" class="collapse-button" data-collapse-nav aria-label="Collapse navigation"><x-icon name="chevron" :size="16"/><span>Collapse sidebar</span></button></div></aside>
