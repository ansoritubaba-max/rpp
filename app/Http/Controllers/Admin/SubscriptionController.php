<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSubscriptionRequest;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    public function store(AssignSubscriptionRequest $request, User $teacher, SubscriptionService $subscriptionService): RedirectResponse
    {
        abort_unless($teacher->role === 'guru', 404);
        $subscriptionService->activate(
            $teacher,
            Plan::query()->findOrFail($request->integer('plan_id')),
            Carbon::parse($request->validated('starts_at'))->startOfDay(),
            Carbon::parse($request->validated('expires_at'))->endOfDay(),
            $request->user(),
        );

        return back()->with('success', 'Paket guru berhasil diaktifkan.');
    }

    public function extend(ExtendSubscriptionRequest $request, Subscription $subscription, SubscriptionService $subscriptionService): RedirectResponse
    {
        $subscriptionService->extend($subscription, $request->integer('days'));

        return back()->with('success', 'Masa aktif paket berhasil diperpanjang.');
    }
}
