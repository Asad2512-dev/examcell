<?php

namespace App\Services;

use App\Models\AdmitCard;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class AdmitVerificationService
{
    public const VERSION = 1;

    public function url(string $reference): string
    {
        return URL::signedRoute('admit.verify', ['reference' => $reference]);
    }

    public function qrDataUri(string $reference): string
    {
        $qr = new QrCode(data: $this->url($reference), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 320, margin: 24);

        return (new PngWriter)->write($qr)->getDataUri();
    }

    public function valid(AdmitCard $card): bool
    {
        $student = $card->student;
        $exam = $card->examination;
        if (! $student?->active || ! $student->profile?->eligible || $exam?->status !== 'published' || ! $exam->admit_enabled) {
            return false;
        }
        $courses = $exam->courses()->with('schedule')->get()
            ->filter(fn ($course) => $course->eligible()->where('student_id', $student->id)->exists());

        return $courses->isNotEmpty() && $courses->every(fn ($course) => $course->schedule !== null);
    }
}
