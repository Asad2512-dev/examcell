<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services\ExamCycle;
use App\Services\SchedulingService;
use App\Services\Settings;
use App\Services\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Fixture;
use Tests\TestCase;

class GuidedFlowTest extends TestCase
{
    use Fixture, RefreshDatabase;

    private function csv(string $content, string $name = 'people.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function steps(): array
    {
        return collect(app(ExamCycle::class)->overview()['steps'])->keyBy('key')->all();
    }

    public function test_dashboard_tells_the_admin_what_to_do_next(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get('/admin/admin-dashboard')->assertOk()->assertSee('YOUR NEXT STEP', false)->assertSee('Date sheet');
        $overview = app(ExamCycle::class)->overview();
        $this->assertSame('datesheet', $overview['next']['key']);
        $steps = $this->steps();
        $this->assertSame('done', $steps['people']['status']);
        $this->assertSame('done', $steps['rooms']['status']);
        $this->assertSame('done', $steps['exam']['status']);
        $this->assertSame('todo', $steps['university']['status'], 'university is recommended but never blocks existing data');
    }

    public function test_cycle_moves_forward_as_work_is_finished(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->get('/admin/admin-dashboard')->assertOk()->assertSee('YOUR NEXT STEP · 8 OF 10', false);
        $steps = $this->steps();
        foreach (['datesheet', 'seating', 'publish'] as $key) {
            $this->assertSame('done', $steps[$key]['status'], $key);
        }
        $this->assertSame('admit', app(ExamCycle::class)->overview()['next']['key']);
    }

    public function test_readiness_names_the_problem_and_links_to_the_fix(): void
    {
        $f = $this->fixture();
        M\AssessmentComponent::where('exam_course_id', $f['ec']->id)->update(['weight' => 60]);
        $items = collect(app(ExamCycle::class)->readiness($f['exam']->fresh()));
        $this->assertTrue($items->contains(fn ($i) => str_contains($i['text'], 'adds up to 60%')));
        $this->assertTrue($items->contains(fn ($i) => str_contains($i['text'], 'no date and room')));
    }

    public function test_university_profile_is_admin_only_and_changes_the_rules(): void
    {
        $f = $this->fixture();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->get('/admin/university')->assertForbidden();
            $this->post('/admin/university', [])->assertForbidden();
        }
        $this->actingAs($f['admin'])->get('/admin/university')->assertOk()->assertSee('Your university');
        $this->post('/admin/university', ['name' => 'Riverdale University', 'short_name' => 'Riverdale', 'timezone' => 'Europe/London', 'terms' => 'Trimester 1, Trimester 2, Trimester 3', 'slots' => ['08:30', '12:00', '', ''], 'max_grade_point' => 10, 'max_duties_per_day' => 3])
            ->assertRedirect('/admin/admin-dashboard');
        $this->assertSame('Riverdale', University::shortName());
        $this->assertSame(['Trimester 1', 'Trimester 2', 'Trimester 3'], University::terms());
        $this->assertSame(['08:30', '12:00'], University::slots());
        $this->assertSame(10.0, University::maxGradePoint());
        $this->assertSame('done', $this->steps()['university']['status']);
        $this->get('/admin/university')->assertSee('Riverdale University')->assertSee('Trimester 2');
    }

    public function test_university_profile_rejects_bad_values(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/admin/university', ['name' => '', 'timezone' => 'Mars/Base', 'terms' => ' , ', 'slots' => ['9am'], 'max_grade_point' => 0, 'max_duties_per_day' => 99])
            ->assertSessionHasErrors(['name', 'timezone', 'slots.0', 'max_grade_point', 'max_duties_per_day']);
        $this->assertNull(Settings::get('university.name'));
    }

