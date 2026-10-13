<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiContentCache;
use App\Models\AiModel;
use App\Models\AiModelHealth;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Models\Feature;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $logsQuery = AiUsageLog::query();
        $this->applyFilters($logsQuery, $request);

        $stats = (clone $logsQuery)->selectRaw(
            'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(estimated_cost), 0) as estimated_cost, COALESCE(SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END), 0) as cache_hits, COALESCE(AVG(duration_ms), 0) as average_duration',
            ['success', 'failed'],
        )->first();

        $cacheEntries = AiContentCache::query();

        return view('admin.ai.index', [
            'stats' => $stats,
            'logs' => (clone $logsQuery)->with(['user', 'feature', 'provider', 'model'])->latest()->paginate(15)->withQueryString(),
            'models' => AiModel::query()->with(['provider', 'health'])->withCount(['routes', 'routes as active_routes_count' => fn ($query) => $query->where('is_active', true)])->orderBy('priority')->get(),
            'providers' => AiProvider::query()->withCount('models')->orderBy('name')->get(),
            'features' => Feature::query()->where('is_active', true)->orderBy('name')->get(),
            'teachers' => User::query()->where('role', 'guru')->orderBy('name')->get(['id', 'name']),
            'modelSummary' => (clone $logsQuery)->selectRaw('provider_id, model_id, COUNT(*) as request_count, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as error_count, COALESCE(SUM(estimated_cost), 0) as total_cost', ['failed'])
                ->with(['provider:id,name', 'model:id,name'])
                ->groupBy('provider_id', 'model_id')
                ->orderByDesc('request_count')
                ->get(),
            'cacheStats' => (object) [
                'entries' => (clone $cacheEntries)->count(),
                'hits' => (clone $cacheEntries)->sum('hit_count'),
                'expired' => (clone $cacheEntries)->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
            ],
            'topCaches' => AiContentCache::query()->orderByDesc('hit_count')->limit(5)->get(),
        ]);
    }

    public function resetHealth(AiModel $model, AuditService $auditService): RedirectResponse
    {
        $health = $model->health;
        $before = $health?->only(['status', 'last_error', 'failure_count', 'cooldown_until', 'last_success_at']) ?? [];
        $health = AiModelHealth::query()->updateOrCreate(
            ['ai_model_id' => $model->id],
            ['status' => 'unknown', 'failure_count' => 0, 'cooldown_until' => null],
        );
        $auditService->record('ai.health_reset', $model, $before, $health->only(['status', 'failure_count', 'cooldown_until']));

        return back()->with('success', "Cooldown {$model->name} dihapus. Model baru berstatus sehat setelah berhasil menghasilkan dokumen.");
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $period = $request->string('period', '7d')->toString();
        if ($period === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($period === '7d') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($period === '30d') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        $query
            ->when($request->integer('provider'), fn ($builder, $id) => $builder->where('provider_id', $id))
            ->when($request->integer('model'), fn ($builder, $id) => $builder->where('model_id', $id))
            ->when($request->integer('feature'), fn ($builder, $id) => $builder->where('feature_id', $id))
            ->when($request->integer('user'), fn ($builder, $id) => $builder->where('user_id', $id))
            ->when(in_array($request->input('status'), ['success', 'failed'], true), fn ($builder) => $builder->where('status', $request->input('status')));
    }
}
