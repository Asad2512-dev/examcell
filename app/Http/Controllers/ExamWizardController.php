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

/**
 * One screen that replaces three raw record editors: pick classes, tick the papers, choose how marks
 * are split, and every paper and marks breakdown is created in one save.
 */
class ExamWizardController
{
    public const TYPES = ['midterm' => 'Midterm', 'final' => 'Final exam', 'quiz' => 'Quiz', 'assignment' => 'Assignment'];

    public const DURATIONS = ['midterm' => 90, 'final' => 180, 'quiz' => 30, 'assignment' => 60];

    /** Marks breakdown presets: label, hint and [component name, weight %]. */
    public const PRESETS = [
        'single' => ['One written paper', 'All marks come from one exam.', [['Written paper', 100]]],
        'coursework' => ['Coursework + written exam', '30% coursework, 70% written exam.', [['Coursework', 30], ['Written exam', 70]]],
        'quizzes' => ['Quizzes + assignments + written exam', '10% quizzes, 10% assignments, 80% written exam.', [['Quizzes', 10], ['Assignments', 10], ['Written exam', 80]]],
    ];

    public function show(Request $request)
    {
        $sessionId = app(AcademicContext::class)->current()['session_id'];
        if (! $sessionId) {
            return redirect()->route('academic-setup', ['step' => 'session'])->with('status', 'Choose an academic session first. Then you can create an examination.');
        }
        $exam = $request->filled('exam') ? M\Examination::where('status', 'draft')->where('academic_session_id', $sessionId)->find($request->integer('exam')) : null;
        $session = M\AcademicSession::find($sessionId);
        $sections = M\Section::with('department:id,name', 'semester:id,name')->whereIn('semester_id', M\Semester::where('academic_session_id', $sessionId)->select('id'))
            ->where('active', true)->orderBy('name')->get();
        $enrolled = M\Enrollment::whereIn('section_id', $sections->modelKeys())->where('eligible', true)->groupBy('section_id', 'course_id')
            ->selectRaw('section_id, course_id, COUNT(*) AS students')->get()->groupBy('section_id');
        $taught = M\TeacherAssignment::whereIn('section_id', $sections->modelKeys())->get(['section_id', 'course_id'])->groupBy('section_id');
        $courses = M\Course::whereIn('id', $enrolled->flatten()->pluck('course_id')->merge($taught->flatten()->pluck('course_id'))->unique())->get()->keyBy('id');
        $existing = $exam ? $exam->courses()->get(['section_id', 'course_id'])->map(fn ($p) => $p->section_id.':'.$p->course_id)->all() : [];

        $classes = $sections->map(function ($section) use ($enrolled, $taught, $courses, $existing) {
            $papers = collect($enrolled[$section->id] ?? [])->keyBy('course_id');
            foreach ($taught[$section->id] ?? [] as $t) {
                $papers->has($t->course_id) || $papers->put($t->course_id, (object) ['course_id' => $t->course_id, 'students' => 0]);
            }

            return [
                'section' => $section,
                'papers' => $papers->map(fn ($p) => ['course' => $courses[$p->course_id] ?? null, 'students' => (int) $p->students, 'exists' => in_array($section->id.':'.$p->course_id, $existing, true)])
                    ->filter(fn ($p) => $p['course'] && $p['course']->active)->sortBy(fn ($p) => $p['course']->code)->values(),
            ];
        })->filter(fn ($c) => $c['papers']->isNotEmpty())->values();

        return view('admin.exam-wizard', [
            'exam' => $exam, 'session' => $session, 'classes' => $classes,
            'schemes' => M\GradingScheme::orderByRaw("status = 'approved' desc")->orderBy('name')->get(['id', 'name', 'status']),
            'types' => self::TYPES, 'durations' => self::DURATIONS, 'presets' => self::PRESETS,
        ]);
    }

