<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\SessionLimitService;
use App\Services\Settings\SystemSettings;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirectLogin(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->withErrors(['google' => 'Login Google belum diaktifkan.']);
        }

        $request->session()->put('google_auth_intent', 'login');

        return Socialite::driver('google')->redirect();
    }

    public function redirectLink(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            $destination = $request->user()->email_verified_at === null ? 'verification.notice' : 'account.sessions.index';

            return redirect()->route($destination)->withErrors(['google' => 'Login Google belum diaktifkan.']);
        }

        $request->session()->put('google_auth_intent', 'link');

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, GoogleIdentityService $identities, SessionLimitService $sessions, SystemSettings $settings): RedirectResponse
    {
        $intent = $request->session()->pull('google_auth_intent');
        $destination = $intent === 'link' && Auth::check()
            ? ($request->user()->email_verified_at === null ? 'verification.notice' : 'account.sessions.index')
            : 'login';
        if (! $this->configured() || ! in_array($intent, ['login', 'link'], true) || $request->filled('error')) {
            return redirect()->route($destination)->withErrors(['google' => 'Masuk dengan Google dibatalkan atau sesi telah berakhir. Silakan coba lagi.']);
        }

        if (($intent === 'link' && ! Auth::check()) || ($intent === 'login' && Auth::check())) {
            return redirect()->route($destination)->withErrors(['google' => 'Sesi akun berubah. Silakan ulangi dari awal.']);
        }

        try {
            $google = Socialite::driver('google')->user();

            if ($intent === 'link') {
                $identities->link($request->user(), $google);

                return redirect()->route('account.sessions.index')->with('success', 'Akun Google berhasil dihubungkan.');
            }

            $user = $identities->loginOrRegister($google);
            Auth::login($user);
            $request->session()->regenerate();
            $user->forceFill(['last_login_at' => now()])->save();
            $sessions->enforce($user, $request->session()->getId(), $settings);
            if (! $user->isAdmin()) {
                $request->session()->put('subject_help_notice', true);
            }

            return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('dashboard'));
        } catch (DomainException $error) {
            return redirect()->route($destination)->withErrors(['google' => $error->getMessage()]);
        } catch (Throwable $error) {
            Log::warning('Google authentication failed', ['exception_class' => get_class($error)]);

            return redirect()->route($destination)->withErrors(['google' => 'Login Google belum berhasil. Silakan coba lagi.']);
        }
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
