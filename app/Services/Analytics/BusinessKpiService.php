<?php

namespace App\Services\Analytics;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Settings\SystemSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessKpiService
{
    public function __construct(private readonly SystemSettings $settings) {}

    public function forDashboard(): array
    {
        $today = Carbon::now()->toImmutable();
        $monthStart = $today->startOfMonth();
        $previousMonthStart = $today->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->endOfMonth();

        $monthlyRevenue = (float) Payment::query()
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$monthStart->toDateString(), $today->toDateString()])
            ->sum('amount');
        $monthlyAiCostUsd = (float) DB::table('ai_usage_logs')
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $today)
            ->sum('estimated_cost');
        $usdIdrRate = $this->settings->get('ai_usd_idr_rate');
        $usdIdrRate = is_numeric($usdIdrRate) && (float) $usdIdrRate > 0 ? (float) $usdIdrRate : null;
        $monthlyAiCostIdr = $usdIdrRate === null ? null : $monthlyAiCostUsd * $usdIdrRate;
        $aiCostRevenuePercent = $monthlyRevenue > 0 && $monthlyAiCostIdr !== null
            ? round($monthlyAiCostIdr / $monthlyRevenue * 100, 2)
            : null;

        $dueSubscriptions = Subscription::query()
            ->whereBetween('expires_at', [$previousMonthStart, $previousMonthEnd])
            ->whereHas('payments', fn ($query) => $query->where('status', 'paid'))
            ->with(['payments' => fn ($query) => $query->where('status', 'paid')
                ->select(['id', 'subscription_id', 'user_id', 'payment_date'])])
            ->get(['id', 'user_id', 'expires_at']);

        $renewed = 0;
        if ($dueSubscriptions->isNotEmpty()) {
            $laterPayments = Payment::query()
                ->where('status', 'paid')
                ->whereNotNull('subscription_id')
                ->whereIn('user_id', $dueSubscriptions->pluck('user_id')->unique())
                ->whereBetween('payment_date', [
                    $previousMonthStart->subDays(30)->toDateString(),
                    $previousMonthEnd->addDays(7)->toDateString(),
                ])
                ->get(['id', 'user_id', 'subscription_id', 'payment_date'])
                ->groupBy('user_id');

            foreach ($dueSubscriptions as $subscription) {
                $originalPaymentId = $subscription->payments->max('id');
                $from = $subscription->expires_at->copy()->subDays(30)->startOfDay();
                $until = $subscription->expires_at->copy()->addDays(7)->endOfDay();

                if ($laterPayments->get($subscription->user_id, collect())->contains(fn (Payment $payment) =>
                    $payment->id > $originalPaymentId
                    && $payment->subscription_id !== $subscription->id
                    && $payment->payment_date->betweenIncluded($from, $until)
                    && $payment->payment_date->lte($today))) {
                    $renewed++;
                }
            }
        }

        return [
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyAiCostUsd' => $monthlyAiCostUsd,
            'monthlyAiCostIdr' => $monthlyAiCostIdr,
            'usdIdrRate' => $usdIdrRate,
            'aiCostRevenuePercent' => $aiCostRevenuePercent,
            'renewalEligible' => $dueSubscriptions->count(),
            'renewalCount' => $renewed,
            'renewalRatePercent' => $dueSubscriptions->isEmpty()
                ? null
                : round($renewed / $dueSubscriptions->count() * 100, 1),
            'renewalPeriod' => $previousMonthStart->locale('id')->translatedFormat('F Y'),
        ];
    }
}
