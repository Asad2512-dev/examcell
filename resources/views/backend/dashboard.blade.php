
@php
$uiNotificationSlug=match($role){'admin'=>'notification-center','teacher'=>'teacher-notifications',default=>'student-notifications'};
$uiPending=$courses->filter(fn($course)=>!$course->submission||$course->submission->status!=='submitted');
$uiReturned=$courses->filter(fn($course)=>$course->submission?->status==='returned');
$uiGroups=\App\View\WorkspaceNavigation::groups($role);
$uiOpenFlagCount=$role==='admin'?app(\App\Services\AcademicContext::class)->apply(\App\Models\AnalysisFlag::query())->where('status','open')->count():0;
@endphp
@if($role==='admin')
@include('ai.command-box',['from'=>'dashboard'])
<div class="dashboard-kpis kpi-five"><x-stat-card label="Active examinations" :value="$exams->whereIn('status',['draft','approved','published'])->count()" icon="calendar" note="Draft, approved and published"/><x-stat-card label="Pending submissions" :value="$uiPending->count()" icon="edit" note="Courses awaiting submitted marks" tone="warning"/><x-stat-card label="Open review flags" :value="$uiOpenFlagCount" icon="alert" note="Recorded analysis findings" tone="warning"/><x-stat-card label="Rechecking requests" :value="app(\App\Services\AcademicContext::class)->apply(\App\Models\RecheckRequest::query())->whereIn('status',['pending','under_review'])->count()" icon="history" note="Awaiting review or decision" tone="teal"/><x-stat-card label="Published result batches" :value="$exams->filter(fn($e)=>$e->batch?->status==='published')->count()" icon="award" note="Approved and released results" tone="teal"/></div>
@elseif($role==='teacher')
<x-submission-deadlines :courses="$courses"/>
<div class="dashboard-kpis"><x-stat-card label="Examination courses" :value="$courses->count()" icon="book" note="Assigned to your account"/><x-stat-card label="Awaiting submission" :value="$uiPending->count()" icon="edit" note="Save drafts, then submit for review" tone="warning"/><x-stat-card label="Returned submissions" :value="$uiReturned->count()" icon="alert" note="Corrections requiring your attention" tone="warning"/><x-stat-card label="Notifications" :value="$unreadCount" icon="bell" note="Unread account updates" tone="teal"/></div>
@if($uiReturned->isNotEmpty())<div class="callout callout-warning"><strong>{{ $uiReturned->count() }} returned submissions need your attention.</strong><p>Review the return reason and correct your draft before resubmitting. <a class="card-link" href="/teacher/returned-marks-correction">Open returned marks →</a></p></div>
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
<x-card class="next-exam-card"><div class="card-body"><p class="eyebrow">YOUR NEXT EXAMINATION</p><div class="next-exam-content"><div><h2>{{ $uiNext->examCourse->course->name }}</h2><p>{{ $uiNext->starts_at->format('l, d M Y · H:i') }} · {{ $uiNext->roomForStudent(auth()->id()) }}</p></div><div class="page-actions"><x-button href="/student/admit-card" icon="download">Get admit card</x-button><x-button href="/student/seating-plan" variant="secondary" icon="grid">Find my seat</x-button></div></div></div></x-card>
@endif
@endif
<div class="dashboard-middle">
@if($dashboard['courseAverages']->isNotEmpty())<x-chart-card title="Course performance" description="Average percentages from results visible to your account" :labels="$dashboard['courseAverages']->pluck('label')->all()" :values="$dashboard['courseAverages']->pluck('value')->all()"/>
@else<x-card title="Course performance" description="A clearer picture of academic progress"><x-empty-state title="Your performance story starts here" description="Calculated results will appear once they are available to your account." icon="chart"/></x-card>
@endif
<x-card title="Upcoming examinations" description="Your next dates, times and rooms"><div class="attention-list">
@forelse($dashboard['upcoming']->take(4) as $entry)<div class="attention-item"><span class="exam-date"><small>{{ $entry->starts_at->format('M') }}</small><strong>{{ $entry->starts_at->format('d') }}</strong></span><div><strong>{{ $entry->examCourse->course->code }} · {{ $entry->examCourse->course->name }}</strong><p>{{ $entry->starts_at->format('D, H:i') }} · {{ $role==='student'?$entry->roomForStudent(auth()->id()):$entry->room_label }}</p></div></div>
@empty<x-empty-state title="No upcoming examinations" description="Your next published examination will appear here." icon="calendar"/>
@endforelse</div></x-card>
</div>
@if($role==='admin')
<div class="two-column"><x-card title="Attention needed" description="Recorded issues that require a human review"><div class="attention-list"><a class="attention-item" href="/admin/missing-marks-report"><span class="heading-icon tone-warning"><x-icon name="edit"/></span><div><strong>{{ $uiPending->count() }} courses awaiting marks</strong><p>Review missing, draft and returned submissions.</p></div><x-icon name="chevron" :size="14"/></a><a class="attention-item" href="/admin/ai-findings-queue"><span class="heading-icon tone-warning"><x-icon name="alert"/></span><div><strong>{{ $uiOpenFlagCount }} open analysis flags</strong><p>Inspect findings before making any changes.</p></div><x-icon name="chevron" :size="14"/></a><a class="attention-item" href="/admin/conflict-detection-results"><span class="heading-icon"><x-icon name="calendar"/></span><div><strong>Review scheduling conflicts</strong><p>Conflicts are checked during schedule validation.</p></div><x-icon name="chevron" :size="14"/></a></div></x-card>
<x-card title="Recent activity" description="Latest recorded institutional changes"><x-timeline>
@forelse(app(\App\Services\AcademicContext::class)->apply(\App\Models\AuditLog::query())->latest()->limit(4)->get() as $uiLog)<article><strong>{{ ucwords(str_replace(['.','_'],' ',$uiLog->action)) }}</strong><p>{{ class_basename($uiLog->entity_type) }} #{{ $uiLog->entity_id??'—' }}</p><time>{{ $uiLog->created_at->diffForHumans() }}</time></article>
@empty<x-empty-state title="No activity yet" description="Your audit trail appears as workflows are completed." icon="history"/>
@endforelse</x-timeline></x-card></div>
@elseif($role==='teacher')
<div class="two-column"><x-card title="My upcoming duties" description="Invigilation assignments for your account"><x-timeline>
@forelse(app(\App\Services\ScopedQueries::class)->duties(auth()->user())->where('status','assigned')->whereHas('scheduleEntry',fn($q)=>$q->where('starts_at','>=',now()))->get()->sortBy('scheduleEntry.starts_at')->take(5) as $uiDuty)<article><strong>{{ $uiDuty->scheduleEntry->examCourse->course->code }} · {{ $uiDuty->room?->name??$uiDuty->scheduleEntry->room->name }}</strong><p>{{ $uiDuty->scheduleEntry->starts_at->format('d M Y · H:i') }}</p><a class="card-link" href="/teacher/invigilation-duties">View duty →</a></article>
@empty<x-empty-state title="No upcoming duties" description="New invigilation assignments will appear here." icon="shield"/>
@endforelse</x-timeline></x-card><x-card title="Submission checklist" description="Keep your course marks moving"><div class="attention-list">
@forelse($uiPending->take(5) as $uiCourse)<a class="attention-item" href="/teacher/marks-entry?course_id={{ $uiCourse->id }}"><span class="heading-icon"><x-icon name="edit"/></span><div><strong>{{ $uiCourse->course->code }} · {{ $uiCourse->course->name }}</strong><p>{{ $uiCourse->submission?->status==='returned'?'Returned for correction':'Draft or not yet submitted' }}</p></div><x-badge :status="ucfirst($uiCourse->submission?->status??'draft')"/></a>
@empty<x-empty-state title="All assigned marks submitted" description="You’re up to date with your submission checklist." icon="check"/>
@endforelse</div><p>Deadlines are shown above; teacher-specific extensions take precedence.</p></x-card></div>
@endif
<div class="dashboard-bottom-grid"><x-card title="Recent notifications" description="Updates that matter to your work"><x-slot:actions><a class="card-link" href="/{{ $role }}/{{ $uiNotificationSlug }}">View all →</a></x-slot:actions>
@include('screens.notifications',['compact'=>true,'notifications'=>$dashboard['recentNotifications']])</x-card><x-card title="Quick actions" description="Get straight to your next task"><div class="shortcut-grid">
@foreach(array_slice($uiGroups,1,6) as $group)<a class="btn btn-secondary" href="/{{ $role }}/{{ $group['slug'] }}"><span class="shortcut-icon"><x-icon :name="$group['icon']" :size="16"/></span><span class="shortcut-label">{{ $group['label'] }}</span><x-icon name="chevron" :size="12"/></a>
@endforeach</div></x-card></div>
