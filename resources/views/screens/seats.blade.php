<div class="seating-layout"><section class="card"><div class="card-heading"><div><h2>Room A-201</h2><p>CS301 Final · 14 Oct 2026 · 09:00–12:00</p></div><span class="badge badge-info">48 seats</span></div><div class="seat-stage">FRONT OF ROOM · INVIGILATOR DESK</div><div class="seat-grid">
@for($r=0;$r<6;$r++)@for($c=1;$c<=8;$c++)
@php $label=chr(65+$r).str_pad($c,2,'0',STR_PAD_LEFT);$state=$r===5&&$c>4?'empty':($r===3&&$c===5?'unavailable':($r===2&&$c===3&&$role==='admin'?'conflict':'assigned')); @endphp<button class="seat {{ $state }}" data-seat="{{ $label }}" data-seat-state="{{ $state }}" aria-label="Seat {{ $label }}, {{ $state }}" @disabled($state==='unavailable')>{{ $label }}<small>{{ $state==='assigned'?'Assigned':ucfirst($state) }}</small></button>
@endfor
@endfor</div><div class="legend"><span><i></i>Assigned</span><span><i class="empty"></i>Empty</span><span><i class="unavailable"></i>Unavailable</span>
@if($role==='admin')<span><i class="conflict"></i>Conflict</span>
@endif</div></section>
<aside class="card"><div class="card-heading"><h2>Allocation details</h2></div><div class="card-body"><x-badge :status="$role==='admin'?'Needs review':'Published'"/><div class="summary-big" data-selected-seat>A01</div><p class="muted text-sm">Selected seat · Room A-201</p><dl class="key-values"><div><dt>Student</dt><dd data-seat-student>{{ $role==='student'?'Ayesha Khan':'Select a seat' }}</dd></div><div><dt>Examination</dt><dd>CS301 Final</dd></div><div><dt>Room capacity</dt><dd>48 seats · 1 unavailable</dd></div><div><dt>Allocation status</dt><dd data-seat-detail>42 assigned · 4 empty</dd></div></dl>
@if($role==='admin')<div class="info-box warning"><x-icon name="alert" :size="16"/><p>Duplicate allocation on C03. Resolve before approval.</p></div><x-button variant="secondary" :href="route('screen',['admin','seating-conflict-report'])">Review duplicate</x-button>
@else<div class="info-box"><x-icon name="help" :size="16"/><p>Review only records available to your role.</p></div>
@endif</div></aside></div>
