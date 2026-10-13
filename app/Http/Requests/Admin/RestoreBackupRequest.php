<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', Rule::in(['PULIHKAN-'.$this->route('backup')?->id])],
            'acknowledge_loss' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'Ketik frasa konfirmasi persis seperti yang ditampilkan.',
            'acknowledge_loss.accepted' => 'Anda perlu menyetujui bahwa data setelah tanggal backup akan tertimpa.',
        ];
    }
}
