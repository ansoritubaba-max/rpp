<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\User;
use App\Services\Settings\SystemSettings;

class TrialService
{
    public function grant(User $user): void
    {
        $settings = app(SystemSettings::class);
        $days = (int) $settings->get('trial_days');
        if ($days < 1 || $user->subscriptions()->exists()) {
            return;
        }

        $planId = $settings->get('trial_plan_id');
        $plan = $planId ? Plan::query()->whereKey($planId)->where('is_active', true)->first() : null;
        $plan ??= Plan::query()->where('is_active', true)->orderBy('price')->first();
        if (! $plan) {
            return;
        }

        $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays($days),
            'status' => 'active',
        ]);
    }
}
