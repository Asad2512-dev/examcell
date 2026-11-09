<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class UiContentTest extends TestCase
{
    public function test_nested_content_and_placeholders_resolve_with_safe_fallbacks(): void
    {
        $this->assertSame('Upcoming examinations', ui_text('dashboard.upcoming.heading'));
        $this->assertSame('You can request another link in 30 seconds.', ui_text('authentication.resend_wait', ['seconds' => 30]));
        $this->assertSame('missing.section.key', ui_text('missing.section.key'));
        $this->assertSame('Try this wording', ui_text('missing.section.key', [], 'Try this wording'));
    }

    public function test_frontend_content_is_limited_to_approved_public_sections(): void
    {
        $content = ui_frontend_content();

        $this->assertEqualsCanonicalizing(['common', 'navigation', 'authentication', 'landing'], array_keys($content));
        $this->assertArrayNotHasKey('dashboard', $content);
    }

    public function test_live_palette_stylesheet_serves_the_centralized_palette(): void
    {
        $response = $this->get(route('ui.palette'));

        $response->assertOk();
        $this->assertStringContainsString('--palette-3538cd', $response->getContent());
    }

    public function test_appearance_editor_requires_an_admin_session(): void
    {
        $this->get(route('admin.appearance'))->assertRedirect(route('login'));
    }

    public function test_private_copy_overrides_take_precedence_over_source_catalogs(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ui-overrides.json', json_encode([
            'content' => ['dashboard' => ['upcoming.heading' => 'My examination dates']],
        ]));
        Cache::forget('ui-content.dashboard');

        $this->assertSame('My examination dates', ui_text('dashboard.upcoming.heading'));
    }
}
