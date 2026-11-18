<?php

use App\Models as M;
use App\Services\GradingService;
use App\Services\MarksService;
use App\Services\SchedulingService;
use App\Support\ScreenCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only checks against the selected application database; never resets tables.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$session = M\AcademicSession::where('name', 'DEMO Visual Review 2026-27')->firstOrFail();
$exams = M\Examination::where('academic_session_id', $session->id)->pluck('id');
$courses = M\ExamCourse::whereIn('examination_id', $exams)->get();
$issues = [];
foreach ($courses as $course) {
    $eligible = $course->eligible()->pluck('student_id')->sort()->values()->all();
    $seated = $course->schedule->seatingPlan->seats()->pluck('student_id')->sort()->values()->all();
    if ($eligible !== $seated) {
        $issues[] = 'Seat roster mismatch: '.$course->id;
    }
    foreach (app(SchedulingService::class)->entryConflicts($course->schedule) as $error) {
        $issues[] = $course->id.': '.$error;
    }
    if ($course->examination->batch && app(MarksService::class)->missing($course)) {
        $issues[] = 'Missing result input: '.$course->id;
    }
}
foreach (M\CalculatedResult::whereIn('exam_course_id', $courses->pluck('id'))->with('examCourse')->get() as $result) {
    $expected = app(GradingService::class)->calculate($result->examCourse, $result->student_id);
    foreach ($expected as $key => $value) {
        if ((string) $result->$key !== (string) $value) {
            $issues[] = 'Calculated result mismatch: '.$result->id.' / '.$key;
        }
    }
}
foreach (['users', 'student_profiles', 'teacher_profiles', 'departments', 'sections', 'courses', 'enrollments', 'examinations', 'exam_courses', 'schedule_entries', 'seat_assignments', 'marks', 'calculated_results', 'recheck_requests', 'result_corrections', 'duty_requests', 'forecast_runs', 'notifications'] as $table) {
    if (Schema::hasTable($table)) {
        echo $table.': '.DB::table($table)->count().PHP_EOL;
    }
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$pages = 0;
foreach (['admin' => $courses->first()->schedule->seatingPlan->created_by, 'teacher' => M\TeacherAssignment::where('course_id', $courses->first()->course_id)->where('section_id', $courses->first()->section_id)->value('teacher_id'), 'student' => $courses->first()->eligible()->orderBy('student_id')->value('student_id')] as $role => $userId) {
    $user = M\User::findOrFail($userId);
    auth()->setUser($user);
    foreach ((new ScreenCatalog)->forRole($role) as $page) {
        if ($page['kind'] === 'auth') {
            continue;
        }
        $url = '/'.$role.'/'.$page['slug'];
        for ($hop = 0; $hop < 4; $hop++) {
            $request = Request::create($url, 'GET');
            $response = $kernel->handle($request);
            if (! $response->isRedirect()) {
                break;
            }
            $url = $response->headers->get('Location');
        }
        $pages++;
        if ($response->getStatusCode() !== 200) {
            $issues[] = $role.'/'.$page['slug'].': HTTP '.$response->getStatusCode();
        }
    }
}
echo 'Rendered screens: '.$pages.PHP_EOL;
echo 'Demo integrity / render issues: '.count($issues).PHP_EOL;
foreach ($issues as $issue) {
    echo $issue.PHP_EOL;
}
exit($issues ? 1 : 0);
