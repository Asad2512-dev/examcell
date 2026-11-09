
@php
$uiNotificationSlug=match($role){'admin'=>'notification-center','teacher'=>'teacher-notifications',default=>'student-notifications'};
$uiPending=$courses->filter(fn($course)=>!$course->submission||$course->submission->status!=='submitted');
$uiReturned=$courses->filter(fn($course)=>$course->submission?->status==='returned');

$uiOpenFlagCount=$role==='admin'?app(\App\Services\AcademicContext::class)->apply(\App\Models\AnalysisFlag::query())->where('status','open')->count():0;
@endphp
<x-workspace-actions :role="$role"/>
@if($role==='admin')
<div class="admin-start-banner"><div><span class="eyebrow">{{ ui_text('dashboard.copy.start_here') }}</span><h2>{{ ui_text('dashboard.copy.set_up_your_academic_session') }}</h2><p>{{ ui_text('dashboard.copy.session_departments_courses_teachers_students') }}</p></div><x-button href="/admin/academic-setup" icon="chevron">{{ ui_text('dashboard.copy.open_academic_setup') }}</x-button></div>

<div class="dashboard-kpis kpi-five"><x-stat-card label="Active examinations" :value="$exams->whereIn('status',['draft','approved','published'])->count()" icon="calendar" note="Draft, approved and published"/><x-stat-card label="Pending submissions" :value="$uiPending->count()" icon="edit" note="Courses awaiting submitted marks" tone="warning"/><x-stat-card label="Open review flags" :value="$uiOpenFlagCount" icon="alert" note="Recorded analysis findings" tone="warning"/><x-stat-card label="Rechecking requests" :value="app(\App\Services\AcademicContext::class)->apply(\App\Models\RecheckRequest::query())->whereIn('status',['pending','under_review'])->count()" icon="history" note="Awaiting review or decision" tone="teal"/><x-stat-card label="Published result batches" :value="$exams->filter(fn($e)=>$e->batch?->status==='published')->count()" icon="award" note="Approved and released results" tone="teal"/></div>
@elseif($role==='teacher')

<div class="dashboard-kpis"><x-stat-card label="Examination courses" :value="$courses->count()" icon="book" note="Assigned to your account"/><x-stat-card label="Awaiting submission" :value="$uiPending->count()" icon="edit" note="Save drafts, then submit for review" tone="warning"/><x-stat-card label="Returned submissions" :value="$uiReturned->count()" icon="alert" note="Corrections requiring your attention" tone="warning"/><x-stat-card label="Notifications" :value="$unreadCount" icon="bell" note="Unread account updates" tone="teal"/></div>
<x-submission-deadlines :courses="$courses" compact/>
@if($uiReturned->isNotEmpty())<div class="callout callout-warning"><strong>{{ $uiReturned->count() }} returned submissions need your attention.</strong><p>{{ ui_text('dashboard.copy.review_the_return_reason_and_correct_your_draft_before_r') }} <a class="card-link" href="/teacher/courses?tab=marks&amp;view=returned-marks-correction&amp;course_id={{ $uiReturned->first()->id }}">{{ ui_text('dashboard.copy.open_returned_marks') }}</a></p></div>
@endif
@else
@php
$uiStudentResults=app(\App\Services\ScopedQueries::class)->results(auth()->user())->get();
@endphp

