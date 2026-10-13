<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'min:2', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'])],
            'paper_size' => ['required', Rule::in(['a4', 'letter', 'legal'])],
            'margin_top' => ['required', 'numeric', 'between:5,40'],
            'margin_right' => ['required', 'numeric', 'between:5,40'],
            'margin_bottom' => ['required', 'numeric', 'between:5,40'],
            'margin_left' => ['required', 'numeric', 'between:5,40'],
            'session_limit' => ['required', 'integer', 'between:1,10'],
            'trial_days' => ['sometimes', 'required', 'integer', 'between:0,90'],
            'trial_plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'ai_usd_idr_rate' => ['nullable', 'numeric', 'between:1,1000000'],
            'maintenance_mode' => ['required', 'boolean'],
            'generator_user_per_minute' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'generator_feature_per_minute' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'generator_ip_per_minute' => ['sometimes', 'required', 'integer', 'between:1,1000'],
            'generator_endpoint_per_minute' => ['sometimes', 'required', 'integer', 'between:1,1000'],
            'generator_burst_per_10_seconds' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'generator_failure_threshold' => ['sometimes', 'required', 'integer', 'between:2,50'],
            'generator_distinct_ip_threshold' => ['sometimes', 'required', 'integer', 'between:2,50'],
            'generator_block_minutes' => ['sometimes', 'required', 'integer', 'between:1,1440'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=16,min_height=16,max_width=2000,max_height=2000'],
            'favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=16,min_height=16,max_width=2000,max_height=2000'],
        ];
    }
}
