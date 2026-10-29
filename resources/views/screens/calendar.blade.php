<section class="card"><div class="card-heading"><div><h2 data-calendar-heading>October 2026</h2><p data-calendar-range>12–18 October · Examination week</p></div><div class="calendar-tools"><button class="icon-button" data-calendar-shift="-1" aria-label="Previous week">‹</button><button class="icon-button" data-calendar-shift="1" aria-label="Next week">›</button><button class="btn btn-secondary btn-small" data-calendar-mode="week">Week</button><button class="btn btn-secondary btn-small" data-calendar-mode="list">List</button><button class="icon-button" data-print aria-label="Print schedule"><x-icon name="print" :size="17"/></button></div></div>
<div data-calendar-week class="table-scroll"><div class="week-grid">
@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $i=>$day)<div class="calendar-day {{ $i===2?'today':'' }}"><div class="day-heading"><span>{{ $day }}</span><strong data-calendar-date>{{ 12+$i }}</strong></div>
@if(in_array($i,[2,4,6]))<button class="calendar-event {{ $role==='admin' && $i===6?'conflict':'' }}" data-calendar-event data-course="{{ ['CS301','CS302','CS303'][array_search($i,[2,4,6])] }}"><strong>{{ ['CS301','CS302','CS303'][array_search($i,[2,4,6])] }} {{ $i===6?'Practical':'Final' }}</strong><span>{{ $i===6?'13:00–15:00':'09:00–12:00' }}</span><span>BSIT {{ $i===6?'5-B':'5-A' }}</span><span>{{ $i===2?'A-201':($i===4?'B-104':'Lab 2') }}</span>
@if($role==='admin' && $i===6)<span>Room conflict · review required</span>
@endif</button>
@endif</div>
@endforeach</div></div>
<div data-calendar-list class="calendar-list" hidden>
@foreach([['14','CS301 · Database Systems','09:00–12:00','A-201'],['16','CS302 · Operating Systems','09:00–12:00','B-104'],['18','CS303 · Computer Networks','13:00–15:00','Lab 2']] as [$day,$course,$time,$room])<div class="calendar-list-row"><div class="calendar-date">OCT<strong>{{ $day }}</strong></div><div><h3>{{ $course }}</h3><p>{{ $time }} · {{ $room }} · BSIT 5-A</p></div><x-badge :status="$role==='admin' && $day==='18'?'Conflict':'Published'"/></div>
@endforeach</div></section>
@if($role==='admin')<div class="info-box warning"><x-icon name="alert" :size="18"/><p><strong>Proposed schedule: 2 constraints require review.</strong> An unresolved room overlap and an unavailable invigilator must be addressed before approval.</p><a class="card-link" href="{{ route('screen',['admin','conflict-detection-results']) }}">Review conflicts <x-icon name="chevron" :size="13"/></a></div>
@else<div class="info-box"><x-icon name="calendar" :size="18"/><p>Published examination timetable · Last updated 10 Oct 2026. Arrive at your assigned room 30 minutes before the examination.</p></div>
@endif
