<?php

namespace Database\Seeders;

use App\Models as M;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates additive local demo data for visual QA. It never truncates or deletes records.
     */
    public function run(): void
    {
        if (! app()->environment('local') && ! filter_var(env('EXAMCELL_SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->error('Demo seeding is local-only. Set EXAMCELL_SEED_DEMO=true explicitly outside local development.');

            return;
        }

        DB::transaction(function (): void {
            $password = env('EXAMCELL_DEMO_PASSWORD', '12345678');
            $user = fn (string $name, string $email, string $role) => M\User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => $role, 'active' => true]);
            $admin = $user('ExamCell Administrator', 'admin@gmail.com', 'admin');
            $reviewer = $user('ExamCell Reviewer', 'reviewer.demo@gmail.com', 'admin');
            $teacher = $user('ExamCell Teacher', 'teacher@gmail.com', 'teacher');
            $reserve = $user('ExamCell Reserve Teacher', 'reserve.teacher@gmail.com', 'teacher');
            $teacherTwo = $user('ExamCell Course Teacher', 'course.teacher@gmail.com', 'teacher');
            $students = collect([
                $user('Ayesha Khan', 'student@gmail.com', 'student'),
                $user('Bilal Ahmed', 'student.two@gmail.com', 'student'),
                $user('Hira Malik', 'student.three@gmail.com', 'student'),
                $user('Usman Raza', 'student.four@gmail.com', 'student'),
            ]);

            $department = M\Department::firstOrCreate(['code' => 'IT'], ['name' => 'Information Technology', 'active' => true]);
            $session = M\AcademicSession::firstOrCreate(['name' => 'Fall 2026'], ['starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(5)->toDateString(), 'active' => true]);
            $semester = M\Semester::firstOrCreate(['academic_session_id' => $session->id, 'name' => 'Semester 5'], ['number' => 5]);
            $section = M\Section::firstOrCreate(['department_id' => $department->id, 'semester_id' => $semester->id, 'name' => 'BSIT 5-A'], ['active' => true]);
            $sectionB = M\Section::firstOrCreate(['department_id' => $department->id, 'semester_id' => $semester->id, 'name' => 'BSIT 5-B'], ['active' => true]);

            M\TeacherProfile::updateOrCreate(['teacher_id' => $teacher->id], ['department_id' => $department->id, 'reserve' => false, 'max_duties_per_day' => 2]);
            M\TeacherProfile::updateOrCreate(['teacher_id' => $teacherTwo->id], ['department_id' => $department->id, 'reserve' => false, 'max_duties_per_day' => 2]);
            M\TeacherProfile::updateOrCreate(['teacher_id' => $reserve->id], ['department_id' => $department->id, 'reserve' => true, 'max_duties_per_day' => 2]);

            foreach ($students as $index => $student) {
                M\StudentProfile::updateOrCreate(['user_id' => $student->id], ['section_id' => $index > 1 ? $sectionB->id : $section->id, 'roll_number' => 'BSIT-2026-0'.($index + 1), 'eligible' => true]);
            }
            $courses = collect([
                M\Course::firstOrCreate(['code' => 'CS301'], ['department_id' => $department->id, 'name' => 'Database Systems', 'credits' => 3, 'active' => true]),
                M\Course::firstOrCreate(['code' => 'CS302'], ['department_id' => $department->id, 'name' => 'Operating Systems', 'credits' => 3, 'active' => true]),
                M\Course::firstOrCreate(['code' => 'CS303'], ['department_id' => $department->id, 'name' => 'Web Engineering', 'credits' => 3, 'active' => true]),
            ]);
            foreach ($courses as $course) {
                foreach ($students as $student) {
                    $profile = $student->profile()->first();
                    M\Enrollment::updateOrCreate(['student_id' => $student->id, 'course_id' => $course->id, 'section_id' => $profile->section_id], ['eligible' => true]);
                }
            }
            foreach ($courses as $course) {
                M\TeacherAssignment::firstOrCreate(['teacher_id' => $course->id === $courses[1]->id ? $teacherTwo->id : $teacher->id, 'course_id' => $course->id, 'section_id' => $section->id]);
            }

            $scheme = M\GradingScheme::firstOrCreate(['name' => 'UG 2026 Approved Scheme'], ['status' => 'approved', 'rounding_places' => 2]);
            foreach ([[0, 'F', 0], [50, 'D', 1], [60, 'C', 2], [70, 'B', 3], [85, 'A', 4]] as [$minimum, $grade, $points]) {
                M\GradeBoundary::firstOrCreate(['grading_scheme_id' => $scheme->id, 'minimum' => $minimum], ['grade' => $grade, 'grade_points' => $points]);
            }
            $exam = M\Examination::firstOrCreate(['name' => 'Fall 2026 Final Examinations'], ['type' => 'final', 'academic_session_id' => $session->id, 'grading_scheme_id' => $scheme->id, 'status' => 'published', 'admit_enabled' => true]);
            $rooms = collect([
                M\Room::firstOrCreate(['name' => 'Examination Hall A'], ['capacity' => 30, 'active' => true]),
                M\Room::firstOrCreate(['name' => 'Examination Hall B'], ['capacity' => 30, 'active' => true]),
                M\Room::firstOrCreate(['name' => 'Computer Lab 1'], ['capacity' => 24, 'active' => true]),
            ]);
            $base = now()->startOfDay()->addDays(14);
            foreach ($rooms as $room) {
                M\RoomUnavailability::firstOrCreate(['room_id' => $room->id, 'starts_at' => $base->copy()->addDays(20), 'ends_at' => $base->copy()->addDays(20)->addHours(3)], ['reason' => 'Scheduled maintenance']);
            }

            foreach ($courses as $index => $course) {
                $ec = M\ExamCourse::firstOrCreate(['examination_id' => $exam->id, 'course_id' => $course->id, 'section_id' => $section->id], ['duration_minutes' => 120, 'maximum' => 100, 'passing' => 50]);
                $component = M\AssessmentComponent::firstOrCreate(['exam_course_id' => $ec->id, 'name' => 'Final assessment'], ['maximum' => 100, 'weight' => 100]);
                $room = $rooms[$index];
                $start = $base->copy()->addDays($index * 2)->addHours(9);
                $entry = M\ScheduleEntry::updateOrCreate(['exam_course_id' => $ec->id], ['room_id' => $room->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(120)]);
                $entry->rooms()->syncWithoutDetaching([$room->id]);
                $plan = M\SeatingPlan::firstOrCreate(['schedule_entry_id' => $entry->id], ['created_by' => $admin->id]);
                foreach ($students as $seat => $student) {
                    M\SeatAssignment::updateOrCreate(['seating_plan_id' => $plan->id, 'student_id' => $student->id], ['room_id' => $room->id, 'seat_number' => $seat + 1]);
                    M\AdmitCard::firstOrCreate(['examination_id' => $exam->id, 'student_id' => $student->id], ['reference' => (string) Str::uuid(), 'path' => 'demo/admit-cards/'.$student->id.'.pdf']);
                    $score = 72 + (($seat + $index * 4) % 22);
                    M\Mark::updateOrCreate(['assessment_component_id' => $component->id, 'student_id' => $student->id], ['score' => $score, 'absent' => false, 'entered_by' => $teacher->id]);
                }
                M\DutyAssignment::firstOrCreate(['schedule_entry_id' => $entry->id, 'teacher_id' => $index === 1 ? $teacherTwo->id : $teacher->id], ['room_id' => $room->id, 'reserve' => false, 'status' => 'assigned']);
                M\DutyAssignment::firstOrCreate(['schedule_entry_id' => $entry->id, 'teacher_id' => $reserve->id], ['room_id' => $room->id, 'reserve' => true, 'status' => 'assigned']);
                M\MarkSubmission::updateOrCreate(['exam_course_id' => $ec->id], ['teacher_id' => $teacher->id, 'status' => 'submitted', 'return_reason' => null]);
            }

            $batch = M\ResultBatch::updateOrCreate(['examination_id' => $exam->id], ['status' => 'published', 'approved_by' => $admin->id, 'published_at' => now()]);
            foreach ($exam->courses()->with('course', 'components')->get() as $ec) {
                foreach ($students as $student) {
                    $score = (float) M\Mark::where('assessment_component_id', $ec->components->first()->id)->where('student_id', $student->id)->value('score');
                    $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : 'C');
                    $points = $grade === 'A' ? 4 : ($grade === 'B' ? 3 : 2);
                    M\CalculatedResult::updateOrCreate(['exam_course_id' => $ec->id, 'student_id' => $student->id], ['result_batch_id' => $batch->id, 'percentage' => $score, 'passed' => $score >= 50, 'grade' => $grade, 'grade_points' => $points, 'credits' => $ec->course->credits, 'quality_points' => $points * $ec->course->credits, 'absent' => false]);
                }
            }

            $firstMark = M\Mark::query()->first();
            if ($firstMark) {
                M\ResultCorrection::firstOrCreate(['mark_id' => $firstMark->id, 'status' => 'pending'], ['old_score' => $firstMark->score, 'new_score' => min(100, (float) $firstMark->score + 1), 'old_absent' => false, 'new_absent' => false, 'reason' => 'Department review requested for demonstration workflow.', 'requested_by' => $admin->id]);
            }
            $firstResult = M\CalculatedResult::query()->first();
            if ($firstResult) {
                M\RecheckRequest::firstOrCreate(['calculated_result_id' => $firstResult->id, 'student_id' => $firstResult->student_id], ['reason' => 'Please review the published assessment record.', 'status' => 'pending']);
            }
            M\AiAnalysisRun::firstOrCreate(['examination_id' => $exam->id, 'requested_by' => $admin->id], ['status' => 'completed', 'summary' => ['source' => 'authorized published results', 'records' => $batch->results()->count(), 'note' => 'Reviewable analysis only.']]);
            M\AnalysisFlag::firstOrCreate(['record_key' => 'demo-missing-result-'.$exam->id], ['examination_id' => $exam->id, 'student_id' => $students->first()->id, 'type' => 'review_signal', 'details' => ['message' => 'Reviewable academic pattern signal.'], 'status' => 'open']);
            $conversation = M\Conversation::firstOrCreate(['user_id' => $admin->id, 'title' => 'Examination analysis']);
            M\Message::firstOrCreate(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Summarize the current examination workflow.'], ['input_tokens' => 0, 'output_tokens' => 0, 'provider_status' => 'local']);
            M\Message::firstOrCreate(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'The workspace contains scheduled examinations, seating, duties, marks, and published results for review.'], ['input_tokens' => 0, 'output_tokens' => 0, 'provider_status' => 'local']);
            M\ForecastRun::firstOrCreate(['requested_by' => $student = $students->first()->id, 'student_id' => $student, 'course_id' => $courses->first()->id], ['evidence' => ['status' => 'illustrative local record', 'sample_size' => 1]]);
            M\BackupRun::firstOrCreate(['requested_by' => $admin->id, 'status' => 'verified'], ['path' => null, 'checksum' => null, 'bytes' => 0, 'verified_at' => now()]);
            M\TeacherAvailability::firstOrCreate(['teacher_id' => $teacher->id, 'starts_at' => $base->copy()->addDays(9), 'ends_at' => $base->copy()->addDays(9)->addHours(3)], ['available' => false, 'reason' => 'Department availability record for review.']);
            $primaryDuty = M\DutyAssignment::query()->where('reserve', false)->first();
            $dutyRequest = M\DutyRequest::firstOrCreate(['duty_assignment_id' => $primaryDuty->id, 'teacher_id' => $teacher->id], ['status' => 'pending', 'reason' => 'Request reserve cover for a scheduled paper.', 'requested_at' => now(), 'deadline_at' => $base->copy()->addDays(12)]);
            M\DutyReplacement::firstOrCreate(['duty_request_id' => $dutyRequest->id], ['duty_assignment_id' => $primaryDuty->id, 'candidate_id' => $reserve->id, 'status' => 'proposed', 'reason' => 'Reserve teacher proposed for review.']);
            DB::table('notifications')->updateOrInsert(['id' => (string) Str::uuid()], ['type' => 'App\\Notifications\\WorkflowNotice', 'notifiable_type' => M\User::class, 'notifiable_id' => $admin->id, 'data' => json_encode(['title' => 'Demo workflow ready', 'message' => 'Local review data is available across the workspace.', 'url' => '/admin/admin-dashboard']), 'read_at' => null, 'created_at' => now(), 'updated_at' => now()]);
            M\AuditLog::create(['actor_id' => $admin->id, 'action' => 'demo.seeded', 'entity_type' => 'ExamCellDemo', 'entity_id' => $exam->id, 'changes' => ['note' => 'Local-only additive demo dataset created.']]);
        });

        $this->command?->info('Local demo dataset seeded across the ExamCell workflow tables. Existing records were preserved.');
    }
}
