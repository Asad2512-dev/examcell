<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException as V;

/**
 * Spreadsheet import for students and teachers that needs no AI and no technical knowledge:
 * friendly column names, class names instead of ids, a row-by-row check, and only the good rows
 * are created. Existing accounts are never overwritten.
 */
class PeopleImport
{
    public const MAX_ROWS = 1000;

    /** kind => [column key => [label, required, accepted header spellings]] */
    public const COLUMNS = [
        'students' => [
            'name' => ['Name', true, ['name', 'fullname', 'studentname', 'student']],
            'email' => ['Email', true, ['email', 'emailaddress', 'mail', 'studentemail']],
            'roll' => ['Roll number', true, ['rollnumber', 'rollno', 'roll', 'registrationnumber', 'regno', 'registration', 'studentid', 'id']],
            'class' => ['Class', true, ['class', 'section', 'classsection', 'classname', 'batch']],
            'courses' => ['Courses', false, ['courses', 'coursecodes', 'subjects']],
        ],
        'teachers' => [
            'name' => ['Name', true, ['name', 'fullname', 'teachername', 'teacher']],
            'email' => ['Email', true, ['email', 'emailaddress', 'mail', 'teacheremail']],
            'department' => ['Department', false, ['department', 'dept', 'departmentname']],
            'class' => ['Class', false, ['class', 'section', 'classsection', 'classname']],
            'courses' => ['Courses', false, ['courses', 'coursecodes', 'subjects']],
        ],
        'departments' => [
            'name' => ['Name', true, ['name', 'department', 'departmentname']],
            'code' => ['Code', true, ['code', 'departmentcode', 'deptcode']],
            'active' => ['Active', false, ['active', 'status', 'enabled']],
        ],
        'sessions' => [
            'name' => ['Name', true, ['name', 'session', 'sessionname', 'academicsession']],
            'starts_on' => ['Start date', true, ['startdate', 'startson', 'from', 'fromdate']],
            'ends_on' => ['End date', true, ['enddate', 'endson', 'to', 'todate']],
            'active' => ['Active', false, ['active', 'status', 'enabled']],
        ],
    ];

