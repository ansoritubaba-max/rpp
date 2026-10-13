<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\User;
use App\Services\Auth\SessionLimitService;
use App\Services\Settings\SystemSettings;
use App\Services\Subscription\QuotaService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingsAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_only_admin_can_change_settings_and_uploaded_brand_is_publicly_viewable(): void
    {
        Storage::fake('local');
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $this->actingAs($teacher)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Pengaturan Sistem');

        $this->put(route('admin.settings.update'), $this->settingsData() + [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])->assertRedirect();

        $settings = app(SystemSettings::class);
        $this->assertSame('Ruang Guru', $settings->get('app_name'));
        $this->assertSame(2, $settings->get('session_limit'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.settings_updated']);
        $this->get(route('branding.show', 'logo'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->get(route('branding.show', 'favicon'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->get('/')->assertOk()->assertSee('Ruang Guru')->assertSee('/branding/logo');

        $this->actingAs($teacher)->put(route('admin.settings.update'), $this->settingsData())->assertForbidden();
        $this->assertSame('Ruang Guru', $settings->get('app_name'));
    }

    public function test_maintenance_blocks_teachers_but_keeps_admin_access_and_logout(): void
    {
        app(SystemSettings::class)->setMany(['maintenance_mode' => true]);
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $this->actingAs($teacher)->get(route('dashboard'))->assertStatus(503)->assertSee('Layanan sedang dipelihara');
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();
        $this->put(route('admin.settings.update'), $this->settingsData())->assertRedirect();
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
    }

    public function test_notifications_cover_expiry_quota_success_failure_and_read_state(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $subscription = $teacher->subscriptions()->firstOrFail();
        $subscription->update(['expires_at' => now()->addDays(3)]);
        $feature = Feature::query()->where('code', 'rpp')->firstOrFail();
        DB::table('plan_features')->where('plan_id', $subscription->plan_id)->where('feature_id', $feature->id)->update(['generation_limit' => 1]);

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $teacher->notifications()->where('type', 'subscription_expiring')->count());

        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $teacher->id, 'type' => 'generation_success']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $teacher->id, 'type' => 'quota_low']);
        try {
            app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
            $this->fail('Pembuatan kedua seharusnya ditolak oleh kuota.');
        } catch (\DomainException) {
            $this->assertDatabaseHas('user_notifications', ['user_id' => $teacher->id, 'type' => 'generation_failed']);
        }
        $this->assertDatabaseHas('feature_usages', ['feature_id' => $feature->id, 'generation_count' => 1]);

        $notification = $teacher->notifications()->where('type', 'quota_low')->firstOrFail();
        $this->get(route('notifications.index'))->assertOk()->assertSee('Kuota hampir habis');
        $this->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $teacher->notifications()->whereNull('read_at')->count());

        $other = User::factory()->create(['role' => 'guru', 'email_verified_at' => now()]);
        $this->actingAs($other)->patch(route('notifications.read', $notification))->assertForbidden();
    }

    public function test_session_limit_keeps_only_the_newest_other_session(): void
    {
        config()->set('session.driver', 'database');
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        app(SystemSettings::class)->setMany(['session_limit' => 2]);
        foreach (['old' => 20, 'new' => 1] as $id => $minutesAgo) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $teacher->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => '', 'last_activity' => now()->subMinutes($minutesAgo)->timestamp]);
        }

        app(SessionLimitService::class)->enforce($teacher, 'current', app(SystemSettings::class));

        $this->assertDatabaseMissing('sessions', ['id' => 'old']);
        $this->assertDatabaseHas('sessions', ['id' => 'new']);
    }

    private function settingsData(): array
    {
        return [
            'app_name' => 'Ruang Guru', 'support_email' => 'bantuan@example.test',
            'timezone' => 'Asia/Jakarta', 'paper_size' => 'a4',
            'margin_top' => 20, 'margin_right' => 18, 'margin_bottom' => 20, 'margin_left' => 18,
            'session_limit' => 2, 'maintenance_mode' => 0,
        ];
    }
}
