<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Subscription\MidtransSnapService;
use App\Services\Subscription\QuotaService;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __invoke(QuotaService $quotaService, MidtransSnapService $snap): View
    {
        $user = request()->user();
        $latestSubscription = $user->subscriptions()->with('plan')
            ->withExists(['payments as has_paid_payment' => fn ($query) => $query->where('status', 'paid')])
            ->latest('expires_at')->first();

        return view('subscriptions.index', [
            'quota' => $quotaService->summary($user),
            'latestSubscription' => $latestSubscription,
            'isTrialSubscription' => $latestSubscription && $latestSubscription->created_by === null && ! $latestSubscription->has_paid_payment,
            'payments' => $user->payments()->with('plan')->latest('payment_date')->limit(10)->get(),
            'history' => $user->subscriptions()->with('plan')->latest('starts_at')->limit(10)->get(),
            'plans' => Plan::query()->where('is_active', true)->with('features')->orderBy('sort_order')->orderBy('price')->get(),
            'paymentReady' => $snap->configured(),
        ]);
    }
}
