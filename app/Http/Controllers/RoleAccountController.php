<?php

namespace App\Http\Controllers;

use App\Models as M;
use App\Services\AcademicContext;
use App\Services\AcademicService;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleAccountController
{
    private function role(string $directory): string
    {
        abort_unless(in_array($directory, ['teachers', 'students'], true), 404);

        return $directory === 'teachers' ? 'teacher' : 'student';
    }

    private function account(string $directory, int $id): M\User
    {
        return M\User::where('role', $this->role($directory))->findOrFail($id);
    }

    private function response(Request $request, string $directory, string $mode, ?M\User $account = null)
    {
        $accountRole = $this->role($directory);
        $request->attributes->set('ws', ['key' => $directory, 'tab' => 'main', 'primary' => $directory]);
        $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive', 'scope' => 'nullable|in:all,session']);
        $context = app(AcademicContext::class);
        $sections = $context->apply(M\Section::query())->with('department', 'semester')->orderBy('name')->get();
        $query = M\User::where('role', $accountRole)->with(['teacherProfile', 'profile.section.department', 'assignments' => fn ($q) => $context->apply($q)->with('course', 'section'), 'enrollments' => fn ($q) => $context->apply($q)->with('course')]);
        if ($request->input('scope') === 'session') {
            $ids = $accountRole === 'teacher'
                ? $context->apply(M\TeacherAssignment::query())->select('teacher_id')
                : $context->apply(M\StudentProfile::query())->select('user_id');
            $query->whereIn('id', $ids);
        }
        if ($request->filled('q')) {
            $search = '%'.$request->input('q').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhereHas('profile', fn ($p) => $p->where('roll_number', 'like', $search)));
        }
        if ($request->filled('status')) {
            $query->where('active', $request->input('status') === 'active');
        }
        $records = $query->orderBy('name')->paginate(20)->withQueryString();
        $credentials = null;
        if ($encrypted = $request->session()->pull('account_credentials')) {
            $payload = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            if ($payload['admin_id'] === $request->user()->id && $payload['directory'] === $directory && $payload['expires_at'] > time()) {
                $credentials = $payload;
            }
        }
        $admin = $request->user();
        $page = ['slug' => $directory, 'title' => ucfirst($directory), 'module' => ucfirst($directory), 'kind' => 'table'];

        return response()->view('admin.role-accounts', compact('directory', 'accountRole', 'mode', 'account', 'sections', 'records', 'credentials', 'page') + [
            'role' => 'admin', 'userName' => $admin->name, 'initials' => collect(explode(' ', $admin->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''),
            'unreadCount' => $admin->unreadNotifications()->count(), 'notifications' => $admin->notifications()->latest()->limit(10)->get(),
            'departments' => M\Department::orderBy('name')->get(), 'courses' => M\Course::where('active', true)->orderBy('code')->get(),
            'sessionName' => M\AcademicSession::find($context->current()['session_id'])?->name,
        ])->header('Cache-Control', 'private, no-store, max-age=0')->header('Referrer-Policy', 'same-origin');
    }

    public function legacy(Request $request)
    {
        $resource = match ($request->input('view')) {
            'courses' => 'courses', 'sections' => 'sections', 'student-enrollment' => 'enrollments',
            'teacher-course-assignment' => 'teacher-assignments', 'semesters' => 'semesters', default => null,
        };
        if ($resource || in_array($request->input('tab'), ['academics', 'sessions'], true)) {
            return redirect('/admin/records?'.http_build_query(['resource' => $resource ?? ($request->input('tab') === 'sessions' ? 'sessions' : 'departments')]));
        }

        return redirect('/admin/'.($request->input('tab') === 'teachers' ? 'teachers' : 'students'));
    }

    public function index(Request $request, string $directory)
    {
        return $this->response($request, $directory, 'directory');
    }

    public function create(Request $request, string $directory)
    {
        return $this->response($request, $directory, 'create');
    }

    public function edit(Request $request, string $directory, int $id)
    {
        return $this->response($request, $directory, 'edit', $this->account($directory, $id));
    }

    public function save(Request $request, string $directory, ?int $id = null)
    {
        $role = $this->role($directory);
        $account = $id ? $this->account($directory, $id) : null;
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($id)],
            'password' => $id ? 'prohibited' : 'required|string|min:12|max:255|confirmed',
            'active' => 'required|boolean',
            'department_id' => [$role === 'teacher' && ! $id ? 'required' : 'nullable', 'integer', 'exists:departments,id'],
            'section_id' => [$role === 'student' && ! $id ? 'required' : 'nullable', 'integer', Rule::exists('sections', 'id')->whereIn('id', app(AcademicContext::class)->apply(M\Section::query())->select('sections.id'))],
            'roll_number' => [$role === 'student' && ! $id ? 'required' : 'nullable', 'string', 'max:40', Rule::unique('student_profiles')->ignore($account?->profile?->id)],
            'course_ids' => 'nullable|array|max:100', 'course_ids.*' => 'required|integer|distinct|exists:courses,id',
        ]);
        try {
            $account = DB::transaction(function () use ($data, $id, $role) {
                $service = app(AcademicService::class);
                $account = $service->save('users', collect($data)->only(['name', 'email', 'password', 'active'])->all() + ['role' => $role], $id);
                // Directory edits change account identity/status only. Academic history has its own editors.
                if ($id) {
                    return $account;
                }
                if ($role === 'teacher') {
                    $service->save('teacher-profiles', ['teacher_id' => $account->id, 'department_id' => $data['department_id'], 'reserve' => false, 'max_duties_per_day' => 2]);
                } else {
                    $section = M\Section::findOrFail($data['section_id']);
                    $service->save('students', ['user_id' => $account->id, 'section_id' => $section->id, 'roll_number' => $data['roll_number'], 'eligible' => true]);
                    foreach ($data['course_ids'] ?? [] as $course) {
                        if (! M\Course::whereKey($course)->where('department_id', $section->department_id)->where('active', true)->exists()) {
                            throw ValidationException::withMessages(['course_ids' => 'Select active courses from this class’s department.']);
                        }
                        $service->save('enrollments', ['student_id' => $account->id, 'course_id' => $course, 'section_id' => $section->id, 'eligible' => true]);
                    }
                }

                return $account;
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[1] ?? 0, [1062, 1452])) {
                throw ValidationException::withMessages(['record' => 'This account or roll number already exists. No changes were saved.']);
            }
            throw $exception;
        }
        if (! $id) {
            $this->credentials($request, $directory, $account, $data['password']);
        }

        return redirect('/admin/'.$directory)->with('status', $id ? ucfirst($role).' account updated.' : ucfirst($role).' added. Their login is ready.');
    }

    public function password(Request $request, string $directory, int $id)
    {
        $account = $this->account($directory, $id);
        $data = $request->validate(['password' => 'required|string|min:12|max:255|confirmed']);
        DB::transaction(function () use ($account, $data) {
            $account->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            app(AuditService::class)->record('account.password_set', $account, ['role' => $account->role]);
        });
        $this->credentials($request, $directory, $account, $data['password']);

        return redirect('/admin/'.$directory)->with('status', 'New password saved. Share the updated login privately.');
    }

    private function credentials(Request $request, string $directory, M\User $account, string $password): void
    {
        // Only ciphertext is stored in the session, bound to the current administrator.
        $request->session()->flash('account_credentials', Crypt::encryptString(json_encode([
            'admin_id' => $request->user()->id, 'directory' => $directory, 'expires_at' => time() + 300,
            'name' => $account->name, 'email' => $account->email, 'password' => $password, 'active' => $account->active,
        ], JSON_THROW_ON_ERROR)));
    }
}
