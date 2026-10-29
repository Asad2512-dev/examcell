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
$presentationGroups=\App\View\WorkspaceNavigation::groups($role);
@endphp

@php
$section=collect($presentationGroups)->first(fn($g)=>\App\View\WorkspaceNavigation::active($g,$page['slug'],$resource));
@endphp

@if($section&&count($section['children'])>1&&count($section['children'])<=8)<x-tabs :items="collect($section['children'])->map(fn($s)=>['key'=>$s['slug'],'label'=>$s['title'],'href'=>'/'.$role.'/'.$s['slug']])->all()" :active="$page['slug']"/>
@endif
@if($role==='student'&&$page['slug']==='updated-examination-schedule')<div class="callout" role="status"><strong><x-icon name="calendar" :size="18"/> Your examination schedule has been updated.</strong><p>Review the latest dates, times and room assignments below before your next examination.</p></div>
@endif
@if(str_contains($page['slug'],'report')&&in_array($module,['results','analytics','schedule','seats','duties']))<x-report-toolbar :type="$module==='analytics'?'results':$module" :courses="$courses"/>
@endif
@include('backend.'.$module)
@endsection