<div class="dashboard-kpis"><x-stat-card label="Enrolled courses" :value="$metrics['Courses']" icon="book" note="Your academic enrollments"/><x-stat-card label="Scheduled examinations" :value="$metrics['Scheduled exams']" icon="calendar" note="Published schedules for your courses" tone="teal"/><x-stat-card label="CGPA" :value="app(\App\Services\GradingService::class)->academicGpa($uiStudentResults)??'—'" icon="award" note="Published results only"/><x-stat-card label="Notifications" :value="$unreadCount" icon="bell" note="Unread examination updates" tone="warning"/></div>
@if($dashboard['upcoming']->first())
@php
$uiNext=$dashboard['upcoming']->first();
@endphp
<x-card class="next-exam-card"><div class="card-body"><p class="eyebrow">{{ ui_text('dashboard.copy.your_next_examination') }}</p><div class="next-exam-content"><div><h2>{{ $uiNext->examCourse->course->name }}</h2><p>{{ $uiNext->starts_at->format('l, d M Y · H:i') }} · {{ $uiNext->roomForStudent(auth()->id()) }}</p></div><div class="page-actions"><x-button href="/student/admit-card" icon="download">{{ ui_text('dashboard.copy.get_admit_card') }}</x-button><x-button href="/student/seating-plan" variant="secondary" icon="grid">{{ ui_text('dashboard.copy.find_my_seat') }}</x-button></div></div></div></x-card>
@endif
@endif
<div class="dashboard-focus">
<div class="dashboard-upcoming">
<x-card :title="ui_text('dashboard.upcoming.heading')" :description="ui_text('dashboard.upcoming.description')"><div class="attention-list">
@forelse($dashboard['upcoming']->take(4) as $entry)<div class="attention-item"><span class="exam-date"><small>{{ $entry->starts_at->format('M') }}</small><strong>{{ $entry->starts_at->format('d') }}</strong></span><div><strong>{{ $entry->examCourse->course->code }} · {{ $entry->examCourse->course->name }}</strong><p>{{ $entry->starts_at->format('D, H:i') }} · {{ $role==='student'?$entry->roomForStudent(auth()->id()):$entry->room_label }}</p></div></div>
@empty<x-empty-state title="{{ ui_text('dashboard.copy.no_upcoming_examinations') }}" description="Your next published examination will appear here." icon="calendar"/>
@endforelse</div></x-card>
</div>
@if($role==='admin')
<div class="dashboard-attention"><x-card title="{{ ui_text('dashboard.copy.attention_needed') }}" description="Recorded issues that require a human review"><div class="attention-list"><a class="attention-item" href="/admin/missing-marks-report"><span class="heading-icon tone-warning"><x-icon name="edit"/></span><div><strong>{{ $uiPending->count() }} courses awaiting marks</strong><p>{{ ui_text('dashboard.copy.review_missing_draft_and_returned_submissions') }}</p></div><x-icon name="chevron" :size="14"/></a><a class="attention-item" href="/admin/ai-findings-queue"><span class="heading-icon tone-warning"><x-icon name="alert"/></span><div><strong>{{ $uiOpenFlagCount }} open analysis flags</strong><p>{{ ui_text('dashboard.copy.inspect_findings_before_making_any_changes') }}</p></div><x-icon name="chevron" :size="14"/></a><a class="attention-item" href="/admin/conflict-detection-results"><span class="heading-icon"><x-icon name="calendar"/></span><div><strong>{{ ui_text('dashboard.copy.review_scheduling_conflicts') }}</strong><p>{{ ui_text('dashboard.copy.conflicts_are_checked_during_schedule_validation') }}</p></div><x-icon name="chevron" :size="14"/></a></div></x-card>
</div>
@elseif($role==='teacher')
<div class="dashboard-duties"><x-card title="{{ ui_text('dashboard.copy.my_upcoming_duties') }}" description="Invigilation assignments for your account"><x-timeline>
@forelse(app(\App\Services\ScopedQueries::class)->duties(auth()->user())->where('status','assigned')->whereHas('scheduleEntry',fn($q)=>$q->where('starts_at','>=',now()))->get()->sortBy('scheduleEntry.starts_at')->take(5) as $uiDuty)<article><strong>{{ $uiDuty->scheduleEntry->examCourse->course->code }} · {{ $uiDuty->room?->name??$uiDuty->scheduleEntry->room->name }}</strong><p>{{ $uiDuty->scheduleEntry->starts_at->format('d M Y · H:i') }}</p><a class="card-link" href="/teacher/invigilation-duties">{{ ui_text('dashboard.copy.view_duty') }}</a></article>
@empty<x-empty-state title="{{ ui_text('dashboard.copy.no_upcoming_duties') }}" description="New invigilation assignments will appear here." icon="shield"/>
@endforelse</x-timeline></x-card></div>
@endif
</div>
<x-card title="{{ ui_text('dashboard.copy.recent_notifications') }}" description="Your latest account updates"><x-slot:actions><a class="card-link" href="/{{ $role }}/{{ $uiNotificationSlug }}">{{ ui_text('dashboard.copy.view_all') }}</a></x-slot:actions>
@include('screens.notifications',['compact'=>true,'notifications'=>$dashboard['recentNotifications']->take(3)])</x-card>
<x-disclosure title="{{ ui_text('dashboard.copy.performance_and_activity') }}" description="Open charts and additional information when you need them.">
<div class="dashboard-secondary">
@if($dashboard['courseAverages']->isNotEmpty())<x-chart-card title="{{ ui_text('dashboard.copy.course_performance') }}" description="Average percentages from results visible to your account" :labels="$dashboard['courseAverages']->pluck('label')->all()" :values="$dashboard['courseAverages']->pluck('value')->all()"/>
@else<x-card title="{{ ui_text('dashboard.copy.course_performance') }}" description="A clearer picture of academic progress"><x-empty-state title="{{ ui_text('dashboard.copy.your_performance_story_starts_here') }}" description="Calculated results will appear once they are available to your account." icon="chart"/></x-card>
@endif

@if($role==='admin')
<x-card title="{{ ui_text('dashboard.copy.recent_activity') }}" description="Latest recorded institutional changes"><x-timeline>
@forelse(app(\App\Services\AcademicContext::class)->apply(\App\Models\AuditLog::query())->latest()->limit(4)->get() as $uiLog)<article><strong>{{ ucwords(str_replace(['.','_'],' ',$uiLog->action)) }}</strong><p>{{ class_basename($uiLog->entity_type) }} #{{ $uiLog->entity_id??'—' }}</p><time>{{ $uiLog->created_at->diffForHumans() }}</time></article>
@empty<x-empty-state title="{{ ui_text('dashboard.copy.no_activity_yet') }}" description="Your audit trail appears as workflows are completed." icon="history"/>
@endforelse</x-timeline></x-card>
@else
<x-card title="{{ ui_text('dashboard.copy.more_tools') }}" description="Available from your sidebar"><div class="card-body"><p>{{ ui_text('dashboard.copy.use_ai_assistant_for_help_with_your_records_you_can_find') }}</p></div></x-card>
@endif
</div>
</x-disclosure>
@if($role==='admin')
<x-disclosure title="{{ ui_text('dashboard.copy.ask_ai_for_help') }}" description="Optional assistance. Proposals still require your review." :open="(bool)session('ai_reply') || (old('from')==='dashboard' && $errors->any())">
@include('ai.command-box',['from'=>'dashboard'])
</x-disclosure>
@endif
