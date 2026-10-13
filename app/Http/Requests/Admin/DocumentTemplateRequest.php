<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'curriculum_id' => $this->input('curriculum_id') ?: null,
            'education_level_id' => $this->input('education_level_id') ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'feature_id' => ['required', 'integer', 'exists:features,id'],
            'curriculum_id' => ['nullable', 'integer', 'exists:curriculums,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'paper_size' => ['required', 'in:a4,letter,legal'],
            'orientation' => ['required', 'in:portrait,landscape'],
            'margin_top' => ['required', 'numeric', 'min:5', 'max:50'],
            'margin_right' => ['required', 'numeric', 'min:5', 'max:50'],
            'margin_bottom' => ['required', 'numeric', 'min:5', 'max:50'],
            'margin_left' => ['required', 'numeric', 'min:5', 'max:50'],
            'font_family' => ['required', 'in:DejaVu Sans,DejaVu Serif,Helvetica,Times-Roman,Courier'],
            'font_size' => ['required', 'numeric', 'min:8', 'max:16'],
            'line_height' => ['required', 'numeric', 'min:1', 'max:2.5'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
