<?php
namespace Tests\Feature;

use App\Models\User;
use App\Support\ScreenCatalog;
use App\View\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixture;
use Tests\TestCase;

class PresentationTest extends TestCase
{
    use RefreshDatabase, Fixture;

    public function test_error_views_show_human_readable_content(): void
    {
        $this->get('/missing-page')->assertNotFound()->assertSee('We couldn’t find that page')->assertDontSee('@yield');
        $html=view('errors.500')->render();
        $this->assertStringContainsString('Something went wrong',$html);
        $this->assertStringNotContainsString('@yield',$html);
    }

    public function test_catalog_has_no_duplicates_and_requested_navigation_is_present(): void
    {
        $pages=(new ScreenCatalog)->all();
        $keys=array_map(fn($p)=>$p['role'].':'.$p['slug'],$pages);
        $this->assertCount(count(array_unique($keys)),$keys);
        foreach(['admin'=>12,'teacher'=>7,'student'=>6] as $role=>$count){$this->assertCount($count,WorkspaceNavigation::groups($role));}
        foreach(['admin','teacher','student'] as $role){
            $map=\App\Support\Workspaces::legacy($role);
            foreach(array_filter($pages,fn($p)=>$p['role']===$role&&$p['kind']!=='auth') as $p){$this->assertArrayHasKey($p['slug'],$map,$role.':'.$p['slug']);}
        }
    }

    public function test_styleguide_is_admin_only_and_renders_component_examples(): void
    {
        $this->get('/styleguide')->assertRedirect('/sign-in');
        foreach(['teacher','student','admin'] as $role){
            $user=User::create(['name'=>'Styleguide '.$role,'email'=>$role.'@style.test','password'=>'TestPassword2026!','role'=>$role]);
            $response=$this->actingAs($user)->get('/styleguide');
            if($role==='admin')$response->assertOk()->assertSee('The ExamCell design system')->assertSee('dataTable')->assertSee('examCalendar');
            else $response->assertForbidden();
        }
    }

    public function test_populated_screens_and_controller_fallbacks_keep_existing_access(): void
    {
        $f=$this->fixture();$this->publish($f);$this->submitted($f);
        foreach(['admin','teacher','student'] as $role){
            $this->actingAs($f[$role]);
            foreach((new ScreenCatalog)->forRole($role) as $page){
                if($page['kind']==='auth')continue;
                $this->get('/'.$role.'/'.$page['slug'])->assertOk()->assertDontSee('@if(');
            }
            foreach(['records','duty-replacements','exam-schedule','my-duties','recheck-requests','forecast'] as $slug){
                $response=$this->get('/'.$role.'/'.$slug);
                $forbidden=($slug==='records'&&$role!=='admin')||($role==='student'&&in_array($slug,['duty-replacements','my-duties']));
                $forbidden?$response->assertForbidden():$response->assertOk();
            }
        }
    }

    public function test_printable_templates_embed_brand_and_generate_valid_pdfs(): void
    {
        $f=$this->fixture();$this->publish($f);$this->actingAs($f['admin']);
        $card=app(\App\Services\ReportService::class)->admit($f['exam']->fresh(),$f['student']->id);
        $pdf=Storage::get($card->path);$this->assertStringStartsWith('%PDF',$pdf);
        file_put_contents('/tmp/examcell-redesign-admit.pdf',$pdf);
        $report=\Barryvdh\DomPDF\Facade\Pdf::loadView('backend.report-pdf',['title'=>'Examination schedule report','rows'=>collect([['Course'=>'CS301 · Database Systems','Room'=>'Room 1','Date'=>'14 Oct 2026 09:00']])])->setPaper('a4','landscape')->output();
        $this->assertStringStartsWith('%PDF',$report);$this->assertStringContainsString('/Subtype /Image',$report);
        file_put_contents('/tmp/examcell-redesign-report.pdf',$report);
    }
}