    /** @return list<list<string>> raw cell rows, header first */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            $rows = app(XlsxReader::class)->rows($path, [], true);
        } elseif (in_array($extension, ['csv', 'txt', 'tsv'], true)) {
            $text = (string) file_get_contents($path);
            $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
            $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'UTF-8');
            $first = strtok($text, "\n") ?: '';
            $delimiter = collect([',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")])->sortDesc()->keys()->first();
            $rows = [];
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $text);
            rewind($stream);
            while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
                if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                    continue;
                }
                $rows[] = array_map(fn ($c) => trim((string) $c), $row);
                if (count($rows) > self::MAX_ROWS + 1) {
                    break;
                }
            }
            fclose($stream);
        } else {
            throw V::withMessages(['file' => 'Please upload a CSV or Excel (.xlsx) file.']);
        }
        if (count($rows) < 2) {
            throw V::withMessages(['file' => 'The file has no people in it. Add one person per row under the column titles.']);
        }
        if (count($rows) > self::MAX_ROWS + 1) {
            throw V::withMessages(['file' => 'Please import at most '.self::MAX_ROWS.' people at a time.']);
        }

        return $rows;
    }

    /** Check every row. @return array{rows: list<array>, ok: int, bad: int, classes: array} */
    public function check(string $kind, array $raw): array
    {
        if (in_array($kind, ['departments', 'sessions'], true)) {
            return $this->checkAcademic($kind, $raw);
        }
        $spec = self::COLUMNS[$kind];
        $index = $this->columnIndex($spec, array_shift($raw));
        $session = app(AcademicContext::class)->current()['session_id'];
        $sections = M\Section::with('department:id,name,code')->whereIn('semester_id', M\Semester::where('academic_session_id', $session)->select('id'))->where('active', true)->get();
        $byName = $sections->groupBy(fn ($s) => $this->norm($s->name));
        $courses = M\Course::where('active', true)->get()->keyBy(fn ($c) => $this->norm($c->code));
        $departments = M\Department::where('active', true)->get();
        $classCourses = M\TeacherAssignment::whereIn('section_id', $sections->modelKeys())->get(['section_id', 'course_id'])
            ->concat(M\Enrollment::whereIn('section_id', $sections->modelKeys())->groupBy('section_id', 'course_id')->get(['section_id', 'course_id']))
            ->groupBy('section_id')->map(fn ($g) => $g->pluck('course_id')->unique()->values());
        $locked = M\ExamCourse::whereHas('examination', fn ($q) => $q->where('status', '!=', 'draft'))->get(['course_id', 'section_id'])->map(fn ($e) => $e->section_id.':'.$e->course_id)->flip();
        $takenEmails = M\User::pluck('id', 'email')->mapWithKeys(fn ($id, $email) => [strtolower($email) => true]);
        $takenRolls = $kind === 'students' ? M\StudentProfile::pluck('roll_number')->mapWithKeys(fn ($r) => [strtolower($r) => true]) : collect();
        $seenEmail = [];
        $seenRoll = [];

        $rows = [];
        foreach ($raw as $i => $cells) {
            $get = fn (string $key) => isset($index[$key]) ? trim((string) ($cells[$index[$key]] ?? '')) : '';
            $row = ['line' => $i + 2, 'name' => $get('name'), 'email' => strtolower($get('email')), 'roll' => $get('roll'), 'class' => $get('class'), 'department' => $get('department'), 'courses' => $get('courses'), 'errors' => [], 'notes' => [], 'section_id' => null, 'course_ids' => [], 'department_id' => null];
            $e = &$row['errors'];

            $row['name'] === '' && $e[] = 'Name is missing.';
            if ($row['email'] === '' || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL) || strlen($row['email']) > 254) {
                $e[] = $row['email'] === '' ? 'Email is missing.' : 'This email does not look right.';
            } elseif ($takenEmails->has($row['email'])) {
                $e[] = 'This email already has an account.';
            } elseif (isset($seenEmail[$row['email']])) {
                $e[] = 'This email appears again on line '.$seenEmail[$row['email']].'.';
            } else {
                $seenEmail[$row['email']] = $row['line'];
            }
            if ($kind === 'students') {
                if ($row['roll'] === '') {
                    $e[] = 'Roll number is missing.';
                } elseif ($takenRolls->has(strtolower($row['roll']))) {
                    $e[] = 'This roll number already exists.';
                } elseif (isset($seenRoll[strtolower($row['roll'])])) {
                    $e[] = 'This roll number appears again on line '.$seenRoll[strtolower($row['roll'])].'.';
                } else {
                    $seenRoll[strtolower($row['roll'])] = $row['line'];
                }
            }

            $section = null;
            if ($row['class'] !== '') {
                $matches = $byName[$this->norm($row['class'])] ?? collect();
                if ($matches->count() === 1) {
                    $section = $matches->first();
                } elseif ($matches->count() > 1) {
                    $e[] = 'More than one class is called "'.$row['class'].'". Rename one of them first.';
                } else {
                    $e[] = 'We could not find a class called "'.$row['class'].'" in this session. Open "Academic Setup" to see class names.';
                }
            } elseif ($kind === 'students') {
                $e[] = 'Class is missing.';
            }
            $row['section_id'] = $section?->id;

            $wanted = collect(preg_split('/[;,|\/]+/', $row['courses']))->map(fn ($c) => trim($c))->filter()->values();
            if ($wanted->isNotEmpty()) {
                foreach ($wanted as $code) {
                    $course = $courses[$this->norm($code)] ?? null;
                    if (! $course) {
                        $e[] = 'Unknown course "'.$code.'".';
                    } elseif ($section && $course->department_id !== $section->department_id) {
                        $e[] = $course->code.' belongs to a different department from '.$section->name.'.';
                    } else {
                        $row['course_ids'][] = $course->id;
                    }
                }
            } elseif ($kind === 'students' && $section) {
                $row['course_ids'] = ($classCourses[$section->id] ?? collect())->all();
                if (! $row['course_ids']) {
                    $row['notes'][] = 'This class has no courses yet, so no enrollments will be made.';
                }
            }
            if ($kind === 'teachers' && $row['courses'] !== '' && ! $section) {
                $e[] = 'Add the Class these courses are taught in.';
            }
            foreach ($row['course_ids'] as $courseId) {
                if ($section && isset($locked[$section->id.':'.$courseId])) {
                    $e[] = 'An approved examination already uses this class and course, so it can no longer change.';
                    break;
                }
            }

            $row['department_id'] = $section?->department_id;
            if ($kind === 'teachers' && $row['department'] !== '') {
                $dept = $departments->first(fn ($d) => $this->norm($d->name) === $this->norm($row['department']) || $this->norm($d->code) === $this->norm($row['department']));
                if ($dept) {
                    $row['department_id'] = $dept->id;
                } else {
                    $e[] = 'We could not find the department "'.$row['department'].'".';
                }
            }
            if ($kind === 'teachers' && ! $row['department_id']) {
                $row['department_id'] = $departments->first()?->id;
                if (! $row['department_id']) {
                    $e[] = 'Add a department first.';
                }
            }
            unset($e);
            $row['ok'] = ! $row['errors'];
            $rows[] = $row;
        }
        $ok = count(array_filter($rows, fn ($r) => $r['ok']));

        return ['rows' => $rows, 'ok' => $ok, 'bad' => count($rows) - $ok, 'kind' => $kind];
    }

    /** Create the good rows. @return array{created: int, skipped: int, credentials: list<array>} */
    public function commit(string $kind, array $rows, bool $withPasswords): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => $r['ok']));
        if (in_array($kind, ['departments', 'sessions'], true)) {
            DB::transaction(function () use ($kind, $rows) {
                foreach ($rows as $row) {
                    if ($kind === 'departments') {
                        M\Department::create(['name' => $row['name'], 'code' => $row['code'], 'active' => $row['active']]);
                    } else {
                        M\AcademicSession::create(['name' => $row['name'], 'starts_on' => $row['starts_on'], 'ends_on' => $row['ends_on'], 'active' => $row['active']]);
                    }
                }
                app(AuditService::class)->record($kind.'.bulk_imported', $kind, ['count' => count($rows)]);
            });

            return ['created' => count($rows), 'credentials' => []];
        }
        // One shared, unknowable hash makes thousands of accounts instantly; people set their own password.
        $placeholder = Hash::make(Str::random(64));
        $credentials = [];
        @set_time_limit(300);
        DB::transaction(function () use ($kind, $rows, $withPasswords, $placeholder, &$credentials) {
            $now = now();
            $enrollments = [];
            $assignments = [];
            foreach ($rows as $row) {
                $password = $withPasswords ? $this->temporaryPassword() : null;
                $user = new M\User(['name' => $row['name'], 'email' => $row['email'], 'role' => $kind === 'students' ? 'student' : 'teacher', 'active' => true]);
                $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => $password ? Hash::driver('bcrypt')->make($password, ['rounds' => 10]) : $placeholder]));
                $user->save();
                if ($kind === 'students') {
                    M\StudentProfile::create(['user_id' => $user->id, 'section_id' => $row['section_id'], 'roll_number' => $row['roll'], 'eligible' => true]);
                    foreach ($row['course_ids'] as $courseId) {
                        $enrollments[] = ['student_id' => $user->id, 'course_id' => $courseId, 'section_id' => $row['section_id'], 'eligible' => true, 'created_at' => $now, 'updated_at' => $now];
                    }
                } else {
                    M\TeacherProfile::create(['teacher_id' => $user->id, 'department_id' => $row['department_id'], 'reserve' => false, 'max_duties_per_day' => University::maxDutiesPerDay()]);
                    foreach ($row['course_ids'] as $courseId) {
                        $assignments[] = ['teacher_id' => $user->id, 'course_id' => $courseId, 'section_id' => $row['section_id'], 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                $credentials[] = ['name' => $row['name'], 'email' => $row['email'], 'roll' => $row['roll'], 'password' => $password];
            }
            foreach (array_chunk($enrollments, 500) as $chunk) {
                M\Enrollment::insert($chunk);
            }
            foreach (array_chunk($assignments, 500) as $chunk) {
                M\TeacherAssignment::insert($chunk);
            }
            app(AuditService::class)->record($kind.'.bulk_imported', $kind, ['count' => count($rows), 'with_temporary_passwords' => $withPasswords]);
        });

        return ['created' => count($rows), 'credentials' => $credentials];
    }

    public function template(string $kind): string
    {
        $session = app(AcademicContext::class)->current()['session_id'];
        $section = M\Section::whereIn('semester_id', M\Semester::where('academic_session_id', $session)->select('id'))->where('active', true)->orderBy('name')->value('name') ?? 'Class A';
        $headers = array_map(fn ($c) => $c[0], self::COLUMNS[$kind]);
        $examples = match ($kind) {
            'students' => [['Ayesha Khan', 'ayesha@example.edu', 'CS-001', $section, ''], ['Bilal Ahmed', 'bilal@example.edu', 'CS-002', $section, '']],
            'teachers' => [['Dr Sara Malik', 'sara@example.edu', '', '', ''], ['Mr Omar Raza', 'omar@example.edu', '', $section, 'CS101; CS102']],
            'departments' => [['Computer Science', 'CS', 'Yes'], ['Information Technology', 'IT', 'Yes']],
            'sessions' => [['Fall 2026', '2026-08-01', '2026-12-31', 'Yes'], ['Spring 2027', '2027-01-01', '2027-06-30', 'No']],
        };
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_values($headers));
        foreach ($examples as $example) {
            fputcsv($out, $example);
        }
        rewind($out);

        return "\xEF\xBB\xBF".stream_get_contents($out);
    }

    public function templateRows(string $kind): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $this->template($kind));
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function exportRows(string $kind): array
    {
        $headers = array_map(fn ($column) => $column[0], self::COLUMNS[$kind]);
        $rows = match ($kind) {
            'students' => M\User::where('role', 'student')->with('profile.section', 'enrollments.course')->orderBy('name')->get()->map(fn ($user) => [$user->name, $user->email, $user->profile?->roll_number, $user->profile?->section?->name, $user->enrollments->pluck('course.code')->filter()->sort()->implode('; ')]),
            'teachers' => M\User::where('role', 'teacher')->with('teacherProfile', 'assignments.course', 'assignments.section')->orderBy('name')->get()->map(function ($user) {
                $departments = M\Department::pluck('name', 'id');
                $classes = $user->assignments->pluck('section.name')->filter()->unique();

                return [$user->name, $user->email, $departments[$user->teacherProfile?->department_id] ?? '', $classes->count() === 1 ? $classes->first() : '', $user->assignments->pluck('course.code')->filter()->unique()->sort()->implode('; ')];
            }),
            'departments' => M\Department::orderBy('name')->get()->map(fn ($department) => [$department->name, $department->code, $department->active ? 'Yes' : 'No']),
            'sessions' => M\AcademicSession::orderByDesc('starts_on')->get()->map(fn ($session) => [$session->name, $session->starts_on->format('Y-m-d'), $session->ends_on->format('Y-m-d'), $session->active ? 'Yes' : 'No']),
        };

        return [array_values($headers), ...$rows->map(fn ($row) => array_values($row))->all()];
    }

    private function checkAcademic(string $kind, array $raw): array
    {
        $index = $this->columnIndex(self::COLUMNS[$kind], array_shift($raw));
        $existingNames = ($kind === 'departments' ? M\Department::query() : M\AcademicSession::query())->pluck('name')->map(fn ($name) => $this->norm($name))->flip();
        $existingCodes = $kind === 'departments' ? M\Department::pluck('code')->map(fn ($code) => $this->norm($code))->flip() : collect();
        $seenNames = [];
        $seenCodes = [];
        $rows = [];
        foreach ($raw as $i => $cells) {
            $get = fn (string $key) => isset($index[$key]) ? trim((string) ($cells[$index[$key]] ?? '')) : '';
            $activeInput = $get('active');
            $row = ['line' => $i + 2, 'name' => $get('name'), 'code' => $get('code'), 'starts_on' => $get('starts_on'), 'ends_on' => $get('ends_on'), 'active' => $this->truthy($activeInput), 'errors' => [], 'notes' => []];
            if ($activeInput !== '' && ! in_array($this->norm($activeInput), ['1', '0', 'yes', 'no', 'true', 'false', 'active', 'inactive', 'enabled', 'disabled'], true)) {
                $row['errors'][] = 'Active must be Yes or No.';
            }
            if ($row['name'] === '') {
                $row['errors'][] = 'Name is missing.';
            } elseif ($existingNames->has($this->norm($row['name']))) {
                $row['errors'][] = 'This name already exists.';
            } elseif (isset($seenNames[$this->norm($row['name'])])) {
                $row['errors'][] = 'This name appears again on line '.$seenNames[$this->norm($row['name'])].'.';
            } else {
                $seenNames[$this->norm($row['name'])] = $row['line'];
            }
            if ($kind === 'departments') {
                if ($row['code'] === '') {
                    $row['errors'][] = 'Code is missing.';
                } elseif ($existingCodes->has($this->norm($row['code']))) {
                    $row['errors'][] = 'This code already exists.';
                } elseif (isset($seenCodes[$this->norm($row['code'])])) {
                    $row['errors'][] = 'This code appears again on line '.$seenCodes[$this->norm($row['code'])].'.';
                } else {
                    $seenCodes[$this->norm($row['code'])] = $row['line'];
                }
            } else {
                foreach (['starts_on' => 'Start date', 'ends_on' => 'End date'] as $field => $label) {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[$field]) || ! strtotime($row[$field])) {
                        $row['errors'][] = $label.' must use YYYY-MM-DD.';
                    }
                }
                if (! $row['errors'] && $row['ends_on'] <= $row['starts_on']) {
                    $row['errors'][] = 'End date must be after the start date.';
                }
            }
            $row['ok'] = ! $row['errors'];
            $rows[] = $row;
        }
        $ok = count(array_filter($rows, fn ($row) => $row['ok']));

        return ['rows' => $rows, 'ok' => $ok, 'bad' => count($rows) - $ok, 'kind' => $kind];
    }

    private function truthy(string $value): bool
    {
        return $value === '' || in_array($this->norm($value), ['1', 'yes', 'true', 'active', 'enabled'], true);
    }

    private function columnIndex(array $spec, array $header): array
    {
        $index = [];
        foreach ($header as $position => $title) {
            $n = $this->norm((string) $title);
            foreach ($spec as $key => [, , $names]) {
                if (! isset($index[$key]) && in_array($n, $names, true)) {
                    $index[$key] = $position;
                }
            }
        }
        $missing = collect($spec)->filter(fn ($c, $key) => $c[1] && ! isset($index[$key]))->map(fn ($c) => $c[0])->values();
        if ($missing->isNotEmpty()) {
            throw V::withMessages(['file' => 'We could not find the column'.($missing->count() > 1 ? 's' : '').': '.$missing->implode(', ').'. Use the template so the first row has the right titles.']);
        }

        return $index;
    }

    private function norm(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
