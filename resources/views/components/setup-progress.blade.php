@props(['steps', 'step', 'progress'])
<nav class="setup-progress" aria-label="{{ ui_text('common.copy.academic_setup_steps') }}">
@foreach($steps as $key => $label)
<a href="{{ route('academic-setup', ['step'=>$key]) }}" @if($key===$step) aria-current="step" @endif>
<span class="setup-step-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span>
<span><strong>{{ $label }}</strong><small>{{ $key==='review'?'Check your setup':(($progress[$key]??false)?'Ready':($key===$step?'In progress':'Not started')) }}</small></span>
@if($progress[$key]??false)<x-icon name="check" :size="16"/>@endif
</a>
@endforeach
</nav>
