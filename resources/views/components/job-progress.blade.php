@props(['status'=>'queued','count'=>0,'failure'=>null])
<div class="job-progress"><div class="flex justify-between items-center gap-4"><x-badge :status="ucfirst($status)"/><strong class="text-xs">{{ $count }} documents generated</strong></div>
@if($status==='completed')<progress max="1" value="1" aria-label="Generation completed"></progress>
@elseif($status==='processing')<progress aria-label="Generation is processing"></progress>
@endif @if($failure)<p class="field-error mt-3">{{ $failure }}</p>
@endif</div>
