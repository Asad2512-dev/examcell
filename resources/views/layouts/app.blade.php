<x-app-layout :role="$role" :page="$page" :resource="$resource??null" :user-name="$userName" :initials="$initials" :unread-count="$unreadCount" :notifications="$notifications">
@yield('content')</x-app-layout>
