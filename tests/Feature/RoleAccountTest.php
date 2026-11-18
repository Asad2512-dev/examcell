<?php

namespace Tests\Feature;

use App\Models as M;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\Fixture;
use Tests\TestCase;

class RoleAccountTest extends TestCase
{
    use Fixture, RefreshDatabase;

    private function payload(array $f, string $role): array
    {
        return ['name' => 'New '.$role, 'email' => 'new-'.$role.'@example.test', 'password' => 'PrivateInitialPassword!', 'password_confirmation' => 'PrivateInitialPassword!', 'active' => 1,
            ...($role === 'teacher' ? ['department_id' => $f['dep']->id] : ['section_id' => $f['section']->id, 'roll_number' => 'NEW-001', 'course_ids' => [$f['course']->id]])];
    }

    public function test_directories_are_separate_and_admin_only(): void
    {
        $f = $this->fixture();
        foreach (['teachers', 'students'] as $directory) {
            $this->get('/admin/'.$directory)->assertRedirect('/sign-in');
            foreach (['student', 'teacher'] as $role) {
                $this->actingAs($f[$role])->get('/admin/'.$directory)->assertForbidden();
                $this->post('/admin/'.$directory, [])->assertForbidden();
                $this->post('/admin/'.$directory.'/'.$f['other']->id.'/password', [])->assertForbidden();
            }
            $this->actingAs($f['admin'])->get('/admin/'.$directory)->assertOk()->assertSee('Add '.rtrim($directory, 's'))->assertDontSee('People &amp; Academics')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->post('/logout');
        }
        $this->actingAs($f['admin'])->get('/admin/teachers')->assertSee($f['teacher']->email)->assertDontSee($f['student']->email);
        $this->get('/admin/students')->assertSee($f['student']->email)->assertDontSee($f['teacher']->email);
        $this->get('/admin/teachers/create')->assertOk()->assertSee('Create teacher and login');
        $this->get('/admin/students/create')->assertOk()->assertSee('Create student and login');
    }

    public function test_new_accounts_have_correct_roles_profiles_and_working_logins(): void
    {
        $f = $this->fixture();
        foreach (['teacher', 'student'] as $role) {
            $data = $this->payload($f, $role);
            $this->actingAs($f['admin'])->post('/admin/'.$role.'s', $data + ['role' => 'admin'])->assertSessionHasNoErrors()->assertRedirect('/admin/'.$role.'s');
            $account = M\User::where('email', $data['email'])->sole();
            $this->assertSame($role, $account->role);
            $this->assertTrue(Hash::check($data['password'], $account->password));
            $ciphertext = session('account_credentials');
            $this->assertStringNotContainsString($data['password'], $ciphertext);
            $this->assertSame($data['password'], json_decode(Crypt::decryptString($ciphertext), true)['password']);
            $this->get('/admin/'.$role.'s')->assertOk()->assertSee('Login details')->assertSee($data['password']);
            $this->get('/admin/'.$role.'s')->assertDontSee($data['password']);
            $this->assertStringNotContainsString($data['password'], M\AuditLog::all()->toJson());
            $this->post('/logout');
            $this->post('/sign-in', ['email' => $data['email'], 'password' => $data['password']])->assertRedirect('/'.$role.'/'.$role.'-dashboard');
            $this->get('/'.$role.'/'.$role.'-dashboard')->assertOk();
            $this->post('/logout');
        }
        $this->assertDatabaseHas('teacher_profiles', ['teacher_id' => M\User::where('email', 'new-teacher@example.test')->value('id'), 'department_id' => $f['dep']->id]);
        $this->assertDatabaseHas('student_profiles', ['roll_number' => 'NEW-001', 'section_id' => $f['section']->id]);
    }

    public function test_duplicates_and_failed_academic_validation_are_atomic(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $data = $this->payload($f, 'student');
        $data['roll_number'] = 'TEST-0';
        $this->post('/admin/students', $data)->assertSessionHasErrors('roll_number');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $data['roll_number'] = 'NEW-001';
        $dep = M\Department::create(['name' => 'Other', 'code' => 'OTH']);
        $course = M\Course::create(['department_id' => $dep->id, 'code' => 'OTH1', 'name' => 'Other course', 'credits' => 3]);
        $data['course_ids'] = [$course->id];
        $this->post('/admin/students', $data)->assertSessionHasErrors('course_ids');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->assertDatabaseMissing('student_profiles', ['roll_number' => 'NEW-001']);
        $data = $this->payload($f, 'teacher');
        $data['email'] = $f['student']->email;
        $this->post('/admin/teachers', $data)->assertSessionHasErrors('email');
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
    }

    public function test_new_student_cannot_be_added_to_another_session_section(): void
    {
        $f = $this->fixture();
        $session = M\AcademicSession::create(['name' => 'Spring other', 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-01']);
        $semester = M\Semester::create(['academic_session_id' => $session->id, 'name' => 'Semester 1', 'number' => 1]);
        $section = M\Section::create(['department_id' => $f['dep']->id, 'semester_id' => $semester->id, 'name' => 'Spring class']);
        $data = $this->payload($f, 'student');
        $data['section_id'] = $section->id;
        $this->actingAs($f['admin'])->post('/admin/students', $data)->assertSessionHasErrors('section_id');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }

    public function test_account_edits_preserve_role_and_history_and_status_controls_signin(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->put('/admin/teachers/'.$f['teacher']->id, ['name' => 'Updated teacher', 'email' => 'updated@example.test', 'active' => 0, 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('teacher', $f['teacher']->fresh()->role);
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->get('/admin/students/'.$f['teacher']->id.'/edit')->assertNotFound();
        $this->put('/admin/students/'.$f['admin']->id, ['name' => 'No', 'email' => 'no@example.test', 'active' => 0])->assertNotFound();
        $this->post('/logout');
        $this->post('/sign-in', ['email' => 'updated@example.test', 'password' => 'TestPassword2026!'])->assertSessionHasErrors('email');
    }

    public function test_password_reset_replaces_password_without_leaking_to_logs_or_another_admin(): void
    {
        $f = $this->fixture();
        $password = 'ReplacementPrivatePassword!';
        $this->actingAs($f['admin'])->post('/admin/teachers/'.$f['teacher']->id.'/password', ['password' => $password, 'password_confirmation' => $password])->assertSessionHasNoErrors()->assertRedirect('/admin/teachers');
        $this->assertTrue(Hash::check($password, $f['teacher']->fresh()->password));
        $this->assertFalse(Hash::check('TestPassword2026!', $f['teacher']->fresh()->password));
        $this->actingAs($f['reviewer'])->get('/admin/teachers')->assertDontSee($password);
        $this->assertStringNotContainsString($password, M\AuditLog::all()->toJson());
        $this->post('/admin/students/'.$f['teacher']->id.'/password', ['password' => $password, 'password_confirmation' => $password])->assertNotFound();
        $this->post('/logout');
        $this->post('/sign-in', ['email' => $f['teacher']->email, 'password' => $password])->assertRedirect('/teacher/teacher-dashboard');
    }
}
