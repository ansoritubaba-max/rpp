<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required' => $this->boolean('is_required'),
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    public function rules(): array
    {
        $template = $this->route('template');
        $section = $this->route('section');

        return [
            'section_key' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('template_sections')->where('template_id', $template?->getKey())->ignore($section?->getKey()),
            ],
            'title' => ['required', 'string', 'max:150'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'source_type' => ['required', 'in:static,database,hybrid,ai'],
            'preset_content' => ['nullable', 'string', 'max:10000'],
            'ai_task_key' => ['nullable', 'string', 'max:150'],
            'is_required' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
