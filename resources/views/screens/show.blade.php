@extends('layouts.app')
@section('content')
<div class="print-only print-header"><x-logo :size="44"/><div><strong>ExamCell</strong><p>Examination Cell · {{ $page['title'] }}</p></div><span class="print-reference">{{ now('Asia/Karachi')->format('d M Y') }}</span></div>
<x-page-header :title="$page['title']" :description="$page['description']??'Your examination records, clearly organized. All changes follow the existing approval process.'"><x-slot:eyebrow>{{ $page['module'] }}</x-slot:eyebrow>
@if(in_array($module,['results','schedule','seats','duties','audit','analytics']))<x-button variant="secondary" href="/reports/{{ $module==='analytics'?'results':$module }}?format=csv" icon="download">Export CSV</x-button><x-button variant="secondary" href="/reports/{{ $module==='analytics'?'results':$module }}?format=pdf" icon="file">Download PDF</x-button><x-button variant="secondary" data-print icon="print">Print</x-button>
@endif</x-page-header>
@if(session('status'))<div class="callout callout-success" role="status"><x-icon name="check" :size="16"/> {{ session('status') }}</div>
@endif
@if($errors->any())<div class="callout callout-danger" role="alert"><strong>We couldn’t save these changes.</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
@php
$wsState=request()->attributes->get('ws');
$wsDef=$wsState?collect(\App\Support\Workspaces::definition($role))->firstWhere('key',$wsState['key']):null;
$wsTab=$wsDef?collect($wsDef['tabs'])->firstWhere('key',$wsState['tab']):null;
@endphp
@if($wsDef&&count($wsDef['tabs'])>1)<x-tabs :items="collect($wsDef['tabs'])->map(fn($t)=>['key'=>$t['key'],'label'=>$t['label'],'href'=>'/'.$role.'/'.$wsDef['slug'].'?tab='.$t['key']])->all()" :active="$wsState['tab']"/>
@endif
@if($wsTab&&!empty($wsTab['pills']))<div class="page-actions" role="navigation" aria-label="Sections">@foreach($wsTab['pills'] as $pill)<a class="btn btn-secondary btn-small" href="/{{ $role }}/{{ $wsDef['slug'] }}?tab={{ $wsTab['key'] }}&view={{ $pill[1] }}" @if($page['slug']===$pill[1]) aria-current="page" @endif>{{ $pill[0] }}</a>
@endforeach</div>
@endif
@if($role==='student'&&$page['slug']==='updated-examination-schedule')<div class="callout" role="status"><strong><x-icon name="calendar" :size="18"/> Your examination schedule has been updated.</strong><p>Review the latest dates, times and room assignments below before your next examination.</p></div>
@endif
@if(str_contains($page['slug'],'report')&&in_array($module,['results','analytics','schedule','seats','duties']))<x-report-toolbar :type="$module==='analytics'?'results':$module" :courses="$courses"/>
@endif
@include('backend.'.$module)
@endsection
