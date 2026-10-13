<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', Rule::in(['google', 'groq', 'openrouter', 'openai', 'custom'])],
            'base_url' => ['required', 'url:http,https', 'max:500'],
            'api_key' => [Rule::requiredIf(! $this->route('provider')), 'nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
