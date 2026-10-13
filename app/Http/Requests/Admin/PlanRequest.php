<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount_percent' => ['sometimes', 'required', 'integer', 'between:0,90'],
            'discount_ends_at' => ['nullable', 'date'],
            'reference_price' => ['nullable', 'required_if:show_reference_price,1', 'numeric', 'gt:price', 'max:999999999'],
            'show_reference_price' => ['sometimes', 'required', 'boolean'],
            'is_recommended' => ['sometimes', 'required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'between:0,9999'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['required', 'boolean'],
            'features' => ['required', 'array'],
            'features.*.enabled' => ['required', 'boolean'],
            'features.*.generation_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'features.*.regeneration_limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
