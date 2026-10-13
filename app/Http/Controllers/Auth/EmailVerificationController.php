<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->email_verified_at !== null) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function verify(VerifyEmailCodeRequest $request, EmailOtpService $otp): RedirectResponse
    {
        if (! $otp->verify($request->user(), $request->validated('code'))) {
            return back()->withErrors(['code' => 'Kode salah, kedaluwarsa, atau batas percobaan tercapai. Minta kode baru jika perlu.']);
        }

        return redirect()->route('dashboard')->with('success', 'Alamat email berhasil diverifikasi.');
    }

    public function resend(Request $request, EmailOtpService $otp): RedirectResponse
    {
        if ($request->user()->email_verified_at !== null) {
            return redirect()->route('dashboard');
        }

        try {
            $otp->send($request->user());
        } catch (Throwable $error) {
            Log::error('Failed to send verification code', ['user_id' => $request->user()->id, 'exception' => $error]);
            return back()->withErrors(['code' => 'Kode belum berhasil dikirim. Silakan coba lagi nanti.']);
        }

        return back()->with('status', 'Kode baru sedang dikirim. Periksa kotak masuk atau folder spam.');
    }
}
