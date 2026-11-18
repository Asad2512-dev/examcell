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

        return $rules;
    }
}
