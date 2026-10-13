<?php

namespace App\Services\Auth;

use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\Subscription\TrialService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GoogleUser;

class GoogleIdentityService
{
    public function loginOrRegister(GoogleUser $google): User
    {
        [$providerId, $email, $name] = $this->validatedProfile($google);

        return DB::transaction(function () use ($providerId, $email, $name) {
            $identity = AuthIdentity::query()
                ->where('provider', 'google')
                ->where('provider_user_id', $providerId)
                ->lockForUpdate()
                ->first();

            if ($identity) {
                $user = User::query()->find($identity->user_id);
                if (! $user || $user->status !== 'active') {
                    throw new DomainException('Akun ini tidak dapat digunakan. Hubungi pengelola aplikasi.');
                }

                return $user;
            }

            if (User::query()->withTrashed()->where('email', $email)->exists()) {
                throw new DomainException('Email ini sudah terdaftar. Masuk dengan kata sandi, lalu hubungkan Google dari halaman Perangkat Masuk.');
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(64),
                'role' => 'guru',
                'status' => 'active',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            app(TrialService::class)->grant($user);

            $user->authIdentities()->create([
                'provider' => 'google',
                'provider_user_id' => $providerId,
            ]);

            return $user;
        }, 3);
    }

    public function link(User $user, GoogleUser $google): void
    {
        [$providerId, $email] = $this->validatedProfile($google);

        if ($user->status !== 'active' || Str::lower($user->email) !== $email) {
            throw new DomainException('Gunakan akun Google dengan email yang sama dan sudah terverifikasi.');
        }

        DB::transaction(function () use ($user, $providerId) {
            $linked = AuthIdentity::query()
                ->where('provider', 'google')
                ->where('provider_user_id', $providerId)
                ->lockForUpdate()
                ->first();
            if ($linked && $linked->user_id !== $user->id) {
                throw new DomainException('Akun Google ini sudah terhubung ke pengguna lain.');
            }

            $current = $user->authIdentities()->where('provider', 'google')->lockForUpdate()->first();
            if ($current && $current->provider_user_id !== $providerId) {
                throw new DomainException('Akun ini sudah terhubung ke akun Google lain.');
            }

            if (! $current) {
                $user->authIdentities()->create([
                    'provider' => 'google',
                    'provider_user_id' => $providerId,
                ]);
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }, 3);
    }

    private function validatedProfile(GoogleUser $google): array
    {
        $providerId = trim((string) $google->getId());
        $email = Str::lower(trim((string) $google->getEmail()));
        $name = trim((string) $google->getName());
        $verified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($providerId === '' || strlen($providerId) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 255 || ! $verified) {
            throw new DomainException('Google tidak memberikan identitas dan email terverifikasi yang valid.');
        }

        return [$providerId, $email, Str::limit($name !== '' ? $name : Str::before($email, '@'), 100, '')];
    }
}
