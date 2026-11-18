<div class="notification-list">
    @forelse($notifications??collect() as $notice)
        <article class="notification-item" data-notice-id="{{ $notice->id }}">
            <div class="notice-copy">
                <div class="notice-title"><strong>{{ $notice->data['title'] ?? 'Notification' }}</strong>
                    @if (!$notice->read_at)
                        <span class="badge badge-info" data-notice-new>{{ ui_text('screens.copy.new') }}</span>
                    @endif
                </div>
                <p class="notice-message" data-notice-message>{{ $notice->data['message'] ?? '' }}</p>
                @if (($compact ?? false) && \Illuminate\Support\Str::length($notice->data['message'] ?? '') > 180)
                    <button type="button" class="notice-expand" data-notice-expand aria-expanded="false">Show more</button>
                @endif
                <small
                    class="notice-time">{{ $notice->created_at->diffForHumans() }}</small>
                @if (!empty($notice->data['url']))
                    <a class="card-link" href="{{ $notice->data['url'] }}">{{ ui_text('screens.copy.open_module') }}
                        <x-icon name="arrow" :size="12" /></a>
                @endif
            </div>
            @if (!$notice->read_at)
                <form data-notice-read method="POST" action="/workflow/notifications/{{ $notice->id }}/read">
                    @csrf<x-button variant="secondary" class="btn-small notice-read-button" type="submit"
                        aria-label="Mark {{ $notice->data['title'] ?? 'notification' }} as read">{{ ui_text('screens.copy.mark_read') }}</x-button>
                </form>
            @endif
        </article>
    @empty<x-empty-state title="{{ ui_text('screens.copy.youre_up_to_date') }}"
            description="New examination updates will appear here." icon="bell" />
    @endforelse
</div>
