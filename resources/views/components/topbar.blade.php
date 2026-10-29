@props(['role','page','unreadCount'=>0,'userName','initials'])
<header class="topbar"><button type="button" class="icon-button mobile-toggle" data-open-nav aria-label="Open navigation"><x-icon name="menu"/></button><x-breadcrumb :role="$role" :title="$page['module']"/><div class="topbar-right"><button type="button" class="global-search" data-open-catalog aria-label="Find a workspace tool"><x-icon name="search" :size="16"/><span>Search workspace</span><kbd>⌘ K</kbd></button>
@if($role==='admin')<x-academic-switcher/>
@endif
<x-theme-toggle/><button type="button" class="icon-button notification-toggle" data-open-notifications aria-label="Notifications, {{ $unreadCount }} unread"><x-icon name="bell" :size="19"/>
@if($unreadCount)<span></span>
@endif</button>
@include('layouts.account-menu')</div></header>
