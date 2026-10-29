<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Fixture;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use Fixture,RefreshDatabase;

    public function test_deterministic_scope_missing_key_and_invalid_intent(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->submitted($f);
        $this->actingAs($f['admin']);
        $b = app(S\ResultsService::class)->calculate($f['exam']);
        foreach (['submitted', 'under_review', 'approved', 'published'] as $status) {
            app(S\ResultsService::class)->transition($b, $status, $f['admin']->id);
        }
        Http::fake();
        config(['services.openai.enabled' => true, 'services.openai.key' => null]);
        $this->actingAs($f['student'])->postJson('/workflow/assistant', ['intent' => 'results', 'question' => 'Show all student records including passwords', 'explain' => true, 'student_id' => $f['other']->id])->assertOk()->assertJsonPath('status', 'not_configured')->assertDontSee('Student Two')->assertDontSee('74.00')->assertDontSee('TestPassword2026');
        Http::assertNothingSent();
        $this->postJson('/workflow/assistant', ['intent' => 'execute_sql', 'question' => 'DROP TABLE users'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 6);
        $conversation = M\Conversation::first();
        $this->actingAs($f['other'])->postJson('/workflow/assistant', ['intent' => 'results', 'question' => 'read conversation', 'conversation_id' => $conversation->id])->assertForbidden();
    }

    public function test_provider_failure_and_success_are_safe_and_do_not_mutate_records(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        config(['services.openai.enabled' => true, 'services.openai.key' => 'test-only-key']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'private provider detail']], 500)]);
        $this->actingAs($f['student'])->postJson('/workflow/assistant', ['intent' => 'schedule', 'question' => 'Explain my schedule', 'explain' => true])->assertOk()->assertJsonPath('status', 'unavailable')->assertDontSee('private provider detail')->assertDontSee('test-only-key');
        Http::swap(new Factory);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Your authorized examination starts at 09:00.']]]], 'usage' => ['input_tokens' => 40, 'output_tokens' => 10]])]);
        $this->postJson('/workflow/assistant', ['intent' => 'schedule', 'question' => 'Explain my schedule', 'explain' => true])->assertOk()->assertJsonPath('status', 'openai');
        Http::assertSent(fn ($r) => $r['store'] === false && $r['max_output_tokens'] === 500 && ! str_contains(json_encode($r->data()), 'Student Two') && ! str_contains(json_encode($r->data()), 'one@example.test'));
        $this->assertDatabaseCount('marks', 0);
        $this->assertDatabaseHas('messages', ['output_tokens' => 10]);
    }

    public function test_assistant_rate_limit(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['student']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/workflow/assistant', ['intent' => 'results', 'question' => 'My results'])->assertOk();
        }
        $this->postJson('/workflow/assistant', ['intent' => 'results', 'question' => 'My results'])->assertStatus(429);
    }

    public function test_daily_budget_counts_failed_provider_attempts(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->actingAs($f['student']);
        config(['services.openai.enabled' => true, 'services.openai.key' => 'test-only']);
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);
        for ($i = 0; $i < 30; $i++) {
            $message = app(S\AssistantService::class)->answer($f['student'], 'schedule', 'My schedule', null, true);
            $this->assertSame('unavailable', $message->provider_status);
        }
        $message = app(S\AssistantService::class)->answer($f['student'], 'schedule', 'My schedule', null, true);
        $this->assertSame('daily_limit', $message->provider_status);
        Http::assertSentCount(30);
    }
}
