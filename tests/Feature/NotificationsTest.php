<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name): User
    {
        return User::create(['name' => $name, 'email' => $name.'@notifications.test', 'password' => 'TestPassword2026!', 'role' => 'student']);
    }

    private function notice(User $user)
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => WorkflowNotice::class,
            'data' => ['title' => 'Exam update', 'message' => 'Your examination schedule has changed.', 'url' => '/student/examinations'],
        ]);
    }

    public function test_read_updates_are_idempotent_and_return_the_current_unread_count(): void
    {
        $user = $this->account('reader');
        $notice = $this->notice($user);
        $this->notice($user);
        $this->actingAs($user)->postJson('/workflow/notifications/'.$notice->id.'/read')
            ->assertOk()->assertJsonPath('unread_count', 1);
        $this->postJson('/workflow/notifications/'.$notice->id.'/read')
            ->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_bulk_read_and_individual_read_cannot_modify_another_users_notifications(): void
    {
        $user = $this->account('reader');
        $other = $this->account('other');
        $this->notice($user);
        $this->notice($user);
        $private = $this->notice($other);

        $this->actingAs($user)->postJson('/workflow/notifications/'.$private->id.'/read')->assertNotFound();
        $this->postJson('/workflow/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($private->fresh()->read_at);
    }

    public function test_panel_renders_compact_messages_and_bulk_controls(): void
    {
        $user = $this->account('panel');
        for ($i = 0; $i < 12; $i++) {
            $notice = $this->notice($user);
            $notice->update(['data' => ['title' => 'AI replacement suggestion', 'message' => str_repeat('Both candidates have an equal workload. ', 8), 'url' => '/student/examinations']]);
        }
        $response = $this->actingAs($user)->get('/student/profile');
        $response->assertOk()->assertSee('data-notice-read-all', false)->assertSee('data-notice-expand', false)->assertSee('12 unread');
        if ($path = getenv('EXAMCELL_NOTIFICATION_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_notification_actions_require_authentication_and_keep_html_fallback(): void
    {
        $this->postJson('/workflow/notifications/read-all')->assertUnauthorized();
        $user = $this->account('reader');
        $this->notice($user);
        $this->actingAs($user)->from('/student/profile')->post('/workflow/notifications/read-all')
            ->assertRedirect('/student/profile')->assertSessionHas('status', 'All notifications marked read.');
    }
}
