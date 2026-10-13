<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Backup;
use App\Models\OperationalEvent;
use App\Models\GeneratorAbuseState;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\BusinessKpiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BusinessKpiService $businessKpis): View
    {
        $monthStart = now()->startOfMonth();
        $lastDay = now()->subDay();
        $lastWeek = now()->subDays(7);
        $latestBackup = Backup::query()->latest('started_at')->first();
        $latestSuccessfulBackup = Backup::query()->where('status', 'completed')->latest('completed_at')->first();
        $alerts = [
            'AI gagal' => DB::table('ai_usage_logs')->where('status', 'failed')->where('created_at', '>=', $lastDay)->count(),
            'PDF gagal' => OperationalEvent::query()->where('type', 'pdf_failed')->where('created_at', '>=', $lastDay)->count(),
            'Login dibatasi' => OperationalEvent::query()->where('type', 'login_rate_limited')->where('created_at', '>=', $lastDay)->count(),
            'Generator dibatasi' => OperationalEvent::query()->whereIn('type', ['generator_limited', 'generator_blocked'])->where('created_at', '>=', $lastDay)->count(),
            'Backup gagal' => Backup::query()->where('status', 'failed')->where('started_at', '>=', $lastWeek)->count(),
            'Backup belum tersedia' => $latestSuccessfulBackup ? 0 : 1,
        ];
        $documentActivity = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'label' => $date->locale('id')->translatedFormat('D'),
                'count' => $date->isToday()
                    ? Document::query()->whereDate('created_at', $date)->count()
                    : (DB::table('daily_analytics')->where('date', $date->toDateString())->value('documents_count')
                        ?? Document::query()->whereDate('created_at', $date)->count()),
            ];
        });
        $maxActivity = max(1, (int) $documentActivity->max('count'));
        $kpis = $businessKpis->forDashboard();

        return view('admin.dashboard', [
            'teacherCount' => User::query()->where('role', 'guru')->count(),
            'activeTeacherCount' => User::query()->where('role', 'guru')->where('status', 'active')->count(),
            'subscriptionCount' => Subscription::query()->where('status', 'active')->where('expires_at', '>', now())->count(),
            'expiringCount' => Subscription::query()->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'documentCount' => Document::query()->count(),
            'monthlyRevenue' => $kpis['monthlyRevenue'],
            'monthlyAiCost' => $kpis['monthlyAiCostUsd'],
            'businessKpis' => $kpis,
            'monthlyActiveTeachers' => User::query()->where('role', 'guru')->where('last_login_at', '>=', $monthStart)->count(),
            'monthlyDocuments' => Document::query()->where('created_at', '>=', $monthStart)->count(),
            'newSubscriptions' => Subscription::query()->where('status', 'active')->where('created_at', '>=', $monthStart)->count(),
            'alerts' => $alerts,
            'hasAlerts' => array_sum($alerts) > 0,
            'recentOperationalEvents' => OperationalEvent::query()->with('document:id,uuid,title')->where('created_at', '>=', $lastWeek)->latest('created_at')->limit(6)->get(),
            'generatorWarnings' => GeneratorAbuseState::query()->where('status', 'warning')->where('warning_until', '>', now())->count(),
            'generatorBlocks' => GeneratorAbuseState::query()->where('status', 'temporary_block')->where('blocked_until', '>', now())->count(),
            'recentAiFailures' => DB::table('ai_usage_logs')->where('status', 'failed')->where('created_at', '>=', $lastWeek)->latest('created_at')->limit(3)->get(['error_code', 'created_at']),
            'latestBackup' => $latestBackup,
            'aiStats' => DB::table('ai_usage_logs')->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END), 0) as cache_hits', ['success', 'failed'])->first(),
            'recentTeachers' => User::query()->where('role', 'guru')->with('activeSubscription.plan')->latest()->limit(6)->get(),
            'popularFeatures' => DB::table('documents')->join('features', 'features.id', '=', 'documents.feature_id')->select('features.name', DB::raw('COUNT(documents.id) as total'))->groupBy('features.id', 'features.name')->orderByDesc('total')->limit(5)->get(),
            'documentActivity' => $documentActivity,
            'maxActivity' => $maxActivity,
        ]);
    }
}
