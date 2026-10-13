<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\MailDeliveryGuard;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestForm(MailDeliveryGuard $mailDelivery): View
    {
        return view('auth.forgot-password', ['emailReady' => $mailDelivery->isReady()]);
    }

    public function sendLink(Request $request, MailDeliveryGuard $mailDelivery): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        if (! $mailDelivery->isReady()) {
            return back()->withErrors(['email' => 'Pengiriman email belum tersedia. Hubungi pengelola aplikasi.']);
        }

        try {
            Password::sendResetLink($validated);
        } catch (Throwable $error) {
            Log::error('Password reset email delivery failed', ['exception' => $error]);

            return back()->withErrors(['email' => 'Email belum berhasil dikirim. Silakan coba lagi nanti.']);
        }

        return back()->with('status', 'Jika alamat email terdaftar, tautan pengaturan ulang sedang dikirim. Periksa juga folder spam.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Tautan tidak valid atau telah kedaluwarsa. Silakan minta tautan baru.']);
        }

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diubah. Silakan masuk kembali.');
    }
}
