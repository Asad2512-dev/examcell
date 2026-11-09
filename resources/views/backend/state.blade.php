
@php
$uiStateKey=match($page['slug']){'loading-records'=>'loading-records','empty-records'=>'empty-records','server-error'=>'server-error','permission-denied'=>'permission-denied','unauthorized-access'=>'unauthorized-access','session-expired'=>'session-expired','success-confirmation'=>'success-confirmation','password-reset-confirmation'=>'password-reset-confirmation',default=>'default'};
$uiState=[ui_text('errors.states.'.$uiStateKey.'.title',$page['title']),ui_text('errors.states.'.$uiStateKey.'.description'),match($uiStateKey){'loading-records','empty-records'=>'inbox','server-error'=>'alert','permission-denied','unauthorized-access'=>'shield','session-expired'=>'clock','success-confirmation'=>'check','password-reset-confirmation'=>'lock',default=>'file'}];
@endphp
<x-card>
@if($page['slug']==='loading-records')<x-skeleton :rows="5"/>
@endif<x-empty-state :title="$uiState[0]" :description="$uiState[1]" :icon="$uiState[2]">
@if($page['slug']==='session-expired')<x-button href="/sign-in">{{ ui_text('admin.copy.sign_in_again') }}</x-button>
@else<x-button variant="secondary" href="/{{ $role }}/{{ $role }}-dashboard">{{ ui_text('admin.copy.return_to_dashboard') }}</x-button>
@endif</x-empty-state></x-card>
