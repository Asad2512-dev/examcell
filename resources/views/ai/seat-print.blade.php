@php
$sections=collect($data['sections'])->keyBy('key');
$papers=collect($data['papers']);
$titleType=['map'=>'Room seating plan','door'=>'Door notice','attendance'=>'Attendance sheet'][$type];
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $room['name'] }} · {{ $titleType }}</title>
<link rel="icon" type="image/svg+xml" href="/brand/favicon.svg">
<link rel="stylesheet" href="/css/seat-map.css?v={{ filemtime(public_path('css/seat-map.css')) }}">
<style>
@page{size:A4 portrait;margin:12mm}
:root{--primary:#3538cd;--border:#d6dae3;--muted:#555;--text:#111;--surface:#fff;--surface-muted:#fff;--surface-raised:#fff;--danger:#b42335;--success:#047857}
body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#111;background:#fff;margin:0}
.sheet{max-width:186mm;margin:0 auto;padding:8mm 0}
header{display:flex;align-items:center;gap:12px;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
header img{width:40px;height:40px}header h1{font-size:18px;margin:0}header p{margin:2px 0 0;font-size:12px;color:#444}
.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;font-size:12px;margin-bottom:10px}.meta div{border:1px solid #ccc;border-radius:6px;padding:6px 8px}.meta b{display:block;font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.05em}
.legend{display:flex;flex-wrap:wrap;gap:10px;font-size:11px;margin:8px 0}.legend span{display:inline-flex;align-items:center;gap:5px}.legend i{width:14px;height:14px;border-radius:3px;display:inline-block;border:1px solid #0003}
table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#f1f3f7}
.break{break-before:page;page-break-before:always}
.door h2{font-size:30px;margin:6px 0}.door ul{columns:3;list-style:none;padding:0;font-size:26px;font-weight:700;line-height:1.6}
.sign{height:28px}
.sm2-svg{height:auto!important;max-height:170mm}
.noprint{margin:10px auto;max-width:186mm}@media print{.noprint{display:none}}
</style></head>
<body><div class="noprint"><button onclick="window.print()">{{ ui_text('ai.copy.print') }}</button></div>
<div class="sheet">
<header><img src="/brand/logo-mark.svg" alt="{{ ui_text('ai.copy.examcell_logo') }}"><div><h1>{{ $titleType }} — {{ $room['name'] }}</h1><p>Examination Cell · {{ $papers->pluck('exam')->unique()->implode(', ') }}</p></div></header>
<div class="meta"><div><b>{{ ui_text('ai.copy.date_time') }}</b>{{ $data['title'] }}</div><div><b>{{ ui_text('ai.copy.papers') }}</b>{{ $papers->map(fn($p)=>$p['course'].' ('.$p['section'].')')->implode(', ') }}</div><div><b>{{ ui_text('ai.copy.seats') }}</b>{{ $seats->count() }} students · {{ $room['capacity'] }} seats</div></div>
@if($type==='map')
<div id="print-map"></div>
<div class="legend">@foreach($data['sections'] as $s)<span><i style="background:{{ $s['color'] }}"></i><b>{{ $s['abbr'] }}</b> {{ $s['name'] }} ({{ collect($room['by_section'])->get($s['key'],0) }})</span>@endforeach<span>{{ ui_text('ai.copy.accessible_seat') }}</span></div>
<div class="break"></div>
<h2 style="font-size:16px">Seat list — {{ $room['name'] }}</h2>
<table><thead><tr><th>{{ ui_text('ai.copy.seat') }}</th><th>{{ ui_text('ai.copy.roll_number') }}</th><th>{{ ui_text('ai.copy.name') }}</th><th>{{ ui_text('ai.copy.class') }}</th><th>{{ ui_text('ai.copy.paper') }}</th></tr></thead><tbody>
@foreach($seats as $s)<tr><td>{{ $s['label'] }}</td><td>{{ $s['roll'] }}</td><td>{{ $s['name'] }}</td><td>{{ $sections[$s['section']]['abbr']??'' }}</td><td>{{ \Illuminate\Support\Str::before($s['course']??'',' ·') }}</td></tr>
@endforeach</tbody></table>
<script src="/js/seat-map.js?v={{ filemtime(public_path('js/seat-map.js')) }}"></script>
<script>SeatMap.mount(document.getElementById('print-map'), @json($data), {static:true, print:true, roomId: {{ (int) $room['id'] }}});</script>
@elseif($type==='door')
<section class="door"><h2>{{ $room['name'] }}</h2><p style="font-size:18px">{{ $data['title'] }} · {{ $papers->pluck('course')->implode(', ') }}</p>
<p style="font-size:16px">{{ ui_text('ai.copy.roll_numbers_seated_in_this_room') }}</p>
<ul>@foreach($seats->sortBy('roll') as $s)<li>{{ $s['roll'] }}</li>@endforeach</ul></section>
@else
<table><thead><tr><th style="width:12%">{{ ui_text('ai.copy.seat') }}</th><th style="width:20%">{{ ui_text('ai.copy.roll_number') }}</th><th>{{ ui_text('ai.copy.name') }}</th><th style="width:10%">{{ ui_text('ai.copy.class') }}</th><th style="width:28%">{{ ui_text('ai.copy.signature') }}</th></tr></thead><tbody>
@foreach($seats as $s)<tr><td>{{ $s['label'] }}</td><td>{{ $s['roll'] }}</td><td>{{ $s['name'] }}</td><td>{{ $sections[$s['section']]['abbr']??'' }}</td><td class="sign"></td></tr>
@endforeach</tbody></table>
<p style="font-size:12px;margin-top:16px">Invigilator name &amp; signature: ______________________ &nbsp; Present: ____ / {{ $seats->count() }}</p>
@endif
</div></body></html>
