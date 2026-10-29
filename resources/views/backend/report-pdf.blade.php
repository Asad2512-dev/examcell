<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{{ $title }}</title>
@include('components.pdf-style')</head><body><div class="pdf-brand"><x-logo :size="40" :pdf="true"/><span class="pdf-wordmark">ExamCell</span><p class="pdf-eyebrow">EXAMINATION CELL · ACADEMIC REPORT</p></div><h1>{{ $title }}</h1><p class="pdf-reference">Generated {{ now('Asia/Karachi')->format('d M Y H:i') }} (Asia/Karachi) · Authorized account: {{ auth()->user()->name }}</p>
@if($rows->isNotEmpty())<table><thead><tr>
@foreach(array_keys($rows->first()) as $h)<th>{{ ucwords(str_replace('_',' ',$h)) }}</th>
@endforeach</tr></thead><tbody>
@foreach($rows as $row)<tr>
@foreach($row as $value)<td>{{ $value }}</td>
@endforeach</tr>
@endforeach</tbody></table>
@else<div class="pdf-summary">No records match this report.</div>
@endif<div class="pdf-footer">ExamCell · Examination Management · {{ $rows->count() }} records<br>Generated from the records authorized for this account.</div></body></html>
