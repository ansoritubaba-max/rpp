<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('education_levels')->ignore($this->route('level'))],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['school', 'madrasah'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
