<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'type' => ['nullable', 'string', Rule::exists('features', 'code')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')],
            'status' => ['nullable', Rule::in(['draft', 'generated', 'edited', 'archived'])],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'kata pencarian', 'type' => 'jenis dokumen', 'subject_id' => 'mata pelajaran',
            'grade_id' => 'kelas', 'status' => 'status', 'date_from' => 'tanggal mulai', 'date_to' => 'tanggal akhir',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('documents.index');
    }
}
