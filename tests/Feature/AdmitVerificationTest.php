<?php

namespace Tests\Feature;

use App\Models as M;
use App\Services\AdmitVerificationService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Fixture;
use Tests\TestCase;

class AdmitVerificationTest extends TestCase
{
    use RefreshDatabase, Fixture;

    public function test_signed_public_verification_is_minimal_and_needs_no_login(): void
    {
        $f = $this->fixture(); $this->publish($f);
        $card = app(ReportService::class)->admit($f['exam']->fresh(), $f['student']->id);
        $url = app(AdmitVerificationService::class)->url($card->reference);
        $qr = app(AdmitVerificationService::class)->qrDataUri($card->reference);
        $png = base64_decode(substr($qr, strpos($qr, ',') + 1));
        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertGreaterThan(200, getimagesizefromstring($png)[0]);
        file_put_contents('/tmp/examcell-verification-qr.png', $png);
        file_put_contents('/tmp/examcell-verification-url.txt', $url);
        $this->assertSame(AdmitVerificationService::VERSION, (int) $card->verification_version);
        $this->assertStringStartsWith('%PDF', Storage::get($card->path));
        auth()->logout();
        $response = $this->get($url)->assertOk()->assertSee('Student One')->assertSee('TEST-0')->assertSee('Final test')->assertSee('Valid admit card');
        $response->assertDontSee($f['student']->email)->assertDontSee($f['other']->name)->assertDontSee('Database Systems')->assertDontSee('styleguide');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertEqualsCanonicalizing(['studentName','rollNumber','examName','valid'], array_keys($response->viewData('valid') !== null ? array_diff_key($response->original->getData(), array_flip(['app','errors'])) : []));
    }

    public function test_unsigned_tampered_and_expired_links_never_disclose_student_data(): void
    {
        $f = $this->fixture(); $this->publish($f);
        $card = app(ReportService::class)->admit($f['exam']->fresh(), $f['student']->id);
        $url = app(AdmitVerificationService::class)->url($card->reference);
        auth()->logout();
        $this->get('/verify/admit/'.$card->reference)->assertForbidden()->assertDontSee('Student One');
        $this->get(str_replace($card->reference, '00000000-0000-4000-8000-000000000000', $url))->assertForbidden()->assertDontSee('Student One');
        $this->get($url.'&student_id='.$f['other']->id)->assertForbidden()->assertDontSee('Student One');
        $expired = URL::temporarySignedRoute('admit.verify', now()->subMinute(), ['reference' => $card->reference]);
        $this->get($expired)->assertForbidden()->assertDontSee('Student One');
    }

    public function test_validity_updates_and_reissued_cards_revoke_previous_links(): void
    {
        $f = $this->fixture(); $this->publish($f);
        $card = app(ReportService::class)->admit($f['exam']->fresh(), $f['student']->id);
        $url = app(AdmitVerificationService::class)->url($card->reference);
        auth()->logout();
        $f['student']->update(['active' => false]);
        $this->get($url)->assertOk()->assertSee('Admit card is not currently valid');
        $f['student']->update(['active' => true]);
        $f['exam']->update(['admit_enabled' => false]);
        $this->get($url)->assertOk()->assertSee('Admit card is not currently valid');
        $f['exam']->update(['admit_enabled' => true]);
        $f['student']->profile->update(['eligible' => false]);
        $this->get($url)->assertOk()->assertSee('Admit card is not currently valid');
        $f['student']->profile->update(['eligible' => true]);
        $this->actingAs($f['admin']);
        $new = app(ReportService::class)->admit($f['exam']->fresh(), $f['student']->id);
        $this->assertNotSame($card->reference, $new->reference);
        auth()->logout();
        $this->get($url)->assertNotFound()->assertDontSee('Student One');
        $this->get(app(AdmitVerificationService::class)->url($new->reference))->assertOk()->assertSee('Valid admit card');
        $f['exam']->update(['status' => 'archived']);
        $this->get(app(AdmitVerificationService::class)->url($new->reference))->assertOk()->assertSee('Admit card is not currently valid');
    }

    public function test_legacy_download_is_upgraded_once_without_bypassing_download_permissions(): void
    {
        $f = $this->fixture(); $this->publish($f);
        $card = app(ReportService::class)->admit($f['exam']->fresh(), $f['student']->id);
        $card->update(['verification_version' => 0]);
        $this->actingAs($f['other'])->get('/download/admit/'.$card->id)->assertForbidden();
        $this->actingAs($f['student'])->get('/download/admit/'.$card->id)->assertOk();
        $upgraded = $card->fresh();
        $this->assertSame(1, (int) $upgraded->verification_version);
        $this->assertSame($card->reference, $upgraded->reference);
        $this->assertTrue(Storage::exists($card->path), 'Original PDF must be retained.');
        $this->get('/download/admit/'.$card->id)->assertOk();
        $this->assertSame($upgraded->reference, $card->fresh()->reference);
        $card->fresh()->update(['verification_version' => 0]);
        $f['exam']->update(['status' => 'archived']);
        $this->actingAs($f['admin'])->get('/download/admit/'.$card->id)->assertOk();
        auth()->logout();
        $this->get(app(AdmitVerificationService::class)->url($card->reference))->assertOk()->assertSee('Admit card is not currently valid');
    }
}
