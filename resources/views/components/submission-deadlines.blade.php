@props(['courses','compact'=>false])
@if($compact)
@php
$pendingDeadlines=$courses->filter(fn($course)=>$course->submission?->status!=='submitted')->sortBy(fn($course)=>$course->submissionDeadlineFor(auth()->user())?->timestamp??PHP_INT_MAX);
@endphp
<x-card title="{{ ui_text('common.copy.marks_submission_deadlines') }}" description="Unsubmitted courses, nearest deadline first.">
<x-slot:actions><a class="card-link" href="/teacher/courses?tab=submissions">{{ ui_text('common.copy.all_submissions') }}</a></x-slot:actions>
<div class="attention-list">
@forelse($pendingDeadlines->take(3) as $deadlineCourse)
<a class="attention-item deadline-task" href="/teacher/courses?tab=marks&amp;course_id={{ $deadlineCourse->id }}">
<div><strong>{{ $deadlineCourse->course->code }} · {{ $deadlineCourse->course->name }}</strong><p>{{ $deadlineCourse->examination->name }} · {{ $deadlineCourse->section?->name }}</p><x-badge :status="ucfirst($deadlineCourse->submission?->status??'draft')"/></div>
<x-submission-countdown :deadline="$deadlineCourse->submissionDeadlineFor(auth()->user())"/>
<x-icon name="chevron" :size="16"/>
</a>
@empty<x-empty-state title="{{ ui_text('common.copy.you_re_up_to_date') }}" description="No course marks are awaiting submission." icon="check"/>
@endforelse
</div>
@if($pendingDeadlines->count()>3)<p class="deadline-more">{{ $pendingDeadlines->count()-3 }} more courses awaiting submission. <a class="card-link" href="/teacher/courses?tab=submissions">{{ ui_text('common.copy.view_all') }}</a></p>@endif
</x-card>
@else
<x-card title="{{ ui_text('common.copy.marks_submission_deadlines') }}" description="Your course deadlines, including any teacher-specific extensions."><x-data-table><table class="data-table"><thead><tr><th>{{ ui_text('common.copy.course') }}</th><th>{{ ui_text('common.copy.examination') }}</th><th>{{ ui_text('common.copy.submission') }}</th><th>{{ ui_text('common.copy.time_remaining') }}</th><th>{{ ui_text('common.copy.action') }}</th></tr></thead><tbody>
@forelse($courses as $deadlineCourse)<tr><td>{{ $deadlineCourse->course->code }} · {{ $deadlineCourse->course->name }}</td><td>{{ $deadlineCourse->examination->name }}</td><td><x-badge :status="ucfirst($deadlineCourse->submission?->status??'draft')"/></td><td><x-submission-countdown :deadline="$deadlineCourse->submissionDeadlineFor(auth()->user())" :submitted="$deadlineCourse->submission?->status==='submitted'"/></td><td><x-button variant="secondary" href="/teacher/marks-entry?course_id={{ $deadlineCourse->id }}">{{ ui_text('common.copy.open_marks') }}</x-button></td></tr>
@empty<tr><td colspan="5"><x-empty-state title="{{ ui_text('common.copy.no_assigned_examination_courses') }}" description="Your submission deadlines will appear when courses are assigned." icon="calendar"/></td></tr>
@endforelse</tbody></table></x-data-table></x-card>

@endif
