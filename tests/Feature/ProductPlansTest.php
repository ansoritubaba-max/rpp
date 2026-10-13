<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_three_packages_have_exact_prices_and_all_ten_feature_limits(): void
    {
        $expected = [
            'dasar' => [39000, 40, [
                'rpp' => [10, 5], 'modul_ajar' => [10, 5], 'silabus' => [5, 2],
                'lkpd' => [10, 5], 'soal' => [5, 2], 'kisi_kisi' => [0, 0],
                'prota' => [0, 0], 'promes' => [0, 0], 'atp' => [0, 0], 'bank_soal' => [0, 0],
            ]],
            'guru_pro' => [79000, 215, [
                'rpp' => [40, 15], 'modul_ajar' => [40, 15], 'silabus' => [15, 10],
                'lkpd' => [30, 15], 'soal' => [30, 15], 'kisi_kisi' => [20, 10],
                'prota' => [10, 5], 'promes' => [10, 5], 'atp' => [10, 5], 'bank_soal' => [10, 5],
            ]],
            'sekolah_sultan' => [199000, 745, [
                'rpp' => [150, 75], 'modul_ajar' => [150, 75], 'silabus' => [50, 25],
                'lkpd' => [100, 50], 'soal' => [100, 50], 'kisi_kisi' => [75, 35],
                'prota' => [30, 15], 'promes' => [30, 15], 'atp' => [30, 15], 'bank_soal' => [30, 15],
            ]],
        ];

        $this->assertSame(3, Plan::query()->where('is_active', true)->count());
        foreach ($expected as $key => [$price, $total, $limits]) {
            $plan = Plan::query()->with('features')->where('catalog_key', $key)->firstOrFail();
            $this->assertTrue($plan->is_active);
            $this->assertSame($price, $plan->currentPrice());
            $this->assertSame($total, $plan->totalMonthlyGenerations());
            $this->assertSame(30, $plan->duration_days);
            $this->assertCount(10, $plan->features);
            $this->assertNull($plan->reference_price);
            $this->assertFalse($plan->show_reference_price);
            $this->assertSame($key === 'guru_pro', $plan->is_recommended);
            foreach ($limits as $code => [$generate, $regenerate]) {
                $feature = $plan->features->firstWhere('code', $code);
                $this->assertNotNull($feature, "Fitur {$code} tidak ditemukan pada {$key}.");
                $this->assertSame($generate, $feature->pivot->generation_limit);
                $this->assertSame($regenerate, $feature->pivot->regeneration_limit);
            }
        }
    }

    public function test_legacy_subscription_remains_and_reseeding_does_not_overwrite_admin_changes(): void
    {
        $legacy = Plan::query()->where('name', 'Teacher Pro')->firstOrFail();
        $subscription = Subscription::query()->where('plan_id', $legacy->id)->firstOrFail();
        $this->assertFalse($legacy->is_active);
        $plan = Plan::query()->where('catalog_key', 'dasar')->firstOrFail();
        $plan->update(['description' => 'Diubah admin']);

        $this->seed(ProductPlansSeeder::class);

        $this->assertSame(4, Plan::query()->count());
        $this->assertSame('Diubah admin', $plan->fresh()->description);
        $this->assertSame($legacy->id, $subscription->fresh()->plan_id);
    }

    public function test_teacher_catalog_shows_actual_prices_without_unverified_crossed_prices(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();

        $this->actingAs($teacher)->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('Paket Dasar')
            ->assertSee('Paket Guru Pro')
            ->assertSee('Paket Sekolah / Sultan')
            ->assertSee('40 dokumen baru')
            ->assertSee('215 dokumen baru')
            ->assertSee('745 dokumen baru')
            ->assertSee('Paling direkomendasikan')
            ->assertDontSee('Rp 99.000')
            ->assertDontSee('Rp 249.000')
            ->assertDontSee('Rp 499.000');
    }
}
