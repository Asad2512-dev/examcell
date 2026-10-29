@props(['title'=>'Examination document','reference'=>null])
<article class="printable-document"><header class="print-header"><x-logo :size="48"/><div><strong>ExamCell</strong><p>Examination Cell · Academic records</p></div>
@if($reference)<span class="print-reference">{{ $reference }}</span>
@endif</header><h1>{{ $title }}</h1>{{ $slot }}<footer class="print-footer">Issued by the Examination Cell · {{ now('Asia/Karachi')->format('d M Y') }}</footer></article>
