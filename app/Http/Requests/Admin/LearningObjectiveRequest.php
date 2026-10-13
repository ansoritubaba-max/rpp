<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LearningObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string', 'min:10', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
