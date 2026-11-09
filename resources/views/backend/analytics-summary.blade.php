
@if(count($analytics['courses']))<x-chart-card title="{{ ui_text('results.copy.course_averages') }}" description="Authorized calculated result percentages" :labels="array_column($analytics['courses'],'course')" :values="array_map('floatval',array_column($analytics['courses'],'average'))"/>
@endif
<div class="dashboard-kpis analytics-kpis">
    <x-kpi label="Result records" :value="$analytics['records']" note="Within your authorized scope" icon="file" tone="violet"/>
    <x-kpi label="Average percentage" :value="isset($analytics['average']) ? number_format($analytics['average'],2).'%' : '—'" note="Mean of stored percentages" icon="chart" tone="blue"/>
    <x-kpi label="Absent components" :value="$analytics['absent']" note="Recorded absences" icon="users" tone="warning"/>
</div>
<div class="dashboard-middle">
    <x-card class="">
        <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="chart"/></span><div><h2>{{ ui_text('results.copy.course_performance') }}</h2><p>{{ ui_text('results.copy.aggregates_from_authorized_result_records') }}</p></div></div></div>
        <x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('results.copy.course') }}</th><th>{{ ui_text('results.copy.average') }}</th><th>{{ ui_text('results.copy.results') }}</th><th>{{ ui_text('results.copy.failed') }}</th></tr></thead><tbody>
        @forelse($analytics['courses'] as $course)<tr><td>{{ $course['course'] }}</td><td>{{ number_format($course['average'],2) }}%</td><td>{{ $course['records'] }}</td><td>{{ $course['failed'] }}</td></tr>
        @empty<tr><td colspan="4"><div class="empty-state">{{ ui_text('results.copy.no_course_results_available') }}</div></td></tr>
@endforelse
        </tbody></table></x-data-table>
    </x-card>
    <x-card class="">
        <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="award"/></span><div><h2>{{ ui_text('results.copy.result_distribution') }}</h2><p>{{ ui_text('results.copy.uses_each_courses_configured_passing_percentage') }}</p></div></div></div>
        @php
$total = $analytics['passed'] + $analytics['failed'];
@endphp

        @if($total)
            @php
$ratio = round($analytics['passed'] / $total * 100,1);
@endphp

            <div class="donut-body"><div class="donut" style="--pass-ratio:{{ $ratio }}%" role="img" aria-label="{{ $analytics['passed'] }} passed and {{ $analytics['failed'] }} failed"><div><strong>{{ $ratio }}%</strong><small>{{ ui_text('results.copy.pass_ratio') }}</small></div></div><div class="chart-key"><div><i></i>Passed · {{ $analytics['passed'] }}</div><div><i class="gray"></i>Failed · {{ $analytics['failed'] }}</div></div></div>
        @else<div class="empty-state">{{ ui_text('results.copy.no_calculated_results') }}</div>
@endif
    </x-card>
</div>
<x-card class="">
    <div class="card-heading"><div class="heading-with-icon"><span class="heading-icon"><x-icon name="chart"/></span><div><h2>{{ ui_text('results.copy.grade_distribution') }}</h2><p>{{ ui_text('results.copy.official_grade_labels_from_calculated_results') }}</p></div></div></div>
    <div class="grade-distribution">
@forelse($analytics['grades'] as $grade)
        @php
$percentage = $analytics['records'] ? round($grade['records']/$analytics['records']*100,1) : 0;
@endphp

        <div class="grade-row"><strong>{{ $grade['grade'] }}</strong><div class="grade-track" role="meter" aria-label="Grade {{ $grade['grade'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentage }}"><span style="width:{{ $percentage }}%"></span></div><span>{{ $grade['records'] }} · {{ $percentage }}%</span></div>
    @empty<div class="empty-state">{{ ui_text('results.copy.no_grade_distribution_available') }}</div>
@endforelse</div>
</x-card>
