<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'regex:/^[a-z0-9_]+$/', 'max:80'],
            'content' => ['required', 'string', 'max:20000'],
        ];
    }
}
