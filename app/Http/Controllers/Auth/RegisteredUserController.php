<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Subscription\TrialService;
use App\Services\Settings\SystemSettings;
use App\Services\Auth\EmailOtpService;
use App\Services\Auth\MailDeliveryGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(MailDeliveryGuard $mailDelivery): View
    {
        return view('auth.register', [
            'emailReady' => $mailDelivery->isReady(),
            'trialDays' => (int) app(SystemSettings::class)->get('trial_days'),
        ]);
    }

    public function store(RegisterRequest $request, EmailOtpService $otp, MailDeliveryGuard $mailDelivery, TrialService $trial): RedirectResponse
    {
        if (! $mailDelivery->isReady()) {
            return back()->withInput($request->safe()->only(['name', 'email']))
                ->withErrors(['email' => 'Pendaftaran dengan email belum tersedia karena layanan email belum aktif.']);
        }

        $user = DB::transaction(function () use ($request, $trial) {
            $user = User::query()->create($request->safe()->only(['name', 'email', 'password']) + [
                'role' => 'guru',
                'status' => 'active',
            ]);

            $trial->grant($user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('subject_help_notice', true);

        try {
            $otp->send($user);
        } catch (Throwable $error) {
            Log::error('Failed to send registration verification code', ['user_id' => $user->id, 'exception' => $error]);

            return redirect()->route('verification.notice')->withErrors(['code' => 'Kode belum berhasil dikirim. Gunakan tombol kirim ulang.']);
        }

        return redirect()->route('verification.notice')->with('status', 'Kode verifikasi sedang dikirim ke email Anda.');
    }
}
