<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'guru')],
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'payment_method' => ['required', 'string', 'max:60'],
            'payment_date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'status' => ['required', 'in:pending,paid,cancelled'],
        ];
    }
}
