<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class DuplicateRppRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['title', 'topic', 'subtopic'])
            ->filter(fn ($field) => is_string($this->input($field)))
            ->mapWithKeys(fn ($field) => [$field => trim(strip_tags($this->input($field)))])
            ->all());
    }

    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && $document->feature()->where('code', 'rpp')->exists()
            && ($this->user()?->can('update', $document) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'min:3', 'max:180'],
            'subtopic' => ['nullable', 'string', 'max:180'],
            'meetings' => ['required', 'integer', 'min:1', 'max:20'],
            'lesson_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
