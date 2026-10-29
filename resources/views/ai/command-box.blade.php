@php $cbConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured(); @endphp
<x-card class="ai-command"><form method="POST" action="/admin/ai/command" class="form-grid card-body" role="search">@csrf<input type="hidden" name="from" value="{{ $from??'dashboard' }}">
@if(!empty($conversation))<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
<label class="field"><span><x-icon name="sparkles" :size="16"/> Tell AI what you need</span><input type="text" name="text" maxlength="500" required placeholder="Generate date sheet for Fall 2026 · Who has missing marks? · Pass rate by department" @disabled(!$cbConfigured)></label>
<x-button type="submit" icon="arrow" :disabled="!$cbConfigured">Go</x-button>
@unless($cbConfigured)<p class="muted text-xs">AI is not configured yet. <a class="card-link" href="/admin/settings?tab=ai">Open AI Settings</a></p>@endunless
@if(session('ai_reply'))<p class="callout" role="status">{{ session('ai_reply') }}</p>@endif
<p class="muted text-xs">AI proposes, the system checks, you approve. Nothing is changed until you confirm.</p></form></x-card>
