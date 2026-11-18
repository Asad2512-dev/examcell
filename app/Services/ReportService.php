<?php

namespace App\Services;

use App\Models as M;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException as V;

class ReportService
{
    public function admit(M\Examination $exam, int $student): M\AdmitCard
    {
        return DB::transaction(function () use ($exam, $student) {
            $exam = M\Examination::lockForUpdate()->findOrFail($exam->id);
            if ($exam->status !== 'published' || ! $exam->admit_enabled) {
                throw V::withMessages(['admit' => 'Examination must be published with admit cards enabled.']);
            }
            $u = M\User::with('profile')->whereKey($student)->where('role', 'student')->where('active', true)->firstOrFail();
            if (! $u->profile?->eligible) {
                throw V::withMessages(['admit' => 'Student is not eligible.']);
            }
            $courses = $exam->courses()->with('course', 'schedule.room')->get()->filter(fn ($c) => $c->eligible()->where('student_id', $student)->exists());
            if ($courses->isEmpty()) {
                throw V::withMessages(['admit' => 'No eligible examination courses.']);
            }
            if ($courses->contains(fn ($c) => ! $c->schedule)) {
                throw V::withMessages(['admit' => 'A course schedule is missing.']);
            }
            $previous = M\AdmitCard::where('examination_id', $exam->id)->where('student_id', $student)->first()?->toArray();
            $reference = (string) Str::uuid();
            $path = 'admit/'.$reference.'.pdf';
            $pdf = $this->renderAdmit($u, $exam, $courses, $reference);
            Storage::put($path, $pdf);
            try {
                $record = M\AdmitCard::updateOrCreate(['examination_id' => $exam->id, 'student_id' => $student], ['reference' => $reference, 'path' => $path, 'verification_version' => AdmitVerificationService::VERSION]);
                app(AuditService::class)->record('admit.generated', $record, ['previous' => $previous]);

                return $record;
            } catch (\Throwable $e) {
                Storage::delete($path);
                throw $e;
            }
        });
    }

    public function upgradeVerification(M\AdmitCard $card): M\AdmitCard
    {
        return DB::transaction(function () use ($card) {
            $locked = M\AdmitCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->verification_version >= AdmitVerificationService::VERSION) {
                return $locked;
            }
            $student = $locked->student()->with('profile')->firstOrFail();
            $exam = $locked->examination;
            // Preserve access to historical/invalid cards. Their public status is checked
            // separately; upgrading a PDF must not grant eligibility or change permissions.
            $courses = $exam->courses()->with('course', 'schedule.room')->whereExists(function ($q) use ($student) {
                $q->selectRaw('1')->from('enrollments')->where('student_id', $student->id)
                    ->whereColumn('enrollments.course_id', 'exam_courses.course_id')
                    ->whereColumn('enrollments.section_id', 'exam_courses.section_id');
            })->get();
            $path = 'admit/'.$locked->reference.'.verified.pdf';
            $pdf = $this->renderAdmit($student, $exam, $courses, $locked->reference);
            if (! Storage::put($path, $pdf)) {
                throw new \RuntimeException('Unable to write the verified admit card.');
            }
            try {
                $previousPath = $locked->path;
                $locked->update(['path' => $path, 'verification_version' => AdmitVerificationService::VERSION]);
                app(AuditService::class)->record('admit.verification_upgraded', $locked, ['previous_path' => $previousPath]);
            } catch (\Throwable $error) {
                Storage::delete($path);
                throw $error;
            }

            return $locked;
        });
    }

    private function renderAdmit(M\User $student, M\Examination $exam, $courses, string $reference): string
    {
        $verification = app(AdmitVerificationService::class);

        return Pdf::loadView('backend.admit-pdf', [
            'student' => $student, 'exam' => $exam, 'courses' => $courses, 'reference' => $reference,
            'verificationQr' => $verification->qrDataUri($reference),
            'verificationUrl' => $verification->url($reference),
        ])->output();
    }

    public function bulk(M\Examination $e): int
    {
        $ids = collect();
        foreach ($e->courses as $c) {
            $ids = $ids->merge($c->eligible()->pluck('student_id'));
        }
        $count = 0;
        foreach ($ids->unique() as $id) {
            $this->admit($e, $id);
            $count++;
        }

        return $count;
    }
}
