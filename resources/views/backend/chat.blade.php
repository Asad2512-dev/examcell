@if($role==='admin'&&$page['slug']==='admin-ai-assistant')
@include('ai.command-box',['from'=>'assistant'])
<x-card title="{{ ui_text('ai.copy.ai_conversation') }}" description="Ask for actions or answers. Every action opens a proposal for your approval."><div class="chat-messages">
@forelse(($conversation?->messages??collect()) as $m)<article class="chat-message {{ $m->role }}"><strong>{{ $m->role==='user'?'You':'ExamCell AI' }}</strong>
@php
$parts=preg_split('/\[\[link:(\/admin\/[^|\]]+)\|([^\]]+)\]\]/',$m->content,-1,PREG_SPLIT_DELIM_CAPTURE);
@endphp
<pre>{{ $parts[0] }}</pre>@if(isset($parts[1]))<a class="btn btn-secondary btn-small" href="{{ $parts[1] }}">{{ $parts[2] }}</a>@endif</article>
@empty<x-empty-state title="{{ ui_text('ai.copy.what_would_you_like_to_do') }}" description="Try: Generate date sheet for Fall 2026, Who has missing marks?, Draft a results announcement." icon="sparkles"/>
@endforelse</div></x-card>
<h2>{{ ui_text('ai.copy.records_assistant') }}</h2>
@endif

@if(in_array($page['slug'],['assistant-no-matching-records','assistant-permission-denied','assistant-data-unavailable']))
<x-card><x-empty-state :title="match($page['slug']){'assistant-no-matching-records'=>'No matching records','assistant-permission-denied'=>'These records are restricted',default=>'Data is currently unavailable'}" :description="match($page['slug']){'assistant-no-matching-records'=>'Try a different question or choose another record type.','assistant-permission-denied'=>'Choose records within your account’s authorized scope.',default=>'Try again later or contact the examination cell.'}" icon="sparkles"/></x-card>
@endif
<div class="chat-layout"><x-card title="{{ ui_text('ai.copy.your_academic_records_assistant') }}" description="Ask a focused question. Answers use only the records available to your account."><x-slot:actions><span class="badge badge-info"><x-icon name="lock" :size="12"/>{{ ui_text('ai.copy.role_scoped') }}</span></x-slot:actions><div class="chat-messages">
@if($conversation)
@foreach($conversation->messages as $m)<article class="chat-message {{ $m->role }}"><strong><x-icon :name="$m->role==='user'?'user':'sparkles'" :size="14"/> {{ $m->role==='user'?'You':'ExamCell Assistant' }}</strong><pre>{{ $m->content }}</pre>
@if($m->provider_status)<small class="muted">{{ $m->provider_status }}</small>
@endif</article>
@endforeach
@else<x-empty-state title="{{ ui_text('ai.copy.how_can_i_help_you_today') }}" description="Find your examination schedule, seats or results without leaving your workspace." icon="sparkles"/>
@endif</div><div class="chat-prompts" x-data><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Show my upcoming examination schedule';document.getElementById('assistant-intent').value='schedule'">{{ ui_text('ai.copy.upcoming_examinations') }}</button><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Summarize the result records available to my account';document.getElementById('assistant-intent').value='results'">{{ ui_text('ai.copy.review_results') }}</button><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Where are my allocated seats?';document.getElementById('assistant-intent').value='seats'">{{ ui_text('ai.copy.find_seating') }}</button></div>
<form method="POST" action="/workflow/assistant" class="form-grid chat-composer">
@csrf @if($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
@endif
<x-form-field label="Search in"><select name="intent" id="assistant-intent"><option value="results">{{ ui_text('ai.copy.result_records') }}</option><option value="schedule">{{ ui_text('ai.copy.examination_schedule') }}</option><option value="seats">{{ ui_text('ai.copy.seat_assignments') }}</option>
@if($role!=='student')<option value="duties">{{ ui_text('ai.copy.invigilation_duties') }}</option>
@endif</select></x-form-field><x-form-field label="Your question"><textarea name="question" id="assistant-question" maxlength="1000" required placeholder="{{ ui_text('ai.copy.what_would_you_like_to_know') }}" rows="3"></textarea></x-form-field><label class="toggle-field"><input type="checkbox" name="explain" value="1">{{ ui_text('ai.copy.include_an_ai_explanation_if_configured') }}</label><x-button type="submit" icon="arrow">{{ ui_text('ai.copy.retrieve_and_answer') }}</x-button></form><p>{{ ui_text('ai.copy.exact_answers_come_from_authorized_records_optional_expl') }}</p></x-card></div>
