@if(session('ai_draft'))@php $dr=session('ai_draft'); @endphp
<x-card title="Draft announcement" description="Edit if needed, then send."><form method="POST" action="/admin/ai/send" class="form-grid card-body">@csrf
<input type="hidden" name="kind" value="{{ $dr['kind'] }}"><input type="hidden" name="exam_id" value="{{ $dr['exam_id'] }}">
<x-input name="title" label="Title" :value="$dr['title']" maxlength="120" required/><label class="field"><span>Message</span><textarea name="message" rows="4" maxlength="800" required>{{ $dr['message'] }}</textarea></label>
<x-button type="submit" icon="check" data-confirm="true" data-confirm-title="Send announcement?" data-confirm-message="This notifies every affected student or teacher." data-confirm-label="Send">Send announcement</x-button></form></x-card>
@endif
@if(session('ai_report'))@php $rep=session('ai_report'); $max=max(1,collect($rep['rows'])->max('value')); @endphp
<x-chart-card :title="$rep['plan']['title']" :description="$rep['plan']['summary']" :labels="collect($rep['rows'])->pluck('label')->all()" :values="collect($rep['rows'])->pluck('value')->all()" :type="$rep['plan']['chart']"/>
<x-card :title="$rep['plan']['title']" :description="$rep['plan']['summary']"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ ucfirst($rep['plan']['group_by']) }}</th><th>{{ str_replace('_',' ',$rep['plan']['metric']) }}</th><th>Results</th><th></th></tr></thead><tbody>
@forelse($rep['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}{{ $rep['unit'] }}</td><td>{{ $row['n'] }}</td><td style="width:40%"><progress max="{{ $max }}" value="{{ $row['value'] }}" aria-label="{{ $row['label'] }}"></progress></td></tr>
@empty<tr><td colspan="4">No results match this report in your current academic scope.</td></tr>
@endforelse</tbody></table></x-data-table><p class="muted text-xs card-body">Generated from live database records. The AI only chose the query plan.</p></x-card>
@endif
@if(session('ai_triage'))@php $tr=session('ai_triage'); @endphp
<x-card :title="$tr['kind']==='recheck'?'Rechecking triage':'Anomaly triage'" :description="$tr['summary']"><x-data-table :enhanced="false"><table class="data-table"><thead><tr><th>{{ $tr['kind']==='recheck'?'Request':'Flag' }}</th><th>{{ $tr['kind']==='recheck'?'Recommendation':'Priority' }}</th><th>Notes</th></tr></thead><tbody>
@foreach($tr['items'] as $i)<tr><td>#{{ $i['request_id']??$i['flag_id'] }}</td><td>{{ $i['recommendation']??$i['priority'] }}</td><td>{{ $i['rationale']??$i['comment'] }}</td></tr>
@endforeach</tbody></table></x-data-table><p class="muted text-xs card-body">Recommendations only — decide in the Rechecking queue or Analytics screens. Marks are never changed by AI.</p></x-card>
@endif
