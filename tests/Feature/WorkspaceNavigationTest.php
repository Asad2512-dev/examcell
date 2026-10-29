<?php

namespace Tests\Feature;

use App\Models as M;
use App\Notifications\WorkflowNotice;
use App\Services\Settings;
use App\Support\ScreenCatalog;
use App\Support\Workspaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\Fixture;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use Fixture,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['openai.api_key' => 'test-only-key', 'queue.default' => 'sync', 'ai.retry_delay_ms' => 0]);
    }

    private function reply(array $payload): CreateResponse
    {
        return CreateResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($payload)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]]);
    }

    public function test_sidebars_are_exactly_the_short_flat_lists(): void
    {
        $f = $this->fixture();
        $expected = ['admin' => ['Dashboard', 'AI Assistant', 'Exams & Date Sheets', 'Seating', 'Invigilation', 'Marks & Results', 'Reports & Analytics', 'People & Academics', 'Rooms', 'Settings'], 'teacher' => ['Dashboard', 'AI Assistant', 'My Courses & Marks', 'Exam Schedule', 'Duties', 'Analytics', 'Profile & Availability'], 'student' => ['Dashboard', 'AI Assistant', 'Exams', 'Results', 'Rechecking', 'Profile']];
        foreach ($expected as $role => $labels) {
            $this->assertSame($labels, array_column(Workspaces::definition($role), 'label'));
            $html = $this->actingAs($f[$role])->get('/'.$role.'/'.$role.'-dashboard')->assertOk()->getContent();
            $this->assertSame(count($labels), preg_match_all('/class="nav-item[ "]/', $html), $role.' sidebar items');
            $this->assertStringNotContainsString('<details class="nav-group"', $html);
        }
    }

    public function test_every_legacy_route_redirects_or_renders_for_every_role(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        foreach (['admin', 'teacher', 'student'] as $role) {
            $this->actingAs($f[$role]);
            foreach ((new ScreenCatalog)->forRole($role) as $s) {
                if ($s['kind'] === 'auth') {
                    continue;
                }
                $res = $this->call('GET', '/'.$role.'/'.$s['slug']);
                if ($res->isRedirect()) {
                    $to = $res->headers->get('Location');
                    $this->assertMatchesRegularExpression('#/'.$role.'/[a-z-]+#', $to, $s['slug']);
                    $this->assertSame(200, $this->call('GET', $to)->getStatusCode(), $role.' '.$s['slug'].' → '.$to);
                } else {
                    $this->assertSame(200, $res->getStatusCode(), $role.' '.$s['slug']);
                }
            }
        }
    }

    public function test_known_redirects_land_on_the_right_workspace_and_tab(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        foreach (['date-sheet-list' => '/admin/exams?tab=datesheet', 'teacher-directory' => '/admin/people?tab=teachers', 'rechecking-request-queue' => '/admin/marks?tab=rechecking', 'backup-status' => '/admin/settings?tab=backups', 'account-and-permissions' => '/admin/settings?tab=accounts', 'gpa-and-cgpa-rules' => '/admin/settings?tab=grading&view=gpa-and-cgpa-rules', 'room-directory' => '/admin/rooms', 'seating-plan-overview' => '/admin/seating', 'session-expired' => '/admin/admin-dashboard'] as $old => $new) {
            $this->call('GET', '/admin/'.$old)->assertRedirect($new);
        }
        $this->call('GET', '/admin/courses?q=x')->assertRedirectContains('/admin/people?');
        $this->actingAs($f['student']);
        $this->call('GET', '/student/admit-card')->assertRedirect('/student/exams?tab=admit');
        $this->actingAs($f['teacher']);
        $this->call('GET', '/teacher/marks-csv-import')->assertRedirect('/teacher/courses?tab=import');
        $this->call('GET', '/teacher/replacement-status')->assertRedirect('/teacher/duties?view=replacement-status');
    }

    public function test_workspaces_and_tabs_render_with_tab_bars_and_no_state_demos_in_navigation(): void
    {
        $f = $this->fixture();
        $this->publish($f);
        $this->actingAs($f['admin']);
        foreach (Workspaces::definition('admin') as $w) {
            foreach ($w['tabs'] as $t) {
                $html = $this->get('/admin/'.$w['slug'].(count($w['tabs']) > 1 ? '?tab='.$t['key'] : ''))->assertOk()->getContent();
                if (count($w['tabs']) > 1) {
                    $this->assertStringContainsString('class="tabs"', $html, $w['key'].'/'.$t['key']);
                }
                $this->assertStringNotContainsString('Unauthorized access', strip_tags(substr($html, 0, 3000)));
            }
        }
        $this->get('/admin/exams?exam='.$f['exam']->id.'&tab=datesheet')->assertOk()->assertSee('Approve and publish')->assertSee('Versions');
        $this->get('/admin/exams?exam='.$f['exam']->id.'&tab=overview')->assertOk()->assertSee('All examinations');
        $this->get('/admin/exams?exam='.$f['exam']->id.'&tab=admit')->assertOk()->assertSee('Generate all admit cards');
        $this->get('/admin/seating')->assertOk()->assertSee('AI Generate');
        $this->get('/admin/invigilation')->assertOk()->assertSee('AI Assign');
        $this->get('/admin/marks?tab=rechecking')->assertOk()->assertSee('Triage requests');
        $this->get('/admin/marks?tab=processing')->assertOk()->assertSee('Check anomalies');
        $this->get('/admin/reports?tab=reports')->assertOk()->assertSee('Quick reports');
        $this->get('/admin/people?tab=students')->assertOk()->assertSee('AI Import');
        $this->get('/admin/admin-dashboard')->assertOk()->assertSee('Tell AI what you need');
    }

    public function test_notification_settings_really_stop_notices(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get('/admin/settings?tab=notifications')->assertOk()->assertSee('Save notification settings');
        $this->post('/admin/settings/notifications', ['events' => ['schedule' => '0', 'results' => '1', 'duties' => '1', 'duty_requests' => '1', 'marks' => '1', 'rechecks' => '1', 'admit' => '1']])->assertSessionHasNoErrors();
        $this->assertFalse(Settings::noticeEnabled('Exam schedule published'));
        $this->assertTrue(Settings::noticeEnabled('Results published'));
        $before = $f['student']->notifications()->count();
        $f['student']->notify(new WorkflowNotice('Exam schedule published', 'x', '/'));
        $f['student']->notify(new WorkflowNotice('Results published', 'y', '/'));
        $this->assertSame($before + 1, $f['student']->notifications()->count());
    }

    public function test_accounts_editor_changes_roles_with_safeguards(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get('/admin/settings?tab=accounts')->assertOk()->assertSee('Permissions by role');
        $this->post('/admin/settings/accounts/'.$f['other']->id, ['role' => 'teacher', 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('teacher', $f['other']->fresh()->role);
        $this->post('/admin/settings/accounts/'.$f['other']->id, ['role' => 'student'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $f['other']->fresh()->active);
        $this->post('/admin/settings/accounts/'.$f['admin']->id, ['role' => 'teacher', 'active' => '1'])->assertSessionHasErrors('account');
        $this->actingAs($f['reviewer'])->post('/admin/settings/accounts/'.$f['admin']->id, ['role' => 'teacher', 'active' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($f['admin'])->assertTrue(true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.updated']);
        $this->actingAs($f['student'])->post('/admin/settings/accounts/'.$f['admin']->id, ['role' => 'student'])->assertForbidden();
    }

    public function test_gpa_cgpa_rules_editor_persists_and_is_used(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get('/admin/settings?tab=grading')->assertOk()->assertSee('GPA and CGPA rules');
        $draft = M\GradingScheme::create(['name' => 'Draft scheme', 'status' => 'draft', 'rounding_places' => 2]);
        $this->post('/admin/settings/grading-rules', ['cgpa_attempt_policy' => 'all', 'rounding' => [$draft->id => 1, $f['scheme']->id => 0]])->assertSessionHasNoErrors();
        $this->assertSame('all', Settings::get('cgpa_attempt_policy'));
        $this->assertSame(1, (int) $draft->fresh()->rounding_places);
        $this->assertSame(2, (int) $f['scheme']->fresh()->rounding_places, 'approved schemes are locked');
        $this->post('/admin/settings/grading-rules', ['cgpa_attempt_policy' => 'bogus'])->assertSessionHasErrors('cgpa_attempt_policy');
    }

    public function test_command_box_routes_requests_to_proposals_and_answers(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin']);
        $cmd = fn (array $o) => $this->reply($o + ['exam_id' => null, 'entry_id' => null, 'announcement_kind' => null, 'report_question' => null, 'navigate_to' => null, 'reply' => 'ok']);
        OpenAI::fake([$cmd(['action' => 'datesheet', 'exam_id' => $f['exam']->id]), $this->reply(['entries' => [], 'explanation' => 'x']), $this->reply(['entries' => [], 'explanation' => 'x']), $this->reply(['entries' => [], 'explanation' => 'x'])]);
        $this->post('/admin/ai/command', ['text' => 'Generate date sheet for Final test', 'from' => 'dashboard'])->assertRedirectContains('/admin/exams?exam='.$f['exam']->id.'&tab=datesheet&run=');
        $this->assertSame('datesheet', M\AiRun::first()->feature);
        $this->assertSame(0, M\ScheduleEntry::count(), 'a proposal only');
        OpenAI::fake([$cmd(['action' => 'missing_marks'])]);
        $this->post('/admin/ai/command', ['text' => 'Who has missing marks?', 'from' => 'assistant'])->assertRedirectContains('/admin/assistant?tab=chat&conversation_id=');
        $this->assertStringContainsString('CS301', M\Message::where('role', 'assistant')->latest('id')->first()->content);
        OpenAI::fake([$cmd(['action' => 'navigate', 'navigate_to' => 'rooms'])]);
        $this->post('/admin/ai/command', ['text' => 'open rooms'])->assertRedirect('/admin/rooms');
        OpenAI::fake([$cmd(['action' => 'seating'])]);
        $this->post('/admin/ai/command', ['text' => 'seat it'])->assertRedirectContains('conversation_id=');
        $this->call('GET', '/admin/ai/quick/pass-rate-department')->assertRedirect('/admin/reports?tab=reports')->assertSessionHas('ai_report');
        $this->call('GET', '/admin/ai/quick/nope')->assertNotFound();
        $this->actingAs($f['teacher'])->post('/admin/ai/command', ['text' => 'x'])->assertForbidden();
    }
}
