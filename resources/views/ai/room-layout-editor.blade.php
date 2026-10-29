@php
$rlRooms=\App\Models\Room::orderBy('name')->get();
$rlRoom=$rlRooms->firstWhere('id',(int)request('layout_room'))??$rlRooms->first();
$rlLayout=$rlRoom?->layout();
$rlAcc=$rlRoom?collect($rlRoom->accessible_seats??[])->map(fn($n)=>$rlLayout->labelOf((int)$n))->implode(', '):'';
@endphp
@if($rlRoom)
<link rel="stylesheet" href="/css/seat-map.css?v={{ filemtime(public_path('css/seat-map.css')) }}">
<x-card title="Room layout" description="Set the real rows, columns, aisles and unusable seats. Capacity is calculated from the usable seats.">
<div class="card-body">
<form method="GET" class="sm2-picker">@foreach(collect(request()->query())->except('layout_room') as $k=>$v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
@endforeach<label class="field"><span>Room</span><select name="layout_room" onchange="this.form.submit()">@foreach($rlRooms as $r)<option value="{{ $r->id }}" @selected($r->id===$rlRoom->id)>{{ $r->name }} ({{ $r->capacity }} seats)</option>
@endforeach</select></label></form>
<form method="POST" action="/admin/rooms/{{ $rlRoom->id }}/layout" class="form-grid" id="room-layout-form">@csrf
<x-input name="rows" type="number" label="Rows (front to back)" min="1" max="40" :value="old('rows',$rlLayout->rows)" required/>
<x-input name="cols" type="number" label="Seats per row" min="1" max="30" :value="old('cols',$rlLayout->cols)" required/>
<x-input name="aisles" label="Aisle after column(s)" :value="old('aisles',implode(', ',$rlLayout->aisles))" placeholder="e.g. 3, 6" hint="An aisle separates neighbours: seats across it are not side by side."/>
<x-input name="disabled" label="Broken / unusable seats" :value="old('disabled',implode(', ',$rlRoom->disabled_seats??[]))" placeholder="e.g. A1, C4"/>
<x-input name="accessible" label="Accessible seats" :value="old('accessible',$rlAcc)" placeholder="e.g. A1, A2" hint="Students marked special-needs are only placed here."/>
<x-select name="door_side" label="Door on"><option value="right" @selected(($rlRoom->door_side??'right')==='right')>Right wall</option><option value="left" @selected($rlRoom->door_side==='left')>Left wall</option></x-select>
<p class="muted text-xs" data-layout-capacity aria-live="polite"></p>
<x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Save room layout?" data-confirm-message="Capacity becomes the number of usable seats. Existing seat numbers in saved plans follow the new layout." data-confirm-label="Save layout">Save layout</x-button></form>
<div id="room-layout-preview"></div>
</div></x-card>
<script src="/js/seat-map.js?v={{ filemtime(public_path('js/seat-map.js')) }}"></script>
<script>
(function(){
  var f=document.getElementById('room-layout-form'), box=document.getElementById('room-layout-preview'), cap=f.querySelector('[data-layout-capacity]'), name=@json($rlRoom->name);
  function list(v){return String(v||'').toUpperCase().split(/[\s,;]+/).filter(Boolean);}
  function render(){
    var rows=Math.max(1,Math.min(40,+f.rows.value||1)), cols=Math.max(1,Math.min(30,+f.cols.value||1));
    var disabled=list(f.disabled.value), pos=SeatMap.positions(rows,cols,disabled), acc=list(f.accessible.value);
    var accN=pos.filter(function(p){return acc.indexOf(p.label)>=0;}).map(function(p){return p.n;});
    var aisles=list(f.aisles.value).map(Number).filter(function(a){return a>=1&&a<cols;});
    cap.textContent='Usable seats (capacity): '+pos.length;
    SeatMap.mount(box,{entry:0,mode:'admin',rooms:[{id:1,name:name+' (preview)',rows:rows,cols:cols,aisles:aisles,door:f.door_side.value,disabled:disabled,positions:pos,accessible:accN,capacity:pos.length,used:0,by_section:{}}],seats:[],sections:[],papers:[],pairs:[],summary:null,editable:false},{static:true});
  }
  f.addEventListener('input',render); f.addEventListener('change',render); render();
})();
</script>
@endif
