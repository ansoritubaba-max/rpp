<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return ['archive' => ['required', 'file', 'mimes:zip', 'max:512000']];
    }

    public function messages(): array
    {
        return [
            'archive.required' => 'Pilih berkas backup ZIP terlebih dahulu.',
            'archive.mimes' => 'Berkas harus berupa arsip ZIP backup RuangAjar.',
            'archive.max' => 'Arsip melebihi batas 500 MB. Sesuaikan batas upload hosting atau hubungi pengelola server.',
            'archive.uploaded' => 'Upload gagal. Periksa batas upload dan ruang kosong pada hosting.',
        ];
    }
}
