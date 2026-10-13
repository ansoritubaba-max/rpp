<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Feature;
use App\Services\Notifications\NotificationService;
use App\Services\Subscription\QuotaService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(QuotaService $quotaService, NotificationService $notifications): View
    {
        $user = request()->user();
        $notifications->syncExpiring($user);
        $quota = $quotaService->summary($user);
        $latestSubscription = $user->subscriptions()->with('plan')
            ->withExists(['payments as has_paid_payment' => fn ($query) => $query->where('status', 'paid')])
            ->latest('expires_at')->first();
        $recentDocuments = Document::query()
            ->with(['feature', 'subject', 'grade'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'quota' => $quota,
            'latestSubscription' => $latestSubscription,
            'isTrialSubscription' => $latestSubscription && $latestSubscription->created_by === null && ! $latestSubscription->has_paid_payment,
            'recentDocuments' => $recentDocuments,
            'documentCount' => $user->documents()->count(),
            'featureCount' => $quota['features']->count(),
            'features' => Feature::query()->where('is_active', true)->get(),
        ]);
    }
}
