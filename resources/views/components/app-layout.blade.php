@props(['role','page','resource'=>null,'userName','initials','unreadCount'=>0,'notifications'=>[]])
@php
$pendingApprovals=$role==='admin'?app(\App\Services\AcademicContext::class)->apply(\App\Models\ResultBatch::query())->whereIn('status',['submitted','under_review'])->count():0;
@endphp

@php
$groups=\App\View\WorkspaceNavigation::groups($role);
@endphp

<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $page['title'] }} — ExamCell</title><x-assets /></head><body data-role="{{ $role }}" data-screen="{{ $page['slug'] }}" data-kind="{{ $page['kind'] }}"><a class="skip-link" href="#main-content">Skip to content</a><div class="nav-overlay" data-close-nav></div>
<x-sidebar :role="$role" :page="$page" :groups="$groups" :resource="$resource" :unread-count="$unreadCount" :pending-count="$pendingApprovals"/><div class="app-main"><x-topbar :role="$role" :page="$page" :unread-count="$unreadCount" :user-name="$userName" :initials="$initials"/><main id="main-content" class="content" tabindex="-1"><div class="demo-line"><span><span class="demo-dot"></span>Secure workspace · verified records</span><time class="workspace-date" datetime="{{ now('Asia/Karachi')->toDateString() }}"><x-icon name="calendar" :size="12"/>{{ now('Asia/Karachi')->format('D, d M Y') }}</time></div>{{ $slot }}<footer class="app-footer"><span>ExamCell · Examination Management</span><span>Carefully coordinated. Clearly recorded.</span></footer></main></div>
<div class="toast-stack" aria-live="polite"></div><x-confirm-dialog/>
<x-drawer id="detail-dialog" title="Record details"><div id="record-detail-content"></div></x-drawer>
<dialog class="catalog-dialog" id="catalog-dialog" aria-label="Find a workspace tool"><div class="catalog-head"><x-icon name="search"/><input id="catalog-search" type="search" aria-label="Search workspace tools" placeholder="Search screens and workspace tools…"><button type="button" class="icon-button" data-close-dialog aria-label="Close search"><x-icon name="close"/></button></div><div class="catalog-body">
@foreach($groups as $group)<section class="catalog-group"><h3>{{ $group['label'] }}</h3><div>
@foreach($group['children'] as $tool)<a data-catalog-link href="/{{ $role }}/{{ $tool['slug'] }}"><span>{{ $tool['title'] }}</span><x-icon name="chevron" :size="14"/></a>
@endforeach</div></section>
@endforeach<div class="catalog-empty" hidden>No tools match your search.</div></div></dialog>
<dialog class="notification-dialog" id="notification-dialog" aria-labelledby="notifications-title"><div class="card-heading"><h2 id="notifications-title">Notifications <span class="badge badge-info">{{ $unreadCount }} unread</span></h2><button type="button" class="icon-button" data-close-dialog aria-label="Close notifications"><x-icon name="close"/></button></div>
@include('screens.notifications',['compact'=>true])</dialog>
</body></html>
