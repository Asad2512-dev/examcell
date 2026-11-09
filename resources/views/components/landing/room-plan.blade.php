@props(['id'=>'room', 'compact'=>false])
<svg class="ls-room-svg {{ $compact?'ls-room-compact':'' }}" viewBox="0 0 640 510" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc">
<title id="{{ $id }}-title">{{ ui_text('landing.copy.illustrative_examination_room_plan') }}</title>
<desc id="{{ $id }}-desc">{{ ui_text('landing.copy.four_rows_of_six_labeled_seats_a_central_aisle_a_whitebo') }}</desc>
<defs><pattern id="{{ $id }}-stripes" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="6" stroke="currentColor" stroke-opacity=".16" stroke-width="2"/></pattern></defs>
<g class="ls-room-outline"><path d="M580 145V30H60V470H580V215" fill="none" stroke="currentColor" stroke-width="3"/><path d="M580 145a70 70 0 0 0-70 70h70" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 5"/></g>
<text x="570" y="250" text-anchor="end" class="ls-svg-note">{{ ui_text('landing.copy.door') }}</text>
<rect x="208" y="30" width="224" height="8" rx="2" class="ls-board"/><text x="320" y="63" text-anchor="middle" class="ls-svg-note">{{ ui_text('landing.copy.whiteboard_front') }}</text>
<rect x="256" y="81" width="128" height="30" rx="3" class="ls-desk"/><text x="320" y="100" text-anchor="middle" class="ls-svg-small">{{ ui_text('landing.copy.invigilator') }}</text>
<text x="320" y="302" text-anchor="middle" transform="rotate(-90 320 302)" class="ls-svg-note ls-aisle">{{ ui_text('landing.copy.aisle') }}</text>
@foreach(range(0,3) as $row)
<text x="38" y="{{ 182+$row*79 }}" class="ls-svg-note">{{ chr(65+$row) }}</text>
@foreach(range(0,5) as $column)
@php
$label=chr(65+$row).($column+1);
$tone=($row+$column)%3;
$x=84+$column*73+($column>=3?36:0);
$y=150+$row*79;
@endphp
<g class="ls-seat ls-seat-tone-{{ $tone }} {{ $label==='D6'?'ls-seat-blocked':'' }} {{ $label==='B3'?'ls-seat-match':'' }} {{ in_array($label,['C2','C4'])?'ls-seat-swap':'' }}" data-seat="{{ $label }}" transform="translate({{ $x }} {{ $y }})">
<rect class="ls-seat-back" x="7" y="-5" width="42" height="7" rx="2"/><rect class="ls-seat-face" width="56" height="49" rx="5"/>
@if($tone===1)<rect width="56" height="49" rx="5" fill="url(#{{ $id }}-stripes)"/>
@endif
<text x="28" y="22" text-anchor="middle" class="ls-seat-label">{{ $label }}</text><text x="28" y="38" text-anchor="middle" class="ls-seat-class">{{ $label==='D6'?'BLOCKED':($label==='A1'?'ACCESS':(['CS · A','IT · B','SE · C'][$tone])) }}</text>
</g>
@endforeach
@endforeach
<rect class="ls-search-ring" x="222" y="222" width="70" height="63" rx="9" fill="none" stroke="var(--palette-3538cd)" stroke-width="2" stroke-dasharray="4 3"/>
<path class="ls-swap-path" d="M181 338Q275 280 363 338" fill="none" stroke="var(--palette-3538cd)" stroke-width="1.5" stroke-dasharray="5 5"/><text x="275" y="314" text-anchor="middle" class="ls-svg-small ls-swap-label">{{ ui_text('landing.copy.proposed_swap') }}</text>
<text x="84" y="495" class="ls-svg-note">{{ ui_text('landing.copy.room_101') }}</text><text x="556" y="495" text-anchor="end" class="ls-svg-note">{{ ui_text('landing.copy.illustrative_plan_24_positions') }}</text>
</svg>
