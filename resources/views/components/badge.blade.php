@props(['status'=>'Draft'])
@php $tone = match(strtolower($status)) { 'valid','approved','published','active','available','resolved','eligible','completed','assigned'=>'success', 'unavailable','returned','in progress','under review','pending','needs review'=>'warning', 'invalid','conflict','rejected','failed','error','inactive','ineligible','high','missing'=>'danger', 'submitted','medium'=>'info', default=>'neutral' }; @endphp
<span class="badge badge-{{ $tone }}"><span class="status-dot" aria-hidden="true"></span>{{ $status }}</span>
