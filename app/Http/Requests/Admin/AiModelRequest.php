<?php

namespace App\Http\Requests\Admin;

use App\Models\AiModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var AiModel|null $model */
        $model = $this->route('model');

        return [
            'provider_id' => ['required', 'integer', 'exists:ai_providers,id'],
            'name' => ['required', 'string', 'max:100'],
            'model_key' => [
                'required', 'string', 'max:200',
                Rule::unique('ai_models')->where(fn ($query) => $query->where('provider_id', $this->integer('provider_id')))->ignore($model?->id),
            ],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:180'],
            'estimated_input_cost' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'estimated_output_cost' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'internal_daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'cooldown_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
        ];
    }
}
