<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    public function rules(): array
    {
        return ['rows' => 'required|array|max:5000', 'rows.*.assessment_component_id' => 'required|integer', 'rows.*.student_id' => 'required|integer', 'rows.*.score' => 'nullable|numeric|decimal:0,2', 'rows.*.absent' => 'sometimes|boolean', 'submit' => 'sometimes|boolean'];
    }
}
