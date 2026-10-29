@props(['role'=>'admin','title'=>'Dashboard'])
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="/{{ $role }}/{{ $role }}-dashboard"><x-icon name="home" :size="16"/><span>{{ ucfirst($role) }}</span></a><x-icon name="chevron" :size="12"/><span aria-current="page">{{ $title }}</span></nav>
