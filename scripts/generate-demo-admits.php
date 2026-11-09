<?php

use App\Models\AcademicSession;
use App\Models\AdmitCard;
use App\Models\Examination;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

// Generate real PDFs for the demo students only; safe to resume after interruption.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local/testing only.');
}
$session = AcademicSession::where('name', 'DEMO Visual Review 2026-27')->firstOrFail();
$exam = Examination::where('academic_session_id', $session->id)->where('name', 'DEMO Upcoming Finals')->firstOrFail();
$admin = User::findOrFail($exam->courses()->firstOrFail()->schedule->seatingPlan->created_by);
auth()->setUser($admin);
$students = User::whereHas('profile.section.semester', fn ($q) => $q->where('academic_session_id', $session->id))->get();
$count = 0;
foreach ($students as $student) {
    $card = AdmitCard::where('examination_id', $exam->id)->where('student_id', $student->id)->first();
    if (! $card || ! Storage::exists($card->path)) {
        $card = app(ReportService::class)->admit($exam, $student->id);
    }
    if (! Storage::exists($card->path)) {
        throw new RuntimeException('Missing PDF: '.$student->id);
    }
    $count++;
}
echo 'Verified real demo admit PDFs: '.$count.PHP_EOL;
