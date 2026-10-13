<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('subjects')->where(fn ($query) => $query->where('education_level_id', $this->route('level')->id))->ignore($subject?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'vocational_program_id' => ['nullable', 'integer', Rule::exists('vocational_programs', 'id')->where('education_level_id', $this->route('level')->id)],
            'is_religious' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
