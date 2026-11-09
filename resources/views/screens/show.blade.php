@extends('layouts.app')
@section('content')
<div class="print-only print-header"><x-logo :size="44"/><div><strong>{{ ui_text('screens.copy.examcell') }}</strong><p>Examination Cell · {{ $page['title'] }}</p></div><span class="print-reference">{{ now('Asia/Karachi')->format('d M Y') }}</span></div>
@php
$wsState=request()->attributes->get('ws');
$wsDef=$wsState?collect(\App\Support\Workspaces::definition($role))->firstWhere('key',$wsState['key']):null;
$wsTab=$wsDef?collect($wsDef['tabs'])->firstWhere('key',$wsState['tab']):null;
@endphp
@php $hdrTitle=($wsDef&&($wsState['primary']??null)===$page['slug'])?$wsDef['label'].(count($wsDef['tabs'])>1&&strcasecmp($wsDef['label'],$wsTab['label'])!==0?' · '.$wsTab['label']:''):$page['title']; @endphp
@php
$roleDescription=$role==='teacher'?match($module){
'marks'=>ui_text('screens.role_descriptions.teacher.marks'),
'duties'=>(($wsState['key']??null)==='profile'?ui_text('screens.role_descriptions.teacher.duties_profile'):ui_text('screens.role_descriptions.teacher.duties')),
'schedule'=>ui_text('screens.role_descriptions.teacher.schedule'),
'roster'=>ui_text('screens.role_descriptions.teacher.roster'),
'import'=>ui_text('screens.role_descriptions.teacher.import'),
default=>$page['description']??ui_text('screens.role_descriptions.teacher.default')
}:($page['description']??ui_text('screens.role_descriptions.student'));
@endphp
<x-page-header :title="$hdrTitle" :description="$roleDescription"><x-slot:eyebrow>{{ $page['module'] }}</x-slot:eyebrow>
@if(in_array($module,['results','schedule','seats','duties','audit','analytics'])&&!($role==='admin'&&($wsState['key']??null)==='exams'&&($wsState['tab']??null)==='datesheet'))<x-export-menu :type="$module==='analytics'?'results':$module"/>
@endif</x-page-header>
@if(session('status'))<div class="callout callout-success" role="status"><x-icon name="check" :size="16"/> {{ session('status') }}</div>
@endif
@if($errors->any())<div class="callout callout-danger" role="alert"><strong>{{ ui_text('screens.copy.we_couldnt_save_these_changes') }}</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
@php
// Keep the selected examination/course while changing sections, without carrying a legacy view.
$tabContext=[];
$contextKey=($role==='admin'&&($wsDef['key']??null)==='exams')?'exam':(($role==='teacher'&&($wsDef['key']??null)==='courses')?'course_id':null);
if($contextKey&&is_scalar(request($contextKey))&&ctype_digit((string)request($contextKey))){$tabContext[$contextKey]=request($contextKey);}
@endphp
@if($wsDef&&count($wsDef['tabs'])>1)<x-tabs :items="collect($wsDef['tabs'])->map(fn($t)=>['key'=>$t['key'],'label'=>$t['label'],'href'=>'/'.$role.'/'.$wsDef['slug'].'?'.http_build_query(['tab'=>$t['key']]+$tabContext)])->all()" :active="$wsState['tab']"/>
@endif
@if($wsTab&&!empty($wsTab['pills']))<div class="page-actions" role="navigation" aria-label="{{ ui_text('screens.copy.sections') }}">@foreach($wsTab['pills'] as $pill)<a class="btn btn-secondary btn-small" href="/{{ $role }}/{{ $wsDef['slug'] }}?tab={{ $wsTab['key'] }}&view={{ $pill[1] }}" @if($page['slug']===$pill[1]) aria-current="page" @endif>{{ $pill[0] }}</a>
@endforeach</div>
@endif
@if($role==='student'&&$page['slug']==='updated-examination-schedule')<div class="callout" role="status"><strong><x-icon name="calendar" :size="18"/> {{ ui_text('screens.copy.your_examination_schedule_has_been_updated') }}</strong><p>{{ ui_text('screens.copy.review_the_latest_dates_times_and_room_assignments_below') }}</p></div>
@endif
@if(str_contains($page['slug'],'report')&&in_array($module,['results','analytics','schedule','seats','duties']))<x-report-toolbar :type="$module==='analytics'?'results':$module" :courses="$courses"/>
@endif
@if($role==='admin'&&!in_array($wsState['key']??null,['exams','assistant','dashboard','settings','rooms']))@include('ai.toolbar')
@endif
@if($role==='admin'&&($wsState['key']??null)==='exams')@include('backend.exam-hub')
@endif
@if($role==='admin'&&$page['slug']==='room-directory')@include('ai.room-layout-editor')
@endif
@if(in_array($page['slug'],['grading-scheme-list','gpa-and-cgpa-rules']))@include('backend.grading-rules')
@endif
@if(!($role==='admin'&&($wsState['key']??null)==='exams'&&((request('tab')==='datesheet'&&!request('view'))||request('exam'))&&in_array(request('tab','overview'),['overview','admit','datesheet'])&&!request('view')))@include('backend.'.$module)
@endif
@endsection
