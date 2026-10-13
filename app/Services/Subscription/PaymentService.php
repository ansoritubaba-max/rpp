<?php

namespace App\Services\Subscription;

use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly AuditService $auditService,
    ) {}

    public function markPaid(Payment $payment, ?User $admin = null): Payment
    {
        if ($payment->status === 'paid' && $payment->subscription_id) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $admin) {
            $payment = Payment::query()->lockForUpdate()->with(['user', 'plan'])->findOrFail($payment->id);
            if ($payment->status === 'refunded' || ($payment->status === 'paid' && $payment->subscription_id)) {
                return $payment;
            }
            $before = $payment->only(['status', 'subscription_id', 'verified_by', 'verified_at']);
            $subscription = $this->subscriptionService->activate(
                $payment->user,
                $payment->plan,
                now(),
                now()->addDays($payment->plan->duration_days),
                $admin,
            );

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'paid',
                'payment_date' => now()->toDateString(),
                'verified_by' => $admin?->id,
                'verified_at' => now(),
            ]);

            $this->auditService->record('payment.paid', $payment, $before, $payment->only(['status', 'subscription_id', 'verified_by', 'verified_at']));

            return $payment->fresh(['user', 'plan', 'subscription']);
        }, 3);
    }

    public function cancel(Payment $payment, User $admin): Payment
    {
        DB::transaction(function () use ($payment, $admin) {
            $before = $payment->only(['status', 'verified_by', 'verified_at']);
            if ($payment->subscription_id) {
                $payment->subscription()->where('status', 'active')->update(['status' => 'cancelled']);
            }
            $payment->update([
                'status' => 'cancelled',
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);
            $this->auditService->record('payment.cancelled', $payment, $before, $payment->only(['status', 'verified_by', 'verified_at']));
        }, 3);

        return $payment->fresh();
    }

    public function markRefunded(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->gateway === null || $payment->status === 'refunded') {
                return $payment;
            }

            $before = $payment->only(['status', 'subscription_id']);
            $payment->subscription()?->where('status', 'active')->update(['status' => 'cancelled']);
            $payment->update(['status' => 'refunded']);
            $this->auditService->record('payment.refunded', $payment, $before, $payment->only(['status', 'subscription_id']));

            return $payment;
        }, 3);
    }
}
