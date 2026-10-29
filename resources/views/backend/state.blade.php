
@php
$uiState=match($page['slug']){
'loading-records'=>['Loading your records','Preparing the latest records for this workspace.','inbox'],
'empty-records'=>['A fresh start','There are no records in this view yet. New records will appear here when available.','inbox'],
'server-error'=>['Something went wrong','We couldn’t complete this request. Return to your workspace and try again.','alert'],
'permission-denied','unauthorized-access'=>['This area is restricted','Your current account does not have permission to access these records.','shield'],
'session-expired'=>['Your session has expired','Sign in again to continue working securely.','clock'],
'success-confirmation'=>['Action completed','Completed actions and their details are confirmed by the server on the relevant workflow.','check'],
'password-reset-confirmation'=>['Check your institution email','If the account exists, password recovery instructions are sent through your configured mail transport.','lock'],
default=>[$page['title'],'This view describes a workflow state. The server reports live outcomes within the relevant module.','file']};
@endphp
<x-card>
@if($page['slug']==='loading-records')<x-skeleton :rows="5"/>
@endif<x-empty-state :title="$uiState[0]" :description="$uiState[1]" :icon="$uiState[2]">
@if($page['slug']==='session-expired')<x-button href="/sign-in">Sign in again</x-button>
@else<x-button variant="secondary" href="/{{ $role }}/{{ $role }}-dashboard">Return to dashboard</x-button>
@endif</x-empty-state></x-card>
