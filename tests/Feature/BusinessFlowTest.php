<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'smtp');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_dynamic_plan_with_feature_limits(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $rpp = Feature::query()->where('code', 'rpp')->firstOrFail();
        $soal = Feature::query()->where('code', 'soal')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Paket Hemat',
            'description' => 'Paket fleksibel untuk guru.',
            'price' => 49000,
            'duration_days' => 30,
            'is_active' => true,
            'features' => [
                $rpp->id => ['enabled' => true, 'generation_limit' => 10, 'regeneration_limit' => 20],
                $soal->id => ['enabled' => true, 'generation_limit' => 25, 'regeneration_limit' => 30],
            ],
        ]);

        $response->assertRedirect(route('admin.plans.index'));
        $plan = Plan::query()->where('name', 'Paket Hemat')->firstOrFail();
        $this->assertDatabaseHas('plan_features', ['plan_id' => $plan->id, 'feature_id' => $rpp->id, 'generation_limit' => 10]);
        $this->assertDatabaseHas('plan_features', ['plan_id' => $plan->id, 'feature_id' => $soal->id, 'generation_limit' => 25]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.created', 'entity_id' => $plan->id]);
        $this->get(route('admin.plans.index'))->assertOk()->assertSee('Paket Hemat');
    }

    public function test_admin_can_create_update_and_assign_a_plan_to_teacher(): void
    {
        Notification::fake();
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $plan = Plan::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.teachers.store'), [
            'name' => 'Bapak Andi Pratama',
            'email' => 'andi@example.test',
            'phone' => '081234567890',
            'status' => 'active',
            'password' => 'Guru12345',
            'password_confirmation' => 'Guru12345',
        ])->assertRedirect();

        $teacher = User::query()->where('email', 'andi@example.test')->firstOrFail();
        $this->assertNull($teacher->email_verified_at);
        $code = null;
        Notification::assertSentTo($teacher, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->actingAs($teacher)->post(route('verification.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->post(route('admin.teachers.subscriptions.store', $teacher), [
            'plan_id' => $plan->id,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('subscriptions', ['user_id' => $teacher->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.created', 'entity_id' => $teacher->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.activated']);
        $this->get(route('admin.teachers.show', $teacher))->assertOk()->assertSee('Bapak Andi Pratama')->assertSee($plan->name);

        $this->actingAs($teacher)->get(route('subscription.index'))->assertOk()->assertSee($plan->name)->assertSee('Kuota fitur');
    }

    public function test_marking_payment_paid_activates_subscription_once(): void
    {
        $admin = User::query()->where('email', 'admin@ruangajar.id')->firstOrFail();
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();
        $plan = Plan::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.payments.store'), [
            'user_id' => $teacher->id,
            'plan_id' => $plan->id,
            'amount' => 79000,
            'payment_method' => 'Transfer Bank',
            'payment_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Pembayaran melalui rekening utama.',
        ])->assertRedirect(route('admin.payments.index'));

        $payment = Payment::query()->latest()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.status', $payment), ['status' => 'paid'])->assertRedirect();
        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->subscription_id);
        $this->assertSame(1, Subscription::query()->where('user_id', $teacher->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.paid', 'entity_id' => $payment->id]);

        $subscriptionCount = Subscription::query()->where('user_id', $teacher->id)->count();
        $this->patch(route('admin.payments.status', $payment), ['status' => 'paid'])->assertRedirect();
        $this->assertSame($subscriptionCount, Subscription::query()->where('user_id', $teacher->id)->count());

        $this->patch(route('admin.payments.status', $payment), ['status' => 'cancelled'])->assertRedirect();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'cancelled']);
        $this->assertSame(0, Subscription::query()->where('user_id', $teacher->id)->where('status', 'active')->count());
    }

    public function test_teacher_cannot_access_business_admin_pages(): void
    {
        $teacher = User::query()->where('email', 'guru@ruangajar.id')->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.teachers.index'))->assertForbidden();
        $this->get(route('admin.plans.index'))->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
