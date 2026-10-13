<?php

namespace Tests\Feature;

use App\Models\GeneratorAbuseState;
use App\Models\OperationalEvent;
use App\Models\User;
use App\Services\Settings\SystemSettings;
use App\Services\Subscription\QuotaService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeneratorGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->settings([
            'generator_user_per_minute' => 50,
            'generator_feature_per_minute' => 50,
            'generator_ip_per_minute' => 100,
            'generator_endpoint_per_minute' => 100,
            'generator_burst_per_10_seconds' => 30,
        ]);
        DB::table('plan_features')->update(['generation_limit' => 100, 'regeneration_limit' => 100]);
    }

    public function test_feature_limit_rejects_next_request_without_spending_quota(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_feature_per_minute' => 2]);

        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        try {
            app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
            $this->fail('Permintaan ketiga seharusnya dibatasi.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('Terlalu banyak permintaan', $error->getMessage());
        }

        $this->assertSame(2, (int) DB::table('feature_usages')->where('feature_id', DB::table('features')->where('code', 'rpp')->value('id'))->value('generation_count'));
        app(QuotaService::class)->runGeneration($teacher, 'modul_ajar', fn () => true);
        $this->assertDatabaseHas('operational_events', ['user_id' => $teacher->id, 'type' => 'generator_limited']);
        $this->assertSame('warning', GeneratorAbuseState::query()->where('user_id', $teacher->id)->value('status'));
    }

    public function test_user_limit_is_shared_by_generation_and_regeneration(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_user_per_minute' => 1]);

        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        $this->expectException(DomainException::class);
        app(QuotaService::class)->runRegeneration($teacher, 'modul_ajar', fn () => true);
    }

    public function test_ip_and_endpoint_limits_apply_across_features(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_ip_per_minute' => 1]);
        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        try {
            app(QuotaService::class)->runGeneration($teacher, 'modul_ajar', fn () => true);
            $this->fail('Batas IP seharusnya menolak fitur lain.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('Terlalu banyak permintaan', $error->getMessage());
        }

        $this->settings(['generator_ip_per_minute' => 100, 'generator_endpoint_per_minute' => 1]);
        try {
            app(QuotaService::class)->runGeneration($teacher, 'modul_ajar', fn () => true);
            $this->fail('Batas endpoint seharusnya menolak fitur lain.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('Terlalu banyak permintaan', $error->getMessage());
        }
        $this->assertDatabaseCount('feature_usages', 1);
    }

    public function test_automation_burst_causes_temporary_block(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_burst_per_10_seconds' => 2]);
        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        app(QuotaService::class)->runGeneration($teacher, 'modul_ajar', fn () => true);

        try {
            app(QuotaService::class)->runGeneration($teacher, 'lkpd', fn () => true);
            $this->fail('Burst ketiga seharusnya diblokir.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('diblokir', $error->getMessage());
        }
        $this->assertSame('automation_burst', GeneratorAbuseState::query()->where('user_id', $teacher->id)->value('reason'));
    }

    public function test_repeated_limited_endpoint_requests_escalate_to_temporary_block(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_endpoint_per_minute' => 1, 'generator_failure_threshold' => 2]);
        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                app(QuotaService::class)->runGeneration($teacher, 'modul_ajar', fn () => true);
                $this->fail('Permintaan berulang seharusnya dibatasi.');
            } catch (DomainException) {
            }
        }

        $this->assertSame('endpoint_spam', GeneratorAbuseState::query()->where('user_id', $teacher->id)->value('reason'));
        $this->assertSame(2, OperationalEvent::query()->where('user_id', $teacher->id)->where('type', 'generator_limited')->count());
        $this->assertDatabaseCount('feature_usages', 1);
    }

    public function test_many_distinct_ips_create_a_temporary_block_that_expires(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_distinct_ip_threshold' => 3, 'generator_block_minutes' => 1]);

        foreach (['192.0.2.1', '192.0.2.2'] as $ip) {
            request()->server->set('REMOTE_ADDR', $ip);
            app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        }
        request()->server->set('REMOTE_ADDR', '192.0.2.3');
        try {
            app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
            $this->fail('IP ketiga seharusnya memicu blokir.');
        } catch (DomainException $error) {
            $this->assertStringContainsString('diblokir', $error->getMessage());
        }

        $state = GeneratorAbuseState::query()->where('user_id', $teacher->id)->firstOrFail();
        $this->assertSame('temporary_block', $state->status);
        $this->assertSame(2, OperationalEvent::query()->where('user_id', $teacher->id)->where('type', 'generator_attempt')->count());
        $this->assertSame(0, OperationalEvent::query()->where('user_id', $teacher->id)->where('type', 'generator_attempt')->whereNull('ip_fingerprint')->count());

        $state->update(['blocked_until' => now()->subSecond()]);
        request()->server->set('REMOTE_ADDR', '192.0.2.1');
        app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
        $this->assertSame('normal', $state->fresh()->status);
    }

    public function test_repeated_quota_rejections_trigger_block(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_failure_threshold' => 2]);
        $quota = app(QuotaService::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $quota->runGeneration($teacher, 'rpp', fn () => throw new DomainException('Data tidak dapat diproses.'));
            } catch (DomainException) {
            }
        }

        $this->assertSame('temporary_block', GeneratorAbuseState::query()->where('user_id', $teacher->id)->value('status'));
        $this->assertDatabaseHas('operational_events', ['user_id' => $teacher->id, 'type' => 'generator_blocked']);
        $this->assertDatabaseCount('feature_usages', 0);
    }

    public function test_provider_failures_are_logged_without_blocking_teacher(): void
    {
        $teacher = $this->teacher();
        $this->settings(['generator_failure_threshold' => 2]);
        $quota = app(QuotaService::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $quota->runGeneration($teacher, 'rpp', fn () => throw new \RuntimeException('Provider tidak tersedia.'));
            } catch (\RuntimeException) {
            }
        }

        $this->assertSame(2, OperationalEvent::query()->where('user_id', $teacher->id)->where('type', 'generator_failed')->count());
        $this->assertNotSame('temporary_block', GeneratorAbuseState::query()->where('user_id', $teacher->id)->value('status'));
        $quota->runGeneration($teacher, 'rpp', fn () => true);
        $this->assertDatabaseHas('feature_usages', ['generation_count' => 1]);
    }

    public function test_admin_can_set_generator_limits_and_teacher_cannot(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $teacher = $this->teacher();
        $data = [
            'app_name' => 'RuangAjar', 'support_email' => '', 'timezone' => 'Asia/Jakarta', 'paper_size' => 'a4',
            'margin_top' => 20, 'margin_right' => 18, 'margin_bottom' => 20, 'margin_left' => 18,
            'session_limit' => 3, 'maintenance_mode' => 0,
            'generator_user_per_minute' => 3, 'generator_feature_per_minute' => 2,
            'generator_ip_per_minute' => 50, 'generator_endpoint_per_minute' => 30,
            'generator_burst_per_10_seconds' => 10, 'generator_failure_threshold' => 5,
            'generator_distinct_ip_threshold' => 4, 'generator_block_minutes' => 15,
        ];

        $this->actingAs($teacher)->put(route('admin.settings.update'), $data)->assertForbidden();
        $this->actingAs($admin)->put(route('admin.settings.update'), $data)->assertRedirect();
        $this->assertSame(3, app(SystemSettings::class)->get('generator_user_per_minute'));
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Perlindungan generator');
    }

    private function teacher(): User
    {
        return User::query()->where('role', 'guru')->firstOrFail();
    }

    private function settings(array $values): void
    {
        app(SystemSettings::class)->setMany($values);
    }
}