    public function test_terms_from_the_profile_drive_new_sessions(): void
    {
        $f = $this->fixture();
        Settings::set('university.terms', ['Trimester 1', 'Trimester 2']);
        $this->actingAs($f['admin'])->post('/admin/academic-setup/session', ['mode' => 'new', 'term' => 'Fall', 'year' => 2027, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30'])->assertSessionHasErrors('term');
        $this->post('/admin/academic-setup/session', ['mode' => 'new', 'term' => 'Trimester 2', 'year' => 2027, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30'])->assertRedirect();
        $this->assertDatabaseHas('academic_sessions', ['name' => 'Trimester 2 2027']);
    }

    public function test_date_sheet_uses_the_configured_exam_times(): void
    {
        $f = $this->fixture();
        Settings::set('university.slots', ['10:30']);
        $this->actingAs($f['admin']);
        $entries = app(SchedulingService::class)->generate($f['exam'], '2026-10-14', 3);
        $this->assertSame('10:30', $entries[0]->starts_at->format('H:i'));
    }

    public function test_exam_wizard_creates_papers_and_breakdowns_in_one_save(): void
    {
        $f = $this->fixture();
        $course2 = M\Course::create(['department_id' => $f['dep']->id, 'code' => 'CS302', 'name' => 'Networks', 'credits' => '3.00']);
        M\TeacherAssignment::create(['teacher_id' => $f['teacher']->id, 'course_id' => $course2->id, 'section_id' => $f['section']->id]);
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->get('/admin/exams/new')->assertForbidden();
            $this->post('/admin/exams/new', [])->assertForbidden();
        }
        $this->actingAs($f['admin'])->get('/admin/exams/new')->assertOk()->assertSee('BSIT 5-A')->assertSee('CS302');
        $post = ['name' => 'Midterm 2026', 'type' => 'midterm', 'grading_scheme_id' => $f['scheme']->id, 'papers' => [$f['section']->id.':'.$f['course']->id, $f['section']->id.':'.$course2->id], 'duration_minutes' => 90, 'maximum' => 50, 'pass_percent' => 40, 'preset' => 'coursework'];
        $this->post('/admin/exams/new', $post)->assertRedirect();
        $exam = M\Examination::where('name', 'Midterm 2026')->sole();
        $this->assertSame('draft', $exam->status);
        $this->assertSame(2, $exam->courses()->count());
        $paper = $exam->courses()->where('course_id', $course2->id)->sole();
        $this->assertSame('20.00', $paper->passing);
        $this->assertSame(['Coursework' => '30.00', 'Written exam' => '70.00'], $paper->components()->pluck('weight', 'name')->all());
        $this->assertEquals(15, $paper->components()->where('name', 'Coursework')->value('maximum'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'examination.wizard']);
    }

    public function test_exam_wizard_can_add_papers_later_and_skips_existing_ones(): void
    {
        $f = $this->fixture();
        $course2 = M\Course::create(['department_id' => $f['dep']->id, 'code' => 'CS302', 'name' => 'Networks', 'credits' => '3.00']);
        M\TeacherAssignment::create(['teacher_id' => $f['teacher']->id, 'course_id' => $course2->id, 'section_id' => $f['section']->id]);
        $this->actingAs($f['admin'])->get('/admin/exams/new?exam='.$f['exam']->id)->assertOk()->assertSee('Already in this examination');
        $this->post('/admin/exams/new', ['exam_id' => $f['exam']->id, 'papers' => [$f['section']->id.':'.$f['course']->id, $f['section']->id.':'.$course2->id], 'duration_minutes' => 60, 'maximum' => 100, 'pass_percent' => 50, 'preset' => 'single'])->assertRedirect();
        $this->assertSame(2, $f['exam']->courses()->count());
        $this->assertSame(1, $f['exam']->courses()->where('course_id', $f['course']->id)->count());
    }

    public function test_exam_wizard_rejects_mismatched_papers_and_published_exams(): void
    {
        $f = $this->fixture();
        $other = M\Department::create(['name' => 'Law', 'code' => 'LAW']);
        $foreign = M\Course::create(['department_id' => $other->id, 'code' => 'LW101', 'name' => 'Contracts', 'credits' => '3.00']);
        $base = ['name' => 'Bad', 'type' => 'final', 'grading_scheme_id' => $f['scheme']->id, 'duration_minutes' => 60, 'maximum' => 100, 'pass_percent' => 40, 'preset' => 'single'];
        $this->actingAs($f['admin'])->post('/admin/exams/new', $base + ['papers' => [$f['section']->id.':'.$foreign->id]])->assertSessionHasErrors('papers');
        $this->post('/admin/exams/new', $base + ['papers' => []])->assertSessionHasErrors('papers');
        $this->assertDatabaseMissing('examinations', ['name' => 'Bad']);
        $this->publish($f);
        $this->post('/admin/exams/new', ['exam_id' => $f['exam']->id, 'papers' => [$f['section']->id.':'.$f['course']->id], 'duration_minutes' => 60, 'maximum' => 100, 'pass_percent' => 40, 'preset' => 'single'])->assertNotFound();
    }

    public function test_import_is_admin_only_and_template_downloads(): void
    {
        $f = $this->fixture();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->get('/admin/import')->assertForbidden();
            $this->post('/admin/import/preview', [])->assertForbidden();
            $this->post('/admin/import/commit', [])->assertForbidden();
        }
        $this->actingAs($f['admin'])->get('/admin/import?kind=teachers')->assertOk()->assertSee('Add teachers from a spreadsheet');
        $response = $this->get('/admin/import/template/students')->assertOk();
        $this->assertStringContainsString('Name,Email,"Roll number",Class', $response->getContent());
        $this->assertStringContainsString('BSIT 5-A', $response->getContent());
        $this->get('/admin/import/template/departments?format=xlsx')->assertOk()->assertDownload('departments-template.xlsx');
        $this->get('/admin/import/export/teachers?format=xlsx')->assertOk()->assertDownload('teachers-export.xlsx');
    }

    public function test_departments_and_sessions_can_be_checked_and_imported(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);

        $html = $this->post('/admin/import/preview', ['kind' => 'departments', 'file' => $this->csv("Name,Code,Active\nSoftware Engineering,SE,Yes\nDuplicate,IT,Yes\n")])->assertOk()->getContent();
        $this->assertStringContainsString('1 ready to add', $html);
        $this->assertStringContainsString('code already exists', $html);
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $html, $match);
        $this->post('/admin/import/commit', ['token' => $match[1]])->assertOk()->assertSee('Done. 1 departments added');
        $this->assertDatabaseHas('departments', ['name' => 'Software Engineering', 'code' => 'SE']);

        $html = $this->post('/admin/import/preview', ['kind' => 'sessions', 'file' => $this->csv("Name,Start date,End date,Active\nSummer 2027,2027-06-01,2027-08-31,No\n")])->assertOk()->getContent();
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $html, $match);
        $this->post('/admin/import/commit', ['token' => $match[1]])->assertOk();
        $this->assertDatabaseHas('academic_sessions', ['name' => 'Summer 2027', 'active' => false]);
    }

    public function test_student_import_checks_every_row_and_adds_only_the_good_ones(): void
    {
        $f = $this->fixture();
        $file = "Student Name;E-mail;Roll No;Section\n"
            ."New One;new1@example.edu;N-1;bsit 5-a\n"
            ."Dup Email;one@example.test;N-2;BSIT 5-A\n"
            ."Bad Class;new3@example.edu;N-3;Nowhere\n"
            ."Dup Roll;new4@example.edu;TEST-0;BSIT 5-A\n"
            ."New Five;new5@example.edu;N-5;BSIT 5-A\n";
        $this->actingAs($f['admin']);
        $html = $this->post('/admin/import/preview', ['kind' => 'students', 'file' => $this->csv($file)])->assertOk()->getContent();
        $this->assertStringContainsString('2 ready to add', $html);
        $this->assertStringContainsString('3 with a problem', $html);
        $this->assertStringContainsString('already has an account', $html);
        $this->assertStringContainsString('could not find a class called', $html);
        $this->assertStringContainsString('roll number already exists', $html);
        $this->assertDatabaseMissing('users', ['email' => 'new1@example.edu']);
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $html, $m);
        $this->post('/admin/import/commit', ['token' => $m[1]])->assertOk()->assertSee('Done. 2 students added');
        $new = M\User::where('email', 'new1@example.edu')->sole();
        $this->assertSame('student', $new->role);
        $this->assertSame('N-1', $new->profile->roll_number);
        $this->assertSame([$f['course']->id], $new->enrollments()->pluck('course_id')->all(), 'enrolled in the class courses');
        $this->assertDatabaseMissing('users', ['email' => 'new3@example.edu']);
        $this->assertSame('Student One', M\User::where('email', 'one@example.test')->value('name'), 'existing accounts are never changed');
        $this->assertFalse(Hash::check('anything', $new->password), 'no guessable password is created');
        $this->post('/admin/import/commit', ['token' => $m[1]])->assertSessionHasErrors('file');
    }

    public function test_import_can_hand_out_temporary_passwords(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $html = $this->post('/admin/import/preview', ['kind' => 'students', 'file' => $this->csv("Name,Email,Roll number,Class\nTemp Kid,temp@example.edu,T-1,BSIT 5-A\n")])->getContent();
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $html, $m);
        $out = $this->post('/admin/import/commit', ['token' => $m[1], 'passwords' => 1])->assertOk()->getContent();
        $this->assertStringContainsString('Save these sign-in details now', $out);
        preg_match('#<code>([A-Za-z0-9]{12})</code>#', $out, $pw);
        $this->assertTrue(Hash::check($pw[1], M\User::where('email', 'temp@example.edu')->value('password')));
    }

    public function test_teacher_import_assigns_courses_in_the_right_class(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $file = "Name,Email,Department,Class,Courses\nDr New,drnew@example.edu,IT,BSIT 5-A,cs301\nDr Bad,drbad@example.edu,IT,BSIT 5-A,ZZ999\nDr Plain,drplain@example.edu,,,\n";
        $html = $this->post('/admin/import/preview', ['kind' => 'teachers', 'file' => $this->csv($file)])->getContent();
        $this->assertStringContainsString('2 ready to add', $html);
        $this->assertStringContainsString('Unknown course', $html);
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $html, $m);
        $this->post('/admin/import/commit', ['token' => $m[1]])->assertOk();
        $teacher = M\User::where('email', 'drnew@example.edu')->sole();
        $this->assertSame('teacher', $teacher->role);
        $this->assertSame([$f['course']->id], $teacher->assignments()->pluck('course_id')->all());
        $this->assertSame($f['dep']->id, $teacher->teacherProfile->department_id);
        $this->assertNotNull(M\User::where('email', 'drplain@example.edu')->first()?->teacherProfile);
    }

    public function test_import_explains_a_file_with_missing_columns(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/admin/import/preview', ['kind' => 'students', 'file' => $this->csv("Name,Phone\nA,1\n")])->assertSessionHasErrors('file');
        $this->post('/admin/import/preview', ['kind' => 'students', 'file' => $this->csv("Name,Email,Roll number,Class\n")])->assertSessionHasErrors('file');
        $this->post('/admin/import/preview', ['kind' => 'students'])->assertSessionHasErrors('file');
    }

    public function test_import_blocks_enrolling_into_a_class_with_an_approved_exam(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $html = $this->post('/admin/import/preview', ['kind' => 'students', 'file' => $this->csv("Name,Email,Roll number,Class\nLate,late@example.edu,L-1,BSIT 5-A\n")])->getContent();
        $this->assertStringContainsString('approved examination', $html);
        $this->assertStringContainsString('0 ready to add', $html);
    }

    public function test_settings_are_cached_in_one_query_and_refresh_after_a_change(): void
    {
        $this->fixture();
        Settings::set('probe.key', 'one');
        \DB::enableQueryLog();
        Settings::get('probe.key');
        Settings::get('probe.other');
        Settings::get('probe.third');
        $this->assertLessThanOrEqual(1, count(\DB::getQueryLog()));
        Settings::set('probe.key', 'two');
        $this->assertSame('two', Settings::get('probe.key'));
    }
}
