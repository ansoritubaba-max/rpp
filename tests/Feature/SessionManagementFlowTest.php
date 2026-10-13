<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionManagementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'database');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_sees_only_own_sessions_and_can_revoke_another_device(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $this->addSession('teacher-other', $teacher->id, '192.0.2.10', 'Mozilla/5.0 Chrome/120.0');
        $this->addSession('admin-session', $admin->id, '192.0.2.20', 'Mozilla/5.0 Firefox/120.0');

        $this->actingAs($teacher)->get(route('account.sessions.index'))
            ->assertOk()
            ->assertSee('192.0.2.10')
            ->assertDontSee('192.0.2.20');

        $this->delete(route('account.sessions.destroy', 'admin-session'))->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);

        $this->delete(route('account.sessions.destroy', 'teacher-other'))->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'teacher-other']);
    }

    public function test_teacher_can_revoke_all_other_sessions_without_logging_out_current_device(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $this->addSession('teacher-phone', $teacher->id, '192.0.2.10', 'Mozilla/5.0 Mobile Safari/604.1');
        $this->addSession('teacher-laptop', $teacher->id, '192.0.2.11', 'Mozilla/5.0 Chrome/120.0');
        $this->addSession('admin-session', $admin->id, '192.0.2.20', 'Mozilla/5.0 Firefox/120.0');

        $this->actingAs($teacher)->get(route('account.sessions.index'))->assertOk();
        $this->delete(route('account.sessions.others'))->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'teacher-phone']);
        $this->assertDatabaseMissing('sessions', ['id' => 'teacher-laptop']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->get(route('account.sessions.index'))->assertOk();
    }

    public function test_guest_cannot_open_or_revoke_sessions(): void
    {
        $this->get(route('account.sessions.index'))->assertRedirect(route('login'));
        $this->delete(route('account.sessions.others'))->assertRedirect(route('login'));
    }

    private function addSession(string $id, int $userId, string $ip, string $agent): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }
}
