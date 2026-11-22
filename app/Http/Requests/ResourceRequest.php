<?php

namespace App\Http\Requests;

use App\Support\Resources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        [$model,$rules] = Resources::get($this->route('resource'));
        $record = $this->route('id');
        $table = (new $model)->getTable();
        foreach (['code', 'email', 'roll_number'] as $f) {
            if (isset($rules[$f])) {
                $rules[$f] = [...explode('|', $rules[$f]), Rule::unique($table, $f)->ignore($record)];
            }
        } if (in_array($table, ['departments', 'academic_sessions', 'grading_schemes', 'rooms'])) {
            $rules['name'] = [...explode('|', $rules['name']), Rule::unique($table, 'name')->ignore($record)];
        } if ($table === 'users' && ! $record) {
            $rules['password'] = 'required|string|min:12|max:255';
        }

        // Rooms can be created from a simple seat count or from their real physical
        // grid.  Keeping these fields on the same request makes the layout the
        // source of truth from the first save (instead of asking an administrator
        // to enter a capacity and correct it on a second screen).
        if ($table === 'rooms') {
            $rules['capacity'] = 'required_unless:layout_mode,grid|nullable|integer|between:1,2000';
            $rules += [
                'layout_mode' => 'nullable|in:capacity,grid',
                'rows' => 'required_if:layout_mode,grid|nullable|integer|between:1,40',
                'cols' => 'required_if:layout_mode,grid|nullable|integer|between:1,30',
                'aisles' => 'nullable|string|max:200',
                'disabled' => 'nullable|string|max:2000',
                'accessible' => 'nullable|string|max:500',
                'door_side' => 'nullable|required_if:layout_mode,grid|in:left,right',
            ];
        }

        return $rules;
    }
}
