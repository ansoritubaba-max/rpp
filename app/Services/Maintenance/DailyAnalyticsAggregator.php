<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyAnalyticsAggregator
{
    public function run(Carbon $date): void
    {
        $day = $date->toDateString();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->addDay()->startOfDay();
        $ai = DB::table('ai_usage_logs')->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(estimated_cost), 0) as cost')->first();
        $payments = DB::table('payments')->where('status', 'paid')->whereDate('payment_date', $day)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')->first();

        DB::table('daily_analytics')->updateOrInsert(['date' => $day], [
            'documents_count' => DB::table('documents')->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
            'ai_requests_count' => $ai->total,
            'ai_cost' => $ai->cost,
            'paid_payments_count' => $payments->total,
            'paid_amount' => $payments->amount,
            'updated_at' => now(),
        ]);
    }
}
