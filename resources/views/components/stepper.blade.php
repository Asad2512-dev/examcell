@props(['steps'=>['Configure','Review','Confirm'],'current'=>1])
<ol class="stepper" aria-label="Workflow progress">
@foreach($steps as $step)<li class="{{ $loop->iteration<=$current?'is-current':'' }}" @if($loop->iteration===$current) aria-current="step" @endif><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><strong>{{ $step }}</strong></li>
@endforeach</ol>
