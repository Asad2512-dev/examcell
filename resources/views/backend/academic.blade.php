
@if($resource==='boundaries'&&$records->count())
@foreach(collect($records->items())->groupBy('grading_scheme_id') as $uiScheme=>$uiBoundaries)
@php
$uiSortedBounds=$uiBoundaries->sortBy('minimum')->values();
@endphp

<x-card :title="'Grade boundaries · scheme #'.$uiScheme" description="Percentage ranges from the configured boundaries on this page."><div class="range-bar">
@foreach($uiSortedBounds as $uiIndex=>$uiBoundary)
@php
$uiWidth=max(0,($uiSortedBounds[$uiIndex+1]->minimum??100)-$uiBoundary->minimum);
@endphp

<div style="flex:{{ $uiWidth }}" title="{{ $uiBoundary->grade }}: {{ $uiBoundary->minimum }}–{{ $uiSortedBounds[$uiIndex+1]->minimum??100 }}%">{{ $uiBoundary->grade }} · {{ $uiBoundary->minimum }}%</div>
@endforeach</div></x-card>
@endforeach
@endif
<form class="filter-bar academic-module-picker" method="GET" action="/{{ $role }}/records"><x-form-field ><span>{{ ui_text('admin.copy.manage_academic_records') }}</span><select name="resource">
@foreach(\App\Support\Resources::definitions() as $key=>$definition)<option value="{{ $key }}" @selected($key===$resource)>{{ ucwords(str_replace('-',' ',$key)) }}</option>
@endforeach</select></x-form-field><x-button variant="secondary" class="" type="submit">{{ ui_text('admin.copy.open_module') }}</x-button></form>
@include('backend.filters')
<x-card class=""><div class="card-heading"><h2>{{ $record?'Edit record #'.$record->id:'Create '.str_replace('-',' ',$resource) }}</h2></div><form class="form-grid" method="POST" action="{{ route('resource.save',[$resource,$record?->id]) }}">
@csrf
@foreach(\App\Support\Resources::fields($resource) as $field=>$rule)
@php $value=old($field,$record?->$field);$options=\App\Support\Resources::options($field);$enum=match($field){'role'=>['student'=>'Student','teacher'=>'Teacher','admin'=>'Administrator'],'type'=>['midterm'=>'Midterm','final'=>'Final','quiz'=>'Quiz','assignment'=>'Assignment'],'status'=>['draft'=>'Draft','approved'=>'Approved','archived'=>'Archived'],default=>[]};$options=$options?:$enum;
@endphp
<x-form-field ><span>{{ ucwords(str_replace('_',' ',$field)) }}</span>
@if(str_contains($rule,'boolean'))<input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked($value??true)>
@elseif($options||str_ends_with($field,'_id'))<select name="{{ $field }}" required><option value="">{{ ui_text('admin.copy.select_record') }}</option>
@foreach($options as $id=>$label)<option value="{{ $id }}" @selected((string)$value===(string)$id)>{{ $label }} (#{{ $id }})</option>
@endforeach</select>
@else<input name="{{ $field }}" @if($field==='weight') data-weight-input @endif type="{{ $field==='password'?'password':($field==='email'?'email':(str_contains($rule,'date')?(str_contains($field,'_at')?'datetime-local':'date'):((str_contains($rule,'numeric')||str_contains($rule,'integer'))?'number':'text'))) }}" @if(str_contains($rule,'numeric')) step="0.01" @endif value="{{ $field==='password'?'':($value instanceof \Carbon\CarbonInterface?$value->format(str_contains($field,'_at')?'Y-m-d\TH:i':'Y-m-d'):$value) }}" @if(str_contains($rule,'required')&&$field!=='password') required @endif autocomplete="{{ $field==='password'?'new-password':'off' }}">
@endif
@error($field)<small class="required">{{ $message }}</small>
@enderror</x-form-field>
@endforeach
@if($resource==='components')
@php
$uiOtherWeights=app(\App\Services\AcademicContext::class)->apply(\App\Models\AssessmentComponent::query())->when($record,fn($q)=>$q->where('id','!=',$record->id))->get()->groupBy('exam_course_id')->map(fn($group)=>(float)$group->sum('weight'));
@endphp

<div class="weight-meter" data-weight-summary data-totals="{{ json_encode($uiOtherWeights) }}"><span>{{ ui_text('admin.copy.total_assessment_weight') }} <strong data-weight-value>{{ old('weight',$record?->weight??0) }}%</strong></span><progress data-weight-progress max="100" value="{{ old('weight',$record?->weight??0) }}" aria-label="{{ ui_text('admin.copy.total_assessment_weighting_percentage') }}"></progress><p><span data-weight-message>{{ ui_text('admin.copy.includes_other_configured_components_the_server_validate') }}</span></p></div>
@endif<div class="form-actions"><x-button variant="primary" class="" type="submit">{{ $record?'Save changes':'Create record' }}</x-button></div></form></x-card>
<x-card class="table-card"><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('admin.copy.record') }}</th>
@foreach(array_keys(\App\Support\Resources::fields($resource)) as $f)
@if($f!=='password')<th>{{ ucwords(str_replace('_',' ',preg_replace('/_id$/','',$f))) }}</th>
@endif @endforeach<th>{{ ui_text('admin.copy.action') }}</th></tr></thead><tbody>
@php
$uiLabels=[];
foreach(array_keys(\App\Support\Resources::fields($resource)) as $uiF){if(str_ends_with($uiF,'_id')){try{$uiLabels[$uiF]=\App\Support\Resources::options($uiF);}catch(\Throwable $e){$uiLabels[$uiF]=[];}}}
@endphp
@forelse($records as $row)<tr><td>#{{ $row->id }}</td>
@foreach(array_keys(\App\Support\Resources::fields($resource)) as $f)
@if($f!=='password')<td>{{ is_bool($row->$f)?($row->$f?'Yes':'No'):(isset($uiLabels[$f])?($uiLabels[$f][$row->$f]??$row->$f):$row->$f) }}</td>
@endif @endforeach<td><a class="btn btn-secondary" href="/{{ $role }}/records?resource={{ $resource }}&id={{ $row->id }}">{{ ui_text('admin.copy.edit') }}</a></td></tr>
@empty<tr><td colspan="12" class="empty-state">{{ ui_text('admin.copy.no_records_yet_create_the_first_record_above') }}</td></tr>
@endforelse</tbody></table></x-data-table>
@include('backend.pagination')</x-card>