    public function store(Request $request)
    {
        $sessionId = app(AcademicContext::class)->current()['session_id'];
        abort_unless($sessionId, 422, 'Choose an academic session first.');
        $exam = $request->filled('exam_id') ? M\Examination::where('status', 'draft')->where('academic_session_id', $sessionId)->findOrFail($request->integer('exam_id')) : null;

        $data = $request->validate([
            'exam_id' => 'nullable|integer',
            'name' => [$exam ? 'nullable' : 'required', 'string', 'max:120'],
            'type' => [$exam ? 'nullable' : 'required', Rule::in(array_keys(self::TYPES))],
            'grading_scheme_id' => [$exam ? 'nullable' : 'required', 'integer', 'exists:grading_schemes,id'],
            'papers' => 'required|array|min:1|max:300', 'papers.*' => ['required', 'regex:/^\d+:\d+$/'],
            'duration_minutes' => 'required|integer|between:15,480',
            'maximum' => 'required|numeric|between:1,10000',
            'pass_percent' => 'required|numeric|between:0,100',
            'preset' => ['required', Rule::in(array_keys(self::PRESETS))],
        ], ['papers.required' => 'Tick at least one paper.', 'papers.min' => 'Tick at least one paper.']);

        $pairs = collect($data['papers'])->unique()->map(fn ($p) => array_map('intval', explode(':', $p)));
        $sections = M\Section::with('semester')->whereIn('id', $pairs->pluck(0)->unique())->get()->keyBy('id');
        $courses = M\Course::whereIn('id', $pairs->pluck(1)->unique())->get()->keyBy('id');
        foreach ($pairs as [$sectionId, $courseId]) {
            $section = $sections[$sectionId] ?? null;
            $course = $courses[$courseId] ?? null;
            if (! $section || ! $course || $section->semester->academic_session_id !== (int) $sessionId || $course->department_id !== $section->department_id) {
                throw ValidationException::withMessages(['papers' => 'One of the chosen papers does not belong to this session.']);
            }
        }

        $service = app(AcademicService::class);
        $created = 0;
        $skipped = 0;
        try {
            $exam = DB::transaction(function () use ($data, $exam, $pairs, $sessionId, $service, &$created, &$skipped) {
                $exam ??= $service->save('examinations', ['name' => $data['name'], 'type' => $data['type'], 'academic_session_id' => $sessionId, 'grading_scheme_id' => $data['grading_scheme_id'], 'admit_enabled' => false]);
                $maximum = (float) $data['maximum'];
                $passing = round($maximum * (float) $data['pass_percent'] / 100, 2);
                foreach ($pairs as [$sectionId, $courseId]) {
                    if (M\ExamCourse::where(['examination_id' => $exam->id, 'section_id' => $sectionId, 'course_id' => $courseId])->exists()) {
                        $skipped++;

                        continue;
                    }
                    $paper = $service->save('exam-courses', ['examination_id' => $exam->id, 'course_id' => $courseId, 'section_id' => $sectionId, 'duration_minutes' => (int) $data['duration_minutes'], 'maximum' => $maximum, 'passing' => $passing]);
                    foreach (self::PRESETS[$data['preset']][2] as [$name, $weight]) {
                        $service->save('components', ['exam_course_id' => $paper->id, 'name' => $name, 'maximum' => round($maximum * $weight / 100, 2), 'weight' => $weight]);
                    }
                    $created++;
                }
                app(AuditService::class)->record('examination.wizard', $exam, ['papers_created' => $created, 'preset' => $data['preset']]);

                return $exam;
            });
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? 0, [1062, 1452])) {
                throw ValidationException::withMessages(['papers' => 'Some of these papers already exist. Nothing was saved.']);
            }
            throw $e;
        }

        $message = $created.' paper'.($created === 1 ? '' : 's').' added to '.$exam->name.'.'.($skipped ? ' '.$skipped.' already existed.' : '').' Next: make the date sheet.';

        return redirect('/admin/exams?exam='.$exam->id.'&tab=datesheet')->with('status', $message);
    }
}
