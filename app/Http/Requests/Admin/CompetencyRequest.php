<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'curriculum_id' => ['required', 'exists:curriculums,id'],
            'education_level_id' => ['required', 'exists:education_levels,id'],
            'vocational_program_id' => ['nullable', 'integer', 'exists:vocational_programs,id'],
            'grade_id' => ['required', 'exists:grades,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            'type' => ['required', Rule::in(['KI', 'KD', 'Indicator'])],
            'code' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string', 'min:10', 'max:10000'],
            'source_url' => ['nullable', 'url', 'max:1000'],
            'source_kind' => ['nullable', Rule::in(['official', 'summary', 'local'])],
        ];
    }
}
