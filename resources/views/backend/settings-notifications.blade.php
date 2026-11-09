@php $nsEvents=\App\Services\Settings::EVENTS; @endphp
<x-card title="{{ ui_text('admin.copy.notification_settings') }}" description="Choose which automatic in-app notifications are sent. Turning one off stops new notices of that kind; nothing already sent is removed.">
<form method="POST" action="/admin/settings/notifications" class="form-grid card-body">@csrf
@foreach($nsEvents as $key=>[$label,$titles])<label class="toggle-field"><input type="hidden" name="events[{{ $key }}]" value="0"><input type="checkbox" name="events[{{ $key }}]" value="1" @checked(\App\Services\Settings::get('notify.'.$key,true))> {{ $label }}<span class="muted text-xs"> — {{ implode(', ',$titles) }}</span></label>
@endforeach
<x-button type="submit" icon="check">{{ ui_text('admin.copy.save_notification_settings') }}</x-button></form></x-card>
<x-card title="{{ ui_text('admin.copy.announcements') }}" description="AI drafts announcements for schedule changes, results and duties. Nothing is sent until you approve."><div class="card-body"><a class="btn btn-secondary" href="/admin/assistant?tab=actions">{{ ui_text('admin.copy.draft_an_announcement') }}</a></div></x-card>
