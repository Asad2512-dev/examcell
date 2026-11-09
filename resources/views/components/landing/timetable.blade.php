@props(['id'=>'timetable'])
<div class="ls-timetable" tabindex="0" role="region" aria-label="{{ ui_text('landing.copy.illustrative_five_day_timetable_scroll_horizontally_on_s') }}">
<div class="ls-calendar-days"><span>{{ ui_text('landing.copy.time') }}</span><span>{{ ui_text('landing.copy.mon') }}<small>02</small></span><span>{{ ui_text('landing.copy.tue') }}<small>03</small></span><span>{{ ui_text('landing.copy.wed') }}<small>04</small></span><span>{{ ui_text('landing.copy.thu') }}<small>05</small></span><span>{{ ui_text('landing.copy.fri') }}<small>06</small></span></div>
<div class="ls-calendar-body"><div class="ls-time-axis"><span>09:00</span><span>11:00</span><span>14:00</span></div><div class="ls-day-columns"><i></i><i></i><i></i><i></i><i></i></div>
<div class="ls-exam ls-exam-db"><small>09:00 — 11:00</small><strong>{{ ui_text('landing.copy.database') }}<br>{{ ui_text('landing.copy.systems') }}</strong><span>{{ ui_text('landing.copy.cs301_room_101') }}</span></div>
<div class="ls-exam ls-exam-physics" data-calendar-move><small>09:00 — 11:00</small><strong>{{ ui_text('landing.copy.physics') }}</strong><span>{{ ui_text('landing.copy.ph201_room_204') }}</span></div>
<div class="ls-exam ls-exam-os"><small>09:00 — 11:00</small><strong>{{ ui_text('landing.copy.operating') }}<br>{{ ui_text('landing.copy.systems') }}</strong><span>{{ ui_text('landing.copy.cs302_room_101') }}</span></div>
<div class="ls-exam ls-exam-web"><small>14:00 — 16:00</small><strong>{{ ui_text('landing.copy.web') }}<br>{{ ui_text('landing.copy.engineering') }}</strong><span>{{ ui_text('landing.copy.cs303_room_204') }}</span></div>
<div class="ls-no-paper">{{ ui_text('landing.copy.friday_pm') }}<br>{{ ui_text('landing.copy.no_paper') }}</div></div>
</div>
