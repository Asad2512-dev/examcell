<?php

namespace App\Http\Controllers;

use App\Models as M;
use App\Services\AuditService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as V;

class AdminSettingsController
{
    public function notifications(Request $r)
    {
        foreach (array_keys(Settings::EVENTS) as $key) {
            Settings::set('notify.'.$key, $r->boolean('events.'.$key));
        }
        app(AuditService::class)->record('settings.notifications', 'settings', collect(array_keys(Settings::EVENTS))->mapWithKeys(fn ($k) => [$k => $r->boolean('events.'.$k)])->all());

        return back()->with('status', 'Notification settings saved.');
    }

    public function gradingRules(Request $r)
    {
        $d = $r->validate(['cgpa_attempt_policy' => 'required|in:latest,all', 'rounding' => 'nullable|array', 'rounding.*' => 'integer|between:0,2']);
        Settings::set('cgpa_attempt_policy', $d['cgpa_attempt_policy']);
        foreach ($d['rounding'] ?? [] as $id => $places) {
            M\GradingScheme::where('id', (int) $id)->where('status', '!=', 'approved')->update(['rounding_places' => (int) $places]);
        }
        app(AuditService::class)->record('settings.grading_rules', 'settings', ['cgpa_attempt_policy' => $d['cgpa_attempt_policy'], 'rounding' => $d['rounding'] ?? []]);

        return back()->with('status', 'GPA/CGPA rules saved. Approved grading schemes keep their rounding.');
    }

    public function account(Request $r, M\User $user)
    {
        $d = $r->validate(['role' => 'required|in:admin,teacher,student', 'active' => 'sometimes|boolean']);
        $active = $r->boolean('active');
        if ($user->id === $r->user()->id && ($d['role'] !== $user->role || ! $active)) {
            throw V::withMessages(['account' => 'You cannot change your own role or deactivate yourself.']);
        }
        $remainingAdmins = M\User::where('role', 'admin')->where('active', true)->where('id', '!=', $user->id)->count();
        if ($user->role === 'admin' && $user->active && ($d['role'] !== 'admin' || ! $active) && $remainingAdmins === 0) {
            throw V::withMessages(['account' => 'At least one active administrator is required.']);
        }
        $before = $user->only('role', 'active');
        $user->update(['role' => $d['role'], 'active' => $active]);
        app(AuditService::class)->record('account.updated', $user, ['before' => $before, 'after' => ['role' => $d['role'], 'active' => $active]]);

        return back()->with('status', 'Account updated.');
    }
}
