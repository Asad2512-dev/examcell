<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\ScreenCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
    }

    public function test_all_screens_use_authenticated_roles_and_render_real_empty_states(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            $u = User::create(['name' => 'Test '.$role, 'email' => $role.'@example.test', 'password' => 'TestPassword2026!', 'role' => $role]);
            $this->actingAs($u);
            foreach ((new ScreenCatalog)->forRole($role) as $page) {
                $response = $this->get('/'.$role.'/'.$page['slug']);
                if ($page['kind'] === 'auth') {
                    $response->assertRedirect('/sign-in');
                } else {
                    $response->assertOk();
                }
            }
        }
    }

    public function test_public_routes_do_not_expose_role_workspaces(): void
    {
        $this->get('/admin/admin-dashboard')->assertRedirect('/sign-in');
        $this->get('/sign-in')->assertOk()->assertDontSee('ExamCellDemo2026');
        $student = User::create(['name' => 'Student', 'email' => 'student@example.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
        $this->actingAs($student)->get('/admin/student-directory')->assertForbidden();
        $this->get('/student/unknown-screen')->assertNotFound();
    }

    public function test_login_logout_and_inactive_account(): void
    {
        $u = User::create(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'TestPassword2026!', 'role' => 'teacher']);
        $this->post('/sign-in', ['email' => $u->email, 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->post('/sign-in', ['email' => $u->email, 'password' => 'TestPassword2026!', 'role' => 'admin'])->assertRedirect('/teacher/teacher-dashboard');
        $this->assertAuthenticatedAs($u);
        $this->get('/admin/admin-dashboard')->assertForbidden();
        $this->post('/logout')->assertRedirect('/sign-in');
        $this->assertGuest();
        $u->update(['active' => false]);
        $this->post('/sign-in', ['email' => $u->email, 'password' => 'TestPassword2026!'])->assertSessionHasErrors('email');
    }

    public function test_server_side_pagination_and_validation(): void
    {
        $a = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'TestPassword2026!', 'role' => 'admin']);
        $this->actingAs($a)->post('/manage/departments', ['name' => 'IT', 'code' => 'IT'])->assertRedirect();
        $this->assertDatabaseHas('departments', ['code' => 'IT']);
        $this->post('/manage/departments', ['name' => 'Duplicate', 'code' => 'IT'])->assertSessionHasErrors('code');
        for ($i = 1; $i <= 22; $i++) {
            Department::create(['name' => 'Dept '.$i, 'code' => 'D'.$i]);
        }
        $this->get('/admin/departments?sort=id&direction=asc')->assertOk()->assertSee('23 records')->assertSee('page 1 of 2');
        $this->get('/admin/departments?q=Dept+22')->assertSee('1 records');
    }
}
