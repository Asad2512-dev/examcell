<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class NormalSignInTest extends TestCase
{
    use RefreshDatabase, Fixture;

    public function test_every_role_signs_in_directly_to_its_dashboard_without_styleguide(): void
    {
        $f = $this->fixture();
        $this->get('/sign-in')->assertOk()->assertDontSee('styleguide');
        foreach (['admin', 'teacher', 'student'] as $role) {
            $this->post('/sign-in', ['email' => $f[$role]->email, 'password' => 'TestPassword2026!'])
                ->assertRedirect('/'.$role.'/'.$role.'-dashboard');
            $this->assertAuthenticatedAs($f[$role]);
            $this->get('/'.$role.'/'.$role.'-dashboard')->assertOk()->assertDontSee('href="/styleguide"', false);
            $this->post('/logout')->assertRedirect('/sign-in');
            $this->assertGuest();
        }
    }
}
