<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Settings\SystemSettings;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessKpiDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 12:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_does_not_invent_a_ratio_without_exchange_rate_or_revenue(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Tingkat perpanjangan paket')->assertSee('Biaya AI / pendapatan')
            ->assertSee('Isi kurs USD/IDR');

        $this->assertNull($response->viewData('businessKpis')['renewalRatePercent']);
        $this->assertNull($response->viewData('businessKpis')['aiCostRevenuePercent']);
    }

    public function test_dashboard_calculates_paid_renewals_and_converts_ai_cost_with_admin_rate(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $expiry = $previousMonthStart->copy()->addDays(14)->endOfDay();

        foreach ([1, 2] as $number) {
            $teacher = User::factory()->create(['role' => 'guru', 'email' => "kpi-{$number}@example.test"]);
            $old = $teacher->subscriptions()->create([
                'plan_id' => $plan->id,
                'starts_at' => $previousMonthStart->copy()->subDays(15),
                'expires_at' => $expiry,
                'status' => 'expired',
            ]);
            $this->paidPayment($teacher, $plan, $old->id, $previousMonthStart, 79000);

            if ($number === 1) {
                $new = $teacher->subscriptions()->create([
                    'plan_id' => $plan->id,
                    'starts_at' => $expiry->copy()->addDay(),
                    'expires_at' => $expiry->copy()->addDays(31),
                    'status' => 'active',
                ]);
                $this->paidPayment($teacher, $plan, $new->id, $expiry->copy()->addDay(), 79000);
            }
        }

        $currentPayer = User::factory()->create(['role' => 'guru', 'email' => 'kpi-current@example.test']);
        $currentSubscription = $currentPayer->subscriptions()->create([
            'plan_id' => $plan->id, 'starts_at' => now(), 'expires_at' => now()->addDays(30), 'status' => 'active',
        ]);
        $this->paidPayment($currentPayer, $plan, $currentSubscription->id, now(), 100000);
        AiUsageLog::query()->create([
            'user_id' => $currentPayer->id,
            'input_tokens' => 100,
            'output_tokens' => 100,
            'estimated_cost' => '0.250000',
            'status' => 'success',
            'duration_ms' => 100,
        ]);
        app(SystemSettings::class)->setMany(['ai_usd_idr_rate' => 16000]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('50,0%');
        $kpis = $response->viewData('businessKpis');
        $this->assertSame(2, $kpis['renewalEligible']);
        $this->assertSame(1, $kpis['renewalCount']);
        $this->assertSame(50.0, $kpis['renewalRatePercent']);
        $this->assertSame(100000.0, $kpis['monthlyRevenue']);
        $this->assertSame(4000.0, $kpis['monthlyAiCostIdr']);
        $this->assertSame(4.0, $kpis['aiCostRevenuePercent']);
        $response->assertSee('4,00%');
    }

    private function paidPayment(User $teacher, Plan $plan, int $subscriptionId, Carbon $date, int $amount): Payment
    {
        return $teacher->payments()->create([
            'plan_id' => $plan->id,
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'payment_method' => 'Manual',
            'payment_date' => $date->toDateString(),
            'status' => 'paid',
            'verified_at' => $date,
        ]);
    }
}
