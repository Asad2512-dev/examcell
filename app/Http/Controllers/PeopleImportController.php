<?php

namespace App\Http\Controllers;

use App\Services\AcademicContext;
use App\Services\AuditService;
use App\Services\PeopleImport;
use App\Services\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PeopleImportController
{
    public function show(Request $request)
    {
        $kind = $this->kind($request->query('kind', 'students'));
        $session = app(AcademicContext::class)->current()['session_id'];

        return view('admin.people-import', ['kind' => $kind, 'columns' => PeopleImport::COLUMNS[$kind], 'hasSession' => (bool) $session, 'check' => null, 'result' => null]);
    }

    public function template(string $kind)
    {
        $kind = $this->kind($kind);
        $format = request()->query('format', 'csv');
        if ($format === 'xlsx') {
            return response(app(XlsxWriter::class)->make(app(PeopleImport::class)->templateRows($kind), ucfirst($kind)), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$kind.'-template.xlsx"',
            ]);
        }

        return response(app(PeopleImport::class)->template($kind), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$kind.'-template.csv"',
        ]);
    }

    public function export(string $kind)
    {
        $kind = $this->kind($kind);
        $format = request()->validate(['format' => 'nullable|in:xlsx,csv'])['format'] ?? 'xlsx';
        $rows = app(PeopleImport::class)->exportRows($kind);
        app(AuditService::class)->record($kind.'.exported', $kind, ['format' => $format, 'records' => max(0, count($rows) - 1)]);
        if ($format === 'xlsx') {
            return response(app(XlsxWriter::class)->make($rows, ucfirst($kind)), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$kind.'-export.xlsx"',
            ]);
        }

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-]/', $value) ? "'".$value : $value, $row));
            }
            fclose($stream);
        }, $kind.'-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['kind' => 'required|in:students,teachers,departments,sessions', 'file' => 'required|file|max:4096|mimes:csv,txt,xlsx,tsv'], [
            'file.required' => 'Choose a file to upload.', 'file.max' => 'That file is too large. Keep it under 4 MB.', 'file.mimes' => 'Please upload a CSV or Excel (.xlsx) file.',
        ]);
        $import = app(PeopleImport::class);
        $raw = $import->read($request->file('file')->path(), $request->file('file')->getClientOriginalExtension());
        $check = $import->check($data['kind'], $raw);
        $token = Str::random(40);
        Cache::put('people-import.'.$request->user()->id.'.'.$token, ['kind' => $data['kind'], 'raw' => $raw], now()->addMinutes(30));

        return view('admin.people-import', ['kind' => $data['kind'], 'columns' => PeopleImport::COLUMNS[$data['kind']], 'hasSession' => true, 'check' => $check, 'token' => $token, 'result' => null]);
    }

    public function commit(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|size:40', 'passwords' => 'sometimes|boolean']);
        $key = 'people-import.'.$request->user()->id.'.'.$data['token'];
        $stored = Cache::get($key);
        if (! $stored) {
            throw ValidationException::withMessages(['file' => 'That preview expired. Please upload the file again.']);
        }
        $import = app(PeopleImport::class);
        $check = $import->check($stored['kind'], $stored['raw']);
        if ($check['ok'] === 0) {
            throw ValidationException::withMessages(['file' => 'There are no rows without problems to import.']);
        }
        $result = $import->commit($stored['kind'], $check['rows'], $request->boolean('passwords'));
        Cache::forget($key);

        return view('admin.people-import', ['kind' => $stored['kind'], 'columns' => PeopleImport::COLUMNS[$stored['kind']], 'hasSession' => true, 'check' => null, 'result' => $result + ['skipped' => $check['bad'], 'withPasswords' => $request->boolean('passwords')]]);
    }

    private function kind(mixed $kind): string
    {
        return in_array($kind, ['students', 'teachers', 'departments', 'sessions'], true) ? $kind : 'students';
    }
}
