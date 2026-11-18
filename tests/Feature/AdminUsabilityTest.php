<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixture;
use Tests\TestCase;

class AdminUsabilityTest extends TestCase
{
    use Fixture, RefreshDatabase;

    public function test_admin_workflows_render_with_guided_allocation_and_consistent_controls(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['admin']);
        $pages = [
            'rooms' => '/admin/rooms',
            'assistant' => '/admin/assistant?tab=chat',
            'datesheet' => '/admin/exams?tab=datesheet&exam='.$fixture['exam']->id,
            'seating' => '/admin/seating',
            'invigilation' => '/admin/invigilation',
            'marks' => '/admin/marks',
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk()->assertDontSee('@php', false);
            if (in_array($name, ['rooms', 'datesheet', 'seating', 'invigilation'])) {
                $response->assertSee('Exam preparation steps');
            }
            if ($name === 'datesheet') {
                $response->assertSee('Check and allocate room')->assertSee('Need more than one room?');
            }
            if ($directory = getenv('EXAMCELL_ADMIN_PREVIEW')) {
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            }
        }
    }
}
