<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:plans,id'],
            'starts_at' => ['required', 'date', 'before_or_equal:today'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
