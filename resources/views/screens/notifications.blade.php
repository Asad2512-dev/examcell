<div class="notification-list">
@forelse($notifications??collect() as $notice)<article class="notification-item"><div class="notice-copy"><div class="notice-title"><strong>{{ $notice->data['title']??'Notification' }}</strong>
@if(!$notice->read_at)<span class="badge badge-info">New</span>
@endif</div><p>{{ $notice->data['message']??'' }}</p><small class="notice-time">{{ $notice->created_at->diffForHumans() }}</small>
@if(!empty($notice->data['url']))<a class="card-link" href="{{ $notice->data['url'] }}">Open module <x-icon name="arrow" :size="12"/></a>
@endif</div>
@if(!$notice->read_at)<form method="POST" action="/workflow/notifications/{{ $notice->id }}/read">
@csrf<x-button variant="secondary" class="btn-small" type="submit" aria-label="Mark {{ $notice->data['title']??'notification' }} as read">Mark read</x-button></form>
@endif</article>
@empty<x-empty-state title="You’re up to date" description="New examination updates will appear here." icon="bell"/>
@endforelse</div>
