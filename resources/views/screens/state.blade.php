
@php
$name=strtolower($page['title']);$extra=is_array($page['extra'])&&array_is_list($page['extra'])?$page['extra']:null;
$stateKey=match(true){str_contains($name,'unpublished')=>'unpublished',str_contains($name,'empty')=>'empty',str_contains($name,'server')||str_contains($name,'unavailable')=>'unavailable',str_contains($name,'permission')||str_contains($name,'unauthorized')=>'permission',str_contains($name,'session')=>'session',str_contains($name,'replacement')=>'replacement',str_contains($name,'success')||str_contains($name,'confirmation')=>'success',str_contains($name,'prediction')=>'prediction',default=>'default'};
$defaultState=array_map(fn($part)=>ui_text('errors.student.'.$stateKey.'.'.$part),['title','description','detail']);
[$title,$description,$detail]=$extra?array_pad($extra,3,''):$defaultState;
@endphp
<section class="card state-card"><span class="state-symbol"><x-icon :name="str_contains($name,'success')?'check':(str_contains($name,'permission')?'shield':'file')" :size="28"/></span><h2>{{ $title }}</h2><p>{{ $description }}</p><p>{{ $detail }}</p>
@if(str_contains($name,'loading'))<div aria-label="{{ ui_text('screens.copy.loading_sample_records') }}"><div class="skeleton"></div><div class="skeleton" style="width:220px"></div><div class="skeleton" style="width:180px"></div></div>
@endif @if(str_contains($name,'progress'))<div class="progress-track"><span></span></div><small class="muted">{{ ui_text('screens.copy.244_of_248_cards_completed_4_require_review') }}</small>
@endif<div class="state-actions"><x-button variant="secondary" :href="route('screen',[$role,$role.'-dashboard'])">{{ ui_text('screens.copy.back_to_dashboard') }}</x-button><x-button data-state-action>{{ $page['action'] }}</x-button></div></section>
