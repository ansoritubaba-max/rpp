<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedPasswordReset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccountSecurityFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'smtp');
        config()->set('session.driver', 'database');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_request_and_use_a_password_reset_link_once(): void
    {
        Notification::fake();
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();

        $this->get(route('password.request'))->assertOk()->assertSee('Lupa kata sandi?');
        $this->post(route('password.email'), ['email' => $teacher->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($teacher, QueuedPasswordReset::class, function (QueuedPasswordReset $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);
        $this->get(route('password.reset', ['token' => $token, 'email' => $teacher->email]))->assertOk();
        DB::table('sessions')->insert([
            'id' => 'old-device', 'user_id' => $teacher->id, 'ip_address' => '192.0.2.10',
            'user_agent' => 'Mozilla/5.0', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $payload = [
            'token' => $token,
            'email' => $teacher->email,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ];
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('SandiBaru123', $teacher->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    }

    public function test_password_reset_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $known = $this->post(route('password.email'), ['email' => 'guru@ruangajar.id'])->assertSessionHas('status');
        $unknown = $this->post(route('password.email'), ['email' => 'tidak-ada@example.test'])->assertSessionHas('status');

        $this->assertSame($known->baseResponse->getSession()->get('status'), $unknown->baseResponse->getSession()->get('status'));
    }

    public function test_login_is_temporarily_limited_after_repeated_failures(): void
    {
        $key = 'login:'.hash('sha256', 'guru@ruangajar.id|127.0.0.1');
        $ipKey = 'login-ip:'.hash('sha256', '127.0.0.1');
        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => 'guru@ruangajar.id', 'password' => 'salah'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => 'guru@ruangajar.id', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Tunggu beberapa menit lalu coba lagi.']);
        $this->assertGuest();

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);
        $this->post(route('login.store'), ['email' => 'guru@ruangajar.id', 'password' => 'password'])->assertRedirect('/dashboard');
    }
}
