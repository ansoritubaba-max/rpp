<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function activate(User $user, Plan $plan, CarbonInterface $startsAt, CarbonInterface $expiresAt, ?User $actor = null): Subscription
    {
        $subscription = DB::transaction(function () use ($user, $plan, $startsAt, $expiresAt, $actor) {
            Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->update(['status' => 'cancelled']);

            return $user->subscriptions()->create([
                'plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => $startsAt->isFuture() ? 'pending' : 'active',
                'created_by' => $actor?->id,
            ]);
        }, 3);

        $this->auditService->record('subscription.activated', $subscription, [], [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'expires_at' => $expiresAt->toDateTimeString(),
            'status' => $subscription->status,
        ]);

        return $subscription;
    }

    public function extend(Subscription $subscription, int $days): Subscription
    {
        $before = $subscription->only(['expires_at', 'status']);
        $base = $subscription->expires_at->isFuture() ? $subscription->expires_at : now();
        $subscription->update([
            'expires_at' => $base->copy()->addDays($days),
            'status' => 'active',
        ]);

        $this->auditService->record('subscription.extended', $subscription, $before, $subscription->only(['expires_at', 'status']));

        return $subscription->fresh();
    }
}
