<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AiRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'routes' => ['required', 'array'],
            'routes.*' => ['required', 'array'],
            'routes.*.*.enabled' => ['required', 'boolean'],
            'routes.*.*.priority' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
