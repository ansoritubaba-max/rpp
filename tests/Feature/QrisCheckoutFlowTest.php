<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Settings\SystemSettings;
use App\Services\Subscription\TrialService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QrisCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-test');
        config()->set('services.midtrans.client_key', 'SB-Mid-client-test');
        config()->set('services.midtrans.merchant_id', null);
        config()->set('services.midtrans.production', false);
    }

    public function test_trial_duration_and_plan_are_configurable_and_granted_once(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'dasar')->firstOrFail();
        $settings = app(SystemSettings::class);
        $settings->setMany(['trial_days' => 3, 'trial_plan_id' => $plan->id]);
        $teacher = User::query()->create(['name' => 'Guru Baru', 'email' => 'baru@example.test', 'password' => 'password', 'role' => 'guru', 'status' => 'active']);

        app(TrialService::class)->grant($teacher);
        app(TrialService::class)->grant($teacher);

        $this->assertSame(1, $teacher->subscriptions()->count());
        $this->assertSame($plan->id, $teacher->subscriptions()->firstOrFail()->plan_id);
        $this->assertSame(now()->addDays(3)->toDateString(), $teacher->subscriptions()->firstOrFail()->expires_at->toDateString());
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Trial guru baru');
    }

    public function test_snap_checkout_uses_real_discount_and_only_owner_can_open_payment(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $plan->update(['discount_percent' => 20, 'discount_ends_at' => now()->addDay()]);
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => function (ClientRequest $request) {
            $body = $request->data();
            $this->assertSame(63200, $body['transaction_details']['gross_amount']);

            return Http::response([
                'token' => '12345678-1234-1234-1234-123456789abc',
            ], 201);
        }]);

        $this->actingAs($teacher)->get(route('subscription.index'))->assertOk()->assertSee('Hemat 20%')->assertSee('63.200');
        $this->post(route('checkout.store', $plan))->assertRedirect();
        $payment = Payment::query()->where('gateway', 'midtrans')->firstOrFail();
        $this->assertSame('63200.00', $payment->amount);
        $this->assertNotNull($payment->snap_token);
        $this->get(route('checkout.show', $payment))->assertOk()->assertSee('Buka pembayaran aman');
        $other = User::query()->create(['name' => 'Guru Lain', 'email' => 'lain@example.test', 'password' => 'password', 'role' => 'guru', 'status' => 'active']);
        $other->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($other)->get(route('checkout.show', $payment))->assertNotFound();
    }

    public function test_only_signed_matching_settlement_activates_subscription_once(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $payment = $teacher->payments()->create([
            'plan_id' => $plan->id, 'amount' => 79000, 'payment_method' => 'QRIS',
            'payment_date' => now()->toDateString(), 'status' => 'pending',
            'gateway' => 'midtrans', 'gateway_order_id' => 'RA-TEST-1',
        ]);
        $payload = [
            'order_id' => 'RA-TEST-1', 'status_code' => '200', 'gross_amount' => '79000.00',
            'payment_type' => 'qris', 'transaction_status' => 'settlement', 'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test');

        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['signature_key' => 'wrong']))->assertForbidden();
        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['gross_amount' => '79001.00', 'signature_key' => hash('sha512', 'RA-TEST-120079001.00SB-Mid-server-test')]))->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.payments.status', $payment), ['status' => 'paid'])->assertForbidden();
        Auth::logout();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(2, $teacher->subscriptions()->count());
        $this->assertSame(1, $teacher->subscriptions()->where('status', 'active')->count());

        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['transaction_status' => 'refund']))->assertOk();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(0, $teacher->subscriptions()->where('status', 'active')->count());
    }

    public function test_gateway_rejection_is_recorded_for_admin_without_exposing_technical_error_to_teacher(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'error_messages' => ['Payment method unavailable'],
        ], 400)]);

        $this->actingAs($teacher)->from(route('subscription.index'))
            ->post(route('checkout.store', $plan))
            ->assertRedirect(route('subscription.index'))
            ->assertSessionHasErrors('checkout');

        $payment = Payment::query()->where('gateway', 'midtrans')->firstOrFail();
        $this->assertSame('cancelled', $payment->status);
        $this->assertSame('SNAP_TOKEN_REJECTED', $payment->gateway_error_code);
        $this->assertNull($payment->snap_token);
        $this->actingAs($admin)->get(route('admin.payments.index'))
            ->assertOk()->assertSee('SNAP_TOKEN_REJECTED')->assertSee('Payment method unavailable')
            ->assertSee('Sandbox (uji coba)');
    }

    public function test_snap_webhook_accepts_valid_bank_transfer_but_not_wrong_merchant_or_amount(): void
    {
        config()->set('services.midtrans.merchant_id', 'G-TEST');
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $payment = $teacher->payments()->create([
            'plan_id' => $plan->id, 'amount' => 79000, 'payment_method' => 'Midtrans Snap',
            'payment_date' => now()->toDateString(), 'status' => 'pending',
            'gateway' => 'midtrans', 'gateway_order_id' => 'RA-SNAP-TEST-1',
            'snap_token' => '12345678-1234-1234-1234-123456789abc',
        ]);
        $payload = [
            'order_id' => $payment->gateway_order_id, 'status_code' => '200',
            'gross_amount' => '79000.00', 'payment_type' => 'bank_transfer',
            'merchant_id' => 'G-TEST', 'transaction_status' => 'settlement',
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-Mid-server-test');

        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['merchant_id' => 'G-OTHER']))->assertForbidden();
        $this->postJson(route('webhooks.midtrans'), array_replace($payload, ['gross_amount' => '79001.00']))->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->subscription_id);
    }

    public function test_teacher_can_reconcile_status_with_midtrans_but_not_accept_a_mismatched_amount(): void
    {
        $teacher = User::query()->where('role', 'guru')->firstOrFail();
        $other = User::query()->create(['name' => 'Guru Lain', 'email' => 'other-qris@example.test', 'password' => 'password', 'role' => 'guru', 'status' => 'active']);
        $other->forceFill(['email_verified_at' => now()])->save();
        $plan = Plan::query()->where('catalog_key', 'guru_pro')->firstOrFail();
        $payment = $teacher->payments()->create([
            'plan_id' => $plan->id, 'amount' => 79000, 'payment_method' => 'QRIS',
            'payment_date' => now()->toDateString(), 'status' => 'pending',
            'gateway' => 'midtrans', 'gateway_order_id' => 'RA-RECONCILE-1',
            'gateway_transaction_id' => 'midtrans-reconcile-1',
            'qr_url' => 'https://api.sandbox.midtrans.com/v2/qris/midtrans-reconcile-1/qr-code',
            'expires_at' => now()->addMinutes(15),
        ]);
        $status = [
            'order_id' => $payment->gateway_order_id,
            'transaction_id' => $payment->gateway_transaction_id,
            'payment_type' => 'qris', 'gross_amount' => '79000.00',
            'transaction_status' => 'settlement', 'fraud_status' => 'accept',
        ];

        $this->actingAs($other)->post(route('checkout.check', $payment))->assertNotFound();
        $gatewayStatus = array_replace($status, ['gross_amount' => '79001.00']);
        Http::fake(['api.sandbox.midtrans.com/v2/RA-RECONCILE-1/status' => function () use (&$gatewayStatus) {
            return Http::response($gatewayStatus, 200);
        }]);
        $this->actingAs($teacher)->post(route('checkout.check', $payment))->assertSessionHasErrors('checkout');
        $this->assertSame('pending', $payment->fresh()->status);

        $gatewayStatus = $status;
        $this->post(route('checkout.check', $payment))->assertRedirect(route('checkout.show', $payment));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->subscription_id);
        $this->post(route('checkout.check', $payment))->assertRedirect(route('checkout.show', $payment));
        $this->assertSame(1, $teacher->payments()->where('status', 'paid')->count());
    }
}
