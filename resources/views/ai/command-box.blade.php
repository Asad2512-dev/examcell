@php $cbConfigured=app(\App\Services\AI\AiClient::class)->configured(); @endphp
<x-card class="ai-command ai-command-primary">
    <form method="POST" action="/admin/ai/command" class="form-grid card-body" role="search">@csrf<input type="hidden"
            name="from" value="{{ $from ?? 'dashboard' }}">
        @if (!empty($conversation))
            <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
        @endif
        <div class="ai-command-intro"><span class="heading-icon"><x-icon name="sparkles" :size="20" /></span><div><h2>Tell ExamCell AI what to do</h2><p>Use one simple sentence. AI finds the right records, prepares the work and shows you a checked proposal before anything important changes.</p></div></div>
        <label class="field ai-command-field"><span class="sr-only">{{ ui_text('ai.copy.tell_ai_what_you_need') }}</span><input id="ai-command-text" type="text" name="text"
                maxlength="500" required
                autocomplete="off" placeholder="Try: Assign invigilators for Fall 2026…"
                @disabled(!$cbConfigured)></label>
        <x-button type="submit" icon="arrow" :disabled="!$cbConfigured">Prepare It</x-button>
        <div class="ai-prompt-examples" aria-label="Example prompts">
            @foreach (['Create the date sheet for the latest draft exam', 'Assign fair invigilation duties', 'Who has not submitted marks?', 'Draft a schedule announcement'] as $example)
                <button type="button" class="btn btn-secondary btn-small" data-ai-prompt="{{ $example }}" onclick="document.getElementById('ai-command-text').value=this.dataset.aiPrompt;document.getElementById('ai-command-text').focus()">{{ $example }}</button>
            @endforeach
        </div>
        @unless ($cbConfigured)
            <p class="muted text-xs">{{ ui_text('ai.copy.ai_is_not_configured_yet') }} <a class="card-link"
                    href="/admin/settings?tab=ai">{{ ui_text('ai.copy.open_ai_settings') }}</a></p>
        @endunless
        @if (session('ai_reply'))
            <p class="callout" role="status">{{ session('ai_reply') }}</p>
        @endif
        <p class="ai-safety-note"><x-icon name="shield" :size="15" /> AI can prepare date sheets, seating, duties, reports, announcements and review queues. Validations and your approval protect final changes.</p>
    </form>
</x-card>
