<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCode extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $code)
    {
        $this->onQueue('mail')->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($notifiable->email_verified_at !== null) {
            return false;
        }

        $record = DB::table('otp_codes')->where('user_id', $notifiable->id)
            ->where('purpose', 'email_verification')->first();

        return $record && now()->lessThan($record->expires_at) && Hash::check($this->code, $record->code_hash);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode verifikasi email RuangAjar')
            ->greeting('Halo, '.$notifiable->name)
            ->line('Masukkan kode berikut untuk memverifikasi alamat email akun RuangAjar Anda:')
            ->line($this->code)
            ->line('Kode berlaku selama 10 menit dan hanya dapat digunakan sekali.')
            ->line('Jika Anda tidak membuat akun ini, abaikan email ini.');
    }
}
