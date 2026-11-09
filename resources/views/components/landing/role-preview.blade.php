@props(['role','label'])
@php
$items=match($role){
'admin'=>[['Planning','Draft date sheet','Review proposal'],['Marks','Submission overview','Follow pending work'],['Results','Approval workflow','Review before publication']],
'teacher'=>[['My course','Database Systems','Assigned course'],['Marks','Draft submission','Review and submit'],['Invigilation','Upcoming duty','Check room and time']],
default=>[['Next examination','Database Systems','See date, room and time'],['My seat','Room 101 · B3','Find your assigned place'],['Results','Published records','View grades and rechecking']]};
@endphp
<div class="ls-mobile-role-preview"><div class="ls-window-bar"><span>{{ strtoupper($label) }} / WORKSPACE</span><span class="ls-mini-tag">{{ ui_text('landing.copy.sample') }}</span></div><div class="ls-role-preview-items">
@foreach($items as [$type,$title,$note])<div><span>{{ $type }}</span><strong>{{ $title }}</strong><small>{{ $note }}</small></div>
@endforeach
</div><details class="ls-role-screenshot"><summary>Explore the actual {{ strtolower($label) }} screen <span aria-hidden="true">↗</span></summary><p class="ls-diagram-hint">{{ ui_text('landing.copy.swipe_horizontally_to_inspect_the_product_screenshot') }}</p><div class="ls-local-scroll" tabindex="0" role="region" aria-label="{{ $label }} product screenshot"><img src="/landing/{{ $role }}-workspace.png" alt="Actual {{ strtolower($label) }} dashboard, with demonstration records." width="1280" height="900" loading="lazy" decoding="async"></div><p class="ls-caption">{{ ui_text('landing.copy.actual_product_screenshot_demonstration_data') }}</p></details></div>
