<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'smtp');
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
    }

    public function test_new_teacher_must_verify_email_before_using_the_app(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Guru Baru',
            'email' => 'baru@example.test',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertRedirect(route('verification.notice'));

        $teacher = User::query()->where('email', 'baru@example.test')->firstOrFail();
        $this->assertNull($teacher->email_verified_at);
        $this->assertAuthenticatedAs($teacher);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verifikasi alamat email');

        $code = null;
        Notification::assertSentTo($teacher, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->post(route('verification.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertNotNull($teacher->fresh()->email_verified_at);
        $this->assertDatabaseMissing('otp_codes', ['user_id' => $teacher->id]);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_wrong_and_expired_codes_do_not_verify_an_account(): void
    {
        $teacher = User::factory()->unverified()->create();
        $this->actingAs($teacher)->post(route('verification.resend'))->assertRedirect();
        $record = DB::table('otp_codes')->where('user_id', $teacher->id)->first();
        $this->assertNotNull($record);
        $this->assertNotSame('000000', $record->code_hash);
        $correctCode = null;
        Notification::assertSentTo($teacher, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$correctCode) {
            $correctCode = $notification->code;

            return true;
        });
        $wrongCode = sprintf('%06d', (((int) $correctCode) + 1) % 1000000);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('verification.verify'), ['code' => $wrongCode])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, DB::table('otp_codes')->where('user_id', $teacher->id)->value('attempts'));
        $this->post(route('verification.verify'), ['code' => $correctCode])->assertSessionHasErrors('code');
        $this->assertNull($teacher->fresh()->email_verified_at);

        DB::table('otp_codes')->where('user_id', $teacher->id)->update(['expires_at' => now()->subMinute(), 'attempts' => 0]);
        $this->post(route('verification.verify'), ['code' => $correctCode])->assertSessionHasErrors('code');
        $this->assertNull($teacher->fresh()->email_verified_at);
    }

    public function test_unverified_teacher_cannot_access_documents_or_admin_pages(): void
    {
        $teacher = User::factory()->unverified()->create();
        $this->actingAs($teacher)->get(route('documents.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('account.sessions.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_changing_teacher_email_requires_new_verification_and_closes_old_sessions(): void
    {
        config()->set('session.driver', 'database');
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        DB::table('sessions')->insert([
            'id' => 'old-teacher-device', 'user_id' => $teacher->id, 'ip_address' => '192.0.2.10',
            'user_agent' => 'Mozilla/5.0', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)->patch(route('admin.teachers.update', $teacher), [
            'name' => $teacher->name,
            'email' => 'baru-guru@example.test',
            'phone' => $teacher->phone,
            'status' => 'active',
        ])->assertRedirect(route('admin.teachers.show', $teacher));

        $this->assertSame('baru-guru@example.test', $teacher->fresh()->email);
        $this->assertNull($teacher->fresh()->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-teacher-device']);
        Notification::assertSentTo($teacher, EmailVerificationCode::class);
        $this->actingAs($teacher->fresh())->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }
}
