<?php

namespace Database\Seeders;

use App\Models as M;
use App\Services as S;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Isolated, additive visual-review dataset. Never updates existing accounts. */
class VisualDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Visual demo data is restricted to local/testing environments.');
        }
        if (M\AcademicSession::where('name', 'DEMO Visual Review 2026-27')->exists()) {
            $this->command?->info('Visual dataset already exists; existing records and passwords retained.');

            return;
        }
        $password = env('EXAMCELL_DEMO_PASSWORD', 'DemoReview2026!');
        $hash = Hash::make($password);
        $account = function ($name, $email, $role) use ($hash) {
            if (M\User::where('email', $email)->exists()) {
                throw new \RuntimeException('Demo email already exists: '.$email.'; no existing account will be modified.');
            }

            return M\User::create(['name' => $name, 'email' => $email, 'role' => $role, 'password' => $hash, 'active' => true]);
        };
        DB::transaction(function () use ($account) {
            $admin = $account('Demo Administrator', 'admin@demo.examcell.test', 'admin');
            $account('Demo Independent Reviewer', 'reviewer@demo.examcell.test', 'admin');
            auth()->setUser($admin);
            $session = M\AcademicSession::create(['name' => 'DEMO Visual Review 2026-27', 'starts_on' => now()->subYear()->toDateString(), 'ends_on' => now()->addYear()->toDateString()]);
            $semester = M\Semester::create(['academic_session_id' => $session->id, 'name' => 'Demo Semester 5', 'number' => 5]);
            $scheme = M\GradingScheme::create(['name' => 'DEMO Illustrative Grading', 'status' => 'approved', 'rounding_places' => 2]);
            foreach ([[0, 'F', 0], [50, 'D', 1], [60, 'C', 2], [70, 'B', 3], [85, 'A', 4]] as [$minimum, $grade, $points]) {
                $scheme->boundaries()->create(['minimum' => $minimum, 'grade' => $grade, 'grade_points' => $points]);
            }
            $groups = [];
            $firstNames = ['Ayesha', 'Bilal', 'Hira', 'Usman', 'Fatima', 'Ahmed', 'Zainab', 'Hassan', 'Maryam', 'Ali', 'Iqra', 'Hamza'];
            $lastNames = ['Khan', 'Ahmed', 'Malik', 'Raza', 'Shah', 'Iqbal', 'Siddiqui', 'Akhtar', 'Butt', 'Farooq'];
            foreach (['CS' => ['Computer Science', 'Database Systems', 'Operating Systems', 'Software Engineering'], 'EE' => ['Electrical Engineering', 'Circuit Analysis', 'Digital Logic', 'Control Systems'], 'BA' => ['Business Administration', 'Financial Accounting', 'Marketing Principles', 'Business Statistics']] as $code => $names) {
                $dep = M\Department::create(['code' => 'DEMO-'.$code, 'name' => 'Demo '.$names[0]]);
                $teachers = collect();
                for ($t = 0; $t < 5; $t++) {
                    $teacher = $account('Demo '.$code.' Faculty '.($t + 1), strtolower($code).'.teacher'.($t + 1).'@demo.examcell.test', 'teacher');
                    M\TeacherProfile::create(['teacher_id' => $teacher->id, 'department_id' => $dep->id, 'reserve' => $t >= 3, 'max_duties_per_day' => 3]);
                    $teachers->push($teacher);
                }
                $courses = collect();
                for ($c = 1; $c <= 3; $c++) {
                    $courses->push(M\Course::create(['department_id' => $dep->id, 'code' => 'DEMO-'.$code.'30'.$c, 'name' => $names[$c], 'credits' => $c === 3 ? 4 : 3]));
                }
                foreach (['A', 'B'] as $letter) {
                    $section = M\Section::create(['department_id' => $dep->id, 'semester_id' => $semester->id, 'name' => 'DEMO '.$code.' 5-'.$letter]);
                    $room = M\Room::create(['name' => 'DEMO '.$code.' Hall '.$letter, 'capacity' => 48, 'seat_columns' => 6, 'seat_rows' => 8, 'aisles' => [3], 'door_side' => 'right']);
                    $students = collect();
                    for ($n = 1; $n <= 40; $n++) {
                        $student = $account($firstNames[($n - 1) % 12].' '.$lastNames[intdiv($n - 1, 12)].' ('.$code.$letter.sprintf('%02d', $n).')', strtolower($code.$letter).'.student'.$n.'@demo.examcell.test', 'student');
                        M\StudentProfile::create(['user_id' => $student->id, 'section_id' => $section->id, 'roll_number' => 'DEMO-'.$code.'-'.$letter.'-'.sprintf('%03d', $n), 'eligible' => true]);
                        foreach ($courses as $course) {
                            M\Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'section_id' => $section->id]);
                        }
                        $students->push($student);
                    }
                    foreach ($courses as $i => $course) {
                        M\TeacherAssignment::create(['teacher_id' => $teachers[$i]->id, 'course_id' => $course->id, 'section_id' => $section->id]);
                    }
                    $groups[] = compact('section', 'room', 'students', 'courses', 'teachers');
                }
            }
            foreach ([['Previous Finals', -120, 'published', 'published'], ['Recent Finals', -60, 'published', 'published'], ['Results Under Review', -25, 'published', 'under_review'], ['Upcoming Finals', 14, 'published', null], ['Draft Midterms', 60, 'draft', null]] as $round => [$name, $offset, $status, $resultStatus]) {
                $exam = M\Examination::create(['name' => 'DEMO '.$name, 'type' => $round === 4 ? 'midterm' : 'final', 'academic_session_id' => $session->id, 'grading_scheme_id' => $scheme->id, 'admit_enabled' => true]);
                foreach ($groups as $g => $group) {
                    extract($group);
                    foreach ($courses as $i => $course) {
                        $ec = M\ExamCourse::create(['examination_id' => $exam->id, 'course_id' => $course->id, 'section_id' => $section->id, 'duration_minutes' => 120, 'maximum' => 100, 'passing' => 50, 'marks_due_at' => now()->startOfDay()->addDays($offset + 10)]);
                        $components = collect();
                        foreach ([['Written paper', 70], ['Practical / coursework', 30]] as [$label, $weight]) {
                            $components->push(M\AssessmentComponent::create(['exam_course_id' => $ec->id, 'name' => $label, 'maximum' => $weight, 'weight' => $weight]));
                        }
                        $start = now()->startOfDay()->addDays($offset + $i * 2 + ($g % 2))->setHour(9);
                        $entry = app(S\SchedulingService::class)->schedule($ec, $room->id, $start->toDateTimeString());
                        app(S\SeatingService::class)->allocate($entry, $admin->id);
                        if ($start->isPast()) {
                            M\DutyAssignment::create(['schedule_entry_id' => $entry->id, 'teacher_id' => $teachers[$i]->id, 'room_id' => $room->id, 'reserve' => false, 'status' => 'assigned']);
                        } else {
                            app(S\DutyService::class)->assign($entry, $teachers[$i]->id);
                        }
                        if ($resultStatus || $round === 4) {
                            foreach ($students as $n => $student) {
                                foreach ($components as $component) {
                                    if ($round === 4 && $n % 4 === 0) {
                                        continue;
                                    }
                                    $absent = ($n + $i + $round) % 29 === 0;
                                    $percent = 32 + (($n * 7 + $i * 11 + $round * 9) % 67);
                                    M\Mark::create(['assessment_component_id' => $component->id, 'student_id' => $student->id, 'score' => $absent ? null : round($percent * $component->maximum / 100, 2), 'absent' => $absent, 'entered_by' => $teachers[$i]->id]);
                                }
                            }
                            M\MarkSubmission::create(['exam_course_id' => $ec->id, 'teacher_id' => $teachers[$i]->id, 'status' => $round === 4 ? ($i === 1 ? 'returned' : 'draft') : 'submitted', 'return_reason' => $round === 4 && $i === 1 ? 'DEMO: complete missing practical marks before resubmitting.' : null]);
                        }
                    }
                }
                if ($status === 'published') {
                    app(S\SchedulingService::class)->transition($exam, 'approved');
                    app(S\SchedulingService::class)->transition($exam->fresh(), 'published');
                }
                if ($resultStatus) {
                    $batch = app(S\ResultsService::class)->calculate($exam->fresh());
                    foreach (['submitted', 'under_review', 'approved', 'published'] as $step) {
                        app(S\ResultsService::class)->transition($batch->fresh(), $step, $admin->id);
                        if ($step === $resultStatus) {
                            break;
                        }
                    }
                    if ($resultStatus === 'published') {
                        foreach ($batch->results()->orderBy('id')->limit(8)->get() as $result) {
                            app(S\ResultsService::class)->recheck($result, $result->student_id, 'DEMO: please review the written assessment total.');
                        }
                    }
                }
            }
            $published = M\Examination::where('academic_session_id', $session->id)->where('name', 'DEMO Recent Finals')->firstOrFail();
            foreach ($published->courses()->limit(6)->get() as $ec) {
                $mark = M\Mark::whereIn('assessment_component_id', $ec->components()->pluck('id'))->where('absent', false)->firstOrFail();
                app(S\ResultsService::class)->requestCorrection($mark, (string) min((float) $mark->component->maximum, (float) $mark->score + 1), false, 'DEMO: department requested verification of the total.', $admin->id);
            }
            foreach ($groups as $group) {
                foreach ($group['students']->take(5) as $student) {
                    app(S\ForecastService::class)->forecast($admin, $student->id, $group['courses']->first()->id);
                }
                M\TeacherAvailability::create(['teacher_id' => $group['teachers']->last()->id, 'starts_at' => now()->addDays(40)->startOfDay(), 'ends_at' => now()->addDays(41)->startOfDay(), 'available' => false, 'reason' => 'DEMO: faculty development workshop.']);
                M\RoomUnavailability::create(['room_id' => $group['room']->id, 'starts_at' => now()->addDays(45)->startOfDay(), 'ends_at' => now()->addDays(46)->startOfDay(), 'reason' => 'DEMO: scheduled maintenance.']);
            }
            $upcoming = M\Examination::where('academic_session_id', $session->id)->where('name', 'DEMO Upcoming Finals')->firstOrFail();
            foreach ($upcoming->courses()->with('schedule.duties')->get()->take(6) as $ec) {
                $duty = $ec->schedule->duties->first();
                // The normal service creates proposals and in-app notifications; no email is sent.
                app(S\DutyService::class)->request($duty, $duty->teacher_id, 'DEMO: requesting reserve cover for a departmental commitment.');
            }
            foreach ([$admin, $groups[0]['teachers']->first(), $groups[0]['students']->first()] as $actor) {
                $conversation = M\Conversation::create(['user_id' => $actor->id, 'title' => 'DEMO Workspace orientation']);
                $conversation->messages()->create(['role' => 'user', 'content' => 'Where can I review my examination information?', 'provider_status' => 'local']);
                $conversation->messages()->create(['role' => 'assistant', 'content' => 'Use the schedule, seating, duties and results pages to review this illustrative local dataset.', 'provider_status' => 'local']);
            }
            M\AuditLog::create(['actor_id' => $admin->id, 'action' => 'demo.visual_seeded', 'entity_type' => M\AcademicSession::class, 'entity_id' => $session->id, 'changes' => ['students' => 240, 'note' => 'Isolated visual-review data; not official academic records.']]);
        });
        $this->command?->info('Created visual demo: 240 students, 15 teachers, 6 sections, 9 courses, 5 examinations.');
    }
}
