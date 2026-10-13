<?php

namespace Tests\Feature;

use App\Models\AuthIdentity;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config()->set('services.google.client_id', 'test-client');
        config()->set('services.google.client_secret', 'test-secret');
    }

    public function test_verified_google_user_can_register_and_login_again(): void
    {
        Socialite::fake('google', $this->googleUser('google-123', 'baru@example.test'));

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $teacher = User::query()->where('email', 'baru@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($teacher);
        $this->assertNotNull($teacher->email_verified_at);
        $this->assertSame('guru', $teacher->role);
        $this->assertDatabaseHas('auth_identities', ['user_id' => $teacher->id, 'provider' => 'google', 'provider_user_id' => 'google-123']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $teacher->id, 'status' => 'active']);

        $this->post(route('logout'));
        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
        $this->assertSame(1, AuthIdentity::query()->where('provider_user_id', 'google-123')->count());
        $this->assertSame(1, User::query()->where('email', 'baru@example.test')->count());
    }

    public function test_existing_email_requires_explicit_link_from_authenticated_account(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        Socialite::fake('google', $this->googleUser('google-existing', $teacher->email));

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseMissing('auth_identities', ['provider_user_id' => 'google-existing']);

        $this->actingAs($teacher)->get(route('account.google.connect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('account.sessions.index'));
        $this->assertDatabaseHas('auth_identities', ['user_id' => $teacher->id, 'provider_user_id' => 'google-existing']);

        $this->post(route('logout'));
        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Socialite::fake('google', $this->googleUser('google-unverified', 'unverified@example.test', false));

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.test']);
    }

    public function test_google_account_with_different_email_cannot_be_linked(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        Socialite::fake('google', $this->googleUser('google-other-email', 'other@example.test'));

        $this->actingAs($teacher)->get(route('account.google.connect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('account.sessions.index'))->assertSessionHasErrors('google');
        $this->assertDatabaseMissing('auth_identities', ['provider_user_id' => 'google-other-email']);
    }

    public function test_unverified_local_account_can_verify_by_linking_same_google_email(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $teacher->forceFill(['email_verified_at' => null])->save();
        Socialite::fake('google', $this->googleUser('google-verify', $teacher->email));

        $this->actingAs($teacher)->get(route('verification.notice'))->assertOk()->assertSee('Verifikasi dengan Google');
        $this->get(route('account.google.connect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('account.sessions.index'));
        $this->assertNotNull($teacher->fresh()->email_verified_at);
        $this->assertDatabaseHas('auth_identities', ['user_id' => $teacher->id, 'provider_user_id' => 'google-verify']);
    }

    public function test_suspended_linked_account_cannot_login_with_google(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $teacher->authIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-suspended']);
        $teacher->update(['status' => 'suspended']);
        Socialite::fake('google', $this->googleUser('google-suspended', $teacher->email));

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_login_is_hidden_and_rejected_when_credentials_are_missing(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->get(route('login'))->assertOk()->assertDontSee('Lanjutkan dengan Google');
        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }

    public function test_failed_google_login_offers_email_registration_with_verification(): void
    {
        config()->set('mail.default', 'smtp');
        Notification::fake();

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('daftar lewat formulir')
            ->assertSee(route('register'), false);

        $this->get(route('register'))->assertOk()->assertSee('termasuk Gmail');
        $this->post(route('register.store'), [
            'name' => 'Guru Gmail',
            'email' => 'guru@gmail.com',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertRedirect(route('verification.notice'));

        $teacher = User::query()->where('email', 'guru@gmail.com')->firstOrFail();
        $this->assertNull($teacher->email_verified_at);
        Notification::assertSentTo($teacher, EmailVerificationCode::class);
    }

    private function googleUser(string $id, string $email, bool $verified = true): GoogleUser
    {
        return GoogleUser::fake([
            'id' => $id,
            'name' => 'Guru Google',
            'email' => $email,
            'email_verified' => $verified,
        ]);
    }
}
