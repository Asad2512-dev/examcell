
@include('backend.filters')<x-card class=""><h2>{{ ui_text('admin.copy.audit_history') }}</h2>
<x-timeline>
@forelse($records as $log)<article class="notification-item"><div><strong>{{ $log->action }}</strong><p>{{ class_basename($log->entity_type) }} #{{ $log->entity_id }} · actor #{{ $log->actor_id??'CLI' }} · {{ $log->created_at }}</p><pre style="white-space:pre-wrap">{{ json_encode($log->changes,JSON_PRETTY_PRINT) }}</pre></div></article>
@empty<p>{{ ui_text('admin.copy.no_audit_events_in_your_scope') }}</p>
@endforelse</x-timeline> @include('backend.pagination')</x-card>
