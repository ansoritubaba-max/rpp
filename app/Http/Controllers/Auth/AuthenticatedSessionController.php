<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Monitoring\OperationalEventRecorder;
use App\Services\Auth\SessionLimitService;
use App\Services\Settings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, OperationalEventRecorder $events, SessionLimitService $sessions, SystemSettings $settings): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['status'] = 'active';
        $identity = Str::lower(trim($credentials['email']));
        $key = 'login:'.hash('sha256', $identity.'|'.$request->ip());
        $ipKey = 'login-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $events->record('login_rate_limited', ipAddress: $request->ip());
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan masuk. Tunggu beberapa menit lalu coba lagi.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($ipKey, 300);
            $events->record('login_failed', ipAddress: $request->ip());
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $sessions->enforce($request->user(), $request->session()->getId(), $settings);
        if (! $request->user()->isAdmin()) {
            $request->session()->put('subject_help_notice', true);
        }

        return redirect()->intended($request->user()->email_verified_at === null
            ? route('verification.notice')
            : ($request->user()->isAdmin() ? route('admin.dashboard') : route('dashboard')));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
