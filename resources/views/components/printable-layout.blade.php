@props(['title'=>'Examination document','reference'=>null])
<article class="printable-document"><header class="print-header"><x-logo :size="48"/><div><strong>{{ ui_text('common.copy.examcell') }}</strong><p>{{ ui_text('common.copy.examination_cell_academic_records') }}</p></div>
@if($reference)<span class="print-reference">{{ $reference }}</span>
@endif</header><h1>{{ $title }}</h1>{{ $slot }}<footer class="print-footer">Issued by the Examination Cell · {{ now('Asia/Karachi')->format('d M Y') }}</footer></article>
