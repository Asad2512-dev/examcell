@php
$smUser=auth()->user();
$smEntries=app(\App\Services\ScopedQueries::class)->schedules($smUser)->whereHas('seatingPlan')->with('examCourse.course')->orderBy('starts_at')->get();
$smEntry=request('entry')?$smEntries->firstWhere('id',(int)request('entry')):$smEntries->first();
$smData=$smEntry?app(\App\Http\Controllers\AiController::class)->seatData($smUser,$smEntry):null;
@endphp
<style>
.sm-grid{display:grid;gap:6px}.sm-seat{min-height:48px;border-radius:8px;border:2px solid transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:11px;color:#111;cursor:default;padding:2px;text-align:center}
.sm-seat[draggable=true]{cursor:grab}.sm-empty{background:transparent;border:1px dashed #9ca3af;color:#6b7280}.sm-dim{opacity:.18}.sm-hit{outline:3px solid #111;outline-offset:2px}.sm-mine{outline:4px solid #dc2626;outline-offset:2px;font-weight:700}
.sm-board{text-align:center;font-size:12px;letter-spacing:.08em;padding:6px;margin-bottom:10px;border-radius:6px;background:#e5e7eb;color:#111}.sm-door{font-size:12px;margin-top:8px;color:#6b7280}.sm-legend{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}.sm-chip{display:inline-flex;gap:6px;align-items:center;font-size:12px;border:1px solid #d1d5db;border-radius:999px;padding:2px 10px;background:transparent;color:inherit;cursor:pointer}.sm-chip i{width:12px;height:12px;border-radius:3px;display:inline-block}
</style>
@if($smData)
<x-card title="Room map" :description="$smData['title'].' — front of room at the top, door at the bottom.'">
<div class="card-body" x-data="seatMap(@js($smData))">
@if($smEntries->count()>1)<form method="GET" class="page-actions"><select name="entry" onchange="this.form.submit()" aria-label="Choose paper">@foreach($smEntries as $e)<option value="{{ $e->id }}" @selected($e->id===$smEntry->id)>{{ $e->examCourse->course->code }} · {{ $e->starts_at->format('d M H:i') }}</option>
@endforeach</select></form>
@endif
<div class="page-actions">
<button type="button" class="btn btn-secondary btn-small" :aria-pressed="view==='room'" @click="view='room'">Room-wise</button>
<button type="button" class="btn btn-secondary btn-small" :aria-pressed="view==='class'" @click="view='class'">Class-wise</button>
@if($smUser->role!=='student')<input type="search" x-model="q" placeholder="Find roll number" aria-label="Find a roll number" @input.debounce.300ms="locate()">@endif
</div>
<p class="callout callout-danger" role="alert" x-show="errors.length"><template x-for="e in errors"><span x-text="e" style="display:block"></span></template></p>
<div class="sm-legend" role="group" aria-label="Class legend"><template x-for="s in sections" :key="s"><button type="button" class="sm-chip" :aria-pressed="filter===s" @click="filter=filter===s?'':s"><i :style="'background:'+color(s)"></i><span x-text="s"></span></button></template></div>
<template x-if="view==='room'"><div>
<template x-for="room in data.rooms" :key="room.id"><section style="margin-bottom:24px"><div class="page-actions"><h3 x-text="room.name+' · '+room.capacity+' seats'"></h3><a class="btn btn-secondary btn-small" target="_blank" :href="'/seat-map/'+data.entry+'/room/'+room.id+'/print'">Print A4</a></div>
<div class="sm-board">FRONT · BOARD / INVIGILATOR DESK</div>
<div class="sm-grid" :style="'grid-template-columns:repeat('+room.cols+',minmax(0,1fr))'">
<template x-for="n in room.capacity" :key="n"><div class="sm-seat" :class="cls(room.id,n)" :style="style(room.id,n)" :id="'seat-'+room.id+'-'+n" tabindex="0"
 :draggable="canDrag(room.id,n)" @dragstart="drag=seatAt(room.id,n)" @dragover.prevent @drop.prevent="drop(room.id,n)"
 :title="label(room.id,n)" :aria-label="label(room.id,n)"><strong x-text="n"></strong><span x-text="seatAt(room.id,n)?.roll??''"></span></div></template></div>
<div class="sm-door">⬇ Door</div></section></template></div></template>
<template x-if="view==='class'"><div><template x-for="s in sections" :key="s"><section style="margin-bottom:16px" x-show="!filter||filter===s"><h3 x-text="s"></h3>
<ul><template x-for="x in data.seats.filter(z=>z.section===s)" :key="x.student"><li :class="{'sm-mine':x.mine}" x-text="(x.roll??'')+' '+(x.name??'')+' → '+roomName(x.room)+', seat '+x.seat"></li></template></ul></section></template></div></template>
<p class="muted text-xs" x-show="!data.locked">Drag a student onto another seat (or an empty one) to swap. Every move is re-validated instantly.</p>
@if($smUser->role==='student')<p class="muted text-xs">Your seat is outlined in red.</p>@endif
</div></x-card>
<script>
function seatMap(data){return{data,view:'room',filter:'',q:'',errors:[],drag:null,
get sections(){return [...new Set(this.data.seats.map(s=>s.section))].sort()},
color(s){const p=['#93c5fd','#fca5a5','#86efac','#fcd34d','#c4b5fd','#f9a8d4','#67e8f9','#fdba74','#bef264','#d1d5db'];return p[this.sections.indexOf(s)%p.length]},
seatAt(r,n){return this.data.seats.find(s=>s.room===r&&s.seat===n)},
roomName(id){return this.data.rooms.find(r=>r.id===id)?.name},
style(r,n){const s=this.seatAt(r,n);return s?'background:'+this.color(s.section):''},
cls(r,n){const s=this.seatAt(r,n);return{'sm-empty':!s,'sm-dim':s&&this.filter&&s.section!==this.filter,'sm-mine':s?.mine,'sm-hit':s&&this.q&&s.roll&&s.roll.toLowerCase()===this.q.trim().toLowerCase()}},
label(r,n){const s=this.seatAt(r,n);return s?(s.name?s.name+' · '+s.roll+' · '+s.course+' · ':'')+s.section+' · seat '+n:'Empty seat '+n},
canDrag(r,n){return !this.data.locked&&!!this.seatAt(r,n)},
locate(){const s=this.data.seats.find(x=>x.roll&&x.roll.toLowerCase()===this.q.trim().toLowerCase());if(s)document.getElementById('seat-'+s.room+'-'+s.seat)?.scrollIntoView({block:'center',behavior:'smooth'})},
async drop(r,n){if(!this.drag||this.data.locked)return;const d=this.drag;this.drag=null;if(d.room===r&&d.seat===n)return;this.errors=[];
const res=await fetch('/workflow/seat-map/'+this.data.entry+'/swap',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content},body:JSON.stringify({student_id:d.student,room_id:r,seat:n})});
const j=await res.json().catch(()=>({errors:['The move could not be saved.']}));if(res.ok)this.data=j.data;else this.errors=j.errors||[j.message||'The move was rejected.']}}}
</script>
@else
<x-card title="Room map"><x-empty-state title="No seating plan yet" description="Once a seating plan is saved, the visual room map appears here."/></x-card>
@endif
