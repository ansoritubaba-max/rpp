<?php

namespace App\Http\Requests\Admin;

use App\Models\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Grade|null $grade */
        $grade = $this->route('grade');

        return [
            'name' => ['required', 'string', 'max:50'],
            'numeric_value' => [
                'required', 'integer', 'min:1', 'max:12',
                Rule::unique('grades')->where(fn ($query) => $query->where('education_level_id', $this->route('level')->id))->ignore($grade?->id),
            ],
            'phase' => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E', 'F'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
