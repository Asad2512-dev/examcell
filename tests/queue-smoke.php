<?php

use App\Jobs\GenerateAdmitCards;
use App\Models\AdmitGenerationRun;
use App\Models\Examination;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Queue smoke check requires _test database.');
}
if (($argv[1] ?? '') === 'verify') {
    $r = AdmitGenerationRun::latest()->firstOrFail();
    if ($r->status !== 'completed' || $r->generated !== 2) {
        throw new RuntimeException('Queue worker did not complete actual PDF generation.');
    }echo "PASS real database queue worker generated two PDFs.\n";
    exit;
}
$e = Examination::where('status', 'published')->firstOrFail();
$a = User::where('role', 'admin')->firstOrFail();
$run = AdmitGenerationRun::create(['examination_id' => $e->id, 'requested_by' => $a->id]);
GenerateAdmitCards::dispatch($run->id);
echo "Queued actual smoke-check job.\n";
