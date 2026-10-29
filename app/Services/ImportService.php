<?php

namespace App\Services;

use App\Models as M;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException as V;

class ImportService
{
    public function parse(string $path, array $headers, string $format = 'csv'): array
    {
        if ($format === 'xlsx') {
            return app(XlsxReader::class)->rows($path, $headers);
        }
        $f = fopen($path, 'r');
        $actual = fgetcsv($f);
        if ($actual && isset($actual[0])) {
            $actual[0] = preg_replace('/^\xEF\xBB\xBF/', '', $actual[0]);
        }
        if ($actual !== $headers) {
            throw V::withMessages(['file' => 'CSV headers must be exactly: '.implode(',', $headers)]);
        }
        $rows = [];
        $errors = [];
        while (($row = fgetcsv($f)) !== false) {
            if (count($rows) >= 1000) {
                throw V::withMessages(['file' => 'Maximum 1000 rows.']);
            }
            if (count($row) !== count($headers)) {
                $errors[] = 'Row '.(count($rows) + 2).' has an incorrect column count.';

                continue;
            }
            $rows[] = array_combine($headers, $row);
        }fclose($f);
        if ($errors) {
            throw V::withMessages(['file' => $errors]);
        }
        if (! $rows) {
            throw V::withMessages(['file' => 'The CSV is empty.']);
        }

        return $rows;
    }

    public function marks(M\ExamCourse $c, string $path, int $actor, bool $commit, string $format = 'csv'): array
    {
        Gate::authorize('update', $c);
        $rows = $this->parse($path, ['roll_number', 'component_id', 'score', 'absent'], $format);
        $prepared = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $v = Validator::make($row, ['roll_number' => 'required|string', 'component_id' => 'required|integer', 'score' => 'nullable|numeric|min:0|decimal:0,2', 'absent' => 'required|in:0,1']);
            if ($v->fails()) {
                throw V::withMessages(['file' => 'Row '.($i + 2).': '.$v->errors()->first()]);
            }
            $student = M\StudentProfile::where('roll_number', $row['roll_number'])->first();
            if (! $student) {
                throw V::withMessages(['file' => 'Unknown roll number at row '.($i + 2)]);
            }
            $comp = $c->components()->find($row['component_id']);
            if (! $comp || ! $c->eligible()->where('student_id', $student->user_id)->exists()) {
                throw V::withMessages(['file' => 'Row '.($i + 2).' is outside the assignment.']);
            }
            $key = $student->user_id.':'.$comp->id;
            if (isset($seen[$key])) {
                throw V::withMessages(['file' => 'Duplicate at row '.($i + 2)]);
            }
            $seen[$key] = true;
            $score = $row['score'] === '' ? null : $row['score'];
            if (($row['absent'] === '1' && $score !== null) || ($score !== null && bccomp($score, (string) $comp->maximum, 2) > 0)) {
                throw V::withMessages(['file' => 'Invalid score/absence at row '.($i + 2)]);
            }
            $prepared[] = ['student_id' => $student->user_id, 'assessment_component_id' => $comp->id, 'score' => $score, 'absent' => $row['absent'] === '1'];
        }
        if ($commit) {
            app(MarksService::class)->save($c, $prepared, $actor);
        }

        return ['rows' => count($rows), 'preview' => $prepared, 'committed' => $commit];
    }

    public function students(string $path, bool $commit, string $format = 'csv'): array
    {
        $rows = $this->parse($path, ['roll_number', 'name', 'email', 'section_id'], $format);
        $seen = [];
        foreach ($rows as $i => $row) {
            $v = Validator::make($row, ['roll_number' => 'required|max:40|unique:student_profiles', 'name' => 'required|max:120', 'email' => 'required|email|max:254|unique:users', 'section_id' => 'required|exists:sections,id']);
            if ($v->fails()) {
                throw V::withMessages(['file' => 'Row '.($i + 2).': '.$v->errors()->first()]);
            }
            foreach (['roll_number', 'email'] as $f) {
                $key = $f.':'.$row[$f];
                if (isset($seen[$key])) {
                    throw V::withMessages(['file' => 'Duplicate '.$f.' at row '.($i + 2)]);
                }
                $seen[$key] = true;
            }
        }
        if ($commit) {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    $u = M\User::create(['name' => $row['name'], 'email' => $row['email'], 'password' => bin2hex(random_bytes(32)), 'role' => 'student', 'active' => true]);
                    M\StudentProfile::create(['user_id' => $u->id, 'roll_number' => $row['roll_number'], 'section_id' => $row['section_id'], 'eligible' => true]);
                    app(AuditService::class)->record('student.imported', $u);
                }
            });
        }

        return ['rows' => count($rows), 'preview' => $rows, 'committed' => $commit, 'note' => 'New accounts must set a password through password reset. No existing account is overwritten.'];
    }
}
