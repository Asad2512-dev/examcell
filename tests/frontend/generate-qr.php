<?php
// Pure encoding fixture: no database reads/writes or real verification credentials.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\URL::forceRootUrl('https://verify.example.test');
Illuminate\Support\Facades\URL::setKeyResolver(fn () => 'test-only-qr-signing-key');
$service = app(App\Services\AdmitVerificationService::class);
$reference = '00000000-0000-4000-8000-000000000001';
echo json_encode(['url' => $service->url($reference), 'image' => $service->qrDataUri($reference)], JSON_THROW_ON_ERROR);
