
@if(in_array($page['slug'],['assistant-no-matching-records','assistant-permission-denied','assistant-data-unavailable']))
<x-card><x-empty-state :title="match($page['slug']){'assistant-no-matching-records'=>'No matching records','assistant-permission-denied'=>'These records are restricted',default=>'Data is currently unavailable'}" :description="match($page['slug']){'assistant-no-matching-records'=>'Try a different question or choose another record type.','assistant-permission-denied'=>'Choose records within your account’s authorized scope.',default=>'Try again later or contact the examination cell.'}" icon="sparkles"/></x-card>
@endif
<div class="chat-layout"><x-card title="Your academic records assistant" description="Ask a focused question. Answers use only the records available to your account."><x-slot:actions><span class="badge badge-info"><x-icon name="lock" :size="12"/>Role scoped</span></x-slot:actions><div class="chat-messages">
@if($conversation)
@foreach($conversation->messages as $m)<article class="chat-message {{ $m->role }}"><strong><x-icon :name="$m->role==='user'?'user':'sparkles'" :size="14"/> {{ $m->role==='user'?'You':'ExamCell Assistant' }}</strong><pre>{{ $m->content }}</pre>
@if($m->provider_status)<small class="muted">{{ $m->provider_status }}</small>
@endif</article>
@endforeach
@else<x-empty-state title="How can I help you today?" description="Find your examination schedule, seats or results without leaving your workspace." icon="sparkles"/>
@endif</div><div class="chat-prompts" x-data><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Show my upcoming examination schedule';document.getElementById('assistant-intent').value='schedule'">Upcoming examinations</button><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Summarize the result records available to my account';document.getElementById('assistant-intent').value='results'">Review results</button><button class="btn btn-secondary btn-small" type="button" @click="document.getElementById('assistant-question').value='Where are my allocated seats?';document.getElementById('assistant-intent').value='seats'">Find seating</button></div>
<form method="POST" action="/workflow/assistant" class="form-grid chat-composer">
@csrf @if($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
@endif
<x-form-field label="Search in"><select name="intent" id="assistant-intent"><option value="results">Result records</option><option value="schedule">Examination schedule</option><option value="seats">Seat assignments</option>
@if($role!=='student')<option value="duties">Invigilation duties</option>
@endif</select></x-form-field><x-form-field label="Your question"><textarea name="question" id="assistant-question" maxlength="1000" required placeholder="What would you like to know?" rows="3"></textarea></x-form-field><label class="toggle-field"><input type="checkbox" name="explain" value="1">Include an AI explanation, if configured</label><x-button type="submit" icon="arrow">Retrieve and answer</x-button></form><p>Exact answers come from authorized records. Optional explanations cannot change marks, schedules or results.</p></x-card></div>
