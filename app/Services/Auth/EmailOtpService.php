<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class EmailOtpService
{
    public function __construct(private readonly MailDeliveryGuard $mailDelivery) {}

    public function send(User $user): void
    {
        $this->mailDelivery->ensureReady();
        $code = sprintf('%06d', random_int(0, 999999));
        DB::table('otp_codes')->updateOrInsert(
            ['user_id' => $user->id, 'purpose' => 'email_verification'],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        Notification::sendNow($user, new EmailVerificationCode($code));
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $record = DB::table('otp_codes')
                ->where('user_id', $user->id)
                ->where('purpose', 'email_verification')
                ->lockForUpdate()
                ->first();

            if (! $record || $record->attempts >= 5 || now()->greaterThan($record->expires_at)) {
                return false;
            }

            if (! Hash::check($code, $record->code_hash)) {
                DB::table('otp_codes')->where('id', $record->id)->increment('attempts');

                return false;
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            DB::table('otp_codes')->where('id', $record->id)->delete();

            return true;
        });
    }
}
