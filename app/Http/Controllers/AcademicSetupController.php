<?php

namespace App\Http\Controllers;

use App\Models as M;
use App\Services\AcademicContext;
use App\Services\AcademicService;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Guided entry points over existing academic records; no new academic ownership model. */
class AcademicSetupController
{
    public const STEPS = ['session' => 'Session', 'departments' => 'Departments', 'courses' => 'Courses', 'teachers' => 'Teachers', 'students' => 'Students', 'review' => 'Review'];

    public function show(Request $request)
    {
        $step = $request->validate(['step' => ['nullable', Rule::in(array_keys(self::STEPS))]])['step'] ?? 'session';
        $request->attributes->set('ws', ['key' => 'setup', 'tab' => 'main', 'primary' => 'academic-setup']);
        $context = app(AcademicContext::class)->current();
        $session = M\AcademicSession::find($context['session_id']);
        // Setup always covers the complete session, even when other admin views filter a semester.
        $semesters = M\Semester::where('academic_session_id', $session?->id)->orderBy('number')->get();
        $sections = M\Section::with('department', 'semester')->whereIn('semester_id', $semesters->modelKeys())->orderBy('name')->get();
        $departments = M\Department::orderBy('name')->get();
        $courses = M\Course::with('department')->orderBy('code')->get();
        $assignments = M\TeacherAssignment::with('teacher', 'course', 'section')->whereIn('section_id', $sections->modelKeys())->get();
        $students = M\StudentProfile::with('user', 'section')->whereIn('section_id', $sections->modelKeys())->orderBy('roll_number')->get();
        $enrollments = M\Enrollment::whereIn('section_id', $sections->modelKeys())->get();
        $gaps = [
            'departments' => $departments->where('active', true)->isEmpty(),
            'courses' => $courses->where('active', true)->isEmpty() || $sections->where('active', true)->isEmpty(),
            'teachers' => $assignments->isEmpty(),
            'students' => $students->isEmpty() || $students->contains(fn ($s) => ! $enrollments->contains(fn ($e) => $e->student_id === $s->user_id && $e->section_id === $s->section_id)),
        ];
        $unassigned = $courses->where('active', true)->filter(fn ($c) => $sections->where('department_id', $c->department_id)->where('active', true)->isNotEmpty() && ! $assignments->contains('course_id', $c->id));
        $gaps['teachers'] = $gaps['teachers'] || $unassigned->isNotEmpty();
        $progress = ['session' => $session !== null, 'departments' => ! $gaps['departments'], 'courses' => ! $gaps['courses'], 'teachers' => ! $gaps['teachers'], 'students' => ! $gaps['students']];
        $next = collect($progress)->search(false) ?: 'review';
        $page = ['slug' => 'academic-setup', 'title' => 'Academic Setup', 'module' => 'Academic Setup', 'kind' => 'form'];
        $user = $request->user();

        return view('admin.academic-setup', compact('step', 'context', 'session', 'semesters', 'sections', 'departments', 'courses', 'assignments', 'students', 'enrollments', 'gaps', 'unassigned', 'progress', 'next', 'page') + [
            'steps' => self::STEPS, 'role' => 'admin', 'userName' => $user->name,
            'initials' => collect(explode(' ', $user->name))->map(fn ($name) => mb_substr($name, 0, 1))->take(2)->implode(''),
            'unreadCount' => $user->unreadNotifications()->count(), 'notifications' => $user->notifications()->latest()->limit(10)->get(),
            'sessions' => M\AcademicSession::orderByDesc('starts_on')->get(),
            'teachers' => M\User::where('role', 'teacher')->where('active', true)->orderBy('name')->get(),
            'studentAccounts' => M\User::where('role', 'student')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request, string $action)
    {
        abort_unless(in_array($action, ['session', 'department', 'course', 'semester', 'section', 'teacher', 'student', 'finish']), 404);
        $sessionId = app(AcademicContext::class)->current()['session_id'];
        if ($action !== 'session') {
            throw_unless($sessionId, ValidationException::withMessages(['session' => 'Select a session before adding academic records.']));
        }
        $rules = match ($action) {
            'session' => ['mode' => 'required|in:existing,new', 'session_id' => 'required_if:mode,existing|nullable|integer|exists:academic_sessions,id', 'term' => 'required_if:mode,new|nullable|in:Fall,Spring', 'year' => 'required_if:mode,new|nullable|integer|between:2000,2100', 'starts_on' => 'required_if:mode,new|nullable|date', 'ends_on' => 'required_if:mode,new|nullable|date|after:starts_on'],
            'department' => ['name' => 'required|string|max:120', 'code' => 'required|string|max:20|unique:departments,code'],
            'course' => ['department_id' => 'required|integer|exists:departments,id', 'name' => 'required|string|max:120', 'code' => 'required|string|max:20|unique:courses,code', 'credits' => 'required|numeric|between:0.01,30'],
            'semester' => ['name' => ['required', 'string', 'max:80', Rule::unique('semesters')->where('academic_session_id', $sessionId)], 'number' => 'required|integer|between:1,12'],
            'section' => ['department_id' => 'required|integer|exists:departments,id', 'semester_id' => ['required', 'integer', Rule::exists('semesters', 'id')->where('academic_session_id', $sessionId)], 'name' => ['required', 'string', 'max:80', Rule::unique('sections')->where('department_id', $request->input('department_id'))->where('semester_id', $request->input('semester_id'))]],
            'teacher', 'student' => [
                'mode' => 'required|in:existing,new',
                'account_id' => ['required_if:mode,existing', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', $action)->where('active', true)],
                'name' => 'required_if:mode,new|nullable|string|max:120',
                'email' => ['required_if:mode,new', 'nullable', 'email', 'max:254', Rule::unique('users')],
                'password' => 'required_if:mode,new|nullable|string|min:12|max:255',
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereIn('semester_id', M\Semester::where('academic_session_id', $sessionId)->select('id'))],
                'course_ids' => 'required|array|min:1|max:100', 'course_ids.*' => 'required|integer|distinct|exists:courses,id',
                ...($action === 'student' ? ['roll_number' => 'nullable|string|max:40'] : []),
            ],
            default => [],
        };
        $data = $request->validate($rules);
        try {
            DB::transaction(function () use ($request, $action, $data, $sessionId) {
                $service = app(AcademicService::class);
                if ($action === 'session') {
                    $session = $data['mode'] === 'new'
                        ? $service->save('sessions', ['name' => $data['term'].' '.$data['year'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'active' => true])
                        : M\AcademicSession::findOrFail($data['session_id']);
                    $request->session()->put(AcademicContext::SESSION_KEY, ['user_id' => $request->user()->id, 'session_id' => $session->id, 'semester_id' => null]);
                } elseif (in_array($action, ['department', 'course', 'semester', 'section'])) {
                    $resource = ['department' => 'departments', 'course' => 'courses', 'semester' => 'semesters', 'section' => 'sections'][$action];
                    $service->save($resource, $data + ($action === 'semester' ? ['academic_session_id' => $sessionId] : []) + ['active' => true]);
                } elseif (in_array($action, ['teacher', 'student'])) {
                    $section = M\Section::with('semester')->findOrFail($data['section_id']);
                    if (M\Course::whereIn('id', $data['course_ids'])->where('department_id', '!=', $section->department_id)->exists()) {
                        throw ValidationException::withMessages(['course_ids' => 'Choose courses from the same department as the class / section.']);
                    }
                    $user = $data['mode'] === 'new'
                        ? $service->save('users', ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => $action, 'active' => true])
                        : M\User::findOrFail($data['account_id']);
                    if ($action === 'student') {
                        $profile = M\StudentProfile::where('user_id', $user->id)->first();
                        if ($profile && $profile->section_id !== $section->id) {
                            throw ValidationException::withMessages(['account_id' => 'This student already has a class profile. Use the existing profile editor to review changes; their history will not be moved here.']);
                        }
                        if (! $profile) {
                            if (empty($data['roll_number'])) {
                                throw ValidationException::withMessages(['roll_number' => 'Enter a roll number for this student.']);
                            }
                            $service->save('students', ['user_id' => $user->id, 'section_id' => $section->id, 'roll_number' => $data['roll_number'], 'eligible' => true]);
                        } elseif (! empty($data['roll_number']) && $data['roll_number'] !== $profile->roll_number) {
                            throw ValidationException::withMessages(['roll_number' => 'This student already has a roll number. Leave this blank to keep it.']);
                        }
                    } elseif (! M\TeacherProfile::where('teacher_id', $user->id)->exists()) {
                        $service->save('teacher-profiles', ['teacher_id' => $user->id, 'department_id' => $section->department_id, 'reserve' => false, 'max_duties_per_day' => 2]);
                    }
                    foreach ($data['course_ids'] as $courseId) {
                        $resource = $action === 'teacher' ? 'teacher-assignments' : 'enrollments';
                        $attributes = [($action === 'teacher' ? 'teacher_id' : 'student_id') => $user->id, 'course_id' => $courseId, 'section_id' => $section->id];
                        $class = $action === 'teacher' ? M\TeacherAssignment::class : M\Enrollment::class;
                        if (! $class::where($attributes)->exists()) {
                            $service->save($resource, $attributes);
                        }
                    }
                } else {
                    // Review is informational: finishing setup never publishes academic records.
                    app(AuditService::class)->record('academic_setup.reviewed', M\AcademicSession::findOrFail($sessionId), ['session_id' => $sessionId]);
                }
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[1] ?? 0, [1062, 1452])) {
                throw ValidationException::withMessages(['record' => 'This record already exists or references an unavailable record. Your changes were not saved.']);
            }
            throw $exception;
        }
        $step = match ($action) {
            'session' => 'departments', 'department' => 'departments', 'semester', 'section', 'course' => 'courses',
            'teacher' => 'teachers', 'student' => 'students', default => 'review',
        };

        return redirect()->route('academic-setup', ['step' => $step])->with('status', $action === 'finish' ? 'Setup review recorded. You can continue to examination planning when ready.' : ($action === 'session' ? 'Session selected. Continue with departments.' : 'Saved successfully. Add another or continue to the next step.'));
    }
}
