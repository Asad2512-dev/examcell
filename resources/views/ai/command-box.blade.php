@php $cbConfigured=app(\App\Services\AI\AiSettings::class)->keyConfigured(); @endphp
<x-card class="ai-command"><form method="POST" action="/admin/ai/command" class="form-grid card-body" role="search">@csrf<input type="hidden" name="from" value="{{ $from??'dashboard' }}">
@if(!empty($conversation))<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
<label class="field"><span><x-icon name="sparkles" :size="16"/> {{ ui_text('ai.copy.tell_ai_what_you_need') }}</span><input type="text" name="text" maxlength="500" required placeholder="{{ ui_text('ai.copy.generate_date_sheet_for_fall_2026_who_has_missing_marks') }}" @disabled(!$cbConfigured)></label>
<x-button type="submit" icon="arrow" :disabled="!$cbConfigured">{{ ui_text('ai.copy.go') }}</x-button>
@unless($cbConfigured)<p class="muted text-xs">{{ ui_text('ai.copy.ai_is_not_configured_yet') }} <a class="card-link" href="/admin/settings?tab=ai">{{ ui_text('ai.copy.open_ai_settings') }}</a></p>@endunless
@if(session('ai_reply'))<p class="callout" role="status">{{ session('ai_reply') }}</p>@endif
<p class="muted text-xs">{{ ui_text('ai.copy.ai_proposes_the_system_checks_you_approve_nothing_is_cha') }}</p></form></x-card>
