<?php

namespace App\Http\Controllers;

use App\Models\AdmitCard;
use App\Services\AdmitVerificationService;

class AdmitVerificationController
{
    public function show(string $reference, AdmitVerificationService $verification)
    {
        $card = AdmitCard::with('student.profile', 'examination')->where('reference', $reference)->firstOrFail();
        // Pass only this explicit public projection. No full model or profile reaches the view.
        return response()->view('public.admit-verification', [
            'studentName' => $card->student->name,
            'rollNumber' => $card->student->profile?->roll_number ?? 'Unavailable',
            'examName' => $card->examination->name,
            'valid' => $verification->valid($card),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
