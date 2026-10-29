
@php
$t=$page['title'];
$isPublished=str_contains(strtolower($t),'published');
$next=match(true){str_contains($t,'Student')=>'student-academic-profile',str_contains($t,'Teacher')=>'teacher-profile-availability',str_contains($t,'Rechecking')=>'rechecking-request-details',str_contains($t,'replacement')||str_contains($t,'Replacement')=>'replacement-comparison',str_contains($t,'Result')||str_contains($t,'result')=>'individual-result-details',str_contains($t,'Duty')||str_contains($t,'duty')=>'duty-assignment',default=>'examination-details'};
$validNext=collect($screens)->firstWhere('slug',$next);
@endphp
<x-table :title="$page['title']" :headers="$page['data']['headers']" :rows="$page['data']['rows']"/>
<div class="context-card card"><div class="context-symbol"><x-icon name="shield" :size="22"/></div><div><h3>{{ $isPublished?'Published record protection':'Record review & linked information' }}</h3><p>{{ $isPublished?'Published marks require an authorized correction, a mandatory reason and a preserved audit trail.':'Use the row action to view a record. Search, status filters, selection, sorting and CSV exports are available.' }}</p></div>
@if($validNext)<x-button variant="secondary" :href="route('screen',[$role,$next])">Open related workflow</x-button>
@else<x-button variant="secondary" data-open-catalog>Related workflows</x-button>
@endif</div>
@if(str_contains($t,'replacement')||str_contains($t,'Replacement'))<div class="info-box warning"><x-icon name="alert" :size="18"/><p>Only eligible, available teachers may be proposed. An administrator must approve the replacement; the original assignment remains in history.</p></div>
@endif
