<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailConfigurationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config()->set('mail.default', 'log');
    }

    public function test_log_mailer_does_not_claim_registration_or_reset_email_was_delivered(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Pendaftaran lewat email belum tersedia');
        $this->post(route('register.store'), [
            'name' => 'Guru Baru',
            'email' => 'baru@example.test',
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.test']);

        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $this->post(route('password.email'), ['email' => $teacher->email])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $teacher->email]);
    }
}
