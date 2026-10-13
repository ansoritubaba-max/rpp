<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'npsn' => ['nullable', 'string', 'max:30'],
            'nsm' => ['nullable', 'string', 'max:30'],
            'foundation_name' => ['nullable', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'principal_identifier' => ['nullable', 'string', 'max:60'],
            'logo_left' => ['nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000'],
            'logo_right' => ['nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000'],
            'remove_logo_left' => ['nullable', 'boolean'],
            'remove_logo_right' => ['nullable', 'boolean'],
        ];
    }
}
