<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\Settings;
use App\Services\University;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** "Your university": the handful of rules that differ between institutions. */
class UniversityController
{
    public function show()
    {
        $slots = University::slots();

        return view('admin.university', [
            'zones' => DateTimeZone::listIdentifiers(),
            'values' => [
                'name' => Settings::get('university.name', ''),
                'short_name' => Settings::get('university.short_name', ''),
                'timezone' => University::timezone(),
                'terms' => implode(', ', University::terms()),
                'slots' => array_pad($slots, 4, ''),
                'max_grade_point' => University::maxGradePoint(),
                'max_duties_per_day' => University::maxDutiesPerDay(),
            ],
            'configured' => (bool) Settings::get('university.configured', false),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'short_name' => 'nullable|string|max:24',
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'terms' => 'required|string|max:200',
            'slots' => 'required|array|max:6',
            'slots.*' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'max_grade_point' => 'required|numeric|between:1,99',
            'max_duties_per_day' => 'required|integer|between:1,10',
        ], ['slots.*.regex' => 'Enter exam start times like 09:00.']);

        $terms = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['terms'])), fn ($t) => $t !== '')));
        if (! $terms || count($terms) > 8 || collect($terms)->contains(fn ($t) => mb_strlen($t) > 30)) {
            throw ValidationException::withMessages(['terms' => 'List 1 to 8 terms separated by commas, for example: Fall, Spring.']);
        }
        $slots = array_values(array_unique(array_filter($data['slots'])));
        sort($slots);
        if (! $slots) {
            throw ValidationException::withMessages(['slots' => 'Add at least one exam start time.']);
        }

        Settings::set('university.name', $data['name']);
        Settings::set('university.short_name', $data['short_name'] ?: mb_substr($data['name'], 0, 24));
        Settings::set('university.timezone', $data['timezone']);
        Settings::set('university.terms', $terms);
        Settings::set('university.slots', $slots);
        Settings::set('university.max_grade_point', (float) $data['max_grade_point']);
        Settings::set('university.max_duties_per_day', (int) $data['max_duties_per_day']);
        Settings::set('university.configured', true);
        app(AuditService::class)->record('university.updated', 'university', ['name' => $data['name'], 'timezone' => $data['timezone'], 'terms' => $terms, 'slots' => $slots]);

        return redirect('/admin/admin-dashboard')->with('status', 'University details saved. Next: add your classes, teachers and students.');
    }
}
