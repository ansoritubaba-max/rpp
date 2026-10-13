<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionStatusSynchronizer
{
    public function run(): array
    {
        $expired = Subscription::query()->whereIn('status', ['active', 'pending'])
            ->where('expires_at', '<=', now())->update(['status' => 'expired']);
        $activated = 0;
        $superseded = 0;

        Subscription::query()->where('status', 'pending')->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())->orderBy('id')->chunkById(100, function ($subscriptions) use (&$activated, &$superseded): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, &$activated, &$superseded): void {
                        $pending = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();
                        if (! $pending || $pending->status !== 'pending' || $pending->starts_at->isFuture() || ! $pending->expires_at->isFuture()) {
                            return;
                        }

                        $newer = Subscription::query()->where('user_id', $pending->user_id)->where('status', 'active')
                            ->where('expires_at', '>', now())->where('starts_at', '>', $pending->starts_at)->exists();
                        if ($newer) {
                            $pending->update(['status' => 'cancelled']);
                            $superseded++;

                            return;
                        }

                        Subscription::query()->where('user_id', $pending->user_id)->where('status', 'active')
                            ->whereKeyNot($pending->id)->update(['status' => 'cancelled']);
                        $pending->update(['status' => 'active']);
                        $activated++;
                    }, 3);
                }
            });

        return compact('expired', 'activated', 'superseded');
    }
}
