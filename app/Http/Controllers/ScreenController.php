<?php

namespace App\Http\Controllers;

use App\Models as M;
use App\Services as S;
use App\Support\Resources;
use App\Support\ScreenBindings;
use App\Support\ScreenCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class ScreenController
{
    public function show(Request $r, string $role, ?string $screen = null)
    {
        abort_unless($r->user()->role === $role, 403);
        $screen ??= $role.'-dashboard';
        $catalog = new ScreenCatalog;
        $page = $catalog->find($role, $screen);
        if (! $page && in_array($screen, ['records', 'duty-replacements', 'exam-schedule', 'my-duties', 'recheck-requests', 'forecast', 'backup-status'])) {
            $page = ['slug' => $screen, 'title' => ucwords(str_replace('-', ' ', $screen)), 'module' => 'Workspace', 'kind' => 'table', 'role' => $role, 'description' => 'Authoritative academic records.'];
        }abort_unless($page, 404);
        if ($page['kind'] === 'auth') {
            return redirect('/sign-in');
        }
        $module = ScreenBindings::module($page);
        if ($screen === 'backup-status') {
            $module = 'backups';
            abort_unless($role === 'admin', 403);
        }if ($screen === 'forecast') {
            $module = 'analytics';
        }
        if ($role === 'teacher' && (str_contains($screen, 'students') || str_contains($screen, 'roster'))) {
            $module = 'roster';
        }
        $u = $r->user();
        $scope = app(S\ScopedQueries::class);
        $context = app(S\AcademicContext::class);
        $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|string|max:40', 'sort' => 'nullable|in:id,name,created_at', 'direction' => 'nullable|in:asc,desc', 'course_id' => 'nullable|integer', 'id' => 'nullable|integer', 'resource' => 'nullable|string', 'conversation_id' => 'nullable|integer']);
        $courses = $scope->courses($u)->with(['course', 'components', 'submission', 'examination', 'submissionDeadlines' => fn ($q) => $q->when($role === 'teacher', fn ($t) => $t->where('teacher_id', $u->id))])->orderBy('id')->get();
        $selected = $r->filled('course_id') ? $courses->firstWhere('id', $r->integer('course_id')) : $courses->first();
        if ($r->filled('course_id') && ! $selected) {
            abort(404);
        }
        $resource = $r->input('resource', ScreenBindings::resource($page));
        $record = null;
        $records = null;
        $students = collect();
        $marks = collect();
        $conversation = null;
        $query = match ($module) {
            'backups' => M\BackupRun::query(),
            'academic' => (Resources::get($resource)[0])::query(),'schedule' => $scope->schedules($u),'seats' => $scope->seats($u),'duties' => $u->role === 'student' ? abort(403) : $scope->duties($u),'results','corrections','analytics' => $scope->results($u),'marks' => $scope->courses($u)->with('course', 'submission'),'rechecks' => M\RecheckRequest::with('student', 'result.examCourse.course')->when($role === 'student', fn ($q) => $q->where('student_id', $u->id)),'admit' => M\AdmitCard::with('student', 'examination')->when($role !== 'admin', fn ($q) => $q->where('student_id', $u->id)->whereHas('examination', fn ($e) => $e->where('status', 'published'))),'audit' => $role === 'admin' ? M\AuditLog::query() : M\AuditLog::where('actor_id', $u->id),'notifications' => $u->notifications(),'roster' => $scope->roster($u),'courses' => $role === 'student' ? $u->enrollments()->with('course', 'section') : M\TeacherAssignment::with('teacher', 'course', 'section')->where('teacher_id', $u->id),default => null
        };
        if ($module === 'academic') {
            abort_unless($role === 'admin', 403);
        }
        if ($query) {
            $query = $context->apply($query, $u);
            $table = $query->getModel()->getTable();
            if ($r->filled('q')) {
                $q = $r->input('q');
                $fields = array_intersect(['name', 'code', 'email', 'roll_number', 'action', 'type'], Schema::getColumnListing($table));
                if ($fields) {
                    $query->where(function ($x) use ($fields, $q) {
                        foreach ($fields as $field) {
                            $x->orWhere($field, 'like', '%'.$q.'%');
                        }
                    });
                } elseif ($module === 'results' || $module === 'analytics' || $module === 'corrections') {
                    $query->whereHas('examCourse.course', fn ($c) => $c->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%'));
                }
            }
            if ($r->filled('status') && Schema::hasColumn($table, 'status')) {
                $query->where('status', $r->input('status'));
            }
            $sort = $r->input('sort', 'id');
            if (! Schema::hasColumn($table, $sort)) {
                $sort = 'id';
            }
            $records = $query->orderBy($table.'.'.$sort, $r->input('direction', 'desc'))->paginate(20)->withQueryString();
            if ($module === 'academic' && $r->filled('id')) {
                $record = $context->apply((Resources::get($resource)[0])::query(), $u)->findOrFail($r->integer('id'));
            }
        }
        if ($module === 'marks' && $selected) {
            Gate::authorize('view', $selected);
            $students = $selected->eligible()->with('student.profile')->get();
            $marks = M\Mark::whereIn('assessment_component_id', $selected->components->pluck('id'))->get()->keyBy(fn ($m) => $m->student_id.':'.$m->assessment_component_id);
        }
        if ($module === 'chat' && $r->filled('conversation_id')) {
            $conversation = M\Conversation::findOrFail($r->integer('conversation_id'));
            Gate::authorize('view', $conversation);
            $conversation->load('messages');
        }
        $allResults = $module === 'results' && $role === 'student' ? $scope->results($u)->with('examCourse.examination', 'examCourse.section')->get() : collect();
        $metrics = ['Courses' => $role === 'student' ? $u->enrollments()->count() : $courses->count(), 'Scheduled exams' => $scope->schedules($u)->count(), 'Result records' => $scope->results($u)->count(), 'Unread notifications' => $u->unreadNotifications()->count()];

        $dashboardResults = $scope->results($u)->with('examCourse.course')->get();
        $dashboardSchedule = $scope->schedules($u)->with('examCourse.course', 'room')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(6)->get();
        $dashboard = [
            'courseAverages' => $dashboardResults->groupBy(fn ($result) => $result->examCourse?->course?->code ?: 'Course')->map(fn ($group, $code) => ['label' => $code, 'value' => round((float) $group->avg('percentage'), 1)])->values()->take(6),
            'upcoming' => $dashboardSchedule,
            'recentNotifications' => $u->notifications()->latest()->limit(5)->get(),
            'passCount' => $dashboardResults->where('passed', true)->count(),
            'failCount' => $dashboardResults->where('passed', false)->count(),
        ];

        $analytics = $module === 'analytics' ? app(S\AnalyticsService::class)->aggregates($u) : [];

        return view('screens.show', compact('page', 'role', 'module', 'resource', 'record', 'records', 'courses', 'selected', 'students', 'marks', 'conversation', 'metrics', 'allResults', 'analytics', 'dashboard') + ['navigation' => $catalog->navigation($role), 'screens' => $catalog->forRole($role), 'userName' => $u->name, 'initials' => collect(explode(' ', $u->name))->map(fn ($n) => mb_substr($n, 0, 1))->take(2)->implode(''), 'notifications' => $u->notifications()->latest()->limit(10)->get(), 'unreadCount' => $u->unreadNotifications()->count(), 'exams' => $role === 'admin' ? $context->apply(M\Examination::query(), $u)->with(['batch', 'courses' => fn ($q) => $context->apply($q, $u)->with('schedule')])->latest()->get() : collect(), 'replacements' => $context->apply(M\DutyReplacement::query(), $u)->with('duty.teacher', 'candidate', 'request')->when($role === 'teacher', fn ($q) => $q->whereHas('duty', fn ($d) => $d->where('teacher_id', $u->id)))->latest()->limit(50)->get(), 'dutyRequests' => $context->apply(M\DutyRequest::query(), $u)->with('duty.scheduleEntry.examCourse.course', 'teacher', 'proposal.candidate')->when($role === 'teacher', fn ($q) => $q->where('teacher_id', $u->id))->latest()->paginate(20, ['*'], 'requests_page')->withQueryString(), 'corrections' => $role === 'admin' ? $context->apply(M\ResultCorrection::query(), $u)->with('mark.student')->latest()->limit(50)->get() : collect(), 'flags' => $role === 'admin' ? $context->apply(M\AnalysisFlag::query(), $u)->latest()->limit(100)->get() : collect(), 'admitStudents' => $role === 'admin' ? $context->apply(M\StudentProfile::query(), $u)->with('user')->where('eligible', true)->get() : collect()]);
    }
}
