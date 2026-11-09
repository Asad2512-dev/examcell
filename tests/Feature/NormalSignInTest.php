<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class NormalSignInTest extends TestCase
{
    use RefreshDatabase, Fixture;

    public function test_website_opens_sign_in_and_public_introduction_remains_available(): void
    {
        $this->get('/')->assertRedirect('/sign-in');
        $this->followingRedirects()->get('/')->assertOk()->assertSee('Welcome Back')->assertSee('data-auth-form', false);
        $this->get('/about')->assertOk()->assertSee('From exam planning');
        $this->assertSame(url('/about'), route('landing'));
    }

    public function test_existing_sessions_return_to_their_own_panel_from_sign_in(): void
    {
        $f = $this->fixture();
        foreach (['admin', 'teacher', 'student'] as $role) {
            $this->actingAs($f[$role]);
            $this->get('/')->assertRedirect('/sign-in');
            $this->get('/sign-in')->assertRedirect('/workspace');
            $this->get('/login')->assertRedirect('/sign-in');
            $this->post('/sign-in', [])->assertRedirect('/workspace');
            $this->get('/workspace')->assertRedirect('/'.$role.'/'.$role.'-dashboard');
            $this->get('/'.$role.'/'.$role.'-dashboard')->assertOk();
        }
    }

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
