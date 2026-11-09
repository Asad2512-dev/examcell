<?php

namespace Tests\Feature;

use App\Http\Controllers\AcademicSetupController;
use App\Models as M;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Fixture;
use Tests\TestCase;

class AcademicSetupTest extends TestCase
{
    use Fixture, RefreshDatabase;

    public function test_setup_is_admin_only_and_all_steps_render(): void
    {
        $f = $this->fixture();
        $this->get('/admin/academic-setup')->assertRedirect('/sign-in');
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($f[$role])->get('/admin/academic-setup')->assertForbidden();
            $this->post('/admin/academic-setup/department', ['name' => 'Unauthorized', 'code' => 'NO'])->assertForbidden();
        }
        $this->actingAs($f['admin']);
        foreach (array_keys(AcademicSetupController::STEPS) as $step) {
            $this->get('/admin/academic-setup?step='.$step)->assertOk()->assertSee('Academic setup')->assertSee('Fall test');
        }
        $this->getJson('/admin/academic-setup?step=invalid')->assertUnprocessable()->assertJsonValidationErrors('step');
    }

    public function test_new_session_context_and_shared_definitions_are_preserved(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/admin/academic-setup/session', ['mode' => 'new', 'term' => 'Spring', 'year' => 2027, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30'])->assertRedirect('/admin/academic-setup?step=departments');
        $spring = M\AcademicSession::where('name', 'Spring 2027')->sole();
        $this->assertSame($spring->id, session('academic_context.session_id'));
        $this->get('/admin/academic-setup?step=departments')->assertSee($f['dep']->name);
        $this->get('/admin/academic-setup?step=courses')->assertSee($f['course']->name)->assertDontSee($f['section']->name);
        $this->assertDatabaseCount('departments', 1);
        $this->post('/admin/academic-setup/session', ['mode' => 'existing', 'session_id' => $f['session']->id])->assertRedirect();
        $this->get('/admin/academic-setup?step=students')->assertSee('Student One')->assertSee('TEST-0');
    }

    public function test_department_course_semester_and_section_creation_and_duplicates(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $this->post('/admin/academic-setup/department', ['name' => 'Engineering', 'code' => 'ENG'])->assertRedirect();
        $dep = M\Department::where('code', 'ENG')->sole();
        $this->post('/admin/academic-setup/course', ['department_id' => $dep->id, 'name' => 'Physics', 'code' => 'PHY101', 'credits' => 3])->assertRedirect();
        $this->post('/admin/academic-setup/semester', ['name' => 'Semester 1', 'number' => 1])->assertRedirect();
        $semester = M\Semester::where('name', 'Semester 1')->sole();
        $this->assertSame($f['session']->id, $semester->academic_session_id);
        $this->post('/admin/academic-setup/section', ['department_id' => $dep->id, 'semester_id' => $semester->id, 'name' => 'ENG 1-A'])->assertRedirect();
        $this->post('/admin/academic-setup/department', ['name' => 'Duplicate', 'code' => 'ENG'])->assertSessionHasErrors('code');
        $this->post('/admin/academic-setup/semester', ['name' => 'Semester 1', 'number' => 1])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_teacher_and_student_can_be_created_and_assigned_atomically(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        foreach (['teacher', 'student'] as $role) {
            $data = ['mode' => 'new', 'name' => 'New '.$role, 'email' => $role.'-new@example.test', 'password' => 'StrongPrivatePassword!', 'section_id' => $f['section']->id, 'course_ids' => [$f['course']->id], 'roll_number' => 'NEW-001'];
            $this->post('/admin/academic-setup/'.$role, $data)->assertSessionHasNoErrors()->assertRedirect();
            $user = M\User::where('email', $data['email'])->sole();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check($data['password'], $user->password));
            $this->assertDatabaseHas($role === 'teacher' ? 'teacher_assignments' : 'enrollments', [($role === 'teacher' ? 'teacher_id' : 'student_id') => $user->id, 'course_id' => $f['course']->id, 'section_id' => $f['section']->id]);
            $this->post('/admin/academic-setup/'.$role, ['mode' => 'existing', 'account_id' => $user->id, 'section_id' => $f['section']->id, 'course_ids' => [$f['course']->id]])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('teacher_assignments', 2);
        $this->assertDatabaseCount('enrollments', 3);
        $this->assertDatabaseCount('users', 8);
    }

    public function test_invalid_assignments_cannot_create_accounts_or_cross_sessions(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $spring = M\AcademicSession::create(['name' => 'Spring 2027', 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-01']);
        $semester = M\Semester::create(['academic_session_id' => $spring->id, 'name' => 'Other', 'number' => 1]);
        $section = M\Section::create(['semester_id' => $semester->id, 'department_id' => $f['dep']->id, 'name' => 'Other class']);
        $payload = ['mode' => 'new', 'name' => 'Should not save', 'email' => 'blocked@example.test', 'password' => 'StrongPrivatePassword!', 'section_id' => $section->id, 'course_ids' => [$f['course']->id]];
        $this->post('/admin/academic-setup/teacher', $payload)->assertSessionHasErrors('section_id');
        $department = M\Department::create(['name' => 'Other', 'code' => 'OTHER']);
        $course = M\Course::create(['department_id' => $department->id, 'name' => 'Other course', 'code' => 'OTHER1', 'credits' => 3]);
        $payload['section_id'] = $f['section']->id;
        $payload['course_ids'] = [$course->id];
        $this->post('/admin/academic-setup/teacher', $payload)->assertSessionHasErrors('course_ids');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->post('/admin/academic-setup/teacher', ['mode' => 'existing', 'account_id' => $f['student']->id, 'section_id' => $f['section']->id, 'course_ids' => [$f['course']->id]])->assertSessionHasErrors('account_id');
    }

    public function test_roll_number_failure_rolls_back_new_account_and_keeps_input(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->from('/admin/academic-setup?step=students')->post('/admin/academic-setup/student', ['mode' => 'new', 'name' => 'Duplicate roll student', 'email' => 'duplicate@example.test', 'password' => 'StrongPrivatePassword!', 'roll_number' => 'TEST-0', 'section_id' => $f['section']->id, 'course_ids' => [$f['course']->id]])->assertRedirect('/admin/academic-setup?step=students')->assertSessionHasErrors('record')->assertSessionHasInput('name', 'Duplicate roll student');
        $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.test']);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_approved_history_stays_locked_and_new_accounts_roll_back(): void
    {
        $f = $this->fixture();
        $f['exam']->update(['status' => 'approved']);
        $this->actingAs($f['admin'])->post('/admin/academic-setup/teacher', ['mode' => 'new', 'name' => 'Locked teacher', 'email' => 'locked@example.test', 'password' => 'StrongPrivatePassword!', 'section_id' => $f['section']->id, 'course_ids' => [$f['course']->id]])->assertSessionHasErrors('record');
        $this->assertDatabaseMissing('users', ['email' => 'locked@example.test']);
        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    public function test_review_uses_whole_session_and_finishing_does_not_publish(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post('/academic-context', ['session_id' => $f['session']->id, 'semester_id' => $f['semester']->id]);
        $this->get('/admin/academic-setup?step=review')->assertOk()->assertViewHas('progress', fn ($p) => ! in_array(false, $p, true));
        $this->post('/admin/academic-setup/finish')->assertRedirect('/admin/academic-setup?step=review');
        $this->assertSame('draft', $f['exam']->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academic_setup.reviewed']);
    }
}
