
@if(count($analytics['courses']))<x-chart-card title="Course averages" description="Authorized calculated result percentages" :labels="array_column($analytics['courses'],'course')" :values="array_map('floatval',array_column($analytics['courses'],'average'))"/>
@endif
<div class="dashboard-kpis analytics-kpis">
    <x-kpi label="Result records" :value="$analytics['records']" note="Within your authorized scope" icon="file" tone="violet"/>
    <x-kpi label="Average percentage" :value="isset($analytics['average']) ? number_format($analytics['average'],2).'%' : '—'" note="Mean of stored percentages" icon="chart" tone="blue"/>
    <x-kpi label="Absent components" :value="$analytics['absent']" note="Recorded absences" icon="users" tone="warning"/>
</div>
<div class="dashboard-middle">
    <x-card class="">
        <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="chart"/></span><div><h2>Course performance</h2><p>Aggregates from authorized result records.</p></div></div></div>
        <x-data-table><table class="data-table"><thead><tr><th>Course</th><th>Average</th><th>Results</th><th>Failed</th></tr></thead><tbody>
        @forelse($analytics['courses'] as $course)<tr><td>{{ $course['course'] }}</td><td>{{ number_format($course['average'],2) }}%</td><td>{{ $course['records'] }}</td><td>{{ $course['failed'] }}</td></tr>
        @empty<tr><td colspan="4"><div class="empty-state">No course results available.</div></td></tr>
@endforelse
        </tbody></table></x-data-table>
    </x-card>
    <x-card class="">
        <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="award"/></span><div><h2>Result distribution</h2><p>Uses each course’s configured passing percentage.</p></div></div></div>
        @php
$total = $analytics['passed'] + $analytics['failed'];
@endphp

        @if($total)
            @php
$ratio = round($analytics['passed'] / $total * 100,1);
@endphp

            <div class="donut-body"><div class="donut" style="--pass-ratio:{{ $ratio }}%" role="img" aria-label="{{ $analytics['passed'] }} passed and {{ $analytics['failed'] }} failed"><div><strong>{{ $ratio }}%</strong><small>Pass ratio</small></div></div><div class="chart-key"><div><i></i>Passed · {{ $analytics['passed'] }}</div><div><i class="gray"></i>Failed · {{ $analytics['failed'] }}</div></div></div>
        @else<div class="empty-state">No calculated results.</div>
@endif
    </x-card>
</div>
<x-card class="">
    <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="chart"/></span><div><h2>Grade distribution</h2><p>Official grade labels from calculated results.</p></div></div></div>
    <div class="grade-distribution">
@forelse($analytics['grades'] as $grade)
        @php
$percentage = $analytics['records'] ? round($grade['records']/$analytics['records']*100,1) : 0;
@endphp

        <div class="grade-row"><strong>{{ $grade['grade'] }}</strong><div class="grade-track" role="meter" aria-label="Grade {{ $grade['grade'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentage }}"><span style="width:{{ $percentage }}%"></span></div><span>{{ $grade['records'] }} · {{ $percentage }}%</span></div>
    @empty<div class="empty-state">No grade distribution available.</div>
@endforelse</div>
</x-card>
