@php
    $ehTab = request('tab', 'overview');
    $ehExam = request('exam') ? $exams->firstWhere('id', (int) request('exam')) : null;
    $ehConfigured = app(\App\Services\AI\AiClient::class)->configured();
@endphp
@if (!$ehExam)
    <div class="callout">
        <strong>{{ $ehTab === 'datesheet' ? 'Make a date sheet in three steps' : 'Exams in three steps' }}</strong>
        <p>1. Create the examination &nbsp;→&nbsp; 2. Make its date sheet (rooms are picked for you) &nbsp;→&nbsp; 3.
            Seating and invigilators, then publish.</p>
        <p><x-button href="/admin/exams/new" icon="plus">New examination</x-button></p>
    </div>
    <x-card title="{{ ui_text('examinations.copy.examinations') }}"
        description="Open an examination to manage its date sheet and admit cards."><x-data-table :enhanced="false">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ ui_text('examinations.copy.examination') }}</th>
                        <th>{{ ui_text('examinations.copy.type') }}</th>
                        <th>{{ ui_text('examinations.copy.status') }}</th>
                        <th>{{ ui_text('examinations.copy.papers') }}</th>
                        <th>{{ ui_text('examinations.copy.scheduled') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $e)
                        <tr>
                            <td>{{ $e->name }}</td>
                            <td>{{ ucfirst($e->type) }}</td>
                            <td><x-badge :status="ucfirst($e->status)" /></td>
                            <td>{{ $e->courses->count() }}</td>
                            <td>{{ $e->courses->filter(fn($c) => $c->schedule)->count() }}</td>
                            <td>
                                @if ($ehTab !== 'datesheet')
                                    <a class="btn btn-secondary btn-small"
                                        href="/admin/exams?exam={{ $e->id }}&tab={{ in_array($ehTab, ['datesheet', 'admit']) ? $ehTab : 'overview' }}">{{ ui_text('examinations.copy.open') }}</a>
                                @endif <a class="btn btn-primary btn-small"
                                    href="/admin/exams?exam={{ $e->id }}&amp;tab=datesheet">{{ $e->courses->contains(fn($c) => (bool) $c->schedule) ? 'View date sheet' : 'Create date sheet' }}</a>
                            </td>
                        </tr>
                    @empty<tr>
                            <td colspan="6">
                                {{ ui_text('examinations.copy.no_examinations_in_this_academic_scope_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-data-table></x-card>
@else
    @php
        $ehScheduler = app(\App\Services\SchedulingService::class);
        $ehEvents = [];
        $ehClashes = [];
        foreach ($ehExam->courses as $c) {
            if ($c->schedule) {
                $errs = $ehScheduler->entryConflicts($c->schedule->loadMissing('examCourse.course'));
                foreach ($errs as $er) {
                    $ehClashes[] = $c->course->code . ': ' . $er;
                }
                $ehEvents[] = [
                    'id' => $c->schedule->id,
                    'title' => $c->course->code,
                    'detail' =>
                        $c->section->name .
                        ' · ' .
                        $c->schedule->starts_at->format('H:i') .
                        ' · ' .
                        $c->schedule->room_label,
                    'date' => $c->schedule->starts_at->format('Y-m-d H:i'),
                    'conflict' => (bool) $errs,
                ];
            }
        }
        $ehUnscheduled = $ehExam->courses->filter(fn($c) => !$c->schedule)->count();
        $ehLineage = \App\Models\Examination::where('id', $ehExam->id)
            ->orWhere('id', $ehExam->supersedes_id)
            ->orWhere('supersedes_id', $ehExam->id)
            ->orderBy('id')
            ->get();
    @endphp
    <x-card :title="$ehExam->name" :description="ucfirst($ehExam->type) .
        ' · ' .
        $ehExam->courses->count() .
        ' papers · ' .
        $ehUnscheduled .
        ' unscheduled'"><x-slot:actions><x-badge :status="ucfirst($ehExam->status)" /></x-slot:actions>
        <div class="card-body"><a class="card-link"
                href="/admin/exams?tab={{ $ehTab }}">{{ ui_text('examinations.copy.all_examinations') }}</a>
            @if ($ehExam->status === 'draft')
                &nbsp;·&nbsp; <a class="card-link" href="/admin/exams/new?exam={{ $ehExam->id }}">Add more papers</a>
            @endif
        </div>
    </x-card>
    @include('ai.run-panel')
    @if (in_array($ehExam->status, ['draft', 'approved'], true))
        <x-readiness :exam="$ehExam" />
    @endif
    @if ($ehTab === 'overview')
        <x-card title="{{ ui_text('examinations.copy.overview') }}">
            <div class="card-body">
                <p>Admit cards {{ $ehExam->admit_enabled ? 'enabled' : 'disabled' }} · {{ $ehExam->courses->count() }}
                    papers · {{ count($ehClashes) }} clash(es).</p>
                <div class="page-actions">
                    @if ($ehExam->status === 'draft')
                        <form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input
                                type="hidden" name="status" value="approved"><x-button type="submit" icon="check"
                                data-confirm="true"
                                data-confirm-title="{{ ui_text('examinations.copy.approve_this_examination') }}"
                                data-confirm-message="{{ ui_text('examinations.copy.every_paper_must_be_scheduled_seated_covered_and_clash_f') }}"
                                data-confirm-label="{{ ui_text('examinations.copy.approve') }}">{{ ui_text('examinations.copy.approve') }}</x-button>
                        </form>
                    @elseif($ehExam->status === 'approved')
                        <form method="POST" action="/workflow/examination/{{ $ehExam->id }}/state">@csrf<input
                                type="hidden" name="status" value="published"><x-button type="submit" icon="check"
                                data-confirm="true"
                                data-confirm-title="{{ ui_text('examinations.copy.publish_this_examination') }}"
                                data-confirm-message="{{ app(\App\Services\ExamCycle::class)->audience($ehExam) }}. {{ ui_text('examinations.copy.students_and_teachers_are_notified') }}"
                                data-confirm-label="{{ ui_text('examinations.copy.publish') }}">{{ ui_text('examinations.copy.publish') }}</x-button>
                        </form>
                    @elseif($ehExam->status === 'published')
                        <form method="POST" action="/workflow/examination/{{ $ehExam->id }}/revision">@csrf<x-button
                                type="submit"
                                variant="secondary">{{ ui_text('examinations.copy.create_revision') }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>
    @elseif($ehTab === 'datesheet')
        @include('backend.date-sheet')
    @elseif($ehTab === 'admit')
        @php $ehRun=\App\Models\AdmitGenerationRun::where('examination_id',$ehExam->id)->latest()->first(); @endphp
        <x-card title="{{ ui_text('examinations.copy.admit_cards') }}"
            description="Generated for every eligible student once the examination is published.">
            <div class="card-body">
                @if ($ehRun)
                    <x-job-progress :status="$ehRun->status" :count="$ehRun->generated" :failure="$ehRun->failure ?? null" />
                @endif
                <form method="POST" action="/workflow/admit/{{ $ehExam->id }}" class="page-actions">@csrf<input
                        type="hidden" name="bulk" value="1"><x-button type="submit" icon="file"
                        :disabled="$ehExam->status !== 'published' || !$ehExam->admit_enabled">{{ ui_text('examinations.copy.generate_all_admit_cards') }}</x-button></form>
                @if ($ehExam->status !== 'published')
                    <p class="muted text-xs">{{ ui_text('examinations.copy.publish_the_examination_first') }}</p>
                @endif
            </div>
        </x-card>
    @endif
@endif
