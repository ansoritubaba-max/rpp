<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\QuotaService;
use App\Services\Subscription\SubscriptionAccessException;
use App\Services\Subscription\TrialService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeOfferFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_trial_teacher_sees_upgrade_path_before_and_after_expiry(): void
    {
        $teacher = User::query()->create([
            'name' => 'Guru Trial', 'email' => 'trial-upgrade@example.test',
            'password' => 'password', 'role' => 'guru', 'status' => 'active',
        ]);
        $teacher->forceFill(['email_verified_at' => now()])->save();
        app(TrialService::class)->grant($teacher);

        $this->actingAs($teacher)->get(route('dashboard'))
            ->assertOk()->assertSee('Trial aktif')->assertSee('Upgrade paket')->assertSee('Paket aktif');

        $teacher->subscriptions()->firstOrFail()->update([
            'status' => 'expired', 'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Trial sudah berakhir')->assertSee('Lihat paket & upgrade', false)
            ->assertSee('Pilih paket');
        $this->get(route('subscription.index'))->assertOk()
            ->assertSee('Trial Anda telah berakhir')->assertSee('Lanjut ke pembayaran');
        $this->get(route('create.index'))->assertOk()->assertSee('Pilih paket & upgrade', false);
        $this->assertSame(1, $teacher->notifications()->where('type', 'subscription_expired')->count());
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $teacher->notifications()->where('type', 'subscription_expired')->count());

        try {
            app(QuotaService::class)->runGeneration($teacher, 'rpp', fn () => true);
            $this->fail('Paket kedaluwarsa tidak boleh membuat dokumen.');
        } catch (SubscriptionAccessException $error) {
            $this->assertSame('Masa aktif paket Anda telah berakhir.', $error->getMessage());
        }
    }

    public function test_expired_paid_package_is_not_mislabeled_as_trial(): void
    {
        $teacher = User::query()->create([
            'name' => 'Guru Berbayar', 'email' => 'paid-upgrade@example.test',
            'password' => 'password', 'role' => 'guru', 'status' => 'active',
        ]);
        $teacher->forceFill(['email_verified_at' => now()])->save();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $subscription = $teacher->subscriptions()->create([
            'plan_id' => $plan->id, 'starts_at' => now()->subDays(31),
            'expires_at' => now()->subDay(), 'status' => 'expired',
        ]);
        $teacher->payments()->create([
            'plan_id' => $plan->id, 'subscription_id' => $subscription->id,
            'amount' => 79000, 'payment_method' => 'Manual',
            'payment_date' => now()->subMonth()->toDateString(), 'status' => 'paid',
        ]);

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertSee('Paket sudah berakhir')->assertDontSee('Trial sudah berakhir');
        $this->get(route('subscription.index'))->assertOk()->assertSee('Masa paket Anda telah berakhir');
    }
}
