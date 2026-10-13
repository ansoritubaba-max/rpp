<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class TeacherSubjectHelpNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_email_login_shows_notice_once_per_teacher_login_but_not_for_admin(): void
    {
        $this->post(route('login.store'), [
            'email' => 'guru@ruangajar.id',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mata pelajaran belum tersedia?')
            ->assertSee('0813 5785 3399')
            ->assertSee('https://wa.me/6281357853399', false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Mata pelajaran belum tersedia?');

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), [
            'email' => 'admin@ruangajar.id',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Mata pelajaran belum tersedia?');
    }

    public function test_google_login_also_shows_notice_once(): void
    {
        config()->set('services.google.client_id', 'test-client');
        config()->set('services.google.client_secret', 'test-secret');
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'subject-help-google',
            'name' => 'Guru Google',
            'email' => 'guru-google@example.test',
            'email_verified' => true,
        ]));

        $this->get(route('google.redirect'))->assertRedirect();
        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Mata pelajaran belum tersedia?');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Mata pelajaran belum tersedia?');
    }

    public function test_new_teacher_sees_notice_after_email_verification(): void
    {
        config()->set('mail.default', 'smtp');
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Guru Baru',
            'email' => 'guru-baru@example.test',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertRedirect(route('verification.notice'));

        $teacher = User::query()->where('email', 'guru-baru@example.test')->firstOrFail();
        $code = null;
        Notification::assertSentTo($teacher, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $this->get(route('verification.notice'))->assertOk()->assertDontSee('Mata pelajaran belum tersedia?');
        $this->post(route('verification.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Mata pelajaran belum tersedia?');
    }
}
