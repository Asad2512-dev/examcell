<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Services\AcademicContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicContextController
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'semester_id' => ['nullable', 'integer', Rule::exists('semesters', 'id')->where('academic_session_id', $request->input('session_id'))],
        ]);
        $request->session()->put(AcademicContext::SESSION_KEY, [
            'user_id' => $request->user()->id,
            'session_id' => (int) $data['session_id'],
            'semester_id' => empty($data['semester_id']) ? null : (int) $data['semester_id'],
        ]);

        return redirect('/admin/admin-dashboard')->with('status', 'Academic session selection updated.');
    }
}
