<?php

use App\Services\AdmitVerificationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;

// Pure encoding fixture: no database reads/writes or real verification credentials.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
URL::forceRootUrl('https://verify.example.test');
URL::setKeyResolver(fn () => 'test-only-qr-signing-key');
$service = app(AdmitVerificationService::class);
$reference = '00000000-0000-4000-8000-000000000001';
echo json_encode(['url' => $service->url($reference), 'image' => $service->qrDataUri($reference)], JSON_THROW_ON_ERROR);
